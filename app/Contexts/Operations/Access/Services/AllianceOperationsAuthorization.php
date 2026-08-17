<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Services;

use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use Illuminate\Auth\Access\AuthorizationException;

final class AllianceOperationsAuthorization
{
    public function allows(Player $actor, Alliance $alliance, OperationsPermission $permission): bool
    {
        if ($alliance->status !== AllianceStatus::Active
            || (string) $actor->current_kingdom_id !== (string) $alliance->kingdom_id) {
            return false;
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $actor->id)
            ->where('status', MembershipStatus::Active->value)
            ->first();

        return $membership instanceof AllianceMembership
            && $this->allowsMembership($membership, $alliance, $permission);
    }

    public function authorizeContext(AllianceMutationContext $context, OperationsPermission $permission): void
    {
        if (! $this->allowsMembership($context->membership, $context->alliance, $permission)) {
            throw new AuthorizationException;
        }
    }

    public function allowsMembership(AllianceMembership $membership, Alliance $alliance, OperationsPermission $permission): bool
    {
        if ($membership->status !== MembershipStatus::Active
            || (string) $membership->alliance_id !== (string) $alliance->id) {
            return false;
        }

        $isOfficer = in_array($membership->rank, [AllianceRank::R4, AllianceRank::R5], true);
        $isEventCoordinator = $membership->roles()
            ->where('roles.alliance_id', $alliance->id)
            ->where('roles.key', DefaultAllianceRole::EventCoordinator->value)
            ->exists();

        return match ($permission) {
            OperationsPermission::EventPlayerView,
            OperationsPermission::EventPlayerCreate,
            OperationsPermission::EventAllianceView => true,
            OperationsPermission::EventPlayerManage => $isOfficer,
            OperationsPermission::EventAllianceCreate,
            OperationsPermission::EventAllianceManage => $isOfficer || $isEventCoordinator,
            default => false,
        };
    }
}
