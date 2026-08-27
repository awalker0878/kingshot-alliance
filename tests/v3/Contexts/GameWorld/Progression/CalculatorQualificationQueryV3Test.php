<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Progression;

use App\Contexts\GameWorld\Progression\Queries\CalculatorQualificationQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use Tests\v3\TestCase;

final class CalculatorQualificationQueryV3Test extends TestCase
{
    public function test_all_six_calculator_families_have_independent_reviewed_gate_results(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $reports = app(CalculatorQualificationQuery::class)->all($dataset);

        self::assertCount(6, $reports);
        self::assertSame([
            'governor_gear' => 'evidence_review',
            'governor_charms' => 'evidence_review',
            'hero_gear' => 'evidence_incomplete',
            'troop_training_promotion' => 'source_gap',
            'research' => 'source_gap',
            'buildings' => 'evidence_review',
        ], array_column($reports, 'status', 'family'));

        foreach ($reports as $report) {
            self::assertSame($dataset->id, $report['datasetId']);
            self::assertSame($dataset->datasetVersion, $report['datasetVersion']);
            self::assertSame($dataset->checksum, $report['datasetChecksum']);
            self::assertSame('2026-08-27', $report['reviewedAt']);
            self::assertCount(10, $report['gates']);
            self::assertArrayHasKey('source_coverage', $report['gates']);
            self::assertArrayHasKey('golden_fixtures', $report['gates']);
            self::assertArrayHasKey('independent_unlock', $report['gates']);
            self::assertNotSame('calculator_ready', $report['status']);
        }
    }

    public function test_evidence_review_does_not_unlock_a_calculator_without_all_required_gates(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $report = app(CalculatorQualificationQuery::class)->forFamily($dataset, 'governor_gear');

        self::assertSame('pass', $report['gates']['source_coverage']['status']);
        self::assertSame('pass', $report['gates']['conflict_closure']['status']);
        self::assertSame('fail', $report['gates']['immutable_release']['status']);
        self::assertSame('fail', $report['gates']['pure_typed_calculation']['status']);
        self::assertSame('fail', $report['gates']['golden_fixtures']['status']);
        self::assertSame('evidence_review', $report['status']);
    }

    public function test_source_gaps_remain_explicit_instead_of_becoming_zero_value_calculators(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $query = app(CalculatorQualificationQuery::class);

        self::assertSame('source_gap', $query->forFamily($dataset, 'research')['status']);
        self::assertSame('source_gap', $query->forFamily($dataset, 'troop_training_promotion')['status']);
    }
}
