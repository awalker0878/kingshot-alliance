<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Services;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class AllianceAuthorization
{
    public function __construct(
        private AlliancePermissionEvaluator $permissions,
        private PlayerReferenceQuery $players,
    ) {}

    /**
     * Fresh read authorization. This is suitable for presentation/read gating.
     * Protected writes should authorize their locked AllianceMutationContext.
     */
    public function allows(string $playerId, string $allianceId, AlliancePermission $permission): bool
    {
        $player = $this->players->find($playerId);
        $alliance = Alliance::query()->find($allianceId);

        if ($player === null || ! $alliance instanceof Alliance || $alliance->status !== AllianceStatus::Active) {
            return false;
        }

        if ($player->kingdomId !== (string) $alliance->kingdom_id) {
            return false;
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $allianceId)
            ->where('player_id', $playerId)
            ->where('status', MembershipStatus::Active->value)
            ->first();

        return $membership instanceof AllianceMembership
            && $this->permissions->allows($membership, $alliance, $permission);
    }

    public function authorize(string $playerId, string $allianceId, AlliancePermission $permission): void
    {
        if (! $this->allows($playerId, $allianceId, $permission)) {
            throw new AuthorizationException;
        }
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
