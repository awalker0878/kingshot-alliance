<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Console\Commands;

use App\Contexts\Platform\Integrations\Actions\ProcessWebhookFanouts;
use App\Contexts\Platform\Integrations\Actions\QueueDueWebhookDeliveries;
use Illuminate\Console\Command;

final class QueueWebhookDeliveriesCommand extends Command
{
    protected $signature = 'integrations:queue-webhooks {--limit=100}';

    protected $description = 'Recover and queue due webhook deliveries.';

    public function handle(QueueDueWebhookDeliveries $queue, ProcessWebhookFanouts $fanouts): int
    {
        $expanded = $fanouts->handle(max(1, min(500, (int) $this->option('limit'))));
        $queued = $queue->handle(max(1, min(500, (int) $this->option('limit'))));
        $this->info(sprintf('Expanded %d webhook delivery row(s); queued %d due delivery job(s).', $expanded, $queued));

        return self::SUCCESS;
    }
}
