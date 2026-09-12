<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomGovernance\Queries;

use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final readonly class GovernanceCataloguePage
{
    public function __construct(private ScopedCursorCodec $cursors) {}

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @return array{items:list<T>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int}
     */
    public function slice(Builder $query, string $scope, ?string $cursor): array
    {
        $id = $query->getModel()->qualifyColumn('id');
        $total = (clone $query)->count();
        $through = $cursor === null ? (clone $query)->max($id) : null;
        if ($cursor !== null) {
            if (strlen($cursor) > 4096) {
                throw $this->invalid();
            }
            $position = $this->cursors->decode($cursor, $scope);
            $after = $position['after'] ?? null;
            $through = $position['through'] ?? null;
            if (count($position) !== 2 || ! $this->isId($after) || ! $this->isId($through) || strcmp($after, $through) > 0) {
                throw $this->invalid();
            }
            $query->where($id, '<', $after);
        }
        $rows = $through === null ? collect() : $query->where($id, '<=', $through)->orderByDesc($id)->limit(26)->get();
        $items = $rows->take(25)->values();
        $last = $items->last();
        $next = $rows->count() > 25 && $last !== null
            ? $this->cursors->encode($scope, ['after' => (string) $last->getKey(), 'through' => $through]) : null;

        return [...(new PageSlice(array_values($items->all()), $next, 25, $cursor === null))->toArray(), 'total' => $total];
    }

    /** @phpstan-assert-if-true non-empty-string $id */
    public function isId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/Di', $id) === 1;
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages(['cursor' => 'The Governance page cursor is invalid or belongs to another view.']);
    }
}
