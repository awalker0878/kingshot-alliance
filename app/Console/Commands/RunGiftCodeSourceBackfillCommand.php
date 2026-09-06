<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contexts\GameWorld\GiftCodes\Actions\RunGiftCodeSourceBackfill;
use Illuminate\Console\Command;

final class RunGiftCodeSourceBackfillCommand extends Command
{
    protected $signature = 'gift-codes:backfill-sources {--limit=5} {--source=} {--restart}';

    protected $description = 'Run bounded historical backfill independently from Gift Code freshness acquisition';

    public function handle(RunGiftCodeSourceBackfill $backfill): int
    {
        $sourceValue = $this->option('source');
        $source = is_string($sourceValue) && trim($sourceValue) !== '' ? trim($sourceValue) : null;
        $result = $backfill->handle(
            max(1, min(50, (int) $this->option('limit'))),
            $source,
            (bool) $this->option('restart'),
        );
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return $result['failedSources'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
