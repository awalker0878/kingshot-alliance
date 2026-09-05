<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use Illuminate\Support\Facades\DB;

final class GiftCodeRedemptionSignalService
{
    /**
     * @return array{sampleCount:int,distinctAccounts:int,successCount:int,successRate:float,statusCounts:array<string,int>,lastSuccessAt:?string,windowHours:int}|null
     */
    public function forGiftCode(string $giftCodeId): ?array
    {
        if (! (bool) config('game_world.gift_codes.redemption_intelligence', false)) {
            return null;
        }

        $windowHours = max(1, min(24 * 30, (int) config('game_world.gift_codes.intelligence_window_hours', 168)));
        $minimumSamples = max(2, (int) config('game_world.gift_codes.intelligence_min_samples', 5));
        $minimumAccounts = max(2, (int) config('game_world.gift_codes.intelligence_min_accounts', 5));
        $since = now()->subHours($windowHours);

        $base = DB::table('gift_code_redemptions')
            ->join('players', 'players.id', '=', 'gift_code_redemptions.player_id')
            ->where('gift_code_redemptions.gift_code_id', $giftCodeId)
            ->whereNotNull('gift_code_redemptions.last_attempt_at')
            ->where('gift_code_redemptions.last_attempt_at', '>=', $since)
            ->whereNotNull('players.user_id');

        $sampleCount = (clone $base)->count();
        $distinctAccounts = (clone $base)->distinct()->count('players.user_id');
        if ($sampleCount < $minimumSamples || $distinctAccounts < $minimumAccounts) {
            return null;
        }

        $statusCounts = (clone $base)
            ->select('gift_code_redemptions.status', DB::raw('COUNT(*) AS aggregate_count'))
            ->groupBy('gift_code_redemptions.status')
            ->pluck('aggregate_count', 'gift_code_redemptions.status')
            ->map(static fn ($count): int => (int) $count)
            ->all();
        $successCount = (int) (($statusCounts[GiftCodeRedemptionStatus::Redeemed->value] ?? 0)
            + ($statusCounts[GiftCodeRedemptionStatus::AlreadyRedeemed->value] ?? 0));
        $lastSuccess = (clone $base)
            ->whereIn('gift_code_redemptions.status', [
                GiftCodeRedemptionStatus::Redeemed->value,
                GiftCodeRedemptionStatus::AlreadyRedeemed->value,
            ])
            ->max('gift_code_redemptions.last_attempt_at');

        return [
            'sampleCount' => $sampleCount,
            'distinctAccounts' => $distinctAccounts,
            'successCount' => $successCount,
            'successRate' => $sampleCount === 0 ? 0.0 : round(($successCount / $sampleCount) * 100, 1),
            'statusCounts' => $statusCounts,
            'lastSuccessAt' => is_string($lastSuccess) ? $lastSuccess : null,
            'windowHours' => $windowHours,
        ];
    }
}
