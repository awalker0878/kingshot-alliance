<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\PlayerSnapshot;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RosterIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_distinguishes_missing_zero_stale_and_uses_deterministic_trend_windows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-08 12:00:00 UTC'));
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Intelligence Alliance', 'intelligence-alliance', 3301);
        $this->addMember($alliance->id, $member);
        $ownerMembership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $owner->id)
            ->sole();

        $alpha = $this->createRosterEntry(
            $owner,
            $alliance,
            'Alpha',
            membershipId: $ownerMembership->id,
            joinedAt: now()->subDays(2)->toDateString(),
        );
        $bravo = $this->createRosterEntry($owner, $alliance, 'Bravo', state: 'tracked');
        $charlie = $this->createRosterEntry($owner, $alliance, 'Charlie');
        $delta = $this->createRosterEntry($owner, $alliance, 'Delta');
        $echo = $this->createRosterEntry($owner, $alliance, 'Echo');
        $left = $this->createRosterEntry($owner, $alliance, 'Former Player');

        $this->withSession($this->confirmedSession($alliance->id))
            ->post('/alliance/roster/'.$left->id.'/leave')
            ->assertRedirect();

        $this->snapshot($owner, $alliance, $alpha, '70', now()->subDays(35));
        $this->snapshot($owner, $alliance, $alpha, '80', now()->subDays(8));
        $this->snapshot($owner, $alliance, $alpha, '100', now()->subDay());

        $this->snapshot($owner, $alliance, $bravo, '220', now()->subDays(45));
        $this->snapshot($owner, $alliance, $bravo, '250', now()->subDays(13));
        $this->snapshot($owner, $alliance, $bravo, '200', now()->subDays(2));

        // Zero is a recorded value, not missing. The 29-day snapshot is too new for a 30-day
        // baseline and the 61-day snapshot is too old for the accepted 30–60 day window.
        $this->snapshot($owner, $alliance, $charlie, '5', now()->subDays(61));
        $this->snapshot($owner, $alliance, $charlie, '4', now()->subDays(29));
        $this->snapshot($owner, $alliance, $charlie, '0', now()->subDay());

        // History exists, but the latest observation is stale. Reusing the same row as the
        // 30-day baseline must not create a false zero-change comparison.
        $this->snapshot($owner, $alliance, $delta, '300', now()->subDays(31));
        self::assertNotNull($echo->id);

        // Same Kingdom, different tenant: this value must never affect the first alliance.
        $otherOwner = User::factory()->create();
        $other = $this->app->make(CreateAlliance::class)
            ->handle($otherOwner, 'Other Intelligence', 'other-intelligence', 3301);
        $otherEntry = $this->createRosterEntry($otherOwner, $other, 'Other Tenant');
        $this->snapshot($otherOwner, $other, $otherEntry, '999999999', now()->subDay());

        $this->actingAs($member)
            ->withSession($this->activeSession($alliance->id))
            ->get('/alliance/roster/intelligence')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/RosterIntelligence')
                ->where('canManage', false)
                ->where('metrics.trackedPlayers', 5)
                ->where('metrics.recordedPowerPlayers', 4)
                ->where('metrics.totalPower', '600')
                ->where('metrics.averagePower', '150')
                ->where('metrics.medianPower', '150')
                ->where('metrics.snapshotQuality.current', 3)
                ->where('metrics.snapshotQuality.stale', 1)
                ->where('metrics.snapshotQuality.missing', 1)
                ->where('metrics.recentRoster.joins', 1)
                ->where('metrics.recentRoster.departures', 1)
                ->where('metrics.linkage.linked', 1)
                ->where('metrics.linkage.total', 5)
                ->where('metrics.linkage.percent', '20.0')
                ->where('metrics.sevenDayTrend.change', '-30')
                ->where('metrics.sevenDayTrend.comparablePlayers', 2)
                ->where('metrics.thirtyDayTrend.change', '10')
                ->where('metrics.thirtyDayTrend.comparablePlayers', 2)
                ->has('metrics.comparisons', 0));

        $this->actingAs($owner)
            ->withSession($this->activeSession($alliance->id))
            ->get('/alliance/roster/intelligence')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/RosterIntelligence')
                ->where('canManage', true)
                ->has('metrics.comparisons', 5)
                ->where('metrics.comparisons.0.name', 'Alpha')
                ->where('metrics.comparisons.0.sevenDay.change', '20')
                ->where('metrics.comparisons.0.thirtyDay.change', '30')
                ->where('metrics.comparisons.1.name', 'Bravo')
                ->where('metrics.comparisons.1.sevenDay.change', '-50')
                ->where('metrics.comparisons.1.thirtyDay.change', '-20')
                ->where('metrics.comparisons.2.name', 'Charlie')
                ->where('metrics.comparisons.2.current.power', '0')
                ->where('metrics.comparisons.2.sevenDay', null)
                ->where('metrics.comparisons.2.thirtyDay', null)
                ->where('metrics.comparisons.3.name', 'Delta')
                ->where('metrics.comparisons.3.snapshotState', 'stale')
                ->where('metrics.comparisons.3.thirtyDay', null)
                ->where('metrics.comparisons.4.name', 'Echo')
                ->where('metrics.comparisons.4.snapshotState', 'missing'));
    }

    private function createRosterEntry(
        User $owner,
        Alliance $alliance,
        string $name,
        ?string $membershipId = null,
        string $state = 'active',
        ?string $joinedAt = null,
    ): AllianceRosterEntry {
        $this->actingAs($owner)
            ->withSession($this->confirmedSession($alliance->id))
            ->post('/alliance/roster', [
                'name' => $name,
                'membership_id' => $membershipId,
                'state' => $state,
                'joined_at' => $joinedAt,
            ])
            ->assertRedirect();

        return AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('observed_name', $name)
            ->sole();
    }

    private function snapshot(
        User $actor,
        Alliance $alliance,
        AllianceRosterEntry $entry,
        string $power,
        Carbon $capturedAt,
    ): PlayerSnapshot {
        return PlayerSnapshot::query()->create([
            'alliance_id' => $alliance->id,
            'roster_entry_id' => $entry->id,
            'kingdom_player_id' => $entry->kingdom_player_id,
            'actor_user_id' => $actor->id,
            'observed_name' => $entry->observed_name,
            'power' => $power,
            'captured_at' => $capturedAt,
            'source' => 'manual',
            'idempotency_key' => hash('sha256', implode('|', [
                (string) $alliance->id,
                (string) $entry->id,
                $power,
                $capturedAt->toIso8601String(),
            ])),
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

    /** @return array<string, string> */
    private function activeSession(string $allianceId): array
    {
        return [
            (string) config('identity.active_alliance_session_key') => $allianceId,
        ];
    }

    /** @return array<string, int|string|null> */
    private function confirmedSession(string $allianceId): array
    {
        return [
            ...$this->activeSession($allianceId),
            'auth.password_confirmed_at' => time(),
        ];
    }
}
