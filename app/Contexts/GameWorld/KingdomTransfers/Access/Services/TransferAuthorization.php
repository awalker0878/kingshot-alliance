<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Access\Services;

use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Auth\Access\AuthorizationException;

final class TransferAuthorization
{
    public function allows(Player $actor, Alliance $alliance, TransferPermission $permission): bool
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

    public function authorizeContext(AllianceMutationContext $context, TransferPermission $permission): void
    {
        if (! $this->allowsMembership($context->membership, $context->alliance, $permission)) {
            throw new AuthorizationException;
        }
    }

    public function allowsMembership(AllianceMembership $membership, Alliance $alliance, TransferPermission $permission): bool
    {
        if ($membership->status !== MembershipStatus::Active
            || (string) $membership->alliance_id !== (string) $alliance->id) {
            return false;
        }

        return match ($permission) {
            TransferPermission::Manage => in_array($membership->rank, [AllianceRank::R4, AllianceRank::R5], true),
        };
    }
}
