<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Console\Commands;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Console\Command;

final class BootstrapKingdomAdministratorCommand extends Command
{
    protected $signature = 'kingdoms:bootstrap-admin {kingdom} {player}';

    protected $description = 'Bootstrap the first Kingdom administrator to a Player without granting game authority to a Platform User.';

    public function handle(BootstrapKingdomAdministrator $bootstrap): int
    {
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

        $kingdom = Kingdom::query()->where('number', (int) $kingdomArgument)->first();
        if (! $kingdom instanceof Kingdom) {
            $this->error('No Kingdom exists with that number.');

            return self::FAILURE;
        }

        $playerInput = $this->argument('player');
        if (! is_string($playerInput)) {
            $this->error('No Player exists with that ID.');

            return self::FAILURE;
        }

        $player = Player::query()->find(trim($playerInput));
        if (! $player instanceof Player) {
            $this->error('No Player exists with that ID.');

            return self::FAILURE;
        }

        $assignment = $bootstrap->handle((string) $kingdom->id, (string) $player->id);
        $this->info(sprintf(
            'Bootstrapped Kingdom #%d administrator to Player %s.',
            $assignment->kingdomNumber,
            $assignment->playerId,
        ));

        return self::SUCCESS;
    }
}
