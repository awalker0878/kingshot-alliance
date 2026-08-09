<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\CreateTransferPlan;
use App\Domain\Kingdoms\Enums\KingdomStatus;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransferPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_and_view_a_draft_transfer_cycle_with_captured_home_kingdom(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Transfer Alpha', 'transfer-alpha', 4101);

        $this->actingAs($owner)
            ->withSession($session)
            ->post('/alliance/transfers', [
                'label' => 'Summer transfer window',
                'starts_on' => '2026-08-15',
                'ends_on' => '2026-08-22',
            ])
            ->assertRedirect();

        $plan = TransferPlan::query()->sole();
        self::assertSame($alliance->id, $plan->alliance_id);
        self::assertSame($alliance->kingdom_id, $plan->home_kingdom_id);
        self::assertSame(TransferPlanState::Draft, $plan->state);
        self::assertSame('2026-08-15', $plan->starts_on?->toDateString());
        self::assertSame('2026-08-22', $plan->ends_on?->toDateString());

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_user_id' => $owner->id,
            'event' => 'kingdoms.transfer_plan_created',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'kingdoms.transfer_plan_created',
        ]);

        $this->withSession([(string) config('identity.active_alliance_session_key') => $alliance->id])
            ->get('/alliance/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/TransferPlans')
                ->where('alliance.id', $alliance->id)
                ->where('alliance.kingdom', '4101')
                ->where('canManage', true)
                ->where('plan.id', $plan->id)
                ->where('plan.homeKingdom', '4101')
                ->where('plan.state', TransferPlanState::Draft->value));
    }

    public function test_transfer_cycle_creation_requires_an_alliance_kingdom(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'No Kingdom Transfers', 'no-kingdom-transfers');
        $session = $this->confirmedSession($alliance->id);

        $this->actingAs($owner)
            ->withSession($session)
            ->from('/alliance/transfers/manage')
            ->post('/alliance/transfers', ['label' => 'Impossible cycle'])
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('plan');

        self::assertSame(0, TransferPlan::query()->count());
    }

    public function test_transfer_lifecycle_requires_recent_password_confirmation(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('Password Transfer', 'password-transfer', 4102);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->post('/alliance/transfers', ['label' => 'Protected cycle'])
            ->assertRedirect(route('password.confirm'));

        self::assertSame(0, TransferPlan::query()->count());
    }

    public function test_member_can_view_transfer_cycle_but_cannot_manage_it(): void
    {
        [$owner, $alliance, $ownerSession] = $this->ownerAlliance('Member Transfers', 'member-transfers', 4103);
        $this->actingAs($owner)->withSession($ownerSession)
            ->post('/alliance/transfers', ['label' => 'Member-visible cycle'])
            ->assertRedirect();

        $member = User::factory()->create();
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'user_id' => $member->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $role = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::Member->value)
            ->sole();
        $membership->roles()->attach($role->id, ['alliance_id' => $alliance->id]);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($member)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/TransferPlans')
                ->where('canManage', false));

        $this->withSession([$sessionKey => $alliance->id])
            ->get('/alliance/transfers/manage')
            ->assertForbidden();

        $this->withSession($this->confirmedSession($alliance->id))
            ->post('/alliance/transfers', ['label' => 'Unauthorized'])
            ->assertForbidden();
    }

    public function test_normal_lifecycle_is_draft_open_locked_closed_and_retries_are_idempotent(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Lifecycle Transfers', 'lifecycle-transfers', 4104);
        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/transfers', ['label' => 'Lifecycle cycle'])
            ->assertRedirect();
        $plan = TransferPlan::query()->sole();

        $this->withSession($session)->post("/alliance/transfers/{$plan->id}/open")->assertRedirect();
        self::assertSame(TransferPlanState::Open, $plan->refresh()->state);

        $this->withSession($session)->post("/alliance/transfers/{$plan->id}/lock")->assertRedirect();
        self::assertSame(TransferPlanState::Locked, $plan->refresh()->state);

        $this->withSession($session)->post("/alliance/transfers/{$plan->id}/close")->assertRedirect();
        self::assertSame(TransferPlanState::Closed, $plan->refresh()->state);

        $auditCount = $this->eventCount('audit_events', 'event', 'kingdoms.transfer_plan_closed', $alliance->id);
        $outboxCount = $this->eventCount('outbox_messages', 'event_type', 'kingdoms.transfer_plan_closed', $alliance->id);

        $this->withSession($session)->post("/alliance/transfers/{$plan->id}/close")->assertRedirect();
        self::assertSame($auditCount, $this->eventCount('audit_events', 'event', 'kingdoms.transfer_plan_closed', $alliance->id));
        self::assertSame($outboxCount, $this->eventCount('outbox_messages', 'event_type', 'kingdoms.transfer_plan_closed', $alliance->id));
    }

    public function test_invalid_lifecycle_transition_fails_closed(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Invalid Transfer', 'invalid-transfer', 4105);
        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/transfers', ['label' => 'Draft cycle'])
            ->assertRedirect();
        $plan = TransferPlan::query()->sole();

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$plan->id}/lock")
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('plan');

        self::assertSame(TransferPlanState::Draft, $plan->refresh()->state);
    }

    public function test_only_one_transfer_cycle_can_be_open_for_an_alliance(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Single Open Transfer', 'single-open-transfer', 4106);
        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/transfers', ['label' => 'First'])
            ->assertRedirect();
        $this->withSession($session)
            ->post('/alliance/transfers', ['label' => 'Second'])
            ->assertRedirect();

        $first = TransferPlan::query()->where('label', 'First')->sole();
        $second = TransferPlan::query()->where('label', 'Second')->sole();
        $this->withSession($session)->post("/alliance/transfers/{$first->id}/open")->assertRedirect();

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$second->id}/open")
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('plan');

        self::assertSame(TransferPlanState::Open, $first->refresh()->state);
        self::assertSame(TransferPlanState::Draft, $second->refresh()->state);
    }

    public function test_submitted_plan_id_is_re_resolved_under_active_alliance(): void
    {
        [$ownerA, $allianceA, $sessionA] = $this->ownerAlliance('Tenant A', 'transfer-tenant-a', 4107);
        [$ownerB, $allianceB] = $this->ownerAlliance('Tenant B', 'transfer-tenant-b', 4108);
        $planB = $this->app->make(CreateTransferPlan::class)->handle($allianceB, $ownerB, ['label' => 'B plan']);

        $this->actingAs($ownerA)
            ->withSession($sessionA)
            ->post("/alliance/transfers/{$planB->id}/open")
            ->assertNotFound();

        self::assertSame(TransferPlanState::Draft, $planB->refresh()->state);
        self::assertSame(0, TransferPlan::query()->where('alliance_id', $allianceA->id)->count());
    }

    public function test_home_kingdom_drift_blocks_progress_but_cancellation_remains_a_safe_recovery(): void
    {
        [$owner, $alliance, $session] = $this->ownerAlliance('Drift Transfer', 'drift-transfer', 4109);
        $this->actingAs($owner)->withSession($session)
            ->post('/alliance/transfers', ['label' => 'Drift cycle'])
            ->assertRedirect();
        $plan = TransferPlan::query()->sole();

        $newKingdom = Kingdom::query()->create([
            'number' => 4110,
            'status' => KingdomStatus::Active,
        ]);
        $alliance->forceFill(['kingdom_id' => $newKingdom->id])->save();

        $this->withSession($session)
            ->from('/alliance/transfers/manage')
            ->post("/alliance/transfers/{$plan->id}/open")
            ->assertRedirect('/alliance/transfers/manage')
            ->assertSessionHasErrors('plan');
        self::assertSame(TransferPlanState::Draft, $plan->refresh()->state);

        $this->withSession($session)
            ->post("/alliance/transfers/{$plan->id}/cancel")
            ->assertRedirect();
        self::assertSame(TransferPlanState::Cancelled, $plan->refresh()->state);
    }

    /** @return array{0: User, 1: \App\Domain\Alliances\Models\Alliance, 2: array<string, mixed>} */
    private function ownerAlliance(string $name, string $slug, int $kingdom): array
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, $name, $slug, $kingdom);

        return [$owner, $alliance, $this->confirmedSession($alliance->id)];
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
