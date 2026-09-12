<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\Alliance\Access\Queries\AllianceAuthorityFactsQuery;
use App\Contexts\Alliance\Access\ValueObjects\AllianceAuthorityFacts;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomAuthorityFacts;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class TerritoryPlanWriteState
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AllianceReferenceQuery $alliances,
        private KingdomReferenceQuery $kingdoms,
        private AllianceAuthorityFactsQuery $allianceAuthority,
        private KingdomAuthorityFactsQuery $kingdomAuthority,
    ) {}

    public function lock(string $actorPlayerId, string $planId): TerritoryPlanMutationContext
    {
        $this->assertTransaction();
        $route = TerritoryPlan::query()->whereKey($planId)->firstOrFail(['id', 'scope', 'kingdom_id', 'owner_alliance_id']);

        return $this->lockRoute($actorPlayerId, $route);
    }

    public function lockForEvent(string $actorPlayerId, string $planId, TerritoryPlanScope $scope, string $kingdomId, ?string $allianceId): TerritoryPlanMutationContext
    {
        $this->assertTransaction();
        $route = TerritoryPlan::query()->whereKey($planId)->firstOrFail(['id', 'scope', 'kingdom_id', 'owner_alliance_id']);
        if ($route->scope !== $scope || $route->kingdom_id !== $kingdomId || $route->owner_alliance_id !== $allianceId) {
            throw ValidationException::withMessages(['territory_plan_revision_id' => 'The Event and Territory plan must have the same owning scope.']);
        }

        return $this->lockRoute($actorPlayerId, $route);
    }

    private function lockRoute(string $actorPlayerId, TerritoryPlan $route): TerritoryPlanMutationContext
    {
        [$actor, $alliance, $kingdom] = $this->lockCreation($actorPlayerId, $route->scope, (string) $route->kingdom_id, $route->owner_alliance_id);
        $plan = TerritoryPlan::query()->whereKey($route->id)->lockForUpdate()->firstOrFail();
        if ($plan->scope !== $route->scope || $plan->kingdom_id !== $route->kingdom_id || $plan->owner_alliance_id !== $route->owner_alliance_id) {
            throw new AuthorizationException('The Territory plan scope changed while authority was being acquired.');
        }

        return new TerritoryPlanMutationContext($plan, $actor, $alliance, $kingdom);
    }

    /** @return array{PlayerReference,?AllianceAuthorityFacts,?KingdomAuthorityFacts} */
    public function lockCreation(string $actorPlayerId, TerritoryPlanScope $scope, string $kingdomId, ?string $allianceId): array
    {
        $this->assertTransaction();
        $allianceFacts = $kingdomFacts = null;
        if ($scope === TerritoryPlanScope::Alliance) {
            if ($allianceId === null || $allianceId === '') {
                throw new AuthorizationException;
            }
            $alliance = $this->alliances->lockCurrent($allianceId);
            if ($alliance->kingdomId !== $kingdomId) {
                throw new AuthorizationException;
            }
            $this->kingdoms->lockActiveShared($kingdomId);
            $allianceFacts = $this->allianceAuthority->lockCurrent($actorPlayerId, $allianceId);
            if ($allianceFacts === null || $allianceFacts->kingdomId !== $kingdomId) {
                throw new AuthorizationException;
            }
        } else {
            $kingdomFacts = $this->kingdomAuthority->lockCurrent($actorPlayerId, $kingdomId);
            if ($kingdomFacts === null) {
                throw new AuthorizationException;
            }
        }
        $actor = $this->players->lockCurrent($actorPlayerId);
        if ($actor->kingdomId !== $kingdomId) {
            throw new AuthorizationException;
        }

        return [$actor, $allianceFacts, $kingdomFacts];
    }

    public function lockLinkedAlliance(string $allianceId): AllianceReference
    {
        $this->assertTransaction();
        try {
            return $this->alliances->lockCurrentNowait($allianceId);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '55P03') {
                throw $exception;
            }
            throw ValidationException::withMessages(['alliances' => 'A linked Alliance is changing. Retry this plan update.']);
        }
    }

    public function lockLinkedPlayer(string $playerId): PlayerReference
    {
        $this->assertTransaction();
        try {
            return $this->players->lockCurrentNowait($playerId);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '55P03') {
                throw $exception;
            }
            throw ValidationException::withMessages(['objects' => 'A linked Governor is changing. Retry this plan update.']);
        }
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Territory plan write state must be acquired inside a database transaction.');
        }
    }
}
