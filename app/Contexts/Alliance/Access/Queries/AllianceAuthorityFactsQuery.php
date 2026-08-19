<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Queries;

use App\Contexts\Alliance\Access\ValueObjects\AllianceAuthorityFacts;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use Illuminate\Support\Facades\DB;
use LogicException;

final class AllianceAuthorityFactsQuery
{
    public function lockCurrent(string $playerId, string $allianceId): ?AllianceAuthorityFacts
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Alliance authority must be locked inside a database transaction.');
        }

        $alliance = Alliance::query()->whereKey($allianceId)->lockForUpdate()->first();
        if (! $alliance instanceof Alliance || $alliance->status !== AllianceStatus::Active) {
            return null;
        }

        $membership = AllianceMembership::query()
            ->where('player_id', $playerId)
            ->where('alliance_id', $allianceId)
            ->where('status', MembershipStatus::Active->value)
            ->lockForUpdate()
            ->first();

        if (! $membership instanceof AllianceMembership) {
            return null;
        }

        return $this->snapshot($membership, (string) $alliance->kingdom_id);
    }

    public function findCurrent(string $playerId, string $allianceId): ?AllianceAuthorityFacts
    {
        $membership = AllianceMembership::query()
            ->select(
                'alliance_memberships.*',
                'alliances.kingdom_id as authority_kingdom_id',
            )
            ->join('alliances', 'alliances.id', '=', 'alliance_memberships.alliance_id')
            ->where('alliance_memberships.player_id', $playerId)
            ->where('alliance_memberships.alliance_id', $allianceId)
            ->where('alliance_memberships.status', MembershipStatus::Active->value)
            ->where('alliances.status', AllianceStatus::Active->value)
            ->first();

        if (! $membership instanceof AllianceMembership) {
            return null;
        }

        return $this->snapshot(
            $membership,
            (string) $membership->getAttribute('authority_kingdom_id'),
        );
    }

    private function snapshot(AllianceMembership $membership, string $kingdomId): AllianceAuthorityFacts
    {
        $roleKeys = $membership->roles()
            ->orderBy('roles.key')
            ->pluck('roles.key')
            ->map(static fn ($key): string => (string) $key)
            ->all();

        return new AllianceAuthorityFacts(
            playerId: (string) $membership->player_id,
            allianceId: (string) $membership->alliance_id,
            kingdomId: $kingdomId,
            rankObservedAtRead: $membership->rank,
            roleKeysObservedAtRead: $roleKeys,
        );
    }
}
