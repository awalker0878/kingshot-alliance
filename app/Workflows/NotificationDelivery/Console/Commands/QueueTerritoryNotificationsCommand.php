<?php

declare(strict_types=1);

namespace App\Workflows\NotificationDelivery\Console\Commands;

use App\Workflows\NotificationDelivery\Actions\QueueTerritoryNotifications;
use Illuminate\Console\Command;

final class QueueTerritoryNotificationsCommand extends Command
{
    protected $signature = 'notifications:queue-territory {--pages=10} {--page-size=50}';

    protected $description = 'Queue bounded pages of committed Territory collaboration notifications';

    public function handle(QueueTerritoryNotifications $queue): int
    {
        $result = $queue->handle((int) $this->option('pages'), (int) $this->option('page-size'));
        $this->info(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
