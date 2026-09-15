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

    /** @param array<string,mixed> $preferences */
    public function handle(
        string $actorPlayerId,
        string $planId,
        string $name,
        string $style,
        int $cityCount,
        int $spacing,
        array $preferences,
    ): TerritoryHiveTemplate {
        return DB::transaction(function () use ($actorPlayerId, $planId, $name, $style, $cityCount, $spacing, $preferences): TerritoryHiveTemplate {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);

            return TerritoryHiveTemplate::query()->updateOrCreate(
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
        });
    }
}
