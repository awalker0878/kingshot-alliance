<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\TrackedKingdomAllianceState;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\KingdomAlliance;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KingdomAllianceTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_track_neutral_game_alliance_with_captured_kingdom_and_internal_evidence(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('K3 Alpha', 'k3-alpha', 6101);

        $this->actingAs($owner)
            ->withSession($session)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => 'Northern Guard',
                'current_tag' => 'NG',
                'game_alliance_id' => 'alliance-6101-ng',
                'manager_notes' => 'Private relationship context',
            ])
            ->assertRedirect();

        $reference = KingdomAlliance::query()->sole();
        $tracking = TrackedKingdomAlliance::query()->sole();

        self::assertSame($alliance->kingdom_id, $reference->kingdom_id);
        self::assertSame('alliance-6101-ng', $reference->game_alliance_id);
        self::assertSame('Northern Guard', $reference->current_name);
        self::assertSame('NG', $reference->current_tag);
        self::assertSame($alliance->id, $tracking->alliance_id);
        self::assertSame($reference->id, $tracking->kingdom_alliance_id);
        self::assertSame($alliance->kingdom_id, $tracking->kingdom_id);
        self::assertSame(TrackedKingdomAllianceState::Active, $tracking->state);
        self::assertSame('Private relationship context', $tracking->manager_notes);

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_player_id' => $ownerPlayer->id,
            'event' => 'kingdoms.alliance_intelligence_tracking_started',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'kingdoms.alliance_intelligence_tracking_started',
        ]);

        $this->get('/alliance/kingdom-alliances')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/KingdomAlliances')
                ->where('alliance.kingdom', '6101')
                ->where('canManage', true)
                ->where('tracking.0.name', 'Northern Guard')
                ->where('tracking.0.tag', 'NG')
                ->where('tracking.0.contextCurrent', true));
    }

    public function test_same_name_and_tag_without_stable_id_never_auto_merge(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('No Name Merge', 'no-name-merge', 6102);

        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => 'Same Name',
                'current_tag' => 'SAME',
            ])->assertRedirect();
        $this->withSession($session)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => 'Same Name',
                'current_tag' => 'SAME',
            ])->assertRedirect();

        self::assertSame(2, KingdomAlliance::query()->where('kingdom_id', $alliance->kingdom_id)->count());
        self::assertSame(2, TrackedKingdomAlliance::query()->where('alliance_id', $alliance->id)->count());
        self::assertSame(2, KingdomAlliance::query()->whereNull('game_alliance_id')->count());
    }

    public function test_stable_id_reuses_only_neutral_reference_while_tenant_tracking_stays_private(): void
    {
        [$ownerA, $ownerPlayerA, $allianceA, $sessionA] = $this->ownerAlliance('Shared Neutral A', 'shared-neutral-a', 6103);
        [$ownerB, $ownerPlayerB, $allianceB, $sessionB] = $this->ownerAlliance('Shared Neutral B', 'shared-neutral-b', 6103);

        $this->actingAs($ownerA)->withSession($sessionA)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => 'Shared Game Alliance',
                'current_tag' => 'SGA',
                'game_alliance_id' => 'stable-shared-6103',
                'manager_notes' => 'TENANT-A-PRIVATE',
            ])->assertRedirect();

        $this->actingAs($ownerB)->withSession($sessionB)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => 'Different observed label',
                'current_tag' => 'DIFF',
                'game_alliance_id' => 'stable-shared-6103',
                'manager_notes' => 'TENANT-B-PRIVATE',
            ])->assertRedirect();

        self::assertSame(1, KingdomAlliance::query()->where('game_alliance_id', 'stable-shared-6103')->count());
        self::assertSame(2, TrackedKingdomAlliance::query()->count());

        $trackingA = TrackedKingdomAlliance::query()->where('alliance_id', $allianceA->id)->sole();
        $trackingB = TrackedKingdomAlliance::query()->where('alliance_id', $allianceB->id)->sole();
        self::assertSame($trackingA->kingdom_alliance_id, $trackingB->kingdom_alliance_id);
        self::assertSame('TENANT-A-PRIVATE', $trackingA->manager_notes);
        self::assertSame('TENANT-B-PRIVATE', $trackingB->manager_notes);

        $this->get('/alliance/kingdom-alliances/manage')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tracking.0.managerNotes', 'TENANT-B-PRIVATE')
                ->where('tracking.0.gameAllianceId', 'stable-shared-6103'));
    }

    public function test_stable_id_can_be_assigned_once_but_conflicts_or_replacement_fail_closed(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Stable Assignment', 'stable-assignment', 6104);

        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/kingdom-alliances', ['current_name' => 'First unresolved'])
            ->assertRedirect();
        $this->withSession($session)
            ->post('/alliance/kingdom-alliances', ['current_name' => 'Second unresolved'])
            ->assertRedirect();

        $rows = TrackedKingdomAlliance::query()->orderBy('created_at')->get();
        $first = $rows[0];
        $second = $rows[1];

        $this->withSession($session)
            ->patch("/alliance/kingdom-alliances/{$first->id}", [
                'current_name' => 'First resolved',
                'game_alliance_id' => 'stable-once-6104',
            ])->assertRedirect();
        self::assertSame('stable-once-6104', $first->refresh()->kingdomAlliance->game_alliance_id);

        $this->withSession($session)
            ->from('/alliance/kingdom-alliances/manage')
            ->patch("/alliance/kingdom-alliances/{$second->id}", [
                'current_name' => 'Second unresolved',
                'game_alliance_id' => 'stable-once-6104',
            ])
            ->assertRedirect('/alliance/kingdom-alliances/manage')
            ->assertSessionHasErrors('game_alliance_id');

        $this->withSession($session)
            ->from('/alliance/kingdom-alliances/manage')
            ->patch("/alliance/kingdom-alliances/{$first->id}", [
                'current_name' => 'First resolved',
                'game_alliance_id' => 'replacement-6104',
            ])
            ->assertRedirect('/alliance/kingdom-alliances/manage')
            ->assertSessionHasErrors('game_alliance_id');

        self::assertSame('stable-once-6104', $first->refresh()->kingdomAlliance->game_alliance_id);
        self::assertNull($second->refresh()->kingdomAlliance->game_alliance_id);
    }

    public function test_member_sees_safe_tracking_only_and_cannot_manage_or_mutate(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Tracking Privacy', 'tracking-privacy', 6105);
        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => 'Privacy Alliance',
                'current_tag' => 'PA',
                'game_alliance_id' => 'stable-private-6105',
                'manager_notes' => 'MANAGER-ONLY-6105',
            ])->assertRedirect();

        $tracking = TrackedKingdomAlliance::query()->sole();
        $member = $this->member($alliance);

        $this->actingAs($member[0])->withSession($this->activeSession($member[1]->id))
            ->get('/alliance/kingdom-alliances')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/KingdomAlliances')
                ->where('canManage', false)
                ->where('tracking.0.name', 'Privacy Alliance')
                ->where('tracking.0.tag', 'PA')
                ->missing('tracking.0.id')
                ->missing('tracking.0.kingdomAllianceId')
                ->missing('tracking.0.gameAllianceId')
                ->missing('tracking.0.managerNotes'));

        $this->get('/alliance/kingdom-alliances/manage')->assertForbidden();
        $this->withSession($this->confirmedSession($member[1]->id))
            ->patch("/alliance/kingdom-alliances/{$tracking->id}", ['current_name' => 'Forbidden'])
            ->assertForbidden();
    }

    public function test_cross_tenant_tracking_ids_are_re_resolved_under_active_alliance(): void
    {
        [$ownerA, $ownerPlayerA, $allianceA, $sessionA] = $this->ownerAlliance('Tracking Tenant A', 'tracking-tenant-a', 6106);
        [$ownerB, $ownerPlayerB, $allianceB, $sessionB] = $this->ownerAlliance('Tracking Tenant B', 'tracking-tenant-b', 6107);

        $this->actingAs($ownerB)->withSession($sessionB)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => 'Tenant B Target',
                'game_alliance_id' => 'tenant-b-6107',
            ])->assertRedirect();
        $trackingB = TrackedKingdomAlliance::query()->where('alliance_id', $allianceB->id)->sole();

        $this->actingAs($ownerA)->withSession($sessionA)
            ->patch("/alliance/kingdom-alliances/{$trackingB->id}", [
                'current_name' => 'Hijacked',
                'game_alliance_id' => 'tenant-b-6107',
            ])->assertNotFound();
        $this->withSession($sessionA)
            ->post("/alliance/kingdom-alliances/{$trackingB->id}/archive")
            ->assertNotFound();

        self::assertSame(TrackedKingdomAllianceState::Active, $trackingB->refresh()->state);
        self::assertSame('Tenant B Target', $trackingB->kingdomAlliance->refresh()->current_name);
        self::assertSame(0, TrackedKingdomAlliance::query()->where('alliance_id', $allianceA->id)->count());
    }

    public function test_alliance_kingdom_is_immutable_after_tracking_is_created(): void
    {
        [$owner, , $alliance, $session] = $this->ownerAlliance('Tracking Immutable', 'tracking-immutable', 6108);
        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => 'Current Kingdom Alliance',
                'game_alliance_id' => 'current-kingdom-6108',
            ])->assertRedirect();
        $tracking = TrackedKingdomAlliance::query()->sole();
        $newKingdom = Kingdom::query()->create(['number' => 6199, 'status' => 'active']);

        try {
            $alliance->forceFill(['kingdom_id' => $newKingdom->id])->save();
            self::fail('Alliance Kingdom must be immutable after creation.');
        } catch (QueryException) {
            // Database invariant is the final authority for out-of-band writes.
        }

        self::assertSame('6108', (string) $alliance->fresh()->kingdom->number);
        self::assertSame($alliance->kingdom_id, $tracking->refresh()->kingdom_id);
        self::assertSame(TrackedKingdomAllianceState::Active, $tracking->state);
    }

    public function test_tracking_mutations_require_recent_password_confirmation(): void
    {
        [$owner, $ownerPlayer, $alliance] = $this->ownerAlliance('Tracking Password', 'tracking-password', 6109);
        $this->actingAs($owner)
            ->withSession($this->activeSession($ownerPlayer->id))
            ->post('/alliance/kingdom-alliances', ['current_name' => 'Protected tracking'])
            ->assertRedirect(route('password.confirm'));

        self::assertSame(0, TrackedKingdomAlliance::query()->count());
    }

    public function test_archive_is_idempotent_and_reference_can_be_retracked_without_losing_history(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Tracking Archive', 'tracking-archive', 6110);
        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => 'Archive Target',
                'game_alliance_id' => 'archive-target-6110',
            ])->assertRedirect();
        $first = TrackedKingdomAlliance::query()->sole();

        $this->withSession($session)
            ->post("/alliance/kingdom-alliances/{$first->id}/archive")
            ->assertRedirect();
        $auditCount = $this->eventCount(
            'audit_events',
            'event',
            'kingdoms.alliance_intelligence_tracking_archived',
            $alliance->id,
        );

        $this->withSession($session)
            ->post("/alliance/kingdom-alliances/{$first->id}/archive")
            ->assertRedirect();
        self::assertSame($auditCount, $this->eventCount(
            'audit_events',
            'event',
            'kingdoms.alliance_intelligence_tracking_archived',
            $alliance->id,
        ));

        $this->withSession($session)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => 'Archive Target',
                'game_alliance_id' => 'archive-target-6110',
            ])->assertRedirect();

        self::assertSame(1, KingdomAlliance::query()->count());
        self::assertSame(2, TrackedKingdomAlliance::query()->count());
        self::assertSame(1, TrackedKingdomAlliance::query()->where('state', 'active')->count());
        self::assertSame(1, TrackedKingdomAlliance::query()->where('state', 'archived')->count());
    }

    public function test_private_tracking_notes_never_enter_audit_or_outbox_payloads(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Tracking Event Privacy', 'tracking-event-privacy', 6111);
        $secret = 'PRIVATE-TRACKING-NOTES-6111';

        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/kingdom-alliances', [
                'current_name' => 'Event Privacy Alliance',
                'game_alliance_id' => 'event-privacy-6111',
                'manager_notes' => $secret,
            ])->assertRedirect();
        $tracking = TrackedKingdomAlliance::query()->sole();

        $this->withSession($session)
            ->patch("/alliance/kingdom-alliances/{$tracking->id}", [
                'current_name' => 'Event Privacy Alliance Updated',
                'game_alliance_id' => 'event-privacy-6111',
                'manager_notes' => $secret.' updated',
            ])->assertRedirect();

        $events = [
            'kingdoms.alliance_intelligence_tracking_started',
            'kingdoms.alliance_intelligence_tracking_updated',
        ];
        $auditPayload = DB::table('audit_events')->whereIn('event', $events)->pluck('metadata')->implode(' ');
        $outboxPayload = DB::table('outbox_messages')->whereIn('event_type', $events)->pluck('payload')->implode(' ');

        self::assertStringNotContainsString($secret, $auditPayload);
        self::assertStringNotContainsString($secret, $outboxPayload);
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

    /** @return array{0: User, 1: Player} */
    private function member(Alliance $alliance): array
    {
        $memberUser = User::factory()->create();
        $kingdom = Kingdom::query()->findOrFail($alliance->kingdom_id);
        $memberPlayer = $this->player(
            $memberUser,
            $kingdom,
            'tracking-member-'.$memberUser->id,
            'Tracking Member',
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
        return [(string) config('identity.active_player_session_key') => $playerId];
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
