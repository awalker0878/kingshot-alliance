<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Queries;

use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\Invitation;

final class MembershipStatisticsQuery
{
    public function activeCount(string $allianceId): int
    {
        return AllianceMembership::query()
            ->where('alliance_id', $allianceId)
            ->where('status', MembershipStatus::Active->value)
            ->count();
    }

    public function pendingInvitationCount(string $allianceId): int
    {
        return Invitation::query()
            ->where('alliance_id', $allianceId)
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->count();
    }

    /** @return array{active:int, joined_last_30_days:int, left_last_30_days:int} */
    public function contributionStatistics(string $allianceId): array
    {
        return [
            'active' => $this->activeCount($allianceId),
            'joined_last_30_days' => AllianceMembership::query()
                ->where('alliance_id', $allianceId)
                ->whereNotNull('joined_at')
                ->where('joined_at', '>=', now()->subDays(30))
                ->count(),
            'left_last_30_days' => AllianceMembership::query()
                ->where('alliance_id', $allianceId)
                ->whereNotNull('left_at')
                ->where('left_at', '>=', now()->subDays(30))
                ->count(),
        ];
    }

    /** @return list<array{playerId:string, rankObservedAtRead:string}> */
    public function activeMemberFacts(string $allianceId): array
    {
        return array_values(
            AllianceMembership::query()    
            ->where('alliance_id', $allianceId)    
            ->where('status', MembershipStatus::Active->value)    
            ->orderBy('created_at')    
            ->get(['player_id', 'rank'])    
            ->map(static fn (AllianceMembership $membership): array => [    
                'playerId' => (string) $membership->player_id,    
                'rankObservedAtRead' => $membership->rank->value,    
            ])    
            ->values()    
            ->all(),
        );
    }
}
