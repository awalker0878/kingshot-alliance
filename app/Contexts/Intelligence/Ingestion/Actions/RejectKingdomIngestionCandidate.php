<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionCandidateState;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionBatch;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionCandidate;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionSubscription;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RejectKingdomIngestionCandidate
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $subscriptionId,
        string $candidateId,
    ): string {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $subscriptionId, $candidateId): string {
            [$scope, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $subscription = KingdomIngestionSubscription::query()
                ->where('alliance_id', $allianceId)
                ->whereKey($subscriptionId)
                ->lockForUpdate()
                ->firstOrFail();

            $route = KingdomIngestionCandidate::query()
                ->select(['id', 'batch_id'])
                ->where('alliance_id', $allianceId)
                ->where('subscription_id', $subscription->id)
                ->whereKey($candidateId)
                ->firstOrFail();
            $batch = KingdomIngestionBatch::query()
                ->where('subscription_id', $subscription->id)
                ->whereKey($route->batch_id)
                ->lockForUpdate()
                ->firstOrFail();
            $candidate = KingdomIngestionCandidate::query()
                ->where('alliance_id', $allianceId)
                ->where('subscription_id', $subscription->id)
                ->where('batch_id', $batch->id)
                ->whereKey($route->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($candidate->state === KingdomIngestionCandidateState::Rejected) {
                return (string) $candidate->id;
            }
            if ($candidate->state !== KingdomIngestionCandidateState::Quarantined) {
                throw ValidationException::withMessages([
                    'candidate' => 'Only quarantined ingestion candidates can be rejected.',
                ]);
            }

            $candidate->forceFill([
                'state' => KingdomIngestionCandidateState::Rejected,
                'rejection_code' => 'manager_rejected',
            ])->save();
            $batch->increment('records_rejected');

            $metadata = [
                'subscription_id' => (string) $subscription->id,
                'batch_id' => (string) $batch->id,
                'candidate_id' => (string) $candidate->id,
                'target_kind' => $candidate->target_kind->value,
                'quarantine_code' => $candidate->quarantine_code,
                'rejection_code' => 'manager_rejected',
                'origin' => 'player',
            ];
            $event = 'intelligence.ingestion_candidate_rejected';
            $this->audit->record($event, $actor, $candidate, $allianceId, $metadata);
            $this->outbox->record($event, (string) $allianceId, $candidate, $metadata);

            return (string) $candidate->id;
        });
    }
}
