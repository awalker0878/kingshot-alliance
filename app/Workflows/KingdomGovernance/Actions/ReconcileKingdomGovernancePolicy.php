<?php

declare(strict_types=1);

namespace App\Workflows\KingdomGovernance\Actions;

use App\Contexts\GameWorld\Governance\Actions\ReconcileKingdomSystemPolicy;
use App\Contexts\Operations\Access\Services\KingdomOperationsRoleProvisioner;

final readonly class ReconcileKingdomGovernancePolicy
{
    public function __construct(
        private ReconcileKingdomSystemPolicy $governancePolicy,
        private KingdomOperationsRoleProvisioner $operationsPolicy,
    ) {}

    public function handle(string $actorPlayerId, string $kingdomId): void
    {
        $roles = $this->governancePolicy->handle($actorPlayerId, $kingdomId);
        $this->operationsPolicy->provision($kingdomId, $roles['administrator'], $roles['eventCoordinator'], $roles['viewer']);
    }
}
