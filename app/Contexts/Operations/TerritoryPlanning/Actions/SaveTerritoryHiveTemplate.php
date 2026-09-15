<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryHiveTemplate;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use Illuminate\Support\Facades\DB;

final readonly class SaveTerritoryHiveTemplate
{
    public function __construct(
        private TerritoryPlanWriteState $writeState,
        private TerritoryPlanningAuthorization $authorization,
    ) {}

    /**
     * @param  array<string, mixed>  $preferences
     * @return array{id:string,kingdom_id:string,name:string,map_dataset_id:string,map_dataset_checksum:string,style:string,city_count:int,spacing:int,planning_preferences:array<string,mixed>}
     */
    public function handle(
        string $actorPlayerId,
        string $planId,
        string $name,
        string $style,
        int $cityCount,
        int $spacing,
        array $preferences,
    ): array {
        return DB::transaction(function () use ($actorPlayerId, $planId, $name, $style, $cityCount, $spacing, $preferences): array {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            $template = TerritoryHiveTemplate::query()->updateOrCreate(
                ['kingdom_id' => $context->plan->kingdom_id, 'name' => trim($name)],
                [
                    'map_dataset_id' => $context->plan->map_dataset_id,
                    'map_dataset_checksum' => $context->plan->map_dataset_checksum,
                    'style' => $style,
                    'city_count' => $cityCount,
                    'spacing' => $spacing,
                    'planning_preferences' => $preferences,
                    'created_by_player_id' => $actorPlayerId,
                ],
            );

            return [
                'id' => (string) $template->id,
                'kingdom_id' => (string) $template->kingdom_id,
                'name' => (string) $template->name,
                'map_dataset_id' => (string) $template->map_dataset_id,
                'map_dataset_checksum' => (string) $template->map_dataset_checksum,
                'style' => (string) $template->style,
                'city_count' => (int) $template->city_count,
                'spacing' => (int) $template->spacing,
                'planning_preferences' => is_array($template->planning_preferences) ? $template->planning_preferences : [],
            ];
        });
    }
}
