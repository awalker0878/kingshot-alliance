<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Models\NotificationDelivery;

final class UpdateNotificationInboxState
{
    public function markRead(string $deliveryId, int $recipientUserId, ?string $playerId): void
    {
        $this->ownedDelivery($deliveryId, $recipientUserId, $playerId)->update(['read_at' => now()]);
    }

    public function dismiss(string $deliveryId, int $recipientUserId, ?string $playerId): void
    {
        $this->ownedDelivery($deliveryId, $recipientUserId, $playerId)->update([
            'read_at' => now(),
            'dismissed_at' => now(),
        ]);
    }

    private function ownedDelivery(string $deliveryId, int $recipientUserId, ?string $playerId): NotificationDelivery
    {
        return NotificationDelivery::query()
            ->whereKey($deliveryId)
            ->where('recipient_user_id', $recipientUserId)
            ->where(static function ($query) use ($playerId): void {
                $query->whereNull('player_id');
                if ($playerId !== null) {
                    $query->orWhere('player_id', $playerId);
                }
            })
            ->firstOrFail();
    }
}
