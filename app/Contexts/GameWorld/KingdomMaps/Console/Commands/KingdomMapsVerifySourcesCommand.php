<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Console\Commands;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\Services\KingdomMapSourceVerifier;
use Illuminate\Console\Command;

final class KingdomMapsVerifySourcesCommand extends Command
{
    protected $signature = 'kingdom-maps:verify-sources {release?}';

    protected $description = 'Verify Kingdom map source rights, confidence ceilings and source lineage.';

    public function handle(KingdomMapDatasetQuery $datasets, KingdomMapSourceVerifier $verifier): int
    {
        $release = $this->argument('release');
        $targets = is_string($release) && trim($release) !== ''
            ? [$datasets->require(trim($release))]
            : $datasets->all();

        if ($targets === []) {
            $this->components->error('No released Kingdom map datasets were found.');

            return self::FAILURE;
        }

        foreach ($targets as $dataset) {
            $result = $verifier->verify($dataset);
            $this->line(sprintf(
                '%s: valid=%s rights_complete=%s ksmapper_authorized=%s sources=%d',
                $dataset->id,
                $result['valid'] ? 'true' : 'false',
                $result['rights_complete'] ? 'true' : 'false',
                $result['ksmapper_authorized'] ? 'true' : 'false',
                $result['source_count'],
            ));
            foreach ($result['lineages'] as $lineage => $sourceIds) {
                $this->line(sprintf('  %s: %s', $lineage, implode(', ', $sourceIds)));
            }
            foreach ($result['errors'] as $error) {
                $this->components->error($error);
            }
            if (! $result['valid']) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
