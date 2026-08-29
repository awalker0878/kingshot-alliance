<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\EventManagement;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\ReadModels\EventManagement\Queries\RallyRosterBuilderQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class RallyRosterBuilderV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_builder_derives_factual_assignment_gaps_without_persisting_attention_state(): void
    {
        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $actor = $scenario->player((int) $user->id, 77001);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $otherUser = $scenario->authUser('rally-builder-other@example.test');
        $other = $scenario->player((int) $otherUser->id, 77001);
        $scenario->roster($actor, $alliance, $other);
        $event = $this->bearHunt($actor, $alliance->allianceId);
        $occurrenceId = (string) $event->occurrences()->firstOrFail()->id;

        $rallies = [[
            'occurrenceId' => $occurrenceId,
            'startsAt' => CarbonImmutable::now('UTC')->addDay()->toIso8601String(),
            'groups' => [
                [
                    'id' => 'rally-1',
                    'maxJoiners' => 2,
                    'assignments' => [[
                        'id' => 'assignment-1',
                        'playerId' => $actor->playerId,
                        'role' => 'lead',
                        'status' => 'confirmed',
                    ]],
                ],
                [
                    'id' => 'rally-2',
                    'maxJoiners' => 1,
                    'assignments' => [
                        [
                            'id' => 'assignment-2',
                            'playerId' => $actor->playerId,
                            'role' => 'joiner',
                            'status' => 'assigned',
                        ],
                        [
                            'id' => 'assignment-3',
                            'playerId' => $other->playerId,
                            'role' => 'joiner',
                            'status' => 'declined',
                        ],
                    ],
                ],
            ],
        ]];
        $participants = [
            ['occurrenceId' => $occurrenceId, 'playerId' => $actor->playerId, 'registration' => 'registered'],
            ['occurrenceId' => $occurrenceId, 'playerId' => $other->playerId, 'registration' => 'registered'],
        ];

        $projection = app(RallyRosterBuilderQuery::class)->forEvent(
            $actor->playerId,
            $event,
            $rallies,
            $participants,
            [],
        )[0];

        self::assertSame('needs_attention', $projection['state']);
        self::assertSame(2, $projection['groupCount']);
        self::assertSame(2, $projection['assignmentCount']);
        self::assertSame('available', $projection['observationState']);
        self::assertSame([
            'registered_or_rostered_unassigned',
            'assigned_to_multiple_groups',
            'group_missing_lead',
            'assignment_declined',
            'joiner_capacity_gap',
            'standby_not_assigned',
            'governor_observation_unknown',
        ], array_column($projection['issues'], 'code'));

        self::assertArrayNotHasKey('rally_ready', $event->getAttributes());
        self::assertArrayNotHasKey('attention_state', $event->getAttributes());
    }

    private function bearHunt(PlayerReference $actor, string $allianceId): Event
    {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            title: 'Rally Builder',
            durationMinutes: 60,
        );

        return Event::query()->with('occurrences')->findOrFail($created->eventId);
    }
}
