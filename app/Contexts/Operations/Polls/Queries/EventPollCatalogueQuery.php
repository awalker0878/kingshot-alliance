<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Polls\Queries;

use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Facts for a currently authorized occurrence; caller retains member/manager admission. */
final readonly class EventPollCatalogueQuery
{
    public function __construct(private ScopedCursorCodec $cursors) {}

    /** @return array{items:Collection<int,EventPoll>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int} */
    public function forOccurrence(EventOccurrence $occurrence, string $actorId, bool $manager, ?string $cursor = null): array
    {
        $query = EventPoll::query()->where('occurrence_id', $occurrence->id);
        if (! $manager) {
            $query->whereIn('status', [EventPollStatus::Open->value, EventPollStatus::Closed->value]);
        }
        $total = (clone $query)->count();
        $scope = 'operations.polls.v1:'.$actorId.':'.$occurrence->id.':'.($manager ? 'manage' : 'view');
        $through = $cursor === null ? (clone $query)->max('id') : null;
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $after = $position['after'] ?? null;
            $through = $position['through'] ?? null;
            if (count($position) !== 2 || ! is_string($after) || ! Str::isUlid($after)
                || ! is_string($through) || ! Str::isUlid($through) || strcmp($after, $through) > 0) {
                throw ValidationException::withMessages(['cursor' => 'The Event poll cursor is invalid.']);
            }
            $query->where('id', '>', $after);
        }
        $rows = $through === null ? new Collection : $query->where('id', '<=', $through)->orderBy('id')->limit(26)->get();
        $page = $rows->take(25)->values();
        $last = $page->last();
        $next = $rows->count() > 25 && $last instanceof EventPoll
            ? $this->cursors->encode($scope, ['after' => (string) $last->id, 'through' => $through]) : null;

        return ['items' => $page, 'nextCursor' => $next, 'hasMore' => $next !== null,
            'pageSize' => 25, 'isFirstPage' => $cursor === null, 'total' => $total];
    }
}
