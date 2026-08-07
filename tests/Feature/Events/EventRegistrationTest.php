<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Domain\Events\Models\Event;

use App\Domain\Events\Actions\CancelEventRegistration;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Actions\RegisterForEvent;
use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Events\Enums\RecurrenceFrequency;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Models\AllianceMembership;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_capacity_waitlist_duplicate_registration_and_promotion_are_transactional(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 14:00:00', 'UTC'));

        $owner = User::factory()->create();
        $alliance = $this->createAlliance($owner, 'event-capacity');
        $secondUser = User::factory()->create();
        $secondMembership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'user_id' => $secondUser->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $owner,
            alliance: $alliance,
            title: 'Bear Hunt',
            firstLocalStart: CarbonImmutable::parse('2026-08-07 12:00:00', 'America/Toronto'),
            durationMinutes: 30,
            capacity: 1,
            registrationOpensMinutesBefore: 180,
            frequency: RecurrenceFrequency::None,
        );

        /** @var EventOccurrence $occurrence */
        $occurrence = $event->occurrences->sole();
        $register = $this->app->make(RegisterForEvent::class);

        $ownerRegistration = $register->handle($owner, $alliance, $occurrence->id);
        $duplicate = $register->handle($owner, $alliance, $occurrence->id);
        $secondRegistration = $register->handle($secondUser, $alliance, $occurrence->id);

        self::assertSame($ownerRegistration->id, $duplicate->id);
        self::assertSame(EventRegistrationStatus::Registered, $ownerRegistration->status);
        self::assertSame(EventRegistrationStatus::Waitlisted, $secondRegistration->status);
        self::assertSame(1, $secondRegistration->waitlist_position);
        self::assertSame(2, EventRegistration::query()->where('occurrence_id', $occurrence->id)->count());

        $this->app->make(CancelEventRegistration::class)->handle($owner, $alliance, $occurrence->id);

        $ownerRegistration->refresh();
        $secondRegistration->refresh();

        self::assertSame(EventRegistrationStatus::Cancelled, $ownerRegistration->status);
        self::assertSame(EventRegistrationStatus::Registered, $secondRegistration->status);
        self::assertNull($secondRegistration->waitlist_position);
        self::assertSame($secondMembership->id, $secondRegistration->membership_id);

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'event' => 'event.registration.cancelled',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'event.registration.promoted',
            'aggregate_id' => $secondRegistration->id,
        ]);
    }

    public function test_registration_cannot_resolve_an_occurrence_from_another_alliance(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 14:00:00', 'UTC'));

        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $firstAlliance = $this->createAlliance($firstOwner, 'events-first');
        $secondAlliance = $this->createAlliance($secondOwner, 'events-second');

        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $firstOwner,
            alliance: $firstAlliance,
            title: 'Alliance One Event',
            firstLocalStart: CarbonImmutable::parse('2026-08-07 12:00:00', 'America/Toronto'),
            durationMinutes: 30,
            registrationOpensMinutesBefore: 180,
        );

        /** @var EventOccurrence $occurrence */
        $occurrence = $event->occurrences->sole();

        $this->expectException(ModelNotFoundException::class);

        $this->app->make(RegisterForEvent::class)->handle($secondOwner, $secondAlliance, $occurrence->id);
    }

    private function createAlliance(User $owner, string $slug): Alliance
    {
        return $this->app->make(CreateAlliance::class)->handle(
            owner: $owner,
            name: str_replace('-', ' ', ucfirst($slug)),
            slug: $slug,
            kingdom: '1234',
            language: 'en',
            timezone: 'America/Toronto',
        );
    }
}
