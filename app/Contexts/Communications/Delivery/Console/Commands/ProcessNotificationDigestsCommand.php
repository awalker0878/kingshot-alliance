<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Console\Commands;

use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDigests;
use Illuminate\Console\Command;

final class ProcessNotificationDigestsCommand extends Command
{
    protected $signature = 'notifications:deliver-digests {--limit=100}';

    protected $description = 'Deliver due notification digests with bounded retry and route reauthorization.';

    public function handle(ProcessNotificationDigests $digests): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $processed = $digests->handle($limit);
        $this->info(sprintf('Processed %d notification digest delivery attempt(s).', $processed));

        return self::SUCCESS;
    }
}
