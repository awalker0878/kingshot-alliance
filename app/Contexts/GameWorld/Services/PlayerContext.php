<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Services;

use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use LogicException;

final class PlayerContext
{
    private ?Player $player = null;

    public function activate(Player $player, User $user): void
    {
        if ($player->user_id === null || (int) $player->user_id !== (int) $user->id) {
            throw new LogicException('The active Player must belong to the authenticated User.');
        }

        $this->player = $player;
    }

    public function player(): Player
    {
        return $this->player ?? throw new LogicException('Player context has not been resolved.');
    }

    public function playerOrNull(): ?Player
    {
        return $this->player;
    }

    public function clear(): void
    {
        $this->player = null;
    }
}
