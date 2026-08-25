<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\EventManagement;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Operations\Events\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
