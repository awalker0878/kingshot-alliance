<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\Events;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\BulkCancelEvents;
use App\Contexts\Operations\Events\Actions\CancelEvent;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Actions\PreviewEventBulkCancellation;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventStatus;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class EventBulkCancellationBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_cancellation_previews_lifecycle_and_reports_every_event(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 59001);
        $alliance = $scenario->alliance($actor);
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $ready = $this->event($actor, $alliance->allianceId, $configuration, 'Ready Event', 1);
        $cancelled = $this->event($actor, $alliance->allianceId, $configuration, 'Cancelled Event', 2);
        $completed = $this->event($actor, $alliance->allianceId, $configuration, 'Completed Event', 3);
        app(CancelEvent::class)->handle($actor->playerId, (string) $cancelled->id);
        $completed->forceFill(['status' => EventStatus::Completed])->save();
        $eventIds = [(string) $ready->id, (string) $cancelled->id, (string) $completed->id];

        $preview = app(PreviewEventBulkCancellation::class)->handle($actor, $eventIds);

        self::assertSame(1, $preview['ready']);
        self::assertSame(2, $preview['blocked']);
        self::assertSame([(string) $ready->id], $preview['readyItemIds']);
        self::assertSame(
            ['ready', 'already-cancelled', 'event-completed'],
            array_column($preview['items'], 'code'),
        );

        $result = app(BulkCancelEvents::class)->handle($actor, $eventIds)->toArray();

        self::assertSame(1, $result['succeeded']);
        self::assertSame(1, $result['failed']);
        self::assertSame(1, $result['skipped']);
        self::assertSame([(string) $completed->id], $result['failedItemIds']);
        self::assertSame(EventStatus::Cancelled, $ready->refresh()->status);
        self::assertTrue(AuditEvent::query()->where('event', 'event.events.bulk_cancelled')->exists());
    }

    private function event(
        PlayerReference $actor,
        string $allianceId,
        EventTypeScope $configuration,
        string $title,
        int $dayOffset,
    ): Event {
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays($dayOffset),
            title: $title,
            durationMinutes: 60,
        );

        return Event::query()->findOrFail($created->eventId);
    }
}
