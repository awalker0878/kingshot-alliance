<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Queries;

use App\Contexts\GameWorld\KingdomMaps\Enums\MapDatasetConfidence;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

final class KingdomMapDatasetQuery
{
    private const DIRECTORY = 'resources/data/kingdom-maps';

    /** @return list<KingdomMapDataset> */
    public function all(): array
    {
        $paths = glob(base_path(self::DIRECTORY.'/*.json')) ?: [];
        sort($paths);

        return array_map(fn (string $path): KingdomMapDataset => $this->load($path), $paths);
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

        if (! is_array($data)
            || ! is_string($data['id'] ?? null)
            || ! is_int($data['schema_version'] ?? null)
            || ! is_string($data['observed_at'] ?? null)
            || ! is_string($data['source_label'] ?? null)
            || ! is_string($data['confidence'] ?? null)
            || ! is_array($data['bounds'] ?? null)
            || ! is_array($data['object_types'] ?? null)
            || ! is_array($data['zones'] ?? null)
            || ! is_array($data['structures'] ?? null)
            || ! is_array($data['placement_rules'] ?? null)) {
            throw new RuntimeException('Kingdom map dataset does not satisfy schema version 1.');
        }

        $id = pathinfo($path, PATHINFO_FILENAME);
        if ($data['id'] !== $id) {
            throw new RuntimeException('Kingdom map dataset identity does not match its immutable file name.');
        }

        return new KingdomMapDataset(
            id: $id,
            schemaVersion: $data['schema_version'],
            observedAt: $data['observed_at'],
            sourceLabel: $data['source_label'],
            sourceUri: is_string($data['source_uri'] ?? null) ? $data['source_uri'] : null,
            confidence: MapDatasetConfidence::from($data['confidence']),
            checksum: hash('sha256', $raw),
            data: $data,
        );
    }
}
