<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Console\Commands;

use App\Contexts\GameWorld\GiftCodes\Actions\RebuildGiftCodeContributorProjections;
use Illuminate\Console\Command;

final class RebuildGiftCodeContributorProjectionsCommand extends Command
{
    protected $signature = 'gift-codes:rebuild-contributor-projections {--limit=100}';

    protected $description = 'Advance one bounded cycle of Gift Code contributor projections.';

    public function handle(RebuildGiftCodeContributorProjections $action): int
    {
        $result = $action->cycle(max(1, min(500, (int) $this->option('limit'))));
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
