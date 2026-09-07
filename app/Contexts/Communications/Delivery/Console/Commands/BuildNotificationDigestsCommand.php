<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Console\Commands;

use App\Contexts\Communications\Delivery\Actions\BuildNotificationDigestDispatches;
use Illuminate\Console\Command;

final class BuildNotificationDigestsCommand extends Command
{
    protected $signature = 'notifications:build-digests {--limit=500}';

    protected $description = 'Group due recipient-selected digest routes into bounded idempotent dispatches.';

    public function handle(BuildNotificationDigestDispatches $digests): int
    {
        $limit = max(1, min(2000, (int) $this->option('limit')));
        $created = $digests->handle($limit);
        $this->info(sprintf('Built %d due notification digest dispatch(es).', $created));

        return self::SUCCESS;
    }
}
