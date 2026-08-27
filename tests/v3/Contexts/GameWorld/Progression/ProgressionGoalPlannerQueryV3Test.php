<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Progression;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionGoalPlannerQuery;
use Tests\v3\TestCase;

final class ProgressionGoalPlannerQueryV3Test extends TestCase
{
    public function test_hero_level_planning_returns_factual_distance_without_resource_or_time_totals(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $heroId = (string) $dataset->heroes[0]['id'];
        $current = ['heroes' => [$heroId => ['facts' => ['level' => $this->fact(73)], 'gear' => []]]];

        $plan = app(ProgressionGoalPlannerQuery::class)->plan($dataset, $current, 'hero_level', $heroId, 'level-80');

        self::assertSame('comparable', $plan['comparison']['status']);
        self::assertSame(7, $plan['comparison']['remainingTransitions']);
        self::assertCount(7, $plan['comparison']['path']);
        self::assertSame($dataset->id, $plan['dataset']['id']);
        self::assertSame($dataset->checksum, $plan['dataset']['checksum']);
        self::assertSame('observation-1', $plan['current']['provenance']['observationId']);

        $serialized = json_encode($plan, JSON_THROW_ON_ERROR);
        foreach (['materials', 'resourceTotal', 'timeTotal', 'timeSec', 'Research cost'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $serialized);
        }
    }

    public function test_governor_gear_uses_the_canonical_ladder_and_not_material_arithmetic(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $current = ['governorGear' => [
            'hood' => [
                'quality' => $this->fact('Green'),
                'star' => $this->fact(0),
            ],
        ]];

        $plan = app(ProgressionGoalPlannerQuery::class)->plan($dataset, $current, 'governor_gear', 'hood', 'step-3');

        self::assertSame('Green · ★0', $plan['current']['state']['label']);
        self::assertSame('Blue · ★0', $plan['target']['label']);
        self::assertSame(2, $plan['comparison']['remainingTransitions']);
        self::assertSame(['Green · ★1', 'Blue · ★0'], array_column($plan['comparison']['path'], 'label'));
    }

    public function test_charm_planner_compares_observed_levels_without_inventing_costs(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $current = ['charms' => ['hood-charm' => ['level' => $this->fact(10)]]];

        $plan = app(ProgressionGoalPlannerQuery::class)->plan(
            $dataset,
            $current,
            'governor_charms',
            'hood-charm',
            'level-12',
        );

        self::assertSame('comparable', $plan['comparison']['status']);
        self::assertSame(2, $plan['comparison']['remainingTransitions']);
        self::assertSame('Level 12', $plan['target']['label']);
    }

    public function test_missing_current_observation_stays_not_observed(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $heroId = (string) $dataset->heroes[0]['id'];

        $plan = app(ProgressionGoalPlannerQuery::class)->plan($dataset, [], 'hero_level', $heroId, 'level-80');

        self::assertSame('not_observed', $plan['current']['status']);
        self::assertSame('not_observed', $plan['comparison']['status']);
        self::assertNull($plan['comparison']['remainingTransitions']);
    }

    public function test_reverse_target_is_not_silently_treated_as_an_upgrade(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $heroId = (string) $dataset->heroes[0]['id'];
        $current = ['heroes' => [$heroId => ['facts' => ['level' => $this->fact(80)], 'gear' => []]]];

        $plan = app(ProgressionGoalPlannerQuery::class)->plan($dataset, $current, 'hero_level', $heroId, 'level-73');

        self::assertSame('unsupported_direction', $plan['comparison']['status']);
        self::assertNull($plan['comparison']['remainingTransitions']);
    }

    public function test_hero_gear_targets_stay_within_the_observed_quality_topology(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $heroId = (string) $dataset->heroes[0]['id'];
        $subjectId = $heroId.'|helmet';
        $current = ['heroes' => [$heroId => [
            'facts' => [],
            'gear' => ['helmet' => [
                'quality' => $this->fact('Mythic'),
                'level' => $this->fact(20),
            ]],
        ]]];

        $plan = app(ProgressionGoalPlannerQuery::class)->plan(
            $dataset,
            $current,
            'hero_gear',
            $subjectId,
            'quality-mythic-level-22',
        );

        self::assertSame('comparable', $plan['comparison']['status']);
        self::assertSame(2, $plan['comparison']['remainingTransitions']);
        self::assertCount(100, $plan['states']);
        self::assertSame([], array_values(array_filter(
            $plan['states'],
            static fn (array $state): bool => str_contains((string) $state['id'], 'red'),
        )));
    }

    public function test_research_source_gap_is_visible_and_not_synthesized(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();

        $plan = app(ProgressionGoalPlannerQuery::class)->plan(
            $dataset,
            [],
            'academy_research',
            'battle-fortified-mail-vi',
            null,
        );
        $subject = array_values(array_filter(
            $plan['subjects'],
            static fn (array $row): bool => $row['id'] === 'battle-fortified-mail-vi',
        ))[0] ?? null;

        self::assertIsArray($subject);
        self::assertSame('source_gap', $subject['status']);
        self::assertSame([], $plan['states']);
    }

    public function test_building_target_preserves_source_prerequisite_as_not_observed(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();

        $plan = app(ProgressionGoalPlannerQuery::class)->plan($dataset, [], 'buildings', 'academy', 'level-10');

        self::assertSame('not_observed', $plan['comparison']['status']);
        self::assertNotEmpty($plan['comparison']['prerequisites']);
        self::assertSame('not_observed', $plan['comparison']['prerequisites'][0]['status']);
    }

    /** @return array<string,mixed> */
    private function fact(mixed $value): array
    {
        return [
            'value' => $value,
            'capturedAt' => '2026-08-27T12:00:00Z',
            'observationId' => 'observation-1',
            'evidenceId' => 'evidence-1',
            'reviewId' => 'review-1',
            'datasetId' => 'kingshot-2026-08-23-v2',
            'datasetChecksum' => 'historical-observation-checksum',
        ];
    }
}
