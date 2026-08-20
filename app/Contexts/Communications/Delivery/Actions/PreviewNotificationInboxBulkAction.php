<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use InvalidArgumentException;

final class PreviewNotificationInboxBulkAction
{
    public const MARK_READ = 'mark_read';

    public const DISMISS = 'dismiss';

    /**
     * @param non-empty-list<string> $deliveryIds
     * @return array{
     *   operation: string,
     *   items: non-empty-list<array{itemId: string, label: string, fromStatus: string|null, outcome: string, code: string}>,
     *   ready: int,
     *   blocked: int,
     *   readyItemIds: list<string>
     * }
     */
    public function handle(
        int $recipientUserId,
        ?string $playerId,
        array $deliveryIds,
        string $operation,
    ): array {
        if (! in_array($operation, [self::MARK_READ, self::DISMISS], true)) {
            throw new InvalidArgumentException('Notification inbox bulk operation is unsupported.');
        }

        $deliveries = NotificationDelivery::query()
            ->where('recipient_user_id', $recipientUserId)
            ->whereIn('id', $deliveryIds)
            ->where(static function ($query) use ($playerId): void {
                $query->whereNull('player_id');
                if ($playerId !== null) {
                    $query->orWhere('player_id', $playerId);
                }
            })
            ->get()
            ->keyBy(static fn (NotificationDelivery $delivery): string => (string) $delivery->id);
        $items = [];
        $readyItemIds = [];

        foreach ($deliveryIds as $deliveryId) {
            $delivery = $deliveries->get($deliveryId);
            if (! $delivery instanceof NotificationDelivery) {
                $items[] = $this->item($deliveryId, $deliveryId, null, 'blocked', 'notification-unavailable');
                continue;
            }

            $metadata = is_array($delivery->metadata) ? $delivery->metadata : [];
            $label = is_string($metadata['title'] ?? null) ? $metadata['title'] : 'Notification';
            $fromStatus = $delivery->dismissed_at !== null
                ? 'dismissed'
                : ($delivery->read_at !== null ? 'read' : 'unread');

            if ($delivery->dismissed_at !== null) {
                $items[] = $this->item($deliveryId, $label, $fromStatus, 'skipped', 'already-dismissed');
            } elseif ($operation === self::MARK_READ && $delivery->read_at !== null) {
                $items[] = $this->item($deliveryId, $label, $fromStatus, 'skipped', 'already-read');
            } else {
                $items[] = $this->item($deliveryId, $label, $fromStatus, 'ready', 'ready');
                $readyItemIds[] = $deliveryId;
            }
        }

        return [
            'operation' => $operation,
            'items' => $items,
            'ready' => count($readyItemIds),
            'blocked' => count($deliveryIds) - count($readyItemIds),
            'readyItemIds' => $readyItemIds,
        ];
    }

    /** @return array{itemId: string, label: string, fromStatus: string|null, outcome: string, code: string} */
    private function item(
        string $itemId,
        string $label,
        ?string $fromStatus,
        string $outcome,
        string $code,
    ): array {
        return [
            'itemId' => $itemId,
            'label' => $label,
            'fromStatus' => $fromStatus,
            'outcome' => $outcome,
            'code' => $code,
        ];
    }
}
