<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Console\Commands;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use Illuminate\Console\Command;

final class KingdomMapsListCommand extends Command
{
    protected $signature = 'kingdom-maps:list';

    protected $description = 'List immutable released Kingdom map datasets.';

    public function handle(KingdomMapDatasetQuery $datasets): int
    {
        $rows = [];
        foreach ($datasets->all() as $dataset) {
            $rows[] = [
                $dataset->id,
                (string) $dataset->schemaVersion,
                $dataset->releasedAt,
                $dataset->observedAt,
                $dataset->confidence->value,
                substr($dataset->checksum, 0, 12),
            ];
        }

        $this->table(['Release', 'Schema', 'Released', 'Observed', 'Confidence', 'SHA-256'], $rows);

        return self::SUCCESS;
    }
}
