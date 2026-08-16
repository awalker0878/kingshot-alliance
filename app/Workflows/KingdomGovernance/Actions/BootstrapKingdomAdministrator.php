<?php

declare(strict_types=1);

namespace App\Workflows\KingdomGovernance\Actions;

use App\Contexts\GameWorld\Governance\Actions\BootstrapKingdomAdministrator as BootstrapGameWorldKingdomAdministrator;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Services\KingdomOperationsRoleProvisioner;
use Illuminate\Support\Facades\DB;

final readonly class BootstrapKingdomAdministrator
{
    public function __construct(
        private BootstrapGameWorldKingdomAdministrator $bootstrapGovernance,
        private KingdomOperationsRoleProvisioner $operationsRoles,
    ) {}

    public function handle(Kingdom $kingdom, Player $target): KingdomRoleAssignment
    {
        return DB::transaction(function () use ($kingdom, $target): KingdomRoleAssignment {
            $assignment = $this->bootstrapGovernance->handle($kingdom, $target);
            $this->operationsRoles->provision($kingdom);

            return $assignment;
        });
    }
}
