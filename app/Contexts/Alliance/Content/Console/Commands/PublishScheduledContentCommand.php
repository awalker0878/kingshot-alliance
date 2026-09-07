<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Console\Commands;

use App\Contexts\Alliance\Content\Actions\PublishScheduledContent;
use Illuminate\Console\Command;

final class PublishScheduledContentCommand extends Command
{
    protected $signature = 'content:publish-scheduled {--limit=100}';

    protected $description = 'Publish due scheduled alliance content.';

    public function handle(PublishScheduledContent $publisher): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $published = $publisher->handle($limit);
        $this->info(sprintf('Published %d scheduled content item(s).', $published));

        return self::SUCCESS;
    }
}
