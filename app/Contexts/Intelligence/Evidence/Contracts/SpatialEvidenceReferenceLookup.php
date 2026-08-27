<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Contracts;

interface SpatialEvidenceReferenceLookup
{
    public function isApprovedSpatialReview(
        string $evidenceId,
        string $reviewId,
        string $allianceId,
        string $kingdomId,
        string $schemaVersion,
        string $mapDatasetId,
        string $mapDatasetChecksum,
    ): bool;
}
