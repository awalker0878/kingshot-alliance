<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Observations;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\Intelligence\Evidence\Contracts\SpatialEvidenceReferenceLookup;
use App\Contexts\Intelligence\Observations\Actions\RecordSpatialObservationEvidence;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCompleteness;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCoverageKind;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedIdentityState;
use App\Contexts\Intelligence\Observations\Models\SpatialObservation;
use App\Contexts\Intelligence\Observations\Models\SpatialObservationEvidenceReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class SpatialObservationEvidenceV3Test extends TestCase
{
    use RefreshDatabase;

    private const DATASET_ID = 'kingshot-community-observed-2026-08-21-v1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SpatialEvidenceReferenceLookup::class, new class implements SpatialEvidenceReferenceLookup
        {
            public function isApprovedSpatialReview(
                string $evidenceId,
                string $reviewId,
                string $allianceId,
                string $kingdomId,
                string $schemaVersion,
                string $mapDatasetId,
                string $mapDatasetChecksum,
            ): bool {
                return true;
            }
        });
    }

    public function test_destination_replays_one_stable_receipt_without_duplicate_observation(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 62101);
        $alliance = $scenario->alliance($actor);
        $dataset = app(KingdomMapDatasetQuery::class)->require(self::DATASET_ID);
        $idempotencyKey = hash('sha256', 'territory-spatial-idempotency');
        $action = app(RecordSpatialObservationEvidence::class);

        $first = $action->handle(
            actorPlayerId: $actor->playerId,
            allianceId: $alliance->allianceId,
            kingdomId: $actor->kingdomId,
            evidenceId: '01TERRITORYEVIDENCE000001',
            reviewId: '01TERRITORYREVIEW00000001',
            schemaVersion: RecordSpatialObservationEvidence::SCHEMA_VERSION,
            mapDatasetId: $dataset->id,
            mapDatasetChecksum: $dataset->checksum,
            capturedAt: now()->subMinutes(5)->toIso8601String(),
            coverageKind: SpatialObservationCoverageKind::PartialRegion,
            completeness: SpatialObservationCompleteness::Partial,
            coverageBounds: null,
            objects: [$this->headquarters('hq-observed', 100, 100)],
            idempotencyKey: $idempotencyKey,
        );
        $replayed = $action->handle(
            actorPlayerId: $actor->playerId,
            allianceId: $alliance->allianceId,
            kingdomId: $actor->kingdomId,
            evidenceId: '01TERRITORYEVIDENCE000001',
            reviewId: '01TERRITORYREVIEW00000001',
            schemaVersion: RecordSpatialObservationEvidence::SCHEMA_VERSION,
            mapDatasetId: $dataset->id,
            mapDatasetChecksum: $dataset->checksum,
            capturedAt: now()->subMinutes(5)->toIso8601String(),
            coverageKind: SpatialObservationCoverageKind::PartialRegion,
            completeness: SpatialObservationCompleteness::Partial,
            coverageBounds: null,
            objects: [$this->headquarters('hq-observed', 100, 100)],
            idempotencyKey: $idempotencyKey,
        );

        self::assertFalse($first->idempotentReplay);
        self::assertTrue($replayed->idempotentReplay);
        self::assertSame($first->receiptId, $replayed->receiptId);
        self::assertSame($first->observationId, $replayed->observationId);
        self::assertSame(1, SpatialObservation::query()->count());
        self::assertSame(1, SpatialObservationEvidenceReceipt::query()->count());
    }

    public function test_correction_appends_replacement_and_invalidates_original_without_deleting_history(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 62102);
        $alliance = $scenario->alliance($actor);
        $dataset = app(KingdomMapDatasetQuery::class)->require(self::DATASET_ID);
        $action = app(RecordSpatialObservationEvidence::class);

        $original = $action->handle(
            actorPlayerId: $actor->playerId,
            allianceId: $alliance->allianceId,
            kingdomId: $actor->kingdomId,
            evidenceId: '01TERRITORYEVIDENCE000002',
            reviewId: '01TERRITORYREVIEW00000002',
            schemaVersion: RecordSpatialObservationEvidence::SCHEMA_VERSION,
            mapDatasetId: $dataset->id,
            mapDatasetChecksum: $dataset->checksum,
            capturedAt: now()->subMinutes(10)->toIso8601String(),
            coverageKind: SpatialObservationCoverageKind::SingleObject,
            completeness: SpatialObservationCompleteness::Partial,
            coverageBounds: null,
            objects: [$this->headquarters('hq-original', 100, 100)],
            idempotencyKey: hash('sha256', 'territory-spatial-original'),
        );
        $replacement = $action->handle(
            actorPlayerId: $actor->playerId,
            allianceId: $alliance->allianceId,
            kingdomId: $actor->kingdomId,
            evidenceId: '01TERRITORYEVIDENCE000003',
            reviewId: '01TERRITORYREVIEW00000003',
            schemaVersion: RecordSpatialObservationEvidence::SCHEMA_VERSION,
            mapDatasetId: $dataset->id,
            mapDatasetChecksum: $dataset->checksum,
            capturedAt: now()->subMinutes(8)->toIso8601String(),
            coverageKind: SpatialObservationCoverageKind::SingleObject,
            completeness: SpatialObservationCompleteness::Partial,
            coverageBounds: null,
            objects: [$this->headquarters('hq-corrected', 101, 100)],
            idempotencyKey: hash('sha256', 'territory-spatial-correction'),
            correctsObservationId: $original->observationId,
            correctionReason: 'Corrected the reviewed HQ coordinate from the source screenshot.',
        );

        self::assertNotSame($original->observationId, $replacement->observationId);
        self::assertSame(2, SpatialObservation::query()->count());
        $originalRow = SpatialObservation::query()->findOrFail($original->observationId);
        $replacementRow = SpatialObservation::query()->findOrFail($replacement->observationId);
        self::assertNotNull($originalRow->invalidated_at);
        self::assertSame($actor->playerId, (string) $originalRow->invalidated_by_player_id);
        self::assertSame(
            $original->observationId,
            (string) $replacementRow->corrects_observation_id,
        );
    }

    /** @return array<string,mixed> */
    private function headquarters(string $key, int $x, int $y): array
    {
        return [
            'key' => $key,
            'type' => 'headquarters',
            'x' => $x,
            'y' => $y,
            'player_id' => null,
            'plan_local_identity' => null,
            'observed_label' => 'Alliance HQ',
            'identity_state' => SpatialObservedIdentityState::Unresolved->value,
            'confidence' => 0.99,
            'source_metadata' => [],
        ];
    }
}
