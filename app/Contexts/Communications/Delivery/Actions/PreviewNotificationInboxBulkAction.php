<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use InvalidArgumentException;

final class PreviewNotificationInboxBulkAction
{
    public const MARK_READ = 'mark_read';

    public const MARK_UNREAD = 'mark_unread';

    public const ARCHIVE = 'archive';

    public const RESTORE = 'restore';

    /** @var list<string> */
    public const OPERATIONS = [self::MARK_READ, self::MARK_UNREAD, self::ARCHIVE, self::RESTORE];

    /**
     * @param  non-empty-list<string>  $messageIds
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
        array $messageIds,
        string $operation,
    ): array {
        if (! in_array($operation, self::OPERATIONS, true)) {
            throw new InvalidArgumentException('Notification inbox bulk operation is unsupported.');
        }

        $messages = NotificationMessage::query()
            ->where('recipient_user_id', $recipientUserId)
            ->whereIn('id', $messageIds)
            ->where(static function ($query) use ($playerId): void {
                $query->whereNull('player_id');
                if ($playerId !== null) {
                    $query->orWhere('player_id', $playerId);
                }
            })
            ->get()
            ->keyBy(static fn (NotificationMessage $message): string => (string) $message->id);
        $items = [];
        $readyItemIds = [];

        foreach ($messageIds as $messageId) {
            $message = $messages->get($messageId);
            if (! $message instanceof NotificationMessage) {
                $items[] = $this->item($messageId, $messageId, null, 'blocked', 'notification-unavailable');

                continue;
            }

            $fromStatus = $message->archived_at !== null
                ? 'archived'
                : ($message->read_at !== null ? 'read' : 'unread');
            $skipCode = match ($operation) {
                self::MARK_READ => $message->read_at !== null ? 'already-read' : null,
                self::MARK_UNREAD => $message->read_at === null ? 'already-unread' : null,
                self::ARCHIVE => $message->archived_at !== null ? 'already-archived' : null,
                self::RESTORE => $message->archived_at === null ? 'already-restored' : null,
                default => null,
            };

            if ($skipCode !== null) {
                $items[] = $this->item($messageId, (string) $message->title, $fromStatus, 'skipped', $skipCode);
            } else {
                $items[] = $this->item($messageId, (string) $message->title, $fromStatus, 'ready', 'ready');
                $readyItemIds[] = $messageId;
            }
        }

        return [
            'operation' => $operation,
            'items' => $items,
            'ready' => count($readyItemIds),
            'blocked' => count($messageIds) - count($readyItemIds),
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
