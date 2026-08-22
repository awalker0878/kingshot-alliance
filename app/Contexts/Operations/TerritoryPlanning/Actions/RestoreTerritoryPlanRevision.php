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
        if (
            ! is_array($snapshot)
            || ! is_array($snapshot['alliances'] ?? null)
            || ! is_array($snapshot['groups'] ?? null)
            || ! is_array($snapshot['objects'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'revision' => 'This published revision cannot be restored because its snapshot is invalid.',
            ]);
        }

        DB::transaction(function () use ($actorPlayerId, $planId, $expectedRevision): void {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            if ((int) $context->plan->revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'revision' => 'This plan changed before the revision could be restored.',
                ]);
            }
        });

        $receipt = $this->save->handle(
            $actorPlayerId,
            $planId,
            $expectedRevision,
            $snapshot['alliances'],
            $snapshot['groups'],
            $snapshot['objects'],
            is_array($snapshot['plan']['planning_preferences'] ?? null)
                ? $snapshot['plan']['planning_preferences']
                : [],
        );

        $actor = $this->players->require($actorPlayerId);
        $plan = TerritoryPlan::query()->findOrFail($planId);
        $this->audit->record(
            'territory.plan.revision_restored',
            $actor,
            $plan,
            $plan->owner_alliance_id === null ? null : (string) $plan->owner_alliance_id,
            [
                'territory_plan_revision_id' => $revisionId,
                'source_revision_number' => (int) $revision->revision_number,
                'result_revision' => $receipt->revision,
            ],
        );

        return $receipt;
    }
}
