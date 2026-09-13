<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class EventOccurrenceCatalogueQuery
{
    public function __construct(private EventCalendarQuery $events, private ScopedCursorCodec $cursors) {}

    /** @return array{event:Event,items:Collection<int,EventOccurrence>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int} */
    public function forEvent(PlayerReference $actor, string $eventId, ?string $cursor = null): array
    {
        $event = $this->events->eventForManage($actor, $eventId);
        $query = EventOccurrence::query()->where('event_id', $event->id);
        $total = (clone $query)->count();
        $scope = 'operations.event-occurrences.v1:'.$actor->playerId.':'.$event->id;
        $through = $cursor === null ? (clone $query)->max('id') : null;
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $after = $position['after'] ?? null;
            $time = $position['time'] ?? null;
            $through = $position['through'] ?? null;
            if (count($position) !== 3 || ! is_string($after) || ! Str::isUlid($after)
                || ! is_string($through) || ! Str::isUlid($through) || strcmp($after, $through) > 0
                || ! is_int($time) || $time < 0 || $time > 253402300799) {
                throw ValidationException::withMessages(['cursor' => 'The Event occurrence cursor is invalid.']);
            }
            $start = CarbonImmutable::createFromTimestampUTC($time);
            $query->where(static fn (Builder $query) => $query->where('starts_at', '<', $start)
                ->orWhere(static fn (Builder $query) => $query->where('starts_at', $start)->where('id', '<', $after)));
        }
        $rows = $through === null ? new Collection : $query->where('id', '<=', $through)
            ->orderByDesc('starts_at')->orderByDesc('id')->limit(26)->get();
        $page = $rows->take(25)->values();
        $last = $page->last();
        $next = $rows->count() > 25 && $last instanceof EventOccurrence
            ? $this->cursors->encode($scope, ['after' => (string) $last->id, 'time' => $last->starts_at->getTimestamp(), 'through' => $through]) : null;

        return ['event' => $event, 'items' => $page, 'nextCursor' => $next, 'hasMore' => $next !== null,
            'pageSize' => 25, 'isFirstPage' => $cursor === null, 'total' => $total];
    }
}
