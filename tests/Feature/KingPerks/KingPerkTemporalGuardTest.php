<?php

declare(strict_types=1);

namespace Tests\Feature\KingPerks;

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
use App\Domain\KingPerks\Enums\KingPerkRequestStatus;
use App\Domain\KingPerks\Models\KingPerkAppointment;
use App\Domain\KingPerks\Models\KingPerkPlan;
use App\Domain\KingPerks\Models\KingPerkRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class KingPerkTemporalGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_overlapping_appointments_for_the_same_position(): void
    {
        [$plan, $actor, $other] = $this->fixture();
        $this->appointment($plan, $actor, $actor, KingAppointmentType::NobleAdvisor, '2026-09-01 00:00');

        $this->expectException(QueryException::class);
        $this->appointment($plan, $other, $actor, KingAppointmentType::NobleAdvisor, '2026-09-01 00:15');
    }

    public function test_database_allows_adjacent_position_windows_for_different_players(): void
    {
        [$plan, $actor, $other] = $this->fixture();
        $first = $this->appointment($plan, $actor, $actor, KingAppointmentType::NobleAdvisor, '2026-09-01 00:00');
        $second = $this->appointment($plan, $other, $actor, KingAppointmentType::NobleAdvisor, '2026-09-01 00:30');

        self::assertSame('2026-09-01T00:30:00+00:00', $first->ends_at->toIso8601String());
        self::assertSame('2026-09-01T01:30:00+00:00', $first->player_cooldown_ends_at->toIso8601String());
        self::assertSame('2026-09-01T01:00:00+00:00', $second->ends_at->toIso8601String());
    }

    public function test_database_rejects_player_reappointment_during_persisted_cooldown_across_positions(): void
    {
        [$plan, $actor] = $this->fixture();
        $this->appointment($plan, $actor, $actor, KingAppointmentType::NobleAdvisor, '2026-09-01 00:00');

        $this->expectException(QueryException::class);
        $this->appointment($plan, $actor, $actor, KingAppointmentType::ChiefMinister, '2026-09-01 00:45');
    }

    public function test_database_allows_player_reappointment_when_persisted_cooldown_has_elapsed(): void
    {
        [$plan, $actor] = $this->fixture();
        $this->appointment($plan, $actor, $actor, KingAppointmentType::NobleAdvisor, '2026-09-01 00:00');
        $next = $this->appointment($plan, $actor, $actor, KingAppointmentType::ChiefMinister, '2026-09-01 01:30');

        self::assertSame('2026-09-01T02:00:00+00:00', $next->ends_at->toIso8601String());
    }

    public function test_database_rejects_invalid_request_availability_window(): void
    {
        [$plan, $actor] = $this->fixture();

        $this->expectException(QueryException::class);
        KingPerkRequest::query()->create([
            'plan_id' => $plan->id,
            'player_id' => $actor->id,
            'push_category' => KingPerkPushCategory::Training,
            'availability_starts_at' => CarbonImmutable::parse('2026-09-01 02:00', 'UTC'),
            'availability_ends_at' => CarbonImmutable::parse('2026-09-01 01:00', 'UTC'),
            'status' => KingPerkRequestStatus::Submitted,
        ]);
    }

    /** @return array{KingPerkPlan,Player,Player} */
    private function fixture(): array
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => random_int(8200, 8999), 'status' => 'active']);
        $actor = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'kp-actor-'.random_int(1000, 9999),
            'current_name' => 'King Perks Actor',
        ]);
        $other = Player::query()->create([
            'user_id' => $otherUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'kp-other-'.random_int(1000, 9999),
            'current_name' => 'King Perks Other',
        ]);

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
        $plan = KingPerkPlan::query()->create([
            'event_id' => $event->id,
            'occurrence_id' => $occurrence->id,
            'kingdom_id' => $kingdom->id,
            'status' => KingPerkPlanStatus::Published,
            'window_starts_at' => CarbonImmutable::parse('2026-09-01 00:00', 'UTC'),
            'window_ends_at' => CarbonImmutable::parse('2026-09-06 10:00', 'UTC'),
            'created_by_player_id' => $actor->id,
            'published_by_player_id' => $actor->id,
            'published_at' => now(),
        ]);

        return [$plan, $actor, $other];
    }

    private function appointment(
        KingPerkPlan $plan,
        Player $target,
        Player $actor,
        KingAppointmentType $type,
        string $startsAt,
    ): KingPerkAppointment {
        $start = CarbonImmutable::parse($startsAt, 'UTC');

        return KingPerkAppointment::query()->create([
            'plan_id' => $plan->id,
            'appointment_type' => $type,
            'assigned_player_id' => $target->id,
            'starts_at' => $start,
            'ends_at' => $start,
            'player_cooldown_ends_at' => $start,
            'status' => KingPerkAppointmentStatus::Scheduled,
            'assigned_by_player_id' => $actor->id,
        ]);
    }
}
