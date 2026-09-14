<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Console\Commands;

use App\Contexts\Operations\TerritoryPlanning\Actions\PruneExpiredTerritoryRecoveryDrafts;
use Illuminate\Console\Command;

final class PruneTerritoryRecoveryDraftsCommand extends Command
{
    protected $signature = 'territory:prune-recovery {--limit=250}';

    protected $description = 'Remove a bounded page of expired private territory recovery drafts.';

    public function handle(PruneExpiredTerritoryRecoveryDrafts $prune): int
    {
        $this->info('Removed '.$prune->handle((int) $this->option('limit')).' expired territory recovery drafts.');

        return self::SUCCESS;
    }
}
