<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Console\Commands;

use App\Contexts\Alliance\Content\Actions\QueuePublishedAnnouncementBroadcasts;
use Illuminate\Console\Command;

final class QueueAnnouncementBroadcastsCommand extends Command
{
    protected $signature = 'content:queue-announcement-broadcasts {--limit=25} {--recipients=100}';

    protected $description = 'Resume bounded published Alliance announcement audiences.';

    public function handle(QueuePublishedAnnouncementBroadcasts $queue): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $recipients = max(1, min(1000, (int) $this->option('recipients')));
        $broadcasts = $queue->handle($limit, $recipients);
        $this->info(sprintf('Completed queueing %d Alliance announcement broadcast(s).', $broadcasts));

        return self::SUCCESS;
    }
}
