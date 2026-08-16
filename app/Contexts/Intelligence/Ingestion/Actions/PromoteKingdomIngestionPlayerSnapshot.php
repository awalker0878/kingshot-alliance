<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Actions;

use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionBatchState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionCandidateState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionSubscriptionState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionTargetKind;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionBatch;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionCandidate;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionSubscription;
use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionAdapterRegistry;
use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionMutationState;
use App\Contexts\Intelligence\Roster\Actions\RecordPlayerSnapshot;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PromoteKingdomIngestionPlayerSnapshot
{
    public function __construct(
        private KingdomIngestionMutationState $mutations,
        private KingdomIngestionAdapterRegistry $adapters,
        private RecordPlayerSnapshot $snapshots,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $subscriptionId, string $candidateId): ?PlayerSnapshot
    {
        return DB::transaction(function () use ($subscriptionId, $candidateId): ?PlayerSnapshot {
            $context = $this->mutations->lockSubscription($subscriptionId);
            $subscription = $context->subscription;

            // Route only, then acquire the domain order subscription -> batch -> candidate.
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
                ->where('subscription_id', $subscription->id)
                ->where('batch_id', $batch->id)
                ->whereKey($route->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($candidate->state === KingdomIngestionCandidateState::Promoted) {
                return $this->existingPromotion($candidate);
            }
            if (in_array($candidate->state, [KingdomIngestionCandidateState::Quarantined, KingdomIngestionCandidateState::Rejected], true)) {
                return null;
            }
            if ($candidate->target_kind !== KingdomIngestionTargetKind::PlayerSnapshot) {
                throw ValidationException::withMessages([
                    'candidate' => 'This promotion path accepts only player snapshot candidates.',
                ]);
            }
            if ($subscription->state !== KingdomIngestionSubscriptionState::Active
                || $batch->state !== KingdomIngestionBatchState::Pending) {
                throw ValidationException::withMessages([
                    'subscription' => 'The ingestion subscription and batch must still be active before a candidate can be promoted.',
                ]);
            }

            if ((string) $candidate->alliance_id !== (string) $subscription->alliance_id
                || (string) $candidate->kingdom_id !== (string) $subscription->kingdom_id
                || (string) $batch->alliance_id !== (string) $subscription->alliance_id
                || (string) $batch->kingdom_id !== (string) $subscription->kingdom_id
                || $batch->adapter_key !== $subscription->adapter_key
                || $batch->adapter_version !== $subscription->adapter_version) {
                $this->quarantine($candidate, $batch, 'candidate_context_mismatch');

                return null;
            }

            if ($context->alliance->kingdom_id === null
                || (string) $context->alliance->kingdom_id !== (string) $subscription->kingdom_id) {
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

            $playerIds = Player::query()
                ->where('current_kingdom_id', $subscription->kingdom_id)
                ->where('game_player_id', $candidate->stable_game_id)
                ->limit(2)
                ->pluck('id');
            if ($playerIds->isEmpty()) {
                $this->quarantine($candidate, $batch, 'unknown_player');

                return null;
            }
            if ($playerIds->count() !== 1) {
                $this->quarantine($candidate, $batch, 'ambiguous_player_identity');

                return null;
            }

            // Player -> roster is the identity/Kingdom movement order used elsewhere.
            $player = Player::query()
                ->whereKey($playerIds->first())
                ->lockForUpdate()
                ->firstOrFail();
            if ((string) $player->current_kingdom_id !== (string) $subscription->kingdom_id
                || $player->game_player_id !== $candidate->stable_game_id) {
                $this->quarantine($candidate, $batch, 'player_identity_changed');

                return null;
            }

            $entries = AllianceRosterEntry::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('player_id', $player->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->limit(2)
                ->get();
            if ($entries->isEmpty()) {
                $this->quarantine($candidate, $batch, 'roster_target_missing');

                return null;
            }
            if ($entries->count() !== 1) {
                $this->quarantine($candidate, $batch, 'ambiguous_roster_target');

                return null;
            }
            $entry = $entries->first();

            try {
                $snapshot = $this->snapshots->handle(
                    $context->alliance,
                    null,
                    (string) $entry->id,
                    $this->snapshotAttributes($candidate),
                    'ingestion',
                    null,
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
                $this->quarantine($candidate, $batch, 'snapshot_validation_failed');

                return null;
            }

            $candidate->forceFill([
                'state' => KingdomIngestionCandidateState::Promoted,
                'quarantine_code' => null,
                'rejection_code' => null,
                'promoted_record_type' => KingdomIngestionTargetKind::PlayerSnapshot->value,
                'promoted_record_id' => (string) $snapshot->id,
                'promoted_at' => now(),
            ])->save();

            $metadata = [
                'subscription_id' => (string) $subscription->id,
                'batch_id' => (string) $batch->id,
                'candidate_id' => (string) $candidate->id,
                'snapshot_id' => (string) $snapshot->id,
                'roster_entry_id' => (string) $entry->id,
                'player_id' => (string) $player->id,
                'stable_game_id' => $candidate->stable_game_id,
                'adapter_key' => $subscription->adapter_key,
                'adapter_version' => $subscription->adapter_version,
                'source_record_id' => $candidate->source_record_id,
                'identity_hash' => $candidate->identity_hash,
                'payload_hash' => $candidate->payload_hash,
                'origin' => 'system',
            ];
            $event = 'intelligence.ingestion_candidate_promoted';
            $this->audit->record($event, null, $candidate, $context->alliance, $metadata);
            $this->outbox->record(
                $event,
                (string) $context->alliance->id,
                $candidate,
                $metadata,
                $event.':'.$candidate->id,
            );

            return $snapshot;
        });
    }

    private function existingPromotion(KingdomIngestionCandidate $candidate): PlayerSnapshot
    {
        if ($candidate->promoted_record_type !== KingdomIngestionTargetKind::PlayerSnapshot->value
            || $candidate->promoted_record_id === null) {
            throw ValidationException::withMessages([
                'candidate' => 'The promoted candidate does not contain a valid player snapshot reference.',
            ]);
        }

        return PlayerSnapshot::query()
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
            && in_array(KingdomIngestionTargetKind::PlayerSnapshot, $adapter->supportedTargetKinds(), true);
    }

    /** @return array{observed_name:string,power:string,progression_level:string|null,observed_alliance_tag:string|null,captured_at:string} */
    private function snapshotAttributes(KingdomIngestionCandidate $candidate): array
    {
        $payload = $candidate->normalized_payload;
        $observedName = $payload['observed_name'] ?? null;
        $power = $payload['power'] ?? null;
        $progressionLevel = $payload['progression_level'] ?? null;
        $observedAllianceTag = $payload['observed_alliance_tag'] ?? null;

        if (! is_string($observedName)
            || ! is_string($power)
            || ($progressionLevel !== null && ! is_string($progressionLevel))
            || ($observedAllianceTag !== null && ! is_string($observedAllianceTag))) {
            throw ValidationException::withMessages([
                'candidate' => 'The normalized player snapshot payload is invalid.',
            ]);
        }

        return [
            'observed_name' => $observedName,
            'power' => $power,
            'progression_level' => $progressionLevel,
            'observed_alliance_tag' => $observedAllianceTag,
            'captured_at' => $candidate->captured_at->toIso8601String(),
        ];
    }

    private function quarantine(KingdomIngestionCandidate $candidate, KingdomIngestionBatch $batch, string $reasonCode): void
    {
        $candidate->forceFill([
            'state' => KingdomIngestionCandidateState::Quarantined,
            'quarantine_code' => $reasonCode,
        ])->save();
        $batch->increment('records_quarantined');

        $event = 'intelligence.ingestion_candidate_quarantined';
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
                'origin' => 'system',
            ],
            $event.':'.$candidate->id.':'.$reasonCode,
        );
    }
}
