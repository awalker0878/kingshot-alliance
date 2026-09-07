<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Console\Commands;

use App\Contexts\Intelligence\Contributions\Actions\QueueDueContributionReports;
use Illuminate\Console\Command;

final class QueueContributionReportsCommand extends Command
{
    protected $signature = 'contributions:queue-reports {--limit=50}';

    protected $description = 'Queue due contribution reports through the notification outbox.';

    public function handle(QueueDueContributionReports $queue): int
    {
        $limit = max(1, min(250, (int) $this->option('limit')));
        $queued = $queue->handle($limit);
        $this->info(sprintf('Queued %d due contribution report(s).', $queued));

        return self::SUCCESS;
    }
}
