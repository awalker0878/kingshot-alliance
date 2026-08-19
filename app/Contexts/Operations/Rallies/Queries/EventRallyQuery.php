<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Queries;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
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
        private RallyAllianceResolver $rallyAlliances,
        private AllianceReferenceQuery $alliances,
        private PlayerReferenceQuery $players,
        private RallyPlayerEligibility $eligibility,
        private EventEligiblePlayerQuery $eligiblePlayers,
    ) {}

    /** @return array<string,mixed> */
    public function forOccurrence(EventOccurrence $occurrence, ?PlayerReference $activePlayer): array
    {
        $occurrence->loadMissing('event');
        $event = $occurrence->event;
        $alliances = $this->rallyAlliances->forEvent($event);

        return [
            'savedFormations' => $activePlayer instanceof PlayerReference ? $this->savedFormations($activePlayer->playerId) : [],
            'alliances' => $alliances->map(static fn (AllianceReference $alliance): array => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
            ])->values()->all(),
            'guidance' => $this->guidance($occurrence, $alliances),
            'recommendations' => $this->recommendations($occurrence),
            'groups' => $this->groupsForPlayer($occurrence, $activePlayer),
            'myAssignments' => $activePlayer instanceof PlayerReference ? $this->myAssignments($occurrence, $activePlayer->playerId) : [],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function management(Event $event): array
    {
        $eventPlayers = $this->eligiblePlayers->for($event);
        $alliances = $this->rallyAlliances->forEvent($event);

        return array_values($event->occurrences()
            ->orderBy('starts_at')
            ->get()
            ->map(function (EventOccurrence $occurrence) use ($event, $eventPlayers, $alliances): array {
                return [
                    'occurrenceId' => (string) $occurrence->id,
                    'startsAt' => $occurrence->starts_at->toIso8601String(),
                    'alliances' => $alliances->map(static fn (AllianceReference $alliance): array => [
                        'id' => $alliance->allianceId,
                        'name' => $alliance->name,
                    ])->values()->all(),
                    'guidance' => $this->guidance($occurrence, $alliances),
                    'recommendations' => $this->recommendations($occurrence),
                    'groups' => $this->managementGroups($occurrence),
                    'candidatesByAlliance' => $alliances->mapWithKeys(function (AllianceReference $alliance) use ($event, $eventPlayers): array {
                        $players = $eventPlayers
                            ->filter(fn (PlayerReference $player): bool => $this->eligibility->eligible($event, $alliance, $player))
                            ->map(static fn (PlayerReference $player): array => [
                                'playerId' => $player->playerId,
                                'name' => $player->currentName,
                                'claimed' => $player->claimed(),
                            ])
                            ->values()
                            ->all();

                        return [$alliance->allianceId => $players];
                    })->all(),
                ];
            })->all());
    }

    /** @return list<array<string,mixed>> */
    private function savedFormations(string $playerId): array
    {
        return array_values(PlayerFormation::query()
            ->where('player_id', $playerId)
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
     * @param  Collection<int, AllianceReference>  $alliances
     * @return list<array<string,mixed>>
     */
    private function guidance(EventOccurrence $occurrence, Collection $alliances): array
    {
        $date = $occurrence->starts_at->toDateString();
        $allianceMap = $alliances->keyBy(static fn (AllianceReference $alliance): string => $alliance->allianceId);

        return array_values(RallyGuidanceRule::query()
            ->whereIn('alliance_id', $allianceMap->keys()->all())
            ->where('is_active', true)
            ->where(static fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(static fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $date))
            ->orderBy('alliance_id')
            ->orderByDesc('effective_from')
            ->orderBy('name')
            ->get()
            ->map(static function (RallyGuidanceRule $rule) use ($allianceMap): array {
                /** @var AllianceReference|null $alliance */
                $alliance = $allianceMap->get((string) $rule->alliance_id);

                return [
                    'id' => (string) $rule->id,
                    'allianceId' => (string) $rule->alliance_id,
                    'allianceName' => $alliance->name ?? 'Unknown Alliance',
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
                ];
            })->all());
    }

    /** @return list<array<string,mixed>> */
    private function recommendations(EventOccurrence $occurrence): array
    {
        $records = EventRecommendedFormation::query()
            ->where('occurrence_id', $occurrence->id)
            ->orderBy('alliance_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $alliances = $this->alliances->byIds($records->pluck('alliance_id')->map(static fn ($id): string => (string) $id)->all());

        return array_values($records->map(static fn (EventRecommendedFormation $formation): array => [
            'id' => (string) $formation->id,
            'allianceId' => (string) $formation->alliance_id,
            'allianceName' => $alliances[(string) $formation->alliance_id]->name ?? 'Unknown Alliance',
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
        ])->all());
    }

    /** @return list<array<string,mixed>> */
    private function groupsForPlayer(EventOccurrence $occurrence, ?PlayerReference $player): array
    {
        $groups = RallyGroup::query()
            ->where('occurrence_id', $occurrence->id)
            ->with('recommendedFormation')
            ->orderBy('alliance_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $alliances = $this->alliances->byIds($groups->pluck('alliance_id')->map(static fn ($id): string => (string) $id)->all());
        $assignments = $player instanceof PlayerReference
            ? RallyAssignment::query()->whereIn('rally_group_id', $groups->pluck('id'))->where('player_id', $player->playerId)->get()->keyBy('rally_group_id')
            : collect();

        return array_values($groups->map(static function (RallyGroup $group) use ($alliances, $assignments): array {
            /** @var RallyAssignment|null $assignment */
            $assignment = $assignments->get((string) $group->id);

            return [
                'id' => (string) $group->id,
                'allianceId' => (string) $group->alliance_id,
                'allianceName' => $alliances[(string) $group->alliance_id]->name ?? 'Unknown Alliance',
                'name' => (string) $group->name,
                'maxJoiners' => $group->max_joiners,
                'notes' => $group->notes,
                'recommendedFormationId' => $group->recommended_formation_id === null ? null : (string) $group->recommended_formation_id,
                'recommendedFormationName' => $group->recommendedFormation?->name,
                'myAssignmentStatus' => $assignment?->statusEnum()->value,
            ];
        })->all());
    }

    /** @return list<array<string,mixed>> */
    private function myAssignments(EventOccurrence $occurrence, string $playerId): array
    {
        $records = RallyAssignment::query()
            ->where('player_id', $playerId)
            ->where('status', '!=', RallyAssignmentStatus::Removed->value)
            ->whereHas('rallyGroup', static fn ($query) => $query->where('occurrence_id', $occurrence->id))
            ->with('rallyGroup.recommendedFormation')
            ->get();
        $alliances = $this->alliances->byIds($records->map(static fn (RallyAssignment $assignment): string => (string) $assignment->rallyGroup->alliance_id)->all());

        return array_values($records->map(static fn (RallyAssignment $assignment): array => [
            'id' => (string) $assignment->id,
            'groupId' => (string) $assignment->rally_group_id,
            'groupName' => (string) $assignment->rallyGroup->name,
            'allianceId' => (string) $assignment->rallyGroup->alliance_id,
            'allianceName' => $alliances[(string) $assignment->rallyGroup->alliance_id]->name ?? 'Unknown Alliance',
            'role' => $assignment->roleEnum()->value,
            'slotNumber' => $assignment->slot_number,
            'status' => $assignment->statusEnum()->value,
            'notes' => $assignment->notes,
            'recommendedFormationName' => $assignment->rallyGroup->recommendedFormation?->name,
            'respondedAt' => $assignment->responded_at?->toIso8601String(),
            'recordedAt' => $assignment->recorded_at?->toIso8601String(),
        ])->all());
    }

    /** @return list<array<string,mixed>> */
    private function managementGroups(EventOccurrence $occurrence): array
    {
        $groups = RallyGroup::query()
            ->where('occurrence_id', $occurrence->id)
            ->with(['recommendedFormation', 'assignments'])
            ->orderBy('alliance_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $alliances = $this->alliances->byIds($groups->pluck('alliance_id')->map(static fn ($id): string => (string) $id)->all());
        $playerIds = $groups->flatMap(static fn (RallyGroup $group) => $group->assignments->pluck('player_id'))
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
        $players = $this->players->byIds($playerIds);

        return array_values($groups->map(static function (RallyGroup $group) use ($alliances, $players): array {
            $activeJoiners = $group->assignments->filter(
                static fn (RallyAssignment $assignment): bool => $assignment->roleEnum()->value === 'joiner' && $assignment->statusEnum()->occupiesAssignment(),
            )->count();

            return [
                'id' => (string) $group->id,
                'allianceId' => (string) $group->alliance_id,
                'allianceName' => $alliances[(string) $group->alliance_id]->name ?? 'Unknown Alliance',
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
                    'playerName' => $players[(string) $assignment->player_id]->currentName ?? 'Unknown Player',
                    'role' => $assignment->roleEnum()->value,
                    'slotNumber' => $assignment->slot_number,
                    'status' => $assignment->statusEnum()->value,
                    'notes' => $assignment->notes,
                ])->values()->all(),
            ];
        })->all());
    }
}
