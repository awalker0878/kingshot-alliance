<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\Domain\Kingdoms\Actions\AssignTransferParticipantGroup;
use App\Domain\Kingdoms\Actions\CloseTransferPlan;
use App\Domain\Kingdoms\Actions\CompleteTransferParticipant;
use App\Domain\Kingdoms\Actions\CreateTransferBlocker;
use App\Domain\Kingdoms\Actions\CreateTransferPlan;
use App\Domain\Kingdoms\Actions\LockTransferPlan;
use App\Domain\Kingdoms\Actions\OpenTransferPlan;
use App\Domain\Kingdoms\Actions\ResolveTransferBlocker;
use App\Domain\Kingdoms\Actions\SaveTransferGroup;
use App\Domain\Kingdoms\Actions\SaveTransferParticipant;
use App\Domain\Kingdoms\Actions\TransitionTransferReadiness;
use App\Domain\Kingdoms\Enums\TransferDirection;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Enums\TransferReadinessState;
use App\Domain\Kingdoms\Models\TransferCompletion;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Kingdoms\Models\TransferReadinessTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransferIncrementAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_increment_works_from_cycle_through_group_readiness_completion_and_tenant_isolation(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $otherOwner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 5601, 'status' => 'active']);
        $ownerPlayer = $this->player($owner, $kingdom, 'accepted-transfers-r5', 'Accepted Transfers R5');
        $memberPlayer = $this->player($member, $kingdom, 'accepted-transfers-member', (string) $member->name);
        $otherOwnerPlayer = $this->player($otherOwner, $kingdom, 'other-accepted-transfers-r5', 'Other Accepted Transfers R5');
        $createAlliance = $this->app->make(CreateAlliance::class);
        $alliance = $createAlliance->handle($ownerPlayer, 'Accepted Transfers', 'accepted-transfers');
        $otherAlliance = $createAlliance->handle($otherOwnerPlayer, 'Other Accepted Transfers', 'other-accepted-transfers');
        $this->addMember($alliance, $memberPlayer);
        $confirmed = $this->confirmedSession($ownerPlayer->id);

        self::assertSame($alliance->kingdom_id, $otherAlliance->kingdom_id);

        $outgoingRoster = $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Outgoing Alpha',
            'game_player_id' => 'k2-outgoing-1',
            'manager_notes' => 'Private outgoing roster note',
        ]);
        $stayingRoster = $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Staying Bravo',
            'game_player_id' => 'k2-staying-1',
            'manager_notes' => 'Private staying roster note',
        ]);

        $plan = $this->app->make(CreateTransferPlan::class)->handle($alliance, $ownerPlayer, [
            'label' => 'Accepted transfer cycle',
        ]);
        $source = Kingdom::query()->create(['number' => 5701, 'status' => 'active']);
        $destination = Kingdom::query()->create(['number' => 5699, 'status' => 'active']);

        $outgoing = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Outgoing, [
            'roster_entry_id' => $outgoingRoster->id,
            'destination_kingdom' => $destination->number,
            'manager_notes' => 'Private outgoing transfer note',
        ]);
        $staying = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Staying, [
            'roster_entry_id' => $stayingRoster->id,
            'manager_notes' => 'Private staying transfer note',
        ]);
        $incoming = $this->participant($alliance, $ownerPlayer, $plan, TransferDirection::Incoming, [
            'name' => 'Incoming Charlie',
            'game_player_id' => 'k2-incoming-1',
            'source_kingdom' => $source->number,
            'manager_notes' => 'Private incoming transfer note',
        ]);

        $group = $this->app->make(SaveTransferGroup::class)->handle($alliance, $ownerPlayer, (string) $plan->id, [
            'name' => 'Outbound 5699',
            'direction' => TransferDirection::Outgoing,
            'destination_kingdom' => $destination->number,
            'coordinator_player_id' => $memberPlayer->id,
            'manager_notes' => 'Private group note',
        ]);
        $this->app->make(AssignTransferParticipantGroup::class)->handle(
            $alliance,
            $ownerPlayer,
            (string) $plan->id,
            (string) $outgoing->id,
            (string) $group->id,
        );

        $blocker = $this->app->make(CreateTransferBlocker::class)->handle(
            $alliance,
            $ownerPlayer,
            (string) $plan->id,
            (string) $incoming->id,
            'Waiting for the transfer window',
            'Private scheduling detail for managers only',
        );
        $transition = $this->app->make(TransitionTransferReadiness::class);
        $transition->handle(
            $alliance,
            $ownerPlayer,
            (string) $plan->id,
            (string) $incoming->id,
            TransferReadinessState::Blocked,
        );
        $this->app->make(ResolveTransferBlocker::class)->handle(
            $alliance,
            $ownerPlayer,
            (string) $plan->id,
            (string) $incoming->id,
            (string) $blocker->id,
        );
        $this->confirm($alliance, $ownerPlayer, $incoming, true);
        $this->confirm($alliance, $ownerPlayer, $outgoing);
        $this->confirm($alliance, $ownerPlayer, $staying);

        self::assertSame(TransferReadinessState::Confirmed, $incoming->refresh()->readiness_state);
        self::assertGreaterThanOrEqual(
            9,
            TransferReadinessTransition::query()->where('transfer_plan_id', $plan->id)->count(),
        );
        self::assertSame(0, TransferCompletion::query()->count(), 'Confirmed is planning-only before explicit completion.');

        $this->actingAs($member)
            ->withSession($this->activeSession($memberPlayer->id))
            ->get('/alliance/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/TransferPlans')
                ->where('plan.label', 'Accepted transfer cycle')
                ->has('participants', 3)
                ->where('groups.0.name', 'Outbound 5699')
                ->where('groups.0.coordinator.name', $member->name)
                ->missing('groups.0.managerNotes')
                ->missing('groups.0.coordinatorPlayerId')
                ->missing('participants.0.managerNotes')
                ->missing('participants.1.managerNotes')
                ->missing('participants.2.managerNotes'));

        $this->get('/alliance/transfers/manage')->assertForbidden();
        $this->get('/alliance/transfers/readiness')->assertForbidden();
        $this->get('/alliance/transfers/completion')->assertForbidden();

        $this->withSession($this->confirmedSession($memberPlayer->id))
            ->patch("/alliance/transfers/{$plan->id}/groups/{$group->id}", [
                'name' => 'Coordinator cannot mutate',
                'direction' => 'outgoing',
                'destination_kingdom' => $destination->number,
            ])
            ->assertForbidden();

        $this->actingAs($owner);
        $this->app->make(OpenTransferPlan::class)->handle($alliance, $ownerPlayer, (string) $plan->id);
        $this->app->make(LockTransferPlan::class)->handle($alliance, $ownerPlayer, (string) $plan->id);
        self::assertSame(TransferPlanState::Locked, $plan->refresh()->state);

        $snapshotsBefore = PlayerSnapshot::query()->count();
        $complete = $this->app->make(CompleteTransferParticipant::class);
        $incomingCompletion = $complete->handle($alliance, $ownerPlayer, (string) $plan->id, (string) $incoming->id);
        $outgoingCompletion = $complete->handle($alliance, $ownerPlayer, (string) $plan->id, (string) $outgoing->id);
        $stayingCompletion = $complete->handle($alliance, $ownerPlayer, (string) $plan->id, (string) $staying->id);
        $outgoingRetry = $complete->handle($alliance, $ownerPlayer, (string) $plan->id, (string) $outgoing->id);

        self::assertSame($outgoingCompletion->id, $outgoingRetry->id);
        self::assertSame(3, TransferCompletion::query()->where('transfer_plan_id', $plan->id)->count());
        self::assertSame($snapshotsBefore, PlayerSnapshot::query()->count());

        $incomingRoster = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('observed_name', 'Incoming Charlie')
            ->sole();
        self::assertSame($incomingCompletion->roster_entry_id, $incomingRoster->id);
        self::assertSame($alliance->kingdom_id, $incomingRoster->player->current_kingdom_id);
        self::assertSame('k2-incoming-1', $incomingRoster->player->game_player_id);
        self::assertSame(RosterState::Left, $outgoingRoster->refresh()->state);
        self::assertNotNull($outgoingRoster->left_at);
        self::assertSame($outgoingRoster->id, $outgoingCompletion->roster_entry_id);
        self::assertSame(RosterState::Active, $stayingRoster->refresh()->state);
        self::assertNull($stayingRoster->left_at);
        self::assertSame($stayingRoster->id, $stayingCompletion->roster_entry_id);

        $this->actingAs($member)
            ->withSession($this->activeSession($memberPlayer->id))
            ->get('/alliance/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('participants', 3)
                ->where('participants.0.completedAt', fn (mixed $value): bool => is_string($value) && $value !== '')
                ->where('participants.1.completedAt', fn (mixed $value): bool => is_string($value) && $value !== '')
                ->where('participants.2.completedAt', fn (mixed $value): bool => is_string($value) && $value !== '')
                ->missing('participants.0.completion')
                ->missing('participants.1.completion')
                ->missing('participants.2.completion'));

        $this->actingAs($owner);
        $this->app->make(CloseTransferPlan::class)->handle($alliance, $ownerPlayer, (string) $plan->id);
        self::assertSame(TransferPlanState::Closed, $plan->refresh()->state);

        $this->actingAs($otherOwner)
            ->withSession($this->confirmedSession($otherOwnerPlayer->id))
            ->post("/alliance/transfers/{$plan->id}/cancel")
            ->assertNotFound();

        self::assertSame(
            3,
            $this->eventCount('audit_events', 'event', 'kingdoms.transfer_participant_completed', $alliance->id),
        );
        self::assertSame(
            3,
            $this->eventCount('outbox_messages', 'event_type', 'kingdoms.transfer_participant_completed', $alliance->id),
        );
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'event' => 'kingdoms.transfer_group_created',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'kingdoms.transfer_blocker_resolved',
        ]);
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

    private function confirm(
        Alliance $alliance,
        Player $actor,
        TransferParticipant $participant,
        bool $fromBlocked = false,
    ): void {
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

        if ($fromBlocked) {
            self::assertSame(TransferReadinessState::Confirmed, $participant->refresh()->readiness_state);
        }
    }

    private function addMember(Alliance $alliance, Player $player): AllianceMembership
    {
        return AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $player->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
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

    private function eventCount(string $table, string $column, string $event, string $allianceId): int
    {
        return (int) DB::table($table)
            ->where('alliance_id', $allianceId)
            ->where($column, $event)
            ->count();
    }
}
