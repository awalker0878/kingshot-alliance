<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Console\Commands;

use App\Contexts\Intelligence\Ingestion\Actions\QueueDueKingdomIngestionSubscriptions;
use Illuminate\Console\Command;

final class QueueKingdomIngestionCommand extends Command
{
    protected $signature = 'kingdoms:queue-ingestion {--limit=100}';

    protected $description = 'Queue due approved Kingdom ingestion subscriptions.';

    public function handle(QueueDueKingdomIngestionSubscriptions $queue): int
    {
        $queued = $queue->handle(max(1, min(500, (int) $this->option('limit'))));
        $this->info(sprintf('Queued %d due Kingdom ingestion subscription(s).', $queued));

        return self::SUCCESS;
    }
}
