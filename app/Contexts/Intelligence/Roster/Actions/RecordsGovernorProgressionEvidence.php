<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Actions;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Roster\Services\GovernorProgressionObservationWriter;
use App\Contexts\Intelligence\Roster\ValueObjects\GovernorProgressionEvidenceRecordResult;
use Illuminate\Validation\ValidationException;

abstract readonly class RecordsGovernorProgressionEvidence
{
    public function __construct(protected GovernorProgressionObservationWriter $writer) {}

    /** @param array<string,mixed> $payload */
    protected function record(
        EvidenceKind $kind,
        string $expectedSchemaVersion,
        string $actorPlayerId,
        string $allianceId,
        string $rosterEntryId,
        string $evidenceId,
        string $reviewId,
        string $schemaVersion,
        string $progressionDatasetId,
        string $progressionDatasetChecksum,
        string $capturedAt,
        array $payload,
        string $idempotencyKey,
    ): GovernorProgressionEvidenceRecordResult {
        if ($schemaVersion !== $expectedSchemaVersion) {
            throw ValidationException::withMessages(['schema_version' => 'The reviewed screenshot schema version does not match this destination Action.']);
        }

        return $this->writer->record(
            actorPlayerId: $actorPlayerId,
            allianceId: $allianceId,
            rosterEntryId: $rosterEntryId,
            evidenceId: $evidenceId,
            reviewId: $reviewId,
            kind: $kind,
            schemaVersion: $schemaVersion,
            progressionDatasetId: $progressionDatasetId,
            progressionDatasetChecksum: $progressionDatasetChecksum,
            capturedAt: $capturedAt,
            payload: $payload,
            idempotencyKey: $idempotencyKey,
        );
    }
}
