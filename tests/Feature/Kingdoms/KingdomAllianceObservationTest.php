<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\KingdomAllianceObservation;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KingdomAllianceObservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_observations_append_and_latest_projection_uses_capture_time_with_missing_distinct_from_zero(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Observation Alpha', 'observation-alpha', 6201);
        $tracking = $this->track($owner, $alliance, $session, 'Northern Watch', 'northern-watch-6201');

        $this->withSession($session)->post("/alliance/kingdom-alliances/{$tracking->id}/observations", [
            'observed_name' => 'Northern Watch Current',
            'observed_tag' => 'NW',
            'power' => '0',
            'member_count' => 0,
            'captured_at' => now()->subDays(2)->toIso8601String(),
        ])->assertRedirect();

        $this->withSession($session)->post("/alliance/kingdom-alliances/{$tracking->id}/observations", [
            'observed_name' => 'Northern Watch Older',
            'observed_tag' => 'OLD',
            'power' => '9000',
            'captured_at' => now()->subDays(10)->toIso8601String(),
        ])->assertRedirect();

        self::assertSame(2, KingdomAllianceObservation::query()->count());
        self::assertSame('Northern Watch Current', $tracking->kingdomAlliance->refresh()->current_name);
        self::assertSame('NW', $tracking->kingdomAlliance->current_tag);

        $this->get("/alliance/kingdom-alliances/{$tracking->id}/history")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/KingdomAllianceHistory')
                ->where('freshness', 'current')
                ->where('latest.power', '0')
                ->where('latest.memberCount', 0)
                ->where('history.0.power', '0')
                ->where('history.1.power', '9000'));

        $this->withSession($session)->post("/alliance/kingdom-alliances/{$tracking->id}/observations", [
            'observed_name' => 'Northern Watch Missing Metrics',
            'captured_at' => now()->toIso8601String(),
        ])->assertRedirect();

        $this->get("/alliance/kingdom-alliances/{$tracking->id}/history")
            ->assertInertia(fn (Assert $page) => $page
                ->where('latest.power', null)
                ->where('latest.memberCount', null));
    }

    public function test_exact_retry_is_idempotent_and_emits_one_durable_event(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Observation Retry', 'observation-retry', 6202);
        $tracking = $this->track($owner, $alliance, $session, 'Retry Alliance', 'retry-alliance-6202');
        $payload = [
            'observed_name' => 'Retry Alliance',
            'observed_tag' => 'RTY',
            'power' => '123456789',
            'member_count' => 99,
            'captured_at' => now()->subMinute()->toIso8601String(),
        ];

        $this->withSession($session)->post("/alliance/kingdom-alliances/{$tracking->id}/observations", $payload)->assertRedirect();
        $this->withSession($session)->post("/alliance/kingdom-alliances/{$tracking->id}/observations", $payload)->assertRedirect();

        self::assertSame(1, KingdomAllianceObservation::query()->count());
        self::assertSame(1, $this->eventCount('audit_events', 'event', 'kingdoms.alliance_intelligence_observation_recorded', $alliance->id));
        self::assertSame(1, $this->eventCount('outbox_messages', 'event_type', 'kingdoms.alliance_intelligence_observation_recorded', $alliance->id));
    }

    public function test_correction_appends_replacement_invalidates_original_and_keeps_private_reason_out_of_events(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Observation Correction', 'observation-correction', 6203);
        $tracking = $this->track($owner, $alliance, $session, 'Correction Alliance', 'correction-6203');

        $this->withSession($session)->post("/alliance/kingdom-alliances/{$tracking->id}/observations", [
            'observed_name' => 'Correction Alliance',
            'power' => '100',
            'member_count' => 10,
            'captured_at' => now()->subHour()->toIso8601String(),
        ])->assertRedirect();
        $original = KingdomAllianceObservation::query()->sole();
        $secret = 'PRIVATE-CORRECTION-REASON-6203';

        $this->withSession($session)->post("/alliance/kingdom-alliances/{$tracking->id}/observations", [
            'observed_name' => 'Correction Alliance',
            'power' => '125',
            'member_count' => 12,
            'captured_at' => now()->subMinutes(30)->toIso8601String(),
            'corrects_observation_id' => $original->id,
            'correction_reason' => $secret,
        ])->assertRedirect();

        $original->refresh();
        $replacement = KingdomAllianceObservation::query()->where('id', '!=', $original->id)->sole();
        self::assertNotNull($original->invalidated_at);
        self::assertSame($ownerPlayer->id, $original->invalidated_by_player_id);
        self::assertSame($secret, $original->invalidation_reason);
        self::assertSame($original->id, $replacement->corrects_observation_id);

        $this->get("/alliance/kingdom-alliances/{$tracking->id}/history")
            ->assertInertia(fn (Assert $page) => $page
                ->where('latest.power', '125')
                ->where('history.0.power', '125')
                ->where('history.1.invalidatedAt', fn ($value) => is_string($value))
                ->where('history.1.invalidationReason', $secret));

        $events = [
            'kingdoms.alliance_intelligence_observation_recorded',
            'kingdoms.alliance_intelligence_observation_corrected',
        ];
        $auditPayload = DB::table('audit_events')->whereIn('event', $events)->pluck('metadata')->implode(' ');
        $outboxPayload = DB::table('outbox_messages')->whereIn('event_type', $events)->pluck('payload')->implode(' ');
        self::assertStringNotContainsString($secret, $auditPayload);
        self::assertStringNotContainsString($secret, $outboxPayload);
    }

    public function test_standalone_invalidation_is_idempotent_and_removes_row_from_member_projection(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Observation Invalid', 'observation-invalid', 6204);
        $tracking = $this->track($owner, $alliance, $session, 'Invalidation Alliance', 'invalid-6204');

        foreach ([40, 20] as $hours) {
            $this->withSession($session)->post("/alliance/kingdom-alliances/{$tracking->id}/observations", [
                'observed_name' => 'Invalidation Alliance',
                'power' => (string) (1000 + $hours),
                'captured_at' => now()->subHours($hours)->toIso8601String(),
            ])->assertRedirect();
        }
        $latest = KingdomAllianceObservation::query()->orderByDesc('captured_at')->firstOrFail();

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/observations/{$latest->id}/invalidate",
            ['reason' => 'Incorrect screenshot transcription'],
        )->assertRedirect();
        $auditCount = $this->eventCount(
            'audit_events',
            'event',
            'kingdoms.alliance_intelligence_observation_invalidated',
            $alliance->id,
        );

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/observations/{$latest->id}/invalidate",
            ['reason' => 'Repeated request'],
        )->assertRedirect();
        self::assertSame(
            $auditCount,
            $this->eventCount(
                'audit_events',
                'event',
                'kingdoms.alliance_intelligence_observation_invalidated',
                $alliance->id,
            ),
        );

        $member = $this->member($alliance);
        $this->actingAs($member[0])->withSession($this->activeSession($member[1]->id))
            ->get("/alliance/kingdom-alliances/{$tracking->id}/history")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', false)
                ->has('history', 1)
                ->missing('history.0.id')
                ->missing('history.0.actorName')
                ->missing('history.0.invalidatedAt')
                ->missing('history.0.invalidationReason'));
    }

    public function test_cross_tenant_tracking_and_observation_ids_fail_closed(): void
    {
        [$ownerA, $ownerPlayerA, $allianceA, $sessionA] = $this->ownerAlliance('Observation Tenant A', 'observation-tenant-a', 6205);
        [$ownerB, $ownerPlayerB, $allianceB, $sessionB] = $this->ownerAlliance('Observation Tenant B', 'observation-tenant-b', 6205);
        $trackingB = $this->track($ownerB, $allianceB, $sessionB, 'Tenant B Observed', 'tenant-b-observed-6205');

        $this->withSession($sessionB)->post("/alliance/kingdom-alliances/{$trackingB->id}/observations", [
            'observed_name' => 'Tenant B Observed',
            'power' => '800',
            'captured_at' => now()->toIso8601String(),
        ])->assertRedirect();
        $observationB = KingdomAllianceObservation::query()->where('alliance_id', $allianceB->id)->sole();

        $this->actingAs($ownerA)->withSession($sessionA)
            ->get("/alliance/kingdom-alliances/{$trackingB->id}/history")
            ->assertNotFound();
        $this->withSession($sessionA)
            ->post("/alliance/kingdom-alliances/{$trackingB->id}/observations", [
                'observed_name' => 'Hijack',
                'captured_at' => now()->toIso8601String(),
            ])->assertNotFound();
        $this->withSession($sessionA)
            ->post(
                "/alliance/kingdom-alliances/{$trackingB->id}/observations/{$observationB->id}/invalidate",
                ['reason' => 'Hijack'],
            )->assertNotFound();

        self::assertNull($observationB->refresh()->invalidated_at);
        self::assertSame(0, KingdomAllianceObservation::query()->where('alliance_id', $allianceA->id)->count());
    }

    public function test_kingdom_drift_preserves_history_read_but_blocks_observation_mutations(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Observation Drift', 'observation-drift', 6206);
        $tracking = $this->track($owner, $alliance, $session, 'Drift Observed', 'drift-observed-6206');
        $this->withSession($session)->post("/alliance/kingdom-alliances/{$tracking->id}/observations", [
            'observed_name' => 'Drift Observed',
            'power' => '555',
            'captured_at' => now()->toIso8601String(),
        ])->assertRedirect();
        $observation = KingdomAllianceObservation::query()->sole();

        $newKingdom = Kingdom::query()->create(['number' => 6299, 'status' => 'active']);
        $tracking->forceFill(['kingdom_id' => $newKingdom->id])->save();

        $this->get("/alliance/kingdom-alliances/{$tracking->id}/history")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tracking.contextCurrent', false)
                ->where('latest.power', '555'));

        $this->withSession($session)->from("/alliance/kingdom-alliances/{$tracking->id}/history")
            ->post("/alliance/kingdom-alliances/{$tracking->id}/observations", [
                'observed_name' => 'Blocked drift',
                'captured_at' => now()->toIso8601String(),
            ])->assertSessionHasErrors('observation');
        $this->withSession($session)->from("/alliance/kingdom-alliances/{$tracking->id}/history")
            ->post(
                "/alliance/kingdom-alliances/{$tracking->id}/observations/{$observation->id}/invalidate",
                ['reason' => 'Blocked drift'],
            )->assertSessionHasErrors('observation');

        self::assertSame(1, KingdomAllianceObservation::query()->count());
        self::assertNull($observation->refresh()->invalidated_at);
    }

    public function test_observation_mutations_require_recent_password_confirmation(): void
    {
        [$owner, $ownerPlayer, $alliance] = $this->ownerAlliance('Observation Password', 'observation-password', 6207);
        $session = $this->confirmedSession($ownerPlayer->id);
        $tracking = $this->track($owner, $alliance, $session, 'Protected Observation', 'protected-observation-6207');

        $this->actingAs($owner)->withSession([
            ...$this->activeSession($ownerPlayer->id),
            'auth.password_confirmed_at' => 0,
        ])->post("/alliance/kingdom-alliances/{$tracking->id}/observations", [
            'observed_name' => 'Protected Observation',
            'captured_at' => now()->toIso8601String(),
        ])->assertRedirect(route('password.confirm'));

        self::assertSame(0, KingdomAllianceObservation::query()->count());
    }

    public function test_power_bounds_and_future_capture_are_rejected(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Observation Bounds', 'observation-bounds', 6208);
        $tracking = $this->track($owner, $alliance, $session, 'Bounded Observation', 'bounded-observation-6208');

        $this->withSession($session)->from("/alliance/kingdom-alliances/{$tracking->id}/history")
            ->post("/alliance/kingdom-alliances/{$tracking->id}/observations", [
                'observed_name' => 'Bounded Observation',
                'power' => '9223372036854775808',
                'captured_at' => now()->toIso8601String(),
            ])->assertSessionHasErrors('power');

        $this->withSession($session)->from("/alliance/kingdom-alliances/{$tracking->id}/history")
            ->post("/alliance/kingdom-alliances/{$tracking->id}/observations", [
                'observed_name' => 'Bounded Observation',
                'captured_at' => now()->addMinutes(10)->toIso8601String(),
            ])->assertSessionHasErrors('captured_at');

        self::assertSame(0, KingdomAllianceObservation::query()->count());
    }

    /** @return array{0: User, 1: Player, 2: Alliance, 3: array<string, mixed>} */
    private function ownerAlliance(string $name, string $slug, int $kingdomNumber): array
    {
        $ownerUser = User::factory()->create();
        $kingdom = Kingdom::query()->firstOrCreate(
            ['number' => $kingdomNumber],
            ['status' => 'active'],
        );
        $ownerPlayer = $this->player($ownerUser, $kingdom, $slug.'-r5', $name.' R5');
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, $name, $slug);

        return [$ownerUser, $ownerPlayer, $alliance, $this->confirmedSession($ownerPlayer->id)];
    }

    private function player(User $user, Kingdom $kingdom, string $gamePlayerId, string $name): Player
    {
        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }

    /** @param array<string, mixed> $session */
    private function track(
        User $owner,
        Alliance $alliance,
        array $session,
        string $name,
        string $stableId,
    ): TrackedKingdomAlliance {
        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => $name,
                'game_alliance_id' => $stableId,
            ])->assertRedirect();

        return TrackedKingdomAlliance::query()
            ->where('alliance_id', $alliance->id)
            ->latest('created_at')
            ->firstOrFail();
    }

    /** @return array{0: User, 1: Player} */
    private function member(Alliance $alliance): array
    {
        $memberUser = User::factory()->create();
        $kingdom = Kingdom::query()->findOrFail($alliance->kingdom_id);
        $memberPlayer = $this->player(
            $memberUser,
            $kingdom,
            'observation-member-'.$memberUser->id,
            'Observation Member',
        );
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        return [$memberUser, $memberPlayer];
    }

    /** @return array<string, mixed> */
    private function activeSession(string $playerId): array
    {
        return [(string) config('game_world.active_player_session_key') => $playerId];
    }

    /** @return array<string, mixed> */
    private function confirmedSession(string $playerId): array
    {
        return [
            ...$this->activeSession($playerId),
            'auth.password_confirmed_at' => time(),
        ];
    }

    private function eventCount(string $table, string $eventColumn, string $event, string $allianceId): int
    {
        return DB::table($table)
            ->where('alliance_id', $allianceId)
            ->where($eventColumn, $event)
            ->count();
    }
}
