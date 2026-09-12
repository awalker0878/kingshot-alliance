<?php

declare(strict_types=1);

namespace App\ReadModels\TransferManagement\Queries;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\ReadModels\TransferManagement\Enums\TransferChoiceKind;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

/** Bounded, authorized read composition. Options never authorize the later mutation. */
final readonly class TransferManagementChoiceQuery
{
    public const int PAGE_SIZE = 25;

    public function __construct(
        private TransferAuthorization $authorization,
        private AllianceReferenceQuery $alliances,
        private ScopedCursorCodec $cursors,
    ) {}

    /** @return array{page:array{items:list<array{id:string,name:string}>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool},total:int,selected:?array{id:string,name:string}} */
    public function page(string $actorPlayerId, string $allianceId, TransferChoiceKind $kind, ?string $planId = null, string $search = '', ?string $cursor = null, ?string $selectedId = null): array
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, TransferPermission::Manage)) {
            throw new AuthorizationException;
        }
        $search = trim($search);
        if (mb_strlen($search) > 160 || ($selectedId !== null && ! $this->isId($selectedId))) {
            throw ValidationException::withMessages(['q' => 'The transfer choice request is invalid.']);
        }
        if ($kind === TransferChoiceKind::Windows) {
            if ($planId !== null) {
                throw ValidationException::withMessages(['plan' => 'Window choices are independent of an existing plan.']);
            }
        } else {
            if ($planId === null || ! $this->isId($planId)) {
                throw ValidationException::withMessages(['plan' => 'A current mutable transfer plan is required.']);
            }
            // Re-check the exact current plan, not a previously selected client's scope.
            TransferPlan::query()->where('alliance_id', $allianceId)->whereKey($planId)
                ->where('home_kingdom_id', $this->alliances->require($allianceId)->kingdomId)
                ->whereIn('state', [TransferPlanState::Draft->value, TransferPlanState::Open->value])
                ->firstOrFail(['id']);
        }
        $base = match ($kind) {
            TransferChoiceKind::Windows => DB::table('transfer_windows')->where('alliance_id', $allianceId)->select(['id', 'label as name']),
            TransferChoiceKind::Coordinators => DB::table('alliance_memberships as membership')
                ->join('players as player', 'player.id', '=', 'membership.player_id')
                ->where('membership.alliance_id', $allianceId)->where('membership.status', MembershipStatus::Active->value)
                ->select(['player.id as id', 'player.current_name as name']),
            TransferChoiceKind::Roster => DB::table('alliance_roster_entries')->where('alliance_id', $allianceId)
                ->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value])->select(['id', 'observed_name as name']),
        };
        $choices = DB::query()->fromSub($base, 'transfer_choices');
        // An off-page selection is resolved against current owner scope, not the search.
        $selected = $selectedId === null ? null : (clone $choices)->where('id', $selectedId)->first();
        if ($search !== '') {
            $choices->where('name', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%');
        }
        $total = (clone $choices)->count();
        $scope = implode('|', ['transfer-choices', $actorPlayerId, $allianceId, $kind->value, $planId ?? '', hash('sha256', $search)]);
        $through = $cursor === null ? (clone $choices)->max('id') : null;
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $after = $position['after'] ?? null;
            $through = $position['through'] ?? null;
            if (count($position) !== 2 || ! $this->isId($after) || ! $this->isId($through) || strcmp($after, $through) > 0) {
                throw ValidationException::withMessages(['cursor' => 'The transfer choice cursor is invalid.']);
            }
            $choices->where('id', '>', $after);
        }
        $rows = $through === null ? collect() : $choices->where('id', '<=', $through)->orderBy('id')->limit(self::PAGE_SIZE + 1)->get();
        $items = array_values($rows->take(self::PAGE_SIZE)->map(fn (stdClass $row): array => $this->choice($row))->all());
        $last = $items === [] ? null : $items[array_key_last($items)]['id'];
        $next = $rows->count() > self::PAGE_SIZE && $last !== null
            ? $this->cursors->encode($scope, ['after' => $last, 'through' => $through]) : null;

        return ['page' => (new PageSlice($items, $next, self::PAGE_SIZE, $cursor === null))->toArray(),
            'total' => $total, 'selected' => $selected === null ? null : $this->choice($selected)];
    }

    /** @phpstan-assert-if-true non-empty-string $id */
    private function isId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/Di', $id) === 1;
    }

    /** @return array{id:string,name:string} */
    private function choice(stdClass $row): array
    {
        return ['id' => (string) $row->id, 'name' => (string) $row->name];
    }
}
