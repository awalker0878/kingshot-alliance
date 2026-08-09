<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
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
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\PlayerSnapshot;
use App\Domain\Kingdoms\Models\TransferCompletion;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransferCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_completion_creates_home_roster_result_without_fabricating_snapshot(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('Completion Incoming', 'completion-incoming', 5501);
        $plan = $this->plan($alliance, $owner, 'Incoming completion');
        $source = Kingdom::query()->create(['number' => 6501, 'status' => 'active']);
        $participant = $this->participant($alliance, $owner, $plan, TransferDirection::Incoming, [
            'name' => 'Arrival Alpha',
            'game_player_id' => 'arrival-5501',
            'source_kingdom' => $source->number,
        ]);
        $sourcePlayerId = $participant->kingdom_player_id;

        $this->confirmAndLock($alliance, $owner, $plan, $participant);
        self::assertSame(0, PlayerSnapshot::query()->count());

        $completion = $this->app->make(CompleteTransferParticipant::class)->handle(
            $alliance,
            $owner,
            (string) $plan->id,
            (string) $participant->id,
        );

        $roster = $completion->rosterEntry;
        self::assertInstanceOf(AllianceRosterEntry::class, $roster);
        self::assertSame(RosterState::Active, $roster->state);
        self::assertSame('Arrival Alpha', $roster->observed_name);
        self::assertSame('arrival-5501', $roster->player->game_player_id);
        self::assertSame($alliance->kingdom_id, $roster->player->kingdom_id);
        self::assertNotSame($sourcePlayerId, $roster->kingdom_player_id);
        self::assertSame(0, PlayerSnapshot::query()->count());
        self::assertSame(1, $this->eventCount('audit_events', 'event', 'kingdoms.transfer_participant_completed', $alliance->id));
        self::assertSame(1, $this->eventCount('outbox_messages', 'event_type', 'kingdoms.transfer_participant_completed', $alliance->id));
    }

    public function test_incoming_completion_explicitly_links_existing_roster_and_preserves_private_fields(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('Completion Link', 'completion-link', 5502);
        $existing = $this->roster($alliance, $owner, 'Accepted Roster Name', 'link-5502', [
            'game_role' => 'R4',
            'state' => RosterState::Tracked,
            'manager_notes' => 'PRESERVE-PRIVATE-5502',
        ]);
        $plan = $this->plan($alliance, $owner, 'Incoming explicit link');
        $source = Kingdom::query()->create(['number' => 6502, 'status' => 'active']);
        $participant = $this->participant($alliance, $owner, $plan, TransferDirection::Incoming, [
            'name' => 'Different Planning Name',
            'game_player_id' => 'link-5502',
            'source_kingdom' => $source->number,
        ]);
        $this->confirmAndLock($alliance, $owner, $plan, $participant);

        $completion = $this->app->make(CompleteTransferParticipant::class)->handle(
            $alliance,
            $owner,
            (string) $plan->id,
            (string) $participant->id,
            (string) $existing->id,
        );

        $existing->refresh();
        self::assertSame($existing->id, $completion->roster_entry_id);
        self::assertSame('Accepted Roster Name', $existing->observed_name);
        self::assertSame('R4', $existing->game_role);
        self::assertSame(RosterState::Tracked, $existing->state);
        self::assertSame('PRESERVE-PRIVATE-5502', $existing->manager_notes);
        self::assertSame(1, AllianceRosterEntry::query()->where('alliance_id', $alliance->id)->count());
    }

    public function test_outgoing_completion_delegates_mark_left_and_retry_is_idempotent(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('Completion Outgoing', 'completion-outgoing', 5503);
        $roster = $this->roster($alliance, $owner, 'Leaving Player', 'leave-5503');
        $destination = Kingdom::query()->create(['number' => 6503, 'status' => 'active']);
        $plan = $this->plan($alliance, $owner, 'Outgoing completion');
        $participant = $this->participant($alliance, $owner, $plan, TransferDirection::Outgoing, [
            'roster_entry_id' => $roster->id,
            'destination_kingdom' => $destination->number,
        ]);
        $this->confirmAndLock($alliance, $owner, $plan, $participant);

        $complete = $this->app->make(CompleteTransferParticipant::class);
        $first = $complete->handle($alliance, $owner, (string) $plan->id, (string) $participant->id);
        $second = $complete->handle($alliance, $owner, (string) $plan->id, (string) $participant->id);

        self::assertSame($first->id, $second->id);
        self::assertSame(RosterState::Left, $roster->refresh()->state);
        self::assertNotNull($roster->left_at);
        self::assertSame(1, TransferCompletion::query()->where('transfer_participant_id', $participant->id)->count());
        self::assertSame(1, $this->eventCount('audit_events', 'event', 'kingdoms.roster_entry_left', $alliance->id));
        self::assertSame(1, $this->eventCount('audit_events', 'event', 'kingdoms.transfer_participant_completed', $alliance->id));
        self::assertSame(1, $this->eventCount('outbox_messages', 'event_type', 'kingdoms.transfer_participant_completed', $alliance->id));
    }

    public function test_staying_completion_records_outcome_without_roster_lifecycle_mutation(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('Completion Staying', 'completion-staying', 5504);
        $roster = $this->roster($alliance, $owner, 'Staying Player', 'stay-5504', [
            'state' => RosterState::Tracked,
            'manager_notes' => 'Stay private note',
        ]);
        $plan = $this->plan($alliance, $owner, 'Staying completion');
        $participant = $this->participant($alliance, $owner, $plan, TransferDirection::Staying, [
            'roster_entry_id' => $roster->id,
        ]);
        $this->confirmAndLock($alliance, $owner, $plan, $participant);
        $rosterEventsBefore = $this->eventCount('audit_events', 'event', 'kingdoms.roster_entry_updated', $alliance->id);

        $completion = $this->app->make(CompleteTransferParticipant::class)->handle(
            $alliance,
            $owner,
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
        [$owner, $alliance, $session] = $this->ownerAlliance('Completion Gates', 'completion-gates', 5505);
        $plan = $this->plan($alliance, $owner, 'Completion gates');
        $participant = $this->participant($alliance, $owner, $plan, TransferDirection::Incoming, [
            'name' => 'Gate Player',
        ]);

        $this->actingAs($owner)->withSession($session)
            ->from('/alliance/transfers/completion')
            ->post($this->completionUrl($plan, $participant))
            ->assertRedirect('/alliance/transfers/completion')
            ->assertSessionHasErrors('completion');

        $this->confirm($alliance, $owner, $participant);
        $this->withSession($session)
            ->from('/alliance/transfers/completion')
            ->post($this->completionUrl($plan, $participant))
            ->assertRedirect('/alliance/transfers/completion')
            ->assertSessionHasErrors('completion');

        $this->app->make(OpenTransferPlan::class)->handle($alliance, $owner, (string) $plan->id);
        $this->app->make(LockTransferPlan::class)->handle($alliance, $owner, (string) $plan->id);
        $this->withSession($session)->post($this->completionUrl($plan, $participant))->assertRedirect();

        self::assertSame(1, TransferCompletion::query()->where('transfer_participant_id', $participant->id)->count());
    }

    public function test_completion_requires_recent_password_confirmation(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('Completion Password', 'completion-password', 5506);
        $plan = $this->plan($alliance, $owner, 'Password gate');
        $participant = $this->participant($alliance, $owner, $plan, TransferDirection::Incoming, [
            'name' => 'Password Player',
        ]);
        $this->confirmAndLock($alliance, $owner, $plan, $participant);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->post($this->completionUrl($plan, $participant))
            ->assertRedirect(route('password.confirm'));

        self::assertSame(0, TransferCompletion::query()->count());
    }

    public function test_close_fails_until_every_active_participant_is_completed_or_withdrawn(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Completion Close', 'completion-close', 5507);
        $plan = $this->plan($alliance, $owner, 'Close gate');
        $active = $this->participant($alliance, $owner, $plan, TransferDirection::Incoming, [
            'name' => 'Must Complete',
        ]);
        $withdrawn = $this->participant($alliance, $owner, $plan, TransferDirection::Incoming, [
            'name' => 'Withdraw First',
        ]);
        $this->confirm($alliance, $owner, $active);
        $this->app->make(WithdrawTransferParticipant::class)->handle(
            $alliance,
            $owner,
            (string) $plan->id,
            (string) $withdrawn->id,
        );
        $this->app->make(OpenTransferPlan::class)->handle($alliance, $owner, (string) $plan->id);
        $this->app->make(LockTransferPlan::class)->handle($alliance, $owner, (string) $plan->id);

        $this->actingAs($owner)->withSession($session)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$plan->id}/close")
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('plan');
        self::assertSame(TransferPlanState::Locked, $plan->refresh()->state);

        $this->withSession($session)->post($this->completionUrl($plan, $active))->assertRedirect();
        $this->withSession($session)->post("/alliance/transfers/{$plan->id}/close")->assertRedirect();
        self::assertSame(TransferPlanState::Closed, $plan->refresh()->state);
    }

    public function test_completion_fails_closed_on_home_kingdom_drift_and_cross_tenant_roster_id(): void
    {
        [$ownerA, $allianceA, $sessionA] = $this->ownerAlliance('Completion Tenant A', 'completion-tenant-a', 5508);
        [$ownerB, $allianceB] = $this->ownerAlliance('Completion Tenant B', 'completion-tenant-b', 5509);
        $foreignRoster = $this->roster($allianceB, $ownerB, 'Foreign Roster', 'foreign-5509');
        $plan = $this->plan($allianceA, $ownerA, 'Tenant completion');
        $participant = $this->participant($allianceA, $ownerA, $plan, TransferDirection::Incoming, [
            'name' => 'Tenant Incoming',
        ]);
        $this->confirmAndLock($allianceA, $ownerA, $plan, $participant);

        $this->actingAs($ownerA)->withSession($sessionA)
            ->post($this->completionUrl($plan, $participant), ['roster_entry_id' => $foreignRoster->id])
            ->assertNotFound();
        self::assertSame(0, TransferCompletion::query()->where('alliance_id', $allianceA->id)->count());

        $newKingdom = Kingdom::query()->create(['number' => 6599, 'status' => 'active']);
        $allianceA->forceFill(['kingdom_id' => $newKingdom->id])->save();
        $this->withSession($sessionA)
            ->from('/alliance/transfers/completion')
            ->post($this->completionUrl($plan, $participant))
            ->assertRedirect('/alliance/transfers/completion')
            ->assertSessionHasErrors('completion');
        self::assertSame(0, TransferCompletion::query()->where('alliance_id', $allianceA->id)->count());
    }

    public function test_explicit_existing_roster_mismatch_rolls_back_without_completion(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Completion Rollback', 'completion-rollback', 5510);
        $existing = $this->roster($alliance, $owner, 'Different Identity', 'other-5510', [
            'manager_notes' => 'UNCHANGED-5510',
        ]);
        $plan = $this->plan($alliance, $owner, 'Rollback completion');
        $source = Kingdom::query()->create(['number' => 6510, 'status' => 'active']);
        $participant = $this->participant($alliance, $owner, $plan, TransferDirection::Incoming, [
            'name' => 'Expected Identity',
            'game_player_id' => 'expected-5510',
            'source_kingdom' => $source->number,
        ]);
        $this->confirmAndLock($alliance, $owner, $plan, $participant);

        $this->actingAs($owner)->withSession($session)
            ->from('/alliance/transfers/completion')
            ->post($this->completionUrl($plan, $participant), ['roster_entry_id' => $existing->id])
            ->assertRedirect('/alliance/transfers/completion')
            ->assertSessionHasErrors('roster_entry_id');

        self::assertSame(0, TransferCompletion::query()->count());
        self::assertSame('UNCHANGED-5510', $existing->refresh()->manager_notes);
        self::assertSame('other-5510', $existing->player->game_player_id);
    }

    public function test_member_sees_safe_completion_summary_but_not_handoff_provenance(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('Completion Privacy', 'completion-privacy', 5511);
        $plan = $this->plan($alliance, $owner, 'Privacy completion');
        $participant = $this->participant($alliance, $owner, $plan, TransferDirection::Incoming, [
            'name' => 'Privacy Player',
        ]);
        $this->confirmAndLock($alliance, $owner, $plan, $participant);
        $this->app->make(CompleteTransferParticipant::class)->handle(
            $alliance,
            $owner,
            (string) $plan->id,
            (string) $participant->id,
        );

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
                ->where('participants.0.name', 'Privacy Player')
                ->where('participants.0.completedAt', fn (mixed $value): bool => is_string($value) && $value !== '')
                ->missing('participants.0.completion'));

        $this->get('/alliance/transfers/completion')->assertForbidden();
    }

    private function participant(
        Alliance $alliance,
        User $owner,
        TransferPlan $plan,
        TransferDirection $direction,
        array $attributes,
    ): TransferParticipant {
        return $this->app->make(SaveTransferParticipant::class)->handle(
            $alliance,
            $owner,
            (string) $plan->id,
            ['direction' => $direction, ...$attributes],
        );
    }

    private function roster(
        Alliance $alliance,
        User $owner,
        string $name,
        string $gamePlayerId,
        array $attributes = [],
    ): AllianceRosterEntry {
        return $this->app->make(SaveRosterEntry::class)->handle($alliance, $owner, [
            'name' => $name,
            'game_player_id' => $gamePlayerId,
            ...$attributes,
        ]);
    }

    private function confirm(Alliance $alliance, User $owner, TransferParticipant $participant): void
    {
        $transition = $this->app->make(TransitionTransferReadiness::class);
        foreach ([
            TransferReadinessState::Preparing,
            TransferReadinessState::Ready,
            TransferReadinessState::Confirmed,
        ] as $state) {
            $transition->handle(
                $alliance,
                $owner,
                (string) $participant->transfer_plan_id,
                (string) $participant->id,
                $state,
            );
        }
    }

    private function confirmAndLock(
        Alliance $alliance,
        User $owner,
        TransferPlan $plan,
        TransferParticipant $participant,
    ): void {
        $this->confirm($alliance, $owner, $participant);
        $this->app->make(OpenTransferPlan::class)->handle($alliance, $owner, (string) $plan->id);
        $this->app->make(LockTransferPlan::class)->handle($alliance, $owner, (string) $plan->id);
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
