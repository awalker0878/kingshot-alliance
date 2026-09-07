<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Console\Commands;

use App\Contexts\Intelligence\Ingestion\Actions\EnforceKingdomIngestionRetention;
use Illuminate\Console\Command;

final class EnforceKingdomIngestionRetentionCommand extends Command
{
    protected $signature = 'kingdoms:enforce-ingestion-retention';

    protected $description = 'Enforce KINGDOMS-004 operational retention without deleting canonical promoted history.';

    public function handle(EnforceKingdomIngestionRetention $retention): int
    {
        $this->info(json_encode($retention->handle(), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
