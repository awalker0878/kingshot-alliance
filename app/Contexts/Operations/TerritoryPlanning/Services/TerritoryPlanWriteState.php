<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\Alliance\Access\Queries\AllianceAuthorityFactsQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class TerritoryPlanWriteState
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AllianceAuthorityFactsQuery $allianceAuthority,
        private KingdomAuthorityFactsQuery $kingdomAuthority,
    ) {}

    public function lock(string $actorPlayerId, string $planId): TerritoryPlanMutationContext
    {
        $this->assertTransaction();
        $plan = TerritoryPlan::query()->whereKey($planId)->lockForUpdate()->firstOrFail();
        $actor = $this->players->lockCurrent($actorPlayerId);
        if ($actor->kingdomId !== (string) $plan->kingdom_id) {
            throw new AuthorizationException;
        }

        if ($plan->scope === TerritoryPlanScope::Alliance) {
            $allianceId = (string) $plan->owner_alliance_id;
            $facts = $this->allianceAuthority->lockCurrent($actorPlayerId, $allianceId);
            if ($facts === null || $facts->kingdomId !== (string) $plan->kingdom_id || $facts->allianceId !== $allianceId) {
                throw new AuthorizationException;
            }

            return new TerritoryPlanMutationContext($plan, $actor, $facts, null);
        }

        $facts = $this->kingdomAuthority->lockCurrent($actorPlayerId, (string) $plan->kingdom_id);
        if ($facts === null || $facts->kingdomId !== (string) $plan->kingdom_id) {
            throw new AuthorizationException;
        }

        return new TerritoryPlanMutationContext($plan, $actor, null, $facts);
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Territory plan write state must be acquired inside a database transaction.');
        }
    }
}
