<?php

declare(strict_types=1);

namespace App\Workflows\KingdomGovernance\Console\Commands;

use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Console\Command;

final class BootstrapKingdomAdministratorCommand extends Command
{
    protected $signature = 'kingdoms:bootstrap-admin {kingdom} {player}';

    protected $description = 'Bootstrap the first Kingdom administrator to a Player without granting game authority to a Platform User.';

    public function handle(
        BootstrapKingdomAdministrator $bootstrap,
        KingdomReferenceQuery $kingdoms,
        PlayerReferenceQuery $players,
    ): int {
        $kingdomInput = $this->argument('kingdom');
        if (! is_string($kingdomInput)) {
            $this->error('Kingdom must be an existing positive numeric Kingdom number.');

            return self::FAILURE;
        }

        $kingdomArgument = trim($kingdomInput);
        if ($kingdomArgument === '' || ! ctype_digit($kingdomArgument)) {
            $this->error('Kingdom must be an existing positive numeric Kingdom number.');

            return self::FAILURE;
        }

        $kingdom = $kingdoms->findActiveByNumber((int) $kingdomArgument);
        if ($kingdom === null) {
            $this->error('No active Kingdom exists with that number.');

            return self::FAILURE;
        }

        $playerInput = $this->argument('player');
        if (! is_string($playerInput)) {
            $this->error('No Player exists with that ID.');

            return self::FAILURE;
        }

        $player = $players->find(trim($playerInput));
        if ($player === null) {
            $this->error('No Player exists with that ID.');

            return self::FAILURE;
        }

        $assignment = $bootstrap->handle($kingdom->kingdomId, $player->playerId);
        $this->info(sprintf(
            'Bootstrapped Kingdom #%d administrator to Player %s.',
            $assignment->kingdomNumber,
            $assignment->playerId,
        ));

        return self::SUCCESS;
    }
}
