<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlayerContextSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_user_id_can_authoritatively_own_multiple_players(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 7700, 'status' => 'active']);

        foreach (['Alpha', 'Bravo', 'Charlie'] as $index => $name) {
            Player::query()->create([
                'user_id' => $user->id,
                'current_kingdom_id' => $kingdom->id,
                'game_player_id' => 'p-7700-'.($index + 1),
                'current_name' => $name,
            ]);
        }

        self::assertSame(3, Player::query()->where('user_id', $user->id)->count());
        self::assertSame(3, $user->players()->count());
    }

    public function test_user_can_switch_between_only_players_owned_by_their_user_id(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 7701, 'status' => 'active']);
        $first = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'p-7701-a',
            'current_name' => 'Alpha',
        ]);
        $second = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'p-7701-b',
            'current_name' => 'Bravo',
        ]);

        $sessionKey = (string) config('identity.active_player_session_key');

        $this->actingAs($user)->post(route('players.activate', ['player' => $first->id]))
            ->assertRedirect()
            ->assertSessionHas($sessionKey, $first->id);

        $this->actingAs($user)->withSession([$sessionKey => $first->id])
            ->post(route('players.activate', ['player' => $second->id]))
            ->assertRedirect()
            ->assertSessionHas($sessionKey, $second->id);
    }

    public function test_user_cannot_activate_a_player_owned_by_another_user(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 7702, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'p-7702-owner',
            'current_name' => 'Owned Player',
        ]);

        $this->actingAs($attacker)
            ->post(route('players.activate', ['player' => $player->id]))
            ->assertNotFound();
    }


    public function test_single_owned_player_is_activated_automatically(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 7704, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'p-7704-only',
            'current_name' => 'Only Player',
        ]);
        $sessionKey = (string) config('identity.active_player_session_key');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas($sessionKey, $player->id);
    }

    public function test_multiple_owned_players_require_explicit_selection(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 7705, 'status' => 'active']);
        foreach ([['p-7705-a', 'Alpha'], ['p-7705-b', 'Bravo']] as [$gameId, $name]) {
            Player::query()->create([
                'user_id' => $user->id,
                'current_kingdom_id' => $kingdom->id,
                'game_player_id' => $gameId,
                'current_name' => $name,
            ]);
        }
        $sessionKey = (string) config('identity.active_player_session_key');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing($sessionKey);
    }

    public function test_forged_player_session_is_cleared_at_request_boundary(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 7703, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'p-7703-owner',
            'current_name' => 'Foreign Player',
        ]);

        $sessionKey = (string) config('identity.active_player_session_key');

        $this->actingAs($attacker)
            ->withSession([$sessionKey => $player->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing($sessionKey);
    }
}
