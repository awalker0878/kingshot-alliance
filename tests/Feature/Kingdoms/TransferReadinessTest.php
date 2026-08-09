<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\CreateTransferPlan;
use App\Domain\Kingdoms\Enums\TransferBlockerState;
use App\Domain\Kingdoms\Enums\TransferReadinessState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\TransferBlocker;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Kingdoms\Models\TransferReadinessTransition;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransferReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_sequence_is_explicit_and_blocked_requires_an_active_blocker(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Readiness Sequence', 'readiness-sequence', 5401);
        $plan = $this->plan($alliance, $owner, 'Sequence plan');
        $participant = $this->incoming($owner, $session, $plan, 'Alpha');

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
        self::assertSame($owner->id, $transition->actor_user_id);
    }

    public function test_resolving_final_blocker_never_auto_advances_readiness(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('No Auto Ready', 'no-auto-ready', 5402);
        $plan = $this->plan($alliance, $owner, 'No auto-ready plan');
        $participant = $this->incoming($owner, $session, $plan, 'Bravo');
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

    public function test_confirmed_is_planning_only_and_does_not_mutate_roster(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Confirmed Planning', 'confirmed-planning', 5403);
        $plan = $this->plan($alliance, $owner, 'Confirmed plan');
        $participant = $this->incoming($owner, $session, $plan, 'Charlie');
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
        self::assertDatabaseMissing('transfer_participants', [
            'id' => $participant->id,
            'readiness_state' => 'withdrawn',
        ]);
    }

    public function test_member_payload_shows_only_safe_readiness_summary(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Readiness Privacy', 'readiness-privacy', 5404);
        $plan = $this->plan($alliance, $owner, 'Privacy plan');
        $participant = $this->incoming($owner, $session, $plan, 'Delta');
        $this->block($owner, $session, $plan, $participant, 'Private blocker', 'Sensitive private blocker details');

        $this->actingAs($owner)->withSession($session)
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'blocked'])
            ->assertRedirect();

        $member = User::factory()->create();
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'user_id' => $member->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $memberRole = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::Member->value)
            ->sole();
        $membership->roles()->attach($memberRole->id, ['alliance_id' => $alliance->id]);
        $memberSession = [(string) config('identity.active_alliance_session_key') => $alliance->id];

        $this->actingAs($member)
            ->withSession($memberSession)
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
        [$ownerA, $allianceA, $sessionA] = $this->ownerAlliance('Readiness Tenant A', 'readiness-tenant-a', 5405);
        [$ownerB, $allianceB, $sessionB] = $this->ownerAlliance('Readiness Tenant B', 'readiness-tenant-b', 5406);
        $planA = $this->plan($allianceA, $ownerA, 'Plan A');
        $planB = $this->plan($allianceB, $ownerB, 'Plan B');
        $participantA = $this->incoming($ownerA, $sessionA, $planA, 'Echo A');
        $participantB = $this->incoming($ownerB, $sessionB, $planB, 'Echo B');
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

    public function test_readiness_requires_password_and_fails_closed_for_locked_or_drifted_plan(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('Readiness Gates', 'readiness-gates', 5407);
        $session = $this->confirmedSession($alliance->id);
        $plan = $this->plan($alliance, $owner, 'Gate plan');
        $participant = $this->incoming($owner, $session, $plan, 'Foxtrot');
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'preparing'])
            ->assertRedirect(route('password.confirm'));

        $this->withSession($session)->post("/alliance/transfers/{$plan->id}/open")->assertRedirect();
        $this->withSession($session)->post("/alliance/transfers/{$plan->id}/lock")->assertRedirect();
        $this->withSession($session)
            ->from('/alliance/transfers/readiness')
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'preparing'])
            ->assertRedirect('/alliance/transfers/readiness')
            ->assertSessionHasErrors('readiness');

        $driftPlan = $this->plan($alliance, $owner, 'Drift readiness plan');
        $driftParticipant = $this->incoming($owner, $session, $driftPlan, 'Golf');
        $newKingdom = Kingdom::query()->create(['number' => 5499, 'status' => 'active']);
        $alliance->forceFill(['kingdom_id' => $newKingdom->id])->save();

        $this->withSession($session)
            ->from('/alliance/transfers/readiness')
            ->post($this->blockersUrl($driftPlan, $driftParticipant), ['summary' => 'Should fail'])
            ->assertRedirect('/alliance/transfers/readiness')
            ->assertSessionHasErrors('blocker');
    }

    public function test_withdrawal_is_terminal_historic_and_idempotent(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Readiness Withdraw', 'readiness-withdraw', 5408);
        $plan = $this->plan($alliance, $owner, 'Withdraw readiness plan');
        $participant = $this->incoming($owner, $session, $plan, 'Hotel');

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

        $readinessAuditCount = $this->eventCount(
            'audit_events',
            'event',
            'kingdoms.transfer_readiness_changed',
            $alliance->id,
        );
        $withdrawAuditCount = $this->eventCount(
            'audit_events',
            'event',
            'kingdoms.transfer_participant_withdrawn',
            $alliance->id,
        );

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants/{$participant->id}/withdraw")
            ->assertRedirect();

        self::assertSame($readinessAuditCount, $this->eventCount(
            'audit_events',
            'event',
            'kingdoms.transfer_readiness_changed',
            $alliance->id,
        ));
        self::assertSame($withdrawAuditCount, $this->eventCount(
            'audit_events',
            'event',
            'kingdoms.transfer_participant_withdrawn',
            $alliance->id,
        ));
        self::assertSame(2, TransferReadinessTransition::query()->where('transfer_participant_id', $participant->id)->count());

        $this->withSession($session)
            ->from('/alliance/transfers/readiness')
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'ready'])
            ->assertRedirect('/alliance/transfers/readiness')
            ->assertSessionHasErrors('readiness');
    }

    public function test_private_blocker_text_never_enters_audit_or_outbox_payloads(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Readiness Events', 'readiness-events', 5409);
        $plan = $this->plan($alliance, $owner, 'Event safety plan');
        $participant = $this->incoming($owner, $session, $plan, 'India');
        $secret = 'PRIVATE-BLOCKER-TEXT-5409';

        $this->actingAs($owner)->withSession($session)
            ->post($this->blockersUrl($plan, $participant), [
                'summary' => $secret,
                'details' => $secret.' details',
            ])
            ->assertRedirect();
        $blocker = TransferBlocker::query()->sole();

        $this->withSession($session)
            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'blocked'])
            ->assertRedirect();
        $this->withSession($session)
            ->post($this->resolveBlockerUrl($plan, $participant, $blocker))
            ->assertRedirect();

        $auditPayload = DB::table('audit_events')
            ->where('alliance_id', $alliance->id)
            ->whereIn('event', [
                'kingdoms.transfer_blocker_created',
                'kingdoms.transfer_blocker_resolved',
                'kingdoms.transfer_readiness_changed',
            ])
            ->pluck('metadata')
            ->implode(' ');
        $outboxPayload = DB::table('outbox_messages')
            ->where('alliance_id', $alliance->id)
            ->whereIn('event_type', [
                'kingdoms.transfer_blocker_created',
                'kingdoms.transfer_blocker_resolved',
                'kingdoms.transfer_readiness_changed',
            ])
            ->pluck('payload')
            ->implode(' ');

        self::assertStringNotContainsString($secret, $auditPayload);
        self::assertStringNotContainsString($secret, $outboxPayload);
    }

    public function test_readiness_board_query_count_does_not_scale_with_participant_count(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Readiness Query Shape', 'readiness-query-shape', 5410);
        $plan = $this->plan($alliance, $owner, 'Query plan');

        for ($index = 1; $index <= 20; $index++) {
            $participant = $this->incoming($owner, $session, $plan, sprintf('Player %02d', $index));
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

    /** @return array{0: User, 1: Alliance, 2: array<string, mixed>} */
    private function ownerAlliance(string $name, string $slug, int $kingdom): array
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, $name, $slug, $kingdom);

        return [$owner, $alliance, $this->confirmedSession($alliance->id)];
    }

    private function plan(Alliance $alliance, User $owner, string $label): TransferPlan
    {
        return $this->app->make(CreateTransferPlan::class)->handle($alliance, $owner, ['label' => $label]);
    }

    private function incoming(
        User $owner,
        array $session,
        TransferPlan $plan,
        string $name,
    ): TransferParticipant {
        $this->actingAs($owner)
            ->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => $name,
            ])
            ->assertRedirect();

        return TransferParticipant::query()
            ->where('transfer_plan_id', $plan->id)
            ->where('observed_name', $name)
            ->sole();
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
            ->post($this->blockersUrl($plan, $participant), [
                'summary' => $summary,
                'details' => $details,
            ])
            ->assertRedirect();

        return TransferBlocker::query()
            ->where('transfer_participant_id', $participant->id)
            ->where('summary', $summary)
            ->sole();
    }

    private function readinessUrl(TransferPlan $plan, TransferParticipant $participant): string
    {
        return "/alliance/transfers/{$plan->id}/participants/{$participant->id}/readiness";
    }

    private function blockersUrl(TransferPlan $plan, TransferParticipant $participant): string
    {
        return "/alliance/transfers/{$plan->id}/participants/{$participant->id}/blockers";
    }

    private function resolveBlockerUrl(
        TransferPlan $plan,
        TransferParticipant $participant,
        TransferBlocker $blocker,
    ): string {
        return "/alliance/transfers/{$plan->id}/participants/{$participant->id}/blockers/{$blocker->id}/resolve";
    }

    /** @return array<string, mixed> */
    private function confirmedSession(string $allianceId): array
    {
        return [
            (string) config('identity.active_alliance_session_key') => $allianceId,
            'auth.password_confirmed_at' => time(),
        ];
    }

    private function eventCount(string $table, string $column, string $event, string $allianceId): int
    {
        return (int) DB::table($table)
            ->where('alliance_id', $allianceId)
            ->where($column, $event)
            ->count();
    }
}
