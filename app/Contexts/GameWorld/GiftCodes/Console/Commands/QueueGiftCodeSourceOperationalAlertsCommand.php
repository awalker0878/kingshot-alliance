<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Console\Commands;

use App\Contexts\GameWorld\GiftCodes\Actions\QueueGiftCodeSourceOperationalAlerts;
use Illuminate\Console\Command;

final class QueueGiftCodeSourceOperationalAlertsCommand extends Command
{
    protected $signature = 'gift-codes:source-operational-alerts {--limit=100}';

    protected $description = 'Queue operational alerts for a bounded set of Gift Code sources.';

    public function handle(QueueGiftCodeSourceOperationalAlerts $action): int
    {
        $result = $action->handle(max(1, min(500, (int) $this->option('limit'))));
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
