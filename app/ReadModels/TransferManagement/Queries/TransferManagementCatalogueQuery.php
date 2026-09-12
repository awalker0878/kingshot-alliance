<?php

declare(strict_types=1);

namespace App\ReadModels\TransferManagement\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomCapacityObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\ReadModels\TransferManagement\Enums\TransferCatalogueKind;
use App\ReadModels\TransferManagement\Presenters\TransferManagementPresenter;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Read-only management catalogues. Domain mutations and eligibility stay with KingdomTransfers. */
final readonly class TransferManagementCatalogueQuery
{
    public function __construct(private TransferAuthorization $authorization, private ScopedCursorCodec $cursors, private TransferManagementPresenter $presenter) {}

    /** @return array{items:list<array<string,mixed>>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int} */
    public function page(string $actorId, string $allianceId, TransferCatalogueKind $kind, ?string $planId = null, ?string $cursor = null): array
    {
        if (! $this->authorization->allows($actorId, $allianceId, TransferPermission::Manage)) {
            throw new AuthorizationException;
        }
        $plan = $planId === null ? null : TransferPlan::query()->where('alliance_id', $allianceId)->whereKey($planId)->firstOrFail();
        $scope = implode('|', ['transfer-catalogue', $actorId, $allianceId, $kind->value, $planId ?? '']);
        if ($plan === null && ! in_array($kind, [TransferCatalogueKind::Windows, TransferCatalogueKind::Plans], true)) {
            if ($cursor !== null) {
                throw ValidationException::withMessages(['cursor' => 'This catalogue requires a selected plan.']);
            }

            return [...(new PageSlice([], null, 25))->toArray(), 'total' => 0];
        }

        return match ($kind) {
            TransferCatalogueKind::Windows => $this->slice(TransferWindow::query()->where('alliance_id', $allianceId), $scope, $cursor, $this->presenter->window(...)),
            TransferCatalogueKind::Plans => $this->slice(TransferPlan::query()->where('alliance_id', $allianceId)->with(['homeKingdom', 'window']), $scope, $cursor, $this->presenter->plan(...)),
            TransferCatalogueKind::Groups => $this->slice(TransferGroup::query()->where('alliance_id', $allianceId)->where('transfer_window_id', $plan?->transfer_window_id)->withCount('kingdoms'), $scope, $cursor, $this->presenter->officialGroupSummary(...)),
            TransferCatalogueKind::Conditions => $this->slice(TransferKingdomConditionObservation::query()->where('alliance_id', $allianceId)->where('transfer_window_id', $plan?->transfer_window_id)->with('kingdom:id,number'), $scope, $cursor, $this->presenter->condition(...)),
            TransferCatalogueKind::Capacities => $this->slice(TransferKingdomCapacityObservation::query()->where('alliance_id', $allianceId)->where('transfer_window_id', $plan?->transfer_window_id)->with('kingdom:id,number'), $scope, $cursor, $this->presenter->capacity(...)),
            TransferCatalogueKind::Cohorts => $this->slice(TransferCohort::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)->with(['coordinator:id,current_name', 'destinationKingdom:id,number']), $scope, $cursor, fn (TransferCohort $row): array => $this->presenter->cohort($row, true)),
        };
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @param  callable(T):array<string,mixed>  $present
     * @return array{items:list<array<string,mixed>>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int}
     */
    private function slice(Builder $query, string $scope, ?string $cursor, callable $present): array
    {
        $total = (clone $query)->count();
        $through = $cursor === null ? (clone $query)->max('id') : null;
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $after = $position['after'] ?? null;
            $through = $position['through'] ?? null;
            if (count($position) !== 2 || ! $this->isId($after) || ! $this->isId($through) || strcmp($after, $through) > 0) {
                throw ValidationException::withMessages(['cursor' => 'The catalogue cursor is invalid.']);
            }
            $query->where('id', '>', $after);
        }
        $rows = $through === null ? collect() : $query->where('id', '<=', $through)->orderBy('id')->limit(26)->get();
        $page = $rows->take(25);
        $last = $page->last();
        $next = $rows->count() > 25 && $last !== null
            ? $this->cursors->encode($scope, ['after' => (string) $last->getKey(), 'through' => $through]) : null;
        $items = [];
        foreach ($page as $row) {
            $items[] = $present($row);
        }

        return [...(new PageSlice($items, $next, 25, $cursor === null))->toArray(), 'total' => $total];
    }

    /** @phpstan-assert-if-true non-empty-string $id */
    private function isId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/Di', $id) === 1;
    }
}
