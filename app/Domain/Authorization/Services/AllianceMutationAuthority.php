<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\ValueObjects\AllianceMutationContext;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class AllianceMutationAuthority
{
    public function __construct(private AlliancePermissionEvaluator $permissions) {}

    /**
     * Acquire authority for an ordinary Alliance mutation.
     *
     * The Alliance receives a shared row lock so concurrent ordinary mutations can
     * proceed together while lifecycle changes (suspend/close/delete) wait. The
     * actor's active membership is locked exclusively because membership status,
     * rank, and specialist roles are the mutable Alliance-authority record.
     */
    public function require(
        Player $actor,
        Alliance $alliance,
        PermissionKey $permission,
    ): AllianceMutationContext {
        return $this->acquire($actor, $alliance, $permission, false);
    }

    /**
     * Acquire authority for an Alliance-wide invariant such as capacity/quota,
     * leadership, or a singleton state transition.
     */
    public function requireExclusive(
        Player $actor,
        Alliance $alliance,
        PermissionKey $permission,
    ): AllianceMutationContext {
        return $this->acquire($actor, $alliance, $permission, true);
    }

    private function acquire(
        Player $actor,
        Alliance $alliance,
        PermissionKey $permission,
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
        if ((string) $currentActor->current_kingdom_id !== (string) $currentAlliance->kingdom_id
            || ! $this->permissions->allows($membership, $currentAlliance, $permission)) {
            throw new AuthorizationException;
        }

        return new AllianceMutationContext(
            alliance: $currentAlliance,
            actor: $currentActor,
            membership: $membership,
        );
    }
}
