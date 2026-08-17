<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\ValueObjects;

/**
 * Stable request identity for the active Alliance scope.
 *
 * Mutable rank/roles/permissions are deliberately absent. Protected writes must
 * re-read those facts from Alliance-owned persistence at execution time.
 */
final readonly class AllianceScopeReference
{
    public function __construct(
        public string $playerId,
        public string $kingdomId,
        public string $allianceId,
        public string $membershipId,
    ) {}
}
