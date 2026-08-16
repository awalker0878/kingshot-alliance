<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Queries;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Queries\EventEligiblePlayerQuery;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\EventRecommendedFormation;
use App\Contexts\Operations\Rallies\Models\PlayerFormation;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Rallies\Models\RallyGuidanceRule;
use App\Contexts\Operations\Rallies\Services\RallyAllianceResolver;
use App\Contexts\Operations\Rallies\Services\RallyPlayerEligibility;
use Illuminate\Support\Collection;

final readonly class EventRallyQuery
{
    public function __construct(
        private RallyAllianceResolver $alliances,
        private RallyPlayerEligibility $eligibility,
        private EventEligiblePlayerQuery $eligiblePlayers,
    ) {}

    /** @return array<string,mixed> */
    public function forOccurrence(EventOccurrence $occurrence, ?Player $activePlayer): array
    {
        $occurrence->loadMissing('event');
        $event = $occurrence->event;
        $alliances = $this->alliances->forEvent($event);

        return [
            'savedFormations' => $activePlayer instanceof Player ? $this->savedFormations($activePlayer) : [],
            'alliances' => $alliances->map(static fn ($alliance): array => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
            ])->values()->all(),
            'guidance' => $this->guidance($occurrence, $alliances),
            'recommendations' => $this->recommendations($occurrence),
            'groups' => $this->groupsForPlayer($occurrence, $activePlayer),
            'myAssignments' => $activePlayer instanceof Player ? $this->myAssignments($occurrence, $activePlayer) : [],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function management(Event $event): array
    {
        $eventPlayers = $this->eligiblePlayers->for($event);

        return array_values($event->occurrences()
            ->orderBy('starts_at')
            ->get()
            ->map(function (EventOccurrence $occurrence) use ($event, $eventPlayers): array {
                $alliances = $this->alliances->forEvent($event);

                return [
                    'occurrenceId' => (string) $occurrence->id,
                    'startsAt' => $occurrence->starts_at->toIso8601String(),
                    'alliances' => $alliances->map(static fn ($alliance): array => [
                        'id' => (string) $alliance->id,
                        'name' => (string) $alliance->name,
                    ])->values()->all(),
                    'guidance' => $this->guidance($occurrence, $alliances),
                    'recommendations' => $this->recommendations($occurrence),
                    'groups' => $this->managementGroups($occurrence),
                    'candidatesByAlliance' => $alliances->mapWithKeys(function ($alliance) use ($event, $eventPlayers): array {
                        $players = $eventPlayers
                            ->filter(fn (Player $player): bool => $this->eligibility->eligible($event, $alliance, $player))
                            ->map(static fn (Player $player): array => [
                                'playerId' => (string) $player->id,
                                'name' => (string) $player->current_name,
                                'claimed' => $player->user_id !== null,
                            ])
                            ->values()
                            ->all();

                        return [(string) $alliance->id => $players];
                    })->all(),
                ];
            })->all());
    }

    /** @return list<array<string,mixed>> */
    private function savedFormations(Player $player): array
    {
        return array_values(PlayerFormation::query()
            ->where('player_id', $player->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(static fn (PlayerFormation $formation): array => [
                'id' => (string) $formation->id,
                'name' => (string) $formation->name,
                'infantryPercent' => (int) $formation->infantry_percent,
                'cavalryPercent' => (int) $formation->cavalry_percent,
                'archerPercent' => (int) $formation->archer_percent,
                'heroes' => $formation->heroes ?? [],
                'notes' => $formation->notes,
                'isDefault' => (bool) $formation->is_default,
            ])->all());
    }

    /**
     * @param  Collection<int,Alliance>  $alliances
     * @return list<array<string,mixed>>
     */
    private function guidance(EventOccurrence $occurrence, Collection $alliances): array
    {
        $date = $occurrence->starts_at->toDateString();

        return array_values(RallyGuidanceRule::query()
            ->whereIn('alliance_id', $alliances->pluck('id'))
            ->where('is_active', true)
            ->where(static fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(static fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $date))
            ->with('alliance')
            ->orderBy('alliance_id')
            ->orderByDesc('effective_from')
            ->orderBy('name')
            ->get()
            ->map(static fn (RallyGuidanceRule $rule): array => [
                'id' => (string) $rule->id,
                'allianceId' => (string) $rule->alliance_id,
                'allianceName' => (string) $rule->alliance->name,
                'name' => (string) $rule->name,
                'infantryPercent' => (int) $rule->infantry_percent,
                'cavalryPercent' => (int) $rule->cavalry_percent,
                'archerPercent' => (int) $rule->archer_percent,
                'heroes' => $rule->hero_recommendations ?? [],
                'leadRequirements' => $rule->lead_requirements,
                'joinerGuidance' => $rule->joiner_guidance,
                'source' => $rule->source,
                'rationale' => $rule->rationale,
                'effectiveFrom' => $rule->effective_from?->toDateString(),
                'effectiveUntil' => $rule->effective_until?->toDateString(),
            ])->all());
    }

    /** @return list<array<string,mixed>> */
    private function recommendations(EventOccurrence $occurrence): array
    {
        return EventRecommendedFormation::query()
            ->where('occurrence_id', $occurrence->id)
            ->with(['alliance', 'guidanceRule'])
            ->orderBy('alliance_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(static fn (EventRecommendedFormation $formation): array => [
                'id' => (string) $formation->id,
                'allianceId' => (string) $formation->alliance_id,
                'allianceName' => (string) $formation->alliance->name,
                'guidanceRuleId' => $formation->guidance_rule_id === null ? null : (string) $formation->guidance_rule_id,
                'key' => (string) $formation->key,
                'name' => (string) $formation->name,
                'assignmentRole' => $formation->assignment_role,
                'infantryPercent' => (int) $formation->infantry_percent,
                'cavalryPercent' => (int) $formation->cavalry_percent,
                'archerPercent' => (int) $formation->archer_percent,
                'heroes' => $formation->heroes ?? [],
                'notes' => $formation->notes,
                'sortOrder' => (int) $formation->sort_order,
            ])->all();
    }

    /** @return list<array<string,mixed>> */
    private function groupsForPlayer(EventOccurrence $occurrence, ?Player $player): array
    {
        return RallyGroup::query()
            ->where('occurrence_id', $occurrence->id)
            ->with(['alliance', 'recommendedFormation'])
            ->orderBy('alliance_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (RallyGroup $group) use ($player): array {
                $assignment = $player instanceof Player
                    ? RallyAssignment::query()->where('rally_group_id', $group->id)->where('player_id', $player->id)->first()
                    : null;

                return [
                    'id' => (string) $group->id,
                    'allianceId' => (string) $group->alliance_id,
                    'allianceName' => (string) $group->alliance->name,
                    'name' => (string) $group->name,
                    'maxJoiners' => $group->max_joiners,
                    'notes' => $group->notes,
                    'recommendedFormationId' => $group->recommended_formation_id === null ? null : (string) $group->recommended_formation_id,
                    'recommendedFormationName' => $group->recommendedFormation?->name,
                    'myAssignmentStatus' => $assignment?->status?->value,
                ];
            })->all();
    }

    /** @return list<array<string,mixed>> */
    private function myAssignments(EventOccurrence $occurrence, Player $player): array
    {
        return RallyAssignment::query()
            ->where('player_id', $player->id)
            ->where('status', '!=', RallyAssignmentStatus::Removed->value)
            ->whereHas('rallyGroup', static fn ($query) => $query->where('occurrence_id', $occurrence->id))
            ->with(['rallyGroup.alliance', 'rallyGroup.recommendedFormation'])
            ->get()
            ->map(static fn (RallyAssignment $assignment): array => [
                'id' => (string) $assignment->id,
                'groupId' => (string) $assignment->rally_group_id,
                'groupName' => (string) $assignment->rallyGroup->name,
                'allianceId' => (string) $assignment->rallyGroup->alliance_id,
                'allianceName' => (string) $assignment->rallyGroup->alliance->name,
                'role' => $assignment->role->value,
                'slotNumber' => $assignment->slot_number,
                'status' => $assignment->status->value,
                'notes' => $assignment->notes,
                'recommendedFormationName' => $assignment->rallyGroup->recommendedFormation?->name,
                'respondedAt' => $assignment->responded_at?->toIso8601String(),
                'recordedAt' => $assignment->recorded_at?->toIso8601String(),
            ])->all();
    }

    /** @return list<array<string,mixed>> */
    private function managementGroups(EventOccurrence $occurrence): array
    {
        return RallyGroup::query()
            ->where('occurrence_id', $occurrence->id)
            ->with(['alliance', 'recommendedFormation', 'assignments.player'])
            ->orderBy('alliance_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(static function (RallyGroup $group): array {
                $activeJoiners = $group->assignments->filter(static fn (RallyAssignment $assignment): bool => $assignment->role->value === 'joiner' && $assignment->status->occupiesAssignment())->count();

                return [
                    'id' => (string) $group->id,
                    'allianceId' => (string) $group->alliance_id,
                    'allianceName' => (string) $group->alliance->name,
                    'name' => (string) $group->name,
                    'maxJoiners' => $group->max_joiners,
                    'activeJoiners' => $activeJoiners,
                    'notes' => $group->notes,
                    'sortOrder' => (int) $group->sort_order,
                    'recommendedFormationId' => $group->recommended_formation_id === null ? null : (string) $group->recommended_formation_id,
                    'recommendedFormationName' => $group->recommendedFormation?->name,
                    'assignments' => $group->assignments->map(static fn (RallyAssignment $assignment): array => [
                        'id' => (string) $assignment->id,
                        'playerId' => (string) $assignment->player_id,
                        'playerName' => (string) $assignment->player->current_name,
                        'role' => $assignment->role->value,
                        'slotNumber' => $assignment->slot_number,
                        'status' => $assignment->status->value,
                        'notes' => $assignment->notes,
                    ])->values()->all(),
                ];
            })->all();
    }
}
