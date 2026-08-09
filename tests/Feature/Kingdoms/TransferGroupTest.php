<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\CreateTransferPlan;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\TransferGroup;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransferGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_group_normalizes_destination_and_member_payload_hides_private_fields(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Incoming Groups', 'incoming-groups', 5301);
        $plan = $this->plan($alliance, $owner, 'Incoming group plan');
        [$coordinator, $coordinatorMembership] = $this->member($alliance);

        $group = $this->group(
            $owner,
            $session,
            $plan,
            'Arrival Team',
            'incoming',
            9999,
            $coordinatorMembership->id,
            'Leadership-only arrival note',
        );

        self::assertSame('incoming', $group->direction->value);
        self::assertSame($plan->home_kingdom_id, $group->destination_kingdom_id);
        self::assertSame($coordinatorMembership->id, $group->coordinator_membership_id);
        self::assertSame('Leadership-only arrival note', $group->manager_notes);

        $participant = $this->incoming($owner, $session, $plan, 'Incoming Alpha');
        $this->actingAs($owner)
            ->withSession($session)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}/group", [
                'transfer_group_id' => $group->id,
            ])
            ->assertRedirect();

        $memberSession = [(string) config('identity.active_alliance_session_key') => $alliance->id];
        $this->actingAs($coordinator)
            ->withSession($memberSession)
            ->get('/alliance/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/TransferPlans')
                ->where('groups.0.name', 'Arrival Team')
                ->where('groups.0.direction', 'incoming')
                ->where('groups.0.destinationKingdom', '5301')
                ->where('groups.0.coordinator.name', $coordinator->name)
                ->where('participants.0.group.name', 'Arrival Team')
                ->missing('groups.0.id')
                ->missing('groups.0.managerNotes')
                ->missing('groups.0.coordinatorMembershipId')
                ->missing('participants.0.transferGroupId'));
    }

    public function test_outgoing_group_requires_direction_and_destination_compatibility_and_rejects_staying(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Outgoing Groups', 'outgoing-groups', 5302);
        $plan = $this->plan($alliance, $owner, 'Outgoing group plan');
        $matching = $this->outgoing($alliance, $owner, $session, $plan, 'Bravo', 5399);
        $other = $this->outgoing($alliance, $owner, $session, $plan, 'Charlie', 5398);
        $staying = $this->staying($alliance, $owner, $session, $plan, 'Delta');
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
        self::assertNull($other->refresh()->transfer_group_id);

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$plan->id}/participants/{$staying->id}/group", [
                'transfer_group_id' => $group->id,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('transfer_group_id');
        self::assertNull($staying->refresh()->transfer_group_id);
    }

    public function test_group_update_revalidates_assigned_participants_and_rolls_back_incompatible_destination(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Group Revalidation', 'group-revalidation', 5303);
        $plan = $this->plan($alliance, $owner, 'Revalidation plan');
        $participant = $this->outgoing($alliance, $owner, $session, $plan, 'Echo', 5397);
        $group = $this->group($owner, $session, $plan, 'Westbound', 'outgoing', 5397);

        $this->actingAs($owner)
            ->withSession($session)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}/group", [
                'transfer_group_id' => $group->id,
            ])
            ->assertRedirect();

        $originalDestination = $group->destination_kingdom_id;

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$plan->id}/groups/{$group->id}", [
                'name' => 'Westbound',
                'direction' => 'outgoing',
                'destination_kingdom' => 5396,
                'coordinator_membership_id' => null,
                'manager_notes' => 'Must roll back',
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('destination_kingdom');

        $group->refresh();
        self::assertSame($originalDestination, $group->destination_kingdom_id);
        self::assertNull($group->manager_notes);
    }

    public function test_grouped_participant_edit_cannot_break_group_destination_contract(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Participant Revalidation', 'participant-revalidation', 5304);
        $plan = $this->plan($alliance, $owner, 'Participant revalidation plan');
        $participant = $this->outgoing($alliance, $owner, $session, $plan, 'Foxtrot', 5395);
        $group = $this->group($owner, $session, $plan, 'Southbound', 'outgoing', 5395);
        $roster = AllianceRosterEntry::query()->findOrFail($participant->roster_entry_id);

        $this->actingAs($owner)
            ->withSession($session)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}/group", [
                'transfer_group_id' => $group->id,
            ])
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

        $destinationId = Kingdom::query()->where('number', 5395)->value('id');
        self::assertSame($destinationId, $participant->refresh()->destination_kingdom_id);
    }

    public function test_coordinator_is_same_alliance_only_and_never_receives_management_permission(): void
    {
        [$ownerA, $allianceA, $sessionA] = $this->ownerAlliance('Coordinator A', 'coordinator-a', 5305);
        [$ownerB, $allianceB] = $this->ownerAlliance('Coordinator B', 'coordinator-b', 5306);
        $planA = $this->plan($allianceA, $ownerA, 'Coordinator plan');
        $membershipB = AllianceMembership::query()
            ->where('alliance_id', $allianceB->id)
            ->where('user_id', $ownerB->id)
            ->sole();

        $this->actingAs($ownerA)
            ->withSession($sessionA)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$planA->id}/groups", [
                'name' => 'Foreign Coordinator',
                'direction' => 'incoming',
                'coordinator_membership_id' => $membershipB->id,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('coordinator_membership_id');

        [$coordinator, $membershipA] = $this->member($allianceA);
        $group = $this->group(
            $ownerA,
            $sessionA,
            $planA,
            'Workflow Only',
            'incoming',
            null,
            $membershipA->id,
        );
        $participant = $this->incoming($ownerA, $sessionA, $planA, 'Golf');
        $coordinatorSession = $this->confirmedSession($allianceA->id);

        $this->actingAs($coordinator)
            ->withSession($coordinatorSession)
            ->post("/alliance/transfers/{$planA->id}/groups", [
                'name' => 'Unauthorized',
                'direction' => 'incoming',
            ])
            ->assertForbidden();

        $this->withSession($coordinatorSession)
            ->patch("/alliance/transfers/{$planA->id}/participants/{$participant->id}/group", [
                'transfer_group_id' => $group->id,
            ])
            ->assertForbidden();
        self::assertNull($participant->refresh()->transfer_group_id);
    }

    public function test_group_mutation_requires_recent_password_confirmation(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('Group Password', 'group-password', 5310);
        $plan = $this->plan($alliance, $owner, 'Password plan');

        $this->actingAs($owner)
            ->withSession([(string) config('identity.active_alliance_session_key') => $alliance->id])
            ->post("/alliance/transfers/{$plan->id}/groups", [
                'name' => 'Needs Password',
                'direction' => 'incoming',
            ])
            ->assertRedirect(route('password.confirm'));

        self::assertSame(
            0,
            TransferGroup::query()->where('transfer_plan_id', $plan->id)->count(),
        );
    }

    public function test_cross_alliance_group_id_locked_plan_and_home_drift_fail_closed(): void
    {
        [$ownerA, $allianceA, $sessionA] = $this->ownerAlliance('Group Tenant A', 'group-tenant-a', 5307);
        [$ownerB, $allianceB, $sessionB] = $this->ownerAlliance('Group Tenant B', 'group-tenant-b', 5308);
        $planA = $this->plan($allianceA, $ownerA, 'Plan A');
        $planB = $this->plan($allianceB, $ownerB, 'Plan B');
        $groupB = $this->group($ownerB, $sessionB, $planB, 'Foreign Group', 'incoming');
        $participantA = $this->incoming($ownerA, $sessionA, $planA, 'Hotel');

        $this->actingAs($ownerA)
            ->withSession($sessionA)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$planA->id}/participants/{$participantA->id}/group", [
                'transfer_group_id' => $groupB->id,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('transfer_group_id');

        $groupA = $this->group($ownerA, $sessionA, $planA, 'Mutable Group', 'incoming');
        $this->withSession($sessionA)
            ->post("/alliance/transfers/{$planA->id}/open")
            ->assertRedirect();
        $this->withSession($sessionA)
            ->post("/alliance/transfers/{$planA->id}/lock")
            ->assertRedirect();
        $this->withSession($sessionA)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$planA->id}/groups/{$groupA->id}", [
                'name' => 'Locked Rename',
                'direction' => 'incoming',
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('group');

        $driftPlan = $this->plan($allianceA, $ownerA, 'Drift Plan');
        $driftGroup = $this->group($ownerA, $sessionA, $driftPlan, 'Drift Group', 'incoming');
        $newKingdom = Kingdom::query()->create(['number' => 5393, 'status' => 'active']);
        $allianceA->forceFill(['kingdom_id' => $newKingdom->id])->save();

        $this->withSession($sessionA)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$driftPlan->id}/groups/{$driftGroup->id}", [
                'name' => 'Drift Rename',
                'direction' => 'incoming',
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('group');
    }

    public function test_archive_requires_unassignment_is_idempotent_and_events_exclude_private_notes(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Archive Group', 'archive-group', 5309);
        $plan = $this->plan($alliance, $owner, 'Archive plan');
        $group = $this->group(
            $owner,
            $session,
            $plan,
            'Archive Me',
            'incoming',
            null,
            null,
            'Secret group note',
        );
        $participant = $this->incoming($owner, $session, $plan, 'India');

        $this->actingAs($owner)
            ->withSession($session)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}/group", [
                'transfer_group_id' => $group->id,
            ])
            ->assertRedirect();

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$plan->id}/groups/{$group->id}/archive")
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('group');

        $this->withSession($session)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}/group", [
                'transfer_group_id' => null,
            ])
            ->assertRedirect();
        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/groups/{$group->id}/archive")
            ->assertRedirect();

        $auditCount = $this->eventCount(
            'audit_events',
            'event',
            'kingdoms.transfer_group_archived',
            $alliance->id,
        );
        $outboxCount = $this->eventCount(
            'outbox_messages',
            'event_type',
            'kingdoms.transfer_group_archived',
            $alliance->id,
        );

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/groups/{$group->id}/archive")
            ->assertRedirect();

        self::assertSame(
            $auditCount,
            $this->eventCount('audit_events', 'event', 'kingdoms.transfer_group_archived', $alliance->id),
        );
        self::assertSame(
            $outboxCount,
            $this->eventCount(
                'outbox_messages',
                'event_type',
                'kingdoms.transfer_group_archived',
                $alliance->id,
            ),
        );
        self::assertSame('archived', $group->refresh()->state->value);
        self::assertFalse(DB::table('audit_events')
            ->where('alliance_id', $alliance->id)
            ->whereRaw('metadata::text like ?', ['%Secret group note%'])
            ->exists());
        self::assertFalse(DB::table('outbox_messages')
            ->where('alliance_id', $alliance->id)
            ->whereRaw('payload::text like ?', ['%Secret group note%'])
            ->exists());
    }

    /** @return array{0: User, 1: Alliance, 2: array<string, mixed>} */
    private function ownerAlliance(string $name, string $slug, int $kingdom): array
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, $name, $slug, $kingdom);

        return [$owner, $alliance, $this->confirmedSession($alliance->id)];
    }

    /** @return array{0: User, 1: AllianceMembership} */
    private function member(Alliance $alliance): array
    {
        $user = User::factory()->create();
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $role = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::Member->value)
            ->sole();
        $membership->roles()->attach($role->id, ['alliance_id' => $alliance->id]);

        return [$user, $membership];
    }

    private function plan(Alliance $alliance, User $owner, string $label): TransferPlan
    {
        return $this->app->make(CreateTransferPlan::class)->handle(
            $alliance,
            $owner,
            ['label' => $label],
        );
    }

    private function group(
        User $owner,
        array $session,
        TransferPlan $plan,
        string $name,
        string $direction,
        ?int $destination = null,
        ?string $coordinatorMembershipId = null,
        ?string $managerNotes = null,
    ): TransferGroup {
        $this->actingAs($owner)
            ->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/groups", [
                'name' => $name,
                'direction' => $direction,
                'destination_kingdom' => $destination,
                'coordinator_membership_id' => $coordinatorMembershipId,
                'manager_notes' => $managerNotes,
            ])
            ->assertRedirect();

        return TransferGroup::query()
            ->where('transfer_plan_id', $plan->id)
            ->where('name', $name)
            ->sole();
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

    private function outgoing(
        Alliance $alliance,
        User $owner,
        array $session,
        TransferPlan $plan,
        string $name,
        int $destination,
    ): TransferParticipant {
        $roster = $this->roster($alliance, $owner, $name);
        $this->actingAs($owner)
            ->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'outgoing',
                'roster_entry_id' => $roster->id,
                'destination_kingdom' => $destination,
            ])
            ->assertRedirect();

        return TransferParticipant::query()
            ->where('transfer_plan_id', $plan->id)
            ->where('roster_entry_id', $roster->id)
            ->sole();
    }

    private function staying(
        Alliance $alliance,
        User $owner,
        array $session,
        TransferPlan $plan,
        string $name,
    ): TransferParticipant {
        $roster = $this->roster($alliance, $owner, $name);
        $this->actingAs($owner)
            ->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/participants", [
                'direction' => 'staying',
                'roster_entry_id' => $roster->id,
            ])
            ->assertRedirect();

        return TransferParticipant::query()
            ->where('transfer_plan_id', $plan->id)
            ->where('roster_entry_id', $roster->id)
            ->sole();
    }

    private function roster(Alliance $alliance, User $owner, string $name): AllianceRosterEntry
    {
        return $this->app->make(SaveRosterEntry::class)->handle($alliance, $owner, [
            'name' => $name,
            'state' => RosterState::Active,
        ]);
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
