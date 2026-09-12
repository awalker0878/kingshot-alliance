<?php

declare(strict_types=1);

namespace App\Workflows\KingdomGovernance\Actions;

use App\Contexts\GameWorld\Governance\Actions\ReconcileKingdomSystemRoles;
use App\Contexts\Operations\Access\Services\KingdomOperationsRoleProvisioner;
use Illuminate\Support\Facades\DB;

final readonly class ReconcileKingdomGovernancePolicy
{
    public function __construct(
        private ReconcileKingdomSystemRoles $governanceRoles,
        private KingdomOperationsRoleProvisioner $operationsPolicy,
    ) {}

    public function handle(string $actorPlayerId, string $kingdomId): void
    {
        DB::transaction(function () use ($actorPlayerId, $kingdomId): void {
            $roles = $this->governanceRoles->handle($actorPlayerId, $kingdomId);
            $this->operationsPolicy->provision($kingdomId, $roles['administrator'], $roles['eventCoordinator'], $roles['viewer']);
        });
    }
}
