<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Console\Commands;

use App\Contexts\GameWorld\GiftCodes\Actions\RunApprovedGiftCodeSourceIngestion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class IngestApprovedGiftCodeSourcesCommand extends Command
{
    protected $signature = 'gift-codes:ingest-approved-sources {--limit=10} {--after=} {--source=} {--cycle}';

    protected $description = 'Run bounded idempotent ingestion for active approved Gift Code sources.';

    public function handle(RunApprovedGiftCodeSourceIngestion $ingestion): int
    {
        $afterValue = $this->option('after');
        $afterOption = is_string($afterValue) ? trim($afterValue) : '';
        $sourceValue = $this->option('source');
        $source = is_string($sourceValue) && trim($sourceValue) !== '' ? trim($sourceValue) : null;
        $cursorKey = 'gift-codes:approved-source-ingestion';
        $cycle = (bool) $this->option('cycle') && $afterOption === '' && $source === null;
        $storedCursor = $cycle ? Cache::get($cursorKey) : null;
        $after = $afterOption !== ''
            ? $afterOption
            : (is_string($storedCursor) && $storedCursor !== '' ? $storedCursor : null);

        $result = $ingestion->handle(
            max(1, min(100, (int) $this->option('limit'))),
            $after,
            $source,
        );

        if ($cycle) {
            if ($result->nextSourceCursor === null) {
                Cache::forget($cursorKey);
            } else {
                Cache::forever($cursorKey, $result->nextSourceCursor);
            }
        }

        $this->line(json_encode($result->toArray(), JSON_THROW_ON_ERROR));

        return $result->failedSources > 0 ? self::FAILURE : self::SUCCESS;
    }
}
