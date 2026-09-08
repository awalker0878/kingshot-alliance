<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\Progression;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\ReadModels\Progression\Queries\ProgressionPrerequisiteEvaluator;
use Tests\v3\TestCase;

final class ProgressionPrerequisiteEvaluatorV3Test extends TestCase
{
    public function test_prerequisite_uses_the_level_facts_own_dataset_pin(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $currentPin = ['datasetId' => $dataset->id, 'datasetChecksum' => $dataset->checksum];
        $oldPin = ['datasetId' => 'older-dataset', 'datasetChecksum' => str_repeat('a', 64)];

        $result = $this->evaluate([
            'state_id' => ['value' => 'level:2', ...$currentPin],
            'level' => ['value' => 2, ...$oldPin],
        ]);
        self::assertSame('dataset_mismatch', $result['status']);
        self::assertNull($result['observedLevel']);

        $result = $this->evaluate([
            'state_id' => ['value' => 'level:2', ...$oldPin],
            'level' => ['value' => 2, ...$currentPin],
        ]);
        self::assertSame('satisfied', $result['status']);
        self::assertSame(2, $result['observedLevel']);
    }

    public function test_unlabelled_or_partial_pins_cannot_satisfy_a_prerequisite(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        foreach ([[], ['datasetId' => $dataset->id], ['datasetChecksum' => $dataset->checksum]] as $pin) {
            self::assertSame('dataset_mismatch', $this->evaluate(['level' => ['value' => 2, ...$pin]])['status']);
        }
    }

    public function test_only_observed_integer_levels_determine_satisfaction(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $pin = ['datasetId' => $dataset->id, 'datasetChecksum' => $dataset->checksum];
        self::assertSame('satisfied', $this->evaluate(['level' => ['value' => 3, ...$pin]])['status']);
        self::assertSame('not_satisfied', $this->evaluate(['level' => ['value' => 1, ...$pin]])['status']);
        self::assertSame('unknown_current_state', $this->evaluate([])['status']);
        foreach ([null, '', '2e3', 2.5, -1, true] as $value) {
            self::assertSame('unknown_current_state', $this->evaluate(['level' => ['value' => $value, ...$pin]])['status']);
        }
    }

    public function test_unresolved_requirement_text_is_preserved_without_guessing(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $results = app(ProgressionPrerequisiteEvaluator::class)->evaluate($dataset, [], ['Unknown Subject Lv.2', 'Reach the next stage']);
        self::assertSame(['unobservable', 'unsupported'], array_column($results, 'status'));
        self::assertSame(['Unknown Subject Lv.2', 'Reach the next stage'], array_column($results, 'label'));
    }

    private function evaluate(array $facts): array
    {
        return app(ProgressionPrerequisiteEvaluator::class)->evaluate(
            app(ProgressionDatasetQuery::class)->latest(),
            ['current' => ['buildings' => ['academy' => $facts]]],
            ['Academy Lv.2'],
        )[0];
    }
}
