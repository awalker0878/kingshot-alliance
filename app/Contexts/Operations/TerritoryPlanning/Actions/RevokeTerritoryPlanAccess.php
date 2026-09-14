<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAccessGrant;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RevokeTerritoryPlanAccess
{
    public function __construct(private TerritoryPlanWriteState $state, private TerritoryPlanningAuthorization $authorization, private AuditRecorder $audit) {}

    public function handle(string $actorPlayerId, string $planId, string $grantId): void
    {
        DB::transaction(function () use ($actorPlayerId, $planId, $grantId): void {
            $context = $this->state->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            $grant = TerritoryPlanAccessGrant::query()->where('territory_plan_id', $planId)->whereKey($grantId)->lockForUpdate()->firstOrFail();
            if ($grant->revoked_at !== null) {
                return;
            }
            $grant->forceFill(['revoked_at' => now()])->save();
            $this->audit->record('territory.access.revoked', $context->actor, $context->plan, $context->plan->owner_alliance_id, ['grant_id' => $grantId]);
        });
    }
}
