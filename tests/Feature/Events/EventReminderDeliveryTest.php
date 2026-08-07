<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Application\Events\CancelEventRegistration;
use App\Application\Events\CreateEvent;
use App\Application\Events\CreateEventReminderRule;
use App\Application\Events\QueueDueEventReminders;
use App\Application\Events\RegisterForEvent;
use App\Application\Events\SyncEventReminderDeliveries;
use App\Application\Identity\CreateAlliance;
use App\Application\Shared\PublishOutboxBatch;
use App\Domain\Events\Enums\EventReminderDeliveryStatus;
use App\Models\EventOccurrence;
use App\Models\EventReminderDelivery;
use App\Models\OutboxMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->app->make(PublishOutboxBatch::class)->handle();

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
}
