<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Services;

use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Access\Contracts\Permission;
use Illuminate\Database\Eloquent\Builder;

final class KingdomAuthorization
{
    public function allows(Player $player, Kingdom $kingdom, Permission $permission): bool
    {
        if ((string) $player->current_kingdom_id !== (string) $kingdom->id) {
            return false;
        }

        return KingdomRoleAssignment::query()
            ->where('kingdom_id', $kingdom->id)
            ->where('player_id', $player->id)
            ->whereHas('role.permissions', static function (Builder $query) use ($permission): void {
                $query->where('permissions.key', $permission->key());
            })
            ->exists();
    }
}
