<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Console\Commands;

use App\Contexts\GameWorld\GiftCodes\Actions\QueueDueGiftCodeReminders;
use Illuminate\Console\Command;

final class QueueGiftCodeRemindersCommand extends Command
{
    protected $signature = 'gift-codes:queue-personal-reminders {--limit=100}';

    protected $description = 'Queue due personal Gift Code reminders in a bounded batch.';

    public function handle(QueueDueGiftCodeReminders $action): int
    {
        $result = $action->handle(max(1, min(500, (int) $this->option('limit'))));
        $this->info(sprintf('Queued %d personal Gift Code reminder(s).', $result));

        return self::SUCCESS;
    }
}
