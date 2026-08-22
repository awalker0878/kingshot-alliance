<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveTerritoryPlan
{
    public function __construct(private TerritoryPlanWriteState $writeState, private TerritoryPlanningAuthorization $authorization, private AuditRecorder $audit) {}

    public function handle(string $actorPlayerId, string $planId, int $expectedRevision): TerritoryPlanMutationReceipt
    {
        return DB::transaction(function () use ($actorPlayerId, $planId, $expectedRevision): TerritoryPlanMutationReceipt {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            if ((int) $context->plan->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['revision' => 'This plan changed before it could be archived.']);
            }
            $context->plan->forceFill(['status' => TerritoryPlanStatus::Archived, 'updated_by_player_id' => $actorPlayerId])->save();
            $this->audit->record('territory.plan.archived', $context->actor, $context->plan, $context->plan->owner_alliance_id === null ? null : (string) $context->plan->owner_alliance_id, ['revision' => $expectedRevision]);

            return new TerritoryPlanMutationReceipt($planId, $expectedRevision, TerritoryPlanStatus::Archived->value);
        });
    }
}
