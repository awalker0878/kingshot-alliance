<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Services;

use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomMutationContext;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final class KingdomOperationsAuthorization
{
    public function __construct(private KingdomAuthorization $kingdomAuthorization) {}

    public function allows(Player $actor, Kingdom $kingdom, OperationsPermission $permission): bool
    {
        if (! $this->supports($permission)
            || (string) $actor->current_kingdom_id !== (string) $kingdom->id) {
            return false;
        }

        return $this->hasPermission($actor, $kingdom, $permission);
    }

    public function require(Player $actor, Kingdom $kingdom, OperationsPermission $permission): KingdomMutationContext
    {
        if (! $this->supports($permission)) {
            throw new AuthorizationException;
        }

        $context = $this->kingdomAuthorization->acquireActiveScope($actor, $kingdom);
        if (! $this->hasPermission($context->actor, $context->kingdom, $permission)) {
            throw new AuthorizationException;
        }

        return $context;
    }

    private function supports(OperationsPermission $permission): bool
    {
        return in_array($permission, [
            OperationsPermission::EventKingdomView,
            OperationsPermission::EventKingdomCreate,
            OperationsPermission::EventKingdomManage,
        ], true);
    }

    private function hasPermission(Player $actor, Kingdom $kingdom, OperationsPermission $permission): bool
    {
        return KingdomRoleAssignment::query()
            ->where('kingdom_id', $kingdom->id)
            ->where('player_id', $actor->id)
            ->whereHas('role.permissions', static function (Builder $query) use ($permission): void {
                $query->where('permissions.key', $permission->key());
            })
            ->exists();
    }
}
