<?php

declare(strict_types=1);

namespace App\Contexts\Operations\BattlePlans\Queries;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\BattlePlans\Models\EventObjective;
use App\Contexts\Operations\BattlePlans\Models\EventObjectiveAssignment;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Queries\EventEligiblePlayerQuery;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use Carbon\CarbonImmutable;

final readonly class EventObjectiveQuery
{
    public function __construct(
        private EventEligiblePlayerQuery $eligiblePlayers,
        private PlayerReferenceQuery $players,
    ) {}

    /** @return array{objectives:list<array<string,mixed>>,myAssignmentIds:list<string>} */
    public function forOccurrence(EventOccurrence $occurrence, ?PlayerReference $player): array
    {
        $objectives = $this->objectives($occurrence);
        if (! $player instanceof PlayerReference) {
            return ['objectives' => $objectives, 'myAssignmentIds' => []];
        }

        $rosterIds = EventRosterMember::query()
            ->where('player_id', $player->playerId)
            ->whereNotIn('status', [EventRosterMemberStatus::Declined->value, EventRosterMemberStatus::Removed->value])
            ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $occurrence->id))
            ->pluck('roster_id');

        $assignmentIds = [];
        foreach (EventObjectiveAssignment::query()
            ->where('occurrence_id', $occurrence->id)
            ->where(static fn ($query) => $query
                ->where('player_id', $player->playerId)
                ->orWhereIn('roster_id', $rosterIds))
            ->pluck('id') as $id) {
            $assignmentIds[] = (string) $id;
        }

        return ['objectives' => $objectives, 'myAssignmentIds' => $assignmentIds];
    }

    /** @return list<array<string,mixed>> */
    public function management(Event $event): array
    {
        $playerOptions = [];
        foreach ($this->eligiblePlayers->for($event) as $player) {
            $playerOptions[] = [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'claimed' => $player->claimed(),
            ];
        }

        $rows = [];
        foreach ($event->occurrences->sortBy('starts_at') as $occurrence) {
            if (! $occurrence instanceof EventOccurrence) {
                continue;
            }

            $rosterOptions = [];
            foreach ($occurrence->rosters()->orderBy('sort_order')->orderBy('key')->get() as $roster) {
                if (! $roster instanceof EventRoster) {
                    continue;
                }

                $rosterOptions[] = [
                    'id' => (string) $roster->id,
                    'name' => $roster->name,
                    'nameKey' => $roster->name_key,
                    'key' => $roster->key,
                    'type' => $roster->roster_type->value,
                ];
            }

            $objectiveRows = [];
            foreach ($this->objectives($occurrence) as $objective) {
                $objective['startsLocal'] = $this->localTime($objective['startsAt'] ?? null, (string) $event->timezone);
                $objective['endsLocal'] = $this->localTime($objective['endsAt'] ?? null, (string) $event->timezone);
                $objectiveRows[] = $objective;
            }

            $rows[] = [
                'occurrenceId' => (string) $occurrence->id,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                'endsAt' => $occurrence->ends_at->toIso8601String(),
                'objectives' => $objectiveRows,
                'rosters' => $rosterOptions,
                'players' => $playerOptions,
            ];
        }

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function objectives(EventOccurrence $occurrence): array
    {
        $objectives = EventObjective::query()
            ->where('occurrence_id', $occurrence->id)
            ->with(['assignments.roster'])
            ->orderByDesc('priority')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $playerIds = [];
        foreach ($objectives as $objective) {
            foreach ($objective->assignments as $assignment) {
                if ($assignment instanceof EventObjectiveAssignment && is_string($assignment->player_id) && $assignment->player_id !== '') {
                    $playerIds[] = $assignment->player_id;
                }
            }
        }
        $playerReferences = $this->players->byIds($playerIds);

        $rows = [];
        foreach ($objectives as $objective) {
            if (! $objective instanceof EventObjective) {
                continue;
            }

            $assignments = [];
            foreach ($objective->assignments as $assignment) {
                if (! $assignment instanceof EventObjectiveAssignment) {
                    continue;
                }

                $player = $assignment->player_id === null ? null : ($playerReferences[(string) $assignment->player_id] ?? null);
                $assignments[] = [
                    'id' => (string) $assignment->id,
                    'rosterId' => $assignment->roster_id === null ? null : (string) $assignment->roster_id,
                    'rosterName' => $assignment->roster?->name,
                    'rosterNameKey' => $assignment->roster?->name_key,
                    'rosterKey' => $assignment->roster?->key,
                    'playerId' => $assignment->player_id === null ? null : (string) $assignment->player_id,
                    'playerName' => $player?->currentName,
                    'notes' => $assignment->notes,
                    'assignedAt' => $assignment->assigned_at?->toIso8601String(),
                ];
            }

            $rows[] = [
                'id' => (string) $objective->id,
                'parentId' => $objective->parent_id === null ? null : (string) $objective->parent_id,
                'type' => $objective->objective_type,
                'name' => $objective->name,
                'description' => $objective->description,
                'priority' => $objective->priority,
                'startsAt' => $objective->starts_at?->toIso8601String(),
                'endsAt' => $objective->ends_at?->toIso8601String(),
                'status' => $objective->status->value,
                'sortOrder' => $objective->sort_order,
                'metadata' => $objective->metadata ?? [],
                'assignments' => $assignments,
            ];
        }

        return $rows;
    }

    private function localTime(mixed $value, string $timezone): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->setTimezone($timezone)->format('Y-m-d\\TH:i');
    }
}
