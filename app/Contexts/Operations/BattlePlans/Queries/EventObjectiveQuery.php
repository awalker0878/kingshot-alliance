<?php

declare(strict_types=1);

namespace App\Contexts\Operations\BattlePlans\Queries;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\BattlePlans\Models\EventObjective;
use App\Contexts\Operations\BattlePlans\Models\EventObjectiveAssignment;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use Carbon\CarbonImmutable;

final readonly class EventObjectiveQuery
{
    public function __construct(private EventEligiblePlayerQuery $eligiblePlayers) {}

    /** @return array{objectives:list<array<string,mixed>>,myAssignmentIds:list<string>} */
    public function forOccurrence(EventOccurrence $occurrence, ?Player $player): array
    {
        $objectives = $this->objectives($occurrence);
        if (! $player instanceof Player) {
            return ['objectives' => $objectives, 'myAssignmentIds' => []];
        }

        $rosterIds = EventRosterMember::query()
            ->where('player_id', $player->id)
            ->whereNotIn('status', [EventRosterMemberStatus::Declined->value, EventRosterMemberStatus::Removed->value])
            ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $occurrence->id))
            ->pluck('roster_id');

        $assignmentIds = EventObjectiveAssignment::query()
            ->where('occurrence_id', $occurrence->id)
            ->where(static fn ($query) => $query
                ->where('player_id', $player->id)
                ->orWhereIn('roster_id', $rosterIds))
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        return ['objectives' => $objectives, 'myAssignmentIds' => $assignmentIds];
    }

    /** @return list<array<string,mixed>> */
    public function management(Event $event): array
    {
        $players = $this->eligiblePlayers->for($event);

        return $event->occurrences
            ->sortBy('starts_at')
            ->values()
            ->map(function (EventOccurrence $occurrence) use ($players): array {
                $rosters = $occurrence->rosters()->orderBy('sort_order')->orderBy('key')->get();
                $objectives = array_map(function (array $objective) use ($event): array {
                    $objective['startsLocal'] = $objective['startsAt'] === null ? null : CarbonImmutable::parse($objective['startsAt'])->setTimezone($event->timezone)->format('Y-m-d\\TH:i');
                    $objective['endsLocal'] = $objective['endsAt'] === null ? null : CarbonImmutable::parse($objective['endsAt'])->setTimezone($event->timezone)->format('Y-m-d\\TH:i');

                    return $objective;
                }, $this->objectives($occurrence));

                return [
                    'occurrenceId' => (string) $occurrence->id,
                    'startsAt' => $occurrence->starts_at->toIso8601String(),
                    'endsAt' => $occurrence->ends_at->toIso8601String(),
                    'objectives' => $objectives,
                    'rosters' => $rosters->map(static fn ($roster): array => [
                        'id' => (string) $roster->id,
                        'name' => $roster->name,
                        'nameKey' => $roster->name_key,
                        'key' => (string) $roster->key,
                        'type' => $roster->roster_type->value,
                    ])->all(),
                    'players' => $players->map(static fn (Player $player): array => [
                        'id' => (string) $player->id,
                        'name' => (string) $player->current_name,
                        'claimed' => $player->user_id !== null,
                    ])->values()->all(),
                ];
            })->all();
    }

    /** @return list<array<string,mixed>> */
    private function objectives(EventOccurrence $occurrence): array
    {
        return EventObjective::query()
            ->where('occurrence_id', $occurrence->id)
            ->with(['assignments.player', 'assignments.roster'])
            ->orderByDesc('priority')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(static fn (EventObjective $objective): array => [
                'id' => (string) $objective->id,
                'parentId' => $objective->parent_id === null ? null : (string) $objective->parent_id,
                'type' => (string) $objective->objective_type,
                'name' => (string) $objective->name,
                'description' => $objective->description,
                'priority' => (int) $objective->priority,
                'startsAt' => $objective->starts_at?->toIso8601String(),
                'endsAt' => $objective->ends_at?->toIso8601String(),
                'status' => $objective->status->value,
                'sortOrder' => (int) $objective->sort_order,
                'metadata' => $objective->metadata ?? [],
                'assignments' => $objective->assignments->map(static fn (EventObjectiveAssignment $assignment): array => [
                    'id' => (string) $assignment->id,
                    'rosterId' => $assignment->roster_id === null ? null : (string) $assignment->roster_id,
                    'rosterName' => $assignment->roster?->name,
                    'rosterNameKey' => $assignment->roster?->name_key,
                    'rosterKey' => $assignment->roster?->key,
                    'playerId' => $assignment->player_id === null ? null : (string) $assignment->player_id,
                    'playerName' => $assignment->player?->current_name,
                    'notes' => $assignment->notes,
                    'assignedAt' => $assignment->assigned_at?->toIso8601String(),
                ])->values()->all(),
            ])->all();
    }
}
