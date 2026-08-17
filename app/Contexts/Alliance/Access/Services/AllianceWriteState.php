<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Services;

use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Policy-free transaction-time state acquisition for Alliance writes.
 *
 * Callers provide identity only. This service reloads current Alliance-owned
 * authority state inside the transaction and resolves current Player identity
 * through the GameWorld owner query.
 */
final readonly class AllianceWriteState
{
    public function __construct(private PlayerReferenceQuery $players) {}

    public function lockActiveScope(string $actorPlayerId, string $allianceId): AllianceMutationContext
    {
        return $this->lockScope($actorPlayerId, $allianceId, false);
    }

    public function lockExclusiveScope(string $actorPlayerId, string $allianceId): AllianceMutationContext
    {
        return $this->lockScope($actorPlayerId, $allianceId, true);
    }

    private function lockScope(string $actorPlayerId, string $allianceId, bool $exclusiveAlliance): AllianceMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Alliance write state must be acquired inside a database transaction.');
        }

        $allianceQuery = Alliance::query()->whereKey($allianceId);
        $alliance = $exclusiveAlliance
            ? $allianceQuery->lockForUpdate()->firstOrFail()
            : $allianceQuery->sharedLock()->firstOrFail();

        if ($alliance->status !== AllianceStatus::Active) {
            throw new AuthorizationException;
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $actorPlayerId)
            ->where('status', MembershipStatus::Active->value)
            ->lockForUpdate()
            ->first();

        if (! $membership instanceof AllianceMembership) {
            throw new AuthorizationException;
        }

        $actor = $this->players->require((string) $membership->player_id);
        if ($actor->kingdomId !== (string) $alliance->kingdom_id) {
            throw new AuthorizationException;
        }

        return new AllianceMutationContext($alliance, $actor, $membership);
    }
}
