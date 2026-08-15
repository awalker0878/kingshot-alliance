<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Kingdoms\Enums\KingdomIngestionBatchState;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Contexts\GameWorld\Models\KingdomIngestionBatch;
use App\Contexts\GameWorld\Models\KingdomIngestionCandidate;
use App\Contexts\GameWorld\Models\KingdomIngestionSubscription;
use Illuminate\Support\Facades\DB;

final class EnforceKingdomIngestionRetention
{
    /**
     * @return array{
     *   payloadsRedacted: int,
     *   terminalCandidatesPurged: int,
     *   quarantinedCandidatesPurged: int,
     *   batchesPurged: int,
     *   disabledSubscriptionsCompacted: int
     * }
     */
    public function handle(): array
    {
        $payloadDays = max(1, (int) config('kingdoms.ingestion_retention.payload_days', 30));
        $terminalCandidateDays = max($payloadDays, (int) config('kingdoms.ingestion_retention.terminal_candidate_days', 90));
        $quarantinedCandidateDays = max($terminalCandidateDays, (int) config('kingdoms.ingestion_retention.quarantined_candidate_days', 180));
        $batchDays = max($terminalCandidateDays, (int) config('kingdoms.ingestion_retention.batch_days', 90));
        $disabledCompactionDays = max(1, (int) config('kingdoms.ingestion_retention.disabled_compaction_days', 30));

        $payloadsRedacted = DB::table('kingdom_ingestion_candidates')
            ->whereIn('state', [
                KingdomIngestionCandidateState::Promoted->value,
                KingdomIngestionCandidateState::Rejected->value,
            ])
            ->where('updated_at', '<', now()->subDays($payloadDays))
            ->update([
                'normalized_payload' => '[]',
            ]);

        $terminalCandidatesPurged = KingdomIngestionCandidate::query()
            ->whereIn('state', [
                KingdomIngestionCandidateState::Promoted->value,
                KingdomIngestionCandidateState::Rejected->value,
            ])
            ->where('updated_at', '<', now()->subDays($terminalCandidateDays))
            ->delete();

        $quarantinedCandidatesPurged = KingdomIngestionCandidate::query()
            ->where('state', KingdomIngestionCandidateState::Quarantined->value)
            ->where('updated_at', '<', now()->subDays($quarantinedCandidateDays))
            ->delete();

        $batchesPurged = KingdomIngestionBatch::query()
            ->whereIn('state', [
                KingdomIngestionBatchState::Completed->value,
                KingdomIngestionBatchState::Partial->value,
                KingdomIngestionBatchState::Failed->value,
                KingdomIngestionBatchState::Blocked->value,
            ])
            ->whereNotNull('completed_at')
            ->where('completed_at', '<', now()->subDays($batchDays))
            ->whereDoesntHave('candidates')
            ->delete();

        $disabledSubscriptionsCompacted = KingdomIngestionSubscription::query()
            ->where('state', KingdomIngestionSubscriptionState::Disabled->value)
            ->where('updated_at', '<', now()->subDays($disabledCompactionDays))
            ->update([
                'next_run_at' => null,
                'last_claimed_at' => null,
                'consecutive_failures' => 0,
                'circuit_open_until' => null,
                'last_failure_code' => null,
                'updated_at' => now(),
            ]);

        return [
            'payloadsRedacted' => $payloadsRedacted,
            'terminalCandidatesPurged' => $terminalCandidatesPurged,
            'quarantinedCandidatesPurged' => $quarantinedCandidatesPurged,
            'batchesPurged' => $batchesPurged,
            'disabledSubscriptionsCompacted' => $disabledSubscriptionsCompacted,
        ];
    }
}
