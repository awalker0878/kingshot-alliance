<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateNotificationInboxState
{
    public function __construct(private PlayerReferenceQuery $players) {}

    public function markRead(string $messageId, int $recipientUserId, ?string $playerId): void
    {
        $this->update($messageId, $recipientUserId, $playerId, ['read_at' => now()]);
    }

    public function markUnread(string $messageId, int $recipientUserId, ?string $playerId): void
    {
        $this->update($messageId, $recipientUserId, $playerId, ['read_at' => null]);
    }

    public function archive(string $messageId, int $recipientUserId, ?string $playerId): void
    {
        $now = now();
        $this->update($messageId, $recipientUserId, $playerId, [
            'read_at' => $now,
            'archived_at' => $now,
        ]);
    }

    public function restore(string $messageId, int $recipientUserId, ?string $playerId): void
    {
        $this->update($messageId, $recipientUserId, $playerId, ['archived_at' => null]);
    }

    /** @param array<string,mixed> $attributes */
    private function update(
        string $messageId,
        int $recipientUserId,
        ?string $playerId,
        array $attributes,
    ): void {
        DB::transaction(function () use ($messageId, $recipientUserId, $playerId, $attributes): void {
            if ($playerId !== null) {
                $player = $this->players->lockCurrent($playerId);
                if ($player->userId !== $recipientUserId) {
                    throw new ModelNotFoundException('Notification Governor is no longer owned by this account.');
                }
            }

            $this->ownedMessage($messageId, $recipientUserId, $playerId)
                ->forceFill($attributes)
                ->save();
        });
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
            ->lockForUpdate()
            ->firstOrFail();
    }
}
