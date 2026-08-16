<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Services;

use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use Illuminate\Database\Eloquent\Builder;

final class KingdomOperationsAuthorization
{
    public function allows(Player $actor, Kingdom $kingdom, OperationsPermission $permission): bool
    {
        if (! in_array($permission, [
            OperationsPermission::EventKingdomView,
            OperationsPermission::EventKingdomCreate,
            OperationsPermission::EventKingdomManage,
        ], true)) {
            return false;
        }

        if ((string) $actor->current_kingdom_id !== (string) $kingdom->id) {
            return false;
        }

        return KingdomRoleAssignment::query()
            ->where('kingdom_id', $kingdom->id)
            ->where('player_id', $actor->id)
            ->whereHas('role.permissions', static function (Builder $query) use ($permission): void {
                $query->where('permissions.key', $permission->key());
            })
            ->exists();
    }
}
