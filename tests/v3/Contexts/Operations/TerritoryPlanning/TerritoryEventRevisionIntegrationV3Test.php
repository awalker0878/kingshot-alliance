<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\TerritoryPlanning;

use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\TerritoryPlanning\Actions\AttachTerritoryPlanRevisionToEvent;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
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

    public function test_event_link_pins_published_revision_when_live_plan_changes(): void
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
            [[
                'key' => 'owner',
                'alliance_id' => $alliance->allianceId,
                'external_name' => null,
                'display_name' => $alliance->name,
                'presentation_color' => '#4da3ff',
            ]],
            [],
            [$this->city(100)],
        );
        $published = app(PublishTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
        );
        self::assertNotNull($published->publishedRevisionId);

        $linkId = app(AttachTerritoryPlanRevisionToEvent::class)->handle(
            $actor->playerId,
            $event->firstOccurrenceId,
            $published->publishedRevisionId,
            'positioning',
        );
        $link = EventTerritoryPlanRevision::query()->findOrFail($linkId);
        self::assertSame($published->publishedRevisionId, (string) $link->territory_plan_revision_id);

        app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
            [[
                'key' => 'owner',
                'alliance_id' => $alliance->allianceId,
                'external_name' => null,
                'display_name' => $alliance->name,
                'presentation_color' => '#4da3ff',
            ]],
            [],
            [$this->city(200)],
        );

        $link->refresh();
        self::assertSame($published->publishedRevisionId, (string) $link->territory_plan_revision_id);
        $revision = TerritoryPlanRevision::query()->findOrFail($published->publishedRevisionId);
        $snapshot = $revision->snapshot;
        self::assertIsArray($snapshot);
        self::assertSame(100, $snapshot['objects'][0]['x'] ?? null);
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
