<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Models\NotificationMessage;

final class UpdateNotificationInboxState
{
    public function markRead(string $messageId, int $recipientUserId, ?string $playerId): void
    {
        $this->ownedMessage($messageId, $recipientUserId, $playerId)->update(['read_at' => now()]);
    }

    public function markUnread(string $messageId, int $recipientUserId, ?string $playerId): void
    {
        $this->ownedMessage($messageId, $recipientUserId, $playerId)->update(['read_at' => null]);
    }

    public function archive(string $messageId, int $recipientUserId, ?string $playerId): void
    {
        $this->ownedMessage($messageId, $recipientUserId, $playerId)->update([
            'read_at' => now(),
            'archived_at' => now(),
        ]);
    }

    public function restore(string $messageId, int $recipientUserId, ?string $playerId): void
    {
        $this->ownedMessage($messageId, $recipientUserId, $playerId)->update(['archived_at' => null]);
    }

    private function ownedMessage(string $messageId, int $recipientUserId, ?string $playerId): NotificationMessage
    {
        return NotificationMessage::query()
            ->whereKey($messageId)
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
