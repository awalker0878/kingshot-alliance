<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomMaps\Support;

use App\Contexts\GameWorld\KingdomMaps\Enums\MapDatasetConfidence;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use JsonException;
use RuntimeException;
use Tests\Support\RepositoryPath;

/** Per-case golden data; no application startup or cached mutable fixture state. */
trait ReadsTerritoryGeometryFixture
{
    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function fixture(): array
    {
        $contents = file_get_contents(RepositoryPath::fromRoot('tests/Contexts/GameWorld/KingdomMaps/Fixtures/territory-geometry.json'));
        if (! is_string($contents)) {
            throw new RuntimeException('Unable to read Territory geometry fixture.');
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Territory geometry fixture must decode to an object.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $fixture */
    private function dataset(array $fixture): KingdomMapDataset
    {
        $dataset = $fixture['dataset'] ?? null;
        if (! is_array($dataset) || ! is_array($dataset['data'] ?? null)) {
            throw new RuntimeException('Territory geometry dataset fixture shape is invalid.');
        }

        return new KingdomMapDataset(
            id: (string) ($dataset['id'] ?? ''),
            schemaVersion: (int) ($dataset['schema_version'] ?? 0),
            releaseStatus: (string) ($dataset['release_status'] ?? ''),
            releasedAt: (string) ($dataset['released_at'] ?? ''),
            observedAt: (string) ($dataset['observed_at'] ?? ''),
            gameVersion: is_string($dataset['game_version'] ?? null) ? $dataset['game_version'] : null,
            season: is_string($dataset['season'] ?? null) ? $dataset['season'] : null,
            predecessorId: is_string($dataset['predecessor_id'] ?? null) ? $dataset['predecessor_id'] : null,
            sourceLabel: (string) ($dataset['source_label'] ?? ''),
            sourceUri: is_string($dataset['source_uri'] ?? null) ? $dataset['source_uri'] : null,
            confidence: MapDatasetConfidence::from((string) ($dataset['confidence'] ?? '')),
            checksum: (string) ($dataset['checksum'] ?? ''),
            data: $dataset['data'],
        );
    }
}
