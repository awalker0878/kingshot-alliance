<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Models\PlayerSnapshot;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PlayerSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_player_can_record_snapshot_with_audit_outbox_and_player_provenance(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3201, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'snapshot-owner-3201',
            'current_name' => 'Snapshot Owner Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Snapshot Alliance', 'snapshot-alliance');
        $entry = $this->createRosterEntry($owner, $ownerPlayer, $alliance, 'Snapshot Player');
        $capturedAt = now()->subHour()->toIso8601String();

        $this->withSession($this->confirmedSession($ownerPlayer->id))
            ->post('/alliance/roster/'.$entry->id.'/snapshots', [
                'observed_name' => 'Snapshot Player',
                'power' => '1234567890123',
                'progression_level' => 'FC3',
                'observed_alliance_tag' => 'KSA',
                'captured_at' => $capturedAt,
            ])
            ->assertRedirect();

        $snapshot = PlayerSnapshot::query()->sole();
        self::assertSame($alliance->id, $snapshot->alliance_id);
        self::assertSame($entry->id, $snapshot->roster_entry_id);
        self::assertSame($entry->player_id, $snapshot->player_id);
        self::assertSame($ownerPlayer->id, $snapshot->actor_player_id);
        self::assertSame(1234567890123, $snapshot->power);
        self::assertSame('FC3', $snapshot->progression_level);
        self::assertSame('KSA', $snapshot->observed_alliance_tag);
        self::assertSame('manual', $snapshot->source);

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_player_id' => $ownerPlayer->id,
            'actor_user_id' => null,
            'event' => 'kingdoms.player_snapshot_recorded',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'kingdoms.player_snapshot_recorded',
        ]);

        $this->withSession($this->activeSession($ownerPlayer->id))
            ->get('/alliance/roster/'.$entry->id.'/history')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/RosterHistory')
                ->where('latest.power', '1234567890123')
                ->where('latest.progressionLevel', 'FC3')
                ->where('snapshots.0.actorName', 'Snapshot Owner Player'));
    }

    public function test_snapshot_mutation_requires_recent_password_confirmation_for_active_player(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3202, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'snapshot-confirm-owner',
            'current_name' => 'Snapshot Confirm Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Confirm Snapshot', 'confirm-snapshot');
        $entry = $this->createRosterEntry($owner, $ownerPlayer, $alliance, 'Confirm Player');

        $this->withSession([
            ...$this->activeSession($ownerPlayer->id),
            'auth.password_confirmed_at' => 0,
        ])
            ->post('/alliance/roster/'.$entry->id.'/snapshots', $this->snapshotPayload('100'))
            ->assertRedirect(route('password.confirm'));

        $this->assertDatabaseCount('player_snapshots', 0);
    }

    public function test_member_player_can_view_snapshot_history_without_actor_identity_but_cannot_record(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3203, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'snapshot-member-owner',
            'current_name' => 'Snapshot Member Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'snapshot-member-player',
            'current_name' => 'Snapshot Member',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Member Snapshot', 'member-snapshot');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $entry = $this->createRosterEntry($owner, $ownerPlayer, $alliance, 'Visible Snapshot');

        $this->withSession($this->confirmedSession($ownerPlayer->id))
            ->post('/alliance/roster/'.$entry->id.'/snapshots', $this->snapshotPayload('200'))
            ->assertRedirect();

        $this->actingAs($member)
            ->withSession($this->activeSession($memberPlayer->id))
            ->get('/alliance/roster/'.$entry->id.'/history')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/RosterHistory')
                ->where('canManage', false)
                ->where('snapshots.0.power', '200')
                ->missing('snapshots.0.actorName'));

        $this->withSession($this->confirmedSession($memberPlayer->id))
            ->post('/alliance/roster/'.$entry->id.'/snapshots', $this->snapshotPayload('201'))
            ->assertForbidden();

        $this->assertDatabaseCount('player_snapshots', 1);
    }

    public function test_exact_retry_is_idempotent_but_later_capture_preserves_new_history(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3204, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'snapshot-idempotent-owner',
            'current_name' => 'Snapshot Idempotent Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Idempotent Snapshot', 'idempotent-snapshot');
        $entry = $this->createRosterEntry($owner, $ownerPlayer, $alliance, 'Retry Player');
        $capturedAt = now()->subHours(2)->startOfSecond();
        $payload = $this->snapshotPayload('300', $capturedAt->toIso8601String());
        $session = $this->confirmedSession($ownerPlayer->id);

        $this->withSession($session)->post('/alliance/roster/'.$entry->id.'/snapshots', $payload)->assertRedirect();
        $this->withSession($session)->post('/alliance/roster/'.$entry->id.'/snapshots', $payload)->assertRedirect();

        $this->assertDatabaseCount('player_snapshots', 1);
        self::assertSame(1, (int) \DB::table('audit_events')->where('event', 'kingdoms.player_snapshot_recorded')->count());
        self::assertSame(1, (int) \DB::table('outbox_messages')->where('event_type', 'kingdoms.player_snapshot_recorded')->count());

        $laterPayload = $payload;
        $laterPayload['captured_at'] = $capturedAt->addMinute()->toIso8601String();
        $this->withSession($session)->post('/alliance/roster/'.$entry->id.'/snapshots', $laterPayload)->assertRedirect();
        $this->assertDatabaseCount('player_snapshots', 2);

        $this->withSession($session)
            ->patch('/alliance/roster/'.$entry->id, [
                'name' => 'Retry Player Renamed',
                'state' => 'active',
            ])->assertRedirect();

        $this->assertDatabaseCount('player_snapshots', 2);
        self::assertSame(
            ['300', '300'],
            PlayerSnapshot::query()->orderBy('captured_at')->get()
                ->map(static fn (PlayerSnapshot $snapshot): string => (string) $snapshot->power)
                ->all(),
        );
    }

    public function test_power_uses_signed_64_bit_range_without_floating_point_storage(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3205, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'snapshot-power-owner',
            'current_name' => 'Snapshot Power Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Power Range', 'power-range');
        $entry = $this->createRosterEntry($owner, $ownerPlayer, $alliance, 'Power Player');
        $session = $this->confirmedSession($ownerPlayer->id);

        $this->withSession($session)
            ->post('/alliance/roster/'.$entry->id.'/snapshots', $this->snapshotPayload('9223372036854775807'))
            ->assertRedirect();

        self::assertSame(PHP_INT_MAX, PlayerSnapshot::query()->sole()->power);

        $this->from('/alliance/roster/'.$entry->id.'/history')
            ->withSession($session)
            ->post('/alliance/roster/'.$entry->id.'/snapshots', $this->snapshotPayload('9223372036854775808'))
            ->assertRedirect('/alliance/roster/'.$entry->id.'/history')
            ->assertSessionHasErrors('power');

        $this->assertDatabaseCount('player_snapshots', 1);
    }

    public function test_snapshot_history_and_mutation_fail_closed_for_another_active_player_alliance(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3206, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'snapshot-first-owner',
            'current_name' => 'Snapshot First Owner',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'snapshot-second-owner',
            'current_name' => 'Snapshot Second Owner',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstPlayer, 'Snapshot Tenant One', 'snapshot-tenant-one');
        $second = $createAlliance->handle($secondPlayer, 'Snapshot Tenant Two', 'snapshot-tenant-two');
        $secondEntry = $this->createRosterEntry($secondOwner, $secondPlayer, $second, 'Second Tenant Player');

        $this->actingAs($secondOwner)
            ->withSession($this->confirmedSession($secondPlayer->id))
            ->post('/alliance/roster/'.$secondEntry->id.'/snapshots', $this->snapshotPayload('400'))
            ->assertRedirect();

        $this->actingAs($firstOwner)
            ->withSession($this->activeSession($firstPlayer->id))
            ->get('/alliance/roster/'.$secondEntry->id.'/history')
            ->assertNotFound();

        $this->withSession($this->confirmedSession($firstPlayer->id))
            ->post('/alliance/roster/'.$secondEntry->id.'/snapshots', $this->snapshotPayload('401'))
            ->assertNotFound();

        $this->assertDatabaseCount('player_snapshots', 1);
        self::assertSame($secondPlayer->id, PlayerSnapshot::query()->sole()->actor_player_id);
        self::assertSame($first->kingdom_id, $second->kingdom_id);
    }

    public function test_latest_projection_uses_capture_time_and_freshness_distinguishes_current_stale_and_missing(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3207, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'snapshot-freshness-owner',
            'current_name' => 'Snapshot Freshness Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Freshness Alliance', 'freshness-alliance');
        $current = $this->createRosterEntry($owner, $ownerPlayer, $alliance, 'Current Player');
        $missing = $this->createRosterEntry($owner, $ownerPlayer, $alliance, 'Missing Player');
        $stale = $this->createRosterEntry($owner, $ownerPlayer, $alliance, 'Stale Player');
        $session = $this->confirmedSession($ownerPlayer->id);

        $this->withSession($session)->post('/alliance/roster/'.$current->id.'/snapshots', $this->snapshotPayload(
            '600',
            now()->subDay()->toIso8601String(),
        ))->assertRedirect();
        $this->withSession($session)->post('/alliance/roster/'.$current->id.'/snapshots', $this->snapshotPayload(
            '500',
            now()->subDays(10)->toIso8601String(),
        ))->assertRedirect();
        $this->withSession($session)->post('/alliance/roster/'.$stale->id.'/snapshots', $this->snapshotPayload(
            '700',
            now()->subDays(31)->toIso8601String(),
        ))->assertRedirect();

        $this->withSession($this->activeSession($ownerPlayer->id))
            ->get('/alliance/roster')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/Roster')
                ->where('entries.0.name', 'Current Player')
                ->where('entries.0.latestSnapshot.power', '600')
                ->where('entries.1.name', 'Missing Player')
                ->where('entries.1.latestSnapshot', null)
                ->where('entries.2.name', 'Stale Player')
                ->where('entries.2.latestSnapshot.power', '700'));

        foreach ([
            'current' => 'Current Player',
            'stale' => 'Stale Player',
            'missing' => 'Missing Player',
        ] as $filter => $expectedName) {
            $this->get('/alliance/roster?observation='.$filter)
                ->assertOk()
                ->assertInertia(fn (Assert $page): Assert => $page
                    ->component('Alliance/Roster')
                    ->has('entries', 1)
                    ->where('entries.0.name', $expectedName));
        }

        self::assertSame($missing->id, AllianceRosterEntry::query()->findOrFail($missing->id)->id);
    }

    private function createRosterEntry(User $account, Player $actor, Alliance $alliance, string $name): AllianceRosterEntry
    {
        $this->actingAs($account)
            ->withSession($this->confirmedSession($actor->id))
            ->post('/alliance/roster', [
                'name' => $name,
                'state' => 'active',
            ])
            ->assertRedirect();

        return AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('observed_name', $name)
            ->latest('created_at')
            ->firstOrFail();
    }

    /** @return array<string, string> */
    private function snapshotPayload(string $power, ?string $capturedAt = null): array
    {
        return [
            'observed_name' => 'Observed Player',
            'power' => $power,
            'progression_level' => 'FC2',
            'observed_alliance_tag' => 'TAG',
            'captured_at' => $capturedAt ?? now()->subMinute()->toIso8601String(),
        ];
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
