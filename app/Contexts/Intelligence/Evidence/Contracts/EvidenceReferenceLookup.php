<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Contracts;

interface EvidenceReferenceLookup
{
    public function belongsToAlliance(string $evidenceId, string $allianceId): bool;

    public function isApprovedForAlliance(string $evidenceId, string $allianceId): bool;
}
