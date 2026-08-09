<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\CreateTransferPlan;
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

    public function test_manager_can_create_group_with_multiple_coordinators_and_assign_participant(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Grouped Transfer', 'grouped-transfer', 5301);
        $plan = $this->plan($alliance, $owner, 'Grouped plan');
        [$coordinator, $coordinatorMembership] = $this->member($alliance);
        $ownerMembership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $owner->id)
            ->sole();

        $this->actingAs($owner)
            ->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/groups", [
                'name' => 'Arrival Team',
                'coordinator_membership_ids' => [$ownerMembership->id, $coordinatorMembership->id],
            ])
            ->assertRedirect();

        $group = TransferGroup::query()->sole();
        self::assertSame('Arrival Team', $group->name);
        self::assertSame(2, DB::table('transfer_group_coordinators')
            ->where('transfer_group_id', $group->id)
            ->count());

        $participant = $this->incoming($owner, $session, $plan, 'Incoming Alpha');

        $this->withSession($session)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}/group", [
                'transfer_group_id' => $group->id,
            ])
            ->assertRedirect();

        self::assertSame($group->id, $participant->refresh()->transfer_group_id);
        self::assertSame(1, $this->eventCount(
            'audit_events',
            'event',
            'kingdoms.transfer_group_created',
            $alliance->id,
        ));
        self::assertSame(1, $this->eventCount(
            'outbox_messages',
            'event_type',
            'kingdoms.transfer_participant_group_changed',
            $alliance->id,
        ));

        $this->actingAs($coordinator)
            ->withSession([(string) config('identity.active_alliance_session_key') => $alliance->id])
            ->get('/alliance/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/TransferPlans')
                ->where('groups.0.name', 'Arrival Team')
                ->where('participants.0.group.name', 'Arrival Team')
                ->missing('groups.0.id')
                ->missing('groups.0.coordinatorMembershipIds')
                ->missing('participants.0.transferGroupId'));
    }

    public function test_cross_alliance_coordinator_group_and_participant_ids_fail_closed(): void
    {
        [$ownerA, $allianceA, $sessionA] = $this->ownerAlliance('Group Tenant A', 'group-tenant-a', 5302);
        [$ownerB, $allianceB, $sessionB] = $this->ownerAlliance('Group Tenant B', 'group-tenant-b', 5303);
        $planA = $this->plan($allianceA, $ownerA, 'Plan A');
        $planB = $this->plan($allianceB, $ownerB, 'Plan B');
        $membershipB = AllianceMembership::query()
            ->where('alliance_id', $allianceB->id)
            ->where('user_id', $ownerB->id)
            ->sole();

        $this->actingAs($ownerA)
            ->withSession($sessionA)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$planA->id}/groups", [
                'name' => 'Tampered',
                'coordinator_membership_ids' => [$membershipB->id],
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('coordinator_membership_ids');

        $groupB = $this->group($ownerB, $sessionB, $planB, 'Foreign Group');
        $participantA = $this->incoming($ownerA, $sessionA, $planA, 'Tenant A Player');

        $this->actingAs($ownerA)
            ->withSession($sessionA)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$planA->id}/participants/{$participantA->id}/group", [
                'transfer_group_id' => $groupB->id,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('transfer_group_id');

        $this->withSession($sessionA)
            ->patch("/alliance/transfers/{$planA->id}/groups/{$groupB->id}", [
                'name' => 'Foreign overwrite',
                'coordinator_membership_ids' => [],
            ])
            ->assertNotFound();
    }

    public function test_coordinator_assignment_never_grants_management_permission(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Coordinator Permission', 'coordinator-permission', 5304);
        $plan = $this->plan($alliance, $owner, 'Coordinator plan');
        [$coordinator, $membership] = $this->member($alliance);
        $group = $this->group($owner, $session, $plan, 'Workflow Group', [$membership->id]);
        $participant = $this->incoming($owner, $session, $plan, 'Coordinator Target');
        $coordinatorSession = $this->confirmedSession($alliance->id);

        $this->actingAs($coordinator)
            ->withSession($coordinatorSession)
            ->post("/alliance/transfers/{$plan->id}/groups", [
                'name' => 'Unauthorized Group',
                'coordinator_membership_ids' => [],
            ])
            ->assertForbidden();

        $this->withSession($coordinatorSession)
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}/group", [
                'transfer_group_id' => $group->id,
            ])
            ->assertForbidden();

        self::assertNull($participant->refresh()->transfer_group_id);
    }

    public function test_group_mutation_requires_recent_password_confirmation_and_mutable_plan(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('Group Password', 'group-password', 5305);
        $plan = $this->plan($alliance, $owner, 'Password plan');
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->post("/alliance/transfers/{$plan->id}/groups", [
                'name' => 'Blocked Group',
                'coordinator_membership_ids' => [],
            ])
            ->assertRedirect(route('password.confirm'));

        self::assertSame(0, TransferGroup::query()->count());

        $session = $this->confirmedSession($alliance->id);
        $group = $this->group($owner, $session, $plan, 'Mutable Group');

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/open")
            ->assertRedirect();
        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/lock")
            ->assertRedirect();

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$plan->id}/groups/{$group->id}", [
                'name' => 'Locked rename',
                'coordinator_membership_ids' => [],
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('group');
    }

    public function test_home_kingdom_drift_blocks_group_and_assignment_mutations(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Group Drift', 'group-drift', 5306);
        $plan = $this->plan($alliance, $owner, 'Drift plan');
        $group = $this->group($owner, $session, $plan, 'Drift Group');
        $participant = $this->incoming($owner, $session, $plan, 'Drift Player');
        $newKingdom = Kingdom::query()->create(['number' => 5399, 'status' => 'active']);
        $alliance->forceFill(['kingdom_id' => $newKingdom->id])->save();

        $this->actingAs($owner)
            ->withSession($session)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$plan->id}/groups/{$group->id}", [
                'name' => 'Drift rename',
                'coordinator_membership_ids' => [],
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('group');

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->patch("/alliance/transfers/{$plan->id}/participants/{$participant->id}/group", [
                'transfer_group_id' => $group->id,
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('participant');
    }

    public function test_archive_requires_active_participants_to_be_unassigned_and_is_idempotent(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Archive Group', 'archive-group', 5307);
        $plan = $this->plan($alliance, $owner, 'Archive plan');
        $group = $this->group($owner, $session, $plan, 'Archive Me');
        $participant = $this->incoming($owner, $session, $plan, 'Grouped Player');

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

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/groups/{$group->id}/archive")
            ->assertRedirect();

        self::assertSame($auditCount, $this->eventCount(
            'audit_events',
            'event',
            'kingdoms.transfer_group_archived',
            $alliance->id,
        ));
        self::assertNotNull($group->refresh()->archived_at);
    }

    public function test_active_group_names_are_case_insensitively_unique_and_reusable_after_archive(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Group Names', 'group-names', 5308);
        $plan = $this->plan($alliance, $owner, 'Names plan');
        $group = $this->group($owner, $session, $plan, 'Alpha Team');

        $this->actingAs($owner)
            ->withSession($session)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$plan->id}/groups", [
                'name' => 'alpha team',
                'coordinator_membership_ids' => [],
            ])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('name');

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/groups/{$group->id}/archive")
            ->assertRedirect();

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/groups", [
                'name' => 'alpha team',
                'coordinator_membership_ids' => [],
            ])
            ->assertRedirect();

        self::assertSame(2, TransferGroup::query()->where('transfer_plan_id', $plan->id)->count());
        self::assertSame(1, TransferGroup::query()
            ->where('transfer_plan_id', $plan->id)
            ->whereNull('archived_at')
            ->count());
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
        return $this->app->make(CreateTransferPlan::class)->handle($alliance, $owner, ['label' => $label]);
    }

    /** @param array<int, string> $coordinators */
    private function group(
        User $owner,
        array $session,
        TransferPlan $plan,
        string $name,
        array $coordinators = [],
    ): TransferGroup {
        $this->actingAs($owner)
            ->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/groups", [
                'name' => $name,
                'coordinator_membership_ids' => $coordinators,
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
