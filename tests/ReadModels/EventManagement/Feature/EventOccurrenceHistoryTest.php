<?php

declare(strict_types=1);

namespace Tests\ReadModels\EventManagement\Feature;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use App\Contexts\Operations\Events\Queries\EventOccurrenceCatalogueQuery;
use App\ReadModels\EventManagement\Queries\EventCommandQuery;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class EventOccurrenceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private PlayerReference $actor;

    private Event $event;

    private string $selected;

    protected function setUp(): void
    {
        parent::setUp();
        $factory = app(ScenarioFactory::class);
        $user = $factory->authUser();
        $this->actor = $factory->player((int) $user->id, 61734);
        $alliance = $factory->alliance($this->actor);
        $factory->roster($this->actor, $alliance);
        $scope = EventTypeScope::query()->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))->firstOrFail();
        $created = app(CreateEvent::class)->handle($this->actor->playerId, (string) $scope->id,
            EventScope::Alliance, $alliance->allianceId, CarbonImmutable::now('UTC')->addDay(), durationMinutes: 60);
        $this->event = Event::query()->findOrFail($created->eventId);
        $this->selected = $created->firstOccurrenceId ?? throw new \LogicException;
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $this->actor->playerId]);
    }

    public function test_complete_catalogue_is_bounded_and_new_rows_do_not_extend_an_existing_traversal(): void
    {
        $history = $this->history(101);
        $query = app(EventOccurrenceCatalogueQuery::class);
        $page = $query->forEvent($this->actor, (string) $this->event->id);
        self::assertSame(102, $page['total']);
        self::assertCount(25, $page['items']);
        self::assertFalse($page['event']->relationLoaded('occurrences'));
        $seen = $page['items']->modelKeys();
        $late = EventOccurrence::query()->create(['event_id' => $this->event->id, 'starts_at' => now()->subYears(2),
            'ends_at' => now()->subYears(2)->addHour(), 'status' => 'cancelled']);
        while ($page['hasMore']) {
            $page = $query->forEvent($this->actor, (string) $this->event->id, $page['nextCursor']);
            self::assertLessThanOrEqual(25, $page['items']->count());
            self::assertFalse($page['isFirstPage']);
            $seen = [...$seen, ...$page['items']->modelKeys()];
        }
        self::assertCount(102, array_unique($seen));
        self::assertEqualsCanonicalizing([$this->selected, ...$history], $seen);
        self::assertNotContains((string) $late->id, $seen);
        self::assertSame(103, $query->forEvent($this->actor, (string) $this->event->id)['total']);
    }

    public function test_second_precision_history_is_traversed_once_and_empty_history_remains_explicit(): void
    {
        $history = $this->history(54, sameDay: true);
        $query = app(EventOccurrenceCatalogueQuery::class);
        $cursor = null;
        $seen = [];
        do {
            $page = $query->forEvent($this->actor, (string) $this->event->id, $cursor);
            $seen = [...$seen, ...$page['items']->modelKeys()];
            $cursor = $page['nextCursor'];
        } while ($cursor !== null);
        self::assertEqualsCanonicalizing([$this->selected, ...$history], $seen);
        DB::table('event_occurrences')->where('event_id', $this->event->id)->delete();
        $empty = app(EventCommandQuery::class)->forEvent($this->actor, $this->event);
        self::assertNull($empty['selectedOccurrenceId']);
        self::assertSame([], $empty['occurrences']);
        self::assertSame(0, $empty['occurrencePage']['total']);
    }

    public function test_catalogue_rejects_cross_actor_and_cross_event_cursors_and_rechecks_current_authority(): void
    {
        $this->history(40);
        $query = app(EventOccurrenceCatalogueQuery::class);
        $cursor = $query->forEvent($this->actor, (string) $this->event->id)['nextCursor'];
        self::assertIsString($cursor);
        $factory = app(ScenarioFactory::class);
        $other = $factory->player((int) $factory->authUser()->id, 61734);
        $alliance = $factory->alliance($other);
        $factory->roster($other, $alliance);
        $foreign = app(CreateEvent::class)->handle($other->playerId, (string) $this->event->event_type_scope_id,
            EventScope::Alliance, $alliance->allianceId, CarbonImmutable::now('UTC')->addDay(), durationMinutes: 60);
        try {
            $query->forEvent($other, $foreign->eventId, $cursor);
            self::fail('Cursors cannot cross current actor and Event scope.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('cursor', $exception->errors());
        }
        $sameActorEvent = app(CreateEvent::class)->handle($this->actor->playerId, (string) $this->event->event_type_scope_id,
            EventScope::Alliance, (string) $this->event->alliance_id, CarbonImmutable::now('UTC')->addDay(), durationMinutes: 60);
        try {
            $query->forEvent($this->actor, $sameActorEvent->eventId, $cursor);
            self::fail('An authorized actor cannot reuse the cursor for another Event.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('cursor', $exception->errors());
        }
        try {
            $query->forEvent($this->actor, (string) $this->event->id, 'corrupt');
            self::fail('Malformed cursor must reject.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('cursor', $exception->errors());
        }
        // Read-side admission uses current owner rows, never the earlier actor snapshot.
        DB::table('alliance_memberships')->where('alliance_id', $this->event->alliance_id)
            ->where('player_id', $this->actor->playerId)->update(['status' => 'left']);
        $loaded = 0;
        EventOccurrence::retrieved(static function () use (&$loaded): void {
            $loaded++;
        });
        try {
            $query->forEvent($this->actor, (string) $this->event->id, $cursor);
            self::fail('Revoked current membership must reject.');
        } catch (AuthorizationException) {
            self::assertSame(0, $loaded);
        }
    }

    public function test_large_history_does_not_hydrate_for_admission_or_command_selection_and_off_page_selection_remains_available(): void
    {
        $history = $this->history(1000);
        $loaded = 0;
        EventOccurrence::retrieved(static function () use (&$loaded): void {
            $loaded++;
        });
        $event = app(EventCalendarQuery::class)->eventForManage($this->actor, (string) $this->event->id);
        self::assertSame(0, $loaded);
        $command = app(EventCommandQuery::class)->forEvent($this->actor, $event);
        self::assertSame($this->selected, $command['selectedOccurrenceId']);
        self::assertSame(1001, $command['occurrencePage']['total']);
        self::assertLessThanOrEqual(28, $loaded);
        self::assertLessThanOrEqual(26, count($command['occurrences']));
        $loaded = 0;
        $explicit = app(EventCommandQuery::class)->forEvent($this->actor, $event, $history[999]);
        self::assertSame($history[999], $explicit['selectedOccurrenceId']);
        self::assertNull($explicit['state']);
        self::assertLessThanOrEqual(27, $loaded);
        self::assertContains($history[999], array_column($explicit['occurrences'], 'id'));
    }

    public function test_management_composes_only_the_explicit_occurrence_and_exports_the_complete_history_total(): void
    {
        $history = $this->history(100);
        $this->get('/events/'.$this->event->id.'/manage?occurrence='.$history[99])
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Operations/Events/Manage')
            ->has('event.occurrences', 1)->where('event.occurrences.0.id', $history[99])
            ->where('event.occurrenceCount', 101)->where('eventCommand.selectedOccurrenceId', $history[99])
            ->has('operations', 1)->where('operations.0.occurrenceId', $history[99])
            ->has('rosterOperations', 1)->where('rosterOperations.0.occurrenceId', $history[99])
            ->has('rallyOperations', 1)->where('rallyOperations.0.occurrenceId', $history[99])
            ->has('resultOperations', 1)->where('resultOperations.0.occurrenceId', $history[99]));
    }

    /** @return list<string> */
    private function history(int $count, bool $sameDay = false): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $start = $sameDay ? now()->subDays(2)->startOfDay()->addSeconds($i) : now()->subDays($i + 2)->startOfSecond();
            $rows[] = ['id' => strtolower((string) Str::ulid()), 'event_id' => $this->event->id, 'starts_at' => $start,
                'ends_at' => $start->copy()->addHour(), 'status' => 'cancelled', 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('event_occurrences')->insert($rows);

        return array_column($rows, 'id');
    }
}
