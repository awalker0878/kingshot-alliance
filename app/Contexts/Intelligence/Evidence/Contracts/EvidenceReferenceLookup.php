<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Contracts;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;

interface EvidenceReferenceLookup
{
    public function belongsToAlliance(string $evidenceId, string $allianceId): bool;

    public function isApprovedForAlliance(string $evidenceId, string $allianceId): bool;

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
