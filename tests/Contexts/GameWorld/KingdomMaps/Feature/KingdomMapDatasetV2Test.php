<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomMaps\Feature;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\Services\KingdomMapArtifactLoader;
use App\Contexts\GameWorld\KingdomMaps\Services\KingdomMapFactProvenanceValidator;
use App\Contexts\GameWorld\KingdomMaps\Services\KingdomMapSchemaV2Validator;
use App\Contexts\GameWorld\KingdomMaps\Services\TerritoryCoverageGeometry;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\Rectangle;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Tests\TestCase;

final class KingdomMapDatasetV2Test extends TestCase
{
    public function test_production_release_loads_with_researched_sources_facilities_and_spatial_corpus(): void
    {
        $dataset = app(KingdomMapDatasetQuery::class)->current();

        self::assertSame('kingshot-evidence-backed-2026-09-06-v2', $dataset->id);
        self::assertSame(2, $dataset->schemaVersion);
        self::assertSame('released', $dataset->releaseStatus);
        self::assertSame('community_observed', $dataset->confidence->value);
        self::assertSame('user_authorized_reuse_2026-09-06', $dataset->sources()['ksmapper']['rights_basis'] ?? null);
        self::assertSame('community_observed', $dataset->sources()['ksmapper']['confidence_ceiling'] ?? null);

        self::assertSame(['fortress' => 4, 'sanctuary' => 12, 'outpost' => 74], $dataset->data['facility_counts'] ?? null);
        self::assertCount(90, $dataset->data['facilities'] ?? []);
        self::assertSame(6499, $dataset->resourceLayers()['resource_nodes']['count'] ?? null);
        self::assertSame(501, $dataset->resourceLayers()['terrain']['lakes'] ?? null);
        self::assertSame(1948, $dataset->resourceLayers()['terrain']['mountains'] ?? null);

        self::assertSame(285, $dataset->objectDefinition('banner')['max_per_alliance'] ?? null);
        self::assertSame('official', $dataset->objectDefinition('banner')['facts']['max_per_alliance']['confidence'] ?? null);
        self::assertSame(2, $dataset->objectDefinition('headquarters')['max_per_alliance'] ?? null);
        self::assertSame(
            ['badland_headquarters', 'plains_headquarters'],
            array_column($dataset->objectDefinition('headquarters')['variants'] ?? [], 'key'),
        );

        $rules = collect($dataset->data['placement_rules'] ?? [])->keyBy('key');
        self::assertSame(0.75, $rules->get('alliance_resource_territory_ratio')['parameters']['minimum_covered_ratio'] ?? null);
        self::assertSame('official', $rules->get('banner_hq_connectivity')['confidence'] ?? null);
        self::assertSame('official', $rules->get('headquarters_legal_zones')['confidence'] ?? null);
    }

    public function test_release_checksum_pin_is_enforced(): void
    {
        $query = app(KingdomMapDatasetQuery::class);
        $dataset = $query->current();

        self::assertSame($dataset->id, $query->require($dataset->id, $dataset->checksum)->id);

        $this->expectException(ValidationException::class);
        $query->require($dataset->id, str_repeat('0', 64));
    }

    /** @throws JsonException */
    public function test_schema_v1_is_rejected_without_a_compatibility_path(): void
    {
        $data = $this->releaseData();
        $data['schema_version'] = 1;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('schema_version must be exactly 2');
        app(KingdomMapSchemaV2Validator::class)->validate($data);
    }

    /** @throws JsonException */
    public function test_dangling_property_fact_provenance_is_rejected(): void
    {
        $data = $this->releaseData();
        $data['object_types']['banner']['facts']['max_per_alliance']['provenance'] = ['missing_source'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('references an unknown source');
        app(KingdomMapFactProvenanceValidator::class)->validate($data);
    }

    /** @throws JsonException */
    public function test_facility_artifact_checksum_is_enforced(): void
    {
        $data = $this->releaseData();
        $data['artifacts']['facilities']['sha256'] = str_repeat('0', 64);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum does not match');
        app(KingdomMapArtifactLoader::class)->hydrate($data);
    }

    public function test_official_alliance_resource_rule_uses_area_ratio_at_exactly_seventy_five_percent(): void
    {
        $dataset = app(KingdomMapDatasetQuery::class)->current();
        $geometry = app(TerritoryCoverageGeometry::class);
        $resource = new Rectangle(10, 10, 4, 1);
        $territory = [new Rectangle(10, 10, 3, 1)];

        self::assertSame(0.75, $geometry->officialAllianceResourceMinimumRatio($dataset));
        self::assertSame(0.75, $geometry->coveredRatio($resource, $territory));
        self::assertTrue($geometry->meetsRatio($resource, $territory, 0.75));
        self::assertFalse($geometry->meetsRatio($resource, [new Rectangle(10, 10, 2, 1)], 0.75));
    }

    /** @return array<string,mixed> @throws JsonException */
    private function releaseData(): array
    {
        $contents = file_get_contents(base_path('resources/data/kingdom-maps/kingshot-evidence-backed-2026-09-06-v2.json'));
        if (! is_string($contents)) {
            throw new RuntimeException('Unable to read production KingdomMaps V2 release.');
        }
        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('Production KingdomMaps V2 release must decode to an object.');
        }

        return $data;
    }
}
