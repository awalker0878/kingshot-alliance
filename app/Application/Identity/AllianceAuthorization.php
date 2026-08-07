<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Authorization\PermissionKey;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Models\Alliance;
use App\Models\AllianceMembership;
use App\Models\User;
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
