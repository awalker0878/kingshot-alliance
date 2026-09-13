<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use App\Contexts\Operations\TerritoryPlanning\Exceptions\TerritoryRevisionConflict;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryLayoutContract;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryLayoutIdentityValidator;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PublishTerritoryPlan
{
    public function __construct(
        private TerritoryPlanWriteState $writeState,
        private TerritoryPlanningAuthorization $authorization,
        private TerritoryPlanSnapshotBuilder $snapshots,
        private KingdomMapDatasetQuery $datasets,
        private TerritoryLayoutContract $contract,
        private TerritoryLayoutIdentityValidator $identities,
        private PlacementValidator $placement,
        private AuditRecorder $audit,
    ) {}

    public function handle(string $actorPlayerId, string $planId, int $expectedRevision, string $layoutChecksum): TerritoryPlanMutationReceipt
    {
        return DB::transaction(function () use ($actorPlayerId, $planId, $expectedRevision, $layoutChecksum): TerritoryPlanMutationReceipt {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            if ($context->plan->status === TerritoryPlanStatus::Archived) {
                throw ValidationException::withMessages(['plan' => 'Archived plans are read-only. Clone this plan to resume editing.']);
            }
            if ((int) $context->plan->revision !== $expectedRevision) {
                throw new TerritoryRevisionConflict($expectedRevision, $context->plan->revision);
            }
            $dataset = $this->datasets->require((string) $context->plan->map_dataset_id, (string) $context->plan->map_dataset_checksum);
            if (! $context->plan->objects()->exists()) {
                throw ValidationException::withMessages(['layout' => 'Add at least one planned object before publishing.']);
            }

            $snapshot = $this->snapshots->build($context->plan);
            $checksum = $this->snapshots->checksum($snapshot);
            if (! hash_equals($checksum, $layoutChecksum)) {
                throw ValidationException::withMessages(['layout_checksum' => 'Save and review the current layout before publishing.']);
            }
            $layout = $this->contract->normalize($snapshot['alliances'], $snapshot['groups'], $snapshot['objects'], $snapshot['plan']['planning_preferences']);
            $this->identities->validateLinkedAlliances($context->plan, $layout['alliances']);
            $this->identities->validateGovernorIdentities($context->plan, $layout['alliances'], $layout['objects']);
            $validationObjects = array_map(static fn (array $object): array => [
                'key' => $object['key'], 'type' => $object['type'], 'x' => $object['x'], 'y' => $object['y'],
                'rotation' => $object['rotation'], 'alliance_key' => $object['alliance_key'],
                'variant_key' => $object['metadata']['variant_key'] ?? null,
            ], $layout['objects']);
            $validation = $this->placement->validate($dataset, $validationObjects, $layout['planning_preferences']);
            if (! $validation->valid()) {
                throw ValidationException::withMessages(['layout' => [json_encode($validation->toArray(), JSON_THROW_ON_ERROR)]]);
            }

            $existing = TerritoryPlanRevision::query()->where('territory_plan_id', $planId)
                ->where('snapshot_checksum', $checksum)->first();
            if ($existing !== null) {
                return new TerritoryPlanMutationReceipt($planId, $expectedRevision, TerritoryPlanStatus::Published->value, (string) $existing->id, $snapshot, $checksum);
            }
            $nextPublishedRevision = ((int) TerritoryPlanRevision::query()->where('territory_plan_id', $planId)->max('revision_number')) + 1;
            $publishedAt = now();
            $revision = TerritoryPlanRevision::query()->create([
                'territory_plan_id' => $planId,
                'revision_number' => $nextPublishedRevision,
                'schema_version' => TerritoryLayoutContract::SCHEMA_VERSION,
                'map_dataset_id' => $dataset->id,
                'map_dataset_checksum' => $dataset->checksum,
                'snapshot' => $snapshot,
                'snapshot_checksum' => $checksum,
                'published_by_player_id' => $actorPlayerId,
                'published_at' => $publishedAt,
                'created_at' => $publishedAt,
            ]);

            $context->plan->forceFill(['status' => TerritoryPlanStatus::Published, 'published_at' => $publishedAt, 'updated_by_player_id' => $actorPlayerId])->save();
            $this->audit->record('territory.plan.published', $context->actor, $context->plan, $context->plan->owner_alliance_id === null ? null : (string) $context->plan->owner_alliance_id, [
                'territory_plan_revision_id' => (string) $revision->id,
                'published_revision_number' => $nextPublishedRevision,
                'head_revision' => $expectedRevision,
                'map_dataset_id' => $dataset->id,
                'map_dataset_checksum' => $dataset->checksum,
            ]);

            return new TerritoryPlanMutationReceipt($planId, $expectedRevision, TerritoryPlanStatus::Published->value, (string) $revision->id, $snapshot, $checksum);
        });
    }
}
