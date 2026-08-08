<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RosterPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_roster_payload_does_not_expose_private_linkage_data(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Private Roster', 'private-roster', 3101);
        $membership = $this->addMember($alliance->id, $member);

        $this->actingAs($owner)
            ->withSession($this->confirmedSession($alliance->id))
            ->post('/alliance/roster', [
                'name' => 'Private Player',
                'game_player_id' => 'private-3101',
                'membership_id' => $membership->id,
                'state' => 'active',
                'manager_notes' => 'Manager-only note.',
            ])
            ->assertRedirect();

        $this->actingAs($member)
            ->withSession($this->activeSession($alliance->id))
            ->get('/alliance/roster')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/Roster')
                ->where('entries.0.membership', ['name' => $member->name])
                ->missing('entries.0.membership.id')
                ->missing('entries.0.membership.email')
                ->missing('entries.0.managerNotes'));
    }

    public function test_existing_roster_entry_can_keep_its_current_membership_link_when_updated(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Relink Alliance', 'relink-alliance', 3102);
        $membership = $this->addMember($alliance->id, $member);
        $session = $this->confirmedSession($alliance->id);

        $this->actingAs($owner)->withSession($session)->post('/alliance/roster', [
            'name' => 'Linked Player',
            'membership_id' => $membership->id,
            'game_role' => 'R3',
            'state' => 'active',
        ])->assertRedirect();

        $entry = AllianceRosterEntry::query()->sole();

        $this->withSession($session)->patch('/alliance/roster/'.$entry->id, [
            'name' => 'Linked Player',
            'membership_id' => $membership->id,
            'game_role' => 'R4',
            'state' => 'active',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $entry->refresh();
        self::assertSame($membership->id, $entry->membership_id);
        self::assertSame('R4', $entry->game_role);
    }

    public function test_roster_filters_and_linkage_gaps_are_alliance_scoped(): void
    {
        $owner = User::factory()->create();
        $linkedUser = User::factory()->create();
        $unlinkedUser = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Filter Alliance', 'filter-alliance', 3103);
        $linkedMembership = $this->addMember($alliance->id, $linkedUser);
        $this->addMember($alliance->id, $unlinkedUser);
        $session = $this->confirmedSession($alliance->id);

        $this->actingAs($owner)->withSession($session)->post('/alliance/roster', [
            'name' => 'Current Linked',
            'membership_id' => $linkedMembership->id,
            'game_role' => 'R4',
            'state' => 'active',
        ])->assertRedirect();

        $this->withSession($session)->post('/alliance/roster', [
            'name' => 'Stale Unlinked',
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

        $this->withSession($this->activeSession($alliance->id))
            ->get('/alliance/roster?state=tracked&linkage=unlinked&role=R3&observation=stale')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/Roster')
                ->has('entries', 1)
                ->where('entries.0.name', 'Stale Unlinked')
                ->where('filters.state', 'tracked')
                ->where('filters.linkage', 'unlinked')
                ->where('filters.role', 'R3')
                ->where('filters.observation', 'stale'));

        $this->withSession($this->activeSession($alliance->id))
            ->get('/alliance/roster/manage')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/RosterManage')
                ->where('gaps.rosterWithoutMembership', 1)
                ->has('gaps.membershipsWithoutRoster', 2));
    }

    public function test_roster_search_never_discloses_another_alliance_in_the_same_kingdom(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwner, 'Visible Alliance', 'visible-alliance', 3104);
        $second = $createAlliance->handle($secondOwner, 'Hidden Alliance', 'hidden-alliance', 3104);

        $this->actingAs($secondOwner)
            ->withSession($this->confirmedSession($second->id))
            ->post('/alliance/roster', [
                'name' => 'Only Second Alliance',
                'game_player_id' => 'secret-3104',
                'state' => 'active',
                'manager_notes' => 'Never disclose this tenant note.',
            ])
            ->assertRedirect();

        $this->actingAs($firstOwner)
            ->withSession($this->activeSession($first->id))
            ->get('/alliance/roster?q=Only%20Second%20Alliance')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/Roster')
                ->has('entries', 0));
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

    /** @return array<string, string> */
    private function activeSession(string $allianceId): array
    {
        return [
            (string) config('identity.active_alliance_session_key') => $allianceId,
        ];
    }

    /** @return array<string, int|string> */
    private function confirmedSession(string $allianceId): array
    {
        return [
            ...$this->activeSession($allianceId),
            'auth.password_confirmed_at' => time(),
        ];
    }
}
