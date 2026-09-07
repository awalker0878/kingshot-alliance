<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Console\Commands;

use App\Contexts\Alliance\Content\Actions\QueuePublishedAnnouncementBroadcasts;
use Illuminate\Console\Command;

final class QueueAnnouncementBroadcastsCommand extends Command
{
    protected $signature = 'content:queue-announcement-broadcasts {--limit=25}';

    protected $description = 'Fan out published Alliance announcements to active members.';

    public function handle(QueuePublishedAnnouncementBroadcasts $queue): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $broadcasts = $queue->handle($limit);
        $this->info(sprintf('Queued %d Alliance announcement broadcast(s).', $broadcasts));

        return self::SUCCESS;
    }
}
