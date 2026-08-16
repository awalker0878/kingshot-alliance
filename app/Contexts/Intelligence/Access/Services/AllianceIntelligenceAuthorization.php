<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Access\Services;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use Illuminate\Auth\Access\AuthorizationException;

final class AllianceIntelligenceAuthorization
{
    public function __construct(private AllianceAuthorization $allianceAuthorization) {}

    public function allows(Player $actor, Alliance $alliance, IntelligencePermission $permission): bool
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

    public function require(
        Player $actor,
        Alliance $alliance,
        IntelligencePermission|AlliancePermission $permission,
    ): AllianceMutationContext {
        $context = $this->allianceAuthorization->acquireActiveScope($actor, $alliance);

        if ($permission instanceof AlliancePermission) {
            if ($permission !== AlliancePermission::View) {
                throw new AuthorizationException;
            }

            return $context;
        }

        if (! $this->allowsMembership($context->membership, $context->alliance, $permission)) {
            throw new AuthorizationException;
        }

        return $context;
    }

    public function allowsMembership(AllianceMembership $membership, Alliance $alliance, IntelligencePermission $permission): bool
    {
        if ($membership->status !== MembershipStatus::Active
            || (string) $membership->alliance_id !== (string) $alliance->id) {
            return false;
        }

        return match ($permission) {
            IntelligencePermission::View => true,
            IntelligencePermission::KingdomManage => in_array($membership->rank, [AllianceRank::R4, AllianceRank::R5], true),
            IntelligencePermission::ContributionManage => $membership->rank === AllianceRank::R5,
        };
    }
}
