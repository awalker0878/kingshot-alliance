<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\ValueObjects;

final readonly class KingdomAuthorityFacts
{
    /** @param list<string> $permissionKeysObservedAtRead */
    public function __construct(
        public string $playerId,
        public string $kingdomId,
        public array $permissionKeysObservedAtRead,
    ) {}

    public function hasPermissionObservedAtRead(string $permissionKey): bool
    {
        return in_array($permissionKey, $this->permissionKeysObservedAtRead, true);
    }
}
