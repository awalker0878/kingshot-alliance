<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Players;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class PlayerContextSelectionBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_single_owned_player_is_selected_automatically(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $player = $factory->player((int) $user->id, 19001);
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas($sessionKey, $player->playerId);
    }

    public function test_multiple_owned_players_require_an_explicit_selection(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $factory->player((int) $user->id, 19002);
        $factory->player((int) $user->id, 19002);
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing($sessionKey);
    }

    public function test_session_cannot_select_a_player_owned_by_another_user(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $other = $factory->authUser();
        $otherPlayer = $factory->player((int) $other->id, 19003);
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($user)
            ->withSession([$sessionKey => $otherPlayer->playerId])
            ->get(route('dashboard'))
            ->assertForbidden()
            ->assertSessionMissing($sessionKey);
    }
}
