<?php

declare(strict_types=1);

namespace Tests\Feature\Communications\KingPerks;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Communications\Reminders\Actions\QueueDueKingPerkReminders;
use App\Contexts\Communications\Reminders\Models\KingPerkReminderDelivery;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleProvisioner;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Services\KingdomOperationsRoleProvisioner;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Enums\EventStatus;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\KingPerks\Enums\KingAppointmentType;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPlanStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkReminderKind;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Services\KingPerkScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KingPerkReminderDeliveryTest extends TestCase
{
    use RefreshDatabase;

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
        $this->app->make(KingdomOperationsRoleProvisioner::class)->provision($kingdom);
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
}
