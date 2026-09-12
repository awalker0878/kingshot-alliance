<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Platform\Integrations\Enums\IntegrationCatalogueKind;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Contexts\Platform\Integrations\Policies\IntegrationRuntimePolicy;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final readonly class IntegrationManagementQuery
{
    public function __construct(private AllianceAuthorization $authorization, private ScopedCursorCodec $cursors, private IntegrationRuntimePolicy $availability) {}

    /** @return array{items:list<array<string,mixed>>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int} */
    public function page(string $actorId, string $allianceId, IntegrationCatalogueKind $kind, ?string $cursor = null): array
    {
        if (! $this->authorization->allows($actorId, $allianceId, AlliancePermission::Manage)) {
            throw new AuthorizationException;
        }
        $scope = implode('|', ['integration-catalogue', $actorId, $allianceId, $kind->value]);

        return match ($kind) {
            IntegrationCatalogueKind::Credentials => $this->slice(
                ApiCredential::query()->where('alliance_id', $allianceId)
                    ->select(['id', 'name', 'prefix', 'scopes', 'expires_at', 'last_used_at', 'revoked_at']),
                $scope, $cursor, static fn (ApiCredential $credential): array => [
                    'id' => (string) $credential->id, 'name' => (string) $credential->name,
                    'prefix' => (string) $credential->prefix, 'scopes' => $credential->scopes,
                    'active' => $credential->active(),
                    'expiresAt' => $credential->expires_at?->toIso8601String(),
                    'lastUsedAt' => $credential->last_used_at?->toIso8601String(),
                    'revokedAt' => $credential->revoked_at?->toIso8601String(),
                ],
            ),
            IntegrationCatalogueKind::Webhooks => $this->slice(
                WebhookSubscription::query()->where('alliance_id', $allianceId)
                    ->select(['id', 'name', 'url', 'events', 'is_active', 'secret_rotated_at', 'revoked_at']),
                $scope, $cursor, static fn (WebhookSubscription $subscription): array => [
                    'id' => (string) $subscription->id, 'name' => (string) $subscription->name,
                    'url' => (string) $subscription->url, 'events' => $subscription->events,
                    'active' => (bool) $subscription->is_active,
                    'secretRotatedAt' => $subscription->secret_rotated_at?->toIso8601String(),
                    'revokedAt' => $subscription->revoked_at?->toIso8601String(),
                ],
            ),
            IntegrationCatalogueKind::Deliveries => $this->deliveries($allianceId, $scope, $cursor),
        };
    }

    /** @return array{items:list<array<string,mixed>>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int} */
    private function deliveries(string $allianceId, string $scope, ?string $cursor): array
    {
        $enabled = $this->availability->allowsWebhooks($allianceId);

        return $this->slice(WebhookDelivery::query()->where('alliance_id', $allianceId)
            ->select(['id', 'webhook_subscription_id', 'event_type', 'status', 'attempts', 'response_code', 'last_error', 'last_attempt_at', 'delivered_at'])
            ->selectRaw('payload IS NOT NULL AS payload_available')
            ->with(['subscription' => static fn ($query) => $query->where('alliance_id', $allianceId)->select(['id', 'name', 'is_active', 'revoked_at'])]),
            $scope, $cursor, static fn (WebhookDelivery $delivery): array => [
                'id' => (string) $delivery->id, 'subscriptionId' => (string) $delivery->webhook_subscription_id,
                'subscriptionName' => $delivery->subscription?->name,
                'event' => (string) $delivery->event_type, 'status' => $delivery->status->value,
                'attempts' => (int) $delivery->attempts, 'responseCode' => $delivery->response_code,
                'lastError' => $delivery->last_error,
                'lastAttemptAt' => $delivery->last_attempt_at?->toIso8601String(),
                'deliveredAt' => $delivery->delivered_at?->toIso8601String(),
                'canRetry' => $enabled && $delivery->status === WebhookDeliveryStatus::Failed
                    && (bool) $delivery->getAttribute('payload_available')
                    && $delivery->subscription?->is_active && $delivery->subscription->revoked_at === null,
            ],
        );
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @param  callable(T):array<string,mixed>  $present
     * @return array{items:list<array<string,mixed>>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int}
     */
    private function slice(Builder $query, string $scope, ?string $cursor, callable $present): array
    {
        $total = (clone $query)->count();
        $through = $cursor === null ? (clone $query)->max('id') : null;
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $after = $position['after'] ?? null;
            $through = $position['through'] ?? null;
            if (count($position) !== 2 || ! $this->isId($after) || ! $this->isId($through) || strcmp($after, $through) > 0) {
                throw ValidationException::withMessages(['cursor' => 'The integration catalogue cursor is invalid.']);
            }
            $query->where('id', '<', $after);
        }
        $rows = $through === null ? collect() : $query->where('id', '<=', $through)->orderByDesc('id')->limit(26)->get();
        $page = $rows->take(25);
        $last = $page->last();
        $next = $rows->count() > 25 && $last !== null
            ? $this->cursors->encode($scope, ['after' => (string) $last->getKey(), 'through' => $through]) : null;
        $items = [];
        foreach ($page as $row) {
            $items[] = $present($row);
        }

        return [...(new PageSlice($items, $next, 25, $cursor === null))->toArray(), 'total' => $total];
    }

    /** @phpstan-assert-if-true non-empty-string $id */
    private function isId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/Di', $id) === 1;
    }
}
