<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Console\Commands;

use App\Contexts\Operations\KingPerks\Actions\QueueDueKingPerkReminders;
use Illuminate\Console\Command;

final class QueueKingPerkRemindersCommand extends Command
{
    protected $signature = 'king-perks:queue-reminders {--limit=100 : Maximum recipient attempts or empty-source visits, including replay and denial}';

    protected $description = 'Queue due King Perk reminders in a bounded batch.';

    public function handle(QueueDueKingPerkReminders $action): int
    {
        $result = $action->handle(max(1, min(1000, (int) $this->option('limit'))));
        $this->info(sprintf(
            'King Perks: work=%d sources=%d recipients=%d queued=%d superseded_pages=%d expired_cursors_removed=%d',
            $result->workUnits, $result->sourcesExamined, $result->recipientsExamined,
            $result->queued, $result->supersededPages, $result->expiredCursorsRemoved,
        ));

        return self::SUCCESS;
    }
}
