<?php

declare(strict_types=1);

namespace Tests\v2\Workflows\PlayerContext;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v2\Support\ScenarioFactory;
use Tests\v2\TestCase;

final class PlayerContextSelectionBehaviorV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_single_owned_player_is_selected_automatically(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->user();
        $player = $factory->player($user, 19001);
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas($sessionKey, (string) $player->id);
    }

    public function test_multiple_owned_players_require_an_explicit_selection(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->user();
        $factory->player($user, 19002);
        $factory->player($user, 19002);
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing($sessionKey);
    }

    public function test_session_cannot_select_a_player_owned_by_another_user(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->user();
        $other = $factory->user();
        $otherPlayer = $factory->player($other, 19003);
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($user)
            ->withSession([$sessionKey => (string) $otherPlayer->id])
            ->get(route('dashboard'))
            ->assertForbidden()
            ->assertSessionMissing($sessionKey);
    }
}
