<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\KingdomAllianceDiplomacy;
use App\Contexts\GameWorld\Models\KingdomAllianceDiplomacyTransition;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Workflows\KingdomTransfer\Enums\KingdomAllianceDiplomacyState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KingdomAllianceDiplomacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_explicitly_transition_across_locked_states_with_append_history(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Diplomacy States', 'diplomacy-states', 6301);
        $tracking = $this->track($owner, $alliance, $session, 'State Target', 'state-target-6301');
        $states = [
            KingdomAllianceDiplomacyState::Nap,
            KingdomAllianceDiplomacyState::Rival,
            KingdomAllianceDiplomacyState::Ally,
            KingdomAllianceDiplomacyState::Neutral,
            KingdomAllianceDiplomacyState::Friendly,
            KingdomAllianceDiplomacyState::Unknown,
        ];

        foreach ($states as $offset => $state) {
            $this->withSession($session)->post(
                "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
                [
                    'state' => $state->value,
                    'effective_at' => now()->addMinutes($offset)->toIso8601String(),
                    'terms' => 'Terms '.$offset,
                    'rationale' => 'Rationale '.$offset,
                ],
            )->assertRedirect();
        }

        $relationship = KingdomAllianceDiplomacy::query()->sole();
        self::assertSame(KingdomAllianceDiplomacyState::Unknown, $relationship->current_state);
        self::assertSame(6, KingdomAllianceDiplomacyTransition::query()->count());
        self::assertSame(
            [
                'unknown:nap',
                'nap:rival',
                'rival:ally',
                'ally:neutral',
                'neutral:friendly',
                'friendly:unknown',
            ],
            KingdomAllianceDiplomacyTransition::query()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (KingdomAllianceDiplomacyTransition $transition): string => $transition->from_state->value.':'.$transition->to_state->value)
                ->all(),
        );

        $this->get("/alliance/kingdom-alliances/{$tracking->id}/diplomacy")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/KingdomAllianceDiplomacy')
                ->where('current.state', 'unknown')
                ->has('states', 6)
                ->has('history', 6)
                ->where('history.0.actorName', $owner->name));
    }

    public function test_exact_current_meaning_is_idempotent_but_same_state_metadata_change_appends_history(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Diplomacy Retry', 'diplomacy-retry', 6302);
        $tracking = $this->track($owner, $alliance, $session, 'Retry Target', 'retry-target-6302');
        $effective = now()->startOfSecond();
        $payload = [
            'state' => 'nap',
            'effective_at' => $effective->toIso8601String(),
            'review_at' => $effective->copy()->addDays(5)->toIso8601String(),
            'expires_at' => $effective->copy()->addDays(10)->toIso8601String(),
            'terms' => 'NAP terms',
            'rationale' => 'Mutual agreement',
        ];

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
            $payload,
        )->assertRedirect();
        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
            $payload,
        )->assertRedirect();

        self::assertSame(1, KingdomAllianceDiplomacyTransition::query()->count());
        self::assertSame(
            1,
            $this->eventCount('audit_events', 'event', 'kingdoms.diplomacy_transitioned', $alliance->id),
        );

        $payload['terms'] = 'NAP terms revised by both sides';
        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
            $payload,
        )->assertRedirect();

        self::assertSame(2, KingdomAllianceDiplomacyTransition::query()->count());
        $latest = KingdomAllianceDiplomacyTransition::query()
            ->where('terms', 'NAP terms revised by both sides')
            ->sole();
        self::assertSame(KingdomAllianceDiplomacyState::Nap, $latest->from_state);
        self::assertSame(KingdomAllianceDiplomacyState::Nap, $latest->to_state);
        self::assertSame('NAP terms revised by both sides', $latest->terms);
    }

    public function test_review_and_expiry_create_review_indicator_without_automatic_state_change(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Diplomacy Review', 'diplomacy-review', 6303);
        $tracking = $this->track($owner, $alliance, $session, 'Review Target', 'review-target-6303');

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
            [
                'state' => 'friendly',
                'effective_at' => now()->subDays(10)->toIso8601String(),
                'review_at' => now()->subDay()->toIso8601String(),
                'expires_at' => now()->subHour()->toIso8601String(),
            ],
        )->assertRedirect();

        self::assertSame(KingdomAllianceDiplomacyState::Friendly, KingdomAllianceDiplomacy::query()->sole()->current_state);
        self::assertSame(1, KingdomAllianceDiplomacyTransition::query()->count());

        $this->get('/alliance/kingdom-alliances')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tracking.0.diplomacyState', 'friendly')
                ->where('tracking.0.diplomacyNeedsReview', true));

        self::assertSame(KingdomAllianceDiplomacyState::Friendly, KingdomAllianceDiplomacy::query()->sole()->current_state);
        self::assertSame(1, KingdomAllianceDiplomacyTransition::query()->count());
    }

    public function test_observations_never_infer_or_transition_diplomacy(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Diplomacy Observation', 'diplomacy-observation', 6304);
        $tracking = $this->track($owner, $alliance, $session, 'Observed Target', 'observed-target-6304');

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
            [
                'state' => 'ally',
                'effective_at' => now()->subHour()->toIso8601String(),
            ],
        )->assertRedirect();

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/observations",
            [
                'observed_name' => 'Observed Target',
                'power' => '999999999',
                'member_count' => 120,
                'captured_at' => now()->toIso8601String(),
            ],
        )->assertRedirect();

        self::assertSame(1, KingdomAllianceObservation::query()->count());
        self::assertSame(1, KingdomAllianceDiplomacyTransition::query()->count());
        self::assertSame(KingdomAllianceDiplomacyState::Ally, KingdomAllianceDiplomacy::query()->sole()->current_state);
    }

    public function test_member_receives_safe_state_only_and_cannot_open_private_diplomacy_workspace(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Diplomacy Privacy', 'diplomacy-privacy', 6305);
        $tracking = $this->track($owner, $alliance, $session, 'Private Target', 'private-target-6305');
        $secretTerms = 'PRIVATE-TERMS-6305';
        $secretRationale = 'PRIVATE-RATIONALE-6305';

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
            [
                'state' => 'nap',
                'effective_at' => now()->toIso8601String(),
                'terms' => $secretTerms,
                'rationale' => $secretRationale,
            ],
        )->assertRedirect();

        $member = $this->member($alliance);
        $memberSession = $this->activePlayerSession(Player::query()->where('user_id', $member->id)->sole());
        $this->actingAs($member)->withSession($memberSession)
            ->get('/alliance/kingdom-alliances')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', false)
                ->where('tracking.0.diplomacyState', 'nap')
                ->where('tracking.0.diplomacyNeedsReview', false)
                ->missing('tracking.0.diplomacyUrl')
                ->missing('tracking.0.terms')
                ->missing('tracking.0.rationale')
                ->missing('tracking.0.lastActorName'));

        $this->actingAs($member)->withSession($memberSession)
            ->get("/alliance/kingdom-alliances/{$tracking->id}/diplomacy")
            ->assertForbidden();
    }

    public function test_private_terms_and_rationale_never_enter_audit_or_outbox_payloads(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Diplomacy Events', 'diplomacy-events', 6306);
        $tracking = $this->track($owner, $alliance, $session, 'Event Target', 'event-target-6306');
        $secretTerms = 'PRIVATE-TERMS-6306';
        $secretRationale = 'PRIVATE-RATIONALE-6306';

        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
            [
                'state' => 'rival',
                'effective_at' => now()->toIso8601String(),
                'review_at' => now()->addWeek()->toIso8601String(),
                'terms' => $secretTerms,
                'rationale' => $secretRationale,
            ],
        )->assertRedirect();

        $auditPayload = DB::table('audit_events')
            ->where('event', 'kingdoms.diplomacy_transitioned')
            ->pluck('metadata')
            ->implode(' ');
        $outboxPayload = DB::table('outbox_messages')
            ->where('event_type', 'kingdoms.diplomacy_transitioned')
            ->pluck('payload')
            ->implode(' ');

        self::assertStringNotContainsString($secretTerms, $auditPayload);
        self::assertStringNotContainsString($secretRationale, $auditPayload);
        self::assertStringNotContainsString($secretTerms, $outboxPayload);
        self::assertStringNotContainsString($secretRationale, $outboxPayload);
    }

    public function test_cross_tenant_tracking_id_fails_closed(): void
    {
        [$ownerA, $allianceA, $sessionA] = $this->ownerAlliance('Diplomacy Tenant A', 'diplomacy-tenant-a', 6307);
        [$ownerB, $allianceB, $sessionB] = $this->ownerAlliance('Diplomacy Tenant B', 'diplomacy-tenant-b', 6307);
        $trackingB = $this->track($ownerB, $allianceB, $sessionB, 'Tenant B Target', 'tenant-b-target-6307');

        $this->actingAs($ownerA)->withSession($sessionA)
            ->get("/alliance/kingdom-alliances/{$trackingB->id}/diplomacy")
            ->assertNotFound();
        $this->actingAs($ownerA)->withSession($sessionA)
            ->post(
                "/alliance/kingdom-alliances/{$trackingB->id}/diplomacy/transitions",
                [
                    'state' => 'nap',
                    'effective_at' => now()->toIso8601String(),
                ],
            )->assertNotFound();

        self::assertSame(0, KingdomAllianceDiplomacy::query()->where('alliance_id', $allianceA->id)->count());
        self::assertSame(0, KingdomAllianceDiplomacyTransition::query()->where('alliance_id', $allianceA->id)->count());
    }

    public function test_kingdom_drift_preserves_diplomacy_history_read_but_blocks_mutation(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Diplomacy Drift', 'diplomacy-drift', 6308);
        $tracking = $this->track($owner, $alliance, $session, 'Drift Target', 'drift-target-6308');
        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
            [
                'state' => 'friendly',
                'effective_at' => now()->toIso8601String(),
            ],
        )->assertRedirect();

        $newKingdom = Kingdom::query()->create(['number' => 6399, 'status' => 'active']);
        $tracking->forceFill(['kingdom_id' => $newKingdom->id])->save();

        $this->get("/alliance/kingdom-alliances/{$tracking->id}/diplomacy")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tracking.contextCurrent', false)
                ->where('current.state', 'friendly')
                ->has('history', 1));

        $this->withSession($session)
            ->from("/alliance/kingdom-alliances/{$tracking->id}/diplomacy")
            ->post(
                "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
                [
                    'state' => 'rival',
                    'effective_at' => now()->toIso8601String(),
                ],
            )->assertSessionHasErrors('diplomacy');

        self::assertSame(KingdomAllianceDiplomacyState::Friendly, KingdomAllianceDiplomacy::query()->sole()->current_state);
        self::assertSame(1, KingdomAllianceDiplomacyTransition::query()->count());
    }

    public function test_archiving_tracking_retains_history_and_blocks_further_diplomacy_change(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Diplomacy Archive', 'diplomacy-archive', 6309);
        $tracking = $this->track($owner, $alliance, $session, 'Archive Target', 'archive-target-6309');
        $this->withSession($session)->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
            [
                'state' => 'neutral',
                'effective_at' => now()->toIso8601String(),
            ],
        )->assertRedirect();
        $this->withSession($session)->post("/alliance/kingdom-alliances/{$tracking->id}/archive")->assertRedirect();

        $this->get("/alliance/kingdom-alliances/{$tracking->id}/diplomacy")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tracking.state', 'archived')
                ->where('current.state', 'neutral')
                ->has('history', 1));

        $this->withSession($session)
            ->from("/alliance/kingdom-alliances/{$tracking->id}/diplomacy")
            ->post(
                "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
                [
                    'state' => 'ally',
                    'effective_at' => now()->toIso8601String(),
                ],
            )->assertSessionHasErrors('diplomacy');

        self::assertSame(1, KingdomAllianceDiplomacyTransition::query()->count());
    }

    public function test_diplomacy_mutations_require_recent_password_confirmation(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('Diplomacy Password', 'diplomacy-password', 6310);
        $player = Player::query()->where('user_id', $owner->id)->sole();
        $session = $this->confirmedSession($player);
        $tracking = $this->track($owner, $alliance, $session, 'Password Target', 'password-target-6310');

        $this->actingAs($owner)->withSession([
            (string) config('game_world.active_player_session_key') => $player->id,
            'auth.password_confirmed_at' => 0,
        ])->post(
            "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
            [
                'state' => 'nap',
                'effective_at' => now()->toIso8601String(),
            ],
        )->assertRedirect(route('password.confirm'));

        self::assertSame(0, KingdomAllianceDiplomacy::query()->count());
        self::assertSame(0, KingdomAllianceDiplomacyTransition::query()->count());
    }

    public function test_review_and_expiry_dates_must_follow_effective_time(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Diplomacy Dates', 'diplomacy-dates', 6311);
        $tracking = $this->track($owner, $alliance, $session, 'Date Target', 'date-target-6311');
        $effective = now()->addDay();

        $this->withSession($session)
            ->from("/alliance/kingdom-alliances/{$tracking->id}/diplomacy")
            ->post(
                "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
                [
                    'state' => 'nap',
                    'effective_at' => $effective->toIso8601String(),
                    'review_at' => now()->toIso8601String(),
                ],
            )->assertSessionHasErrors('review_at');

        $this->withSession($session)
            ->from("/alliance/kingdom-alliances/{$tracking->id}/diplomacy")
            ->post(
                "/alliance/kingdom-alliances/{$tracking->id}/diplomacy/transitions",
                [
                    'state' => 'nap',
                    'effective_at' => $effective->toIso8601String(),
                    'expires_at' => now()->toIso8601String(),
                ],
            )->assertSessionHasErrors('expires_at');

        self::assertSame(0, KingdomAllianceDiplomacy::query()->count());
    }

    /** @return array{0: User, 1: Alliance, 2: array<string, mixed>} */
    private function ownerAlliance(string $name, string $slug, int $kingdom): array
    {
        $owner = User::factory()->create();
        $kingdomModel = Kingdom::query()->firstOrCreate(
            ['number' => $kingdom],
            ['status' => 'active'],
        );
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdomModel->id,
            'game_player_id' => 'owner-'.$slug,
            'current_name' => $name.' Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, $name, $slug);

        return [$owner, $alliance, $this->confirmedSession($player)];
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

    private function member(Alliance $alliance): User
    {
        $member = User::factory()->create();
        $player = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $alliance->kingdom_id,
            'game_player_id' => 'member-'.$member->id,
            'current_name' => 'Diplomacy Member',
        ]);
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $player->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        return $member;
    }

    /** @return array<string, mixed> */
    private function confirmedSession(Player $player): array
    {
        return [
            (string) config('game_world.active_player_session_key') => $player->id,
            'auth.password_confirmed_at' => time(),
        ];
    }

    /** @return array<string, mixed> */
    private function activePlayerSession(Player $player): array
    {
        return [(string) config('game_world.active_player_session_key') => $player->id];
    }

    private function eventCount(string $table, string $eventColumn, string $event, string $allianceId): int
    {
        return DB::table($table)
            ->where('alliance_id', $allianceId)
            ->where($eventColumn, $event)
            ->count();
    }
}
