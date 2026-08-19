<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionCandidateState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionSubscriptionState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionTargetKind;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionBatch;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionCandidate;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionSubscription;
use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionAdapterRegistry;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReplayKingdomIngestionCandidate
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private KingdomIngestionAdapterRegistry $adapters,
        private PromoteKingdomIngestionPlayerSnapshot $promotePlayer,
        private PromoteKingdomIngestionAllianceObservation $promoteAlliance,
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

            if ($candidate->state !== KingdomIngestionCandidateState::Quarantined) {
                throw ValidationException::withMessages([
                    'candidate' => 'Only quarantined ingestion candidates can be replayed.',
                ]);
            }
            if ($subscription->state !== KingdomIngestionSubscriptionState::Active) {
                throw ValidationException::withMessages([
                    'subscription' => 'The ingestion subscription must be active before replay.',
                ]);
            }
            if ($scope->kingdomId !== (string) $subscription->kingdom_id) {
                throw ValidationException::withMessages([
                    'subscription' => 'Replay is blocked because the alliance Kingdom no longer matches the subscription context.',
                ]);
            }

            $adapter = $this->adapters->require($subscription->adapter_key);
            if ($adapter->version() !== $subscription->adapter_version
                || ! in_array($candidate->target_kind, $adapter->supportedTargetKinds(), true)) {
                throw ValidationException::withMessages([
                    'subscription' => 'Replay is blocked because the candidate source/version is no longer approved.',
                ]);
            }

            $previousCode = $candidate->quarantine_code;
            $candidate->forceFill([
                'state' => KingdomIngestionCandidateState::Pending,
                'quarantine_code' => null,
                'rejection_code' => null,
            ])->save();
            if ($batch->records_quarantined > 0) {
                $batch->decrement('records_quarantined');
            }

            $metadata = [
                'subscription_id' => (string) $subscription->id,
                'batch_id' => (string) $batch->id,
                'candidate_id' => (string) $candidate->id,
                'target_kind' => $candidate->target_kind->value,
                'previous_quarantine_code' => $previousCode,
                'origin' => 'player',
            ];
            $event = 'intelligence.ingestion_replay_requested';
            $this->audit->record($event, $actor, $candidate, $allianceId, $metadata);
            $this->outbox->record($event, (string) $allianceId, $candidate, $metadata);

            match ($candidate->target_kind) {
                KingdomIngestionTargetKind::PlayerSnapshot => $this->promotePlayer->handle(
                    (string) $subscription->id,
                    (string) $candidate->id,
                ),
                KingdomIngestionTargetKind::AllianceObservation => $this->promoteAlliance->handle(
                    (string) $subscription->id,
                    (string) $candidate->id,
                ),
            };

            return (string) $candidate->id;
        });
    }
}
