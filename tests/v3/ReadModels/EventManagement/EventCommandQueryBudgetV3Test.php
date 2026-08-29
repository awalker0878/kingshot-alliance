<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\EventManagement;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderTrigger;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use App\ReadModels\EventManagement\Queries\EventCommandQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class EventCommandQueryBudgetV3Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_selected_occurrence_query_count_does_not_grow_with_governor_population(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 18:00:00', 'UTC'));
        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $actor = $scenario->player((int) $user->id, 76002);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $event = $this->bearHunt($actor, $alliance);

        $small = $this->queryCount($actor, $event);

        foreach (range(1, 30) as $index) {
            $memberUser = $scenario->authUser('event-command-budget-'.$index.'@example.test');
            $member = $scenario->player((int) $memberUser->id, 76002);
            $scenario->roster($actor, $alliance, $member);
        }

        $large = $this->queryCount($actor, $event);

        self::assertLessThanOrEqual(
            $small + 2,
            $large,
            sprintf('Event Command queries grew with roster population: small=%d large=%d', $small, $large),
        );
        self::assertLessThanOrEqual(50, $large, 'Event Command selected-occurrence query budget regressed.');
    }

    private function bearHunt(PlayerReference $actor, AllianceReference $alliance): Event
    {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas(
                'eventType',
                static fn ($query) => $query->where('slug', 'bear-hunt'),
            )
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(2),
            title: 'Event Command Query Budget',
            durationMinutes: 60,
        );

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

        return Event::query()->findOrFail($created->eventId);
    }

    private function queryCount(PlayerReference $actor, Event $event): int
    {
        $loaded = Event::query()
            ->with(['eventType.workflowDimensions', 'typeScope', 'occurrences'])
            ->findOrFail($event->id);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            app(EventCommandQuery::class)->forEvent($actor, $loaded);

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
