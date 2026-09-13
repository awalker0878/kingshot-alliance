<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Queries;

use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventPhase;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Facts for a currently authorized occurrence; caller retains member/manager admission. */
final readonly class EventPhaseCatalogueQuery
{
    public function __construct(private ScopedCursorCodec $cursors) {}

    /** @return array{items:Collection<int,EventPhase>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int} */
    public function forOccurrence(EventOccurrence $occurrence, string $actorId, ?string $cursor = null): array
    {
        $query = EventPhase::query()->where('occurrence_id', $occurrence->id);
        $total = (clone $query)->count();
        $scope = 'operations.phases.v1:'.$actorId.':'.$occurrence->id;
        $through = $cursor === null ? (clone $query)->max('id') : null;
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $after = $position['after'] ?? null;
            $sort = $position['sort'] ?? null;
            $time = $position['time'] ?? null;
            $through = $position['through'] ?? null;
            if (count($position) !== 4 || ! array_key_exists('time', $position)
                || ! is_string($after) || ! Str::isUlid($after) || ! is_string($through) || ! Str::isUlid($through)
                || strcmp($after, $through) > 0 || ! is_int($sort) || $sort < 0 || $sort > 2147483647
                || ($time !== null && (! is_int($time) || $time < 0 || $time > 253402300799))) {
                throw ValidationException::withMessages(['cursor' => 'The Event phase cursor is invalid.']);
            }
            $start = $time === null ? null : CarbonImmutable::createFromTimestampUTC($time);
            $query->where(static function (Builder $query) use ($sort, $start, $after): void {
                $query->where('sort_order', '>', $sort)->orWhere(static function (Builder $sameSort) use ($sort, $start, $after): void {
                    $sameSort->where('sort_order', $sort)->where(static function (Builder $date) use ($start, $after): void {
                        if ($start === null) {
                            $date->whereNull('starts_at')->where('id', '>', $after);

                            return;
                        }
                        $date->where('starts_at', '>', $start)->orWhereNull('starts_at')
                            ->orWhere(static fn (Builder $equal) => $equal->where('starts_at', $start)->where('id', '>', $after));
                    });
                });
            });
        }
        $rows = $through === null ? new Collection : $query->where('id', '<=', $through)
            ->orderBy('sort_order')->orderBy('starts_at')->orderBy('id')->limit(26)->get();
        $page = $rows->take(25)->values();
        $last = $page->last();
        $next = $rows->count() > 25 && $last instanceof EventPhase
            ? $this->cursors->encode($scope, ['after' => (string) $last->id, 'sort' => $last->sort_order,
                'time' => $last->starts_at?->getTimestamp(), 'through' => $through]) : null;

        return ['items' => $page, 'nextCursor' => $next, 'hasMore' => $next !== null,
            'pageSize' => 25, 'isFirstPage' => $cursor === null, 'total' => $total];
    }
}
