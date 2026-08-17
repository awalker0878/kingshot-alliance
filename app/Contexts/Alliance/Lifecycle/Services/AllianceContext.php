<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Services;

use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use LogicException;

/**
 * Request-scoped Alliance identity only.
 *
 * Rank, roles and permissions are intentionally excluded. This object is not an
 * authorization cache; protected writes re-read current Alliance authority.
 */
final class AllianceContext
{
    private ?AllianceScopeReference $scope = null;

    public function activate(PlayerReference $player, AllianceScopeReference $scope): void
    {
        if ($scope->playerId !== $player->playerId || $scope->kingdomId !== $player->kingdomId) {
            throw new LogicException('Alliance context must match the active Player and Kingdom.');
        }

        $this->scope = $scope;
    }

    public function scope(): AllianceScopeReference
    {
        return $this->scope ?? throw new LogicException('Alliance context has not been resolved.');
    }

    public function scopeOrNull(): ?AllianceScopeReference
    {
        return $this->scope;
    }

    public function clear(): void
    {
        $this->scope = null;
    }
}
