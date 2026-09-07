<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Console\Commands;

use App\Contexts\Platform\Integrations\Actions\QueueDueWebhookDeliveries;
use Illuminate\Console\Command;

final class QueueWebhookDeliveriesCommand extends Command
{
    protected $signature = 'integrations:queue-webhooks {--limit=100}';

    protected $description = 'Recover and queue due webhook deliveries.';

    public function handle(QueueDueWebhookDeliveries $queue): int
    {
        $queued = $queue->handle(max(1, min(500, (int) $this->option('limit'))));
        $this->info(sprintf('Queued %d due webhook delivery job(s).', $queued));

        return self::SUCCESS;
    }
}
