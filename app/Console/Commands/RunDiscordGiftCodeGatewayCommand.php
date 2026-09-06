<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contexts\GameWorld\GiftCodes\Services\DiscordGiftCodeGatewayClient;
use Illuminate\Console\Command;

final class RunDiscordGiftCodeGatewayCommand extends Command
{
    protected $signature = 'gift-codes:discord-gateway {--max-seconds=0}';

    protected $description = 'Run the Discord Gateway push transport for approved Gift Code sources';

    public function handle(DiscordGiftCodeGatewayClient $gateway): int
    {
        $handled = $gateway->run(max(0, min(86_400, (int) $this->option('max-seconds'))));
        $this->info(sprintf('Processed %d Discord Gift Code Gateway event(s).', $handled));

        return self::SUCCESS;
    }
}
