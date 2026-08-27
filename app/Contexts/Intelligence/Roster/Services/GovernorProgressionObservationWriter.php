<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Services;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Contracts\GovernorProgressionEvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Roster\Models\GovernorProgressionEvidenceReceipt;
use App\Contexts\Intelligence\Roster\Models\GovernorProgressionObservation;
use App\Contexts\Intelligence\Roster\ValueObjects\GovernorProgressionEvidenceRecordResult;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class GovernorProgressionObservationWriter
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private RosterEntryQuery $roster,
        private GovernorProgressionEvidenceReferenceLookup $evidence,
        private GovernorProgressionObservationValidator $validator,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string,mixed> $payload */
    public function record(
        string $actorPlayerId,
        string $allianceId,
        string $rosterEntryId,
        string $evidenceId,
        string $reviewId,
        EvidenceKind $kind,
        string $schemaVersion,
        string $progressionDatasetId,
        string $progressionDatasetChecksum,
        string $capturedAt,
        array $payload,
        string $idempotencyKey,
    ): GovernorProgressionEvidenceRecordResult {
        if (! $kind->isGovernorProgression()) {
            throw ValidationException::withMessages(['kind' => 'Roster accepts only explicit Governor Progression evidence kinds through this writer.']);
        }
        if (preg_match('/^[a-f0-9]{64}$/', $idempotencyKey) !== 1) {
            throw ValidationException::withMessages(['idempotency_key' => 'The destination idempotency key is invalid.']);
        }
        try {
            $captured = CarbonImmutable::parse($capturedAt)->utc();
        } catch (Throwable) {
            throw ValidationException::withMessages(['captured_at' => 'The Governor progression observation time is invalid.']);
        }

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $rosterEntryId, $evidenceId, $reviewId, $kind, $schemaVersion, $progressionDatasetId, $progressionDatasetChecksum, $captured, $payload, $idempotencyKey): GovernorProgressionEvidenceRecordResult {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $entry = $this->roster->requireActiveOrTracked($allianceId, $rosterEntryId);

            $existing = GovernorProgressionEvidenceReceipt::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof GovernorProgressionEvidenceReceipt) {
                if ((string) $existing->alliance_id !== $allianceId
                    || (string) $existing->roster_entry_id !== $rosterEntryId
                    || (string) $existing->player_id !== $entry->playerId
                    || (string) $existing->evidence_id !== $evidenceId
                    || (string) $existing->review_id !== $reviewId
                    || $existing->evidence_kind !== $kind) {
                    throw ValidationException::withMessages(['idempotency_key' => 'The destination idempotency key is already bound to different reviewed meaning.']);
                }

                return new GovernorProgressionEvidenceRecordResult(
                    receiptId: (string) $existing->id,
                    observationId: (string) $existing->observation_id,
                    idempotentReplay: true,
                );
            }

            if (! $this->evidence->isApprovedGovernorProgressionReview(
                evidenceId: $evidenceId,
                reviewId: $reviewId,
                allianceId: $allianceId,
                rosterEntryId: $rosterEntryId,
                playerId: $entry->playerId,
                kind: $kind,
                schemaVersion: $schemaVersion,
                progressionDatasetId: $progressionDatasetId,
                progressionDatasetChecksum: $progressionDatasetChecksum,
            )) {
                throw ValidationException::withMessages(['evidence' => 'The exact approved Governor Progression review provenance is no longer valid for this destination scope.']);
            }

            $validatedPayload = $this->validator->validate(
                $kind,
                $payload,
                $progressionDatasetId,
                $progressionDatasetChecksum,
            );
            $observation = GovernorProgressionObservation::query()->create([
                'alliance_id' => $allianceId,
                'roster_entry_id' => $entry->rosterEntryId,
                'player_id' => $entry->playerId,
                'kind' => $kind,
                'payload' => $validatedPayload,
                'captured_at' => $captured,
                'progression_dataset_id' => $progressionDatasetId,
                'progression_dataset_checksum' => $progressionDatasetChecksum,
                'source' => 'screenshot_evidence',
                'evidence_id' => $evidenceId,
                'evidence_review_id' => $reviewId,
                'destination_idempotency_key' => $idempotencyKey,
                'accepted_by_player_id' => $actorPlayerId,
                'accepted_at' => now(),
            ]);
            $receipt = GovernorProgressionEvidenceReceipt::query()->create([
                'alliance_id' => $allianceId,
                'roster_entry_id' => $entry->rosterEntryId,
                'player_id' => $entry->playerId,
                'observation_id' => $observation->id,
                'evidence_id' => $evidenceId,
                'review_id' => $reviewId,
                'evidence_kind' => $kind,
                'schema_version' => $schemaVersion,
                'progression_dataset_id' => $progressionDatasetId,
                'progression_dataset_checksum' => $progressionDatasetChecksum,
                'idempotency_key' => $idempotencyKey,
                'accepted_by_player_id' => $actorPlayerId,
                'accepted_at' => now(),
            ]);
            $metadata = [
                'observation_id' => (string) $observation->id,
                'receipt_id' => (string) $receipt->id,
                'roster_entry_id' => $entry->rosterEntryId,
                'player_id' => $entry->playerId,
                'evidence_id' => $evidenceId,
                'review_id' => $reviewId,
                'kind' => $kind->value,
                'schema_version' => $schemaVersion,
                'captured_at' => $captured->toIso8601String(),
                'progression_dataset_id' => $progressionDatasetId,
                'progression_dataset_checksum' => $progressionDatasetChecksum,
                'source' => 'screenshot_evidence',
            ];
            $event = 'intelligence.governor_progression_observed';
            $this->audit->record($event, $actor, $observation, $allianceId, $metadata);
            $this->outbox->record($event, $allianceId, $observation, $metadata, $event.':'.$observation->id);

            return new GovernorProgressionEvidenceRecordResult(
                receiptId: (string) $receipt->id,
                observationId: (string) $observation->id,
                idempotentReplay: false,
            );
        });
    }
}
