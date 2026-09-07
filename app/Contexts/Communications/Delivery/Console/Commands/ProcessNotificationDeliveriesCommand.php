<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Console\Commands;

use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDeliveries;
use Illuminate\Console\Command;

final class ProcessNotificationDeliveriesCommand extends Command
{
    protected $signature = 'notifications:deliver {--limit=100}';

    protected $description = 'Deliver due immediate external notifications with bounded retries.';

    public function handle(ProcessNotificationDeliveries $deliveries): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $processed = $deliveries->handle($limit);
        $this->info(sprintf('Processed %d immediate external notification delivery attempt(s).', $processed));

        return self::SUCCESS;
    }
}
