<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Services;

use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomMutationContext;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\Access\Contracts\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final class KingdomAuthorization
{
    public function allows(Player $player, Kingdom $kingdom, Permission $permission): bool
    {
        if ((string) $player->current_kingdom_id !== (string) $kingdom->id) {
            return false;
        }

        return $this->hasPermission($player, $kingdom, $permission);
    }

    public function allowsContext(KingdomMutationContext $context, Permission $permission): bool
    {
        return $this->hasPermission($context->actor, $context->kingdom, $permission);
    }

    public function authorizeContext(KingdomMutationContext $context, Permission $permission): void
    {
        if (! $this->allowsContext($context, $permission)) {
            throw new AuthorizationException;
        }
    }

    private function hasPermission(Player $player, Kingdom $kingdom, Permission $permission): bool
    {
        return KingdomRoleAssignment::query()
            ->where('kingdom_id', $kingdom->id)
            ->where('player_id', $player->id)
            ->whereHas('role.permissions', static function (Builder $query) use ($permission): void {
                $query->where('permissions.key', $permission->key());
            })
            ->exists();
    }
}
