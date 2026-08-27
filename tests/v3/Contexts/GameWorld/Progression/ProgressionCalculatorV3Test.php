<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Progression;

use App\Contexts\GameWorld\Progression\Enums\CalculatorEligibilityStatus;
use App\Contexts\GameWorld\Progression\Enums\ProgressionCalculationStatus;
use App\Contexts\GameWorld\Progression\Queries\CalculatorEligibilityQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\GameWorld\Progression\Services\ProgressionCalculator;
use Tests\v3\TestCase;

final class ProgressionCalculatorV3Test extends TestCase
{
    public function test_calculator_qualification_is_independent_per_family(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $eligibility = app(CalculatorEligibilityQuery::class);

        self::assertSame(CalculatorEligibilityStatus::CalculatorReady, $eligibility->forFamily($dataset, 'governor_gear')->status);
        self::assertSame(CalculatorEligibilityStatus::CalculatorReady, $eligibility->forFamily($dataset, 'governor_charms')->status);
        self::assertSame(CalculatorEligibilityStatus::EvidenceIncomplete, $eligibility->forFamily($dataset, 'hero_gear_mastery')->status);
        self::assertSame(CalculatorEligibilityStatus::EvidenceIncomplete, $eligibility->forFamily($dataset, 'troop_training_promotion')->status);
        self::assertSame(CalculatorEligibilityStatus::SourceGap, $eligibility->forFamily($dataset, 'research')->status);
        self::assertSame(CalculatorEligibilityStatus::EvidenceConflict, $eligibility->forFamily($dataset, 'buildings_truegold')->status);
        self::assertSame(CalculatorEligibilityStatus::Unsupported, $eligibility->forFamily($dataset, 'hero_level')->status);

        $gear = $eligibility->forFamily($dataset, 'governor_gear');
        self::assertSame('qualified', $gear->qualificationStatus);
        self::assertSame('governor-gear-v1', $gear->calculationVersion);
        self::assertContains('kingshot-official-wiki', $gear->sourceIds);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $gear->qualificationReportChecksum);
        self::assertSame($dataset->checksum, $gear->datasetChecksum);

        $research = $eligibility->forFamily($dataset, 'research');
        self::assertContains('academy-fortified-mail-vi-level-table', $research->blockers);
        self::assertFalse($research->gates['source_version_unit_complete']);
    }

    public function test_governor_gear_golden_calculation_fixtures_are_exact(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $calculator = app(ProgressionCalculator::class);

        $single = $calculator->calculate($dataset, 'governor_gear', 'step:0', 'step:1');
        self::assertSame(ProgressionCalculationStatus::Calculated, $single->status);
        self::assertSame(['step:1'], $single->transitionIds);
        self::assertSame(3800, $single->resources['satin']['quantity']);
        self::assertSame(40, $single->resources['gilded_threads']['quantity']);
        self::assertSame(0, $single->resources['artisans_vision']['quantity']);

        $multi = $calculator->calculate($dataset, 'governor_gear', 'step:0', 'step:3');
        self::assertSame(ProgressionCalculationStatus::Calculated, $multi->status);
        self::assertSame(['step:1', 'step:2', 'step:3'], $multi->transitionIds);
        self::assertSame(20500, $multi->resources['satin']['quantity']);
        self::assertSame(205, $multi->resources['gilded_threads']['quantity']);
        self::assertSame(0, $multi->resources['artisans_vision']['quantity']);
        self::assertSame('governor-gear-v1', $multi->calculationVersion);
        self::assertSame($dataset->checksum, $multi->datasetChecksum);

        $same = $calculator->calculate($dataset, 'governor_gear', 'step:3', 'step:3');
        self::assertSame(ProgressionCalculationStatus::Calculated, $same->status);
        self::assertSame([], $same->transitionIds);
        self::assertSame(0, $same->resources['satin']['quantity']);
        self::assertSame(0, $same->resources['gilded_threads']['quantity']);
        self::assertSame(0, $same->resources['artisans_vision']['quantity']);

        $reverse = $calculator->calculate($dataset, 'governor_gear', 'step:3', 'step:1');
        self::assertSame(ProgressionCalculationStatus::Invalid, $reverse->status);
        self::assertSame([], $reverse->resources);
    }

    public function test_governor_charm_golden_calculation_fixtures_are_exact(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $calculator = app(ProgressionCalculator::class);

        $single = $calculator->calculate($dataset, 'governor_charms', 'level:1', 'level:2');
        self::assertSame(ProgressionCalculationStatus::Calculated, $single->status);
        self::assertSame(40, $single->resources['charm_guides']['quantity']);
        self::assertSame(15, $single->resources['charm_designs']['quantity']);

        $multi = $calculator->calculate($dataset, 'governor_charms', 'level:1', 'level:3');
        self::assertSame(['level:2', 'level:3'], $multi->transitionIds);
        self::assertSame(100, $multi->resources['charm_guides']['quantity']);
        self::assertSame(55, $multi->resources['charm_designs']['quantity']);

        $fromZero = $calculator->calculate($dataset, 'governor_charms', 'level:0', 'level:3');
        self::assertSame(105, $fromZero->resources['charm_guides']['quantity']);
        self::assertSame(60, $fromZero->resources['charm_designs']['quantity']);
        self::assertSame('governor-charms-v1', $fromZero->calculationVersion);

        $reverse = $calculator->calculate($dataset, 'governor_charms', 'level:3', 'level:1');
        self::assertSame(ProgressionCalculationStatus::Invalid, $reverse->status);
    }

    public function test_unqualified_family_returns_unavailable_instead_of_a_partial_or_guessed_total(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $result = app(ProgressionCalculator::class)->calculate($dataset, 'research', 'level:1', 'level:2');

        self::assertSame(ProgressionCalculationStatus::Unavailable, $result->status);
        self::assertSame([], $result->resources);
        self::assertSame([], $result->transitionIds);
        self::assertStringContainsString('Fortified Mail VI', $result->reason ?? '');
    }
}
