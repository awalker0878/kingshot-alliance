<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Services;

use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionBatchState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionCandidateState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionSubscriptionState;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionBatch;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionCandidate;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionSubscription;

final class KingdomIngestionOperationalHealth
{
    /**
     * @return array{
     *   activeSubscriptions: int,
     *   sourceRevokedSubscriptions: int,
     *   overdueSubscriptions: int,
     *   openCircuits: int,
     *   stalePendingCandidates: int,
     *   quarantinedCandidates: int,
     *   recentFailedBatches: int,
     *   attentionRequired: bool
     * }
     */
    public function snapshot(): array
    {
        $overdueMinutes = max(1, (int) config('intelligence.ingestion_health.overdue_minutes', 5));
        $stalePendingMinutes = max(1, (int) config('intelligence.ingestion_health.stale_pending_minutes', 15));
        $quarantinedThreshold = max(1, (int) config('intelligence.ingestion_health.quarantined_threshold', 25));
        $recentFailureMinutes = max(1, (int) config('intelligence.ingestion_health.recent_failure_minutes', 60));

        $activeSubscriptions = KingdomIngestionSubscription::query()
            ->where('state', KingdomIngestionSubscriptionState::Active->value)
            ->count();

        $sourceRevokedSubscriptions = KingdomIngestionSubscription::query()
            ->where('state', KingdomIngestionSubscriptionState::Disabled->value)
            ->where('blocked_reason', 'source_unapproved')
            ->count();

        $overdueSubscriptions = KingdomIngestionSubscription::query()
            ->where('state', KingdomIngestionSubscriptionState::Active->value)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<', now()->subMinutes($overdueMinutes))
            ->where(static function ($query): void {
                $query->whereNull('circuit_open_until')->orWhere('circuit_open_until', '<=', now());
            })
            ->count();

        $openCircuits = KingdomIngestionSubscription::query()
            ->where('state', KingdomIngestionSubscriptionState::Active->value)
            ->where('circuit_open_until', '>', now())
            ->count();

        $stalePendingCandidates = KingdomIngestionCandidate::query()
            ->where('state', KingdomIngestionCandidateState::Pending->value)
            ->where('created_at', '<', now()->subMinutes($stalePendingMinutes))
            ->count();

        $quarantinedCandidates = KingdomIngestionCandidate::query()
            ->where('state', KingdomIngestionCandidateState::Quarantined->value)
            ->count();

        $recentFailedBatches = KingdomIngestionBatch::query()
            ->whereIn('state', [
                KingdomIngestionBatchState::Failed->value,
                KingdomIngestionBatchState::Blocked->value,
            ])
            ->where('updated_at', '>=', now()->subMinutes($recentFailureMinutes))
            ->count();

        return [
            'activeSubscriptions' => $activeSubscriptions,
            'sourceRevokedSubscriptions' => $sourceRevokedSubscriptions,
            'overdueSubscriptions' => $overdueSubscriptions,
            'openCircuits' => $openCircuits,
            'stalePendingCandidates' => $stalePendingCandidates,
            'quarantinedCandidates' => $quarantinedCandidates,
            'recentFailedBatches' => $recentFailedBatches,
            'attentionRequired' => $sourceRevokedSubscriptions > 0
                || $overdueSubscriptions > 0
                || $stalePendingCandidates > 0
                || $quarantinedCandidates >= $quarantinedThreshold
                || $recentFailedBatches > 0,
        ];
    }
}
