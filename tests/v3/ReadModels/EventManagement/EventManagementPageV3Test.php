<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\EventManagement;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\v3\Fixtures\EventCommandVisualFixture;
use Tests\v3\TestCase;

final class EventManagementPageV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_event_command_visual_fixture_renders_management_page(): void
    {
        EventCommandVisualFixture::seed();

        $user = User::query()
            ->where('email', 'event-command-visual@example.test')
            ->firstOrFail();
        $event = Event::query()
            ->where('title', 'Event Command Visual')
            ->firstOrFail();

        $this->withoutExceptionHandling();

        $response = $this->actingAs($user)->get(route('events.management', [
            'event' => (string) $event->id,
        ]));

        $response->assertOk();
        $response->assertInertia(static fn (Assert $page): Assert => $page
            ->component('Operations/Events/Manage')
            ->where('eventCommand.eventId', (string) $event->id)
            ->has('eventCommand.sections'));
    }

    public function test_management_route_rejects_occurrence_from_another_authorized_event(): void
    {
        EventCommandVisualFixture::seed();

        $user = User::query()
            ->where('email', 'event-command-visual@example.test')
            ->firstOrFail();
        $player = Player::query()->where('user_id', $user->id)->firstOrFail();
        $event = Event::query()
            ->where('title', 'Event Command Visual')
            ->firstOrFail();
        self::assertIsString($event->alliance_id);

        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas(
                'eventType',
                static fn ($query) => $query->where('slug', 'alliance-mobilization'),
            )
            ->firstOrFail();
        $foreign = app(CreateEvent::class)->handle(
            actorPlayerId: (string) $player->id,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $event->alliance_id,
            firstLocalStart: now('UTC')->addDays(5)->toImmutable(),
            title: 'Foreign Event Command Occurrence',
            durationMinutes: 60,
        );
        self::assertNotNull($foreign->firstOccurrenceId);

        $this->withoutExceptionHandling();
        $this->expectException(ValidationException::class);

        $this->actingAs($user)->get(route('events.management', [
            'event' => (string) $event->id,
            'occurrence' => $foreign->firstOccurrenceId,
        ]));
    }
}
