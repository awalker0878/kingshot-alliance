<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Database\Eloquent\Builder;

final class AllianceAuthorization
{
    public function __construct(private AllianceRankPermissions $rankPermissions) {}

    public function activeMembership(Player $player, Alliance $alliance): ?AllianceMembership
    {
        if ($alliance->status !== AllianceStatus::Active
            || (string) $player->current_kingdom_id !== (string) $alliance->kingdom_id) {
            return null;
        }

        return AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $player->id)
            ->where('status', MembershipStatus::Active->value)
            ->first();
    }

    public function allows(Player $player, Alliance $alliance, PermissionKey $permission): bool
    {
        $membership = $this->activeMembership($player, $alliance);

        if (! $membership instanceof AllianceMembership) {
            return false;
        }

        if ($this->rankPermissions->allows($membership->rank, $permission)) {
            return true;
        }

        return $membership->roles()
            ->where('roles.alliance_id', $alliance->id)
            ->whereHas('permissions', static function (Builder $permissionQuery) use ($permission): void {
                $permissionQuery->where('permissions.key', $permission->value);
            })
            ->exists();
    }
}
