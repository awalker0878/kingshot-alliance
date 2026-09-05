<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\BulkActionResult;
use App\Shared\Infrastructure\Http\BulkItemResult;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class BulkUpdateNotificationInbox
{
    public function __construct(
        private PreviewNotificationInboxBulkAction $preview,
        private UpdateNotificationInboxState $inboxState,
        private AuditRecorder $audit,
    ) {}

    /** @param non-empty-list<string> $messageIds */
    public function handle(
        AuditActor $actor,
        int $recipientUserId,
        ?string $playerId,
        array $messageIds,
        string $operation,
    ): BulkActionResult {
        $preview = $this->preview->handle($recipientUserId, $playerId, $messageIds, $operation);
        $items = [];

        foreach ($preview['items'] as $item) {
            if ($item['outcome'] === 'skipped') {
                $items[] = BulkItemResult::skipped($item['itemId'], $item['label'], $item['code']);

                continue;
            }
            if ($item['outcome'] !== 'ready') {
                $items[] = BulkItemResult::failed($item['itemId'], $item['label'], $item['code']);

                continue;
            }

            try {
                $code = match ($operation) {
                    PreviewNotificationInboxBulkAction::MARK_READ => $this->markRead(
                        $item['itemId'], $recipientUserId, $playerId,
                    ),
                    PreviewNotificationInboxBulkAction::MARK_UNREAD => $this->markUnread(
                        $item['itemId'], $recipientUserId, $playerId,
                    ),
                    PreviewNotificationInboxBulkAction::ARCHIVE => $this->archive(
                        $item['itemId'], $recipientUserId, $playerId,
                    ),
                    PreviewNotificationInboxBulkAction::RESTORE => $this->restore(
                        $item['itemId'], $recipientUserId, $playerId,
                    ),
                    default => 'notification-operation-unsupported',
                };
                $items[] = BulkItemResult::succeeded($item['itemId'], $item['label'], $code);
            } catch (ModelNotFoundException) {
                $items[] = BulkItemResult::failed(
                    $item['itemId'],
                    $item['label'],
                    'notification-unavailable',
                );
            }
        }

        /** @var non-empty-list<BulkItemResult> $items */
        $result = new BulkActionResult('notification-inbox-update', $items);
        $payload = $result->toArray();
        $this->audit->record(
            'notification.messages.bulk_inbox_updated',
            $actor,
            null,
            null,
            [
                'recipient_user_id' => $recipientUserId,
                'player_id' => $playerId,
                'operation' => $operation,
                'message_ids' => $messageIds,
                'succeeded' => $payload['succeeded'],
                'failed' => $payload['failed'],
                'skipped' => $payload['skipped'],
            ],
        );

        return $result;
    }

    private function markRead(string $messageId, int $recipientUserId, ?string $playerId): string
    {
        $this->inboxState->markRead($messageId, $recipientUserId, $playerId);

        return 'notification-marked-read';
    }

    private function markUnread(string $messageId, int $recipientUserId, ?string $playerId): string
    {
        $this->inboxState->markUnread($messageId, $recipientUserId, $playerId);

        return 'notification-marked-unread';
    }

    private function archive(string $messageId, int $recipientUserId, ?string $playerId): string
    {
        $this->inboxState->archive($messageId, $recipientUserId, $playerId);

        return 'notification-archived';
    }

    private function restore(string $messageId, int $recipientUserId, ?string $playerId): string
    {
        $this->inboxState->restore($messageId, $recipientUserId, $playerId);

        return 'notification-restored';
    }
}
