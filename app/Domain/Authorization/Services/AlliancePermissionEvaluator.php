<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Database\Eloquent\Builder;

final readonly class AlliancePermissionEvaluator
{
    public function __construct(private AllianceRankPermissions $rankPermissions) {}

    public function allows(
        AllianceMembership $membership,
        Alliance $alliance,
        PermissionKey $permission,
    ): bool {
        if ($membership->status !== MembershipStatus::Active
            || (string) $membership->alliance_id !== (string) $alliance->id) {
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
