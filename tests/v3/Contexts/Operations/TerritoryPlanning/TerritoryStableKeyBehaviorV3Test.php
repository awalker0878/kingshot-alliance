<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\TerritoryPlanning;

use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryPlanQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TerritoryStableKeyBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    private const DATASET_ID = 'kingshot-community-observed-2026-08-21-v1';

    public function test_plan_local_keys_and_selected_bear_trap_survive_save_reload_and_publish(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61901);
        $alliance = $scenario->alliance($actor);

        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Alliance,
            $actor->kingdomId,
            $alliance->allianceId,
            'Stable Hive',
            self::DATASET_ID,
        );

        $layers = [[
            'key' => 'alpha-layer',
            'alliance_id' => $alliance->allianceId,
            'external_name' => null,
            'external_tag' => null,
            'display_name' => $alliance->name,
            'presentation_color' => '#4da3ff',
            'sort_order' => 0,
            'visible' => true,
            'locked' => false,
        ]];
        $groups = [[
            'key' => 'primary-hive',
            'label' => 'Primary Hive',
        ]];
        $objects = $this->objects();
        $preferences = [
            'preferred_bear_radius_tiles' => 80,
            'march_seconds_per_tile' => 2,
            'selected_bear_trap_by_alliance' => [
                'alpha-layer' => 'trap-two',
            ],
        ];

        $saved = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $created->revision,
            $layers,
            $groups,
            $objects,
            $preferences,
        );

        $detail = app(TerritoryPlanQuery::class)->detail($actor->playerId, $created->planId);
        self::assertSame('alpha-layer', $detail['alliances'][0]['key'] ?? null);
        self::assertSame('primary-hive', $detail['groups'][0]['key'] ?? null);
        self::assertSame(
            ['hq', 'banner', 'city', 'trap-one', 'trap-two'],
            array_column($detail['objects'] ?? [], 'key'),
        );
        self::assertSame('primary-hive', $detail['objects'][2]['group_key'] ?? null);
        self::assertSame(
            ['alpha-layer' => 'trap-two'],
            $detail['plan']['planning_preferences']['selected_bear_trap_by_alliance'] ?? null,
        );
        self::assertSame(
            'trap-two',
            $detail['analysis']['alliances']['alpha-layer']['selected_bear_trap_key'] ?? null,
        );

        $objects[2]['x'] = 152;
        $resaved = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
            $layers,
            $groups,
            $objects,
            $preferences,
        );

        $reloaded = app(TerritoryPlanQuery::class)->detail($actor->playerId, $created->planId);
        self::assertSame(
            ['hq', 'banner', 'city', 'trap-one', 'trap-two'],
            array_column($reloaded['objects'] ?? [], 'key'),
        );
        self::assertSame('primary-hive', $reloaded['objects'][2]['group_key'] ?? null);
        self::assertSame('trap-two', $reloaded['analysis']['alliances']['alpha-layer']['selected_bear_trap_key'] ?? null);

        $published = app(PublishTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $resaved->revision,
        );
        $revision = TerritoryPlanRevision::query()->findOrFail($published->publishedRevisionId);
        $snapshot = $revision->snapshot;
        self::assertIsArray($snapshot);
        self::assertSame('alpha-layer', $snapshot['alliances'][0]['key'] ?? null);
        self::assertSame('primary-hive', $snapshot['groups'][0]['key'] ?? null);
        self::assertSame('city', $snapshot['objects'][2]['key'] ?? null);
        self::assertSame('alpha-layer', $snapshot['objects'][2]['alliance_key'] ?? null);
        self::assertSame('primary-hive', $snapshot['objects'][2]['group_key'] ?? null);
        self::assertSame(
            ['alpha-layer' => 'trap-two'],
            $snapshot['plan']['planning_preferences']['selected_bear_trap_by_alliance'] ?? null,
        );
    }

    /** @return list<array<string, mixed>> */
    private function objects(): array
    {
        return [
            [
                'key' => 'hq',
                'alliance_key' => 'alpha-layer',
                'group_key' => null,
                'type' => 'headquarters',
                'player_id' => null,
                'external_player_name' => null,
                'label' => 'HQ',
                'x' => 100,
                'y' => 100,
                'rotation' => 0,
                'sort_order' => 0,
                'metadata' => [],
            ],
            [
                'key' => 'banner',
                'alliance_key' => 'alpha-layer',
                'group_key' => null,
                'type' => 'banner',
                'player_id' => null,
                'external_player_name' => null,
                'label' => 'Banner',
                'x' => 112,
                'y' => 100,
                'rotation' => 0,
                'sort_order' => 1,
                'metadata' => [],
            ],
            [
                'key' => 'city',
                'alliance_key' => 'alpha-layer',
                'group_key' => 'primary-hive',
                'type' => 'governor_city',
                'player_id' => null,
                'external_player_name' => 'External Governor',
                'label' => 'Governor',
                'x' => 150,
                'y' => 150,
                'rotation' => 0,
                'sort_order' => 2,
                'metadata' => [],
            ],
            [
                'key' => 'trap-one',
                'alliance_key' => 'alpha-layer',
                'group_key' => 'primary-hive',
                'type' => 'bear_trap',
                'player_id' => null,
                'external_player_name' => null,
                'label' => 'Bear Trap 1',
                'x' => 130,
                'y' => 130,
                'rotation' => 0,
                'sort_order' => 3,
                'metadata' => [],
            ],
            [
                'key' => 'trap-two',
                'alliance_key' => 'alpha-layer',
                'group_key' => 'primary-hive',
                'type' => 'bear_trap',
                'player_id' => null,
                'external_player_name' => null,
                'label' => 'Bear Trap 2',
                'x' => 180,
                'y' => 180,
                'rotation' => 0,
                'sort_order' => 4,
                'metadata' => [],
            ],
        ];
    }
}
