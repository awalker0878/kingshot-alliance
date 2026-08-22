<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Queries;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Operations\Access\Services\KingdomOperationsAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryLayoutAnalyzer;

final readonly class TerritoryPlanQuery
{
    public function __construct(
        private AllianceOperationsAuthorization $allianceAuthorization,
        private KingdomOperationsAuthorization $kingdomAuthorization,
        private KingdomMapDatasetQuery $datasets,
        private PlacementValidator $placement,
        private TerritoryLayoutAnalyzer $analysis,
    ) {}

    /** @return list<array<string,mixed>> */
    public function visiblePlans(string $actorPlayerId, string $kingdomId): array
    {
        return TerritoryPlan::query()
            ->where('kingdom_id', $kingdomId)
            ->where('status', '!=', 'archived')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->filter(fn (TerritoryPlan $plan): bool => $this->canView($actorPlayerId, $plan))
            ->map(fn (TerritoryPlan $plan): array => $this->summary($actorPlayerId, $plan))
            ->values()->all();
    }

    /** @return array<string,mixed> */
    public function detail(string $actorPlayerId, string $planId): array
    {
        $plan = TerritoryPlan::query()->with(['planAlliances', 'groups', 'objects', 'revisions'])->findOrFail($planId);
        abort_unless($this->canView($actorPlayerId, $plan), 403);
        $dataset = $this->datasets->require((string) $plan->map_dataset_id, (string) $plan->map_dataset_checksum);
        $alliances = $plan->planAlliances->map(static fn ($row): array => [
            'key' => (string) $row->id,
            'alliance_id' => $row->alliance_id === null ? null : (string) $row->alliance_id,
            'external_name' => $row->external_name,
            'external_tag' => $row->external_tag,
            'display_name' => (string) $row->display_name,
            'presentation_color' => (string) $row->presentation_color,
            'sort_order' => (int) $row->sort_order,
            'visible' => (bool) $row->visible,
            'locked' => (bool) $row->locked,
        ])->values()->all();
        $groups = $plan->groups->map(static fn ($row): array => ['key' => (string) $row->id, 'label' => $row->label])->values()->all();
        $objects = $plan->objects->map(static fn ($row): array => [
            'key' => (string) $row->id,
            'alliance_key' => (string) $row->territory_plan_alliance_id,
            'group_key' => $row->group_id === null ? null : (string) $row->group_id,
            'type' => $row->object_type->value,
            'player_id' => $row->player_id === null ? null : (string) $row->player_id,
            'external_player_name' => $row->external_player_name,
            'label' => $row->label,
            'x' => (int) $row->coordinate_x,
            'y' => (int) $row->coordinate_y,
            'rotation' => (int) $row->rotation,
            'sort_order' => (int) $row->sort_order,
            'metadata' => $row->metadata ?? [],
        ])->values()->all();
        $validationObjects = array_map(static fn (array $object): array => [
            'key' => $object['key'], 'type' => $object['type'], 'x' => $object['x'], 'y' => $object['y'], 'alliance_key' => $object['alliance_key'],
        ], $objects);
        $preferences = $plan->planning_preferences ?? [];

        return [
            'plan' => $this->summary($actorPlayerId, $plan),
            'alliances' => $alliances,
            'groups' => $groups,
            'objects' => $objects,
            'validation' => $this->placement->validate($dataset, $validationObjects, $preferences)->toArray(),
            'analysis' => $this->analysis->analyze($dataset, $validationObjects, $preferences),
            'map' => [
                'id' => $dataset->id,
                'schema_version' => $dataset->schemaVersion,
                'observed_at' => $dataset->observedAt,
                'source_label' => $dataset->sourceLabel,
                'source_uri' => $dataset->sourceUri,
                'confidence' => $dataset->confidence->value,
                'checksum' => $dataset->checksum,
                'data' => $dataset->data,
            ],
            'revisions' => $plan->revisions->map(static fn (TerritoryPlanRevision $revision): array => [
                'id' => (string) $revision->id,
                'revision_number' => (int) $revision->revision_number,
                'map_dataset_id' => (string) $revision->map_dataset_id,
                'map_dataset_checksum' => (string) $revision->map_dataset_checksum,
                'snapshot_checksum' => (string) $revision->snapshot_checksum,
                'published_at' => $revision->published_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function canView(string $actorPlayerId, TerritoryPlan $plan): bool
    {
        return $plan->scope === TerritoryPlanScope::Alliance
            ? $plan->owner_alliance_id !== null && $this->allianceAuthorization->allows($actorPlayerId, (string) $plan->owner_alliance_id, OperationsPermission::TerritoryAllianceView)
            : $this->kingdomAuthorization->allows($actorPlayerId, (string) $plan->kingdom_id, OperationsPermission::TerritoryKingdomView);
    }

    /** @return array<string,mixed> */
    private function summary(string $actorPlayerId, TerritoryPlan $plan): array
    {
        $canManage = $plan->scope === TerritoryPlanScope::Alliance
            ? $plan->owner_alliance_id !== null && $this->allianceAuthorization->allows($actorPlayerId, (string) $plan->owner_alliance_id, OperationsPermission::TerritoryAllianceManage)
            : $this->kingdomAuthorization->allows($actorPlayerId, (string) $plan->kingdom_id, OperationsPermission::TerritoryKingdomManage);

        return [
            'id' => (string) $plan->id,
            'scope' => $plan->scope->value,
            'kingdom_id' => (string) $plan->kingdom_id,
            'owner_alliance_id' => $plan->owner_alliance_id === null ? null : (string) $plan->owner_alliance_id,
            'name' => (string) $plan->name,
            'status' => $plan->status->value,
            'revision' => (int) $plan->revision,
            'map_dataset_id' => (string) $plan->map_dataset_id,
            'map_dataset_checksum' => (string) $plan->map_dataset_checksum,
            'planning_preferences' => $plan->planning_preferences ?? [],
            'published_at' => $plan->published_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
            'can_manage' => $canManage,
        ];
    }
}
