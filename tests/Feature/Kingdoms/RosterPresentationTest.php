<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RosterPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_roster_payload_exposes_derived_player_membership_without_internal_membership_id_or_private_notes(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3101, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'owner-3101',
            'current_name' => 'Private Roster Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'private-3101',
            'current_name' => 'Private Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Private Roster', 'private-roster');
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
                'name' => 'Private Player',
                'game_player_id' => 'private-3101',
                'state' => 'active',
                'manager_notes' => 'Manager-only note.',
            ])
            ->assertRedirect();

        $this->actingAs($member)
            ->withSession($this->activeSession($memberPlayer->id))
            ->get('/alliance/roster')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/Roster')
                ->where('entries.0.playerId', $memberPlayer->id)
                ->where('entries.0.membership.playerId', $memberPlayer->id)
                ->where('entries.0.membership.name', 'Private Player')
                ->where('entries.0.membership.rank', AllianceRank::R1->value)
                ->missing('entries.0.membership.id')
                ->missing('entries.0.membership.email')
                ->missing('entries.0.managerNotes'));
    }

    public function test_existing_roster_entry_keeps_player_identity_when_updated_and_membership_linkage_remains_derived(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3102, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'owner-3102',
            'current_name' => 'Relink Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'linked-3102',
            'current_name' => 'Linked Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Relink Alliance', 'relink-alliance');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $session = $this->confirmedSession($ownerPlayer->id);

        $this->actingAs($owner)->withSession($session)->post('/alliance/roster', [
            'name' => 'Linked Player',
            'game_player_id' => 'linked-3102',
            'game_role' => 'R3',
            'state' => 'active',
        ])->assertRedirect();

        $entry = AllianceRosterEntry::query()->sole();
        self::assertSame($memberPlayer->id, $entry->player_id);

        $this->withSession($session)->patch('/alliance/roster/'.$entry->id, [
            'name' => 'Linked Player',
            'game_role' => 'R4',
            'state' => 'active',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $entry->refresh();
        self::assertSame($memberPlayer->id, $entry->player_id);
        self::assertSame('R4', $entry->game_role);

        $this->withSession($this->activeSession($ownerPlayer->id))
            ->get('/alliance/roster')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('entries.0.membership.playerId', $memberPlayer->id)
                ->missing('entries.0.membership.id'));
    }

    public function test_roster_filters_and_linkage_gaps_are_derived_from_player_memberships_and_alliance_scoped(): void
    {
        $owner = User::factory()->create();
        $linkedUser = User::factory()->create();
        $unlinkedUser = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3103, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'owner-3103',
            'current_name' => 'Filter Owner',
        ]);
        $linkedPlayer = Player::query()->create([
            'user_id' => $linkedUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'linked-3103',
            'current_name' => 'Current Linked',
        ]);
        $unlinkedPlayer = Player::query()->create([
            'user_id' => $unlinkedUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'unlinked-member-3103',
            'current_name' => 'Membership Without Roster',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Filter Alliance', 'filter-alliance');
        foreach ([$linkedPlayer, $unlinkedPlayer] as $player) {
            AllianceMembership::query()->create([
                'alliance_id' => $alliance->id,
                'player_id' => $player->id,
                'status' => MembershipStatus::Active,
                'rank' => AllianceRank::R1,
                'joined_at' => now(),
            ]);
        }
        $session = $this->confirmedSession($ownerPlayer->id);

        $this->actingAs($owner)->withSession($session)->post('/alliance/roster', [
            'name' => 'Current Linked',
            'game_player_id' => 'linked-3103',
            'game_role' => 'R4',
            'state' => 'active',
        ])->assertRedirect();

        $this->withSession($session)->post('/alliance/roster', [
            'name' => 'Stale Unlinked',
            'game_player_id' => 'roster-only-3103',
            'game_role' => 'R3',
            'state' => 'tracked',
        ])->assertRedirect();

        $staleEntry = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('observed_name', 'Stale Unlinked')
            ->sole();

        $this->withSession($session)
            ->post('/alliance/roster/'.$staleEntry->id.'/snapshots', [
                'observed_name' => 'Stale Unlinked',
                'power' => '1000',
                'captured_at' => now()->subDays(31)->toIso8601String(),
            ])
            ->assertRedirect();

        $this->withSession($this->activeSession($ownerPlayer->id))
            ->get('/alliance/roster?state=tracked&linkage=unlinked&role=R3&observation=stale')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/Roster')
                ->has('entries', 1)
                ->where('entries.0.name', 'Stale Unlinked')
                ->where('entries.0.membership', null)
                ->where('filters.state', 'tracked')
                ->where('filters.linkage', 'unlinked')
                ->where('filters.role', 'R3')
                ->where('filters.observation', 'stale'));

        $this->withSession($this->activeSession($ownerPlayer->id))
            ->get('/alliance/roster/manage')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/RosterManage')
                ->where('gaps.rosterWithoutMembership', 1)
                ->has('gaps.membershipsWithoutRoster', 2)
                ->missing('gaps.membershipsWithoutRoster.0.id'));
    }

    public function test_roster_search_never_discloses_another_alliance_in_the_same_kingdom(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3104, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'visible-owner-3104',
            'current_name' => 'Visible Owner',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'hidden-owner-3104',
            'current_name' => 'Hidden Owner',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstPlayer, 'Visible Alliance', 'visible-alliance');
        $second = $createAlliance->handle($secondPlayer, 'Hidden Alliance', 'hidden-alliance');

        $this->actingAs($secondOwner)
            ->withSession($this->confirmedSession($secondPlayer->id))
            ->post('/alliance/roster', [
                'name' => 'Only Second Alliance',
                'game_player_id' => 'secret-3104',
                'state' => 'active',
                'manager_notes' => 'Never disclose this tenant note.',
            ])
            ->assertRedirect();

        $this->actingAs($firstOwner)
            ->withSession($this->activeSession($firstPlayer->id))
            ->get('/alliance/roster?q=Only%20Second%20Alliance')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/Roster')
                ->where('alliance.id', $first->id)
                ->has('entries', 0));
    }

    /** @return array<string, string> */
    private function activeSession(string $playerId): array
    {
        return [(string) config('identity.active_player_session_key') => $playerId];
    }

    /** @return array<string, int|string> */
    private function confirmedSession(string $playerId): array
    {
        return [
            ...$this->activeSession($playerId),
            'auth.password_confirmed_at' => time(),
        ];
    }
}
