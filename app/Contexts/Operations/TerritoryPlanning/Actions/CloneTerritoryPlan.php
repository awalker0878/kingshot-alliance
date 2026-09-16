<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        return DB::transaction(function () use ($actorPlayerId, $sourcePlanId, $name): TerritoryPlanMutationReceipt {
            $context = $this->writeState->lock($actorPlayerId, $sourcePlanId);
            $this->authorization->authorizeView($context);

            $snapshot = $this->snapshots->build($context->plan);

            $planData = $snapshot['plan'] ?? null;
            if (! is_array($planData)) {
                throw $this->invalidSnapshot();
            }

            $ownerAllianceId = $planData['owner_alliance_id'] ?? null;
            if ($ownerAllianceId !== null && ! is_string($ownerAllianceId)) {
                throw $this->invalidSnapshot();
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
                (string) Str::uuid(),
                $this->rows($snapshot['alliances'] ?? null),
                $this->rows($snapshot['groups'] ?? null),
                $this->rows($snapshot['objects'] ?? null),
                $this->map($planData['planning_preferences'] ?? []),
                $this->rows($snapshot['annotations'] ?? []),
            );
        });
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->invalidSnapshot();
        }

        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw $this->invalidSnapshot();
            }
            $entry = [];
            foreach ($row as $key => $item) {
                if (! is_string($key)) {
                    throw $this->invalidSnapshot();
                }
                $entry[$key] = $item;
            }
            $rows[] = $entry;
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function map(mixed $value): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw $this->invalidSnapshot();
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidSnapshot();
            }
            $result[$key] = $item;
        }

        return $result;
    }

    private function invalidSnapshot(): ValidationException
    {
        return ValidationException::withMessages([
            'plan' => 'The source Territory plan snapshot is invalid and cannot be cloned.',
        ]);
    }
}
