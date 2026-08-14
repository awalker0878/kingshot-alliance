<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Enums\KingdomIngestionTargetKind;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\KingdomIngestionBatch;
use App\Domain\Kingdoms\Models\KingdomIngestionCandidate;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Models\PlayerSnapshot;
use App\Domain\Kingdoms\Services\KingdomIngestionAdapterRegistry;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PromoteKingdomIngestionPlayerSnapshot
{
    public function __construct(
        private KingdomIngestionAdapterRegistry $adapters,
        private RecordPlayerSnapshot $snapshots,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $subscriptionId, string $candidateId): ?PlayerSnapshot
    {
        return DB::transaction(function () use ($subscriptionId, $candidateId): ?PlayerSnapshot {
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

            if ($candidate->target_kind !== KingdomIngestionTargetKind::PlayerSnapshot) {
                throw ValidationException::withMessages([
                    'candidate' => 'This promotion path accepts only player snapshot candidates.',
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

            $players = Player::query()
                ->where('current_kingdom_id', $subscription->kingdom_id)
                ->where('game_player_id', $candidate->stable_game_id)
                ->limit(2)
                ->get();

            if ($players->isEmpty()) {
                $this->quarantine($candidate, $batch, 'unknown_player');

                return null;
            }

            if ($players->count() !== 1) {
                $this->quarantine($candidate, $batch, 'ambiguous_player_identity');

                return null;
            }

            $player = $players->first();

            $entries = AllianceRosterEntry::query()
                ->where('alliance_id', $subscription->alliance_id)
                ->where('player_id', $player->id)
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
                $attributes = $this->snapshotAttributes($candidate);
                $snapshot = $this->snapshots->handle(
                    $alliance,
                    null,
                    (string) $entry->id,
                    $attributes,
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

            return $snapshot;
        });
    }

    private function existingPromotion(KingdomIngestionCandidate $candidate): PlayerSnapshot
    {
        if (
            $candidate->promoted_record_type !== KingdomIngestionTargetKind::PlayerSnapshot->value
            || $candidate->promoted_record_id === null
        ) {
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

    /**
     * @return array{
     *   observed_name: string,
     *   power: string,
     *   progression_level: string|null,
     *   observed_alliance_tag: string|null,
     *   captured_at: string
     * }
     */
    private function snapshotAttributes(KingdomIngestionCandidate $candidate): array
    {
        $payload = $candidate->normalized_payload;
        $observedName = $payload['observed_name'] ?? null;
        $power = $payload['power'] ?? null;
        $progressionLevel = $payload['progression_level'] ?? null;
        $observedAllianceTag = $payload['observed_alliance_tag'] ?? null;

        if (
            ! is_string($observedName)
            || ! is_string($power)
            || ($progressionLevel !== null && ! is_string($progressionLevel))
            || ($observedAllianceTag !== null && ! is_string($observedAllianceTag))
        ) {
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
