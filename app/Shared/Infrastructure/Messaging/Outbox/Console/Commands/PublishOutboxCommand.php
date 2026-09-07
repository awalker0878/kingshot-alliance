<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\Outbox\Console\Commands;

use App\Shared\Infrastructure\Messaging\Outbox\Actions\PublishOutboxBatch;
use Illuminate\Console\Command;

final class PublishOutboxCommand extends Command
{
    protected $signature = 'outbox:publish {--limit=100}';

    protected $description = 'Publish eligible transactional outbox messages.';

    public function handle(PublishOutboxBatch $publisher): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $published = $publisher->handle($limit);
        $this->info(sprintf('Published %d outbox message(s).', $published));

        return self::SUCCESS;
    }
}
