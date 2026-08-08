<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\Role;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\KingdomPlayer;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_manual_roster_entry_with_a_same_alliance_membership_link(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Roster Alliance', 'roster-alliance', 3001);
        $membership = $this->addMember($alliance->id, $member);

        $this->actingAs($owner)
            ->withSession($this->confirmedSession($alliance->id))
            ->post('/alliance/roster', [
                'name' => 'Rafah',
                'game_player_id' => 'player-1001',
                'membership_id' => $membership->id,
                'game_role' => 'R4',
                'state' => 'active',
                'joined_at' => '2026-08-01',
                'manager_notes' => 'Leadership roster note.',
            ])
            ->assertRedirect();

        $entry = AllianceRosterEntry::query()->sole();
        self::assertSame($alliance->id, $entry->alliance_id);
        self::assertSame($membership->id, $entry->membership_id);
        self::assertSame('Rafah', $entry->observed_name);
        self::assertSame('R4', $entry->game_role);
        self::assertSame('active', $entry->state->value);
        self::assertSame('manual', $entry->source);
        self::assertNotNull($entry->last_observed_at);
        self::assertSame('player-1001', $entry->player()->sole()->game_player_id);

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_user_id' => $owner->id,
            'event' => 'kingdoms.roster_entry_created',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'kingdoms.roster_entry_created',
        ]);
    }

    public function test_roster_mutation_requires_recent_password_confirmation(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Confirmation Alliance', 'confirmation-alliance', 3002);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->post('/alliance/roster', [
                'name' => 'Pending Player',
                'state' => 'active',
            ])
            ->assertRedirect(route('password.confirm'));

        $this->assertDatabaseCount('alliance_roster_entries', 0);
    }

    public function test_member_can_view_roster_but_cannot_manage_it(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'View Alliance', 'view-alliance', 3003);
        $this->addMember($alliance->id, $member);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($member)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance/roster')
            ->assertOk();

        $this->get('/alliance/roster/manage')->assertForbidden();

        $this->withSession([
            $sessionKey => $alliance->id,
            'auth.password_confirmed_at' => time(),
        ])->post('/alliance/roster', [
            'name' => 'Unauthorized Player',
            'state' => 'active',
        ])->assertForbidden();
    }

    public function test_default_leadership_roles_receive_kingdom_manage_but_member_and_specialist_roles_do_not(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Permission Alliance', 'permission-alliance', 3004);
        $authorization = $this->app->make(AllianceAuthorization::class);

        foreach ([DefaultAllianceRole::Leader, DefaultAllianceRole::Officer] as $roleKey) {
            $user = User::factory()->create();
            $membership = $this->addMember($alliance->id, $user, $roleKey);
            self::assertTrue($authorization->allows($user, $alliance, PermissionKey::KingdomManage));
            self::assertTrue($membership->roles()->where('roles.key', $roleKey->value)->exists());
        }

        foreach ([
            DefaultAllianceRole::Member,
            DefaultAllianceRole::Recruiter,
            DefaultAllianceRole::EventCoordinator,
            DefaultAllianceRole::ContentManager,
        ] as $roleKey) {
            $user = User::factory()->create();
            $this->addMember($alliance->id, $user, $roleKey);
            self::assertFalse($authorization->allows($user, $alliance, PermissionKey::KingdomManage));
        }
    }

    public function test_same_stable_game_player_is_shared_as_neutral_identity_but_roster_observations_remain_alliance_scoped(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwner, 'First Roster', 'first-roster', 3005);
        $second = $createAlliance->handle($secondOwner, 'Second Roster', 'second-roster', 3005);

        $this->actingAs($firstOwner)
            ->withSession($this->confirmedSession($first->id))
            ->post('/alliance/roster', [
                'name' => 'Shared Player',
                'game_player_id' => 'stable-3005',
                'state' => 'active',
                'manager_notes' => 'First alliance private note.',
            ])->assertRedirect();

        $this->actingAs($secondOwner)
            ->withSession($this->confirmedSession($second->id))
            ->post('/alliance/roster', [
                'name' => 'Renamed Shared Player',
                'game_player_id' => 'stable-3005',
                'state' => 'tracked',
                'manager_notes' => 'Second alliance private note.',
            ])->assertRedirect();

        self::assertSame(1, KingdomPlayer::query()->where('game_player_id', 'stable-3005')->count());
        self::assertSame(2, AllianceRosterEntry::query()->count());
        $firstEntry = AllianceRosterEntry::query()->where('alliance_id', $first->id)->sole();
        $secondEntry = AllianceRosterEntry::query()->where('alliance_id', $second->id)->sole();
        self::assertSame($firstEntry->kingdom_player_id, $secondEntry->kingdom_player_id);
        self::assertSame('First alliance private note.', $firstEntry->manager_notes);
        self::assertSame('Second alliance private note.', $secondEntry->manager_notes);
        self::assertSame('Shared Player', $firstEntry->observed_name);
        self::assertSame('Renamed Shared Player', $secondEntry->observed_name);
    }

    public function test_duplicate_display_names_without_stable_ids_do_not_merge_player_identity(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Duplicate Names', 'duplicate-names', 3006);
        $session = $this->confirmedSession($alliance->id);

        $this->actingAs($owner)->withSession($session)->post('/alliance/roster', [
            'name' => 'Same Name',
            'state' => 'active',
        ])->assertRedirect();
        $this->withSession($session)->post('/alliance/roster', [
            'name' => 'Same Name',
            'state' => 'tracked',
        ])->assertRedirect();

        self::assertSame(2, KingdomPlayer::query()->where('current_name', 'Same Name')->count());
        self::assertSame(2, AllianceRosterEntry::query()->where('alliance_id', $alliance->id)->count());
    }

    public function test_cross_alliance_membership_and_entry_ids_fail_closed(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $secondMember = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwner, 'Tenant One', 'tenant-one', 3007);
        $second = $createAlliance->handle($secondOwner, 'Tenant Two', 'tenant-two', 3007);
        $secondMembership = $this->addMember($second->id, $secondMember);

        $this->actingAs($secondOwner)
            ->withSession($this->confirmedSession($second->id))
            ->post('/alliance/roster', [
                'name' => 'Second Player',
                'state' => 'active',
            ])->assertRedirect();
        $secondEntry = AllianceRosterEntry::query()->where('alliance_id', $second->id)->sole();

        $this->actingAs($firstOwner)
            ->withSession($this->confirmedSession($first->id))
            ->from('/alliance/roster/manage')
            ->post('/alliance/roster', [
                'name' => 'Bad Link',
                'membership_id' => $secondMembership->id,
                'state' => 'active',
            ])
            ->assertRedirect('/alliance/roster/manage')
            ->assertSessionHasErrors('membership_id');

        $this->patch('/alliance/roster/'.$secondEntry->id, [
            'name' => 'Cross Tenant Edit',
            'state' => 'active',
        ])->assertNotFound();
    }

    public function test_mark_left_retains_identity_and_membership_link(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'History Alliance', 'history-alliance', 3008);
        $membership = $this->addMember($alliance->id, $member);
        $session = $this->confirmedSession($alliance->id);

        $this->actingAs($owner)->withSession($session)->post('/alliance/roster', [
            'name' => 'Leaving Player',
            'game_player_id' => 'leaving-3008',
            'membership_id' => $membership->id,
            'state' => 'active',
        ])->assertRedirect();
        $entry = AllianceRosterEntry::query()->sole();
        $playerId = $entry->kingdom_player_id;

        $this->withSession($session)
            ->post('/alliance/roster/'.$entry->id.'/leave')
            ->assertRedirect();

        $entry->refresh();
        self::assertSame('left', $entry->state->value);
        self::assertSame($playerId, $entry->kingdom_player_id);
        self::assertSame($membership->id, $entry->membership_id);
        self::assertNotNull($entry->left_at);
        $this->assertDatabaseHas('kingdom_players', ['id' => $playerId]);
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'event' => 'kingdoms.roster_entry_left',
        ]);
    }

    private function addMember(
        string $allianceId,
        User $user,
        DefaultAllianceRole $roleKey = DefaultAllianceRole::Member,
    ): AllianceMembership {
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $allianceId,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $role = Role::query()
            ->where('alliance_id', $allianceId)
            ->where('key', $roleKey->value)
            ->sole();
        $membership->roles()->attach($role->id, ['alliance_id' => $allianceId]);

        return $membership;
    }

    /** @return array<string, int|string> */
    private function confirmedSession(string $allianceId): array
    {
        return [
            (string) config('identity.active_alliance_session_key') => $allianceId,
            'auth.password_confirmed_at' => time(),
        ];
    }
}
