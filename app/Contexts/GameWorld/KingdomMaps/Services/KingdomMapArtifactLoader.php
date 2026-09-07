<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Services;

use App\Contexts\GameWorld\KingdomMaps\Enums\MapDatasetConfidence;
use JsonException;
use RuntimeException;

final class KingdomMapArtifactLoader
{
    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function hydrate(array $data): array
    {
        $artifacts = $data['artifacts'] ?? null;
        if (! is_array($artifacts) || array_is_list($artifacts)) {
            throw new RuntimeException('Kingdom map schema V2 requires an artifact manifest.');
        }

        $facilityManifest = $artifacts['facilities'] ?? null;
        if (! is_array($facilityManifest) || array_is_list($facilityManifest)) {
            throw new RuntimeException('Kingdom map schema V2 requires a facilities artifact.');
        }

        $sources = $data['sources'] ?? [];
        if (! is_array($sources)) {
            throw new RuntimeException('Kingdom map facilities artifact cannot resolve provenance sources.');
        }
        $this->validateManifestProvenance($facilityManifest, $sources);

        $path = $facilityManifest['path'] ?? null;
        if (! is_string($path)
            || preg_match('#^resources/data/kingdom-maps/artifacts/[a-z0-9][a-z0-9._-]{1,119}\.json$#', $path) !== 1) {
            throw new RuntimeException('Kingdom map facilities artifact path is invalid.');
        }
        $expectedHash = $facilityManifest['sha256'] ?? null;
        if (! is_string($expectedHash) || preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1) {
            throw new RuntimeException('Kingdom map facilities artifact SHA-256 is invalid.');
        }

        $absolutePath = base_path($path);
        $raw = file_get_contents($absolutePath);
        if (! is_string($raw)) {
            throw new RuntimeException('Unable to read Kingdom map facilities artifact.');
        }
        if (! hash_equals($expectedHash, hash('sha256', $raw))) {
            throw new RuntimeException('Kingdom map facilities artifact checksum does not match its immutable manifest.');
        }

        try {
            $artifact = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Kingdom map facilities artifact contains invalid JSON.', previous: $exception);
        }
        if (! is_array($artifact) || ($artifact['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('Kingdom map facilities artifact must satisfy schema version 1.');
        }

        $artifactId = pathinfo($path, PATHINFO_FILENAME);
        if (($artifact['id'] ?? null) !== $artifactId) {
            throw new RuntimeException('Kingdom map facilities artifact identity does not match its immutable file name.');
        }
        if (! is_string($artifact['observed_at'] ?? null)) {
            throw new RuntimeException('Kingdom map facilities artifact observed_at is required.');
        }
        $facilities = $artifact['facilities'] ?? null;
        if (! is_array($facilities) || ! array_is_list($facilities)) {
            throw new RuntimeException('Kingdom map facilities artifact facilities must be a list.');
        }

        $bounds = $data['bounds'] ?? [];
        $minX = (int) ($bounds['x'] ?? 0);
        $minY = (int) ($bounds['y'] ?? 0);
        $maxX = $minX + (int) ($bounds['width'] ?? 0);
        $maxY = $minY + (int) ($bounds['height'] ?? 0);
        $keys = [];
        $counts = ['fortress' => 0, 'sanctuary' => 0, 'outpost' => 0];

        foreach ($facilities as $index => $facility) {
            if (! is_array($facility)) {
                throw new RuntimeException('Kingdom map facility entry must be an object.');
            }
            foreach (['key', 'name', 'category'] as $key) {
                if (! is_string($facility[$key] ?? null) || trim($facility[$key]) === '') {
                    throw new RuntimeException('Kingdom map facility '.$index.' has invalid '.$key.'.');
                }
            }
            if (isset($keys[$facility['key']])) {
                throw new RuntimeException('Kingdom map facility keys must be unique.');
            }
            $keys[$facility['key']] = true;
            if (! array_key_exists($facility['category'], $counts)) {
                throw new RuntimeException('Kingdom map facility category is unsupported.');
            }
            $counts[$facility['category']]++;
            if (! is_int($facility['x'] ?? null) || ! is_int($facility['y'] ?? null)) {
                throw new RuntimeException('Kingdom map facility coordinates must be integers.');
            }
            if ($facility['x'] < $minX || $facility['x'] >= $maxX || $facility['y'] < $minY || $facility['y'] >= $maxY) {
                throw new RuntimeException('Kingdom map facility coordinates must remain inside the released bounds.');
            }
            if ($facility['category'] === 'outpost'
                && (! is_int($facility['level'] ?? null) || $facility['level'] < 1)) {
                throw new RuntimeException('Kingdom map outpost level must be a positive integer.');
            }
            $this->validateFacilityProvenance($facility, $sources);
        }

        $data['facilities'] = $facilities;
        $data['facility_counts'] = $counts;
        $data['artifact_checksums'] = ['facilities' => $expectedHash];

        return $data;
    }

    /** @param array<string,mixed> $manifest @param array<string,mixed> $sources */
    private function validateManifestProvenance(array $manifest, array $sources): void
    {
        $this->validateConfidenceAndSources($manifest, $sources, 'facilities artifact manifest');
        if (($manifest['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('Kingdom map facilities artifact manifest schema version is unsupported.');
        }
    }

    /** @param array<string,mixed> $facility @param array<string,mixed> $sources */
    private function validateFacilityProvenance(array $facility, array $sources): void
    {
        $this->validateConfidenceAndSources($facility, $sources, 'facility '.$facility['key']);
    }

    /** @param array<string,mixed> $fact @param array<string,mixed> $sources */
    private function validateConfidenceAndSources(array $fact, array $sources, string $label): void
    {
        $confidenceValue = $fact['confidence'] ?? null;
        $confidence = is_string($confidenceValue) ? MapDatasetConfidence::tryFrom($confidenceValue) : null;
        if (! $confidence instanceof MapDatasetConfidence) {
            throw new RuntimeException('Kingdom map '.$label.' has an unsupported confidence.');
        }
        $provenance = $fact['provenance'] ?? null;
        if (! is_array($provenance) || ! array_is_list($provenance) || $provenance === []) {
            throw new RuntimeException('Kingdom map '.$label.' requires provenance.');
        }
        foreach ($provenance as $sourceId) {
            $source = is_string($sourceId) ? ($sources[$sourceId] ?? null) : null;
            if (! is_array($source)) {
                throw new RuntimeException('Kingdom map '.$label.' references an unknown provenance source.');
            }
            $ceilingValue = $source['confidence_ceiling'] ?? null;
            $ceiling = is_string($ceilingValue) ? MapDatasetConfidence::tryFrom($ceilingValue) : null;
            if (! $ceiling instanceof MapDatasetConfidence || $this->rank($confidence) > $this->rank($ceiling)) {
                throw new RuntimeException('Kingdom map '.$label.' exceeds its provenance confidence ceiling.');
            }
        }
    }

    private function rank(MapDatasetConfidence $confidence): int
    {
        return match ($confidence) {
            MapDatasetConfidence::Unknown => 0,
            MapDatasetConfidence::Disputed => 1,
            MapDatasetConfidence::CommunityObserved => 2,
            MapDatasetConfidence::VerifiedObservation => 3,
            MapDatasetConfidence::Official => 4,
        };
    }
}
