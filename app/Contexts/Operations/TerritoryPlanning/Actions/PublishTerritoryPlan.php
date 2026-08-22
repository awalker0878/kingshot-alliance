<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
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
        private AuditRecorder $audit,
    ) {}

    public function handle(string $actorPlayerId, string $planId, int $expectedRevision): TerritoryPlanMutationReceipt
    {
        return DB::transaction(function () use ($actorPlayerId, $planId, $expectedRevision): TerritoryPlanMutationReceipt {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            if ((int) $context->plan->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['revision' => 'This plan changed before publication. Reload it and review the current layout.']);
            }
            $dataset = $this->datasets->require((string) $context->plan->map_dataset_id, (string) $context->plan->map_dataset_checksum);
            if (! $context->plan->objects()->exists()) {
                throw ValidationException::withMessages(['layout' => 'Add at least one planned object before publishing.']);
            }

            $snapshot = $this->snapshots->build($context->plan);
            $nextPublishedRevision = ((int) TerritoryPlanRevision::query()->where('territory_plan_id', $planId)->max('revision_number')) + 1;
            $publishedAt = now();
            $revision = TerritoryPlanRevision::query()->create([
                'territory_plan_id' => $planId,
                'revision_number' => $nextPublishedRevision,
                'schema_version' => 1,
                'map_dataset_id' => $dataset->id,
                'map_dataset_checksum' => $dataset->checksum,
                'snapshot' => $snapshot,
                'snapshot_checksum' => $this->snapshots->checksum($snapshot),
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

            return new TerritoryPlanMutationReceipt($planId, $expectedRevision, TerritoryPlanStatus::Published->value, (string) $revision->id);
        });
    }
}
