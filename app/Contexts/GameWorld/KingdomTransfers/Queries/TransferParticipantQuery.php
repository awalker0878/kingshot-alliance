<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class TransferParticipantQuery
{
    public function __construct(private readonly TransferAuthorization $authorization, private readonly ScopedCursorCodec $cursors) {}

    public function activeForPlayer(string $actorPlayerId, string $allianceId, string $planId, string $playerId): ?TransferParticipant
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, TransferPermission::View)) {
            throw new AuthorizationException;
        }

        return TransferParticipant::query()->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $planId)->where('player_id', $playerId)
            ->whereNull('withdrawn_at')->first();
    }

    public const int PAGE_SIZE = 25;

    /** @return PageSlice<TransferParticipant> */
    public function page(string $actorPlayerId, string $allianceId, string $planId, bool $includeWithdrawn = false, ?string $cursor = null, TransferPermission $permission = TransferPermission::View): PageSlice
    {
        $this->authorizePlan($actorPlayerId, $allianceId, $planId, $permission);
        $scope = implode('|', ['transfer-participants', $actorPlayerId, $allianceId, $planId, $includeWithdrawn ? 'all' : 'active', $permission->value]);
        $query = $this->participantRows($allianceId, $planId, $includeWithdrawn);
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $id = $position['id'] ?? null;
            if (array_keys($position) !== ['id'] || ! is_string($id) || ! preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/Di', $id)) {
                throw ValidationException::withMessages(['participant_cursor' => 'The participant cursor is invalid.']);
            }
            $query->where('id', '>', $id);
        }
        // IDs are stable when labels or workflow states change. No boundary-row lookup.
        $rows = $query->orderBy('id')->limit(self::PAGE_SIZE + 1)->get();
        $items = $rows->take(self::PAGE_SIZE)->values();
        $last = $items->last();

        return new PageSlice(array_values($items->all()), $rows->count() > self::PAGE_SIZE && $last !== null
            ? $this->cursors->encode($scope, ['id' => (string) $last->id]) : null, self::PAGE_SIZE, $cursor === null);
    }

    /** @return array{total:int,incoming:int,outgoing:int,staying:int,completed:int,confirmed:int,withdrawn:int} */
    public function summary(string $actorPlayerId, string $allianceId, string $planId, bool $includeWithdrawn = false, TransferPermission $permission = TransferPermission::View): array
    {
        $this->authorizePlan($actorPlayerId, $allianceId, $planId, $permission);
        $query = TransferParticipant::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId);
        if (! $includeWithdrawn) {
            $query->whereNull('withdrawn_at');
        }
        $completed = 'exists (select 1 from transfer_completions as completion where completion.transfer_participant_id = transfer_participants.id and completion.alliance_id = transfer_participants.alliance_id and completion.transfer_plan_id = transfer_participants.transfer_plan_id)';
        $row = $query->toBase()->selectRaw('count(*) as total, '
            ."sum(case when direction = 'incoming' then 1 else 0 end) as incoming, "
            ."sum(case when direction = 'outgoing' then 1 else 0 end) as outgoing, "
            ."sum(case when direction = 'staying' then 1 else 0 end) as staying, "
            ."sum(case when $completed then 1 else 0 end) as completed, "
            ."sum(case when readiness_state = 'confirmed' and withdrawn_at is null and not ($completed) then 1 else 0 end) as confirmed, "
            .'sum(case when withdrawn_at is not null then 1 else 0 end) as withdrawn')->first();

        return ['total' => (int) ($row->total ?? 0), 'incoming' => (int) ($row->incoming ?? 0),
            'outgoing' => (int) ($row->outgoing ?? 0), 'staying' => (int) ($row->staying ?? 0),
            'completed' => (int) ($row->completed ?? 0), 'confirmed' => (int) ($row->confirmed ?? 0),
            'withdrawn' => (int) ($row->withdrawn ?? 0)];
    }

    private function authorizePlan(string $actorPlayerId, string $allianceId, string $planId, TransferPermission $permission): void
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, $permission)) {
            throw new AuthorizationException;
        }
        TransferPlan::query()->where('alliance_id', $allianceId)->whereKey($planId)->firstOrFail();
    }

    /** @return Builder<TransferParticipant> */
    private function participantRows(string $allianceId, string $planId, bool $includeWithdrawn): Builder
    {
        $query = TransferParticipant::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)->with([
            'player:id,current_kingdom_id,game_player_id,current_name',
            'sourceKingdom:id,number',
            'destinationKingdom:id,number',
            'cohort' => static fn ($rows) => $rows->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId),
            'cohort.coordinator:id,current_name',
            'cohort.destinationKingdom:id,number',
            'completion' => static fn ($rows) => $rows->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)
                ->select('id', 'transfer_participant_id', 'roster_entry_id', 'completed_by_player_id', 'completed_at')
                ->when($includeWithdrawn, static fn ($rows) => $rows->with('completedBy:id,current_name')),
            'capacityReservation' => static fn ($rows) => $rows->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)
                ->select('id', 'transfer_participant_id', 'bucket', 'state', 'reserved_at', 'released_at', 'notes'),
            'invitationAllocation' => static fn ($rows) => $rows->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)
                ->select('id', 'transfer_participant_id', 'kind', 'state', 'notes'),
        ]);
        if (! $includeWithdrawn) {
            $query->whereNull('withdrawn_at');
        } else {
            $query->withCount([
                'blockers as active_blocker_count' => static fn (Builder $rows) => $rows->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)->where('state', 'active'),
                'blockers as resolved_blocker_count' => static fn (Builder $rows) => $rows->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)->where('state', 'resolved'),
                'readinessTransitions as readiness_transition_count' => static fn (Builder $rows) => $rows->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId),
            ]);
        }

        return $query;
    }
}
