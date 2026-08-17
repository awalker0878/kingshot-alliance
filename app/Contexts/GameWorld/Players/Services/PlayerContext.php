<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Services;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use LogicException;

/**
 * Request-scoped identity only.
 *
 * This object deliberately never exposes an Eloquent Player. It identifies the
 * active game principal selected by the authenticated account. Protected writes
 * must reload the current Player/authority state in their owning operation.
 */
final class PlayerContext
{
    private ?PlayerReference $player = null;

    public function activate(PlayerReference $player, int $authenticatedUserId): void
    {
        if ($player->userId === null || $player->userId !== $authenticatedUserId) {
            throw new LogicException('The active Player must belong to the authenticated User.');
        }

        $this->player = $player;
    }

    public function player(): PlayerReference
    {
        return $this->player ?? throw new LogicException('Player context has not been resolved.');
    }

    public function playerOrNull(): ?PlayerReference
    {
        return $this->player;
    }

    public function clear(): void
    {
        $this->player = null;
    }
}
