<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Progression;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use Tests\v3\TestCase;

final class ProgressionDatasetV3Test extends TestCase
{
    public function test_latest_release_is_the_frozen_complete_source_corpus(): void
    {
        $query = app(ProgressionDatasetQuery::class);
        $first = $query->latest();
        $second = $query->latest();

        self::assertSame('kingshot-2026-08-23-v2', $first->id);
        self::assertSame(2, $first->schemaVersion);
        self::assertSame('2026.08.23.2', $first->datasetVersion);
        self::assertSame('2026-08-23', $first->observedAt);
        self::assertSame('candidate_complete_source_corpus', $first->release['review_status'] ?? null);
        self::assertSame(
            'docs/product/factual-governor-progression-source-inventory.md',
            $first->release['source_inventory_document'] ?? null,
        );
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first->checksum);
        self::assertSame($first->checksum, $second->checksum);
        self::assertCount(34, $first->heroes);
        self::assertSame('charles', $query->canonicalHeroId('Charles', $first));
        self::assertSame('wee-and-woo', $query->canonicalHeroId('Wee & Woo', $first));
        self::assertNull($query->canonicalHeroId('Imaginary Hero', $first));
        self::assertSame($first->checksum, $query->require($first->id, $first->checksum)->checksum);
    }

    public function test_all_current_heroes_have_structured_skill_ladders_and_gen_six_seven_have_source_provenance(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $byName = [];
        $skillCount = 0;

        foreach ($dataset->heroes as $hero) {
            self::assertIsArray($hero['skills'] ?? null, (string) ($hero['name'] ?? 'unknown'));
            self::assertNotEmpty($hero['skills'], (string) ($hero['name'] ?? 'unknown'));
            $skillCount += count($hero['skills']);
            $byName[(string) $hero['name']] = $hero;
        }

        self::assertSame(262, $skillCount);
        foreach (['Triton', 'Sophia', 'Yang', 'Charles', 'Ava', 'Wee & Woo'] as $heroName) {
            $hero = $byName[$heroName] ?? null;
            self::assertIsArray($hero, $heroName);
            self::assertSame('maintained_source_inspectable', $hero['skill_source_status'] ?? null, $heroName);
            self::assertCount(8, $hero['skills'] ?? [], $heroName);
            self::assertContains('kingshotdata', $hero['source_ids'] ?? [], $heroName);
            self::assertMatchesRegularExpression('#^https://kingshotdata\.com/heroes/#', (string) ($hero['skill_source_url'] ?? ''));
        }
    }

    public function test_academy_and_alliance_technology_coverage_is_complete_without_inventing_missing_rows(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $academy = $dataset->catalogue('academy_research');
        $alliance = $dataset->catalogue('alliance_tech_tables');

        self::assertIsArray($academy);
        self::assertSame(191, $academy['declared_technologies'] ?? null);
        self::assertSame(714, $academy['declared_levels'] ?? null);
        self::assertSame(714, $academy['visible_level_rows'] ?? null);
        self::assertSame(720, $academy['declared_max_level_sum'] ?? null);
        self::assertCount(191, $academy['technologies'] ?? []);
        self::assertSame(714, array_sum(array_map(
            static fn (array $technology): int => count($technology['levels'] ?? []),
            $academy['technologies'] ?? [],
        )));

        $fortifiedMail = null;
        foreach ($academy['technologies'] ?? [] as $technology) {
            if (($technology['name'] ?? null) === 'Fortified Mail VI') {
                $fortifiedMail = $technology;
                break;
            }
        }
        self::assertIsArray($fortifiedMail);
        self::assertSame(6, $fortifiedMail['max_level'] ?? null);
        self::assertSame('source_table_missing', $fortifiedMail['levels_status'] ?? null);
        self::assertSame([], $fortifiedMail['levels'] ?? null);

        self::assertCount(1, $dataset->sourceGaps());
        self::assertSame('academy-fortified-mail-vi-level-table', $dataset->sourceGaps()[0]['id'] ?? null);
        self::assertSame(6, $dataset->sourceGaps()[0]['missing_visible_level_rows'] ?? null);

        self::assertIsArray($alliance);
        self::assertSame(60, $alliance['declared_technologies'] ?? null);
        self::assertSame(279, $alliance['visible_level_rows'] ?? null);
        self::assertCount(60, $alliance['technologies'] ?? []);
        self::assertSame(279, array_sum(array_map(
            static fn (array $technology): int => count($technology['levels'] ?? []),
            $alliance['technologies'] ?? [],
        )));
        self::assertSame([
            ['name' => 'Growth', 'technologies' => 24, 'levels' => 108],
            ['name' => 'Territory', 'technologies' => 16, 'levels' => 80],
            ['name' => 'Battle', 'technologies' => 20, 'levels' => 91],
        ], $alliance['trees'] ?? null);
    }

    public function test_release_dispositions_report_semantic_game_coverage_instead_of_source_representation_counts(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $dispositions = [];
        foreach ($dataset->dispositions() as $row) {
            $dispositions[(string) $row['family']] = $row;
            self::assertNotSame('indexed_external_table', $row['status'] ?? null, (string) $row['family']);
        }

        self::assertSame(34, $dispositions['heroes']['canonical_entities'] ?? null);
        self::assertSame(34, $dispositions['hero_skills']['canonical_entities'] ?? null);
        self::assertSame(262, $dispositions['hero_skills']['facts_imported'] ?? null);
        self::assertSame(34, $dispositions['hero_star_shards']['canonical_entities'] ?? null);
        self::assertSame(1054, $dispositions['hero_star_shards']['facts_imported'] ?? null);
        self::assertSame(34, $dispositions['hero_exclusive_equipment']['canonical_entities'] ?? null);
        self::assertSame(22, $dispositions['hero_exclusive_equipment']['applicable_heroes'] ?? null);
        self::assertSame(220, $dispositions['hero_exclusive_equipment']['facts_imported'] ?? null);
        self::assertSame(12, $dispositions['buildings']['canonical_entities'] ?? null);
        self::assertSame(191, $dispositions['academy_research']['canonical_entities'] ?? null);
        self::assertSame(714, $dispositions['academy_research']['facts_imported'] ?? null);
        self::assertSame(60, $dispositions['alliance_tech']['canonical_entities'] ?? null);
        self::assertSame(279, $dispositions['alliance_tech']['facts_imported'] ?? null);
        self::assertSame(14, $dispositions['pets']['canonical_entities'] ?? null);
        self::assertSame(6, $dispositions['masters']['canonical_entities'] ?? null);
        self::assertSame(8, $dispositions['database_reference_tables']['canonical_entities'] ?? null);
        self::assertSame(33, $dispositions['progression_event_tables']['canonical_entities'] ?? null);
        self::assertSame('excluded_strategy_opinion', $dispositions['g2384_lineups']['status'] ?? null);
    }

    public function test_confirmed_source_sweeps_and_open_structured_feeds_are_present_in_release(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();

        foreach ([
            'buildings_tables' => 12,
            'pets_tables' => 14,
            'masters_tables' => 6,
            'heroes_tables' => 34,
            'database_tables' => 8,
            'events_tables' => 33,
        ] as $family => $expected) {
            $catalogue = $dataset->catalogue($family);
            self::assertIsArray($catalogue, $family);
            self::assertSame($expected, $catalogue['discovered_pages'] ?? null, $family);
            self::assertCount($expected, $catalogue['pages'] ?? [], $family);
        }

        self::assertSame(58, $dataset->catalogue('governor_gear')['source_meta']['count'] ?? null);
        self::assertSame(30, $dataset->catalogue('war_academy')['source_meta']['count'] ?? null);
        self::assertSame(80, $dataset->catalogue('hero_xp')['source_meta']['count'] ?? null);

        $sourceIds = array_column($dataset->sources(), 'id');
        self::assertContains('kingshotdata', $sourceIds);
        self::assertContains('kingshotpro-open-data', $sourceIds);
        self::assertContains('g2384-kingshot-data', $sourceIds);
    }

    public function test_every_formation_is_explicitly_a_community_convention_and_sums_to_one_hundred(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        self::assertNotEmpty($dataset->formations);

        foreach ($dataset->formations as $formation) {
            self::assertSame('community_convention', $formation['evidence_status']);
            self::assertSame(100, $formation['infantry'] + $formation['cavalry'] + $formation['archer']);
            self::assertArrayNotHasKey('recommended', $formation);
            self::assertArrayNotHasKey('score', $formation);
            self::assertArrayNotHasKey('best', $formation);
            self::assertNotEmpty($formation['source_ids'] ?? []);
        }
    }

    public function test_source_conflicts_remain_visible_even_when_a_reference_ladder_is_canonicalized(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $conflictIds = array_column($dataset->conflicts(), 'id');

        self::assertContains('governor-charm-max-level', $conflictIds);
        self::assertContains('training-building-unlock-copy', $conflictIds);
        self::assertStringContainsString(
            'separately gated',
            (string) collect($dataset->conflicts())->firstWhere('id', 'governor-charm-max-level')['resolution'],
        );
    }

    public function test_progression_systems_preserve_known_bounds_without_turning_reference_data_into_recommendations(): void
    {
        $systems = app(ProgressionDatasetQuery::class)->latest()->systems;

        self::assertSame(80, $systems['hero_progression']['max_level'] ?? null);
        self::assertSame(1065, $systems['hero_progression']['shards_to_max_star'] ?? null);
        self::assertSame(275, $systems['exclusive_equipment']['total_widgets_to_level_10'] ?? null);
        self::assertSame(20, $systems['hero_gear']['mastery_forging']['max_level'] ?? null);
        self::assertSame(191, $systems['research']['academy']['technologies'] ?? null);
        self::assertSame(714, $systems['research']['academy']['levels'] ?? null);
        self::assertCount(14, $systems['pets'] ?? []);
        self::assertCount(6, $systems['masters'] ?? []);
        self::assertSame('conflicting', $systems['governor_charms']['evidence_status'] ?? null);
    }
}
