<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;

final class TerritoryPlanSnapshotBuilder
{
    /** @return array<string,mixed> */
    public function build(TerritoryPlan $plan): array
    {
        $plan->load(['planAlliances', 'groups', 'objects']);

        return [
            'schema_version' => 1,
            'plan' => [
                'id' => (string) $plan->id,
                'scope' => $plan->scope->value,
                'kingdom_id' => (string) $plan->kingdom_id,
                'owner_alliance_id' => $plan->owner_alliance_id === null ? null : (string) $plan->owner_alliance_id,
                'name' => (string) $plan->name,
                'head_revision' => (int) $plan->revision,
                'map_dataset_id' => (string) $plan->map_dataset_id,
                'map_dataset_checksum' => (string) $plan->map_dataset_checksum,
                'planning_preferences' => $plan->planning_preferences ?? [],
            ],
            'alliances' => $plan->planAlliances->map(static fn ($row): array => [
                'key' => (string) $row->id,
                'alliance_id' => $row->alliance_id === null ? null : (string) $row->alliance_id,
                'external_name' => $row->external_name,
                'external_tag' => $row->external_tag,
                'display_name' => (string) $row->display_name,
                'presentation_color' => (string) $row->presentation_color,
                'sort_order' => (int) $row->sort_order,
                'visible' => (bool) $row->visible,
                'locked' => (bool) $row->locked,
            ])->values()->all(),
            'groups' => $plan->groups->map(static fn ($row): array => ['key' => (string) $row->id, 'label' => $row->label])->values()->all(),
            'objects' => $plan->objects->map(static fn ($row): array => [
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
            ])->values()->all(),
        ];
    }

    /** @param array<string,mixed> $snapshot */
    public function checksum(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
