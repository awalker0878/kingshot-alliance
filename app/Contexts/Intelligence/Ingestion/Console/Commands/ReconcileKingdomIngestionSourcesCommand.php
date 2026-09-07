<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Console\Commands;

use App\Contexts\Intelligence\Ingestion\Actions\ReconcileKingdomIngestionSources;
use Illuminate\Console\Command;

final class ReconcileKingdomIngestionSourcesCommand extends Command
{
    protected $signature = 'kingdoms:reconcile-ingestion-sources {--limit=500}';

    protected $description = 'Disable Kingdom ingestion subscriptions whose approved source/version was revoked.';

    public function handle(ReconcileKingdomIngestionSources $reconcile): int
    {
        $revoked = $reconcile->handle(max(1, min(2000, (int) $this->option('limit'))));
        $this->info(sprintf('Disabled %d Kingdom ingestion subscription(s) with revoked source approval.', $revoked));

        return self::SUCCESS;
    }
}
