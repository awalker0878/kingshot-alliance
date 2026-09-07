<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Console\Commands;

use App\Contexts\GameWorld\GiftCodes\Actions\ReconcileGiftCodeSourcePolicyChanges;
use Illuminate\Console\Command;

final class ReconcileGiftCodeSourcePoliciesCommand extends Command
{
    protected $signature = 'gift-codes:reconcile-source-policies {--limit=200}';

    protected $description = 'Reconcile bounded Gift Code trust and facts after approved-source policy changes.';

    public function handle(ReconcileGiftCodeSourcePolicyChanges $reconcile): int
    {
        $this->line(json_encode(
            $reconcile->handle(max(1, min(1000, (int) $this->option('limit')))),
            JSON_THROW_ON_ERROR,
        ));

        return self::SUCCESS;
    }
}
