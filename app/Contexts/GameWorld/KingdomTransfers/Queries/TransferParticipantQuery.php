<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class TransferParticipantQuery
{
    public function __construct(private readonly TransferAuthorization $authorization) {}

    public function activeForPlayer(string $actorPlayerId, string $allianceId, string $planId, string $playerId): ?TransferParticipant
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, TransferPermission::View)) {
            throw new AuthorizationException;
        }

        return TransferParticipant::query()->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $planId)->where('player_id', $playerId)
            ->whereNull('withdrawn_at')->first();
    }

    /** @return Collection<int,TransferParticipant> */
    public function forPlan(string $allianceId, string $planId, bool $includeWithdrawn = false): Collection
    {
        $query = TransferParticipant::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)->with([
            'player:id,current_kingdom_id,game_player_id,current_name',
            'sourceKingdom:id,number',
            'destinationKingdom:id,number',
            'cohort.coordinator:id,current_name',
            'cohort.destinationKingdom:id,number',
            'completion:id,transfer_participant_id,roster_entry_id,completed_by_player_id,completed_at',
            'capacityReservation:id,transfer_participant_id,bucket,state,reserved_at,released_at,notes',
            'invitationAllocation:id,transfer_participant_id,kind,state,notes',
        ]);
        if (! $includeWithdrawn) {
            $query->whereNull('withdrawn_at');
        } else {
            $query->with('completion.completedBy:id,current_name')->withCount([
                'blockers as active_blocker_count' => static fn (Builder $rows) => $rows->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)->where('state', 'active'),
                'blockers as resolved_blocker_count' => static fn (Builder $rows) => $rows->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)->where('state', 'resolved'),
                'readinessTransitions as readiness_transition_count' => static fn (Builder $rows) => $rows->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId),
            ]);
        }

        return $query->orderByRaw("case direction when 'staying' then 0 when 'outgoing' then 1 else 2 end")->orderByRaw('case when withdrawn_at is null then 0 else 1 end')->orderBy('observed_name')->orderBy('id')->get();
    }
}
