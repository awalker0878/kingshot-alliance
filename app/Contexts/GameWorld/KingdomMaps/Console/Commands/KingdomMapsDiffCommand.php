<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Console\Commands;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\Services\KingdomMapReleaseDiff;
use Illuminate\Console\Command;

final class KingdomMapsDiffCommand extends Command
{
    protected $signature = 'kingdom-maps:diff {from} {to} {--json}';

    protected $description = 'Show semantic differences between two immutable Kingdom map releases.';

    public function handle(KingdomMapDatasetQuery $datasets, KingdomMapReleaseDiff $diff): int
    {
        $from = $datasets->require((string) $this->argument('from'));
        $to = $datasets->require((string) $this->argument('to'));
        $result = $diff->between($from, $to);

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'from' => $from->id,
                'to' => $to->id,
                'diff' => $result,
            ], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        foreach ($result as $section => $changes) {
            $this->components->info($section);
            foreach (['added', 'removed', 'changed'] as $kind) {
                $values = $changes[$kind] ?? [];
                $this->line(sprintf('  %s: %s', $kind, $values === [] ? 'none' : implode(', ', $values)));
            }
        }

        return self::SUCCESS;
    }
}
