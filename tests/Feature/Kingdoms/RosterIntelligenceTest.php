<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Contexts\Accounts\Models\User;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Models\PlayerSnapshot;
use App\Domain\Memberships\Enums\AllianceRank;
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

    public function test_dashboard_distinguishes_missing_zero_stale_and_uses_deterministic_player_trend_windows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-08 12:00:00 UTC'));
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3301, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'intelligence-owner-3301',
            'current_name' => 'Intelligence Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'intelligence-member-3301',
            'current_name' => 'Intelligence Member',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Intelligence Alliance', 'intelligence-alliance');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $alpha = $this->createRosterEntry(
            $owner,
            $ownerPlayer,
            $alliance,
            'Alpha',
            gamePlayerId: 'intelligence-owner-3301',
            joinedAt: now()->subDays(2)->toDateString(),
        );
        self::assertSame($ownerPlayer->id, $alpha->player_id);
        $bravo = $this->createRosterEntry($owner, $ownerPlayer, $alliance, 'Bravo', state: 'tracked');
        $charlie = $this->createRosterEntry($owner, $ownerPlayer, $alliance, 'Charlie');
        $delta = $this->createRosterEntry($owner, $ownerPlayer, $alliance, 'Delta');
        $echo = $this->createRosterEntry($owner, $ownerPlayer, $alliance, 'Echo');
        $left = $this->createRosterEntry($owner, $ownerPlayer, $alliance, 'Former Player');

        $this->withSession($this->confirmedSession($ownerPlayer->id))
            ->post('/alliance/roster/'.$left->id.'/leave')
            ->assertRedirect();

        $this->snapshot($ownerPlayer, $alliance, $alpha, '70', now()->subDays(35));
        $this->snapshot($ownerPlayer, $alliance, $alpha, '80', now()->subDays(8));
        $this->snapshot($ownerPlayer, $alliance, $alpha, '100', now()->subDay());

        $this->snapshot($ownerPlayer, $alliance, $bravo, '220', now()->subDays(45));
        $this->snapshot($ownerPlayer, $alliance, $bravo, '250', now()->subDays(13));
        $this->snapshot($ownerPlayer, $alliance, $bravo, '200', now()->subDays(2));

        // Zero is a recorded value, not missing. The 29-day snapshot is too new for a 30-day
        // baseline and the 61-day snapshot is too old for the accepted 30–60 day window.
        $this->snapshot($ownerPlayer, $alliance, $charlie, '5', now()->subDays(61));
        $this->snapshot($ownerPlayer, $alliance, $charlie, '4', now()->subDays(29));
        $this->snapshot($ownerPlayer, $alliance, $charlie, '0', now()->subDay());

        // History exists, but the latest observation is stale. Reusing the same row as the
        // 30-day baseline must not create a false zero-change comparison.
        $this->snapshot($ownerPlayer, $alliance, $delta, '300', now()->subDays(31));
        self::assertNotNull($echo->id);

        // Same Kingdom, different Alliance tenant: this value must never affect the first Alliance.
        $otherOwner = User::factory()->create();
        $otherOwnerPlayer = Player::query()->create([
            'user_id' => $otherOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'other-intelligence-owner-3301',
            'current_name' => 'Other Intelligence Owner',
        ]);
        $other = $this->app->make(CreateAlliance::class)->handle($otherOwnerPlayer, 'Other Intelligence', 'other-intelligence');
        $otherEntry = $this->createRosterEntry($otherOwner, $otherOwnerPlayer, $other, 'Other Tenant');
        $this->snapshot($otherOwnerPlayer, $other, $otherEntry, '999999999', now()->subDay());

        $this->actingAs($member)
            ->withSession($this->activeSession($memberPlayer->id))
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
            ->withSession($this->activeSession($ownerPlayer->id))
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
        User $account,
        Player $actor,
        Alliance $alliance,
        string $name,
        ?string $gamePlayerId = null,
        string $state = 'active',
        ?string $joinedAt = null,
    ): AllianceRosterEntry {
        $this->actingAs($account)
            ->withSession($this->confirmedSession($actor->id))
            ->post('/alliance/roster', [
                'name' => $name,
                'game_player_id' => $gamePlayerId,
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
        Player $actor,
        Alliance $alliance,
        AllianceRosterEntry $entry,
        string $power,
        Carbon $capturedAt,
    ): PlayerSnapshot {
        return PlayerSnapshot::query()->create([
            'alliance_id' => $alliance->id,
            'roster_entry_id' => $entry->id,
            'player_id' => $entry->player_id,
            'actor_player_id' => $actor->id,
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

    /** @return array<string, string> */
    private function activeSession(string $playerId): array
    {
        return [(string) config('game_world.active_player_session_key') => $playerId];
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
