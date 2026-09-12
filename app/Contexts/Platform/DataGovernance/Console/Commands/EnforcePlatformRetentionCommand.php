<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Console\Commands;

use App\Contexts\Platform\DataGovernance\Actions\EnforcePlatformRetention;
use Illuminate\Console\Command;

final class EnforcePlatformRetentionCommand extends Command
{
    protected $signature = 'platform:enforce-retention {--limit=500 : Maximum records per retention category (1–500)}';

    protected $description = 'Enforce configured retention windows for operational records.';

    public function handle(EnforcePlatformRetention $retention): int
    {
        $this->info(json_encode($retention->handle((int) $this->option('limit')), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
