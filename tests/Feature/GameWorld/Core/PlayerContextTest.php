<?php

declare(strict_types=1);

namespace Tests\Feature\GameWorld\Core;

use App\Contexts\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class PlayerContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_account_can_own_multiple_players_and_explicitly_activate_each_one(): void
    {
        $user = User::factory()->create();
        $factory = new ScenarioFactory();
        $first = $factory->playerFor($user, 4210, 'Alpha', 'game-4210-a')['player'];
        $second = $factory->playerFor($user, 4210, 'Bravo', 'game-4210-b')['player'];
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($user)
            ->post(route('players.activate', ['player' => $first->id]))
            ->assertRedirect()
            ->assertSessionHas($sessionKey, $first->id);

        $this->actingAs($user)
            ->withSession([$sessionKey => $first->id])
            ->post(route('players.activate', ['player' => $second->id]))
            ->assertRedirect()
            ->assertSessionHas($sessionKey, $second->id);
    }

    public function test_account_cannot_activate_or_forge_another_accounts_player_context(): void
    {
        $owner = (new ScenarioFactory())->claimedPlayer(4211, 'Owner Player', 'game-4211-owner');
        $attacker = User::factory()->create();
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($attacker)
            ->post(route('players.activate', ['player' => $owner['player']->id]))
            ->assertNotFound();

        $this->actingAs($attacker)
            ->withSession([$sessionKey => $owner['player']->id])
            ->get(route('dashboard'))
            ->assertForbidden()
            ->assertSessionMissing($sessionKey);
    }

    public function test_single_owned_player_is_selected_automatically_but_multiple_players_require_choice(): void
    {
        $single = (new ScenarioFactory())->claimedPlayer(4212, 'Only Player', 'game-4212-only');
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($single['user'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas($sessionKey, $single['player']->id);

        $multiUser = User::factory()->create();
        $factory = new ScenarioFactory();
        $factory->playerFor($multiUser, 4213, 'One', 'game-4213-one');
        $factory->playerFor($multiUser, 4213, 'Two', 'game-4213-two');

        $this->actingAs($multiUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing($sessionKey);
    }

    public function test_malformed_active_player_session_is_cleared_at_the_request_boundary(): void
    {
        $user = User::factory()->create();
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($user)
            ->withSession([$sessionKey => ['forged']])
            ->get(route('dashboard'))
            ->assertForbidden()
            ->assertSessionMissing($sessionKey);
    }
}
