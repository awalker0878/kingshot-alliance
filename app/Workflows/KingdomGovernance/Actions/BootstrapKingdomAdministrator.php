<?php

declare(strict_types=1);

namespace App\Workflows\KingdomGovernance\Actions;

use App\Contexts\GameWorld\Governance\Actions\BootstrapKingdomAdministrator as BootstrapGameWorldKingdomAdministrator;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomAdministratorBootstrap;
use App\Contexts\Operations\Access\Services\KingdomOperationsRoleProvisioner;
use Illuminate\Support\Facades\DB;

final readonly class BootstrapKingdomAdministrator
{
    public function __construct(
        private BootstrapGameWorldKingdomAdministrator $bootstrapGovernance,
        private KingdomOperationsRoleProvisioner $operationsRoles,
    ) {}

    public function handle(string $kingdomId, string $targetPlayerId): KingdomAdministratorBootstrap
    {
        return DB::transaction(function () use ($kingdomId, $targetPlayerId): KingdomAdministratorBootstrap {
            $assignment = $this->bootstrapGovernance->handle($kingdomId, $targetPlayerId);
            $this->operationsRoles->provision(
                kingdomId: $assignment->kingdomId,
                administratorRoleId: $assignment->administratorRoleId,
                eventCoordinatorRoleId: $assignment->eventCoordinatorRoleId,
                viewerRoleId: $assignment->viewerRoleId,
            );

            return $assignment;
        });
    }
}
