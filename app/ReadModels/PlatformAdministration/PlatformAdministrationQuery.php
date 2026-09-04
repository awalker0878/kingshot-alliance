<?php

declare(strict_types=1);

namespace App\ReadModels\PlatformAdministration;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\Platform\DataGovernance\Models\LegalHold;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

final class PlatformAdministrationQuery
{
    /** @return array<string, mixed> */
    public function dashboard(?string $correlation = null): array
    {
        $outboxGraceMinutes = max(1, (int) config('operations.launch.outbox_grace_minutes', 15));
        $maximumOutboxAttempts = max(1, (int) config('operations.outbox.maximum_attempts', 10));
        $alliances = Alliance::query()
            ->select('alliances.*')
            ->selectSub(
                DB::table('alliance_memberships')
                    ->selectRaw('count(*)')
                    ->whereColumn('alliance_memberships.alliance_id', 'alliances.id')
                    ->where('alliance_memberships.status', 'active'),
                'active_members_count',
            )
            ->selectSub(
                DB::table('media_assets')
                    ->selectRaw('coalesce(sum(size_bytes), 0)')
                    ->whereColumn('media_assets.alliance_id', 'alliances.id'),
                'storage_bytes',
            )
            ->selectSub(
                DB::table('api_credentials')
                    ->selectRaw('count(*)')
                    ->whereColumn('api_credentials.alliance_id', 'alliances.id')
                    ->whereNull('api_credentials.revoked_at'),
                'active_api_credentials',
            )
            ->selectSub(
                DB::table('webhook_subscriptions')
                    ->selectRaw('count(*)')
                    ->whereColumn('webhook_subscriptions.alliance_id', 'alliances.id')
                    ->where('webhook_subscriptions.is_active', true)
                    ->whereNull('webhook_subscriptions.revoked_at'),
                'active_webhooks',
            )
            ->selectSub(
                DB::table('outbox_messages')
                    ->selectRaw('count(*)')
                    ->whereColumn('outbox_messages.alliance_id', 'alliances.id')
                    ->whereNull('outbox_messages.published_at'),
                'pending_outbox',
            )
            ->orderBy('name')
            ->limit(200)
            ->get();

        $planAssignments = DB::table('alliance_plan_assignments')
            ->pluck('plan_code', 'alliance_id');
        $settings = DB::table('alliance_platform_settings')
            ->get()
            ->keyBy('alliance_id');

        return [
            'metrics' => [
                'alliances' => Alliance::query()->count(),
                'activeAlliances' => Alliance::query()->where('status', AllianceStatus::Active->value)->count(),
                'suspendedAlliances' => Alliance::query()->where('status', AllianceStatus::Suspended->value)->count(),
                'closedAlliances' => Alliance::query()->where('status', AllianceStatus::Closed->value)->count(),
                'deletedAlliances' => Alliance::query()->where('status', AllianceStatus::Deleted->value)->count(),
                'pendingOutbox' => OutboxMessage::query()->whereNull('published_at')->count(),
                'overdueOutbox' => OutboxMessage::query()
                    ->whereNull('published_at')
                    ->where('available_at', '<=', now()->subMinutes($outboxGraceMinutes))
                    ->count(),
                'exhaustedOutbox' => OutboxMessage::query()
                    ->whereNull('published_at')
                    ->where('attempts', '>=', $maximumOutboxAttempts)
                    ->count(),
                'pendingWebhooks' => WebhookDelivery::query()->whereIn('status', ['pending', 'delivering'])->count(),
                'failedWebhooks' => WebhookDelivery::query()->where('status', 'failed')->count(),
                'failedJobs' => DB::table('failed_jobs')->count(),
                'defaultQueue' => Queue::size('default'),
                'notificationsQueue' => Queue::size('notifications'),
                'integrationsQueue' => Queue::size('integrations'),
                'maintenanceQueue' => Queue::size('maintenance'),
                'failedNotifications' => NotificationDelivery::query()
                    ->where('status', DeliveryStatus::Failed->value)
                    ->count(),
            ],
            'alliances' => $alliances->map(static function (Alliance $alliance) use ($planAssignments, $settings): array {
                $setting = $settings->get($alliance->id);

                return [
                    'id' => (string) $alliance->id,
                    'name' => (string) $alliance->name,
                    'slug' => (string) $alliance->slug,
                    'status' => $alliance->status->value,
                    'timezone' => (string) $alliance->timezone,
                    'activeMembers' => (int) $alliance->getAttribute('active_members_count'),
                    'storageBytes' => (int) $alliance->getAttribute('storage_bytes'),
                    'apiCredentials' => (int) $alliance->getAttribute('active_api_credentials'),
                    'webhooks' => (int) $alliance->getAttribute('active_webhooks'),
                    'pendingOutbox' => (int) $alliance->getAttribute('pending_outbox'),
                    'plan' => (string) ($planAssignments->get($alliance->id) ?? 'standard'),
                    'retentionDays' => is_object($setting) ? (int) $setting->retention_days : 30,
                    'queuePartition' => is_object($setting) ? (string) $setting->queue_partition : 'standard',
                    'apiAccessEnabled' => is_object($setting) ? (bool) $setting->api_access_enabled : true,
                    'webhooksEnabled' => is_object($setting) ? (bool) $setting->webhooks_enabled : true,
                    'retentionUntil' => $alliance->retention_until?->toIso8601String(),
                    'lifecycleReason' => $alliance->lifecycle_reason,
                ];
            })->all(),
            'administrators' => DB::table('platform_administrators as administrators')
                ->leftJoin('users', 'users.id', '=', 'administrators.user_id')
                ->orderByDesc('administrators.granted_at')
                ->get([
                    'administrators.id',
                    'administrators.user_id',
                    'administrators.granted_at',
                    'administrators.revoked_at',
                    'users.name',
                    'users.email',
                    'users.two_factor_confirmed_at',
                ])
                ->map(static fn (object $administrator): array => [
                    'id' => (string) $administrator->id,
                    'userId' => (int) $administrator->user_id,
                    'name' => $administrator->name === null ? null : (string) $administrator->name,
                    'email' => $administrator->email === null ? null : (string) $administrator->email,
                    'mfaEnabled' => $administrator->two_factor_confirmed_at !== null,
                    'grantedAt' => (string) $administrator->granted_at,
                    'revokedAt' => $administrator->revoked_at === null ? null : (string) $administrator->revoked_at,
                ])->all(),
            'plans' => DB::table('platform_plans')
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
                ->map(static fn (object $plan): array => [
                    'code' => (string) $plan->code,
                    'name' => (string) $plan->name,
                    'entitlements' => DB::table('platform_plan_entitlements')
                        ->where('plan_code', $plan->code)
                        ->orderBy('entitlement_key')
                        ->pluck('limit_value', 'entitlement_key')
                        ->map(static fn ($value): int => (int) $value)
                        ->all(),
                ])->all(),
            'legalHolds' => LegalHold::query()
                ->whereNull('released_at')
                ->latest('placed_at')
                ->limit(100)
                ->get()
                ->map(static fn (LegalHold $hold): array => [
                    'id' => (string) $hold->id,
                    'subjectType' => (string) $hold->subject_type,
                    'subjectId' => (string) $hold->subject_id,
                    'reason' => (string) $hold->reason,
                    'placedAt' => $hold->placed_at->toIso8601String(),
                ])->all(),
            'diagnostics' => [
                'outboxGraceMinutes' => $outboxGraceMinutes,
                'maximumOutboxAttempts' => $maximumOutboxAttempts,
                'outboxFailures' => OutboxMessage::query()
                    ->whereNull('published_at')
                    ->whereNotNull('last_error')
                    ->orderByDesc('updated_at')
                    ->limit(25)
                    ->get()
                    ->map(fn (OutboxMessage $message): array => [
                        'id' => (string) $message->id,
                        'allianceId' => $message->alliance_id,
                        'eventType' => (string) $message->event_type,
                        'aggregateType' => class_basename((string) $message->aggregate_type),
                        'aggregateId' => (string) $message->aggregate_id,
                        'attempts' => (int) $message->attempts,
                        'exhausted' => $message->attempts >= $maximumOutboxAttempts,
                        'availableAt' => $message->available_at->toIso8601String(),
                        'occurredAt' => $message->occurred_at->toIso8601String(),
                        'errorFingerprint' => $this->fingerprint($message->last_error),
                    ])->all(),
                'webhookFailures' => WebhookDelivery::query()
                    ->where('status', WebhookDeliveryStatus::Failed->value)
                    ->orderByDesc('updated_at')
                    ->limit(25)
                    ->get()
                    ->map(fn (WebhookDelivery $delivery): array => [
                        'id' => (string) $delivery->id,
                        'allianceId' => (string) $delivery->alliance_id,
                        'eventType' => (string) $delivery->event_type,
                        'attempts' => (int) $delivery->attempts,
                        'responseCode' => $delivery->response_code,
                        'failedAt' => $delivery->updated_at?->toIso8601String(),
                        'errorFingerprint' => $this->fingerprint($delivery->last_error ?? $delivery->response_excerpt),
                    ])->all(),
                'notificationFailures' => NotificationDelivery::query()
                    ->where('status', DeliveryStatus::Failed->value)
                    ->orderByDesc('failed_at')
                    ->limit(25)
                    ->get()
                    ->map(function (NotificationDelivery $delivery): array {
                        $message = NotificationMessage::query()
                            ->whereKey($delivery->notification_message_id)
                            ->first();

                        return [
                            'id' => (string) $delivery->id,
                            'notificationType' => $message instanceof NotificationMessage
                                ? (string) $message->notification_type
                                : 'unknown',
                            'channel' => $delivery->channel->value,
                            'attempts' => (int) $delivery->attempt_count,
                            'maxAttempts' => (int) $delivery->max_attempts,
                            'failedAt' => $delivery->failed_at?->toIso8601String(),
                            'errorFingerprint' => $this->fingerprint($delivery->last_error),
                        ];
                    })->all(),
                'failedJobs' => DB::table('failed_jobs')
                    ->orderByDesc('failed_at')
                    ->limit(25)
                    ->get(['uuid', 'queue', 'exception', 'failed_at'])
                    ->map(fn (object $job): array => [
                        'id' => (string) $job->uuid,
                        'queue' => (string) $job->queue,
                        'failedAt' => (string) $job->failed_at,
                        'errorFingerprint' => $this->fingerprint((string) $job->exception),
                    ])->all(),
                'correlation' => $correlation,
                'correlatedAudit' => $this->correlatedAudit($correlation),
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function correlatedAudit(?string $correlation): array
    {
        if ($correlation === null || $correlation === '') {
            return [];
        }

        $query = AuditEvent::query();
        if (strlen($correlation) === 36) {
            $query->where('request_id', strtolower($correlation));
        } else {
            $query->where('trace_id', strtolower($correlation));
        }

        $events = $query
            ->orderBy('created_at')
            ->limit(50)
            ->get();
        $items = [];

        foreach ($events as $event) {
            $items[] = [
                'id' => (string) $event->id,
                'event' => (string) $event->event,
                'allianceId' => $event->alliance_id === null ? null : (string) $event->alliance_id,
                'subjectType' => $event->subject_type === null ? null : class_basename((string) $event->subject_type),
                'subjectId' => $event->subject_id === null ? null : (string) $event->subject_id,
                'requestId' => $event->request_id === null ? null : (string) $event->request_id,
                'traceId' => $event->trace_id === null ? null : (string) $event->trace_id,
                'createdAt' => CarbonImmutable::parse((string) $event->created_at)->toIso8601String(),
            ];
        }

        return $items;
    }

    private function fingerprint(?string $error): ?string
    {
        return $error === null || $error === '' ? null : substr(hash('sha256', $error), 0, 16);
    }
}
