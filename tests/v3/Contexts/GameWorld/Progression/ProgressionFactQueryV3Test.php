<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Progression;

use App\Contexts\GameWorld\Progression\Enums\ProgressionFactKind;
use App\Contexts\GameWorld\Progression\Enums\ProgressionFactResolution;
use App\Contexts\GameWorld\Progression\Queries\ProgressionFactQuery;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionFactRequest;
use Tests\v3\TestCase;

final class ProgressionFactQueryV3Test extends TestCase
{
    public function test_hero_generation_is_returned_with_release_and_source_provenance(): void
    {
        $result = app(ProgressionFactQuery::class)->answer(
            new ProgressionFactRequest(ProgressionFactKind::HeroGeneration, 'Amadeus'),
        );

        self::assertSame(ProgressionFactResolution::Known, $result->resolution);
        self::assertSame('heroes', $result->family);
        self::assertSame('Amadeus', $result->title);
        self::assertSame('kingshot-2026-08-23-v2', $result->datasetId);
        self::assertSame('2026.08.23.2', $result->datasetVersion);
        self::assertNotSame('', $result->checksum);
        self::assertNotSame([], $result->sourceIds);
        self::assertArrayHasKey('value', $result->values);
    }

    public function test_widget_max_level_is_a_closed_supported_system_fact(): void
    {
        $result = app(ProgressionFactQuery::class)->answer(
            new ProgressionFactRequest(ProgressionFactKind::SystemMaxLevel, 'Widget'),
        );

        self::assertSame(ProgressionFactResolution::Known, $result->resolution);
        self::assertSame('10', $result->values['maxLevel'] ?? null);
        self::assertSame('max_levels', $result->family);
    }

    public function test_unknown_system_is_unknown_instead_of_becoming_arbitrary_release_lookup(): void
    {
        $result = app(ProgressionFactQuery::class)->answer(
            new ProgressionFactRequest(ProgressionFactKind::SystemMaxLevel, '../../release'),
        );

        self::assertSame(ProgressionFactResolution::Unknown, $result->resolution);
        self::assertSame([], $result->values);
        self::assertSame('max_levels', $result->family);
    }

    public function test_academy_source_gap_remains_unknown_and_is_not_inferred(): void
    {
        $result = app(ProgressionFactQuery::class)->answer(
            new ProgressionFactRequest(ProgressionFactKind::AcademyResearchLevel, 'Fortified Mail VI', 1),
        );

        self::assertSame(ProgressionFactResolution::Unknown, $result->resolution);
        self::assertSame('source_table_missing', $result->evidenceStatus);
        self::assertSame([], $result->values);
        self::assertContains('kingshotdata', $result->sourceIds);
    }

    public function test_explicit_troop_tier_returns_source_status_without_recommendation_logic(): void
    {
        $result = app(ProgressionFactQuery::class)->answer(
            new ProgressionFactRequest(ProgressionFactKind::TroopTierStats, 'Infantry', 3),
        );

        self::assertSame(ProgressionFactResolution::Known, $result->resolution);
        self::assertSame('T3 Infantry', $result->title);
        self::assertSame('EST', $result->values['status'] ?? null);
        self::assertSame('scored', $result->confidence);
        self::assertSame('EST', $result->evidenceStatus);
    }
}
