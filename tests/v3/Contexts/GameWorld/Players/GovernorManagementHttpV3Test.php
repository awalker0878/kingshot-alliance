<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Players;

use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GovernorManagementHttpV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_zero_governor_account_can_open_workspace_and_register_first_governor(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)
            ->get(route('governors.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounts/Governor/Governors')
                ->where('activePlayerId', null)
                ->has('governors', 0));

        $this->actingAs($user)
            ->post(route('governors.store'), [
                'name' => 'My Governor',
                'kingdom_number' => 19401,
                'game_player_id' => 'self-service-19401',
            ])
            ->assertRedirect(route('governors.index'));

        $player = Player::query()->where('user_id', $user->id)->firstOrFail();
        self::assertSame('My Governor', (string) $player->current_name);
        self::assertSame('self-service-19401', (string) $player->game_player_id);
        self::assertNull($player->canonical_player_id);
        $this->assertSame((string) $player->id, session((string) config('game_world.active_player_session_key')));
    }

    public function test_self_service_registration_never_claims_a_preexisting_unowned_stable_identity(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $kingdom = $factory->kingdom(19402);
        $existing = app(PersistPlayerIdentity::class)->handle($kingdom->kingdomId, 'Observed Elsewhere', 'existing-19402');

        $this->actingAs($user)
            ->from(route('governors.index'))
            ->post(route('governors.store'), [
                'name' => 'Claim Attempt',
                'kingdom_number' => 19402,
                'game_player_id' => 'existing-19402',
            ])
            ->assertRedirect(route('governors.index'))
            ->assertSessionHasErrors('game_player_id');

        $player = Player::query()->findOrFail($existing->playerId);
        self::assertNull($player->user_id);
        self::assertSame('Observed Elsewhere', (string) $player->current_name);
    }

    public function test_account_cannot_update_another_accounts_governor(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $other = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $otherPlayer = $factory->player((int) $other->id, 19403);

        $this->actingAs($user)
            ->patch(route('governors.update', ['player' => $otherPlayer->playerId]), [
                'name' => 'Hijacked',
                'game_player_id' => $otherPlayer->gamePlayerId,
            ])
            ->assertNotFound();
    }
}
