<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Services;

use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Policy-free transaction-time state acquisition for Alliance writes.
 *
 * This service stabilizes the Alliance, active membership, and active Player
 * scope. It does not interpret Alliance, Operations, Intelligence, or Transfer
 * permission vocabularies.
 */
final class AllianceWriteState
{
    public function lockActiveScope(Player $actor, Alliance $alliance): AllianceMutationContext
    {
        return $this->lockScope($actor, $alliance, false);
    }

    public function lockExclusiveScope(Player $actor, Alliance $alliance): AllianceMutationContext
    {
        return $this->lockScope($actor, $alliance, true);
    }

    private function lockScope(Player $actor, Alliance $alliance, bool $exclusiveAlliance): AllianceMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Alliance write state must be acquired inside a database transaction.');
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

        $currentActor = Player::query()
            ->whereKey($membership->player_id)
            
            ->firstOrFail();

        if ((string) $currentActor->current_kingdom_id !== (string) $currentAlliance->kingdom_id) {
            throw new AuthorizationException;
        }

        return new AllianceMutationContext($currentAlliance, $currentActor, $membership);
    }
}
