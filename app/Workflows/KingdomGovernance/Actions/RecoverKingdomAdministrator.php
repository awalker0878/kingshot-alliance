<?php

declare(strict_types=1);

namespace App\Workflows\KingdomGovernance\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\Governance\Actions\RepairKingdomAdministratorAssignment;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomAdministratorBootstrap;
use App\Contexts\Operations\Access\Services\KingdomOperationsRoleProvisioner;
use App\Contexts\Platform\Administration\Services\PlatformAdministratorAuthorization;

final readonly class RecoverKingdomAdministrator
{
    public function __construct(
        private PlatformAdministratorAuthorization $platformAuthorization,
        private RepairKingdomAdministratorAssignment $repairGovernance,
        private KingdomOperationsRoleProvisioner $operationsRoles,
    ) {}

    public function handle(AccountIdentity $operator, string $kingdomId, string $targetPlayerId, string $reason, bool $replaceExisting = false): KingdomAdministratorBootstrap
    {
        $this->platformAuthorization->authorize($operator);
        $assignment = $this->repairGovernance->handle($operator, $kingdomId, $targetPlayerId, $reason, $replaceExisting);
        $this->operationsRoles->provision(
            $assignment->kingdomId,
            $assignment->administratorRoleId,
            $assignment->eventCoordinatorRoleId,
            $assignment->viewerRoleId,
        );

        return $assignment;
    }
}
