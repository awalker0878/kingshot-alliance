<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Authorization\DefaultAllianceRole;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Models\Alliance;
use App\Models\AllianceMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final readonly class MembershipAdministrationGuard
{
    public function __construct(private AllianceAuthorization $authorization) {}

    public function assertCanManage(User $actor, Alliance $alliance, AllianceMembership $target): void
    {
        if ((string) $target->alliance_id !== (string) $alliance->id) {
            throw new AuthorizationException();
        }

        if (! $this->authorization->allows($actor, $alliance, PermissionKey::MembershipManage)) {
            throw new AuthorizationException();
        }

        $actorMembership = $this->authorization->activeMembership($actor, $alliance);

        if (! $actorMembership instanceof AllianceMembership) {
            throw new AuthorizationException();
        }

        if ((int) $target->user_id === (int) $actor->id) {
            throw ValidationException::withMessages([
                'membership' => 'Use the leave-alliance action to change your own membership status.',
            ]);
        }

        $actorRank = $this->rank($actorMembership);
        $targetRank = $this->rank($target);

        if ($actorRank < $this->rankFor(DefaultAllianceRole::Owner) && $actorRank <= $targetRank) {
            throw new AuthorizationException();
        }
    }

    public function assertCanChangeOwnerMembership(AllianceMembership $target): void
    {
        if (! $this->hasRole($target, DefaultAllianceRole::Owner)) {
            return;
        }

        $otherActiveOwners = AllianceMembership::query()
            ->where('alliance_id', $target->alliance_id)
            ->where('id', '!=', $target->id)
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('roles', static fn ($query) => $query->where('roles.key', DefaultAllianceRole::Owner->value))
            ->exists();

        if (! $otherActiveOwners) {
            throw ValidationException::withMessages([
                'membership' => 'An alliance must retain at least one active owner.',
            ]);
        }
    }

    public function hasRole(AllianceMembership $membership, DefaultAllianceRole $role): bool
    {
        return $membership->roles()
            ->where('roles.alliance_id', $membership->alliance_id)
            ->where('roles.key', $role->value)
            ->exists();
    }

    public function rank(AllianceMembership $membership): int
    {
        $keys = $membership->roles()
            ->where('roles.alliance_id', $membership->alliance_id)
            ->pluck('roles.key');

        $rank = 0;

        foreach ($keys as $key) {
            $role = DefaultAllianceRole::tryFrom((string) $key);

            if ($role instanceof DefaultAllianceRole) {
                $rank = max($rank, $this->rankFor($role));
            }
        }

        return $rank;
    }

    private function rankFor(DefaultAllianceRole $role): int
    {
        return match ($role) {
            DefaultAllianceRole::Owner => 100,
            DefaultAllianceRole::Leader => 80,
            DefaultAllianceRole::Officer => 60,
            DefaultAllianceRole::Recruiter,
            DefaultAllianceRole::EventCoordinator,
            DefaultAllianceRole::ContentManager => 40,
            DefaultAllianceRole::Member => 10,
        };
    }
}
