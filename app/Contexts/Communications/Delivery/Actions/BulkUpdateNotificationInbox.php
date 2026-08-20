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

    /** @param non-empty-list<string> $deliveryIds */
    public function handle(
        AuditActor $actor,
        int $recipientUserId,
        ?string $playerId,
        array $deliveryIds,
        string $operation,
    ): BulkActionResult {
        $preview = $this->preview->handle($recipientUserId, $playerId, $deliveryIds, $operation);
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
                if ($operation === PreviewNotificationInboxBulkAction::MARK_READ) {
                    $this->inboxState->markRead($item['itemId'], $recipientUserId, $playerId);
                    $code = 'notification-marked-read';
                } else {
                    $this->inboxState->dismiss($item['itemId'], $recipientUserId, $playerId);
                    $code = 'notification-dismissed';
                }

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
            'notification.deliveries.bulk_inbox_updated',
            $actor,
            null,
            null,
            [
                'recipient_user_id' => $recipientUserId,
                'player_id' => $playerId,
                'operation' => $operation,
                'delivery_ids' => $deliveryIds,
                'succeeded' => $payload['succeeded'],
                'failed' => $payload['failed'],
                'skipped' => $payload['skipped'],
            ],
        );

        return $result;
    }
}
