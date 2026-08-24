<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\Participation;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Operations\Participation\Enums\EventResponseSource;
use App\Contexts\Operations\Participation\Models\EventResponse;
use App\Contexts\Operations\Participation\Queries\EventParticipationQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class EventParticipationQueryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_batch_projection_returns_only_the_requested_governors_participation(): void
    {
        $scenario = new ScenarioFactory;
        $actorUser = $scenario->authUser();
        $actor = $scenario->player((int) $actorUser->id, 64201);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);

        $otherUser = $scenario->authUser();
        $other = $scenario->player((int) $otherUser->id, 64201);
        $scenario->roster($actor, $alliance, $other);

        $occurrence = $this->swordland($actor, $alliance);
        $actorResponse = $this->response($occurrence, $actor, EventResponseChoice::Going);
        $otherResponse = $this->response($occurrence, $other, EventResponseChoice::Unavailable);

        $rows = app(EventParticipationQuery::class)->forPlayerOccurrences(
            [(string) $occurrence->id],
            $actor,
        );

        self::assertArrayHasKey((string) $occurrence->id, $rows);
        self::assertSame((string) $actorResponse->id, $rows[(string) $occurrence->id]['response']['id'] ?? null);
        self::assertSame('going', $rows[(string) $occurrence->id]['response']['response'] ?? null);

        $serialized = json_encode($rows, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString((string) $otherResponse->id, $serialized);
        self::assertStringNotContainsString('unavailable', $serialized);
    }

    public function test_batch_projection_is_bounded_to_requested_occurrences(): void
    {
        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $actor = $scenario->player((int) $user->id, 64202);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);

        $requested = $this->swordland($actor, $alliance, 'Requested Swordland');
        $hidden = $this->swordland($actor, $alliance, 'Other Swordland');
        $this->response($requested, $actor, EventResponseChoice::Going);
        $hiddenResponse = $this->response($hidden, $actor, EventResponseChoice::Maybe);

        $rows = app(EventParticipationQuery::class)->forPlayerOccurrences(
            [(string) $requested->id],
            $actor,
        );

        self::assertSame([(string) $requested->id], array_keys($rows));
        self::assertStringNotContainsString(
            (string) $hiddenResponse->id,
            json_encode($rows, JSON_THROW_ON_ERROR),
        );
    }

    private function response(
        EventOccurrence $occurrence,
        PlayerReference $player,
        EventResponseChoice $choice,
    ): EventResponse {
        return EventResponse::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'player_id' => $player->playerId,
            'response' => $choice->value,
            'source' => EventResponseSource::Self->value,
            'responded_by_player_id' => $player->playerId,
            'responded_at' => now(),
        ]);
    }

    private function swordland(
        PlayerReference $actor,
        AllianceReference $alliance,
        string $title = 'Swordland',
    ): EventOccurrence {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'swordland-showdown'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(2)->startOfHour(),
            title: $title,
            durationMinutes: 60,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
    }
}
