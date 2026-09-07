<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Console\Commands;

use App\Contexts\Operations\Participation\Reminders\Actions\QueueDueEventReminders;
use Illuminate\Console\Command;

final class QueueEventRemindersCommand extends Command
{
    protected $signature = 'events:queue-reminders {--limit=100}';

    protected $description = 'Materialize and queue due Event reminders.';

    public function handle(QueueDueEventReminders $queue): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $queued = $queue->handle($limit);
        $this->info(sprintf('Queued %d due Event reminder(s).', $queued));

        return self::SUCCESS;
    }
}
