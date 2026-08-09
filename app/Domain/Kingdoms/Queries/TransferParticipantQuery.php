<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use Illuminate\Database\Eloquent\Collection;

final class TransferParticipantQuery
{
    /** @return Collection<int, TransferParticipant> */
    public function forPlan(Alliance $alliance, TransferPlan $plan, bool $includeWithdrawn = false): Collection
    {
        $query = TransferParticipant::query()
            ->where('alliance_id', $alliance->id)
            ->where('transfer_plan_id', $plan->id)
            ->with([
                'rosterEntry.player:id,kingdom_id,game_player_id,current_name',
                'membership.user:id,name,email',
                'sourceKingdom:id,number',
                'destinationKingdom:id,number',
                'group.coordinator.user:id,name,email',
                'group.destinationKingdom:id,number',
                'completion:id,transfer_participant_id,roster_entry_id,completed_by_user_id,completed_at',
            ]);

        if ($includeWithdrawn === false) {
            $query->whereNull('withdrawn_at');
        } else {
            $query->with([
                'blockers.createdBy:id,name',
                'blockers.resolvedBy:id,name',
                'readinessTransitions.actor:id,name',
                'completion.completedBy:id,name',
                'completion.rosterEntry.player:id,kingdom_id,game_player_id,current_name',
            ]);
        }

        return $query
            ->orderByRaw("case direction when 'staying' then 0 when 'outgoing' then 1 else 2 end")
            ->orderByRaw('case when withdrawn_at is null then 0 else 1 end')
            ->orderBy('observed_name')
            ->orderBy('id')
            ->get();
    }
}
