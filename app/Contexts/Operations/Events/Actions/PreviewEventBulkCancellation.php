<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Actions;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventStatus;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

final readonly class PreviewEventBulkCancellation
{
    public function __construct(private EventCalendarQuery $events) {}

    /**
     * @param non-empty-list<string> $eventIds
     * @return array{
     *   operation: string,
     *   items: non-empty-list<array{itemId: string, label: string, fromStatus: string|null, outcome: string, code: string}>,
     *   ready: int,
     *   blocked: int,
     *   readyItemIds: list<string>
     * }
     */
    public function handle(PlayerReference $actor, array $eventIds): array
    {
        $items = [];
        $readyItemIds = [];

        foreach ($eventIds as $eventId) {
            try {
                $event = $this->events->eventForManage($actor, $eventId);
            } catch (AuthorizationException|ModelNotFoundException) {
                $items[] = $this->item($eventId, $eventId, null, 'blocked', 'event-unavailable');
                continue;
            }

            $label = $this->label($event);
            if ($event->status === EventStatus::Cancelled) {
                $items[] = $this->item($eventId, $label, $event->status, 'skipped', 'already-cancelled');
            } elseif ($event->status === EventStatus::Completed) {
                $items[] = $this->item($eventId, $label, $event->status, 'blocked', 'event-completed');
            } else {
                $items[] = $this->item($eventId, $label, $event->status, 'ready', 'ready');
                $readyItemIds[] = $eventId;
            }
        }

        return [
            'operation' => 'cancel',
            'items' => $items,
            'ready' => count($readyItemIds),
            'blocked' => count($eventIds) - count($readyItemIds),
            'readyItemIds' => $readyItemIds,
        ];
    }

    private function label(Event $event): string
    {
        if (is_string($event->title) && trim($event->title) !== '') {
            return trim($event->title);
        }

        return Str::headline((string) $event->eventType->slug);
    }

    /** @return array{itemId: string, label: string, fromStatus: string|null, outcome: string, code: string} */
    private function item(
        string $itemId,
        string $label,
        ?EventStatus $from,
        string $outcome,
        string $code,
    ): array {
        return [
            'itemId' => $itemId,
            'label' => $label,
            'fromStatus' => $from?->value,
            'outcome' => $outcome,
            'code' => $code,
        ];
    }
}
