<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\TerritoryPlanning;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCompleteness;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCoverageKind;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedIdentityState;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedObjectType;
use App\Contexts\Intelligence\Observations\Models\SpatialObservation;
use App\Contexts\Intelligence\Observations\Models\SpatialObservedObject;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\ReadModels\TerritoryPlanning\Queries\TerritoryReconciliationQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TerritoryReconciliationBoundariesV3Test extends TestCase
{
    use RefreshDatabase;

    private const DATASET_ID = 'kingshot-community-observed-2026-08-21-v1';

    public function test_unauthorized_actor_is_rejected_before_observation_history_is_retrieved(): void
    {
        [$owner, $alliance, $planId, $revisionId] = $this->publishedPlan(62101);
        $this->recordObservation(
            $alliance->allianceId,
            $owner->kingdomId,
            $owner->playerId,
            2,
        );

        $scenario = new ScenarioFactory;
        $otherAccount = $scenario->authUser();
        $otherActor = $scenario->player((int) $otherAccount->id, 62102);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            app(TerritoryReconciliationQuery::class)->build(
                actorPlayerId: $otherActor->playerId,
                planId: $planId,
                revisionId: $revisionId,
                allianceId: $alliance->allianceId,
            );
            self::fail('Unauthorized Territory reconciliation should fail closed.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            $queries = array_column(DB::getQueryLog(), 'query');
            self::assertFalse(
                collect($queries)->contains(
                    static fn (string $sql): bool => str_contains($sql, 'spatial_observations'),
                ),
                'Observation history was queried before Territory plan authorization completed.',
            );
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_incompatible_observation_coordinate_system_fails_closed(): void
    {
        [$actor, $alliance, $planId, $revisionId] = $this->publishedPlan(62103);
        $realDatasets = app(KingdomMapDatasetQuery::class);
        $baseDataset = $realDatasets->require(self::DATASET_ID);
        $incompatibleId = 'test-incompatible-coordinate-system';
        $incompatibleChecksum = str_repeat('a', 64);
        $data = $baseDataset->data;
        $data['coordinate_system'] = [
            'origin' => 'test-opposite-origin',
            'axis_x' => 'test-reversed-x',
            'axis_y' => 'test-reversed-y',
        ];
        $incompatible = new KingdomMapDataset(
            id: $incompatibleId,
            schemaVersion: $baseDataset->schemaVersion,
            observedAt: $baseDataset->observedAt,
            sourceLabel: 'Test incompatible coordinate system',
            sourceUri: null,
            confidence: $baseDataset->confidence,
            checksum: $incompatibleChecksum,
            data: $data,
        );
        $this->app->instance(
            KingdomMapDatasetQuery::class,
            new class($realDatasets, $incompatible) extends KingdomMapDatasetQuery
            {
                public function __construct(
                    private readonly KingdomMapDatasetQuery $delegate,
                    private readonly KingdomMapDataset $incompatible,
                ) {}

                public function require(string $datasetId, ?string $expectedChecksum = null): KingdomMapDataset
                {
                    if ($datasetId !== $this->incompatible->id) {
                        return $this->delegate->require($datasetId, $expectedChecksum);
                    }
                    if ($expectedChecksum !== null
                        && ! hash_equals($this->incompatible->checksum, $expectedChecksum)) {
                        throw new \RuntimeException('The test incompatible dataset checksum did not match.');
                    }

                    return $this->incompatible;
                }
            },
        );
        $observation = SpatialObservation::query()->create([
            'alliance_id' => $alliance->allianceId,
            'kingdom_id' => $actor->kingdomId,
            'captured_at' => CarbonImmutable::now('UTC')->subMinutes(5),
            'coverage_kind' => SpatialObservationCoverageKind::CompleteHive,
            'completeness' => SpatialObservationCompleteness::Complete,
            'coverage_bounds' => null,
            'map_dataset_id' => $incompatibleId,
            'map_dataset_checksum' => $incompatibleChecksum,
            'source' => 'screenshot_evidence',
            'source_evidence_id' => null,
            'source_review_id' => null,
            'destination_idempotency_key' => hash('sha256', 'incompatible-observation'),
            'accepted_by_player_id' => $actor->playerId,
            'accepted_at' => now(),
        ]);

        $result = app(TerritoryReconciliationQuery::class)->build(
            actorPlayerId: $actor->playerId,
            planId: $planId,
            revisionId: $revisionId,
            allianceId: $alliance->allianceId,
            observationId: (string) $observation->id,
        );

        self::assertSame('dataset_incompatible', $result['state']);
        self::assertSame('dataset_incompatible', $result['compatibility']);
        self::assertArrayNotHasKey('governors', $result);
        self::assertArrayNotHasKey('structures', $result);
        self::assertArrayNotHasKey('summary', $result);
    }

    public function test_reconciliation_query_budget_does_not_scale_with_observed_object_count(): void
    {
        [$actor, $alliance, $planId, $revisionId] = $this->publishedPlan(62104);
        $smallObservationId = $this->recordObservation(
            $alliance->allianceId,
            $actor->kingdomId,
            $actor->playerId,
            2,
        );
        $largeObservationId = $this->recordObservation(
            $alliance->allianceId,
            $actor->kingdomId,
            $actor->playerId,
            120,
        );

        $smallQueries = $this->countQueries(fn (): array => app(TerritoryReconciliationQuery::class)->build(
            actorPlayerId: $actor->playerId,
            planId: $planId,
            revisionId: $revisionId,
            allianceId: $alliance->allianceId,
            observationId: $smallObservationId,
        ));
        $largeQueries = $this->countQueries(fn (): array => app(TerritoryReconciliationQuery::class)->build(
            actorPlayerId: $actor->playerId,
            planId: $planId,
            revisionId: $revisionId,
            allianceId: $alliance->allianceId,
            observationId: $largeObservationId,
        ));

        self::assertLessThanOrEqual($smallQueries + 2, $largeQueries);
        self::assertLessThanOrEqual(20, $largeQueries);
    }

    /** @return array{0:mixed,1:mixed,2:string,3:string} */
    private function publishedPlan(int $kingdomNumber): array
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, $kingdomNumber);
        $alliance = $scenario->alliance($actor);
        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Alliance,
            $actor->kingdomId,
            $alliance->allianceId,
            'Reconciliation boundary hive',
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
                'external_tag' => null,
                'display_name' => $alliance->name,
                'presentation_color' => '#4da3ff',
                'sort_order' => 0,
                'visible' => true,
                'locked' => false,
            ]],
            [],
            [
                $this->plannedObject('hq', 'headquarters', 100, 100, null, 0),
                $this->plannedObject('banner', 'banner', 108, 100, null, 1),
                $this->plannedObject('city-alpha', 'governor_city', 104, 104, 'Governor Alpha', 2),
                $this->plannedObject('city-beta', 'governor_city', 104, 108, 'Governor Beta', 3),
            ],
        );
        $published = app(PublishTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
        );
        self::assertNotNull($published->publishedRevisionId);

        return [$actor, $alliance, $created->planId, (string) $published->publishedRevisionId];
    }

    /** @return array<string,mixed> */
    private function plannedObject(
        string $key,
        string $type,
        int $x,
        int $y,
        ?string $governor,
        int $sortOrder,
    ): array {
        return [
            'key' => $key,
            'alliance_key' => 'owner',
            'group_key' => null,
            'type' => $type,
            'player_id' => null,
            'external_player_name' => $governor,
            'label' => $governor ?? $key,
            'x' => $x,
            'y' => $y,
            'rotation' => 0,
            'sort_order' => $sortOrder,
            'metadata' => [],
        ];
    }

    private function recordObservation(
        string $allianceId,
        string $kingdomId,
        string $acceptedByPlayerId,
        int $objectCount,
    ): string {
        $dataset = app(KingdomMapDatasetQuery::class)->require(self::DATASET_ID);
        $observation = SpatialObservation::query()->create([
            'alliance_id' => $allianceId,
            'kingdom_id' => $kingdomId,
            'captured_at' => CarbonImmutable::now('UTC')->subMinutes($objectCount),
            'coverage_kind' => SpatialObservationCoverageKind::PartialRegion,
            'completeness' => SpatialObservationCompleteness::Partial,
            'coverage_bounds' => null,
            'map_dataset_id' => $dataset->id,
            'map_dataset_checksum' => $dataset->checksum,
            'source' => 'screenshot_evidence',
            'source_evidence_id' => null,
            'source_review_id' => null,
            'destination_idempotency_key' => hash('sha256', implode(':', [
                $allianceId,
                $kingdomId,
                (string) $objectCount,
            ])),
            'accepted_by_player_id' => $acceptedByPlayerId,
            'accepted_at' => now(),
        ]);
        for ($index = 0; $index < $objectCount; $index++) {
            SpatialObservedObject::query()->create([
                'spatial_observation_id' => $observation->id,
                'object_key' => 'unexpected-'.$objectCount.'-'.$index,
                'object_type' => SpatialObservedObjectType::GovernorCity,
                'coordinate_x' => 200 + ($index % 20) * 10,
                'coordinate_y' => 200 + intdiv($index, 20) * 10,
                'player_id' => null,
                'plan_local_identity' => null,
                'observed_label' => 'Observed '.$index,
                'identity_state' => SpatialObservedIdentityState::Unresolved,
                'confidence' => 0.95,
                'source_metadata' => [],
            ]);
        }

        return (string) $observation->id;
    }

    /** @param  callable():array<string,mixed>  $callback */
    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $callback();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
