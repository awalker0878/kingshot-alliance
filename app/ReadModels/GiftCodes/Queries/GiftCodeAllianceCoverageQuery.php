<?php

declare(strict_types=1);

namespace App\ReadModels\GiftCodes\Queries;

use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Validation\ValidationException;

/** Cross-context aggregate projection. It exposes counts only, never member redemption history. */
final class GiftCodeAllianceCoverageQuery
{
    /**
     * @return array{eligibleGovernors:int,codes:list<array{id:string,code:string,expiresAt:?string,completed:int,incomplete:int,retryReady:int,unknown:int}>}
     */
    public function forAlliance(string $allianceId, int $limit = 50): array
    {
        $memberIds = AllianceMembership::query()
            ->where('alliance_id', $allianceId)
            ->where('status', MembershipStatus::Active->value)
            ->orderBy('id')
            ->limit(2001)
            ->pluck('player_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
        if (count($memberIds) > 2000) {
            throw ValidationException::withMessages(['alliance' => 'Alliance Gift Code coverage exceeds the supported Governor bound.']);
        }

        $readyPlayerIds = Player::query()
            ->whereIn('id', $memberIds)
            ->whereNotNull('game_player_id')
            ->where('game_player_id', '<>', '')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
        $unknown = max(0, count($memberIds) - count($readyPlayerIds));
        $limit = max(1, min(100, $limit));
        $codes = GiftCode::query()
            ->where('status', GiftCodeStatus::Valid->value)
            ->where(static fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderByDesc('discovered_at')
            ->limit($limit)
            ->get(['id', 'code', 'expires_at']);
        if ($codes->isEmpty()) {
            return ['eligibleGovernors' => count($readyPlayerIds), 'codes' => []];
        }

        $codeIds = $codes->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $grouped = GiftCodeRedemption::query()
            ->whereIn('gift_code_id', $codeIds)
            ->whereIn('player_id', $readyPlayerIds)
            ->get(['gift_code_id', 'status', 'next_attempt_at'])
            ->groupBy('gift_code_id');

        $result = [];
        foreach ($codes as $code) {
            $redemptions = $grouped->get((string) $code->id, collect());
            $completed = $redemptions->filter(static fn (GiftCodeRedemption $redemption): bool => $redemption->status->successful())->count();
            $retryReady = $redemptions->filter(static fn (GiftCodeRedemption $redemption): bool =>
                in_array($redemption->status, [GiftCodeRedemptionStatus::RateLimited, GiftCodeRedemptionStatus::TransientFailure], true)
                && ($redemption->next_attempt_at === null || $redemption->next_attempt_at->isPast())
            )->count();
            $result[] = [
                'id' => (string) $code->id,
                'code' => $code->code,
                'expiresAt' => $code->expires_at?->toIso8601String(),
                'completed' => $completed,
                'incomplete' => max(0, count($readyPlayerIds) - $completed),
                'retryReady' => $retryReady,
                'unknown' => $unknown,
            ];
        }

        return ['eligibleGovernors' => count($readyPlayerIds), 'codes' => $result];
    }
}
