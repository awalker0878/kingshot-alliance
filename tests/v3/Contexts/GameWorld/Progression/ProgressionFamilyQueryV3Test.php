<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Progression;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionFamilyQuery;
use Illuminate\Validation\ValidationException;
use Tests\v3\TestCase;

final class ProgressionFamilyQueryV3Test extends TestCase
{
    public function test_large_factual_families_are_bounded_to_forty_rows_per_page(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $result = app(ProgressionFamilyQuery::class)->page($dataset, 'academy_research');

        self::assertSame('academy_research', $result['family']);
        self::assertSame(40, $result['perPage']);
        self::assertCount(40, $result['rows']);
        self::assertSame(715, $result['total']);
        self::assertSame(18, $result['lastPage']);
        self::assertSame(1, $result['page']);
        self::assertContains('Technology', $result['columns']);
        self::assertContains('Tree', $result['columns']);
    }

    public function test_academy_source_gap_is_returned_as_unknown_instead_of_synthesized_levels(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $result = app(ProgressionFamilyQuery::class)->page(
            $dataset,
            'academy_research',
            'Fortified Mail VI',
        );

        self::assertSame(1, $result['total']);
        self::assertCount(1, $result['rows']);
        self::assertSame('Fortified Mail VI', $result['rows'][0]['values']['Technology'] ?? null);
        self::assertSame('6', $result['rows'][0]['values']['Max level'] ?? null);
        self::assertSame('source_table_missing', $result['rows'][0]['values']['Level data'] ?? null);
        self::assertSame('unknown_level_table', $result['rows'][0]['confidence']);
        self::assertSame(['kingshotdata'], $result['rows'][0]['sourceIds']);
        self::assertArrayNotHasKey('Level', $result['rows'][0]['values']);
    }

    public function test_hero_skill_browser_exposes_all_structured_skills_with_provenance(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $query = app(ProgressionFamilyQuery::class);

        $result = $query->page($dataset, 'hero_skills', 'Disguise Pigment');
        self::assertSame(1, $result['total']);
        self::assertSame('Ava', $result['rows'][0]['values']['Hero'] ?? null);
        self::assertSame('Disguise Pigment', $result['rows'][0]['values']['Skill'] ?? null);
        self::assertContains('kingshotdata', $result['rows'][0]['sourceIds']);
        self::assertSame('maintained_source_inspectable', $result['rows'][0]['confidence']);

        $firstPage = $query->page($dataset, 'hero_skills');
        self::assertSame(262, $firstPage['total']);
        self::assertCount(40, $firstPage['rows']);
        self::assertSame(7, $firstPage['lastPage']);
    }

    public function test_alliance_technology_detail_rows_are_searchable_and_source_scoped(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $result = app(ProgressionFamilyQuery::class)->page($dataset, 'alliance_tech_tables');

        self::assertSame(279, $result['total']);
        self::assertCount(40, $result['rows']);
        self::assertSame(['kingshotdata'], $result['rows'][0]['sourceIds']);
        self::assertContains('Entity', $result['columns']);
        self::assertContains('Table', $result['columns']);
    }

    public function test_open_structured_family_exposes_source_confidence_without_recommendation_fields(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $result = app(ProgressionFamilyQuery::class)->page($dataset, 'governor_gear');

        self::assertGreaterThan(0, $result['total']);
        self::assertSame('scored', $result['sourceMeta']['confidence'] ?? null);
        self::assertSame('kingshotpro-open-data', $result['rows'][0]['sourceIds'][0] ?? null);

        $serialized = json_encode($result, JSON_THROW_ON_ERROR);
        foreach (['"recommendation"', '"recommended"', '"tier_list"', '"optimizer"'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $serialized);
        }
    }

    public function test_unknown_family_is_rejected_instead_of_reading_arbitrary_release_files(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();

        $this->expectException(ValidationException::class);
        app(ProgressionFamilyQuery::class)->page($dataset, '../../release');
    }
}
