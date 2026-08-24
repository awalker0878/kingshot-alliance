<?php

declare(strict_types=1);

namespace App\Contexts\Operations\BattlePlans\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\BattlePlans\Models\EventObjective;
use App\Contexts\Operations\BattlePlans\Models\EventObjectiveAssignment;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;

final class PlayerBattlePlanQuery
{
    /**
     * Return only assignments effective for the supplied Player. Other Governors' assignments
     * are never materialized into this projection.
     *
     * @return list<array<string,mixed>>
     */
    public function forPlayer(EventOccurrence $occurrence, PlayerReference $player): array
    {
        $rosterIds = EventRosterMember::query()
            ->where('player_id', $player->playerId)
            ->whereNotIn('status', [
                EventRosterMemberStatus::Declined->value,
                EventRosterMemberStatus::Removed->value,
            ])
            ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $occurrence->id))
            ->pluck('roster_id');

        $assignments = EventObjectiveAssignment::query()
            ->where('occurrence_id', $occurrence->id)
            ->where(static fn ($query) => $query
                ->where('player_id', $player->playerId)
                ->orWhereIn('roster_id', $rosterIds))
            ->with(['objective', 'roster'])
            ->orderBy('assigned_at')
            ->orderBy('id')
            ->limit(25)
            ->get();

        $rows = [];
        foreach ($assignments as $assignment) {
            if (! $assignment instanceof EventObjectiveAssignment) {
                continue;
            }
            $objective = $assignment->objective;
            if (! $objective instanceof EventObjective) {
                continue;
            }

            $rows[] = [
                'assignmentId' => (string) $assignment->id,
                'objectiveId' => (string) $objective->id,
                'objectiveName' => $objective->name,
                'objectiveType' => $objective->objective_type,
                'objectiveStatus' => $objective->status->value,
                'objectiveDescription' => $objective->description,
                'startsAt' => $objective->starts_at?->toIso8601String(),
                'endsAt' => $objective->ends_at?->toIso8601String(),
                'rosterId' => $assignment->roster_id === null ? null : (string) $assignment->roster_id,
                'rosterName' => $assignment->roster?->name,
                'rosterNameKey' => $assignment->roster?->name_key,
                'rosterKey' => $assignment->roster?->key,
                'assignmentScope' => $assignment->player_id === $player->playerId ? 'player' : 'roster',
                'notes' => $assignment->notes,
                'assignedAt' => $assignment->assigned_at?->toIso8601String(),
            ];
        }

        return $rows;
    }
}
