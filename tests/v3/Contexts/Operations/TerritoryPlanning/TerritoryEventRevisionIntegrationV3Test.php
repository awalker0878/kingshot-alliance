<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\TerritoryPlanning;

use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\TerritoryPlanning\Actions\AttachTerritoryPlanRevisionToEvent;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\DetachTerritoryPlanRevisionFromEvent;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\EventTerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TerritoryEventRevisionIntegrationV3Test extends TestCase
{
    use RefreshDatabase;

    private const DATASET_ID = 'kingshot-community-observed-2026-08-21-v1';

    public function test_event_positioning_pins_replaces_and_detaches_immutable_published_revisions(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 62001);
        $alliance = $scenario->alliance($actor);
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();

        $event = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            title: 'Bear Hunt Positioning',
            durationMinutes: 60,
        );
        self::assertNotNull($event->firstOccurrenceId);

        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Alliance,
            $actor->kingdomId,
            $alliance->allianceId,
            'Event Hive Positioning',
            self::DATASET_ID,
        );
        $saved = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $created->revision,
            $this->layers($alliance->allianceId, $alliance->name),
            [],
            [$this->city(100)],
        );
        $firstPublished = app(PublishTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
        );
        self::assertNotNull($firstPublished->publishedRevisionId);

        $linkId = app(AttachTerritoryPlanRevisionToEvent::class)->handle(
            $actor->playerId,
            $event->firstOccurrenceId,
            $firstPublished->publishedRevisionId,
            'positioning',
        );
        $link = EventTerritoryPlanRevision::query()->findOrFail($linkId);
        self::assertSame($firstPublished->publishedRevisionId, (string) $link->territory_plan_revision_id);

        $resaved = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
            $this->layers($alliance->allianceId, $alliance->name),
            [],
            [$this->city(200)],
        );

        $link->refresh();
        self::assertSame($firstPublished->publishedRevisionId, (string) $link->territory_plan_revision_id);
        $firstRevision = TerritoryPlanRevision::query()->findOrFail($firstPublished->publishedRevisionId);
        $snapshot = $firstRevision->snapshot;
        self::assertIsArray($snapshot);
        self::assertSame(100, $snapshot['objects'][0]['x'] ?? null);

        $secondPublished = app(PublishTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $resaved->revision,
        );
        self::assertNotNull($secondPublished->publishedRevisionId);
        $replacedLinkId = app(AttachTerritoryPlanRevisionToEvent::class)->handle(
            $actor->playerId,
            $event->firstOccurrenceId,
            $secondPublished->publishedRevisionId,
            'positioning',
        );

        self::assertSame($linkId, $replacedLinkId);
        self::assertSame(1, EventTerritoryPlanRevision::query()->count());
        self::assertSame(
            $secondPublished->publishedRevisionId,
            (string) EventTerritoryPlanRevision::query()->findOrFail($linkId)->territory_plan_revision_id,
        );

        app(DetachTerritoryPlanRevisionFromEvent::class)->handle(
            $actor->playerId,
            $event->firstOccurrenceId,
            'positioning',
        );
        self::assertSame(0, EventTerritoryPlanRevision::query()->count());
    }

    /** @return list<array<string, mixed>> */
    private function layers(string $allianceId, string $name): array
    {
        return [[
            'key' => 'owner',
            'alliance_id' => $allianceId,
            'external_name' => null,
            'display_name' => $name,
            'presentation_color' => '#4da3ff',
        ]];
    }

    /** @return array<string, mixed> */
    private function city(int $coordinate): array
    {
        return [
            'key' => 'event-city',
            'alliance_key' => 'owner',
            'group_key' => null,
            'type' => 'governor_city',
            'player_id' => null,
            'external_player_name' => 'Event Governor',
            'label' => 'Event Governor',
            'x' => $coordinate,
            'y' => $coordinate,
            'rotation' => 0,
            'sort_order' => 0,
            'metadata' => [],
        ];
    }
}
