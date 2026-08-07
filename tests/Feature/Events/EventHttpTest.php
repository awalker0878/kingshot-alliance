<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Application\Events\CreateEvent;
use App\Application\Identity\CreateAlliance;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Models\AllianceMembership;
use App\Models\EventOccurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EventHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_calendar_and_detail_are_scoped_to_the_active_alliance(): void
    {
        $owner = User::factory()->create(['timezone' => 'America/Toronto']);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($owner, 'First Events', 'first-events', timezone: 'America/Toronto');
        $second = $createAlliance->handle($owner, 'Second Events', 'second-events', timezone: 'Asia/Baghdad');
        $createEvent = $this->app->make(CreateEvent::class);
        $firstEvent = $createEvent->handle(
            $owner,
            $first,
            'First Alliance Bear',
            CarbonImmutable::now('America/Toronto')->addDay()->startOfHour(),
            30,
        );
        $secondEvent = $createEvent->handle(
            $owner,
            $second,
            'Second Alliance Bear',
            CarbonImmutable::now('Asia/Baghdad')->addDay()->startOfHour(),
            30,
        );
        /** @var EventOccurrence $firstOccurrence */
        $firstOccurrence = $firstEvent->occurrences->sole();
        /** @var EventOccurrence $secondOccurrence */
        $secondOccurrence = $secondEvent->occurrences->sole();
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $calendar = $this->actingAs($owner)
            ->withSession([$sessionKey => $first->id])
            ->get('/alliance/events');

        $calendar->assertOk();
        $calendar->assertInertia(fn (Assert $page) => $page
            ->component('Alliance/Events/Index')
            ->where('alliance.id', $first->id)
            ->has('events', 1)
            ->where('events.0.id', $firstOccurrence->id)
            ->where('events.0.title', 'First Alliance Bear'));

        $this->get('/alliance/events/'.$firstOccurrence->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/Events/Show')
                ->where('event.id', $firstOccurrence->id));

        $this->get('/alliance/events/'.$secondOccurrence->id)->assertNotFound();
    }

    public function test_member_can_register_and_cancel_through_http(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Registration HTTP', 'registration-http', timezone: 'UTC');
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $owner,
            alliance: $alliance,
            title: 'Registration Event',
            firstLocalStart: CarbonImmutable::now('UTC')->addDay()->startOfHour(),
            durationMinutes: 30,
            registrationOpensMinutesBefore: 2880,
        );
        /** @var EventOccurrence $occurrence */
        $occurrence = $event->occurrences->sole();
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $owner->id)
            ->sole();
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->post('/alliance/events/'.$occurrence->id.'/registration')
            ->assertRedirect();

        $this->assertDatabaseHas('event_registrations', [
            'alliance_id' => $alliance->id,
            'occurrence_id' => $occurrence->id,
            'membership_id' => $membership->id,
            'status' => 'registered',
        ]);

        $this->delete('/alliance/events/'.$occurrence->id.'/registration')->assertRedirect();
        $this->assertDatabaseHas('event_registrations', [
            'alliance_id' => $alliance->id,
            'occurrence_id' => $occurrence->id,
            'membership_id' => $membership->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_csv_and_ical_exports_do_not_leak_another_alliance(): void
    {
        $owner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($owner, 'Export First', 'export-first', timezone: 'UTC');
        $second = $createAlliance->handle($owner, 'Export Second', 'export-second', timezone: 'UTC');
        $createEvent = $this->app->make(CreateEvent::class);
        $createEvent->handle(
            $owner,
            $first,
            'Visible Export Event',
            CarbonImmutable::now('UTC')->addDay()->startOfHour(),
            30,
        );
        $createEvent->handle(
            $owner,
            $second,
            'Hidden Export Event',
            CarbonImmutable::now('UTC')->addDay()->startOfHour(),
            30,
        );
        $sessionKey = (string) config('identity.active_alliance_session_key');
        $this->actingAs($owner)->withSession([$sessionKey => $first->id]);

        $csv = $this->get('/alliance/events/export.csv');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('Visible Export Event', $csv->getContent());
        self::assertStringNotContainsString('Hidden Export Event', $csv->getContent());

        $ical = $this->get('/alliance/events/feed.ics');
        $ical->assertOk();
        $ical->assertHeader('content-type', 'text/calendar; charset=UTF-8');
        self::assertStringContainsString('SUMMARY:Visible Export Event', $ical->getContent());
        self::assertStringNotContainsString('Hidden Export Event', $ical->getContent());
    }

    public function test_active_member_without_event_permission_cannot_open_coordinator_dashboard(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Permission Events', 'permission-events');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'user_id' => $member->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($member)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance/events/manage')
            ->assertForbidden();
    }

    public function test_owner_can_create_a_recurring_event_through_http(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'HTTP Coordinator', 'http-coordinator', timezone: 'America/Toronto');
        $sessionKey = (string) config('identity.active_alliance_session_key');
        $firstStart = CarbonImmutable::now('America/Toronto')->addDay()->startOfHour();
        $until = $firstStart->addWeeks(2);

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->post('/alliance/events', [
                'title' => 'Weekly Bear',
                'first_local_start' => $firstStart->format('Y-m-d\\TH:i'),
                'duration_minutes' => 30,
                'capacity' => 50,
                'registration_opens_minutes_before' => 1440,
                'registration_closes_minutes_before' => 10,
                'recurrence_frequency' => 'weekly',
                'recurrence_interval' => 1,
                'recurrence_until_local' => $until->format('Y-m-d\\TH:i'),
                'instructions' => 'Join with the configured formation guidance.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'alliance_id' => $alliance->id,
            'title' => 'Weekly Bear',
            'recurrence_frequency' => 'weekly',
        ]);
        self::assertSame(3, EventOccurrence::query()->where('alliance_id', $alliance->id)->count());
    }
}
