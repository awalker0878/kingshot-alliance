<?php

declare(strict_types=1);

namespace App\Workflows\KingdomGovernance\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\Governance\Actions\RepairKingdomAdministratorAssignment;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomAdministratorBootstrap;
use App\Contexts\Operations\Access\Services\KingdomOperationsRoleProvisioner;
use App\Contexts\Platform\Administration\Actions\AuthorizePlatformOperatorWrite;
use Illuminate\Support\Facades\DB;

final readonly class RecoverKingdomAdministrator
{
    public function __construct(
        private AuthorizePlatformOperatorWrite $platformAuthorization,
        private RepairKingdomAdministratorAssignment $repairGovernance,
        private KingdomOperationsRoleProvisioner $operationsRoles,
    ) {}

    public function handle(AccountIdentity $operator, string $kingdomId, string $targetPlayerId, string $reason, bool $replaceExisting = false): KingdomAdministratorBootstrap
    {
        return DB::transaction(function () use ($operator, $kingdomId, $targetPlayerId, $reason, $replaceExisting): KingdomAdministratorBootstrap {
            $this->platformAuthorization->handle($operator);
            $assignment = $this->repairGovernance->handle($operator, $kingdomId, $targetPlayerId, $reason, $replaceExisting);
            $this->operationsRoles->provision(
                $assignment->kingdomId,
                $assignment->administratorRoleId,
                $assignment->eventCoordinatorRoleId,
                $assignment->viewerRoleId,
            );

            return $assignment;
        });
    }
}
