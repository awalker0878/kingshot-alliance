<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Models\Event;

use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EventOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_shows_only_active_alliance_upcoming_events(): void
    {
        $owner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($owner, 'Overview First', 'overview-first', timezone: 'America/Toronto');
        $second = $createAlliance->handle($owner, 'Overview Second', 'overview-second', timezone: 'UTC');
        $createEvent = $this->app->make(CreateEvent::class);
        $firstEvent = $createEvent->handle(
            $owner,
            $first,
            'Visible Overview Event',
            CarbonImmutable::now('America/Toronto')->addDay()->startOfHour(),
            30,
        );
        $createEvent->handle(
            $owner,
            $second,
            'Hidden Overview Event',
            CarbonImmutable::now('UTC')->addDay()->startOfHour(),
            30,
        );
        /** @var EventOccurrence $occurrence */
        $occurrence = $firstEvent->occurrences->sole();
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $first->id])
            ->get('/alliance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/Overview')
                ->where('alliance.id', $first->id)
                ->where('contentHub.canManageEvents', true)
                ->has('contentHub.upcomingActivities', 1)
                ->where('contentHub.upcomingActivities.0.id', $occurrence->id)
                ->where('contentHub.upcomingActivities.0.title', 'Visible Overview Event')
                ->where('contentHub.upcomingActivities.0.allianceTimezone', 'America/Toronto'));
    }
}
