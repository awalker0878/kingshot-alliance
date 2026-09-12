<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\Participation\Support;

use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Reminders\Actions\CreateEventReminderRule;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use Carbon\CarbonImmutable;

/** Real owner-created source for transport contracts; reuse only within the reset case. */
final class EventReminderSourceFixture
{
    /** @return array{occurrence:string,event:string,rule:string} */
    public function forPlayer(string $playerId): array
    {
        $event = Event::query()->where('scope', EventScope::Player->value)->where('player_id', $playerId)
            ->where('title', 'Notification transport fixture')->first();
        if (! $event instanceof Event) {
            $scope = EventTypeScope::query()->where('scope', EventScope::Player->value)
                ->whereHas('eventType', static fn ($query) => $query->where('slug', 'hall-of-governors'))->firstOrFail();
            $created = app(CreateEvent::class)->handle(
                actorPlayerId: $playerId, configurationId: (string) $scope->id, scope: EventScope::Player,
                targetId: $playerId, firstLocalStart: CarbonImmutable::now('UTC')->addDays(3),
                title: 'Notification transport fixture', durationMinutes: 60,
            );
            $event = Event::query()->findOrFail($created->eventId);
            app(CreateEventReminderRule::class)->handle($playerId, (string) $event->id, 60, EventReminderAudience::Target);
        }
        $occurrence = EventOccurrence::query()->where('event_id', $event->id)->firstOrFail();
        $rule = EventReminderRule::query()->where('event_id', $event->id)->firstOrFail();

        return ['occurrence' => (string) $occurrence->id, 'event' => (string) $event->id, 'rule' => (string) $rule->id];
    }
}
