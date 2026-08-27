<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Contracts;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;

interface GovernorProgressionEvidenceReferenceLookup
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
    ): bool;
}
