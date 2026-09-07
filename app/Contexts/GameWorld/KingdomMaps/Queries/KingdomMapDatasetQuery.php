<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Queries;

use App\Contexts\GameWorld\KingdomMaps\Enums\MapDatasetConfidence;
use App\Contexts\GameWorld\KingdomMaps\Services\KingdomMapArtifactLoader;
use App\Contexts\GameWorld\KingdomMaps\Services\KingdomMapFactProvenanceValidator;
use App\Contexts\GameWorld\KingdomMaps\Services\KingdomMapSchemaV2Validator;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

final class KingdomMapDatasetQuery
{
    private const DIRECTORY = 'resources/data/kingdom-maps';

    public function __construct(
        private readonly KingdomMapSchemaV2Validator $validator,
        private readonly KingdomMapFactProvenanceValidator $factProvenance,
        private readonly KingdomMapArtifactLoader $artifacts,
    ) {}

    /** @return list<KingdomMapDataset> */
    public function all(): array
    {
        $paths = glob(base_path(self::DIRECTORY.'/*.json')) ?: [];
        sort($paths);

        return array_map(fn (string $path): KingdomMapDataset => $this->load($path), $paths);
    }

    public function current(): KingdomMapDataset
    {
        $datasets = $this->all();
        if ($datasets === []) {
            throw new RuntimeException('No released Kingdom map dataset is available.');
        }

        usort($datasets, static fn (KingdomMapDataset $a, KingdomMapDataset $b): int => strcmp($b->releasedAt, $a->releasedAt));

        return $datasets[0];
    }

    public function require(string $datasetId, ?string $expectedChecksum = null): KingdomMapDataset
    {
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{1,119}$/', $datasetId)) {
            throw ValidationException::withMessages(['map_dataset_id' => 'The selected map dataset is invalid.']);
        }

        $path = base_path(self::DIRECTORY.'/'.$datasetId.'.json');
        if (! is_file($path)) {
            throw ValidationException::withMessages(['map_dataset_id' => 'The selected map dataset is unavailable.']);
        }

        $dataset = $this->load($path);
        if ($expectedChecksum !== null && ! hash_equals($dataset->checksum, $expectedChecksum)) {
            throw ValidationException::withMessages(['map_dataset_id' => 'The selected map dataset changed and must be reloaded before saving.']);
        }

        return $dataset;
    }

    public function loadPath(string $path): KingdomMapDataset
    {
        return $this->load($path);
    }

    private function load(string $path): KingdomMapDataset
    {
        $raw = file_get_contents($path);
        if (! is_string($raw)) {
            throw new RuntimeException('Unable to read Kingdom map dataset.');
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Kingdom map dataset contains invalid JSON.', previous: $exception);
        }

        if (! is_array($data)) {
            throw new RuntimeException('Kingdom map dataset root must be an object.');
        }

        $this->validator->validate($data);
        $this->factProvenance->validate($data);

        $id = pathinfo($path, PATHINFO_FILENAME);
        if ($data['id'] !== $id) {
            throw new RuntimeException('Kingdom map dataset identity does not match its immutable file name.');
        }

        $data = $this->artifacts->hydrate($data);

        $primarySourceUri = null;
        $primarySourceId = $data['primary_source_id'] ?? null;
        if (is_string($primarySourceId)) {
            $source = $data['sources'][$primarySourceId] ?? null;
            if (is_array($source) && is_string($source['uri'] ?? null)) {
                $primarySourceUri = $source['uri'];
            }
        }

        $confidence = MapDatasetConfidence::tryFrom((string) $data['confidence']);
        if (! $confidence instanceof MapDatasetConfidence) {
            throw new RuntimeException('Kingdom map dataset confidence is unsupported.');
        }

        return new KingdomMapDataset(
            id: $id,
            schemaVersion: 2,
            releaseStatus: (string) $data['release_status'],
            releasedAt: (string) $data['released_at'],
            observedAt: (string) $data['observed_at'],
            gameVersion: is_string($data['game_version'] ?? null) ? $data['game_version'] : null,
            season: is_string($data['season'] ?? null) ? $data['season'] : null,
            predecessorId: is_string($data['predecessor_id'] ?? null) ? $data['predecessor_id'] : null,
            sourceLabel: (string) $data['title'],
            sourceUri: $primarySourceUri,
            confidence: $confidence,
            checksum: hash('sha256', $raw),
            data: $data,
        );
    }
}
