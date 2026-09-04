<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Queries;

use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class NotificationInboxQuery
{
    public const VIEW_ALL = 'all';

    public const VIEW_UNREAD = 'unread';

    public const VIEW_ARCHIVED = 'archived';

    /** @var list<string> */
    public const VIEWS = [self::VIEW_ALL, self::VIEW_UNREAD, self::VIEW_ARCHIVED];

    public const SCOPE_ALL = 'all';

    public const SCOPE_ACCOUNT = 'account';

    public const SCOPE_GOVERNOR = 'governor';

    /** @var list<string> */
    public const SCOPES = [self::SCOPE_ALL, self::SCOPE_ACCOUNT, self::SCOPE_GOVERNOR];

    /**
     * @param array{
     *   view?: string,
     *   type?: string|null,
     *   scope?: string,
     *   delivery_status?: string|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   cursor?: string|null,
     *   limit?: int
     * } $filters
     * @return array{items:list<array<string,mixed>>,nextCursor:string|null,hasMore:bool}
     */
    public function handle(int $recipientUserId, ?string $playerId, array $filters = []): array
    {
        $view = in_array($filters['view'] ?? null, self::VIEWS, true)
            ? (string) $filters['view']
            : self::VIEW_ALL;
        $scope = in_array($filters['scope'] ?? null, self::SCOPES, true)
            ? (string) $filters['scope']
            : self::SCOPE_ALL;
        $limit = max(1, min(50, (int) ($filters['limit'] ?? 25)));

        $query = NotificationMessage::query()
            ->where('recipient_user_id', $recipientUserId);
        $this->applyScope($query, $playerId, $scope);

        match ($view) {
            self::VIEW_UNREAD => $query->whereNull('archived_at')->whereNull('read_at'),
            self::VIEW_ARCHIVED => $query->whereNotNull('archived_at'),
            default => $query->whereNull('archived_at'),
        };

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $query->where('notification_type', $type);
        }

        $deliveryStatus = trim((string) ($filters['delivery_status'] ?? ''));
        if ($deliveryStatus !== '' && DeliveryStatus::tryFrom($deliveryStatus) instanceof DeliveryStatus) {
            $query->whereExists(static function ($subquery) use ($deliveryStatus): void {
                $subquery->selectRaw('1')
                    ->from('notification_deliveries')
                    ->whereColumn('notification_deliveries.notification_message_id', 'notification_messages.id')
                    ->where('notification_deliveries.status', $deliveryStatus);
            });
        }

        $dateFrom = $this->date($filters['date_from'] ?? null, false);
        if ($dateFrom instanceof CarbonImmutable) {
            $query->where('created_at', '>=', $dateFrom);
        }
        $dateTo = $this->date($filters['date_to'] ?? null, true);
        if ($dateTo instanceof CarbonImmutable) {
            $query->where('created_at', '<=', $dateTo);
        }

        $cursor = $this->decodeCursor($filters['cursor'] ?? null);
        if ($cursor !== null) {
            $query->where(static function (Builder $cursorQuery) use ($cursor): void {
                $cursorQuery
                    ->where('created_at', '<', $cursor['created_at'])
                    ->orWhere(static function (Builder $sameTimestamp) use ($cursor): void {
                        $sameTimestamp
                            ->where('created_at', '=', $cursor['created_at'])
                            ->where('id', '<', $cursor['id']);
                    });
            });
        }

        /** @var Collection<int, NotificationMessage> $messages */
        $messages = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();
        $hasMore = $messages->count() > $limit;
        $messages = $messages->take($limit)->values();
        $deliveries = $this->deliveriesFor($messages->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all());

        $items = $messages->map(function (NotificationMessage $message) use ($deliveries): array {
            $messageDeliveries = $deliveries->get((string) $message->id, collect());
            $statusCounts = [];
            foreach ($messageDeliveries as $delivery) {
                if (! $delivery instanceof NotificationDelivery) {
                    continue;
                }
                $status = $delivery->status->value;
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }

            return [
                'id' => (string) $message->id,
                'type' => (string) $message->notification_type,
                'title' => (string) $message->title,
                'body' => $message->body,
                'actionUrl' => $message->action_url,
                'urgency' => $message->urgency->value,
                'scope' => $message->player_id === null ? self::SCOPE_ACCOUNT : self::SCOPE_GOVERNOR,
                'playerId' => $message->player_id,
                'availableAt' => $message->available_at->toIso8601String(),
                'createdAt' => $message->created_at?->toIso8601String(),
                'readAt' => $message->read_at?->toIso8601String(),
                'archivedAt' => $message->archived_at?->toIso8601String(),
                'deliverySummary' => [
                    'total' => $messageDeliveries->count(),
                    'statuses' => $statusCounts,
                ],
                'deliveries' => $messageDeliveries
                    ->map(static fn (NotificationDelivery $delivery): array => [
                        'id' => (string) $delivery->id,
                        'channel' => $delivery->channel->value,
                        'status' => $delivery->status->value,
                        'targetLabel' => $delivery->route_target_label,
                        'digestCadence' => $delivery->digest_cadence->value,
                        'routingReason' => $delivery->routing_reason,
                        'dueAt' => $delivery->due_at->toIso8601String(),
                        'sentAt' => $delivery->sent_at?->toIso8601String(),
                        'failedAt' => $delivery->failed_at?->toIso8601String(),
                        'nextAttemptAt' => $delivery->next_attempt_at?->toIso8601String(),
                        'attemptCount' => (int) $delivery->attempt_count,
                        'maxAttempts' => (int) $delivery->max_attempts,
                        'lastError' => $delivery->last_error === null
                            ? null
                            : mb_substr((string) $delivery->last_error, 0, 500),
                    ])
                    ->values()
                    ->all(),
            ];
        })->all();

        $last = $messages->last();

        return [
            'items' => $items,
            'nextCursor' => $hasMore && $last instanceof NotificationMessage
                ? $this->encodeCursor($last)
                : null,
            'hasMore' => $hasMore,
        ];
    }

    private function applyScope(Builder $query, ?string $playerId, string $scope): void
    {
        if ($scope === self::SCOPE_ACCOUNT || $playerId === null) {
            $query->whereNull('player_id');

            return;
        }

        if ($scope === self::SCOPE_GOVERNOR) {
            $query->where('player_id', $playerId);

            return;
        }

        $query->where(static function (Builder $scopeQuery) use ($playerId): void {
            $scopeQuery->whereNull('player_id')->orWhere('player_id', $playerId);
        });
    }

    /** @param list<string> $messageIds @return Collection<string, Collection<int, NotificationDelivery>> */
    private function deliveriesFor(array $messageIds): Collection
    {
        if ($messageIds === []) {
            return collect();
        }

        return NotificationDelivery::query()
            ->whereIn('notification_message_id', $messageIds)
            ->orderBy('created_at')
            ->get()
            ->groupBy(static fn (NotificationDelivery $delivery): string => (string) $delivery->notification_message_id);
    }

    private function date(mixed $value, bool $endOfDay): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::parse(trim($value), 'UTC');

            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{created_at:CarbonImmutable,id:string}|null */
    private function decodeCursor(mixed $cursor): ?array
    {
        if (! is_string($cursor) || $cursor === '' || strlen($cursor) > 512) {
            return null;
        }

        $padding = str_repeat('=', (4 - strlen($cursor) % 4) % 4);
        $decoded = base64_decode(strtr($cursor.$padding, '-_', '+/'), true);
        if (! is_string($decoded)) {
            return null;
        }

        try {
            $payload = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($payload)
                || ! is_string($payload['created_at'] ?? null)
                || ! is_string($payload['id'] ?? null)) {
                return null;
            }

            return [
                'created_at' => CarbonImmutable::parse($payload['created_at'], 'UTC'),
                'id' => $payload['id'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function encodeCursor(NotificationMessage $message): string
    {
        $json = json_encode([
            'created_at' => $message->created_at?->toIso8601String(),
            'id' => (string) $message->id,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
