<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Actions;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Roster\ValueObjects\GovernorProgressionEvidenceRecordResult;

final readonly class RecordGovernorProfileEvidence extends RecordsGovernorProgressionEvidence
{
    /** @param array<string,mixed> $payload */
    public function handle(string $actorPlayerId, string $allianceId, string $rosterEntryId, string $evidenceId, string $reviewId, string $schemaVersion, string $progressionDatasetId, string $progressionDatasetChecksum, string $capturedAt, array $payload, string $idempotencyKey): GovernorProgressionEvidenceRecordResult
    {
        return $this->record(EvidenceKind::GovernorProfile, 'governor-profile/1', $actorPlayerId, $allianceId, $rosterEntryId, $evidenceId, $reviewId, $schemaVersion, $progressionDatasetId, $progressionDatasetChecksum, $capturedAt, $payload, $idempotencyKey);
    }
}
