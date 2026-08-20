<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Actions;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\BulkActionResult;
use App\Shared\Infrastructure\Http\BulkItemResult;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class BulkCancelEvents
{
    public function __construct(
        private PreviewEventBulkCancellation $preview,
        private CancelEvent $cancel,
        private AuditRecorder $audit,
    ) {}

    /** @param non-empty-list<string> $eventIds */
    public function handle(PlayerReference $actor, array $eventIds): BulkActionResult
    {
        $preview = $this->preview->handle($actor, $eventIds);
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
                $this->cancel->handle($actor->playerId, $item['itemId']);
                $items[] = BulkItemResult::succeeded($item['itemId'], $item['label'], 'event-cancelled');
            } catch (AuthorizationException|ModelNotFoundException) {
                $items[] = BulkItemResult::failed($item['itemId'], $item['label'], 'event-unavailable');
            }
        }

        /** @var non-empty-list<BulkItemResult> $items */
        $result = new BulkActionResult('event-cancellation', $items);
        $payload = $result->toArray();
        $this->audit->record('event.events.bulk_cancelled', $actor, metadata: [
            'event_ids' => $eventIds,
            'succeeded' => $payload['succeeded'],
            'failed' => $payload['failed'],
            'skipped' => $payload['skipped'],
        ]);

        return $result;
    }
}
