<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomMaps\Feature;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\Services\KingdomMapArtifactLoader;
use App\Contexts\GameWorld\KingdomMaps\Services\KingdomMapSpatialArtifactValidator;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class KingdomMapSpatialArtifactsTest extends TestCase
{
    public function test_successor_materializes_exact_captured_source_and_preserves_historical_release(): void
    {
        $query = app(KingdomMapDatasetQuery::class);
        $dataset = $query->current();
        self::assertSame('kingshot-spatial-complete-v2-r1', $dataset->id);
        self::assertSame('kingshot-evidence-backed-2026-09-06-v2', $dataset->predecessorId);
        self::assertCount(6499, $dataset->data['resource_nodes']);
        self::assertCount(2449, $dataset->data['terrain_features']);
        self::assertSame(90461, array_sum(array_column($dataset->data['terrain_features'], 'cell_count')));
        self::assertSame('unqualified_resource_node', $dataset->data['resource_ownership_semantics']);
        foreach (['facilities' => 90, 'terrain' => 2449, 'resources' => 6499] as $key => $count) {
            $availability = $dataset->data['layer_availability'][$key];
            self::assertSame('materialized', $availability['state']);
            self::assertSame($count, $availability['available_count']);
            self::assertSame($count, $availability['expected_count']);
            self::assertSame($dataset->id, $availability['release_id']);
            self::assertSame($dataset->checksum, $availability['release_checksum']);
            self::assertSame($dataset->data['artifact_checksums'][$key], $availability['artifact_sha256']);
            self::assertSame('community_observed', $availability['confidence']);
        }
        $historical = $query->require('kingshot-evidence-backed-2026-09-06-v2');
        self::assertSame('39c679d5897bbb29939e00b7903a0e7f5dd44d1b95f10dabef29d5762b5405ec', $historical->checksum);
        self::assertSame([], $historical->data['terrain_features']);
        self::assertSame([], $historical->data['resource_nodes']);
        self::assertSame('unavailable', $historical->data['layer_availability']['terrain']['state']);
        self::assertSame(0, $historical->data['layer_availability']['resources']['available_count']);
        self::assertSame(6499, $historical->data['layer_availability']['resources']['expected_count']);
        self::assertNull($historical->data['layer_availability']['resources']['extent']);
        self::assertSame('source_corpus_not_materialized', $historical->data['layer_availability']['resources']['unavailable_reason']);
    }

    public function test_source_inconsistencies_are_recomputed_and_retained_without_erasing_observed_records(): void
    {
        $data = app(KingdomMapDatasetQuery::class)->current()->data;
        self::assertSame([
            ['code' => 'resource_terrain_overlap', 'resource_keys' => ['r_256_130'], 'terrain_keys' => ['lake_0057']],
            ['code' => 'resource_terrain_overlap', 'resource_keys' => ['r_768_1140'], 'terrain_keys' => ['mountain_1853']],
            ['code' => 'resource_footprint_overlap', 'resource_keys' => ['r_639_342', 'r_640_342'], 'terrain_keys' => []],
        ], $data['spatial_diagnostics']);
        $keys = array_column($data['resource_nodes'], 'key');
        foreach (['r_256_130', 'r_768_1140', 'r_639_342', 'r_640_342'] as $key) {
            self::assertContains($key, $keys);
        }
    }

    #[DataProvider('invalidTerrain')]
    public function test_invalid_terrain_contracts_are_rejected(Closure $mutate): void
    {
        $release = $this->release();
        $artifact = $this->artifact('terrain');
        $mutate($artifact);
        $this->expectException(RuntimeException::class);
        app(KingdomMapSpatialArtifactValidator::class)->terrain($artifact, $release['artifacts']['terrain'], $release);
    }

    /** @return array<string,array{Closure}> */
    public static function invalidTerrain(): array
    {
        return [
            'other game' => [static function (array &$a): void {
                $a['game'] = 'whiteout';
            }],
            'unsupported encoding' => [static function (array &$a): void {
                $a['encoding'] = 'legacy_radius';
            }],
            'bounding box fill' => [static function (array &$a): void {
                $a['features'][0]['spans'] = [[$a['features'][0]['bounds']['x'], $a['features'][0]['bounds']['y'], $a['features'][0]['bounds']['width']]];
            }],
            'fractional cell' => [static function (array &$a): void {
                $a['features'][0]['spans'][0][0] += 0.5;
            }],
            'zero width' => [static function (array &$a): void {
                $a['features'][0]['spans'][0][2] = 0;
            }],
            'false count' => [static function (array &$a): void {
                $a['features'][0]['cell_count']++;
            }],
            'false centroid' => [static function (array &$a): void {
                $a['features'][0]['centroid']['x'] += 10;
            }],
            'duplicate identity' => [static function (array &$a): void {
                $a['features'][1]['key'] = $a['features'][0]['key'];
            }],
            'unordered spans' => [static function (array &$a): void {
                $a['features'][0]['spans'] = array_reverse($a['features'][0]['spans']);
            }],
            'unknown geometry field' => [static function (array &$a): void {
                $a['features'][0]['size'] = 1;
            }],
            'invalid instant' => [static function (array &$a): void {
                $a['observed_at'] = '2026-09-32T02:58:26Z';
            }],
            'source hash changed' => [static function (array &$a): void {
                $a['source_sha256'] = str_repeat('0', 64);
            }],
            'confidence elevated' => [static function (array &$a): void {
                $a['confidence'] = 'official';
            }],
        ];
    }

    #[DataProvider('invalidResources')]
    public function test_invalid_resource_contracts_are_rejected(Closure $mutate): void
    {
        $release = $this->release();
        $artifact = $this->artifact('resources');
        $mutate($artifact);
        $this->expectException(RuntimeException::class);
        app(KingdomMapSpatialArtifactValidator::class)->resources($artifact, $release['artifacts']['resources'], $release);
    }

    /** @return array<string,array{Closure}> */
    public static function invalidResources(): array
    {
        return [
            'fractional position' => [static function (array &$a): void {
                $a['nodes'][0]['x'] += 0.5;
            }],
            'string position' => [static function (array &$a): void {
                $a['nodes'][0]['x'] = (string) $a['nodes'][0]['x'];
            }],
            'duplicate node' => [static function (array &$a): void {
                $a['nodes'][1] = $a['nodes'][0];
            }],
            'legacy scalar footprint' => [static function (array &$a): void {
                $a['nodes'][0]['size'] = 2;
            }],
            'wrong footprint' => [static function (array &$a): void {
                $a['nodes'][0]['footprint']['width'] = 3;
            }],
            'other game resource' => [static function (array &$a): void {
                $a['nodes'][0]['resource_type'] = 'coal';
            }],
            'wrong identity' => [static function (array &$a): void {
                $a['nodes'][0]['key'] = 'r_0_0';
            }],
            'source count mismatch' => [static function (array &$a): void {
                array_pop($a['nodes']);
            }],
            'unqualified production assertion' => [static function (array &$a): void {
                $a['ownership_semantics'] = 'official_alliance_mine';
            }],
        ];
    }

    public function test_materialization_claim_requires_the_immutable_artifact(): void
    {
        $release = $this->release();
        unset($release['artifacts']['terrain']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('materialized terrain layer has no artifact');
        app(KingdomMapArtifactLoader::class)->hydrate($release);
    }

    public function test_resource_and_terrain_checksum_pins_are_verified_before_hydration(): void
    {
        $release = $this->release();
        $release['artifacts']['terrain']['sha256'] = str_repeat('0', 64);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum does not match');
        app(KingdomMapArtifactLoader::class)->hydrate($release);
    }

    public function test_new_source_lineage_does_not_become_independent_or_official(): void
    {
        $release = $this->release();
        self::assertSame($release['sources']['ksmapper']['lineage'], $release['sources']['ksmapper_terrain_2026_09_13']['lineage']);
        $release['artifacts']['resources']['confidence'] = 'official';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds its provenance confidence ceiling');
        app(KingdomMapArtifactLoader::class)->hydrate($release);
    }

    public function test_artifact_path_cannot_escape_its_owned_directory(): void
    {
        $release = $this->release();
        $release['artifacts']['terrain']['path'] = 'resources/data/kingdom-maps/artifacts/../outside.json';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('artifact path is invalid');
        app(KingdomMapArtifactLoader::class)->hydrate($release);
    }

    /** @return array<string,mixed> */
    private function release(): array
    {
        return $this->readJson('resources/data/kingdom-maps/kingshot-spatial-complete-v2-r1.json');
    }

    /** @return array<string,mixed> */
    private function artifact(string $name): array
    {
        return $this->readJson('resources/data/kingdom-maps/artifacts/kingshot-'.$name.'-2026-09-13.json');
    }

    /** @return array<string,mixed> */
    private function readJson(string $path): array
    {
        $raw = file_get_contents(base_path($path));
        self::assertIsString($raw);
        $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
