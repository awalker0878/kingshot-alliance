<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Exceptions\TerritoryRevisionConflict;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryLayoutContract;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RestoreTerritoryPlanRevision
{
    public function __construct(
        private TerritoryPlanWriteState $writeState,
        private TerritoryPlanningAuthorization $authorization,
        private SaveTerritoryPlan $save,
        private TerritoryLayoutContract $contract,
        private TerritoryPlanSnapshotBuilder $snapshots,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $planId,
        string $revisionId,
        int $expectedRevision,
    ): TerritoryPlanMutationReceipt {
        return DB::transaction(function () use ($actorPlayerId, $planId, $revisionId, $expectedRevision): TerritoryPlanMutationReceipt {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            if ($context->plan->revision !== $expectedRevision) {
                throw new TerritoryRevisionConflict($expectedRevision, $context->plan->revision);
            }

            $revision = TerritoryPlanRevision::query()
                ->where('territory_plan_id', $planId)
                ->sharedLock()->findOrFail($revisionId);
            $snapshot = $revision->snapshot;
            if ($revision->schema_version !== TerritoryLayoutContract::SCHEMA_VERSION
                || ($snapshot['schema_version'] ?? null) !== TerritoryLayoutContract::SCHEMA_VERSION
                || ! hash_equals($revision->snapshot_checksum, $this->snapshots->checksum($snapshot))
                || $revision->map_dataset_id !== $context->plan->map_dataset_id
                || $revision->map_dataset_checksum !== $context->plan->map_dataset_checksum) {
                throw $this->invalidSnapshot();
            }
            $snapshot = $this->contract->decode(json_encode($snapshot, JSON_THROW_ON_ERROR));
            $alliances = $this->rows($snapshot['alliances'] ?? null);
            $groups = $this->rows($snapshot['groups'] ?? null);
            $objects = $this->rows($snapshot['objects'] ?? null);
            $planData = $snapshot['plan'] ?? null;
            if (! is_array($planData)) {
                throw $this->invalidSnapshot();
            }
            if (($planData['map_dataset_id'] ?? null) !== $revision->map_dataset_id
                || ($planData['map_dataset_checksum'] ?? null) !== $revision->map_dataset_checksum) {
                throw $this->invalidSnapshot();
            }
            $preferences = $planData['planning_preferences'] ?? [];
            if (! is_array($preferences)) {
                throw $this->invalidSnapshot();
            }

            $receipt = $this->save->handle(
                $actorPlayerId,
                $planId,
                $expectedRevision,
                $alliances,
                $groups,
                $objects,
                $preferences,
            );

            $plan = $context->plan->refresh();
            $this->audit->record(
                'territory.plan.revision_restored',
                $context->actor,
                $plan,
                $plan->owner_alliance_id,
                [
                    'territory_plan_revision_id' => $revisionId,
                    'source_revision_number' => $revision->revision_number,
                    'result_revision' => $receipt->revision,
                ],
            );

            return $receipt;
        });
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $value): array
    {
        if (! is_array($value)) {
            throw $this->invalidSnapshot();
        }

        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                throw $this->invalidSnapshot();
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function invalidSnapshot(): ValidationException
    {
        return ValidationException::withMessages([
            'revision' => 'This published revision cannot be restored because its snapshot is invalid.',
        ]);
    }
}
