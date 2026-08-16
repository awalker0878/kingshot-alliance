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
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class AllianceMutationAuthority
{
    public function __construct(private AlliancePermissionEvaluator $permissions) {}

    /**
     * Acquire authority for an ordinary Alliance-owned mutation.
     *
     * Downstream contexts must use acquireActiveScope() and apply their own
     * permission policy after the current membership has been locked.
     */
    public function require(
        Player $actor,
        Alliance $alliance,
        AlliancePermission $permission,
    ): AllianceMutationContext {
        $context = $this->acquire($actor, $alliance, false);

        if (! $this->permissions->allows($context->membership, $context->alliance, $permission)) {
            throw new AuthorizationException;
        }

        return $context;
    }

    /**
     * Acquire authority for an Alliance-owned invariant such as capacity/quota,
     * leadership, or a singleton state transition.
     */
    public function requireExclusive(
        Player $actor,
        Alliance $alliance,
        AlliancePermission $permission,
    ): AllianceMutationContext {
        $context = $this->acquire($actor, $alliance, true);

        if (! $this->permissions->allows($context->membership, $context->alliance, $permission)) {
            throw new AuthorizationException;
        }

        return $context;
    }

    /**
     * Lock and return the current active Alliance scope without interpreting a
     * downstream context's permission vocabulary. The caller owns authorization.
     */
    public function acquireActiveScope(Player $actor, Alliance $alliance): AllianceMutationContext
    {
        return $this->acquire($actor, $alliance, false);
    }

    private function acquire(
        Player $actor,
        Alliance $alliance,
        bool $exclusiveAlliance,
    ): AllianceMutationContext {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Alliance mutation authority must be acquired inside a database transaction.');
        }

        $allianceQuery = Alliance::query()->whereKey($alliance->id);
        $currentAlliance = $exclusiveAlliance
            ? $allianceQuery->lockForUpdate()->firstOrFail()
            : $allianceQuery->sharedLock()->firstOrFail();

        if ($currentAlliance->status !== AllianceStatus::Active) {
            throw new AuthorizationException;
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $currentAlliance->id)
            ->where('player_id', $actor->id)
            ->where('status', MembershipStatus::Active->value)
            ->lockForUpdate()
            ->first();

        if (! $membership instanceof AllianceMembership) {
            throw new AuthorizationException;
        }

        $currentActor = Player::query()->whereKey($membership->player_id)->firstOrFail();
        if ((string) $currentActor->current_kingdom_id !== (string) $currentAlliance->kingdom_id) {
            throw new AuthorizationException;
        }

        return new AllianceMutationContext(
            alliance: $currentAlliance,
            actor: $currentActor,
            membership: $membership,
        );
    }
}
