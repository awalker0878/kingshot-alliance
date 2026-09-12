<?php

declare(strict_types=1);

namespace App\ReadModels\PlatformAdministration;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Platform\Administration\Services\PlatformAdministratorAuthorization;
use App\Contexts\Platform\Integrations\Queries\IntegrationUsageQuery;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

final readonly class PlatformCatalogueQuery
{
    public function __construct(private AccountIdentityQuery $accounts, private PlatformAdministratorAuthorization $authorization,
        private ScopedCursorCodec $cursors, private IntegrationUsageQuery $integrationUsage) {}

    /** @return array{items:list<array<string,mixed>>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int} */
    public function page(int $actorId, PlatformCatalogueKind $kind, ?string $cursor = null, ?string $allianceId = null, ?string $correlation = null): array
    {
        $this->authorize($actorId);
        $scope = implode('|', ['platform-catalogue', $actorId, $kind->value,
            $kind === PlatformCatalogueKind::Features ? ($allianceId ?? '') : '',
            $kind === PlatformCatalogueKind::CorrelatedAudit ? ($correlation ?? '') : '']);
        $query = match ($kind) {
            PlatformCatalogueKind::Alliances => $this->alliances(),
            PlatformCatalogueKind::Administrators => DB::table('platform_administrators')->leftJoin('users', 'users.id', '=', 'platform_administrators.user_id')
                ->select(['platform_administrators.id', 'platform_administrators.user_id', 'platform_administrators.granted_at', 'platform_administrators.revoked_at', 'users.name', 'users.email', 'users.two_factor_confirmed_at']),
            PlatformCatalogueKind::LegalHolds => DB::table('legal_holds')->whereNull('released_at')->select(['id', 'subject_type', 'subject_id', 'reason', 'placed_at']),
            PlatformCatalogueKind::OutboxFailures => DB::table('outbox_messages')->whereNull('published_at')->whereNotNull('last_error')
                ->select(['id', 'alliance_id', 'event_type', 'aggregate_type', 'aggregate_id', 'attempts', 'available_at', 'occurred_at', new DiagnosticFingerprint('last_error')]),
            PlatformCatalogueKind::WebhookFailures => DB::table('webhook_deliveries')->where('status', 'failed')
                ->select(['id', 'alliance_id', 'event_type', 'attempts', 'response_code', 'updated_at', new DiagnosticFingerprint('last_error', 'response_excerpt')]),
            PlatformCatalogueKind::NotificationFailures => DB::table('notification_deliveries')->where('notification_deliveries.status', DeliveryStatus::Failed->value)
                ->leftJoin('notification_messages', 'notification_messages.id', '=', 'notification_deliveries.notification_message_id')
                ->select(['notification_deliveries.id', 'notification_messages.notification_type', 'notification_deliveries.channel', 'notification_deliveries.attempt_count', 'notification_deliveries.max_attempts', 'notification_deliveries.failed_at', new DiagnosticFingerprint('notification_deliveries.last_error')]),
            PlatformCatalogueKind::FailedJobs => DB::table('failed_jobs')->select(['id', 'uuid', 'queue', 'failed_at', new DiagnosticFingerprint('exception')]),
            PlatformCatalogueKind::CorrelatedAudit => $this->audit($correlation),
            PlatformCatalogueKind::Features => DB::table('alliance_feature_flags')->where('alliance_id', $allianceId)->select(['id', 'feature_key', 'enabled']),
        };
        $idColumn = match ($kind) {
            PlatformCatalogueKind::Administrators => 'platform_administrators.id',
            PlatformCatalogueKind::NotificationFailures => 'notification_deliveries.id',
            default => 'id',
        };
        $page = $this->slice($query, $idColumn, $scope, $cursor, $kind === PlatformCatalogueKind::FailedJobs);
        $page['items'] = $kind === PlatformCatalogueKind::Alliances ? $this->allianceRows($page['items'])
            : array_map(fn (stdClass $row): array => $this->present($kind, $row), $page['items']);

        return $page;
    }

    /** @return array<string,mixed>|null */
    public function alliance(int $actorId, string $allianceId): ?array
    {
        $this->authorize($actorId);
        $row = $this->alliances()->where('alliances.id', $allianceId)->first();

        return $row === null ? null : $this->allianceRows([$row])[0];
    }

    private function authorize(int $actorId): void
    {
        $this->authorization->authorize($this->accounts->require($actorId));
    }

    private function alliances(): Builder
    {
        return DB::table('alliances')->select(['alliances.id', 'alliances.name', 'alliances.slug', 'alliances.status', 'alliances.timezone', 'alliances.retention_until', 'alliances.lifecycle_reason'])
            ->selectSub(DB::table('alliance_memberships')->selectRaw('count(*)')->whereColumn('alliance_memberships.alliance_id', 'alliances.id')->where('status', 'active'), 'active_members_count')
            ->selectSub(DB::table('media_assets')->selectRaw('coalesce(sum(size_bytes), 0)')->whereColumn('media_assets.alliance_id', 'alliances.id'), 'storage_bytes')
            ->selectSub(DB::table('outbox_messages')->selectRaw('count(*)')->whereColumn('outbox_messages.alliance_id', 'alliances.id')->whereNull('published_at'), 'pending_outbox');
    }

    /** @param list<stdClass> $rows
     * @return list<array<string,mixed>>
     */
    private function allianceRows(array $rows): array
    {
        $ids = array_map(static fn (stdClass $row): string => (string) $row->id, $rows);
        if ($ids === []) {
            return [];
        }
        $plans = DB::table('alliance_plan_assignments')->whereIn('alliance_id', $ids)->pluck('plan_code', 'alliance_id');
        $settings = DB::table('alliance_platform_settings')->whereIn('alliance_id', $ids)->get()->keyBy('alliance_id');
        $usage = $this->integrationUsage->forAlliances($ids);

        return array_map(function (stdClass $row) use ($plans, $settings, $usage): array {
            $id = (string) $row->id;
            $setting = $settings->get($id);

            return ['id' => $id, 'name' => (string) $row->name, 'slug' => (string) $row->slug,
                'status' => (string) $row->status, 'timezone' => (string) $row->timezone,
                'activeMembers' => (int) $row->active_members_count, 'storageBytes' => (int) $row->storage_bytes,
                'apiCredentials' => $usage[$id]['activeCredentials'], 'webhooks' => $usage[$id]['activeWebhooks'],
                'pendingOutbox' => (int) $row->pending_outbox, 'plan' => (string) $plans->get($id, 'standard'),
                'retentionDays' => (int) ($setting->retention_days ?? 30), 'queuePartition' => (string) ($setting->queue_partition ?? 'standard'),
                'apiAccessEnabled' => (bool) ($setting->api_access_enabled ?? true), 'webhooksEnabled' => (bool) ($setting->webhooks_enabled ?? true),
                'retentionUntil' => $this->date($row->retention_until), 'lifecycleReason' => $row->lifecycle_reason];
        }, $rows);
    }

    private function audit(?string $correlation): Builder
    {
        $query = DB::table('audit_events')->select(['id', 'event', 'alliance_id', 'subject_type', 'subject_id', 'request_id', 'trace_id', 'created_at']);
        if ($correlation === null || $correlation === '') {
            return $query->whereRaw('1 = 0');
        }
        if (preg_match('/^(?:[0-9a-fA-F]{32}|[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12})$/D', $correlation) !== 1) {
            throw ValidationException::withMessages(['correlation' => 'The correlation identifier is invalid.']);
        }

        return $query->where(strlen($correlation) === 36 ? 'request_id' : 'trace_id', strtolower($correlation));
    }

    /** @return array<string,mixed> */
    private function present(PlatformCatalogueKind $kind, stdClass $row): array
    {
        return match ($kind) {
            PlatformCatalogueKind::Administrators => ['id' => (string) $row->id, 'userId' => (int) $row->user_id,
                'name' => $row->name, 'email' => $row->email, 'mfaEnabled' => $row->two_factor_confirmed_at !== null,
                'grantedAt' => (string) $row->granted_at, 'revokedAt' => $row->revoked_at],
            PlatformCatalogueKind::LegalHolds => ['id' => (string) $row->id, 'subjectType' => (string) $row->subject_type,
                'subjectId' => (string) $row->subject_id, 'reason' => (string) $row->reason, 'placedAt' => $this->date($row->placed_at)],
            PlatformCatalogueKind::OutboxFailures => ['id' => (string) $row->id, 'allianceId' => $row->alliance_id,
                'eventType' => (string) $row->event_type, 'aggregateType' => class_basename((string) $row->aggregate_type),
                'aggregateId' => (string) $row->aggregate_id, 'attempts' => (int) $row->attempts,
                'exhausted' => (int) $row->attempts >= max(1, (int) config('operations.outbox.maximum_attempts', 10)),
                'availableAt' => $this->date($row->available_at), 'occurredAt' => $this->date($row->occurred_at), 'errorFingerprint' => $row->error_fingerprint],
            PlatformCatalogueKind::WebhookFailures => ['id' => (string) $row->id, 'allianceId' => (string) $row->alliance_id,
                'eventType' => (string) $row->event_type, 'attempts' => (int) $row->attempts,
                'responseCode' => $row->response_code === null ? null : (int) $row->response_code,
                'failedAt' => $this->date($row->updated_at), 'errorFingerprint' => $row->error_fingerprint],
            PlatformCatalogueKind::NotificationFailures => ['id' => (string) $row->id, 'notificationType' => $row->notification_type ?? 'unknown',
                'channel' => (string) $row->channel, 'attempts' => (int) $row->attempt_count, 'maxAttempts' => (int) $row->max_attempts,
                'failedAt' => $this->date($row->failed_at), 'errorFingerprint' => $row->error_fingerprint],
            PlatformCatalogueKind::FailedJobs => ['id' => (string) $row->uuid, 'queue' => (string) $row->queue,
                'failedAt' => $this->date($row->failed_at), 'errorFingerprint' => $row->error_fingerprint],
            PlatformCatalogueKind::CorrelatedAudit => ['id' => (string) $row->id, 'event' => (string) $row->event,
                'allianceId' => $row->alliance_id, 'subjectType' => $row->subject_type === null ? null : class_basename((string) $row->subject_type),
                'subjectId' => $row->subject_id, 'requestId' => $row->request_id, 'traceId' => $row->trace_id, 'createdAt' => $this->date($row->created_at)],
            PlatformCatalogueKind::Features => ['key' => (string) $row->feature_key, 'enabled' => (bool) $row->enabled],
            PlatformCatalogueKind::Alliances => throw new \LogicException('Alliance rows require their bounded supporting facts.'),
        };
    }

    private function date(?string $date): ?string
    {
        return $date === null ? null : CarbonImmutable::parse($date)->toIso8601String();
    }

    /** @return array{items:list<stdClass>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int} */
    private function slice(Builder $query, string $idColumn, string $scope, ?string $cursor, bool $numeric): array
    {
        $total = (clone $query)->count();
        $through = $cursor === null ? (clone $query)->max($idColumn) : null;
        if ($through !== null) {
            $through = (string) $through;
        }
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $after = $position['after'] ?? null;
            $through = $position['through'] ?? null;
            if (count($position) !== 2 || ! $this->validId($after, $numeric) || ! $this->validId($through, $numeric)
                || ($numeric ? (int) $after > (int) $through : strcmp($after, $through) > 0)) {
                throw ValidationException::withMessages(['cursor' => 'The Platform catalogue cursor is invalid.']);
            }
            $query->where($idColumn, '<', $after);
        }
        $rows = $through === null ? collect() : $query->where($idColumn, '<=', $through)->orderByDesc($idColumn)->limit(26)->get();
        $page = $rows->take(25);
        $last = $page->last();
        $next = $rows->count() > 25 && $last !== null
            ? $this->cursors->encode($scope, ['after' => (string) $last->id, 'through' => $through]) : null;

        return [...(new PageSlice(array_values($page->all()), $next, 25, $cursor === null))->toArray(), 'total' => $total];
    }

    /** @phpstan-assert-if-true non-empty-string $id */
    private function validId(mixed $id, bool $numeric): bool
    {
        return is_string($id) && ($numeric
            ? preg_match('/^[1-9][0-9]{0,18}$/D', $id) === 1 && strlen($id) <= strlen((string) PHP_INT_MAX) && (strlen($id) < strlen((string) PHP_INT_MAX) || strcmp($id, (string) PHP_INT_MAX) <= 0)
            : preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/Di', $id) === 1);
    }
}
