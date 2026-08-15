<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Enums\KingdomStatus;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Actions\CreateTransferPlan;
use App\Domain\Kingdoms\Enums\TransferBlockerState;
use App\Domain\Kingdoms\Enums\TransferReadinessState;
use App\Domain\Kingdoms\Models\TransferBlocker;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Kingdoms\Models\TransferReadinessTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransferReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_sequence_is_explicit_and_blocked_requires_an_active_blocker(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Readiness Sequence', 'readiness-sequence', 5401);
        $plan = $this->plan($alliance, $ownerPlayer, 'Sequence plan');
        $participant = $this->incoming($owner, $session, $plan, 'Alpha', 5491);

        self::assertSame(TransferReadinessState::NotStarted, $participant->readiness_state);

        $this->actingAs($owner)
            ->withSession($session)
            ->from('/alliance/transfers/readiness')
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'ready'])
            ->assertRedirect('/alliance/transfers/readiness')
            ->assertSessionHasErrors('readiness');

        $this->withSession($session)
            ->from('/alliance/transfers/readiness')
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'blocked'])
            ->assertRedirect('/alliance/transfers/readiness')
            ->assertSessionHasErrors('readiness');

        $this->withSession($session)
            ->post($this->blockersUrl($plan, $participant), [
                'summary' => 'Waiting for transfer window',
                'details' => 'Management-only scheduling detail',
            ])
            ->assertRedirect();

        self::assertSame(TransferReadinessState::NotStarted, $participant->refresh()->readiness_state);

        $this->withSession($session)
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'blocked'])
            ->assertRedirect();

        self::assertSame(TransferReadinessState::Blocked, $participant->refresh()->readiness_state);
        $transition = TransferReadinessTransition::query()->sole();
        self::assertSame(TransferReadinessState::NotStarted, $transition->from_state);
        self::assertSame(TransferReadinessState::Blocked, $transition->to_state);
        self::assertSame($ownerPlayer->id, $transition->actor_player_id);
        self::assertSame($ownerPlayer->id, $transition->actor->id);
    }

    public function test_resolving_final_blocker_never_auto_advances_readiness(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('No Auto Ready', 'no-auto-ready', 5402);
        $plan = $this->plan($alliance, $ownerPlayer, 'No auto-ready plan');
        $participant = $this->incoming($owner, $session, $plan, 'Bravo', 5492);
        $blocker = $this->block($owner, $session, $plan, $participant, 'Needs confirmation');

        $this->actingAs($owner)->withSession($session)
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'blocked'])
            ->assertRedirect();

        $this->withSession($session)
            ->post($this->resolveBlockerUrl($plan, $participant, $blocker))
            ->assertRedirect();

        self::assertSame(TransferBlockerState::Resolved, $blocker->refresh()->state);
        self::assertSame(TransferReadinessState::Blocked, $participant->refresh()->readiness_state);
        self::assertSame(1, TransferReadinessTransition::query()->count());

        $this->withSession($session)
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'ready'])
            ->assertRedirect();

        self::assertSame(TransferReadinessState::Ready, $participant->refresh()->readiness_state);
        self::assertSame(2, TransferReadinessTransition::query()->count());
    }

    public function test_confirmed_is_manual_planning_state_and_does_not_mutate_player_or_roster(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Confirmed Planning', 'confirmed-planning', 5403);
        $plan = $this->plan($alliance, $ownerPlayer, 'Confirmed plan');
        $participant = $this->incoming($owner, $session, $plan, 'Charlie', 5493);
        $player = $participant->player;
        $sourceKingdomId = $player->current_kingdom_id;
        $rosterCount = AllianceRosterEntry::query()->where('alliance_id', $alliance->id)->count();

        $this->actingAs($owner)->withSession($session)
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'preparing'])
            ->assertRedirect();
        $this->withSession($session)
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'ready'])
            ->assertRedirect();
        $this->withSession($session)
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'confirmed'])
            ->assertRedirect();

        self::assertSame(TransferReadinessState::Confirmed, $participant->refresh()->readiness_state);
        self::assertSame($rosterCount, AllianceRosterEntry::query()->where('alliance_id', $alliance->id)->count());
        self::assertSame($sourceKingdomId, $player->refresh()->current_kingdom_id);
        self::assertNull($participant->completion);
    }

    public function test_r1_member_payload_shows_only_safe_readiness_summary(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Readiness Privacy', 'readiness-privacy', 5404);
        $plan = $this->plan($alliance, $ownerPlayer, 'Privacy plan');
        $participant = $this->incoming($owner, $session, $plan, 'Delta', 5494);
        $this->block($owner, $session, $plan, $participant, 'Private blocker', 'Sensitive private blocker details');

        $this->actingAs($owner)->withSession($session)
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'blocked'])
            ->assertRedirect();

        $member = User::factory()->create();
        $memberPlayer = $this->player($member, $alliance->kingdom, 'readiness-r1', 'Readiness R1');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $this->actingAs($member)
            ->withSession($this->activeSession($memberPlayer->id))
            ->get('/alliance/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/TransferPlans')
                ->where('participants.0.name', 'Delta')
                ->where('participants.0.readiness', 'blocked')
                ->missing('participants.0.blockers')
                ->missing('participants.0.readinessHistory')
                ->missing('participants.0.managerNotes'));

        $this->get('/alliance/transfers/readiness')->assertForbidden();
    }

    public function test_cross_alliance_participant_and_blocker_ids_fail_closed(): void
    {
        [$ownerA, $playerA, $allianceA, $sessionA] = $this->ownerAlliance('Readiness Tenant A', 'readiness-tenant-a', 5405);
        [$ownerB, $playerB, $allianceB, $sessionB] = $this->ownerAlliance('Readiness Tenant B', 'readiness-tenant-b', 5406);
        $planA = $this->plan($allianceA, $playerA, 'Plan A');
        $planB = $this->plan($allianceB, $playerB, 'Plan B');
        $participantA = $this->incoming($ownerA, $sessionA, $planA, 'Echo A', 5495);
        $participantB = $this->incoming($ownerB, $sessionB, $planB, 'Echo B', 5496);
        $blockerB = $this->block($ownerB, $sessionB, $planB, $participantB, 'Tenant B blocker');

        $this->actingAs($ownerA)
            ->withSession($sessionA)
            ->patch($this->readinessUrl($planA, $participantB), ['readiness' => 'preparing'])
            ->assertNotFound();

        $this->withSession($sessionA)
            ->post($this->resolveBlockerUrl($planA, $participantA, $blockerB))
            ->assertNotFound();

        self::assertSame(TransferReadinessState::NotStarted, $participantA->refresh()->readiness_state);
        self::assertSame(TransferBlockerState::Active, $blockerB->refresh()->state);
    }

    public function test_readiness_mutations_require_recent_password_confirmation(): void
    {
        [$owner, $ownerPlayer, $alliance, $confirmed] = $this->ownerAlliance('Readiness Password', 'readiness-password', 5411);
        $plan = $this->plan($alliance, $ownerPlayer, 'Password plan');
        $participant = $this->incoming($owner, $confirmed, $plan, 'Foxtrot', 5497);

        $this->actingAs($owner)
            ->withSession($this->activeSession($ownerPlayer->id))
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'preparing'])
            ->assertRedirect(route('password.confirm'));

        self::assertSame(TransferReadinessState::NotStarted, $participant->refresh()->readiness_state);
    }

    public function test_readiness_fails_closed_for_locked_or_mismatched_plan_home_kingdom(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Readiness Gates', 'readiness-gates', 5407);
        $plan = $this->plan($alliance, $ownerPlayer, 'Gate plan');
        $participant = $this->incoming($owner, $session, $plan, 'Foxtrot', 5498);

        $this->actingAs($owner)->withSession($session)->post("/alliance/transfers/{$plan->id}/open")->assertRedirect();
        $this->withSession($session)->post("/alliance/transfers/{$plan->id}/lock")->assertRedirect();
        $this->withSession($session)
            ->from('/alliance/transfers/readiness')
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'preparing'])
            ->assertRedirect('/alliance/transfers/readiness')
            ->assertSessionHasErrors('readiness');

        $mismatchPlan = $this->plan($alliance, $ownerPlayer, 'Mismatch readiness plan');
        $mismatchParticipant = $this->incoming($owner, $session, $mismatchPlan, 'Golf', 5499);
        $otherKingdom = Kingdom::query()->create(['number' => 5500, 'status' => KingdomStatus::Active]);
        $mismatchPlan->forceFill(['home_kingdom_id' => $otherKingdom->id])->save();

        $this->withSession($session)
            ->from('/alliance/transfers/readiness')
            ->post($this->blockersUrl($mismatchPlan, $mismatchParticipant), ['summary' => 'Should fail'])
            ->assertRedirect('/alliance/transfers/readiness')
            ->assertSessionHasErrors('blocker');
    }

    public function test_withdrawal_is_terminal_historical_and_idempotent(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Readiness Withdraw', 'readiness-withdraw', 5408);
        $plan = $this->plan($alliance, $ownerPlayer, 'Withdraw readiness plan');
        $participant = $this->incoming($owner, $session, $plan, 'Hotel', 5501);

        $this->actingAs($owner)->withSession($session)
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'preparing'])
            ->assertRedirect();
        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants/{$participant->id}/withdraw")
            ->assertRedirect();

        $participant->refresh();
        self::assertSame(TransferReadinessState::Withdrawn, $participant->readiness_state);
        self::assertNotNull($participant->withdrawn_at);
        self::assertSame(2, TransferReadinessTransition::query()->where('transfer_participant_id', $participant->id)->count());
        self::assertTrue(TransferReadinessTransition::query()
            ->where('transfer_participant_id', $participant->id)
            ->where('actor_player_id', $ownerPlayer->id)
            ->exists());

        $readinessAuditCount = $this->eventCount('audit_events', 'event', 'kingdoms.transfer_readiness_changed', $alliance->id);
        $withdrawAuditCount = $this->eventCount('audit_events', 'event', 'kingdoms.transfer_participant_withdrawn', $alliance->id);

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants/{$participant->id}/withdraw")
            ->assertRedirect();

        self::assertSame($readinessAuditCount, $this->eventCount('audit_events', 'event', 'kingdoms.transfer_readiness_changed', $alliance->id));
        self::assertSame($withdrawAuditCount, $this->eventCount('audit_events', 'event', 'kingdoms.transfer_participant_withdrawn', $alliance->id));
        self::assertSame(2, TransferReadinessTransition::query()->where('transfer_participant_id', $participant->id)->count());

        $this->withSession($session)
            ->from('/alliance/transfers/readiness')
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'ready'])
            ->assertRedirect('/alliance/transfers/readiness')
            ->assertSessionHasErrors('readiness');
    }

    public function test_private_blocker_text_never_enters_audit_or_outbox_payloads(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Readiness Events', 'readiness-events', 5409);
        $plan = $this->plan($alliance, $ownerPlayer, 'Event safety plan');
        $participant = $this->incoming($owner, $session, $plan, 'India', 5502);
        $secret = 'PRIVATE-BLOCKER-TEXT-5409';

        $this->actingAs($owner)->withSession($session)
            ->post($this->blockersUrl($plan, $participant), ['summary' => $secret, 'details' => $secret.' details'])
            ->assertRedirect();
        $blocker = TransferBlocker::query()->sole();

        $this->withSession($session)->patch($this->readinessUrl($plan, $participant), ['readiness' => 'blocked'])->assertRedirect();
        $this->withSession($session)->post($this->resolveBlockerUrl($plan, $participant, $blocker))->assertRedirect();

        $auditPayload = DB::table('audit_events')
            ->where('alliance_id', $alliance->id)
            ->whereIn('event', ['kingdoms.transfer_blocker_created', 'kingdoms.transfer_blocker_resolved', 'kingdoms.transfer_readiness_changed'])
            ->pluck('metadata')->implode(' ');
        $outboxPayload = DB::table('outbox_messages')
            ->where('alliance_id', $alliance->id)
            ->whereIn('event_type', ['kingdoms.transfer_blocker_created', 'kingdoms.transfer_blocker_resolved', 'kingdoms.transfer_readiness_changed'])
            ->pluck('payload')->implode(' ');

        self::assertStringNotContainsString($secret, $auditPayload);
        self::assertStringNotContainsString($secret, $outboxPayload);
    }

    public function test_readiness_board_query_count_does_not_scale_with_participant_count(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Readiness Query Shape', 'readiness-query-shape', 5410);
        $plan = $this->plan($alliance, $ownerPlayer, 'Query plan');

        for ($index = 1; $index <= 20; $index++) {
            $participant = $this->incoming($owner, $session, $plan, sprintf('Player %02d', $index), 5503);
            if ($index <= 5) {
                $this->block($owner, $session, $plan, $participant, 'Blocker '.$index);
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($owner)
            ->withSession($session)
            ->get('/alliance/transfers/readiness')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/TransferReadinessManage')
                ->has('participants', 20));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        self::assertLessThanOrEqual(25, $queryCount, 'Readiness board queries should remain bounded as participant count grows.');
    }

    /** @return array{0: User, 1: Player, 2: Alliance, 3: array<string, mixed>} */
    private function ownerAlliance(string $name, string $slug, int $kingdomNumber): array
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => $kingdomNumber, 'status' => KingdomStatus::Active]);
        $ownerPlayer = $this->player($owner, $kingdom, $slug.'-r5', $name.' R5');
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, $name, $slug);

        return [$owner, $ownerPlayer, $alliance, $this->confirmedSession($ownerPlayer->id)];
    }

    private function plan(Alliance $alliance, Player $actor, string $label): TransferPlan
    {
        return $this->app->make(CreateTransferPlan::class)->handle($alliance, $actor, ['label' => $label]);
    }

    private function incoming(
        User $owner,
        array $session,
        TransferPlan $plan,
        string $name,
        int $sourceKingdom,
    ): TransferParticipant {
        $this->actingAs($owner)
            ->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => $name,
                'source_kingdom' => $sourceKingdom,
            ])
            ->assertRedirect();

        return TransferParticipant::query()->where('transfer_plan_id', $plan->id)->where('observed_name', $name)->sole();
    }

    private function block(
        User $owner,
        array $session,
        TransferPlan $plan,
        TransferParticipant $participant,
        string $summary,
        ?string $details = null,
    ): TransferBlocker {
        $this->actingAs($owner)
            ->withSession($session)
            ->post($this->blockersUrl($plan, $participant), ['summary' => $summary, 'details' => $details])
            ->assertRedirect();

        return TransferBlocker::query()->where('transfer_participant_id', $participant->id)->where('summary', $summary)->sole();
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

    private function readinessUrl(TransferPlan $plan, TransferParticipant $participant): string
    {
        return "/alliance/transfers/{$plan->id}/participants/{$participant->id}/readiness";
    }

    private function blockersUrl(TransferPlan $plan, TransferParticipant $participant): string
    {
        return "/alliance/transfers/{$plan->id}/participants/{$participant->id}/blockers";
    }

    private function resolveBlockerUrl(TransferPlan $plan, TransferParticipant $participant, TransferBlocker $blocker): string
    {
        return "/alliance/transfers/{$plan->id}/participants/{$participant->id}/blockers/{$blocker->id}/resolve";
    }

    /** @return array<string, mixed> */
    private function activeSession(string $playerId): array
    {
        return [(string) config('game_world.active_player_session_key') => $playerId];
    }

    /** @return array<string, mixed> */
    private function confirmedSession(string $playerId): array
    {
        return [...$this->activeSession($playerId), 'auth.password_confirmed_at' => time()];
    }

    private function eventCount(string $table, string $column, string $event, string $allianceId): int
    {
        return (int) DB::table($table)->where('alliance_id', $allianceId)->where($column, $event)->count();
    }
}
