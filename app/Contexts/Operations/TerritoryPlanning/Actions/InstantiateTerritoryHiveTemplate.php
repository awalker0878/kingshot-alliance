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
        $objects = $this->objects($existingObjects);

        return DB::transaction(function () use ($actorPlayerId, $planId, $templateId, $objects, $allianceKey, $centerX, $centerY): array {
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
                $objects,
                $template->style,
                $allianceKey,
                $centerX,
                $centerY,
                $template->city_count,
                $template->spacing,
                $this->preferences($template->planning_preferences),
            );
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{key:string,type:string,x:int,y:int,alliance_key:string,rotation?:int}>
     */
    private function objects(array $rows): array
    {
        $objects = [];
        foreach ($rows as $row) {
            $key = $row['key'] ?? null;
            $type = $row['type'] ?? null;
            $x = $row['x'] ?? null;
            $y = $row['y'] ?? null;
            $objectAllianceKey = $row['alliance_key'] ?? null;
            $rotation = $row['rotation'] ?? null;
            if (! is_string($key) || $key === ''
                || ! is_string($type) || $type === ''
                || ! is_int($x) || ! is_int($y)
                || ! is_string($objectAllianceKey) || $objectAllianceKey === ''
                || ($rotation !== null && ! is_int($rotation))) {
                throw ValidationException::withMessages([
                    'existing_objects' => 'Existing Hive objects must use valid typed coordinates and identities.',
                ]);
            }

            $object = [
                'key' => $key,
                'type' => $type,
                'x' => $x,
                'y' => $y,
                'alliance_key' => $objectAllianceKey,
            ];
            if ($rotation !== null) {
                $object['rotation'] = $rotation;
            }
            $objects[] = $object;
        }

        return $objects;
    }

    /** @return array<string, mixed> */
    private function preferences(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \LogicException('Persisted Hive template preferences are invalid.');
        }

        $preferences = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new \LogicException('Persisted Hive template preferences are invalid.');
            }
            $preferences[$key] = $item;
        }

        return $preferences;
    }
}
