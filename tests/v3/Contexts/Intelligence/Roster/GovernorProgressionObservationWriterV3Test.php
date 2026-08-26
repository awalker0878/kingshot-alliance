<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Roster;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\Intelligence\Evidence\Contracts\GovernorProgressionEvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Roster\Models\GovernorProgressionEvidenceReceipt;
use App\Contexts\Intelligence\Roster\Models\GovernorProgressionObservation;
use App\Contexts\Intelligence\Roster\Services\GovernorProgressionObservationWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GovernorProgressionObservationWriterV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_destination_replay_returns_same_receipt_without_duplicate_observation(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 62401);
        $alliance = $scenario->alliance($actor);
        $entry = $scenario->roster($actor, $alliance);
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        $evidenceId = 'evidence-governor-profile';
        $reviewId = 'review-governor-profile';
        $idempotencyKey = hash('sha256', 'governor-profile-review');

        $this->app->instance(GovernorProgressionEvidenceReferenceLookup::class, new class implements GovernorProgressionEvidenceReferenceLookup
        {
            public function isApprovedGovernorProgressionReview(
                string $evidenceId,
                string $reviewId,
                string $allianceId,
                string $rosterEntryId,
                string $playerId,
                EvidenceKind $kind,
                string $schemaVersion,
                string $progressionDatasetId,
                string $progressionDatasetChecksum,
            ): bool {
                return true;
            }
        });

        $writer = app(GovernorProgressionObservationWriter::class);
        $arguments = [
            'actorPlayerId' => $actor->playerId,
            'allianceId' => $alliance->allianceId,
            'rosterEntryId' => $entry->rosterEntryId,
            'evidenceId' => $evidenceId,
            'reviewId' => $reviewId,
            'kind' => EvidenceKind::GovernorProfile,
            'schemaVersion' => 'governor-profile/1',
            'progressionDatasetId' => $dataset->id,
            'progressionDatasetChecksum' => $dataset->checksum,
            'capturedAt' => '2026-08-26T12:00:00Z',
            'payload' => ['power' => '45000000'],
            'idempotencyKey' => $idempotencyKey,
        ];

        $first = $writer->record(...$arguments);
        $second = $writer->record(...$arguments);

        self::assertFalse($first->idempotentReplay);
        self::assertTrue($second->idempotentReplay);
        self::assertSame($first->receiptId, $second->receiptId);
        self::assertSame($first->observationId, $second->observationId);
        self::assertSame(1, GovernorProgressionObservation::query()->count());
        self::assertSame(1, GovernorProgressionEvidenceReceipt::query()->count());
    }

    public function test_destination_rejects_unapproved_evidence_provenance(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 62402);
        $alliance = $scenario->alliance($actor);
        $entry = $scenario->roster($actor, $alliance);
        $dataset = app(ProgressionDatasetQuery::class)->latest();

        $this->app->instance(GovernorProgressionEvidenceReferenceLookup::class, new class implements GovernorProgressionEvidenceReferenceLookup
        {
            public function isApprovedGovernorProgressionReview(
                string $evidenceId,
                string $reviewId,
                string $allianceId,
                string $rosterEntryId,
                string $playerId,
                EvidenceKind $kind,
                string $schemaVersion,
                string $progressionDatasetId,
                string $progressionDatasetChecksum,
            ): bool {
                return false;
            }
        });

        try {
            app(GovernorProgressionObservationWriter::class)->record(
                actorPlayerId: $actor->playerId,
                allianceId: $alliance->allianceId,
                rosterEntryId: $entry->rosterEntryId,
                evidenceId: 'evidence-unapproved',
                reviewId: 'review-unapproved',
                kind: EvidenceKind::GovernorProfile,
                schemaVersion: 'governor-profile/1',
                progressionDatasetId: $dataset->id,
                progressionDatasetChecksum: $dataset->checksum,
                capturedAt: '2026-08-26T12:00:00Z',
                payload: ['power' => '45000000'],
                idempotencyKey: hash('sha256', 'unapproved-review'),
            );
            self::fail('Expected invalid Evidence provenance to be rejected.');
        } catch (ValidationException) {
            self::assertSame(0, GovernorProgressionObservation::query()->count());
            self::assertSame(0, GovernorProgressionEvidenceReceipt::query()->count());
        }
    }
}
