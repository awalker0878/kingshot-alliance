<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Console\Commands;

use App\Contexts\Operations\KingPerks\Actions\QueueDueKingPerkReminders;
use Illuminate\Console\Command;

final class QueueKingPerkRemindersCommand extends Command
{
    protected $signature = 'king-perks:queue-reminders {--limit=100}';

    protected $description = 'Queue due King Perk reminders in a bounded batch.';

    public function handle(QueueDueKingPerkReminders $action): int
    {
        $result = $action->handle(max(1, min(1000, (int) $this->option('limit'))));
        $this->info(sprintf('Queued %d King Perk reminder(s).', $result));

        return self::SUCCESS;
    }
}
