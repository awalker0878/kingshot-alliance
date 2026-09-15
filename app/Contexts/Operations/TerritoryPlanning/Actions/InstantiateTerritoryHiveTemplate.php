<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryHiveTemplate;
use App\Contexts\Operations\TerritoryPlanning\Services\HiveLayoutGenerator;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class InstantiateTerritoryHiveTemplate
{
    public function __construct(
        private TerritoryPlanWriteState $writeState,
        private TerritoryPlanningAuthorization $authorization,
        private KingdomMapDatasetQuery $datasets,
        private HiveLayoutGenerator $generator,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $existingObjects
     * @return array<string, mixed>
     */
    public function handle(
        string $actorPlayerId,
        string $planId,
        string $templateId,
        array $existingObjects,
        string $allianceKey,
        int $centerX,
        int $centerY,
    ): array {
        return DB::transaction(function () use ($actorPlayerId, $planId, $templateId, $existingObjects, $allianceKey, $centerX, $centerY): array {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeView($context);
            $template = TerritoryHiveTemplate::query()->whereKey($templateId)->firstOrFail();
            if ($template->kingdom_id !== $context->plan->kingdom_id) {
                abort(404);
            }
            if ($template->map_dataset_id !== $context->plan->map_dataset_id
                || ! hash_equals($template->map_dataset_checksum, $context->plan->map_dataset_checksum)) {
                throw ValidationException::withMessages(['template' => 'This Hive template is pinned to a different map release.']);
            }
            $dataset = $this->datasets->require($template->map_dataset_id, $template->map_dataset_checksum);

            return $this->generator->preview(
                $dataset,
                $existingObjects,
                $template->style,
                $allianceKey,
                $centerX,
                $centerY,
                $template->city_count,
                $template->spacing,
                $template->planning_preferences ?? [],
            );
        });
    }
}
