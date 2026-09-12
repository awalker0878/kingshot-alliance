<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Console\Commands;

use App\Contexts\Platform\AllianceAdministration\Services\PlatformUsageService;
use Illuminate\Console\Command;

final class CapturePlatformUsageCommand extends Command
{
    protected $signature = 'platform:capture-usage {--limit=500}';

    protected $description = 'Capture alliance usage and capacity snapshots.';

    public function handle(PlatformUsageService $usage): int
    {
        $captured = $usage->captureAll((int) $this->option('limit'));
        $this->info(sprintf('Captured usage for %d alliance(s).', $captured));

        return self::SUCCESS;
    }
}
