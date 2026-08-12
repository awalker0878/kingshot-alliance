<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Kingdoms\Enums\KingdomAllianceStatus;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Enums\KingdomIngestionTargetKind;
use App\Domain\Kingdoms\Enums\TrackedKingdomAllianceState;
use App\Domain\Kingdoms\Models\KingdomAlliance;
use App\Domain\Kingdoms\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\KingdomIngestionBatch;
use App\Domain\Kingdoms\Models\KingdomIngestionCandidate;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Kingdoms\Services\KingdomIngestionAdapterRegistry;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PromoteKingdomIngestionAllianceObservation
{
    public function __construct(
        private KingdomIngestionAdapterRegistry $adapters,
        private RecordKingdomAllianceObservation $observations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $subscriptionId, string $candidateId): ?KingdomAllianceObservation
    {
        return DB::transaction(function () use ($subscriptionId, $candidateId): ?KingdomAllianceObservation {
            $subscription = KingdomIngestionSubscription::query()->lockForUpdate()->findOrFail($subscriptionId);
            $candidate = KingdomIngestionCandidate::query()
                ->where('subscription_id', $subscription->id)
                ->lockForUpdate()
                ->findOrFail($candidateId);

            if ($candidate->state === KingdomIngestionCandidateState::Promoted) {
                return $this->existingPromotion($candidate);
            }

            if (in_array($candidate->state, [
                KingdomIngestionCandidateState::Quarantined,
                KingdomIngestionCandidateState::Rejected,
            ], true)) {
                return null;
            }

            if ($candidate->target_kind !== KingdomIngestionTargetKind::AllianceObservation) {
                throw ValidationException::withMessages([
                    'candidate' => 'This promotion path accepts only game-alliance observation candidates.',
                ]);
            }

            if ($subscription->state !== KingdomIngestionSubscriptionState::Active) {
                throw ValidationException::withMessages([
                    'subscription' => 'The ingestion subscription must be active before a candidate can be promoted.',
                ]);
            }

            $batch = KingdomIngestionBatch::query()
                ->where('subscription_id', $subscription->id)
                ->lockForUpdate()
                ->findOrFail($candidate->batch_id);
            $alliance = Alliance::query()->lockForUpdate()->findOrFail($subscription->alliance_id);

            if (
                $candidate->alliance_id !== $subscription->alliance_id
                || $candidate->kingdom_id !== $subscription->kingdom_id
                || $batch->alliance_id !== $subscription->alliance_id
                || $batch->kingdom_id !== $subscription->kingdom_id
                || $batch->adapter_key !== $subscription->adapter_key
                || $batch->adapter_version !== $subscription->adapter_version
            ) {
                $this->quarantine($candidate, $batch, 'candidate_context_mismatch');

                return null;
            }

            if ($alliance->kingdom_id === null || $alliance->kingdom_id !== $subscription->kingdom_id) {
                $this->quarantine($candidate, $batch, 'kingdom_context_changed');

                return null;
            }

            if (! $this->sourceStillApproved($subscription)) {
                $this->quarantine($candidate, $batch, 'source_version_unapproved');

                return null;
            }

            if ($candidate->stable_game_id === null || trim($candidate->stable_game_id) === '') {
                $this->quarantine($candidate, $batch, 'missing_stable_game_id');

                return null;
            }

            $references = KingdomAlliance::query()
                ->where('kingdom_id', $subscription->kingdom_id)
                ->where('game_alliance_id', $candidate->stable_game_id)
                ->limit(2)
                ->get();

            if ($references->isEmpty()) {
                $this->quarantine($candidate, $batch, 'unknown_game_alliance');

                return null;
            }

            if ($references->count() !== 1) {
                $this->quarantine($candidate, $batch, 'ambiguous_game_alliance_identity');

                return null;
            }

            $reference = $references->first();
            if ($reference->status !== KingdomAllianceStatus::Active) {
                $this->quarantine($candidate, $batch, 'game_alliance_inactive');

                return null;
            }

            $trackingRows = TrackedKingdomAlliance::query()
                ->where('alliance_id', $subscription->alliance_id)
                ->where('kingdom_id', $subscription->kingdom_id)
                ->where('kingdom_alliance_id', $reference->id)
                ->lockForUpdate()
                ->limit(3)
                ->get();
            $activeTracking = $trackingRows
                ->filter(static fn (TrackedKingdomAlliance $tracking): bool => $tracking->state === TrackedKingdomAllianceState::Active)
                ->values();

            if ($activeTracking->isEmpty()) {
                $this->quarantine(
                    $candidate,
                    $batch,
                    $trackingRows->isEmpty() ? 'tracking_target_missing' : 'tracking_target_inactive',
                );

                return null;
            }

            if ($activeTracking->count() !== 1) {
                $this->quarantine($candidate, $batch, 'ambiguous_tracking_target');

                return null;
            }

            $tracking = $activeTracking->first();

            try {
                $attributes = $this->observationAttributes($candidate);
                $observation = $this->observations->handle(
                    $alliance,
                    null,
                    (string) $tracking->id,
                    $attributes,
                    'ingestion',
                    [
                        'subscription_id' => (string) $subscription->id,
                        'batch_id' => (string) $batch->id,
                        'adapter_key' => $subscription->adapter_key,
                        'adapter_version' => $subscription->adapter_version,
                        'source_record_id' => $candidate->source_record_id,
                        'identity_hash' => $candidate->identity_hash,
                        'payload_hash' => $candidate->payload_hash,
                    ],
                );
            } catch (ValidationException) {
                $this->quarantine($candidate, $batch, 'observation_validation_failed');

                return null;
            }

            $candidate->forceFill([
                'state' => KingdomIngestionCandidateState::Promoted,
                'quarantine_code' => null,
                'rejection_code' => null,
                'promoted_record_type' => KingdomIngestionTargetKind::AllianceObservation->value,
                'promoted_record_id' => (string) $observation->id,
                'promoted_at' => now(),
            ])->save();

            $metadata = [
                'subscription_id' => (string) $subscription->id,
                'batch_id' => (string) $batch->id,
                'candidate_id' => (string) $candidate->id,
                'observation_id' => (string) $observation->id,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $reference->id,
                'stable_game_id' => $candidate->stable_game_id,
                'adapter_key' => $subscription->adapter_key,
                'adapter_version' => $subscription->adapter_version,
                'source_record_id' => $candidate->source_record_id,
                'identity_hash' => $candidate->identity_hash,
                'payload_hash' => $candidate->payload_hash,
            ];

            $event = 'kingdoms.ingestion_candidate_promoted';
            $this->audit->record($event, null, $candidate, $alliance, $metadata);
            $this->outbox->record(
                $event,
                (string) $alliance->id,
                $candidate,
                $metadata,
                $event.':'.$candidate->id,
            );

            return $observation;
        });
    }

    private function existingPromotion(KingdomIngestionCandidate $candidate): KingdomAllianceObservation
    {
        if (
            $candidate->promoted_record_type !== KingdomIngestionTargetKind::AllianceObservation->value
            || $candidate->promoted_record_id === null
        ) {
            throw ValidationException::withMessages([
                'candidate' => 'The promoted candidate does not contain a valid game-alliance observation reference.',
            ]);
        }

        return KingdomAllianceObservation::query()
            ->where('alliance_id', $candidate->alliance_id)
            ->findOrFail($candidate->promoted_record_id);
    }

    private function sourceStillApproved(KingdomIngestionSubscription $subscription): bool
    {
        try {
            $adapter = $this->adapters->require($subscription->adapter_key);
        } catch (ValidationException) {
            return false;
        }

        return $adapter->version() === $subscription->adapter_version
            && in_array(KingdomIngestionTargetKind::AllianceObservation, $adapter->supportedTargetKinds(), true);
    }

    /**
     * @return array{
     *   observed_name: string,
     *   observed_tag: string|null,
     *   power: string|null,
     *   member_count: int|null,
     *   captured_at: string
     * }
     */
    private function observationAttributes(KingdomIngestionCandidate $candidate): array
    {
        $payload = $candidate->normalized_payload;
        $observedName = $payload['observed_name'] ?? null;
        $observedTag = $payload['observed_tag'] ?? null;
        $power = $payload['power'] ?? null;
        $memberCount = $payload['member_count'] ?? null;

        if (
            ! is_string($observedName)
            || ($observedTag !== null && ! is_string($observedTag))
            || ($power !== null && ! is_string($power))
            || ($memberCount !== null && ! is_int($memberCount))
        ) {
            throw ValidationException::withMessages([
                'candidate' => 'The normalized game-alliance observation payload is invalid.',
            ]);
        }

        return [
            'observed_name' => $observedName,
            'observed_tag' => $observedTag,
            'power' => $power,
            'member_count' => $memberCount,
            'captured_at' => $candidate->captured_at->toIso8601String(),
        ];
    }

    private function quarantine(
        KingdomIngestionCandidate $candidate,
        KingdomIngestionBatch $batch,
        string $reasonCode,
    ): void {
        $candidate->forceFill([
            'state' => KingdomIngestionCandidateState::Quarantined,
            'quarantine_code' => $reasonCode,
        ])->save();
        $batch->increment('records_quarantined');

        $event = 'kingdoms.ingestion_candidate_quarantined';
        $this->outbox->record(
            $event,
            (string) $candidate->alliance_id,
            $candidate,
            [
                'subscription_id' => (string) $candidate->subscription_id,
                'batch_id' => (string) $candidate->batch_id,
                'candidate_id' => (string) $candidate->id,
                'target_kind' => $candidate->target_kind->value,
                'quarantine_code' => $reasonCode,
            ],
            $event.':'.$candidate->id.':'.$reasonCode,
        );
    }
}
