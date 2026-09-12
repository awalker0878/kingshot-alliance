<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Queries;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

final class KingdomAdministratorAssignments
{
    /** @return Builder<KingdomRoleAssignment> */
    public function effective(string $kingdomId, ?DateTimeInterface $at = null): Builder
    {
        return KingdomRoleAssignment::query()->effective($at)->where('kingdom_id', $kingdomId)
            ->whereHas('role', static fn ($query) => $query->where('kingdom_id', $kingdomId)->where('key', DefaultKingdomRole::Administrator->value))
            ->whereHas('player', static fn ($query) => $query->where('current_kingdom_id', $kingdomId)->whereNull('canonical_player_id'));
    }
}
