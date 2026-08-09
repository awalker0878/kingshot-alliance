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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PlayerSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_record_snapshot_with_audit_outbox_and_private_actor_provenance(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Snapshot Alliance', 'snapshot-alliance', 3201);
        $entry = $this->createRosterEntry($owner, $alliance, 'Snapshot Player');
        $capturedAt = now()->subHour()->toIso8601String();

        $this->withSession($this->confirmedSession($alliance->id))
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
        self::assertSame($entry->kingdom_player_id, $snapshot->kingdom_player_id);
        self::assertSame($owner->id, $snapshot->actor_user_id);
        self::assertSame(1234567890123, $snapshot->power);
        self::assertSame('FC3', $snapshot->progression_level);
        self::assertSame('KSA', $snapshot->observed_alliance_tag);
        self::assertSame('manual', $snapshot->source);

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_user_id' => $owner->id,
            'event' => 'kingdoms.player_snapshot_recorded',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'kingdoms.player_snapshot_recorded',
        ]);

        $this->withSession($this->activeSession($alliance->id))
            ->get('/alliance/roster/'.$entry->id.'/history')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/RosterHistory')
                ->where('latest.power', '1234567890123')
                ->where('latest.progressionLevel', 'FC3')
                ->where('snapshots.0.actorName', $owner->name));
    }

    public function test_snapshot_mutation_requires_recent_password_confirmation(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Confirm Snapshot', 'confirm-snapshot', 3202);
        $entry = $this->createRosterEntry($owner, $alliance, 'Confirm Player');

        $this->withSession([
            ...$this->activeSession($alliance->id),
            'auth.password_confirmed_at' => 0,
        ])
            ->post('/alliance/roster/'.$entry->id.'/snapshots', $this->snapshotPayload('100'))
            ->assertRedirect(route('password.confirm'));

        $this->assertDatabaseCount('player_snapshots', 0);
    }

    public function test_member_can_view_snapshot_history_without_actor_identity_but_cannot_record(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Member Snapshot', 'member-snapshot', 3203);
        $this->addMember($alliance->id, $member);
        $entry = $this->createRosterEntry($owner, $alliance, 'Visible Snapshot');

        $this->withSession($this->confirmedSession($alliance->id))
            ->post('/alliance/roster/'.$entry->id.'/snapshots', $this->snapshotPayload('200'))
            ->assertRedirect();

        $this->actingAs($member)
            ->withSession($this->activeSession($alliance->id))
            ->get('/alliance/roster/'.$entry->id.'/history')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/RosterHistory')
                ->where('canManage', false)
                ->where('snapshots.0.power', '200')
                ->missing('snapshots.0.actorName'));

        $this->withSession($this->confirmedSession($alliance->id))
            ->post('/alliance/roster/'.$entry->id.'/snapshots', $this->snapshotPayload('201'))
            ->assertForbidden();

        $this->assertDatabaseCount('player_snapshots', 1);
    }

    public function test_exact_retry_is_idempotent_but_later_capture_preserves_new_history(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Idempotent Snapshot', 'idempotent-snapshot', 3204);
        $entry = $this->createRosterEntry($owner, $alliance, 'Retry Player');
        $capturedAt = now()->subHours(2)->startOfSecond();
        $payload = $this->snapshotPayload('300', $capturedAt->toIso8601String());
        $session = $this->confirmedSession($alliance->id);

        $this->withSession($session)
            ->post('/alliance/roster/'.$entry->id.'/snapshots', $payload)
            ->assertRedirect();
        $this->withSession($session)
            ->post('/alliance/roster/'.$entry->id.'/snapshots', $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('player_snapshots', 1);
        self::assertSame(1, (int) \DB::table('audit_events')
            ->where('event', 'kingdoms.player_snapshot_recorded')->count());
        self::assertSame(1, (int) \DB::table('outbox_messages')
            ->where('event_type', 'kingdoms.player_snapshot_recorded')->count());

        $laterPayload = $payload;
        $laterPayload['captured_at'] = $capturedAt->addMinute()->toIso8601String();
        $this->withSession($session)
            ->post('/alliance/roster/'.$entry->id.'/snapshots', $laterPayload)
            ->assertRedirect();

        $this->assertDatabaseCount('player_snapshots', 2);

        $this->withSession($session)
            ->patch('/alliance/roster/'.$entry->id, [
                'name' => 'Retry Player Renamed',
                'state' => 'active',
            ])
            ->assertRedirect();

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
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Power Range', 'power-range', 3205);
        $entry = $this->createRosterEntry($owner, $alliance, 'Power Player');
        $session = $this->confirmedSession($alliance->id);

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

    public function test_snapshot_history_and_mutation_fail_closed_for_another_alliance(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwner, 'Snapshot Tenant One', 'snapshot-tenant-one', 3206);
        $second = $createAlliance->handle($secondOwner, 'Snapshot Tenant Two', 'snapshot-tenant-two', 3206);
        $secondEntry = $this->createRosterEntry($secondOwner, $second, 'Second Tenant Player');

        $this->actingAs($secondOwner)
            ->withSession($this->confirmedSession($second->id))
            ->post('/alliance/roster/'.$secondEntry->id.'/snapshots', $this->snapshotPayload('400'))
            ->assertRedirect();

        $this->actingAs($firstOwner)
            ->withSession($this->activeSession($first->id))
            ->get('/alliance/roster/'.$secondEntry->id.'/history')
            ->assertNotFound();

        $this->withSession($this->confirmedSession($first->id))
            ->post('/alliance/roster/'.$secondEntry->id.'/snapshots', $this->snapshotPayload('401'))
            ->assertNotFound();

        $this->assertDatabaseCount('player_snapshots', 1);
    }

    public function test_latest_projection_uses_capture_time_and_freshness_distinguishes_current_stale_and_missing(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Freshness Alliance', 'freshness-alliance', 3207);
        $current = $this->createRosterEntry($owner, $alliance, 'Current Player');
        $missing = $this->createRosterEntry($owner, $alliance, 'Missing Player');
        $stale = $this->createRosterEntry($owner, $alliance, 'Stale Player');
        $session = $this->confirmedSession($alliance->id);

        $this->withSession($session)
            ->post('/alliance/roster/'.$current->id.'/snapshots', $this->snapshotPayload(
                '600',
                now()->subDay()->toIso8601String(),
            ))->assertRedirect();
        $this->withSession($session)
            ->post('/alliance/roster/'.$current->id.'/snapshots', $this->snapshotPayload(
                '500',
                now()->subDays(10)->toIso8601String(),
            ))->assertRedirect();
        $this->withSession($session)
            ->post('/alliance/roster/'.$stale->id.'/snapshots', $this->snapshotPayload(
                '700',
                now()->subDays(31)->toIso8601String(),
            ))->assertRedirect();

        $this->withSession($this->activeSession($alliance->id))
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

    private function createRosterEntry(User $owner, Alliance $alliance, string $name): AllianceRosterEntry
    {
        $this->actingAs($owner)
            ->withSession($this->confirmedSession($alliance->id))
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
