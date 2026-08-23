<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Progression;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use Tests\v3\TestCase;

final class ProgressionDatasetV3Test extends TestCase
{
    public function test_latest_release_is_versioned_checksums_and_contains_complete_discovered_hero_identity_roster(): void
    {
        $query = app(ProgressionDatasetQuery::class);
        $first = $query->latest();
        $second = $query->latest();

        self::assertSame('kingshot-2026-08-23-v1', $first->id);
        self::assertSame('2026.08.23.1', $first->datasetVersion);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first->checksum);
        self::assertSame($first->checksum, $second->checksum);
        self::assertCount(34, $first->heroes);
        self::assertSame('charles', $query->canonicalHeroId('Charles', $first));
        self::assertSame('wee-and-woo', $query->canonicalHeroId('Wee & Woo', $first));
        self::assertNull($query->canonicalHeroId('Imaginary Hero', $first));
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
        }
    }

    public function test_source_conflicts_and_family_dispositions_are_visible_in_release(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $conflictIds = array_column($dataset->conflicts(), 'id');
        self::assertContains('governor-charm-max-level', $conflictIds);

        $dispositions = collect($dataset->dispositions())->keyBy('family');
        self::assertSame('canonicalized', $dispositions->get('heroes')['status'] ?? null);
        self::assertSame(34, $dispositions->get('heroes')['canonical_entities'] ?? null);
        self::assertSame('indexed_external_table', $dispositions->get('academy_research')['status'] ?? null);
        self::assertSame('excluded_strategy_opinion', $dispositions->get('g2384_lineups')['status'] ?? null);
    }

    public function test_progression_systems_preserve_current_known_bounds_without_unlocking_calculators(): void
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
