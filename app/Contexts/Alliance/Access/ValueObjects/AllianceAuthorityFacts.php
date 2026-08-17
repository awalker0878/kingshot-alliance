<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\ValueObjects;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;

/**
 * Fresh read projection of mutable Alliance authority.
 *
 * This is deliberately not request/security context and must not be used as
 * durable proof of authority for a later write. Protected writes must read
 * current authority again inside their transaction.
 */
final readonly class AllianceAuthorityFacts
{
    /** @param list<string> $roleKeysObservedAtRead */
    public function __construct(
        public string $playerId,
        public string $allianceId,
        public string $kingdomId,
        public AllianceRank $rankObservedAtRead,
        public array $roleKeysObservedAtRead,
    ) {}

    public function hasRoleObservedAtRead(string $roleKey): bool
    {
        return in_array($roleKey, $this->roleKeysObservedAtRead, true);
    }
}
