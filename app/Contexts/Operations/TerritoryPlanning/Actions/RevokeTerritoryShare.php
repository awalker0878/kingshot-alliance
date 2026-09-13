<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryShare;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RevokeTerritoryShare
{
    public function __construct(private TerritoryPlanWriteState $state, private TerritoryPlanningAuthorization $authorization, private AuditRecorder $audit) {}

    public function handle(string $actorPlayerId, string $planId, string $shareId): void
    {
        DB::transaction(function () use ($actorPlayerId, $planId, $shareId): void {
            $context = $this->state->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            $share = TerritoryShare::query()->where('territory_plan_id', $planId)->whereKey($shareId)->lockForUpdate()->firstOrFail();
            if ($share->revoked_at !== null) {
                return;
            }
            $share->forceFill(['revoked_at' => now()])->save();
            $this->audit->record('territory.share.revoked', $context->actor, $context->plan, $context->plan->owner_alliance_id, ['share_id' => $shareId]);
        });
    }
}
