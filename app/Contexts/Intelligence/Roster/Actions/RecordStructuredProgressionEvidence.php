<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Actions;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Roster\ValueObjects\GovernorProgressionEvidenceRecordResult;
use Illuminate\Validation\ValidationException;

final readonly class RecordStructuredProgressionEvidence extends RecordsGovernorProgressionEvidence
{
    /** @param array<string,mixed> $payload */
    public function handle(
        EvidenceKind $kind,
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
        $expectedSchema = match ($kind) {
            EvidenceKind::GovernorBuildings => 'governor-buildings/1',
            EvidenceKind::GovernorAcademyResearch => 'governor-academy-research/1',
            EvidenceKind::GovernorWarAcademyResearch => 'governor-war-academy-research/1',
            default => throw ValidationException::withMessages(['kind' => 'Unsupported structured progression evidence destination.']),
        };

        return $this->record(
            $kind,
            $expectedSchema,
            $actorPlayerId,
            $allianceId,
            $rosterEntryId,
            $evidenceId,
            $reviewId,
            $schemaVersion,
            $progressionDatasetId,
            $progressionDatasetChecksum,
            $capturedAt,
            $payload,
            $idempotencyKey,
        );
    }
}
