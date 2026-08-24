<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\TerritoryPlanning;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\TerritoryPlanning\Actions\AttachTerritoryPlanRevisionToEvent;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Queries\PublishedEventTerritoryRevisionQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class PublishedEventTerritoryRevisionQueryV3Test extends TestCase
{
    use RefreshDatabase;

    private const DATASET_ID = 'kingshot-community-observed-2026-08-21-v1';

    public function test_projection_returns_only_attached_published_revision_and_ignores_new_mutable_head(): void
    {
        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $actor = $scenario->player((int) $user->id, 64401);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->bearHunt($actor, $alliance, 'Bear Hunt');

        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Alliance,
            $actor->kingdomId,
            $alliance->allianceId,
            'Published Bear Hive',
            self::DATASET_ID,
        );
        $saved = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $created->revision,
            $this->layers($alliance),
            [],
            [$this->city(100)],
        );
        $published = app(PublishTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
        );
        self::assertNotNull($published->publishedRevisionId);
        app(AttachTerritoryPlanRevisionToEvent::class)->handle(
            $actor->playerId,
            (string) $occurrence->id,
            $published->publishedRevisionId,
            'positioning',
        );

        $newMutableHead = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
            $this->layers($alliance),
            [],
            [$this->city(250)],
        );
        self::assertGreaterThan($saved->revision, $newMutableHead->revision);

        $rows = app(PublishedEventTerritoryRevisionQuery::class)->forOccurrence(
            $actor->playerId,
            $occurrence,
            'positioning',
        );

        self::assertCount(1, $rows);
        self::assertSame($published->publishedRevisionId, $rows[0]['revisionId'] ?? null);
        self::assertSame('Published Bear Hive', $rows[0]['planName'] ?? null);
        self::assertSame('positioning', $rows[0]['purpose'] ?? null);
        self::assertSame(self::DATASET_ID, $rows[0]['mapDatasetId'] ?? null);
        self::assertNotSame($newMutableHead->revision, $rows[0]['revisionNumber'] ?? null);
    }

    public function test_projection_does_not_disclose_attachment_to_governor_without_event_scope(): void
    {
        $scenario = new ScenarioFactory;
        $ownerUser = $scenario->authUser();
        $owner = $scenario->player((int) $ownerUser->id, 64402);
        $alliance = $scenario->alliance($owner);
        $scenario->roster($owner, $alliance);
        $occurrence = $this->bearHunt($owner, $alliance, 'Private Bear Hunt');

        $created = app(CreateTerritoryPlan::class)->handle(
            $owner->playerId,
            TerritoryPlanScope::Alliance,
            $owner->kingdomId,
            $alliance->allianceId,
            'Private Hive',
            self::DATASET_ID,
        );
        $saved = app(SaveTerritoryPlan::class)->handle(
            $owner->playerId,
            $created->planId,
            $created->revision,
            $this->layers($alliance),
            [],
            [$this->city(150)],
        );
        $published = app(PublishTerritoryPlan::class)->handle(
            $owner->playerId,
            $created->planId,
            $saved->revision,
        );
        self::assertNotNull($published->publishedRevisionId);
        app(AttachTerritoryPlanRevisionToEvent::class)->handle(
            $owner->playerId,
            (string) $occurrence->id,
            $published->publishedRevisionId,
            'positioning',
        );

        $otherUser = $scenario->authUser();
        $other = $scenario->player((int) $otherUser->id, 64403);
        $otherAlliance = $scenario->alliance($other);
        $scenario->roster($other, $otherAlliance);

        self::assertSame(
            [],
            app(PublishedEventTerritoryRevisionQuery::class)->forOccurrence(
                $other->playerId,
                $occurrence,
                'positioning',
            ),
        );
    }

    /** @return list<array<string,mixed>> */
    private function layers(AllianceReference $alliance): array
    {
        return [[
            'key' => 'owner',
            'alliance_id' => $alliance->allianceId,
            'external_name' => null,
            'display_name' => $alliance->name,
            'presentation_color' => '#4da3ff',
        ]];
    }

    /** @return array<string,mixed> */
    private function city(int $coordinate): array
    {
        return [
            'key' => 'assistant-city-'.$coordinate,
            'alliance_key' => 'owner',
            'group_key' => null,
            'type' => 'governor_city',
            'player_id' => null,
            'external_player_name' => 'Assistant Governor',
            'label' => 'Assistant Governor',
            'x' => $coordinate,
            'y' => $coordinate,
            'rotation' => 0,
            'sort_order' => 0,
            'metadata' => [],
        ];
    }

    private function bearHunt(
        PlayerReference $actor,
        AllianceReference $alliance,
        string $title,
    ): EventOccurrence {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay()->startOfHour(),
            title: $title,
            durationMinutes: 60,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return EventOccurrence::query()->with('event')->findOrFail($created->firstOccurrenceId);
    }
}
