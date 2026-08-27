<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\Progression;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionTopologyQuery;
use App\ReadModels\Progression\Queries\ProgressionPlannerQuery;
use Tests\v3\TestCase;

final class ProgressionPlannerQueryV3Test extends TestCase
{
    public function test_governor_gear_plan_uses_observed_current_state_and_keeps_provenance_separate(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $planner = app(ProgressionPlannerQuery::class);
        $observation = $this->observationState([
            'governorGear' => [
                'hood' => [
                    'quality' => $this->fact('Green', $dataset->id, $dataset->checksum),
                    'star' => $this->fact(0, $dataset->id, $dataset->checksum),
                ],
            ],
        ]);

        $model = $planner->compose($dataset, $observation, 'governor_gear', 'hood', 'step:3', true);

        self::assertSame('observed', $model['current']['status']);
        self::assertSame('matched', $model['current']['datasetStatus']);
        self::assertSame('step:0', $model['current']['stateId']);
        self::assertSame($dataset->id, $model['current']['observationDatasetId']);
        self::assertSame($dataset->checksum, $model['current']['observationDatasetChecksum']);
        self::assertSame('comparable', $model['comparison']['status']);
        self::assertSame(3, $model['comparison']['remainingTransitions']);
        self::assertSame('calculator_ready', $model['calculator']['status']);
        self::assertSame('calculated', $model['calculation']['status']);
        self::assertSame(20500, $model['calculation']['resources']['satin']['quantity']);
        self::assertSame(205, $model['calculation']['resources']['gilded_threads']['quantity']);
    }

    public function test_dataset_mismatch_preserves_observed_state_but_blocks_resource_recalculation(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $planner = app(ProgressionPlannerQuery::class);
        $observation = $this->observationState([
            'governorGear' => [
                'hood' => [
                    'quality' => $this->fact('Green', 'historical-dataset', str_repeat('a', 64)),
                    'star' => $this->fact(0, 'historical-dataset', str_repeat('a', 64)),
                ],
            ],
        ]);

        $model = $planner->compose($dataset, $observation, 'governor_gear', 'hood', 'step:3', true);

        self::assertSame('observed', $model['current']['status']);
        self::assertSame('dataset_mismatch', $model['current']['datasetStatus']);
        self::assertSame('historical-dataset', $model['current']['observationDatasetId']);
        self::assertSame('step:0', $model['current']['stateId']);
        self::assertSame('comparable', $model['comparison']['status']);
        self::assertSame('calculator_ready', $model['calculator']['status']);
        self::assertNull($model['calculation']);
    }

    public function test_governor_charm_plan_never_treats_missing_level_as_level_zero(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $planner = app(ProgressionPlannerQuery::class);

        $unknown = $planner->compose(
            $dataset,
            $this->observationState(['charms' => ['left' => ['charm_id' => $this->fact('Charm', $dataset->id, $dataset->checksum)]]]),
            'governor_charms',
            'left',
            'level:3',
            true,
        );
        self::assertSame('observed_unresolved', $unknown['current']['status']);
        self::assertNull($unknown['current']['stateId']);
        self::assertSame('unknown_current', $unknown['comparison']['status']);
        self::assertNull($unknown['calculation']);

        $observed = $planner->compose(
            $dataset,
            $this->observationState(['charms' => ['left' => ['level' => $this->fact(1, $dataset->id, $dataset->checksum)]]]),
            'governor_charms',
            'left',
            'level:3',
            true,
        );
        self::assertSame('level:1', $observed['current']['stateId']);
        self::assertSame(100, $observed['calculation']['resources']['charm_guides']['quantity']);
        self::assertSame(55, $observed['calculation']['resources']['charm_designs']['quantity']);
    }

    public function test_research_goal_remains_useful_with_unknown_current_and_source_gated_calculator(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $topology = app(ProgressionTopologyQuery::class);
        $subject = null;
        $targetState = null;

        foreach ($topology->subjects($dataset, 'academy_research') as $candidate) {
            foreach ($topology->states($dataset, 'academy_research', $candidate['id']) as $state) {
                if ($state['prerequisites'] !== []) {
                    $subject = $candidate;
                    $targetState = $state;
                    break 2;
                }
            }
        }

        self::assertIsArray($subject);
        self::assertIsArray($targetState);

        $model = app(ProgressionPlannerQuery::class)->compose(
            $dataset,
            $this->observationState([]),
            'academy_research',
            $subject['id'],
            $targetState['id'],
            false,
        );

        self::assertSame('unknown', $model['current']['status']);
        self::assertSame('unknown_current', $model['comparison']['status']);
        self::assertSame('source_gap', $model['calculator']['status']);
        self::assertSame($targetState['label'], $model['target']['label']);
        self::assertSame(
            array_map(
                static fn (string $label): array => ['label' => $label, 'status' => 'unknown'],
                $targetState['prerequisites'],
            ),
            $model['prerequisites'],
        );
        self::assertNull($model['calculation']);
    }

    public function test_no_target_is_auto_selected_or_recommended(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $model = app(ProgressionPlannerQuery::class)->compose(
            $dataset,
            $this->observationState([]),
            'hero_level',
            (string) ($dataset->heroes[0]['id'] ?? ''),
            null,
            false,
        );

        self::assertNull($model['target']);
        self::assertNull($model['comparison']);
        self::assertNull($model['calculator']);
        self::assertNotEmpty($model['states']);
    }

    /** @param array<string,mixed> $current */
    private function observationState(array $current): array
    {
        return [
            'history' => [],
            'current' => [
                'profile' => $current['profile'] ?? [],
                'heroes' => $current['heroes'] ?? [],
                'governorGear' => $current['governorGear'] ?? [],
                'charms' => $current['charms'] ?? [],
                'completeRosterCapture' => null,
            ],
            'last_updated_at' => '2026-08-26T12:00:00+00:00',
        ];
    }

    /** @return array<string,mixed> */
    private function fact(mixed $value, string $datasetId, string $checksum): array
    {
        return [
            'value' => $value,
            'capturedAt' => '2026-08-26T12:00:00+00:00',
            'observationId' => 'observation-1',
            'evidenceId' => 'evidence-1',
            'reviewId' => 'review-1',
            'datasetId' => $datasetId,
            'datasetChecksum' => $checksum,
        ];
    }
}
