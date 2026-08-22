<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAlliance;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanGroup;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanObject;

final class TerritoryPlanSnapshotBuilder
{
    /**
     * @return array{
     *     schema_version: 1,
     *     plan: array{
     *         id: string,
     *         scope: string,
     *         kingdom_id: string,
     *         owner_alliance_id: string|null,
     *         name: string,
     *         head_revision: int,
     *         map_dataset_id: string,
     *         map_dataset_checksum: string,
     *         planning_preferences: array<string, mixed>
     *     },
     *     alliances: list<array<string, mixed>>,
     *     groups: list<array<string, mixed>>,
     *     objects: list<array<string, mixed>>
     * }
     */
    public function build(TerritoryPlan $plan): array
    {
        $plan->load(['planAlliances', 'groups', 'objects']);

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

            $objects[] = [
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
        }

        return [
            'schema_version' => 1,
            'plan' => [
                'id' => $plan->id,
                'scope' => $plan->scope->value,
                'kingdom_id' => $plan->kingdom_id,
                'owner_alliance_id' => $plan->owner_alliance_id,
                'name' => $plan->name,
                'head_revision' => $plan->revision,
                'map_dataset_id' => $plan->map_dataset_id,
                'map_dataset_checksum' => $plan->map_dataset_checksum,
                'planning_preferences' => $plan->planning_preferences ?? [],
            ],
            'alliances' => $alliances,
            'groups' => $groups,
            'objects' => $objects,
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function checksum(array $snapshot): string
    {
        return hash(
            'sha256',
            json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }
}
