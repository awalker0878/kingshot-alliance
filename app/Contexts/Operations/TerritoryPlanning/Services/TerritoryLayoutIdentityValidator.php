<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use Illuminate\Validation\ValidationException;

final readonly class TerritoryLayoutIdentityValidator
{
    public function __construct(private TerritoryPlanWriteState $writeState, private PlayerMembershipQuery $memberships) {}

    /** @param list<array<string, mixed>> $planAlliances */
    public function validateLinkedAlliances(TerritoryPlan $plan, array $planAlliances): void
    {
        $scope = $plan->scope;
        $kingdomId = $plan->kingdom_id;
        $ownerAllianceId = $plan->owner_alliance_id;

        if ($scope === TerritoryPlanScope::Alliance) {
            $only = $planAlliances[0] ?? null;
            if (
                count($planAlliances) !== 1
                || ! is_array($only)
                || $only['alliance_id'] !== $ownerAllianceId
                || $only['external_name'] !== null
            ) {
                throw ValidationException::withMessages([
                    'alliances' => 'An Alliance-scoped plan must contain exactly its owning Alliance layer.',
                ]);
            }
        }

        foreach ($planAlliances as $planAlliance) {
            $allianceId = $planAlliance['alliance_id'];
            if (! is_string($allianceId)) {
                continue;
            }

            $reference = $this->writeState->lockLinkedAlliance($allianceId);
            if ($reference->kingdomId !== $kingdomId) {
                throw ValidationException::withMessages([
                    'alliances' => 'Linked Alliances must belong to the plan Kingdom.',
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $planAlliances
     * @param  list<array<string, mixed>>  $objects
     */
    public function validateGovernorIdentities(
        TerritoryPlan $plan,
        array $planAlliances,
        array $objects,
    ): void {
        $kingdomId = $plan->kingdom_id;
        $allianceByKey = [];
        foreach ($planAlliances as $planAlliance) {
            $allianceByKey[$planAlliance['key']] = $planAlliance['alliance_id'];
        }

        $players = [];
        foreach ($objects as $object) {
            if (! is_string($object['player_id'])) {
                continue;
            }

            $playerId = $object['player_id'];
            $linkedAllianceId = $allianceByKey[$object['alliance_key']] ?? null;
            if (! is_string($linkedAllianceId)) {
                throw ValidationException::withMessages([
                    'objects' => 'A linked Governor can only be placed on a linked Alliance layer.',
                ]);
            }

            if (isset($players[$playerId])) {
                throw ValidationException::withMessages([
                    'objects' => 'A linked Governor may occupy only one city in a plan.',
                ]);
            }

            $players[$playerId] = $linkedAllianceId;
        }

        ksort($players);
        foreach ($players as $playerId => $allianceId) {
            $player = $this->writeState->lockLinkedPlayer($playerId);
            if ($player->kingdomId !== $kingdomId) {
                throw ValidationException::withMessages([
                    'objects' => 'Linked Governors must belong to the plan Kingdom.',
                ]);
            }

            if (! $this->memberships->lockActiveMember($allianceId, $playerId)) {
                throw ValidationException::withMessages([
                    'objects' => 'Linked Governors must be active members of their planned Alliance layer.',
                ]);
            }
        }
    }
}
