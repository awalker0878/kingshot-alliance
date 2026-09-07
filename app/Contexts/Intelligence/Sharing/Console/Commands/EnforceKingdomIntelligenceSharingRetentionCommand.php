<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Console\Commands;

use App\Contexts\Intelligence\Sharing\Actions\EnforceKingdomIntelligenceSharingRetention;
use Illuminate\Console\Command;

final class EnforceKingdomIntelligenceSharingRetentionCommand extends Command
{
    protected $signature = 'kingdoms:enforce-sharing-retention {--limit=500}';

    protected $description = 'Enforce bounded KINGDOMS-005 consent/grant retention without deleting canonical observations.';

    public function handle(EnforceKingdomIntelligenceSharingRetention $retention): int
    {
        $limit = max(1, min(2000, (int) $this->option('limit')));
        $this->info(json_encode($retention->handle($limit), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
