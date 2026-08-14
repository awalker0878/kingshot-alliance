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
        if (! $this->contextMatches($player, $alliance)) {
            return null;
        }

        return $this->activeMembershipQuery($player, $alliance)->first();
    }

    /**
     * Resolve and lock the actor's active membership for a game-domain mutation.
     *
     * Call this only inside an existing database transaction. Membership lifecycle,
     * rank changes, and specialist-role changes serialize on this same row, so the
     * caller retains a stable Alliance-authority snapshot until commit.
     */
    public function activeMembershipForUpdate(Player $player, Alliance $alliance): ?AllianceMembership
    {
        if (! $this->contextMatches($player, $alliance)) {
            return null;
        }

        return $this->activeMembershipQuery($player, $alliance)
            ->lockForUpdate()
            ->first();
    }

    public function allows(Player $player, Alliance $alliance, PermissionKey $permission): bool
    {
        return $this->membershipAllows($this->activeMembership($player, $alliance), $alliance, $permission);
    }

    /**
     * Check Alliance authority while holding the actor membership row lock.
     *
     * This is the mutation-boundary variant of allows(); callers must already be
     * inside a transaction.
     */
    public function allowsForUpdate(Player $player, Alliance $alliance, PermissionKey $permission): bool
    {
        return $this->membershipAllows($this->activeMembershipForUpdate($player, $alliance), $alliance, $permission);
    }

    private function contextMatches(Player $player, Alliance $alliance): bool
    {
        return $alliance->status === AllianceStatus::Active
            && (string) $player->current_kingdom_id === (string) $alliance->kingdom_id;
    }

    private function activeMembershipQuery(Player $player, Alliance $alliance): Builder
    {
        return AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $player->id)
            ->where('status', MembershipStatus::Active->value);
    }

    private function membershipAllows(?AllianceMembership $membership, Alliance $alliance, PermissionKey $permission): bool
    {
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
