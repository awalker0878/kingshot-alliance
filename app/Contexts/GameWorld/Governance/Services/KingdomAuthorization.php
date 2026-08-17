<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Services;

use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomMutationContext;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\Access\Contracts\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final class KingdomAuthorization
{
    public function allows(string $playerId, string $kingdomId, Permission $permission): bool
    {
        $player = Player::query()->find($playerId);
        if (! $player instanceof Player || (string) $player->current_kingdom_id !== $kingdomId) {
            return false;
        }

        return $this->hasPermission($playerId, $kingdomId, $permission);
    }

    public function allowsContext(KingdomMutationContext $context, Permission $permission): bool
    {
        return $this->hasPermission((string) $context->actor->id, (string) $context->kingdom->id, $permission);
    }

    public function authorizeContext(KingdomMutationContext $context, Permission $permission): void
    {
        if (! $this->allowsContext($context, $permission)) {
            throw new AuthorizationException;
        }
    }

    private function hasPermission(string $playerId, string $kingdomId, Permission $permission): bool
    {
        return KingdomRoleAssignment::query()
            ->where('kingdom_id', $kingdomId)
            ->where('player_id', $playerId)
            ->whereHas('role.permissions', static function (Builder $query) use ($permission): void {
                $query->where('permissions.key', $permission->key());
            })
            ->exists();
    }
}
