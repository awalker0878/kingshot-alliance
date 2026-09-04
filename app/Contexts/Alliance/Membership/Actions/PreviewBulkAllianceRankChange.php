<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use Illuminate\Validation\ValidationException;

final readonly class PreviewBulkAllianceRankChange
{
    public function __construct(private AllianceAuthorization $authorization) {}

    /**
     * @param  list<string>  $membershipIds
     * @return array<string, mixed>
     */
    public function handle(string $allianceId, string $actorPlayerId, array $membershipIds, AllianceRank $rank): array
    {
        $membershipIds = array_values(array_unique($membershipIds));
        if ($membershipIds === [] || count($membershipIds) > 50) {
            throw ValidationException::withMessages(['membership_ids' => 'Select between 1 and 50 Alliance memberships.']);
        }
        if ($rank === AllianceRank::R5) {
            throw ValidationException::withMessages(['rank' => 'Use Alliance leadership transfer to assign R5.']);
        }
        $this->authorization->authorize($actorPlayerId, $allianceId, AlliancePermission::RoleManage);

        $rows = AllianceMembership::query()->where('alliance_id', $allianceId)->whereIn('id', $membershipIds)->get()->keyBy('id');
        $items = [];
        foreach ($membershipIds as $membershipId) {
            $membership = $rows->get($membershipId);
            $outcome = 'ready';
            $code = 'ready';
            if (! $membership instanceof AllianceMembership) {
                $outcome = 'blocked';
                $code = 'membership_not_found';
            } elseif ($membership->status !== MembershipStatus::Active) {
                $outcome = 'blocked';
                $code = 'membership_inactive';
            } elseif ((string) $membership->player_id === $actorPlayerId) {
                $outcome = 'blocked';
                $code = 'self_rank_change_blocked';
            } elseif ($membership->rank === AllianceRank::R5) {
                $outcome = 'blocked';
                $code = 'leadership_transfer_required';
            } elseif ($membership->rank === $rank) {
                $outcome = 'skipped';
                $code = 'already_set';
            }
            $items[] = [
                'itemId' => $membershipId,
                'playerId' => $membership instanceof AllianceMembership ? (string) $membership->player_id : null,
                'fromRank' => $membership instanceof AllianceMembership ? $membership->rank->value : null,
                'toRank' => $rank->value,
                'outcome' => $outcome,
                'code' => $code,
            ];
        }

        return [
            'operation' => 'rank',
            'targetRank' => $rank->value,
            'items' => $items,
            'ready' => count(array_filter($items, static fn (array $item): bool => $item['outcome'] === 'ready')),
            'blocked' => count(array_filter($items, static fn (array $item): bool => $item['outcome'] === 'blocked')),
        ];
    }
}
