<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\EventManagement;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventStatus;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderTrigger;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use App\Contexts\Operations\Results\Models\EventResult;
use App\ReadModels\EventManagement\Queries\EventCommandQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class EventCommandBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_upcoming_lifecycle_is_derived_per_occurrence_and_not_persisted(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 18:00:00', 'UTC'));
        [$actor, $alliance] = $this->allianceScenario();

        $planning = $this->event($actor, $alliance, CarbonImmutable::now('UTC')->addDays(10));
        $ready = $this->event($actor, $alliance, CarbonImmutable::now('UTC')->addDays(2));
        $attention = $this->event(
            $actor,
            $alliance,
            CarbonImmutable::now('UTC')->addDays(2),
            reminder: false,
        );

        self::assertSame('planning', $this->command($actor, $planning)['state']);
        self::assertSame('ready', $this->command($actor, $ready)['state']);

        $attentionProjection = $this->command($actor, $attention);
        self::assertSame('needs_attention', $attentionProjection['state']);
        self::assertGreaterThan(0, $attentionProjection['blockerCount']);

        foreach ([$planning, $ready, $attention] as $event) {
            self::assertArrayNotHasKey('event_ready', $event->getAttributes());
            self::assertArrayNotHasKey('event_complete', $event->getAttributes());
            self::assertArrayNotHasKey('readiness_state', $event->getAttributes());
            self::assertArrayNotHasKey('blocker_count', $event->getAttributes());
        }
    }

    public function test_active_closeout_and_complete_are_derived_without_mutating_event_truth(): void
    {
        $base = CarbonImmutable::parse('2026-08-24 18:00:00', 'UTC');
        CarbonImmutable::setTestNow($base);
        [$actor, $alliance] = $this->allianceScenario();

        $activeEvent = $this->event($actor, $alliance, $base->addHour());
        $activeOccurrence = $activeEvent->occurrences()->firstOrFail();
        CarbonImmutable::setTestNow($base->addMinutes(70));
        self::assertSame('active', $this->command($actor, $activeEvent)['state']);
        self::assertSame(EventOccurrenceStatus::Scheduled, $activeOccurrence->fresh()->status);

        CarbonImmutable::setTestNow($base);
        $endedEvent = $this->event($actor, $alliance, $base->subHours(2));
        $endedOccurrence = $endedEvent->occurrences()->firstOrFail();
        $endedOccurrence->forceFill(['status' => EventOccurrenceStatus::Completed])->save();

        $required = $this->command($actor, $endedEvent);
        self::assertSame('closeout_required', $required['state']);
        self::assertGreaterThan(0, $required['blockerCount']);

        EventResult::query()->create([
            'occurrence_id' => (string) $endedOccurrence->id,
            'outcome' => 'recorded',
            'score' => 0,
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => CarbonImmutable::now('UTC'),
        ]);

        $complete = $this->command($actor, $endedEvent);
        self::assertSame('complete', $complete['state']);
        self::assertSame(0, $complete['blockerCount']);
        self::assertSame(EventStatus::Published, $this->reloadEvent($endedEvent)->status);
        self::assertSame(EventOccurrenceStatus::Completed, $endedOccurrence->fresh()->status);
    }

    public function test_cancelled_occurrence_preserves_event_truth_and_becomes_not_applicable(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 18:00:00', 'UTC'));
        [$actor, $alliance] = $this->allianceScenario();
        $event = $this->event($actor, $alliance, CarbonImmutable::now('UTC')->addDays(2));
        $occurrence = $event->occurrences()->firstOrFail();
        $occurrence->forceFill(['status' => EventOccurrenceStatus::Cancelled])->save();

        $projection = $this->command($actor, $event);
        self::assertNull($projection['state']);
        self::assertSame('cancelled', $projection['occurrenceStatus']);
        self::assertSame('not_applicable', $projection['sections'][0]['items'][0]['status']);
        self::assertSame(EventStatus::Published, $this->reloadEvent($event)->status);
    }

    public function test_explicit_occurrence_must_belong_to_the_authorized_event(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 18:00:00', 'UTC'));
        [$actor, $alliance] = $this->allianceScenario();
        $first = $this->event($actor, $alliance, CarbonImmutable::now('UTC')->addDays(2));
        $second = $this->event($actor, $alliance, CarbonImmutable::now('UTC')->addDays(3));
        $foreignOccurrence = $second->occurrences()->firstOrFail();

        $this->expectException(ValidationException::class);
        app(EventCommandQuery::class)->forEvent(
            $actor,
            $this->reloadEvent($first),
            (string) $foreignOccurrence->id,
        );
    }

    public function test_default_selection_prefers_recent_unclosed_occurrence_before_upcoming_occurrence(): void
    {
        $now = CarbonImmutable::parse('2026-08-24 18:00:00', 'UTC');
        CarbonImmutable::setTestNow($now);
        [$actor, $alliance] = $this->allianceScenario();
        $event = $this->event($actor, $alliance, $now->subHours(2));
        $ended = $event->occurrences()->firstOrFail();
        $ended->forceFill(['status' => EventOccurrenceStatus::Completed])->save();
        $upcoming = EventOccurrence::query()->create([
            'event_id' => (string) $event->id,
            'starts_at' => $now->addDays(2),
            'ends_at' => $now->addDays(2)->addHour(),
            'status' => EventOccurrenceStatus::Scheduled,
            'settings' => [],
        ]);

        $projection = $this->command($actor, $event);
        self::assertSame((string) $ended->id, $projection['selectedOccurrenceId']);
        self::assertSame('closeout_required', $projection['state']);

        EventResult::query()->create([
            'occurrence_id' => (string) $ended->id,
            'outcome' => 'recorded',
            'score' => 0,
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => $now,
        ]);

        $afterCloseout = $this->command($actor, $event);
        self::assertSame((string) $upcoming->id, $afterCloseout['selectedOccurrenceId']);
    }

    public function test_blockers_and_warnings_expose_owner_and_navigation_only_handoffs(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 18:00:00', 'UTC'));
        [$actor, $alliance] = $this->allianceScenario();
        $event = $this->event(
            $actor,
            $alliance,
            CarbonImmutable::now('UTC')->addDays(2),
            reminder: false,
        );
        $projection = $this->command($actor, $event);

        $actionable = [];
        foreach ($projection['sections'] as $section) {
            foreach ($section['items'] as $item) {
                if (in_array($item['status'], ['needs_attention', 'warning', 'unknown'], true)) {
                    $actionable[] = $item;
                }
            }
        }

        self::assertNotEmpty($actionable);
        foreach ($actionable as $item) {
            self::assertNotSame('', $item['owner']);
            self::assertIsArray($item['handoff']);
            self::assertStringStartsWith('/', $item['handoff']['href']);
            self::assertStringNotContainsString('mark-ready', $item['handoff']['href']);
            self::assertStringNotContainsString('complete-closeout', $item['handoff']['href']);
        }
    }

    /** @return array{PlayerReference,AllianceReference} */
    private function allianceScenario(): array
    {
        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $actor = $scenario->player((int) $user->id, 76001);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);

        return [$actor, $alliance];
    }

    private function event(
        PlayerReference $actor,
        AllianceReference $alliance,
        CarbonImmutable $start,
        bool $reminder = true,
    ): Event {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas(
                'eventType',
                static fn ($query) => $query->where('slug', 'alliance-mobilization'),
            )
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: $start,
            title: 'Event Command Test',
            durationMinutes: 60,
        );
        self::assertNotNull($created->firstOccurrenceId);

        if ($reminder) {
            EventReminderRule::query()->create([
                'event_id' => $created->eventId,
                'trigger_type' => EventReminderTrigger::BeforeStart,
                'minutes_before' => 60,
                'audience' => EventReminderAudience::AllScopePlayers,
                'channel' => 'database',
                'is_enabled' => true,
                'created_by_player_id' => $actor->playerId,
                'updated_by_player_id' => $actor->playerId,
            ]);
        }

        return Event::query()->findOrFail($created->eventId);
    }

    /** @return array<string, mixed> */
    private function command(PlayerReference $actor, Event $event): array
    {
        return app(EventCommandQuery::class)->forEvent($actor, $this->reloadEvent($event));
    }

    private function reloadEvent(Event $event): Event
    {
        return Event::query()
            ->with(['eventType', 'typeScope.capabilities', 'occurrences'])
            ->findOrFail($event->id);
    }
}
