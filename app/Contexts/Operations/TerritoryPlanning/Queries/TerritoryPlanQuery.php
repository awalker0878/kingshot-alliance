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
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAlliance;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanGroup;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanObject;
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

    /** @return list<array<string, mixed>> */
    public function visiblePlans(string $actorPlayerId, string $kingdomId): array
    {
        $plans = [];
        foreach (
            TerritoryPlan::query()
                ->where('kingdom_id', $kingdomId)
                ->where('status', '!=', 'archived')
                ->orderByDesc('updated_at')
                ->limit(100)
                ->get() as $plan
        ) {
            if ($this->canView($actorPlayerId, $plan)) {
                $plans[] = $this->summary($actorPlayerId, $plan);
            }
        }

        return $plans;
    }

    /** @return array<string, mixed> */
    public function detail(string $actorPlayerId, string $planId): array
    {
        $plan = TerritoryPlan::query()
            ->with(['planAlliances', 'groups', 'objects', 'revisions'])
            ->findOrFail($planId);
        abort_unless($this->canView($actorPlayerId, $plan), 403);

        $dataset = $this->datasets->require($plan->map_dataset_id, $plan->map_dataset_checksum);

        $allianceKeyById = [];
        $alliances = [];
        foreach ($plan->planAlliances as $row) {
            if (! $row instanceof TerritoryPlanAlliance) {
                continue;
            }

            $allianceKeyById[$row->id] = $row->plan_key;
            $alliances[] = [
                'key' => $row->plan_key,
                'alliance_id' => $row->alliance_id,
                'external_name' => $row->external_name,
                'external_tag' => $row->external_tag,
                'display_name' => $row->display_name,
                'presentation_color' => $row->presentation_color,
                'sort_order' => $row->sort_order,
                'visible' => $row->visible,
                'locked' => $row->locked,
            ];
        }

        $groupKeyById = [];
        $groups = [];
        foreach ($plan->groups as $row) {
            if (! $row instanceof TerritoryPlanGroup) {
                continue;
            }

            $groupKeyById[$row->id] = $row->plan_key;
            $groups[] = ['key' => $row->plan_key, 'label' => $row->label];
        }

        $objects = [];
        $validationObjects = [];
        foreach ($plan->objects as $row) {
            if (! $row instanceof TerritoryPlanObject) {
                continue;
            }

            $allianceKey = $allianceKeyById[$row->territory_plan_alliance_id] ?? null;
            if (! is_string($allianceKey)) {
                continue;
            }

            $groupKey = $row->group_id === null ? null : ($groupKeyById[$row->group_id] ?? null);
            if ($row->group_id !== null && ! is_string($groupKey)) {
                continue;
            }

            $object = [
                'key' => $row->plan_key,
                'alliance_key' => $allianceKey,
                'group_key' => $groupKey,
                'type' => $row->object_type->value,
                'player_id' => $row->player_id,
                'external_player_name' => $row->external_player_name,
                'label' => $row->label,
                'x' => $row->coordinate_x,
                'y' => $row->coordinate_y,
                'rotation' => $row->rotation,
                'sort_order' => $row->sort_order,
                'metadata' => $row->metadata ?? [],
            ];
            $objects[] = $object;
            $validationObjects[] = [
                'key' => $object['key'],
                'type' => $object['type'],
                'x' => $object['x'],
                'y' => $object['y'],
                'alliance_key' => $object['alliance_key'],
            ];
        }

        $preferences = $plan->planning_preferences ?? [];
        $revisions = [];
        foreach ($plan->revisions as $revision) {
            if (! $revision instanceof TerritoryPlanRevision) {
                continue;
            }
            $revisions[] = [
                'id' => $revision->id,
                'revision_number' => $revision->revision_number,
                'map_dataset_id' => $revision->map_dataset_id,
                'map_dataset_checksum' => $revision->map_dataset_checksum,
                'snapshot_checksum' => $revision->snapshot_checksum,
                'published_at' => $revision->published_at->toIso8601String(),
            ];
        }

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
            'revisions' => $revisions,
        ];
    }

    private function canView(string $actorPlayerId, TerritoryPlan $plan): bool
    {
        return $plan->scope === TerritoryPlanScope::Alliance
            ? $plan->owner_alliance_id !== null && $this->allianceAuthorization->allows(
                $actorPlayerId,
                $plan->owner_alliance_id,
                OperationsPermission::TerritoryAllianceView,
            )
            : $this->kingdomAuthorization->allows(
                $actorPlayerId,
                $plan->kingdom_id,
                OperationsPermission::TerritoryKingdomView,
            );
    }

    /** @return array<string, mixed> */
    private function summary(string $actorPlayerId, TerritoryPlan $plan): array
    {
        $canManage = $plan->scope === TerritoryPlanScope::Alliance
            ? $plan->owner_alliance_id !== null && $this->allianceAuthorization->allows(
                $actorPlayerId,
                $plan->owner_alliance_id,
                OperationsPermission::TerritoryAllianceManage,
            )
            : $this->kingdomAuthorization->allows(
                $actorPlayerId,
                $plan->kingdom_id,
                OperationsPermission::TerritoryKingdomManage,
            );

        return [
            'id' => $plan->id,
            'scope' => $plan->scope->value,
            'kingdom_id' => $plan->kingdom_id,
            'owner_alliance_id' => $plan->owner_alliance_id,
            'name' => $plan->name,
            'status' => $plan->status->value,
            'revision' => $plan->revision,
            'map_dataset_id' => $plan->map_dataset_id,
            'map_dataset_checksum' => $plan->map_dataset_checksum,
            'planning_preferences' => $plan->planning_preferences ?? [],
            'published_at' => $plan->published_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
            'can_manage' => $canManage,
        ];
    }
}
