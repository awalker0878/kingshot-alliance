<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Console\Commands;

use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionOperationalHealth;
use Illuminate\Console\Command;

final class KingdomIngestionHealthCommand extends Command
{
    protected $signature = 'kingdoms:ingestion-health {--json}';

    protected $description = 'Report bounded KINGDOMS-004 operational health signals for monitoring.';

    public function handle(KingdomIngestionOperationalHealth $health): int
    {
        $snapshot = $health->snapshot();

        if ((bool) $this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR));
        } else {
            foreach ($snapshot as $key => $value) {
                $this->line(sprintf('%s=%s', $key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value));
            }
        }

        return $snapshot['attentionRequired'] ? self::FAILURE : self::SUCCESS;
    }
}
