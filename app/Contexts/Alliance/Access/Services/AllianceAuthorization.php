<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Services;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class AllianceAuthorization
{
    public function __construct(private AlliancePermissionEvaluator $permissions) {}

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

    public function allows(Player $player, Alliance $alliance, AlliancePermission $permission): bool
    {
        $membership = $this->activeMembership($player, $alliance);

        return $membership instanceof AllianceMembership
            && $this->permissions->allows($membership, $alliance, $permission);
    }

    public function allowsContext(AllianceMutationContext $context, AlliancePermission $permission): bool
    {
        return $this->permissions->allows($context->membership, $context->alliance, $permission);
    }

    public function authorizeContext(AllianceMutationContext $context, AlliancePermission $permission): void
    {
        if (! $this->allowsContext($context, $permission)) {
            throw new AuthorizationException;
        }
    }
}
