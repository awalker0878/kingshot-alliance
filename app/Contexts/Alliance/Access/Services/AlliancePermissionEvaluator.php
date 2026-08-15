<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Services;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Shared\Access\Contracts\Permission;
use Illuminate\Database\Eloquent\Builder;

final readonly class AlliancePermissionEvaluator
{
    public function __construct(private AllianceRankPermissions $rankPermissions) {}

    public function allows(
        AllianceMembership $membership,
        Alliance $alliance,
        Permission $permission,
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
                $permissionQuery->where('permissions.key', $permission->key());
            })
            ->exists();
    }
}
