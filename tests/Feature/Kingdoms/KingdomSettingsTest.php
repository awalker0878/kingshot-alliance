<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KingdomSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_r5_can_view_alliance_kingdom_as_derived_read_only_state(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 1200, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'kingdom-settings-r5',
            'current_name' => 'Kingdom R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Kingdom Settings', 'kingdom-settings');
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $ownerPlayer->id])
            ->get('/alliance/settings/kingdom')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/KingdomSettings')
                ->where('alliance.id', $alliance->id)
                ->where('alliance.kingdom', '1200'));

        $this->withSession([
            $sessionKey => $ownerPlayer->id,
            'auth.password_confirmed_at' => time(),
        ])->patch('/alliance/settings/kingdom', ['kingdom' => 1300])
            ->assertStatus(405);

        self::assertSame($kingdom->id, $alliance->refresh()->kingdom_id);
        self::assertSame($kingdom->id, $ownerPlayer->refresh()->current_kingdom_id);
    }

    public function test_member_without_alliance_manage_cannot_view_kingdom_settings(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 1600, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'kingdom-settings-owner',
            'current_name' => 'Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'kingdom-settings-member',
            'current_name' => 'Member',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Restricted Kingdom', 'restricted-kingdom');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $this->actingAs($member)
            ->withSession([(string) config('game_world.active_player_session_key') => $memberPlayer->id])
            ->get('/alliance/settings/kingdom')
            ->assertForbidden();
    }

    public function test_sibling_player_does_not_inherit_r5_authority_from_same_user(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 1700, 'status' => 'active']);
        $r5 = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'sibling-r5',
            'current_name' => 'R5 Player',
        ]);
        $sibling = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'sibling-no-membership',
            'current_name' => 'Sibling Player',
        ]);
        $this->app->make(CreateAlliance::class)
            ->handle($r5, 'Sibling Authority', 'sibling-authority');

        $this->actingAs($user)
            ->withSession([(string) config('game_world.active_player_session_key') => $sibling->id])
            ->get('/alliance/settings/kingdom')
            ->assertStatus(409);
    }
}
