<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\TerritoryPlanning;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
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
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\ReadModels\TerritoryPlanning\Queries\TerritoryReconciliationQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TerritoryPlanObservedRealityV3Test extends TestCase
{
    use RefreshDatabase;

    private const DATASET_ID = 'kingshot-community-observed-2026-08-21-v1';

    public function test_partial_observation_preserves_unknown_absence_and_does_not_mutate_published_plan(): void
    {
        [$actor, $alliance, $planId, $revisionId] = $this->publishedPlan(62001);
        $published = TerritoryPlanRevision::query()->findOrFail($revisionId);
        $snapshotBefore = $published->snapshot;
        $checksumBefore = (string) $published->snapshot_checksum;

        $observationId = $this->recordObservation(
            allianceId: $alliance->allianceId,
            kingdomId: $actor->kingdomId,
            acceptedByPlayerId: $actor->playerId,
            capturedAt: CarbonImmutable::now('UTC')->subMinutes(15),
            coverageKind: SpatialObservationCoverageKind::PartialRegion,
            completeness: SpatialObservationCompleteness::Partial,
            objects: [
                $this->observedGovernor('governor-alpha-observed', 108, 104, 'Governor Alpha'),
                $this->observedGovernor(
                    'unexpected-city',
                    120,
                    120,
                    null,
                    SpatialObservedIdentityState::Unresolved,
                    'Unknown Governor',
                ),
            ],
        );

        $result = app(TerritoryReconciliationQuery::class)->build(
            actorPlayerId: $actor->playerId,
            planId: $planId,
            revisionId: $revisionId,
            allianceId: $alliance->allianceId,
            observationId: $observationId,
        );

        self::assertSame('ready', $result['state']);
        self::assertSame('fresh', $result['freshness']['state']);
        $governors = collect($result['governors'])->keyBy(
            static fn (array $row): string => (string) $row['planned']['key'],
        );
        $alpha = $governors->get('city-alpha');
        $beta = $governors->get('city-beta');
        self::assertIsArray($alpha);
        self::assertIsArray($beta);
        self::assertSame('out_of_position', $alpha['status']);
        self::assertSame(4.0, $alpha['distance_tiles']);
        self::assertTrue($alpha['planned_covered']);
        self::assertNull($alpha['observed_covered']);
        self::assertSame('unknown', $alpha['coverage_delta']);
        self::assertSame('not_observed', $beta['status']);
        self::assertSame(0, $result['summary']['missing']);
        self::assertSame(1, $result['summary']['not_observed']);
        self::assertSame(1, $result['summary']['unexpected']);
        self::assertSame('identity_unresolved', $result['unexpected'][0]['status']);

        $published->refresh();
        self::assertSame($checksumBefore, (string) $published->snapshot_checksum);
        self::assertSame($snapshotBefore, $published->snapshot);
    }

    public function test_complete_hive_proves_missing_and_banner_ties_remain_ambiguous(): void
    {
        [$actor, $alliance, $planId, $revisionId] = $this->publishedPlan(62002);
        $observationId = $this->recordObservation(
            allianceId: $alliance->allianceId,
            kingdomId: $actor->kingdomId,
            acceptedByPlayerId: $actor->playerId,
            capturedAt: CarbonImmutable::now('UTC')->subHours(7),
            coverageKind: SpatialObservationCoverageKind::CompleteHive,
            completeness: SpatialObservationCompleteness::Complete,
            objects: [
                $this->observedGovernor('governor-alpha-observed', 104, 104, 'Governor Alpha'),
                $this->observedStructure('banner-left', SpatialObservedObjectType::Banner, 107, 100),
                $this->observedStructure('banner-right', SpatialObservedObjectType::Banner, 109, 100),
            ],
        );

        $result = app(TerritoryReconciliationQuery::class)->build(
            actorPlayerId: $actor->playerId,
            planId: $planId,
            revisionId: $revisionId,
            allianceId: $alliance->allianceId,
            observationId: $observationId,
        );

        self::assertSame('ready', $result['state']);
        self::assertSame('stale', $result['freshness']['state']);
        $governors = collect($result['governors'])->keyBy(
            static fn (array $row): string => (string) $row['planned']['key'],
        );
        self::assertSame('in_position', $governors->get('city-alpha')['status']);
        self::assertSame('missing', $governors->get('city-beta')['status']);
        self::assertSame(1, $result['summary']['missing']);

        $structures = collect($result['structures'])->keyBy(
            static fn (array $row): string => (string) $row['planned']['key'],
        );
        $banner = $structures->get('banner');
        self::assertIsArray($banner);
        self::assertSame('ambiguous', $banner['status']);
        self::assertEqualsCanonicalizing(
            ['banner-left', 'banner-right'],
            $banner['ambiguous_observed_keys'],
        );
        self::assertNotContains(
            'banner-left',
            array_column($result['unexpected'], 'key'),
        );
        self::assertNotContains(
            'banner-right',
            array_column($result['unexpected'], 'key'),
        );
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
            'Observed Reality Hive',
            self::DATASET_ID,
        );
        $saved = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $created->revision,
            $this->allianceLayers($alliance->allianceId, $alliance->name),
            [],
            $this->plannedObjects(),
        );
        $published = app(PublishTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
        );
        self::assertNotNull($published->publishedRevisionId);

        return [$actor, $alliance, $created->planId, (string) $published->publishedRevisionId];
    }

    /** @return list<array<string,mixed>> */
    private function allianceLayers(string $allianceId, string $name): array
    {
        return [[
            'key' => 'owner',
            'alliance_id' => $allianceId,
            'external_name' => null,
            'external_tag' => null,
            'display_name' => $name,
            'presentation_color' => '#4da3ff',
            'sort_order' => 0,
            'visible' => true,
            'locked' => false,
        ]];
    }

    /** @return list<array<string,mixed>> */
    private function plannedObjects(): array
    {
        return [
            $this->plannedObject('hq', 'headquarters', 100, 100, null, 'HQ', 0),
            $this->plannedObject('banner', 'banner', 108, 100, null, 'Banner', 1),
            $this->plannedObject('city-alpha', 'governor_city', 104, 104, 'Governor Alpha', 'Alpha', 2),
            $this->plannedObject('city-beta', 'governor_city', 104, 108, 'Governor Beta', 'Beta', 3),
            $this->plannedObject('trap', 'bear_trap', 116, 116, null, 'Bear Trap', 4),
        ];
    }

    /** @return array<string,mixed> */
    private function plannedObject(
        string $key,
        string $type,
        int $x,
        int $y,
        ?string $governor,
        string $label,
        int $sortOrder,
    ): array {
        return [
            'key' => $key,
            'alliance_key' => 'owner',
            'group_key' => null,
            'type' => $type,
            'player_id' => null,
            'external_player_name' => $governor,
            'label' => $label,
            'x' => $x,
            'y' => $y,
            'rotation' => 0,
            'sort_order' => $sortOrder,
            'metadata' => [],
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $objects
     */
    private function recordObservation(
        string $allianceId,
        string $kingdomId,
        string $acceptedByPlayerId,
        CarbonImmutable $capturedAt,
        SpatialObservationCoverageKind $coverageKind,
        SpatialObservationCompleteness $completeness,
        array $objects,
    ): string {
        $dataset = app(KingdomMapDatasetQuery::class)->require(self::DATASET_ID);
        $observation = SpatialObservation::query()->create([
            'alliance_id' => $allianceId,
            'kingdom_id' => $kingdomId,
            'captured_at' => $capturedAt,
            'coverage_kind' => $coverageKind,
            'completeness' => $completeness,
            'coverage_bounds' => null,
            'map_dataset_id' => $dataset->id,
            'map_dataset_checksum' => $dataset->checksum,
            'source' => 'screenshot_evidence',
            'source_evidence_id' => null,
            'source_review_id' => null,
            'destination_idempotency_key' => hash('sha256', implode(':', [
                $allianceId,
                $kingdomId,
                $capturedAt->toIso8601String(),
                (string) count($objects),
            ])),
            'accepted_by_player_id' => $acceptedByPlayerId,
            'accepted_at' => now(),
        ]);
        foreach ($objects as $object) {
            SpatialObservedObject::query()->create([
                'spatial_observation_id' => $observation->id,
                'object_key' => $object['key'],
                'object_type' => $object['type'],
                'coordinate_x' => $object['x'],
                'coordinate_y' => $object['y'],
                'player_id' => $object['player_id'] ?? null,
                'plan_local_identity' => $object['plan_local_identity'] ?? null,
                'observed_label' => $object['observed_label'] ?? null,
                'identity_state' => $object['identity_state'],
                'confidence' => $object['confidence'] ?? null,
                'source_metadata' => [],
            ]);
        }

        return (string) $observation->id;
    }

    /** @return array<string,mixed> */
    private function observedGovernor(
        string $key,
        int $x,
        int $y,
        ?string $planLocalIdentity,
        SpatialObservedIdentityState $identity = SpatialObservedIdentityState::ResolvedPlanLocal,
        ?string $label = null,
    ): array {
        return [
            'key' => $key,
            'type' => SpatialObservedObjectType::GovernorCity,
            'x' => $x,
            'y' => $y,
            'player_id' => null,
            'plan_local_identity' => $planLocalIdentity,
            'observed_label' => $label ?? $planLocalIdentity,
            'identity_state' => $identity,
            'confidence' => 0.95,
        ];
    }

    /** @return array<string,mixed> */
    private function observedStructure(
        string $key,
        SpatialObservedObjectType $type,
        int $x,
        int $y,
    ): array {
        return [
            'key' => $key,
            'type' => $type,
            'x' => $x,
            'y' => $y,
            'player_id' => null,
            'plan_local_identity' => null,
            'observed_label' => null,
            'identity_state' => SpatialObservedIdentityState::Unresolved,
            'confidence' => 0.95,
        ];
    }
}
