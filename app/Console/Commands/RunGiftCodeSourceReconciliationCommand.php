<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contexts\GameWorld\GiftCodes\Actions\RunGiftCodeSourceReconciliation;
use Illuminate\Console\Command;

final class RunGiftCodeSourceReconciliationCommand extends Command
{
    protected $signature = 'gift-codes:reconcile-sources {--limit=10} {--source=}';

    protected $description = 'Reconcile approved Gift Code sources against pull-provider state and detect missed push deliveries';

    public function handle(RunGiftCodeSourceReconciliation $reconcile): int
    {
        $sourceValue = $this->option('source');
        $source = is_string($sourceValue) && trim($sourceValue) !== '' ? trim($sourceValue) : null;
        $result = $reconcile->handle(
            max(1, min(100, (int) $this->option('limit'))),
            $source,
        );
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return $result['failedSources'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
