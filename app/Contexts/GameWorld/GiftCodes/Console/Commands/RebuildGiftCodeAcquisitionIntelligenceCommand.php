<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Console\Commands;

use App\Contexts\GameWorld\GiftCodes\Actions\RebuildGiftCodeAcquisitionIntelligence;
use Illuminate\Console\Command;

final class RebuildGiftCodeAcquisitionIntelligenceCommand extends Command
{
    protected $signature = 'gift-codes:rebuild-acquisition-intelligence {--cluster-limit=500} {--source-limit=100}';

    protected $description = 'Advance one bounded cycle of Gift Code acquisition intelligence projections.';

    public function handle(RebuildGiftCodeAcquisitionIntelligence $action): int
    {
        $result = $action->cycle(
            max(1, min(1000, (int) $this->option('cluster-limit'))),
            max(1, min(500, (int) $this->option('source-limit'))),
        );
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
