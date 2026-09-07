<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Console\Commands;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\Services\KingdomMapSourceVerifier;
use Illuminate\Console\Command;
use Throwable;

final class KingdomMapsValidateCommand extends Command
{
    protected $signature = 'kingdom-maps:validate';

    protected $description = 'Validate released Kingdom map schemas, artifacts, provenance and source policy.';

    public function handle(KingdomMapDatasetQuery $datasets, KingdomMapSourceVerifier $sources): int
    {
        try {
            $releases = $datasets->all();
            if ($releases === []) {
                $this->components->error('No released Kingdom map datasets were found.');

                return self::FAILURE;
            }

            foreach ($releases as $dataset) {
                $verification = $sources->verify($dataset);
                if (! $verification['valid']) {
                    $this->components->error('Source verification failed for '.$dataset->id.'.');
                    foreach ($verification['errors'] as $error) {
                        $this->line(' - '.$error);
                    }

                    return self::FAILURE;
                }
                $this->components->info(sprintf(
                    '%s: schema=%d sources=%d facilities=%d checksum=%s',
                    $dataset->id,
                    $dataset->schemaVersion,
                    $verification['source_count'],
                    count($dataset->data['facilities'] ?? []),
                    $dataset->checksum,
                ));
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
