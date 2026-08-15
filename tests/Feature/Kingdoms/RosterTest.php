<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_manual_roster_entry_for_existing_member_player_and_linkage_is_derived(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3001, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'owner-3001',
            'current_name' => 'Roster Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'player-1001',
            'current_name' => 'Rafah',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Roster Alliance', 'roster-alliance');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withSession($this->confirmedSession($ownerPlayer->id))
            ->post('/alliance/roster', [
                'name' => 'Rafah',
                'game_player_id' => 'player-1001',
                'game_role' => 'R4',
                'state' => 'active',
                'joined_at' => '2026-08-01',
                'manager_notes' => 'Leadership roster note.',
            ])
            ->assertRedirect();

        $entry = AllianceRosterEntry::query()->sole();
        self::assertSame($alliance->id, $entry->alliance_id);
        self::assertSame($memberPlayer->id, $entry->player_id);
        self::assertSame('Rafah', $entry->observed_name);
        self::assertSame('R4', $entry->game_role);
        self::assertSame('active', $entry->state->value);
        self::assertSame('manual', $entry->source);
        self::assertNotNull($entry->last_observed_at);
        self::assertSame('player-1001', $entry->player()->sole()->game_player_id);

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_player_id' => $ownerPlayer->id,
            'actor_user_id' => null,
            'event' => 'kingdoms.roster_entry_created',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'kingdoms.roster_entry_created',
        ]);
    }

    public function test_roster_mutation_requires_recent_password_confirmation_for_active_player(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3002, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'owner-3002',
            'current_name' => 'Confirmation Owner',
        ]);
        $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Confirmation Alliance', 'confirmation-alliance');

        $this->actingAs($owner)
            ->withSession([(string) config('game_world.active_player_session_key') => $ownerPlayer->id])
            ->post('/alliance/roster', [
                'name' => 'Pending Player',
                'state' => 'active',
            ])
            ->assertRedirect(route('password.confirm'));

        $this->assertDatabaseCount('alliance_roster_entries', 0);
    }

    public function test_member_player_can_view_roster_but_cannot_manage_it(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3003, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'owner-3003',
            'current_name' => 'View Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'member-3003',
            'current_name' => 'View Member',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'View Alliance', 'view-alliance');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $this->actingAs($member)
            ->withSession([(string) config('game_world.active_player_session_key') => $memberPlayer->id])
            ->get('/alliance/roster')
            ->assertOk();

        $this->get('/alliance/roster/manage')->assertForbidden();

        $this->withSession($this->confirmedSession($memberPlayer->id))
            ->post('/alliance/roster', [
                'name' => 'Unauthorized Player',
                'state' => 'active',
            ])->assertForbidden();
    }

    public function test_r5_and_r4_players_receive_kingdom_manage_but_lower_ranks_and_specialists_do_not(): void
    {
        $r5User = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3004, 'status' => 'active']);
        $r5 = Player::query()->create([
            'user_id' => $r5User->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'r5-3004',
            'current_name' => 'R5 Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($r5, 'Permission Alliance', 'permission-alliance');
        $authorization = $this->app->make(AllianceAuthorization::class);

        self::assertTrue($authorization->allows($r5, $alliance, IntelligencePermission::KingdomManage));

        $r4User = User::factory()->create();
        $r4 = Player::query()->create([
            'user_id' => $r4User->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'r4-3004',
            'current_name' => 'R4 Player',
        ]);
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $r4->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R4,
            'joined_at' => now(),
        ]);
        self::assertTrue($authorization->allows($r4, $alliance, IntelligencePermission::KingdomManage));

        foreach ([AllianceRank::R1, AllianceRank::R2, AllianceRank::R3] as $index => $rank) {
            $user = User::factory()->create();
            $player = Player::query()->create([
                'user_id' => $user->id,
                'current_kingdom_id' => $kingdom->id,
                'game_player_id' => 'lower-3004-'.$index,
                'current_name' => 'Lower Rank '.$rank->value,
            ]);
            AllianceMembership::query()->create([
                'alliance_id' => $alliance->id,
                'player_id' => $player->id,
                'status' => MembershipStatus::Active,
                'rank' => $rank,
                'joined_at' => now(),
            ]);
            self::assertFalse($authorization->allows($player, $alliance, IntelligencePermission::KingdomManage));
        }

        foreach ([
            DefaultAllianceRole::Recruiter,
            DefaultAllianceRole::EventCoordinator,
            DefaultAllianceRole::ContentManager,
        ] as $index => $specialistRole) {
            $user = User::factory()->create();
            $player = Player::query()->create([
                'user_id' => $user->id,
                'current_kingdom_id' => $kingdom->id,
                'game_player_id' => 'specialist-3004-'.$index,
                'current_name' => 'Specialist '.$specialistRole->value,
            ]);
            $membership = AllianceMembership::query()->create([
                'alliance_id' => $alliance->id,
                'player_id' => $player->id,
                'status' => MembershipStatus::Active,
                'rank' => AllianceRank::R1,
                'joined_at' => now(),
            ]);
            $role = Role::query()
                ->where('alliance_id', $alliance->id)
                ->where('key', $specialistRole->value)
                ->sole();
            $membership->roles()->attach($role->id, ['alliance_id' => $alliance->id]);
            self::assertFalse($authorization->allows($player, $alliance, IntelligencePermission::KingdomManage));
        }
    }

    public function test_same_stable_game_player_is_shared_as_neutral_identity_but_roster_observations_remain_alliance_scoped(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3005, 'status' => 'active']);
        $firstOwnerPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'first-owner-3005',
            'current_name' => 'First Owner',
        ]);
        $secondOwnerPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'second-owner-3005',
            'current_name' => 'Second Owner',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwnerPlayer, 'First Roster', 'first-roster');
        $second = $createAlliance->handle($secondOwnerPlayer, 'Second Roster', 'second-roster');

        $this->actingAs($firstOwner)
            ->withSession($this->confirmedSession($firstOwnerPlayer->id))
            ->post('/alliance/roster', [
                'name' => 'Shared Player',
                'game_player_id' => 'stable-3005',
                'state' => 'active',
                'manager_notes' => 'First alliance private note.',
            ])->assertRedirect();

        $this->actingAs($secondOwner)
            ->withSession($this->confirmedSession($secondOwnerPlayer->id))
            ->post('/alliance/roster', [
                'name' => 'Renamed Shared Player',
                'game_player_id' => 'stable-3005',
                'state' => 'tracked',
                'manager_notes' => 'Second alliance private note.',
            ])->assertRedirect();

        self::assertSame(1, Player::query()->where('game_player_id', 'stable-3005')->count());
        self::assertSame(2, AllianceRosterEntry::query()->count());
        $firstEntry = AllianceRosterEntry::query()->where('alliance_id', $first->id)->sole();
        $secondEntry = AllianceRosterEntry::query()->where('alliance_id', $second->id)->sole();
        self::assertSame($firstEntry->player_id, $secondEntry->player_id);
        self::assertSame('First alliance private note.', $firstEntry->manager_notes);
        self::assertSame('Second alliance private note.', $secondEntry->manager_notes);
        self::assertSame('Shared Player', $firstEntry->observed_name);
        self::assertSame('Renamed Shared Player', $secondEntry->observed_name);
    }

    public function test_duplicate_display_names_without_stable_ids_do_not_merge_player_identity(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3006, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'owner-3006',
            'current_name' => 'Duplicate Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Duplicate Names', 'duplicate-names');
        $session = $this->confirmedSession($ownerPlayer->id);

        $this->actingAs($owner)->withSession($session)->post('/alliance/roster', [
            'name' => 'Same Name',
            'state' => 'active',
        ])->assertRedirect();
        $this->withSession($session)->post('/alliance/roster', [
            'name' => 'Same Name',
            'state' => 'tracked',
        ])->assertRedirect();

        self::assertSame(2, Player::query()->where('current_name', 'Same Name')->count());
        self::assertSame(2, AllianceRosterEntry::query()->where('alliance_id', $alliance->id)->count());
    }

    public function test_foreign_membership_id_cannot_rebind_roster_identity_and_cross_alliance_entry_ids_fail_closed(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $secondMember = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3007, 'status' => 'active']);
        $firstOwnerPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'first-owner-3007',
            'current_name' => 'First Tenant Owner',
        ]);
        $secondOwnerPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'second-owner-3007',
            'current_name' => 'Second Tenant Owner',
        ]);
        $secondMemberPlayer = Player::query()->create([
            'user_id' => $secondMember->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'second-member-3007',
            'current_name' => 'Second Member',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwnerPlayer, 'Tenant One', 'tenant-one');
        $second = $createAlliance->handle($secondOwnerPlayer, 'Tenant Two', 'tenant-two');
        $secondMembership = AllianceMembership::query()->create([
            'alliance_id' => $second->id,
            'player_id' => $secondMemberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $this->actingAs($secondOwner)
            ->withSession($this->confirmedSession($secondOwnerPlayer->id))
            ->post('/alliance/roster', [
                'name' => 'Second Player',
                'game_player_id' => 'second-roster-player-3007',
                'state' => 'active',
            ])->assertRedirect();
        $secondEntry = AllianceRosterEntry::query()->where('alliance_id', $second->id)->where('observed_name', 'Second Player')->sole();

        $this->actingAs($firstOwner)
            ->withSession($this->confirmedSession($firstOwnerPlayer->id))
            ->post('/alliance/roster', [
                'name' => 'Foreign Link Ignored',
                'game_player_id' => 'first-roster-player-3007',
                'membership_id' => $secondMembership->id,
                'state' => 'active',
            ])->assertRedirect();

        $firstEntry = AllianceRosterEntry::query()->where('alliance_id', $first->id)->sole();
        self::assertNotSame($secondMemberPlayer->id, $firstEntry->player_id);
        self::assertSame('first-roster-player-3007', $firstEntry->player->game_player_id);

        $this->patch('/alliance/roster/'.$secondEntry->id, [
            'name' => 'Cross Tenant Edit',
            'state' => 'active',
        ])->assertNotFound();
    }

    public function test_mark_left_retains_player_identity_while_membership_remains_independent(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3008, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'owner-3008',
            'current_name' => 'History Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'leaving-3008',
            'current_name' => 'Leaving Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'History Alliance', 'history-alliance');
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $session = $this->confirmedSession($ownerPlayer->id);

        $this->actingAs($owner)->withSession($session)->post('/alliance/roster', [
            'name' => 'Leaving Player',
            'game_player_id' => 'leaving-3008',
            'state' => 'active',
        ])->assertRedirect();
        $entry = AllianceRosterEntry::query()->sole();

        $this->withSession($session)
            ->post('/alliance/roster/'.$entry->id.'/leave')
            ->assertRedirect();

        $entry->refresh();
        self::assertSame('left', $entry->state->value);
        self::assertSame($memberPlayer->id, $entry->player_id);
        self::assertNotNull($entry->left_at);
        self::assertSame(MembershipStatus::Active, $membership->refresh()->status);
        self::assertSame($memberPlayer->id, $membership->player_id);
        $this->assertDatabaseHas('players', ['id' => $memberPlayer->id]);
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_player_id' => $ownerPlayer->id,
            'event' => 'kingdoms.roster_entry_left',
        ]);
    }

    /** @return array<string, int|string> */
    private function confirmedSession(string $playerId): array
    {
        return [
            (string) config('game_world.active_player_session_key') => $playerId,
            'auth.password_confirmed_at' => time(),
        ];
    }
}
