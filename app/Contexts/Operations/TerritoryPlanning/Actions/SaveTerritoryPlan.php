<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryObjectType;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use App\Contexts\Operations\TerritoryPlanning\Exceptions\TerritoryRevisionConflict;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAlliance;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanGroup;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanObject;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryLayoutContract;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryLayoutIdentityValidator;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveTerritoryPlan
{
    public function __construct(
        private TerritoryPlanWriteState $writeState,
        private TerritoryPlanningAuthorization $authorization,
        private KingdomMapDatasetQuery $datasets,
        private PlacementValidator $placement,
        private TerritoryLayoutContract $contract,
        private TerritoryLayoutIdentityValidator $identities,
        private TerritoryPlanSnapshotBuilder $snapshots,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $alliances
     * @param  list<array<string, mixed>>  $groups
     * @param  list<array<string, mixed>>  $objects
     * @param  array<string, mixed>  $preferences
     */
    public function handle(
        string $actorPlayerId,
        string $planId,
        int $expectedRevision,
        array $alliances,
        array $groups,
        array $objects,
        array $preferences = [],
    ): TerritoryPlanMutationReceipt {
        $layout = $this->contract->normalize($alliances, $groups, $objects, $preferences);
        $normalizedAlliances = $layout['alliances'];
        $normalizedGroups = $layout['groups'];
        $normalizedObjects = $layout['objects'];
        $preferences = $layout['planning_preferences'];

        return DB::transaction(function () use (
            $actorPlayerId,
            $planId,
            $expectedRevision,
            $normalizedAlliances,
            $normalizedGroups,
            $normalizedObjects,
            $preferences,
        ): TerritoryPlanMutationReceipt {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            if ($context->plan->status === TerritoryPlanStatus::Archived) {
                throw ValidationException::withMessages(['plan' => 'Archived plans are read-only. Clone this plan to resume editing.']);
            }

            if ($context->plan->revision !== $expectedRevision) {
                throw new TerritoryRevisionConflict($expectedRevision, $context->plan->revision);
            }

            $this->identities->validateLinkedAlliances($context->plan, $normalizedAlliances);
            $this->identities->validateGovernorIdentities(
                $context->plan,
                $normalizedAlliances,
                $normalizedObjects,
            );

            $dataset = $this->datasets->require(
                $context->plan->map_dataset_id,
                $context->plan->map_dataset_checksum,
            );
            $validationObjects = array_map(
                static fn (array $object): array => [
                    'key' => $object['key'],
                    'type' => $object['type'],
                    'x' => $object['x'],
                    'y' => $object['y'],
                    'alliance_key' => $object['alliance_key'],
                    'rotation' => $object['rotation'],
                    'variant_key' => $object['metadata']['variant_key'] ?? null,
                ],
                $normalizedObjects,
            );
            $validation = $this->placement->validate(
                $dataset,
                $validationObjects,
                $preferences,
            );

            if (! $validation->valid()) {
                throw ValidationException::withMessages([
                    'layout' => [json_encode($validation->toArray(), JSON_THROW_ON_ERROR)],
                ]);
            }

            TerritoryPlanObject::query()->where('territory_plan_id', $planId)->delete();
            TerritoryPlanGroup::query()->where('territory_plan_id', $planId)->delete();
            TerritoryPlanAlliance::query()->where('territory_plan_id', $planId)->delete();

            $allianceIds = [];
            foreach ($normalizedAlliances as $alliance) {
                $row = TerritoryPlanAlliance::query()->create([
                    'territory_plan_id' => $planId,
                    'plan_key' => $alliance['key'],
                    'alliance_id' => $alliance['alliance_id'],
                    'external_name' => $alliance['external_name'],
                    'external_tag' => $alliance['external_tag'],
                    'display_name' => $alliance['display_name'],
                    'presentation_color' => $alliance['presentation_color'],
                    'sort_order' => $alliance['sort_order'],
                    'visible' => $alliance['visible'],
                    'locked' => $alliance['locked'],
                ]);
                $allianceIds[$alliance['key']] = (string) $row->id;
            }

            $groupIds = [];
            foreach ($normalizedGroups as $group) {
                $row = TerritoryPlanGroup::query()->create([
                    'territory_plan_id' => $planId,
                    'plan_key' => $group['key'],
                    'label' => $group['label'],
                ]);
                $groupIds[$group['key']] = (string) $row->id;
            }

            foreach ($normalizedObjects as $object) {
                TerritoryPlanObject::query()->create([
                    'territory_plan_id' => $planId,
                    'plan_key' => $object['key'],
                    'territory_plan_alliance_id' => $allianceIds[$object['alliance_key']],
                    'group_id' => $object['group_key'] === null
                        ? null
                        : $groupIds[$object['group_key']],
                    'object_type' => TerritoryObjectType::from($object['type']),
                    'player_id' => $object['player_id'],
                    'external_player_name' => $object['external_player_name'],
                    'label' => $object['label'],
                    'coordinate_x' => $object['x'],
                    'coordinate_y' => $object['y'],
                    'rotation' => $object['rotation'],
                    'sort_order' => $object['sort_order'],
                    'metadata' => $object['metadata'],
                ]);
            }

            $context->plan->forceFill([
                'planning_preferences' => $preferences,
                'revision' => $expectedRevision + 1,
                'status' => TerritoryPlanStatus::Draft,
                'updated_by_player_id' => $actorPlayerId,
            ])->save();

            $this->audit->record(
                'territory.plan.saved',
                $context->actor,
                $context->plan,
                $context->plan->owner_alliance_id,
                [
                    'revision' => $expectedRevision + 1,
                    'alliance_count' => count($normalizedAlliances),
                    'object_count' => count($normalizedObjects),
                    'warning_count' => count($validation->warnings),
                    'suggestion_count' => count($validation->suggestions),
                ],
            );

            $snapshot = $this->snapshots->build($context->plan);

            return new TerritoryPlanMutationReceipt(
                $planId,
                $expectedRevision + 1,
                TerritoryPlanStatus::Draft->value,
                snapshot: $snapshot,
                layoutChecksum: $this->snapshots->checksum($snapshot),
            );
        });
    }
}
