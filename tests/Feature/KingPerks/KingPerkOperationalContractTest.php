<?php

declare(strict_types=1);

namespace Tests\Feature\KingPerks;

use App\Domain\Authorization\Enums\DefaultKingdomRole;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Authorization\Services\KingdomRoleProvisioner;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\KingPerks\Enums\KingAppointmentType;
use App\Domain\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Domain\KingPerks\Enums\KingPerkPlanStatus;
use App\Domain\KingPerks\Enums\KingPerkPushCategory;
use App\Domain\KingPerks\Enums\KingPerkReminderKind;
use App\Domain\KingPerks\Enums\KingPerkRequestStatus;
use App\Domain\KingPerks\Models\KingPerkAppointment;
use App\Domain\KingPerks\Models\KingPerkPlan;
use App\Domain\KingPerks\Models\KingPerkRequest;
use App\Domain\KingPerks\Services\KingPerkAutoScheduler;
use App\Domain\KingPerks\Services\KingPerkScheduler;
use App\Domain\Notifications\Actions\QueueDueKingPerkReminders;
use App\Domain\Notifications\Models\KingPerkReminderDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KingPerkOperationalContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_authority_is_bound_to_the_exact_kingdom(): void
    {
        $firstKingdom = $this->kingdom(2601);
        $secondKingdom = $this->kingdom(2602);
        $firstManager = $this->manager($firstKingdom, 'First Kingdom Manager');
        $secondManager = $this->manager($secondKingdom, 'Second Kingdom Manager');
        $target = $this->player($secondKingdom, 'Second Kingdom Target');
        $plan = $this->plan($secondKingdom, $secondManager);

        $this->expectException(AuthorizationException::class);

        $this->app->make(KingPerkScheduler::class)->assignAppointment(
            actor: $firstManager,
            plan: $plan,
            type: KingAppointmentType::NobleAdvisor,
            target: $target,
            startsAt: CarbonImmutable::parse('2026-09-01 00:00', 'UTC'),
        );
    }

    public function test_transfer_blocks_self_confirmation_without_rewriting_historical_assignments(): void
    {
        $firstKingdom = $this->kingdom(2611);
        $secondKingdom = $this->kingdom(2612);
        $manager = $this->manager($firstKingdom, 'Transfer Manager');
        $target = $this->player($firstKingdom, 'Transfer Target');
        $plan = $this->plan($firstKingdom, $manager);
        $scheduler = $this->app->make(KingPerkScheduler::class);

        $pending = $scheduler->assignAppointment(
            actor: $manager,
            plan: $plan,
            type: KingAppointmentType::NobleAdvisor,
            target: $target,
            startsAt: CarbonImmutable::parse('2026-09-01 00:00', 'UTC'),
        );
        $historical = $scheduler->assignAppointment(
            actor: $manager,
            plan: $plan,
            type: KingAppointmentType::ChiefMinister,
            target: $target,
            startsAt: CarbonImmutable::parse('2026-09-01 02:00', 'UTC'),
        );
        $historical = $scheduler->markAppointment($manager, $historical, KingPerkAppointmentStatus::Completed);

        $target->forceFill(['current_kingdom_id' => $secondKingdom->id])->save();

        try {
            $scheduler->confirmAppointment($target->refresh(), $pending);
            self::fail('A transferred Player must not confirm an appointment from the former Kingdom.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        self::assertSame(KingPerkAppointmentStatus::Scheduled, $pending->refresh()->status);
        self::assertSame((string) $target->id, (string) $pending->assigned_player_id);
        self::assertSame(KingPerkAppointmentStatus::Completed, $historical->refresh()->status);
        self::assertSame((string) $target->id, (string) $historical->assigned_player_id);
        self::assertSame((string) $firstKingdom->id, (string) $plan->refresh()->kingdom_id);
    }

    public function test_training_auto_schedule_uses_noble_advisor_then_chief_minister_overflow(): void
    {
        $kingdom = $this->kingdom(2621);
        $manager = $this->manager($kingdom, 'Training Manager');
        $primary = $this->player($kingdom, 'Primary Trainer');
        $overflow = $this->player($kingdom, 'Overflow Trainer');
        $plan = $this->plan($kingdom, $manager);
        $start = CarbonImmutable::parse('2026-09-01 00:00', 'UTC');
        $end = $start->addMinutes(30);

        $this->request($plan, $primary, $start, $end, 1200);
        $this->request($plan, $overflow, $start, $end, 600);

        $result = $this->app->make(KingPerkAutoScheduler::class)->handle(
            actor: $manager,
            plan: $plan,
            category: KingPerkPushCategory::Training,
            from: $start,
            until: $end,
        );

        self::assertSame(2, $result['assigned']);
        self::assertSame(
            KingAppointmentType::NobleAdvisor,
            KingPerkAppointment::query()->where('assigned_player_id', $primary->id)->sole()->appointment_type,
        );
        self::assertSame(
            KingAppointmentType::ChiefMinister,
            KingPerkAppointment::query()->where('assigned_player_id', $overflow->id)->sole()->appointment_type,
        );
        self::assertSame(2, KingPerkRequest::query()->where('status', KingPerkRequestStatus::Scheduled->value)->count());
    }

    public function test_live_replacement_preserves_the_no_show_assignment_as_history(): void
    {
        $kingdom = $this->kingdom(2631);
        $manager = $this->manager($kingdom, 'Live Manager');
        $originalPlayer = $this->player($kingdom, 'Original Player');
        $replacementPlayer = $this->player($kingdom, 'Replacement Player');
        $plan = $this->plan($kingdom, $manager);
        $scheduler = $this->app->make(KingPerkScheduler::class);
        $start = CarbonImmutable::parse('2026-09-01 00:00', 'UTC');

        $original = $scheduler->assignAppointment(
            actor: $manager,
            plan: $plan,
            type: KingAppointmentType::NobleAdvisor,
            target: $originalPlayer,
            startsAt: $start,
        );
        $original = $scheduler->markAppointment($manager, $original, KingPerkAppointmentStatus::NoShow);
        $replacement = $scheduler->assignAppointment(
            actor: $manager,
            plan: $plan,
            type: KingAppointmentType::NobleAdvisor,
            target: $replacementPlayer,
            startsAt: $start,
            notes: 'Live replacement for no-show appointment '.(string) $original->id,
        );

        self::assertSame(2, KingPerkAppointment::query()->where('plan_id', $plan->id)->count());
        self::assertSame(KingPerkAppointmentStatus::NoShow, $original->refresh()->status);
        self::assertSame((string) $originalPlayer->id, (string) $original->assigned_player_id);
        self::assertSame(KingPerkAppointmentStatus::Scheduled, $replacement->refresh()->status);
        self::assertSame((string) $replacementPlayer->id, (string) $replacement->assigned_player_id);
        self::assertNotSame((string) $original->id, (string) $replacement->id);
    }

    public function test_reminders_are_idempotent_and_resolve_current_kingdom_managers(): void
    {
        $now = CarbonImmutable::parse('2026-09-01 00:00', 'UTC');
        CarbonImmutable::setTestNow($now);
        Carbon::setTestNow($now);

        try {
            $kingdom = $this->kingdom(2641);
            $otherKingdom = $this->kingdom(2642);
            $currentManager = $this->manager($kingdom, 'Current Manager');
            $formerManager = $this->manager($kingdom, 'Former Manager');
            $target = $this->player($kingdom, 'Reminder Target');
            $plan = $this->plan($kingdom, $currentManager);

            $this->app->make(KingPerkScheduler::class)->assignAppointment(
                actor: $currentManager,
                plan: $plan,
                type: KingAppointmentType::NobleAdvisor,
                target: $target,
                startsAt: $now->addMinutes(5),
            );

            KingdomRoleAssignment::query()
                ->where('kingdom_id', $kingdom->id)
                ->where('player_id', $formerManager->id)
                ->delete();
            $formerManager->forceFill(['current_kingdom_id' => $otherKingdom->id])->save();

            $queue = $this->app->make(QueueDueKingPerkReminders::class);
            self::assertSame(4, $queue->handle());
            self::assertSame(0, $queue->handle());
            self::assertSame(4, KingPerkReminderDelivery::query()->count());
            self::assertFalse(KingPerkReminderDelivery::query()->where('player_id', $formerManager->id)->exists());
            self::assertTrue(KingPerkReminderDelivery::query()
                ->where('player_id', $currentManager->id)
                ->where('kind', KingPerkReminderKind::AppointmentUnconfirmed10Minutes->value)
                ->exists());
            self::assertSame(3, KingPerkReminderDelivery::query()->where('player_id', $target->id)->count());
        } finally {
            CarbonImmutable::setTestNow();
            Carbon::setTestNow();
        }
    }

    private function kingdom(int $number): Kingdom
    {
        return Kingdom::query()->create([
            'number' => $number,
            'status' => 'active',
        ]);
    }

    private function player(Kingdom $kingdom, string $name): Player
    {
        $user = User::factory()->create();

        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'king-perks-'.Str::ulid(),
            'current_name' => $name,
        ]);
    }

    private function manager(Kingdom $kingdom, string $name): Player
    {
        $player = $this->player($kingdom, $name);
        $roles = $this->app->make(KingdomRoleProvisioner::class)->provision($kingdom);
        $administrator = $roles[DefaultKingdomRole::Administrator->value];

        KingdomRoleAssignment::query()->create([
            'kingdom_id' => $kingdom->id,
            'player_id' => $player->id,
            'kingdom_role_id' => $administrator->id,
        ]);

        return $player;
    }

    private function plan(Kingdom $kingdom, Player $actor): KingPerkPlan
    {
        $type = EventType::query()->where('slug', 'kingdom-of-power')->sole();
        $scope = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Kingdom);
        $startsAt = CarbonImmutable::parse('2026-09-06 10:00', 'UTC');
        $event = Event::query()->create([
            'event_type_scope_id' => $scope->id,
            'event_type_id' => $type->id,
            'scope' => EventScope::Kingdom,
            'kingdom_id' => $kingdom->id,
            'target_display_name' => 'Kingdom #'.$kingdom->number,
            'timezone' => 'UTC',
            'schedule_source' => $scope->schedule_source,
            'recurrence_policy' => $scope->recurrence_policy,
            'starts_at' => $startsAt,
            'duration_minutes' => 300,
            'status' => EventStatus::Published,
            'created_by_player_id' => $actor->id,
            'updated_by_player_id' => $actor->id,
        ]);
        $occurrence = EventOccurrence::query()->create([
            'event_id' => $event->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(300),
            'status' => 'scheduled',
        ]);

        return KingPerkPlan::query()->create([
            'event_id' => $event->id,
            'occurrence_id' => $occurrence->id,
            'kingdom_id' => $kingdom->id,
            'status' => KingPerkPlanStatus::Published,
            'window_starts_at' => CarbonImmutable::parse('2026-09-01 00:00', 'UTC'),
            'window_ends_at' => $startsAt,
            'created_by_player_id' => $actor->id,
            'published_by_player_id' => $actor->id,
            'published_at' => now(),
        ]);
    }

    private function request(
        KingPerkPlan $plan,
        Player $player,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $plannedSpeedupMinutes,
    ): KingPerkRequest {
        return KingPerkRequest::query()->create([
            'plan_id' => $plan->id,
            'player_id' => $player->id,
            'push_category' => KingPerkPushCategory::Training,
            'availability_starts_at' => $start,
            'availability_ends_at' => $end,
            'planned_speedup_minutes' => $plannedSpeedupMinutes,
            'status' => KingPerkRequestStatus::Submitted,
        ]);
    }
}
