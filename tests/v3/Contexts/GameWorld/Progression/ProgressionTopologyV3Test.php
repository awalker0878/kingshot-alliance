<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Progression;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionTopologyQuery;
use Tests\v3\TestCase;

final class ProgressionTopologyV3Test extends TestCase
{
    public function test_planner_families_and_factual_state_ladders_are_dataset_backed(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $query = app(ProgressionTopologyQuery::class);

        self::assertSame([
            'governor_gear',
            'governor_charms',
            'hero_level',
            'hero_gear_level',
            'hero_mastery',
            'academy_research',
            'war_academy_research',
            'buildings',
        ], array_column($query->families(), 'id'));

        $gear = $query->states($dataset, 'governor_gear', 'hood');
        self::assertCount(58, $gear);
        self::assertSame('step:0', $gear[0]['id']);
        self::assertSame('Green', $gear[0]['label']);
        self::assertSame('kingshot-official-wiki', $gear[0]['sourceIds'][0] ?? null);

        $charms = $query->states($dataset, 'governor_charms', 'charm');
        self::assertCount(23, $charms);
        self::assertSame('level:0', $charms[0]['id']);
        self::assertSame('explicit_unupgraded_boundary', $charms[0]['evidenceStatus']);
        self::assertSame('level:22', $charms[22]['id']);

        $heroLevels = $query->states($dataset, 'hero_level', 'amadeus');
        self::assertCount(80, $heroLevels);
        self::assertSame('level:80', $heroLevels[79]['id']);
    }

    public function test_comparison_never_converts_progression_distance_into_resource_totals(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $query = app(ProgressionTopologyQuery::class);
        $states = $query->states($dataset, 'governor_gear', 'hood');

        $unknown = $query->compare($states, null, 'step:3');
        self::assertSame('unknown_current', $unknown['status']);
        self::assertNull($unknown['remainingTransitions']);
        self::assertSame([], $unknown['path']);

        $forward = $query->compare($states, 'step:0', 'step:3');
        self::assertSame('comparable', $forward['status']);
        self::assertSame(3, $forward['remainingTransitions']);
        self::assertSame(['step:0', 'step:1', 'step:2', 'step:3'], array_column($forward['path'], 'id'));
        self::assertArrayNotHasKey('resources', $forward);
        self::assertArrayNotHasKey('time', $forward);

        $reverse = $query->compare($states, 'step:3', 'step:1');
        self::assertSame('invalid_reverse', $reverse['status']);
        self::assertNull($reverse['remainingTransitions']);
    }

    public function test_research_source_gap_is_not_synthesized_into_target_states(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $query = app(ProgressionTopologyQuery::class);

        $subjects = $query->subjects($dataset, 'academy_research');
        $fortifiedMail = null;
        $bandaging = null;
        foreach ($subjects as $subject) {
            if ($subject['id'] === 'battle-fortified-mail-vi') {
                $fortifiedMail = $subject;
            }
            if ($subject['id'] === 'development-bandaging-i') {
                $bandaging = $subject;
            }
        }

        self::assertIsArray($fortifiedMail);
        self::assertSame([], $query->states($dataset, 'academy_research', 'battle-fortified-mail-vi'));

        self::assertIsArray($bandaging);
        $states = $query->states($dataset, 'academy_research', 'development-bandaging-i');
        self::assertCount(3, $states);
        self::assertContains('Academy Lv.2', $states[0]['prerequisites']);
        self::assertContains('Tool Enhancement I Lv.1', $states[0]['prerequisites']);
    }

    public function test_building_targets_are_available_for_factual_planning_while_calculation_remains_separate(): void
    {
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $query = app(ProgressionTopologyQuery::class);

        $subjects = $query->subjects($dataset, 'buildings');
        self::assertNotEmpty($subjects);
        $townCenter = null;
        foreach ($subjects as $subject) {
            if ($subject['id'] === 'castle') {
                $townCenter = $subject;
                break;
            }
        }
        self::assertIsArray($townCenter);

        $states = $query->states($dataset, 'buildings', 'castle');
        self::assertCount(30, $states);
        self::assertSame('level:30', $states[29]['id']);
    }
}
