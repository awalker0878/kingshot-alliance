<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Models\AllianceMembership;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class AllianceAuthorization
{
    public function activeMembership(User $user, Alliance $alliance): ?AllianceMembership
    {
        return AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active->value)
            ->first();
    }

    public function allows(User $user, Alliance $alliance, PermissionKey $permission): bool
    {
        return AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('roles', function (Builder $roleQuery) use ($alliance, $permission): void {
                $roleQuery
                    ->where('roles.alliance_id', $alliance->id)
                    ->whereHas('permissions', static function (Builder $permissionQuery) use ($permission): void {
                        $permissionQuery->where('permissions.key', $permission->value);
                    });
            })
            ->exists();
    }
}
