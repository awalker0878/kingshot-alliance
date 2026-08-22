<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
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
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $planId,
        string $revisionId,
        int $expectedRevision,
    ): TerritoryPlanMutationReceipt {
        $revision = TerritoryPlanRevision::query()
            ->where('territory_plan_id', $planId)
            ->findOrFail($revisionId);
        $snapshot = $revision->snapshot;
        $alliances = $this->rows($snapshot['alliances'] ?? null);
        $groups = $this->rows($snapshot['groups'] ?? null);
        $objects = $this->rows($snapshot['objects'] ?? null);
        $planData = $snapshot['plan'] ?? null;
        if (! is_array($planData)) {
            throw $this->invalidSnapshot();
        }
        $preferences = $planData['planning_preferences'] ?? [];
        if (! is_array($preferences)) {
            throw $this->invalidSnapshot();
        }

        DB::transaction(function () use ($actorPlayerId, $planId, $expectedRevision): void {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            if ($context->plan->revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'revision' => 'This plan changed before the revision could be restored.',
                ]);
            }
        });

        $receipt = $this->save->handle(
            $actorPlayerId,
            $planId,
            $expectedRevision,
            $alliances,
            $groups,
            $objects,
            $preferences,
        );

        $actor = $this->players->require($actorPlayerId);
        $plan = TerritoryPlan::query()->findOrFail($planId);
        $this->audit->record(
            'territory.plan.revision_restored',
            $actor,
            $plan,
            $plan->owner_alliance_id,
            [
                'territory_plan_revision_id' => $revisionId,
                'source_revision_number' => $revision->revision_number,
                'result_revision' => $receipt->revision,
            ],
        );

        return $receipt;
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
