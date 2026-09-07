<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;

final class TerritoryPlanningVisualFixture
{
    public const DATASET_ID = 'kingshot-evidence-backed-2026-09-06-v2';

    public static function seed(): void
    {
        $user = User::factory()->create([
            'name' => 'Territory Visual',
            'email' => 'territory-visual@example.test',
            'timezone' => 'UTC',
        ]);
        $kingdom = Kingdom::query()->create(['number' => 1223, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'GOV-TERRITORY-A',
            'current_name' => 'Map Warden',
        ]);
        $allianceId = app(CreateAlliance::class)->handle(
            (string) $player->id,
            'Dawn Guard',
            'dawn-guard',
            'en',
            'UTC',
        );
        $plan = app(CreateTerritoryPlan::class)->handle(
            (string) $player->id,
            TerritoryPlanScope::Alliance,
            (string) $kingdom->id,
            $allianceId,
            'Bear Hive Alpha',
            self::DATASET_ID,
        );

        app(SaveTerritoryPlan::class)->handle(
            (string) $player->id,
            $plan->planId,
            $plan->revision,
            [[
                'key' => 'owner',
                'alliance_id' => $allianceId,
                'external_name' => null,
                'external_tag' => null,
                'display_name' => 'Dawn Guard',
                'presentation_color' => '#4da3ff',
                'sort_order' => 0,
                'visible' => true,
                'locked' => false,
            ]],
            [['key' => 'hive', 'label' => 'Primary Hive']],
            [
                [
                    'key' => 'hq',
                    'alliance_key' => 'owner',
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
                    'alliance_key' => 'owner',
                    'group_key' => null,
                    'type' => 'banner',
                    'player_id' => null,
                    'external_player_name' => null,
                    'label' => 'Banner',
                    'x' => 110,
                    'y' => 100,
                    'rotation' => 0,
                    'sort_order' => 1,
                    'metadata' => [],
                ],
                [
                    'key' => 'city',
                    'alliance_key' => 'owner',
                    'group_key' => 'hive',
                    'type' => 'governor_city',
                    'player_id' => null,
                    'external_player_name' => 'North Star',
                    'label' => 'North Star',
                    'x' => 112,
                    'y' => 112,
                    'rotation' => 0,
                    'sort_order' => 2,
                    'metadata' => [],
                ],
                [
                    'key' => 'trap-one',
                    'alliance_key' => 'owner',
                    'group_key' => 'hive',
                    'type' => 'bear_trap',
                    'player_id' => null,
                    'external_player_name' => null,
                    'label' => 'Bear Trap 1',
                    'x' => 120,
                    'y' => 120,
                    'rotation' => 0,
                    'sort_order' => 3,
                    'metadata' => [],
                ],
            ],
            [
                'preferred_bear_radius_tiles' => 40,
                'march_seconds_per_tile' => 2,
                'selected_bear_trap_by_alliance' => ['owner' => 'trap-one'],
            ],
        );
    }
}
