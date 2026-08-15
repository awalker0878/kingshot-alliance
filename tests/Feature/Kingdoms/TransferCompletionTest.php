<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Actions\AssignKingdomRole;
use App\Domain\Authorization\Actions\BootstrapKingdomAdministrator;
use App\Domain\Authorization\Enums\DefaultKingdomRole;
use App\Contexts\Accounts\Models\User;
use App\Domain\Kingdoms\Actions\CompleteTransferParticipant;
use App\Domain\Kingdoms\Actions\CreateTransferPlan;
use App\Domain\Kingdoms\Actions\LockTransferPlan;
use App\Domain\Kingdoms\Actions\OpenTransferPlan;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Domain\Kingdoms\Actions\SaveTransferParticipant;
use App\Domain\Kingdoms\Actions\TransitionTransferReadiness;
use App\Domain\Kingdoms\Actions\WithdrawTransferParticipant;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Enums\TransferDirection;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Enums\TransferReadinessState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Models\PlayerSnapshot;
use App\Domain\Kingdoms\Models\TransferCompletion;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransferCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_completion_preserves_durable_player_identity_and_creates_home_roster(): void
    {
        [, $ownerPlayer, $alliance] = $this->ownerAlliance('Completion Incoming', 'completion-incoming', 5501);
        $plan = $this->plan($alliance, $ownerPlayer, 'Incoming completion');
        $source = $this->kingdom(6501);
        $participant = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Incoming, [
            'name' => 'Arrival Alpha',
            'game_player_id' => 'arrival-5501',
            'source_kingdom' => $source->number,
        ]);
        $sourcePlayerId = (string) $participant->player_id;

        $this->confirmAndLock($alliance, $ownerPlayer, $plan, $participant);
        self::assertSame(0, PlayerSnapshot::query()->count());

        $completion = $this->app->make(CompleteTransferParticipant::class)->handle(
            $alliance,
            $ownerPlayer,
            (string) $plan->id,
            (string) $participant->id,
        );

        $roster = $completion->rosterEntry;
        self::assertInstanceOf(AllianceRosterEntry::class, $roster);
        self::assertSame(RosterState::Active, $roster->state);
        self::assertSame('Arrival Alpha', $roster->observed_name);
        self::assertSame('arrival-5501', $roster->player->game_player_id);
        self::assertSame($alliance->kingdom_id, $roster->player->current_kingdom_id);
        self::assertSame($sourcePlayerId, (string) $roster->player_id);
        self::assertSame($sourcePlayerId, (string) $participant->player_id);
        self::assertSame(0, PlayerSnapshot::query()->count());
        self::assertSame(1, $this->eventCount('audit_events', 'event', 'kingdoms.transfer_participant_completed', $alliance->id));
        self::assertSame(1, $this->eventCount('outbox_messages', 'event_type', 'kingdoms.transfer_participant_completed', $alliance->id));
    }

    public function test_incoming_completion_reactivates_historical_roster_for_same_player_and_preserves_private_fields(): void
    {
        [, $ownerPlayer, $alliance] = $this->ownerAlliance('Completion History', 'completion-history', 5502);
        $existing = $this->roster($alliance, $ownerPlayer, 'Historical Name', 'history-5502', [
            'game_role' => 'R4',
            'state' => RosterState::Left,
            'manager_notes' => 'PRESERVE-PRIVATE-5502',
        ]);
        $existingPlayerId = (string) $existing->player_id;
        $plan = $this->plan($alliance, $ownerPlayer, 'Incoming historical return');
        $source = $this->kingdom(6502);
        $participant = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Incoming, [
            'name' => 'Returning Player',
            'game_player_id' => 'history-5502',
            'source_kingdom' => $source->number,
        ]);

        self::assertSame($existingPlayerId, (string) $participant->player_id);
        self::assertSame($source->id, $participant->player->current_kingdom_id);
        $this->confirmAndLock($alliance, $ownerPlayer, $plan, $participant);

        $completion = $this->app->make(CompleteTransferParticipant::class)->handle(
            $alliance,
            $ownerPlayer,
            (string) $plan->id,
            (string) $participant->id,
        );

        $existing->refresh();
        self::assertSame($existing->id, $completion->roster_entry_id);
        self::assertSame($existingPlayerId, (string) $existing->player_id);
        self::assertSame('Returning Player', $existing->observed_name);
        self::assertSame('R4', $existing->game_role);
        self::assertSame(RosterState::Active, $existing->state);
        self::assertSame('PRESERVE-PRIVATE-5502', $existing->manager_notes);
        self::assertSame($alliance->kingdom_id, $existing->player->current_kingdom_id);
        self::assertSame(1, AllianceRosterEntry::query()->where('alliance_id', $alliance->id)->count());
    }

    public function test_incoming_staging_rejects_player_already_active_or_tracked_on_home_roster(): void
    {
        [, $ownerPlayer, $alliance] = $this->ownerAlliance('Completion Duplicate', 'completion-duplicate', 5503);
        $existing = $this->roster($alliance, $ownerPlayer, 'Already Home', 'already-home-5503', [
            'state' => RosterState::Tracked,
        ]);
        $plan = $this->plan($alliance, $ownerPlayer, 'Invalid incoming');
        $source = $this->kingdom(6503);

        try {
            $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Incoming, [
                'name' => 'Already Home',
                'game_player_id' => 'already-home-5503',
                'source_kingdom' => $source->number,
            ]);
            self::fail('An active/tracked home roster Player must not be relabelled as incoming from another Kingdom.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('source_kingdom', $exception->errors());
        }

        self::assertSame($alliance->kingdom_id, $existing->player->fresh()->current_kingdom_id);
        self::assertSame(0, TransferParticipant::query()->where('transfer_plan_id', $plan->id)->count());
    }

    public function test_outgoing_completion_uses_canonical_leave_workflow_moves_same_player_and_is_idempotent(): void
    {
        [, $ownerPlayer, $alliance] = $this->ownerAlliance('Completion Outgoing', 'completion-outgoing', 5504);
        $roster = $this->roster($alliance, $ownerPlayer, 'Leaving Player', 'leave-5504');
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $roster->player_id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R3,
            'joined_at' => now(),
        ]);
        $destination = $this->kingdom(6504);
        $plan = $this->plan($alliance, $ownerPlayer, 'Outgoing completion');
        $participant = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Outgoing, [
            'roster_entry_id' => $roster->id,
            'destination_kingdom' => $destination->number,
        ]);
        $playerId = (string) $participant->player_id;
        $this->confirmAndLock($alliance, $ownerPlayer, $plan, $participant);

        $complete = $this->app->make(CompleteTransferParticipant::class);
        $first = $complete->handle($alliance, $ownerPlayer, (string) $plan->id, (string) $participant->id);
        $second = $complete->handle($alliance, $ownerPlayer, (string) $plan->id, (string) $participant->id);

        self::assertSame($first->id, $second->id);
        self::assertSame($playerId, (string) $roster->player_id);
        self::assertSame(RosterState::Left, $roster->refresh()->state);
        self::assertNotNull($roster->left_at);
        self::assertSame($destination->id, $roster->player->fresh()->current_kingdom_id);
        self::assertSame(MembershipStatus::Left, $membership->refresh()->status);
        self::assertNotNull($membership->left_at);
        self::assertSame(1, TransferCompletion::query()->where('transfer_participant_id', $participant->id)->count());
        self::assertSame(1, $this->eventCount('audit_events', 'event', 'membership.left', $alliance->id));
        self::assertSame(1, $this->eventCount('outbox_messages', 'event_type', 'membership.left', $alliance->id));
        self::assertSame(1, $this->eventCount('audit_events', 'event', 'kingdoms.roster_entry_left', $alliance->id));
        self::assertSame(1, $this->eventCount('audit_events', 'event', 'kingdoms.transfer_participant_completed', $alliance->id));
        self::assertSame(1, $this->eventCount('outbox_messages', 'event_type', 'kingdoms.transfer_participant_completed', $alliance->id));
    }

    public function test_outgoing_r5_cannot_complete_until_leadership_is_transferred(): void
    {
        [, $ownerPlayer, $alliance] = $this->ownerAlliance('Completion R5', 'completion-r5', 5505);
        $roster = $this->roster($alliance, $ownerPlayer, $ownerPlayer->current_name, (string) $ownerPlayer->game_player_id);
        $destination = $this->kingdom(6505);
        $plan = $this->plan($alliance, $ownerPlayer, 'R5 outgoing');
        $participant = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Outgoing, [
            'roster_entry_id' => $roster->id,
            'destination_kingdom' => $destination->number,
        ]);
        $this->confirmAndLock($alliance, $ownerPlayer, $plan, $participant);

        try {
            $this->app->make(CompleteTransferParticipant::class)->handle(
                $alliance,
                $ownerPlayer,
                (string) $plan->id,
                (string) $participant->id,
            );
            self::fail('An R5 Player must transfer Alliance leadership before leaving the Kingdom.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('completion', $exception->errors());
        }

        self::assertSame(MembershipStatus::Active, AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $ownerPlayer->id)
            ->sole()->status);
        self::assertSame(RosterState::Active, $roster->refresh()->state);
        self::assertSame($alliance->kingdom_id, $ownerPlayer->fresh()->current_kingdom_id);
        self::assertSame(0, TransferCompletion::query()->count());
    }

    public function test_outgoing_player_with_kingdom_role_must_resolve_role_before_completion(): void
    {
        [, $ownerPlayer, $alliance] = $this->ownerAlliance('Completion Role', 'completion-role', 5506);
        $roster = $this->roster($alliance, $ownerPlayer, 'Role Holder', 'role-holder-5506');
        $this->app->make(BootstrapKingdomAdministrator::class)->handle($alliance->kingdom, $ownerPlayer);
        $this->app->make(AssignKingdomRole::class)->handle(
            $ownerPlayer,
            $alliance->kingdom,
            $roster->player,
            DefaultKingdomRole::Viewer,
        );
        $destination = $this->kingdom(6506);
        $plan = $this->plan($alliance, $ownerPlayer, 'Role blocked outgoing');
        $participant = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Outgoing, [
            'roster_entry_id' => $roster->id,
            'destination_kingdom' => $destination->number,
        ]);
        $this->confirmAndLock($alliance, $ownerPlayer, $plan, $participant);

        try {
            $this->app->make(CompleteTransferParticipant::class)->handle(
                $alliance,
                $ownerPlayer,
                (string) $plan->id,
                (string) $participant->id,
            );
            self::fail('A Player with Kingdom roles must not move Kingdoms.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('completion', $exception->errors());
        }

        self::assertSame(RosterState::Active, $roster->refresh()->state);
        self::assertSame($alliance->kingdom_id, $roster->player->fresh()->current_kingdom_id);
        self::assertSame(0, TransferCompletion::query()->count());
    }

    public function test_staying_completion_records_outcome_without_roster_lifecycle_mutation(): void
    {
        [, $ownerPlayer, $alliance] = $this->ownerAlliance('Completion Staying', 'completion-staying', 5507);
        $roster = $this->roster($alliance, $ownerPlayer, 'Staying Player', 'stay-5507', [
            'state' => RosterState::Tracked,
            'manager_notes' => 'Stay private note',
        ]);
        $plan = $this->plan($alliance, $ownerPlayer, 'Staying completion');
        $participant = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Staying, [
            'roster_entry_id' => $roster->id,
        ]);
        $this->confirmAndLock($alliance, $ownerPlayer, $plan, $participant);
        $rosterEventsBefore = $this->eventCount('audit_events', 'event', 'kingdoms.roster_entry_updated', $alliance->id);

        $completion = $this->app->make(CompleteTransferParticipant::class)->handle(
            $alliance,
            $ownerPlayer,
            (string) $plan->id,
            (string) $participant->id,
        );

        $roster->refresh();
        self::assertSame($roster->id, $completion->roster_entry_id);
        self::assertSame(RosterState::Tracked, $roster->state);
        self::assertNull($roster->left_at);
        self::assertSame('Stay private note', $roster->manager_notes);
        self::assertSame($rosterEventsBefore, $this->eventCount('audit_events', 'event', 'kingdoms.roster_entry_updated', $alliance->id));
    }

    public function test_completion_requires_confirmed_participant_and_locked_plan(): void
    {
        [$ownerUser, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Completion Gates', 'completion-gates', 5508);
        $plan = $this->plan($alliance, $ownerPlayer, 'Completion gates');
        $source = $this->kingdom(6508);
        $participant = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Incoming, [
            'name' => 'Gate Player',
            'source_kingdom' => $source->number,
        ]);

        $this->actingAs($ownerUser)->withSession($session)
            ->from('/alliance/transfers/completion')
            ->post($this->completionUrl($plan, $participant))
            ->assertRedirect('/alliance/transfers/completion')
            ->assertSessionHasErrors('completion');

        $this->confirm($alliance, $ownerPlayer, $participant);
        $this->withSession($session)
            ->from('/alliance/transfers/completion')
            ->post($this->completionUrl($plan, $participant))
            ->assertRedirect('/alliance/transfers/completion')
            ->assertSessionHasErrors('completion');

        $this->app->make(OpenTransferPlan::class)->handle($alliance, $ownerPlayer, (string) $plan->id);
        $this->app->make(LockTransferPlan::class)->handle($alliance, $ownerPlayer, (string) $plan->id);
        $this->withSession($session)->post($this->completionUrl($plan, $participant))->assertRedirect();

        self::assertSame(1, TransferCompletion::query()->where('transfer_participant_id', $participant->id)->count());
    }

    public function test_completion_requires_recent_password_confirmation_for_the_authenticated_user(): void
    {
        [$ownerUser, $ownerPlayer, $alliance] = $this->ownerAlliance('Completion Password', 'completion-password', 5509);
        $plan = $this->plan($alliance, $ownerPlayer, 'Password gate');
        $source = $this->kingdom(6509);
        $participant = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Incoming, [
            'name' => 'Password Player',
            'source_kingdom' => $source->number,
        ]);
        $this->confirmAndLock($alliance, $ownerPlayer, $plan, $participant);

        $this->actingAs($ownerUser)
            ->withSession($this->activeSession($ownerPlayer->id))
            ->post($this->completionUrl($plan, $participant))
            ->assertRedirect(route('password.confirm'));

        self::assertSame(0, TransferCompletion::query()->count());
    }

    public function test_close_fails_until_every_active_participant_is_completed_or_withdrawn(): void
    {
        [$ownerUser, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Completion Close', 'completion-close', 5510);
        $plan = $this->plan($alliance, $ownerPlayer, 'Close gate');
        $sourceA = $this->kingdom(6510);
        $sourceB = $this->kingdom(6511);
        $active = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Incoming, [
            'name' => 'Must Complete',
            'source_kingdom' => $sourceA->number,
        ]);
        $withdrawn = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Incoming, [
            'name' => 'Withdraw First',
            'source_kingdom' => $sourceB->number,
        ]);
        $this->confirm($alliance, $ownerPlayer, $active);
        $this->app->make(WithdrawTransferParticipant::class)->handle(
            $alliance,
            $ownerPlayer,
            (string) $plan->id,
            (string) $withdrawn->id,
        );
        $this->app->make(OpenTransferPlan::class)->handle($alliance, $ownerPlayer, (string) $plan->id);
        $this->app->make(LockTransferPlan::class)->handle($alliance, $ownerPlayer, (string) $plan->id);

        $this->actingAs($ownerUser)->withSession($session)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$plan->id}/close")
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('plan');
        self::assertSame(TransferPlanState::Locked, $plan->refresh()->state);

        $this->withSession($session)->post($this->completionUrl($plan, $active))->assertRedirect();
        $this->withSession($session)->post("/alliance/transfers/{$plan->id}/close")->assertRedirect();
        self::assertSame(TransferPlanState::Closed, $plan->refresh()->state);
    }

    public function test_completion_re_resolves_plan_and_participant_under_active_players_alliance(): void
    {
        [$ownerUserA, , , $sessionA] = $this->ownerAlliance('Completion Tenant A', 'completion-tenant-a', 5512);
        [, $ownerPlayerB, $allianceB] = $this->ownerAlliance('Completion Tenant B', 'completion-tenant-b', 5513);
        $planB = $this->plan($allianceB, $ownerPlayerB, 'Tenant B completion');
        $source = $this->kingdom(6513);
        $participantB = $this->participant($allianceB, $ownerPlayerB, $planB, TransferDirection::Incoming, [
            'name' => 'Tenant B Incoming',
            'source_kingdom' => $source->number,
        ]);
        $this->confirmAndLock($allianceB, $ownerPlayerB, $planB, $participantB);

        $this->actingAs($ownerUserA)->withSession($sessionA)
            ->post($this->completionUrl($planB, $participantB))
            ->assertNotFound();

        self::assertSame(0, TransferCompletion::query()->where('alliance_id', $allianceB->id)->count());
    }

    public function test_completion_fails_closed_if_plan_home_kingdom_drifts_from_immutable_alliance_kingdom(): void
    {
        [, $ownerPlayer, $alliance] = $this->ownerAlliance('Completion Drift', 'completion-drift', 5514);
        $plan = $this->plan($alliance, $ownerPlayer, 'Drift completion');
        $source = $this->kingdom(6514);
        $participant = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Incoming, [
            'name' => 'Drift Incoming',
            'source_kingdom' => $source->number,
        ]);
        $this->confirmAndLock($alliance, $ownerPlayer, $plan, $participant);
        $driftKingdom = $this->kingdom(6515);
        $plan->forceFill(['home_kingdom_id' => $driftKingdom->id])->save();

        try {
            $this->app->make(CompleteTransferParticipant::class)->handle(
                $alliance,
                $ownerPlayer,
                (string) $plan->id,
                (string) $participant->id,
            );
            self::fail('Completion must fail closed when the transfer plan no longer belongs to the immutable Alliance Kingdom.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('completion', $exception->errors());
        }

        self::assertSame(0, TransferCompletion::query()->where('alliance_id', $alliance->id)->count());
        self::assertSame($source->id, $participant->player->fresh()->current_kingdom_id);
    }

    public function test_request_cannot_override_incoming_player_identity_with_a_posted_roster_id(): void
    {
        [$ownerUserA, $ownerPlayerA, $allianceA, $sessionA] = $this->ownerAlliance('Completion Identity A', 'completion-identity-a', 5516);
        [, $ownerPlayerB, $allianceB] = $this->ownerAlliance('Completion Identity B', 'completion-identity-b', 5517);
        $foreignRoster = $this->roster($allianceB, $ownerPlayerB, 'Foreign Roster', 'foreign-5517');
        $plan = $this->plan($allianceA, $ownerPlayerA, 'Identity completion');
        $source = $this->kingdom(6516);
        $participant = $this->participant($allianceA, $ownerPlayerA, $plan, TransferDirection::Incoming, [
            'name' => 'Expected Identity',
            'game_player_id' => 'expected-5516',
            'source_kingdom' => $source->number,
        ]);
        $capturedPlayerId = (string) $participant->player_id;
        $this->confirmAndLock($allianceA, $ownerPlayerA, $plan, $participant);

        $this->actingAs($ownerUserA)->withSession($sessionA)
            ->post($this->completionUrl($plan, $participant), ['roster_entry_id' => $foreignRoster->id])
            ->assertRedirect();

        $completion = TransferCompletion::query()->where('transfer_participant_id', $participant->id)->sole();
        self::assertSame($capturedPlayerId, (string) $completion->rosterEntry->player_id);
        self::assertNotSame((string) $foreignRoster->player_id, (string) $completion->rosterEntry->player_id);
        self::assertSame(RosterState::Active, $foreignRoster->refresh()->state);
    }

    public function test_member_sees_safe_completion_summary_but_not_manager_handoff_provenance(): void
    {
        [, $ownerPlayer, $alliance] = $this->ownerAlliance('Completion Privacy', 'completion-privacy', 5518);
        $plan = $this->plan($alliance, $ownerPlayer, 'Privacy completion');
        $source = $this->kingdom(6518);
        $participant = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Incoming, [
            'name' => 'Privacy Player',
            'source_kingdom' => $source->number,
        ]);
        $this->confirmAndLock($alliance, $ownerPlayer, $plan, $participant);
        $this->app->make(CompleteTransferParticipant::class)->handle(
            $alliance,
            $ownerPlayer,
            (string) $plan->id,
            (string) $participant->id,
        );

        $memberUser = User::factory()->create();
        $memberPlayer = $this->player($memberUser, $alliance->kingdom, 'privacy-member-5518', 'Privacy Member');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $this->actingAs($memberUser)
            ->withSession($this->activeSession($memberPlayer->id))
            ->get('/alliance/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/TransferPlans')
                ->where('participants.0.name', 'Privacy Player')
                ->where('participants.0.completedAt', fn (mixed $value): bool => is_string($value) && $value !== '')
                ->missing('participants.0.completion'));

        $this->get('/alliance/transfers/completion')->assertForbidden();
    }

    private function participant(
        Alliance $alliance,
        Player $actor,
        TransferPlan $plan,
        TransferDirection $direction,
        array $attributes,
    ): TransferParticipant {
        return $this->app->make(SaveTransferParticipant::class)->handle(
            $alliance,
            $actor,
            (string) $plan->id,
            ['direction' => $direction, ...$attributes],
        );
    }

    private function roster(
        Alliance $alliance,
        Player $actor,
        string $name,
        string $gamePlayerId,
        array $attributes = [],
    ): AllianceRosterEntry {
        return $this->app->make(SaveRosterEntry::class)->handle($alliance, $actor, [
            'name' => $name,
            'game_player_id' => $gamePlayerId,
            ...$attributes,
        ]);
    }

    private function confirm(Alliance $alliance, Player $actor, TransferParticipant $participant): void
    {
        $transition = $this->app->make(TransitionTransferReadiness::class);
        foreach ([
            TransferReadinessState::Preparing,
            TransferReadinessState::Ready,
            TransferReadinessState::Confirmed,
        ] as $state) {
            $transition->handle(
                $alliance,
                $actor,
                (string) $participant->transfer_plan_id,
                (string) $participant->id,
                $state,
            );
        }
    }

    private function confirmAndLock(
        Alliance $alliance,
        Player $actor,
        TransferPlan $plan,
        TransferParticipant $participant,
    ): void {
        $this->confirm($alliance, $actor, $participant);
        $this->app->make(OpenTransferPlan::class)->handle($alliance, $actor, (string) $plan->id);
        $this->app->make(LockTransferPlan::class)->handle($alliance, $actor, (string) $plan->id);
    }

    /** @return array{0: User, 1: Player, 2: Alliance, 3: array<string, mixed>} */
    private function ownerAlliance(string $name, string $slug, int $kingdomNumber): array
    {
        $ownerUser = User::factory()->create();
        $kingdom = $this->kingdom($kingdomNumber);
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

    private function kingdom(int $number): Kingdom
    {
        return Kingdom::query()->firstOrCreate(
            ['number' => $number],
            ['status' => 'active'],
        );
    }

    private function plan(Alliance $alliance, Player $actor, string $label): TransferPlan
    {
        return $this->app->make(CreateTransferPlan::class)->handle($alliance, $actor, ['label' => $label]);
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

    private function completionUrl(TransferPlan $plan, TransferParticipant $participant): string
    {
        return "/alliance/transfers/{$plan->id}/participants/{$participant->id}/complete";
    }

    private function eventCount(string $table, string $column, string $event, string $allianceId): int
    {
        return (int) DB::table($table)
            ->where('alliance_id', $allianceId)
            ->where($column, $event)
            ->count();
    }
}
