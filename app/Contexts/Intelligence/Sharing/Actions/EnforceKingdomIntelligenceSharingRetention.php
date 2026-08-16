<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Actions;

use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareState;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareTargetState;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EnforceKingdomIntelligenceSharingRetention
{
    /**
     * @return array{
     *   expiredInvitationsPurged: int,
     *   terminalSharesPurged: int,
     *   removedTargetsPurged: int,
     *   processed: int
     * }
     */
    public function handle(int $limit = 500): array
    {
        $remaining = max(1, min(2000, $limit));

        $expiredInvitationDays = max(
            1,
            (int) config('kingdoms.shared_intelligence_retention.expired_invitation_days', 30),
        );
        $terminalShareDays = max(
            $expiredInvitationDays,
            (int) config('kingdoms.shared_intelligence_retention.terminal_share_days', 180),
        );
        $removedTargetDays = max(
            1,
            (int) config('kingdoms.shared_intelligence_retention.removed_target_days', 90),
        );

        $expiredInvitationsPurged = $this->purgeExpiredInvitations($remaining, $expiredInvitationDays);
        $remaining -= $expiredInvitationsPurged;

        $terminalSharesPurged = $remaining > 0
            ? $this->purgeTerminalShares($remaining, $terminalShareDays)
            : 0;
        $remaining -= $terminalSharesPurged;

        $removedTargetsPurged = $remaining > 0
            ? $this->purgeRemovedTargets($remaining, $removedTargetDays)
            : 0;

        return [
            'expiredInvitationsPurged' => $expiredInvitationsPurged,
            'terminalSharesPurged' => $terminalSharesPurged,
            'removedTargetsPurged' => $removedTargetsPurged,
            'processed' => $expiredInvitationsPurged + $terminalSharesPurged + $removedTargetsPurged,
        ];
    }

    private function purgeExpiredInvitations(int $limit, int $days): int
    {
        $cutoff = now()->subDays($days);
        $ids = DB::table('kingdom_intelligence_shares')
            ->where('state', KingdomIntelligenceShareState::Pending->value)
            ->where('invitation_expires_at', '<', $cutoff)
            ->orderBy('invitation_expires_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        if ($ids === []) {
            return 0;
        }

        return DB::table('kingdom_intelligence_shares')
            ->whereIn('id', $ids)
            ->where('state', KingdomIntelligenceShareState::Pending->value)
            ->where('invitation_expires_at', '<', $cutoff)
            ->delete();
    }

    private function purgeTerminalShares(int $limit, int $days): int
    {
        $cutoff = now()->subDays($days);
        $terminal = static function (Builder $query) use ($cutoff): void {
            $query->where(function (Builder $declined) use ($cutoff): void {
                $declined
                    ->where('state', KingdomIntelligenceShareState::Declined->value)
                    ->where('declined_at', '<', $cutoff);
            })->orWhere(function (Builder $revoked) use ($cutoff): void {
                $revoked
                    ->where('state', KingdomIntelligenceShareState::Revoked->value)
                    ->where('revoked_at', '<', $cutoff);
            });
        };

        $ids = DB::table('kingdom_intelligence_shares')
            ->where($terminal)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        if ($ids === []) {
            return 0;
        }

        return DB::table('kingdom_intelligence_shares')
            ->whereIn('id', $ids)
            ->where($terminal)
            ->delete();
    }

    private function purgeRemovedTargets(int $limit, int $days): int
    {
        $cutoff = now()->subDays($days);
        $ids = DB::table('kingdom_intelligence_share_targets')
            ->where('state', KingdomIntelligenceShareTargetState::Removed->value)
            ->where('removed_at', '<', $cutoff)
            ->orderBy('removed_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        if ($ids === []) {
            return 0;
        }

        return DB::table('kingdom_intelligence_share_targets')
            ->whereIn('id', $ids)
            ->where('state', KingdomIntelligenceShareTargetState::Removed->value)
            ->where('removed_at', '<', $cutoff)
            ->delete();
    }
}
