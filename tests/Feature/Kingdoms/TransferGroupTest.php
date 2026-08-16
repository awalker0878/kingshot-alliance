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
use App\Contexts\GameWorld\Enums\KingdomStatus;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Actions\CreateTransferPlan;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Domain\Kingdoms\Models\TransferGroup;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransferGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_group_uses_home_destination_and_player_coordinator_while_member_payload_hides_private_fields(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Incoming Groups', 'incoming-groups', 5301);
        $plan = $this->plan($alliance, $ownerPlayer, 'Incoming group plan');
        [$coordinatorUser, $coordinatorPlayer] = $this->member($alliance, 'arrival-coordinator');

        $group = $this->group(
            $owner,
            $session,
            $plan,
            'Arrival Team',
            'incoming',
            9999,
            $coordinatorPlayer->id,
            'Leadership-only arrival note',
        );

        self::assertSame('incoming', $group->direction->value);
        self::assertSame($plan->home_kingdom_id, $group->destination_kingdom_id);
        self::assertSame($coordinatorPlayer->id, $group->coordinator_player_id);
        self::assertSame('Leadership-only arrival note', $group->manager_notes);

        $participant = $this->incoming($owner, $session, $plan, 'Incoming Alpha', 5381);
        $this->actingAs($owner)
            ->withSession($session)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}/group", [
                'transfer_group_id' => $group->id,
            ])
            ->assertRedirect();

        $this->actingAs($coordinatorUser)
            ->withSession($this->activeSession($coordinatorPlayer->id))
            ->get('/alliance/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/TransferPlans')
                ->where('groups.0.name', 'Arrival Team')
                ->where('groups.0.direction', 'incoming')
                ->where('groups.0.destinationKingdom', '5301')
                ->where('groups.0.coordinator.name', $coordinatorPlayer->current_name)
                ->where('participants.0.group.name', 'Arrival Team')
                ->missing('groups.0.id')
                ->missing('groups.0.managerNotes')
                ->missing('groups.0.coordinatorPlayerId')
                ->missing('participants.0.transferGroupId'));
    }

    public function test_outgoing_group_enforces_direction_and_destination_compatibility_and_rejects_staying(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Outgoing Groups', 'outgoing-groups', 5302);
        $plan = $this->plan($alliance, $ownerPlayer, 'Outgoing group plan');
        $matching = $this->outgoing($alliance, $owner, $ownerPlayer, $session, $plan, 'Bravo', 5399);
        $other = $this->outgoing($alliance, $owner, $ownerPlayer, $session, $plan, 'Charlie', 5398);
        $staying = $this->staying($alliance, $owner, $ownerPlayer, $session, $plan, 'Delta');
        $group = $this->group($owner, $session, $plan, 'Kingdom 5399', 'outgoing', 5399);

        $this->actingAs($owner)
            ->withSession($session)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$matching->id}/group", [
                'transfer_group_id' => $group->id,
            ])
            ->assertRedirect();
        self::assertSame($group->id, $matching->refresh()->transfer_group_id);

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$plan->id}/participants/{$other->id}/group", [
                'transfer_group_id' => $group->id,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('transfer_group_id');

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$plan->id}/participants/{$staying->id}/group", [
                'transfer_group_id' => $group->id,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('transfer_group_id');
    }

    public function test_group_update_revalidates_assigned_participants_and_rolls_back_incompatible_destination(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Group Revalidation', 'group-revalidation', 5303);
        $plan = $this->plan($alliance, $ownerPlayer, 'Revalidation plan');
        $participant = $this->outgoing($alliance, $owner, $ownerPlayer, $session, $plan, 'Echo', 5397);
        $group = $this->group($owner, $session, $plan, 'Westbound', 'outgoing', 5397);

        $this->actingAs($owner)->withSession($session)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}/group", ['transfer_group_id' => $group->id])
            ->assertRedirect();

        $originalDestination = $group->destination_kingdom_id;
        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$plan->id}/groups/{$group->id}", [
                'name' => 'Westbound',
                'direction' => 'outgoing',
                'destination_kingdom' => 5396,
                'coordinator_player_id' => null,
                'manager_notes' => 'Must roll back',
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('destination_kingdom');

        self::assertSame($originalDestination, $group->refresh()->destination_kingdom_id);
        self::assertNull($group->manager_notes);
    }

    public function test_grouped_participant_edit_cannot_break_group_destination_contract(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Participant Revalidation', 'participant-revalidation', 5304);
        $plan = $this->plan($alliance, $ownerPlayer, 'Participant revalidation plan');
        $participant = $this->outgoing($alliance, $owner, $ownerPlayer, $session, $plan, 'Foxtrot', 5395);
        $group = $this->group($owner, $session, $plan, 'Southbound', 'outgoing', 5395);
        $roster = AllianceRosterEntry::query()->findOrFail($participant->roster_entry_id);

        $this->actingAs($owner)->withSession($session)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}/group", ['transfer_group_id' => $group->id])
            ->assertRedirect();

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}", [
                'direction' => 'outgoing',
                'roster_entry_id' => $roster->id,
                'destination_kingdom' => 5394,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('destination_kingdom');

        self::assertSame(Kingdom::query()->where('number', 5395)->value('id'), $participant->refresh()->destination_kingdom_id);
    }

    public function test_coordinator_is_same_alliance_player_only_and_never_receives_management_permission(): void
    {
        [$ownerA, $playerA, $allianceA, $sessionA] = $this->ownerAlliance('Coordinator A', 'coordinator-a', 5305);
        [, $playerB] = $this->ownerAlliance('Coordinator B', 'coordinator-b', 5306);
        $planA = $this->plan($allianceA, $playerA, 'Coordinator plan');

        $this->actingAs($ownerA)
            ->withSession($sessionA)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$planA->id}/groups", [
                'name' => 'Foreign Coordinator',
                'direction' => 'incoming',
                'coordinator_player_id' => $playerB->id,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('coordinator_player_id');

        [$coordinatorUser, $coordinatorPlayer] = $this->member($allianceA, 'workflow-coordinator');
        $group = $this->group($ownerA, $sessionA, $planA, 'Workflow Only', 'incoming', null, $coordinatorPlayer->id);
        $participant = $this->incoming($ownerA, $sessionA, $planA, 'Golf', 5385);
        $coordinatorSession = $this->confirmedSession($coordinatorPlayer->id);

        $this->actingAs($coordinatorUser)
            ->withSession($coordinatorSession)
            ->post("/alliance/transfers/{$planA->id}/groups", [
                'name' => 'Unauthorized',
                'direction' => 'incoming',
            ])
            ->assertForbidden();

        $this->withSession($coordinatorSession)
            ->patch("/alliance/transfers/{$planA->id}/participants/{$participant->id}/group", ['transfer_group_id' => $group->id])
            ->assertForbidden();
        self::assertNull($participant->refresh()->transfer_group_id);
    }

    public function test_group_mutation_requires_recent_password_confirmation(): void
    {
        [$owner, $ownerPlayer, $alliance] = $this->ownerAlliance('Group Password', 'group-password', 5310);
        $plan = $this->plan($alliance, $ownerPlayer, 'Password plan');

        $this->actingAs($owner)
            ->withSession($this->activeSession($ownerPlayer->id))
            ->post("/alliance/transfers/{$plan->id}/groups", [
                'name' => 'Needs Password',
                'direction' => 'incoming',
            ])
            ->assertRedirect(route('password.confirm'));

        self::assertSame(0, TransferGroup::query()->where('transfer_plan_id', $plan->id)->count());
    }

    public function test_cross_alliance_group_id_locked_plan_and_mismatched_plan_home_fail_closed(): void
    {
        [$ownerA, $playerA, $allianceA, $sessionA] = $this->ownerAlliance('Group Tenant A', 'group-tenant-a', 5307);
        [$ownerB, $playerB, $allianceB, $sessionB] = $this->ownerAlliance('Group Tenant B', 'group-tenant-b', 5308);
        $planA = $this->plan($allianceA, $playerA, 'Plan A');
        $planB = $this->plan($allianceB, $playerB, 'Plan B');
        $groupB = $this->group($ownerB, $sessionB, $planB, 'Foreign Group', 'incoming');
        $participantA = $this->incoming($ownerA, $sessionA, $planA, 'Hotel', 5387);

        $this->actingAs($ownerA)->withSession($sessionA)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$planA->id}/participants/{$participantA->id}/group", ['transfer_group_id' => $groupB->id])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('transfer_group_id');

        $groupA = $this->group($ownerA, $sessionA, $planA, 'Mutable Group', 'incoming');
        $this->withSession($sessionA)->post("/alliance/transfers/{$planA->id}/open")->assertRedirect();
        $this->withSession($sessionA)->post("/alliance/transfers/{$planA->id}/lock")->assertRedirect();
        $this->withSession($sessionA)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$planA->id}/groups/{$groupA->id}", ['name' => 'Locked Rename', 'direction' => 'incoming'])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('group');

        $mismatchPlan = $this->plan($allianceA, $playerA, 'Mismatch Plan');
        $mismatchGroup = $this->group($ownerA, $sessionA, $mismatchPlan, 'Mismatch Group', 'incoming');
        $otherKingdom = Kingdom::query()->create(['number' => 5393, 'status' => KingdomStatus::Active]);
        $mismatchPlan->forceFill(['home_kingdom_id' => $otherKingdom->id])->save();

        $this->withSession($sessionA)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$mismatchPlan->id}/groups/{$mismatchGroup->id}", ['name' => 'Mismatch Rename', 'direction' => 'incoming'])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('group');
    }

    public function test_archive_requires_unassignment_is_idempotent_and_events_exclude_private_notes(): void
    {
        [$owner, $ownerPlayer, $alliance, $session] = $this->ownerAlliance('Archive Group', 'archive-group', 5309);
        $plan = $this->plan($alliance, $ownerPlayer, 'Archive plan');
        $group = $this->group($owner, $session, $plan, 'Archive Me', 'incoming', null, null, 'Secret group note');
        $participant = $this->incoming($owner, $session, $plan, 'India', 5389);

        $this->actingAs($owner)->withSession($session)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}/group", ['transfer_group_id' => $group->id])
            ->assertRedirect();

        $this->withSession($session)->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$plan->id}/groups/{$group->id}/archive")
            ->assertRedirect('/alliance/transfers/manage')->assertSessionHasErrors('group');

        $this->withSession($session)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}/group", ['transfer_group_id' => null])
            ->assertRedirect();
        $this->withSession($session)->post("/alliance/transfers/{$plan->id}/groups/{$group->id}/archive")->assertRedirect();

        $auditCount = $this->eventCount('audit_events', 'event', 'kingdoms.transfer_group_archived', $alliance->id);
        $outboxCount = $this->eventCount('outbox_messages', 'event_type', 'kingdoms.transfer_group_archived', $alliance->id);
        $this->withSession($session)->post("/alliance/transfers/{$plan->id}/groups/{$group->id}/archive")->assertRedirect();

        self::assertSame($auditCount, $this->eventCount('audit_events', 'event', 'kingdoms.transfer_group_archived', $alliance->id));
        self::assertSame($outboxCount, $this->eventCount('outbox_messages', 'event_type', 'kingdoms.transfer_group_archived', $alliance->id));
        self::assertSame('archived', $group->refresh()->state->value);
        self::assertFalse(DB::table('audit_events')->where('alliance_id', $alliance->id)->whereRaw('metadata::text like ?', ['%Secret group note%'])->exists());
        self::assertFalse(DB::table('outbox_messages')->where('alliance_id', $alliance->id)->whereRaw('payload::text like ?', ['%Secret group note%'])->exists());
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

    /** @return array{0: User, 1: Player} */
    private function member(Alliance $alliance, string $gamePlayerId): array
    {
        $user = User::factory()->create();
        $player = $this->player($user, $alliance->kingdom, $gamePlayerId, $gamePlayerId.' Player');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $player->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        return [$user, $player];
    }

    private function plan(Alliance $alliance, Player $actor, string $label): TransferPlan
    {
        return $this->app->make(CreateTransferPlan::class)->handle($alliance, $actor, ['label' => $label]);
    }

    private function group(
        User $owner,
        array $session,
        TransferPlan $plan,
        string $name,
        string $direction,
        ?int $destination = null,
        ?string $coordinatorPlayerId = null,
        ?string $managerNotes = null,
    ): TransferGroup {
        $this->actingAs($owner)->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/groups", [
                'name' => $name,
                'direction' => $direction,
                'destination_kingdom' => $destination,
                'coordinator_player_id' => $coordinatorPlayerId,
                'manager_notes' => $managerNotes,
            ])->assertRedirect();

        return TransferGroup::query()->where('transfer_plan_id', $plan->id)->where('name', $name)->sole();
    }

    private function incoming(User $owner, array $session, TransferPlan $plan, string $name, int $sourceKingdom): TransferParticipant
    {
        $this->actingAs($owner)->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'incoming',
                'name' => $name,
                'source_kingdom' => $sourceKingdom,
            ])->assertRedirect();

        return TransferParticipant::query()->where('transfer_plan_id', $plan->id)->where('observed_name', $name)->sole();
    }

    private function outgoing(
        Alliance $alliance,
        User $owner,
        Player $actor,
        array $session,
        TransferPlan $plan,
        string $name,
        int $destination,
    ): TransferParticipant {
        $roster = $this->roster($alliance, $actor, $name);
        $this->actingAs($owner)->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'outgoing',
                'roster_entry_id' => $roster->id,
                'destination_kingdom' => $destination,
            ])->assertRedirect();

        return TransferParticipant::query()->where('transfer_plan_id', $plan->id)->where('roster_entry_id', $roster->id)->sole();
    }

    private function staying(
        Alliance $alliance,
        User $owner,
        Player $actor,
        array $session,
        TransferPlan $plan,
        string $name,
    ): TransferParticipant {
        $roster = $this->roster($alliance, $actor, $name);
        $this->actingAs($owner)->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", ['direction' => 'staying', 'roster_entry_id' => $roster->id])
            ->assertRedirect();

        return TransferParticipant::query()->where('transfer_plan_id', $plan->id)->where('roster_entry_id', $roster->id)->sole();
    }

    private function roster(Alliance $alliance, Player $actor, string $name): AllianceRosterEntry
    {
        return $this->app->make(SaveRosterEntry::class)->handle($alliance, $actor, ['name' => $name, 'state' => RosterState::Active]);
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
