<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CloneTerritoryPlan
{
    public function __construct(
        private TerritoryPlanWriteState $writeState,
        private TerritoryPlanningAuthorization $authorization,
        private TerritoryPlanSnapshotBuilder $snapshots,
        private CreateTerritoryPlan $create,
        private SaveTerritoryPlan $save,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $sourcePlanId,
        string $name,
    ): TerritoryPlanMutationReceipt {
        $snapshot = DB::transaction(function () use ($actorPlayerId, $sourcePlanId): array {
            $context = $this->writeState->lock($actorPlayerId, $sourcePlanId);
            $this->authorization->authorizeView($context);

            return $this->snapshots->build($context->plan);
        });

        $planData = $snapshot['plan'] ?? null;
        if (! is_array($planData)) {
            throw ValidationException::withMessages([
                'plan' => 'The source Territory plan snapshot is invalid and cannot be cloned.',
            ]);
        }

        $ownerAllianceId = $planData['owner_alliance_id'] ?? null;
        if ($ownerAllianceId !== null && ! is_string($ownerAllianceId)) {
            throw ValidationException::withMessages([
                'plan' => 'The source Territory plan owner is invalid and cannot be cloned.',
            ]);
        }

        $created = $this->create->handle(
            $actorPlayerId,
            TerritoryPlanScope::from((string) ($planData['scope'] ?? '')),
            (string) ($planData['kingdom_id'] ?? ''),
            $ownerAllianceId,
            $name,
            (string) ($planData['map_dataset_id'] ?? ''),
        );

        return $this->save->handle(
            $actorPlayerId,
            $created->planId,
            $created->revision,
            is_array($snapshot['alliances'] ?? null) ? $snapshot['alliances'] : [],
            is_array($snapshot['groups'] ?? null) ? $snapshot['groups'] : [],
            is_array($snapshot['objects'] ?? null) ? $snapshot['objects'] : [],
            is_array($planData['planning_preferences'] ?? null)
                ? $planData['planning_preferences']
                : [],
        );
    }
}
