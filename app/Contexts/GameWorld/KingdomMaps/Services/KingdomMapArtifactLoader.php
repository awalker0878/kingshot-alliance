<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Services;

use App\Contexts\GameWorld\KingdomMaps\Enums\MapDatasetConfidence;
use JsonException;
use RuntimeException;

final class KingdomMapArtifactLoader
{
    public function __construct(private readonly KingdomMapSpatialArtifactValidator $spatial) {}

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
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
        $this->validateManifestProvenance($facilityManifest, $sources, 'facilities');
        $artifact = $this->read($facilityManifest, 'facilities');
        $expectedHash = $facilityManifest['sha256'];
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
        $extent = ['x' => $minX, 'y' => $minY, 'width' => $maxX - $minX, 'height' => $maxY - $minY];
        $data['layer_availability'] = [
            'facilities' => [
                'state' => 'materialized', 'available_count' => count($facilities),
                'expected_count' => count($facilities), 'extent' => $extent,
                'artifact_sha256' => $expectedHash, 'confidence' => $facilityManifest['confidence'],
                'observed_at' => $artifact['observed_at'], 'unavailable_reason' => null,
            ],
        ];
        $data['terrain_features'] = [];
        $data['resource_nodes'] = [];
        $data['spatial_diagnostics'] = [];
        $resourceArtifact = null;
        foreach (['terrain' => 'terrain', 'resources' => 'resource_nodes'] as $artifactKey => $layerKey) {
            $layer = $data['resource_layers'][$layerKey];
            $expectedCount = $artifactKey === 'terrain' ? $layer['lakes'] + $layer['mountains'] : $layer['count'];
            $manifest = $artifacts[$artifactKey] ?? null;
            if ($manifest === null) {
                if (($layer['data_state'] ?? null) !== 'authorized_source_corpus_reference') {
                    throw new RuntimeException('Kingdom map materialized '.$artifactKey.' layer has no artifact.');
                }
                $data['layer_availability'][$artifactKey] = [
                    'state' => 'unavailable', 'available_count' => 0, 'expected_count' => $expectedCount,
                    'extent' => null, 'artifact_sha256' => null, 'confidence' => $layer['confidence'],
                    'observed_at' => $data['observed_at'], 'unavailable_reason' => 'source_corpus_not_materialized',
                ];

                continue;
            }
            if (! is_array($manifest) || array_is_list($manifest) || ($layer['data_state'] ?? null) !== 'materialized') {
                throw new RuntimeException('Kingdom map '.$artifactKey.' artifact and materialization declaration disagree.');
            }
            $this->validateManifestProvenance($manifest, $sources, $artifactKey);
            $spatial = $this->read($manifest, $artifactKey);
            if ($artifactKey === 'terrain') {
                $this->spatial->terrain($spatial, $manifest, $data);
                $data['terrain_features'] = $spatial['features'];
                $count = count($data['terrain_features']);
            } else {
                $this->spatial->resources($spatial, $manifest, $data);
                $data['resource_nodes'] = $spatial['nodes'];
                $data['resource_ownership_semantics'] = $spatial['ownership_semantics'];
                $count = count($data['resource_nodes']);
                $resourceArtifact = $spatial;
            }
            $data['artifact_checksums'][$artifactKey] = $manifest['sha256'];
            $data['layer_availability'][$artifactKey] = [
                'state' => 'materialized', 'available_count' => $count, 'expected_count' => $expectedCount,
                'extent' => $extent, 'artifact_sha256' => $manifest['sha256'], 'confidence' => $manifest['confidence'],
                'observed_at' => $spatial['observed_at'], 'unavailable_reason' => null,
            ];
        }
        if ($resourceArtifact !== null) {
            if ($data['layer_availability']['terrain']['state'] !== 'materialized') {
                throw new RuntimeException('Kingdom map source consistency diagnostics require the pinned terrain artifact.');
            }
            $diagnostics = $this->spatial->diagnostics($data['terrain_features'], $data['resource_nodes'], $extent);
            if ($resourceArtifact['diagnostics'] !== $diagnostics) {
                throw new RuntimeException('Kingdom map spatial source diagnostics differ from the materialized records.');
            }
            $data['spatial_diagnostics'] = $diagnostics;
        }

        return $data;
    }

    /**
     * @param  array<string,mixed>  $manifest
     * @return array<string,mixed>
     */
    private function read(array $manifest, string $label): array
    {
        $path = $manifest['path'] ?? null;
        if (! is_string($path) || preg_match('#^resources/data/kingdom-maps/artifacts/[a-z0-9][a-z0-9._-]{1,119}\.json$#', $path) !== 1) {
            throw new RuntimeException('Kingdom map '.$label.' artifact path is invalid.');
        }
        $expected = $manifest['sha256'] ?? null;
        if (! is_string($expected) || preg_match('/^[a-f0-9]{64}$/', $expected) !== 1) {
            throw new RuntimeException('Kingdom map '.$label.' artifact SHA-256 is invalid.');
        }
        $root = realpath(base_path('resources/data/kingdom-maps/artifacts'));
        $resolved = realpath(base_path($path));
        if (! is_string($root) || ! is_string($resolved) || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) || ! is_file($resolved)) {
            throw new RuntimeException('Kingdom map '.$label.' artifact is unavailable or outside its owned directory.');
        }
        $size = filesize($resolved);
        if (! is_int($size) || $size > 4000000) {
            throw new RuntimeException('Kingdom map '.$label.' artifact exceeds bounded input size.');
        }
        $raw = file_get_contents($resolved, length: 4000001);
        if (! is_string($raw) || strlen($raw) > 4000000) {
            throw new RuntimeException('Unable to read bounded Kingdom map '.$label.' artifact.');
        }
        if (! hash_equals($expected, hash('sha256', $raw))) {
            throw new RuntimeException('Kingdom map '.$label.' artifact checksum does not match its immutable manifest.');
        }
        try {
            $artifact = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Kingdom map '.$label.' artifact contains invalid JSON.', previous: $exception);
        }
        if (! is_array($artifact) || array_is_list($artifact) || ($artifact['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('Kingdom map '.$label.' artifact must satisfy schema version 1.');
        }
        if (($artifact['id'] ?? null) !== pathinfo($path, PATHINFO_FILENAME)) {
            throw new RuntimeException('Kingdom map '.$label.' artifact identity does not match its immutable file name.');
        }
        if (! is_string($artifact['observed_at'] ?? null)) {
            throw new RuntimeException('Kingdom map '.$label.' artifact observed_at is required.');
        }

        return $artifact;
    }

    /**
     * @param  array<string,mixed>  $manifest
     * @param  array<string,mixed>  $sources
     */
    private function validateManifestProvenance(array $manifest, array $sources, string $label): void
    {
        $this->validateConfidenceAndSources($manifest, $sources, $label.' artifact manifest');
        if (($manifest['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('Kingdom map '.$label.' artifact manifest schema version is unsupported.');
        }
    }

    /**
     * @param  array<string,mixed>  $facility
     * @param  array<string,mixed>  $sources
     */
    private function validateFacilityProvenance(array $facility, array $sources): void
    {
        $this->validateConfidenceAndSources($facility, $sources, 'facility '.$facility['key']);
    }

    /**
     * @param  array<string,mixed>  $fact
     * @param  array<string,mixed>  $sources
     */
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
