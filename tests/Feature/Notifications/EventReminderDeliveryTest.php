<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Events\Actions\CancelEventRegistration;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Actions\RegisterForEvent;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Actions\CreateEventReminderRule;
use App\Domain\Notifications\Actions\QueueDueEventReminders;
use App\Domain\Notifications\Actions\SyncEventReminderDeliveries;
use App\Domain\Notifications\Enums\EventReminderDeliveryStatus;
use App\Domain\Notifications\Models\EventReminderDelivery;
use App\Domain\Platform\Actions\PublishOutboxBatch;
use App\Domain\Platform\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EventReminderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_reminder_materialization_queue_and_publish_are_idempotent(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 14:00:00', 'UTC'));

        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle(
            owner: $owner,
            name: 'Reminder Alliance',
            slug: 'reminder-alliance',
            timezone: 'America/Toronto',
        );
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $owner,
            alliance: $alliance,
            title: 'Reminder Event',
            firstLocalStart: CarbonImmutable::parse('2026-08-07 11:00:00', 'America/Toronto'),
            durationMinutes: 30,
            registrationOpensMinutesBefore: 180,
        );
        /** @var EventOccurrence $occurrence */
        $occurrence = $event->occurrences->sole();

        $this->app->make(RegisterForEvent::class)->handle($owner, $alliance, $occurrence->id);
        $this->app->make(CreateEventReminderRule::class)->handle($owner, $alliance, $event, 60);

        $sync = $this->app->make(SyncEventReminderDeliveries::class);
        self::assertSame(1, $sync->handle($occurrence));
        self::assertSame(0, $sync->handle($occurrence));
        self::assertSame(1, EventReminderDelivery::query()->count());

        $delivery = EventReminderDelivery::query()->sole();
        self::assertSame(EventReminderDeliveryStatus::Pending, $delivery->status);

        self::assertSame(1, $this->app->make(QueueDueEventReminders::class)->handle());
        self::assertSame(0, $this->app->make(QueueDueEventReminders::class)->handle());

        $delivery->refresh();
        self::assertSame(EventReminderDeliveryStatus::Queued, $delivery->status);

        $reminderOutbox = OutboxMessage::query()
            ->where('event_type', 'event.reminder.requested')
            ->sole();
        self::assertNull($reminderOutbox->published_at);

        OutboxMessage::query()->where('id', '!=', $reminderOutbox->id)->delete();

        $publisher = $this->app->make(PublishOutboxBatch::class);
        self::assertSame(1, $publisher->handle());
        self::assertSame(0, $publisher->handle());

        $delivery->refresh();
        $reminderOutbox->refresh();
        self::assertSame(EventReminderDeliveryStatus::Sent, $delivery->status);
        self::assertNotNull($delivery->sent_at);
        self::assertNotNull($reminderOutbox->published_at);

        self::assertSame(0, $this->app->make(QueueDueEventReminders::class)->handle());
        self::assertSame(1, OutboxMessage::query()
            ->where('event_type', 'event.reminder.requested')
            ->count());
    }

    public function test_cancelled_registration_suppresses_a_materialized_reminder(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 14:00:00', 'UTC'));

        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle(
            owner: $owner,
            name: 'Cancelled Reminder Alliance',
            slug: 'cancelled-reminder-alliance',
            timezone: 'America/Toronto',
        );
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $owner,
            alliance: $alliance,
            title: 'Cancelled Reminder Event',
            firstLocalStart: CarbonImmutable::parse('2026-08-07 11:00:00', 'America/Toronto'),
            durationMinutes: 30,
            registrationOpensMinutesBefore: 180,
        );
        /** @var EventOccurrence $occurrence */
        $occurrence = $event->occurrences->sole();

        $this->app->make(RegisterForEvent::class)->handle($owner, $alliance, $occurrence->id);
        $this->app->make(CreateEventReminderRule::class)->handle($owner, $alliance, $event, 60);
        $this->app->make(SyncEventReminderDeliveries::class)->handle($occurrence);
        $this->app->make(CancelEventRegistration::class)->handle($owner, $alliance, $occurrence->id);

        self::assertSame(0, $this->app->make(QueueDueEventReminders::class)->handle());

        $delivery = EventReminderDelivery::query()->sole();
        self::assertSame(EventReminderDeliveryStatus::Cancelled, $delivery->status);
        self::assertSame(0, OutboxMessage::query()->where('event_type', 'event.reminder.requested')->count());
    }

    public function test_sent_reminder_inbox_is_scoped_to_the_active_alliance(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 14:00:00', 'UTC'));

        $owner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $firstAlliance = $createAlliance->handle(
            owner: $owner,
            name: 'First Reminder Alliance',
            slug: 'first-reminder-alliance',
            timezone: 'America/Toronto',
        );
        $secondAlliance = $createAlliance->handle(
            owner: $owner,
            name: 'Second Reminder Alliance',
            slug: 'second-reminder-alliance',
            timezone: 'UTC',
        );

        $createEvent = $this->app->make(CreateEvent::class);
        $firstEvent = $createEvent->handle(
            actor: $owner,
            alliance: $firstAlliance,
            title: 'Visible Reminder Event',
            firstLocalStart: CarbonImmutable::parse('2026-08-07 11:00:00', 'America/Toronto'),
            durationMinutes: 30,
            registrationOpensMinutesBefore: 180,
        );
        $secondEvent = $createEvent->handle(
            actor: $owner,
            alliance: $secondAlliance,
            title: 'Hidden Reminder Event',
            firstLocalStart: CarbonImmutable::parse('2026-08-07 15:00:00', 'UTC'),
            durationMinutes: 30,
            registrationOpensMinutesBefore: 180,
        );
        /** @var EventOccurrence $firstOccurrence */
        $firstOccurrence = $firstEvent->occurrences->sole();
        /** @var EventOccurrence $secondOccurrence */
        $secondOccurrence = $secondEvent->occurrences->sole();

        $register = $this->app->make(RegisterForEvent::class);
        $register->handle($owner, $firstAlliance, $firstOccurrence->id);
        $register->handle($owner, $secondAlliance, $secondOccurrence->id);

        $createRule = $this->app->make(CreateEventReminderRule::class);
        $createRule->handle($owner, $firstAlliance, $firstEvent, 60);
        $createRule->handle($owner, $secondAlliance, $secondEvent, 60);

        $sync = $this->app->make(SyncEventReminderDeliveries::class);
        self::assertSame(1, $sync->handle($firstOccurrence));
        self::assertSame(1, $sync->handle($secondOccurrence));
        self::assertSame(2, $this->app->make(QueueDueEventReminders::class)->handle());

        OutboxMessage::query()->where('event_type', '!=', 'event.reminder.requested')->delete();
        self::assertSame(2, $this->app->make(PublishOutboxBatch::class)->handle());

        $sessionKey = (string) config('identity.active_alliance_session_key');
        $this->actingAs($owner)
            ->withSession([$sessionKey => $firstAlliance->id])
            ->get('/alliance/events')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/Events/Index')
                ->has('eventReminders', 1)
                ->where('eventReminders.0.occurrenceId', $firstOccurrence->id)
                ->where('eventReminders.0.title', 'Visible Reminder Event'));
    }
}
