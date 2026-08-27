<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\GovernorProgressionEvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\ProgressionNormalizationAttempt;
use App\Contexts\Intelligence\Evidence\Services\GovernorProgressionEvidenceSchemaRegistry;
use App\Contexts\Intelligence\Roster\Services\GovernorProgressionObservationValidator;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

final readonly class SaveGovernorProgressionEvidenceReview
{
    public function __construct(
        private AllianceIntelligenceAuthorization $authorization,
        private AllianceIntelligenceWriteState $writeState,
        private RosterEntryQuery $roster,
        private GovernorProgressionEvidenceSchemaRegistry $schemas,
        private GovernorProgressionObservationValidator $validator,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string,mixed> $payload */
    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $rosterEntryId,
        string $evidenceId,
        string $normalizationAttemptId,
        string $capturedAt,
        array $payload,
    ): string {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }
        $entryBefore = $this->roster->requireActiveOrTracked($allianceId, $rosterEntryId);
        try {
            $captured = CarbonImmutable::parse($capturedAt)->utc();
        } catch (Throwable) {
            throw ValidationException::withMessages(['captured_at' => 'The screenshot capture time is invalid.']);
        }
        if ($captured->gt(CarbonImmutable::now('UTC')->addMinutes(5))) {
            throw ValidationException::withMessages(['captured_at' => 'The screenshot capture time cannot be more than five minutes in the future.']);
        }

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $rosterEntryId, $evidenceId, $normalizationAttemptId, $captured, $payload, $entryBefore): string {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $entry = $this->roster->requireActiveOrTracked($allianceId, $rosterEntryId);
            if ($entry->playerId !== $entryBefore->playerId) {
                throw ValidationException::withMessages(['evidence' => 'The target roster entry changed while the review was being saved. Review the current Governor scope again.']);
            }

            $evidence = GameEvidence::query()
                ->whereKey($evidenceId)
                ->where('alliance_id', $allianceId)
                ->where('roster_entry_id', $rosterEntryId)
                ->whereNull('occurrence_id')
                ->whereNull('transfer_plan_id')
                ->whereNull('transfer_participant_id')
                ->lockForUpdate()
                ->firstOrFail();
            $kind = EvidenceKind::from((string) $evidence->getRawOriginal('kind'));
            $expected = EvidenceKind::from((string) $evidence->getRawOriginal('expected_kind'));
            if (! $kind->isGovernorProgression() || $kind !== $expected) {
                throw ValidationException::withMessages(['evidence' => 'The screenshot class has not been safely verified.']);
            }
            $schema = $this->schemas->require($kind);
            $normalization = ProgressionNormalizationAttempt::query()
                ->whereKey($normalizationAttemptId)
                ->where('evidence_id', $evidenceId)
                ->where('status', EvidenceAttemptStatus::Completed->value)
                ->sharedLock()
                ->firstOrFail();
            $extraction = EvidenceExtractionAttempt::query()
                ->whereKey((string) $normalization->extraction_attempt_id)
                ->where('evidence_id', $evidenceId)
                ->where('status', EvidenceAttemptStatus::Completed->value)
                ->where('schema_version', $schema->version)
                ->sharedLock()
                ->firstOrFail();
            $classification = EvidenceClassificationAttempt::query()
                ->whereKey((string) $extraction->classification_attempt_id)
                ->where('evidence_id', $evidenceId)
                ->where('status', EvidenceAttemptStatus::Completed->value)
                ->sharedLock()
                ->firstOrFail();
            if ((float) $classification->confidence < $schema->minimumClassificationConfidence) {
                throw ValidationException::withMessages(['evidence' => 'Classification confidence is below the supported schema threshold.']);
            }

            $datasetId = (string) $normalization->progression_dataset_id;
            $datasetChecksum = (string) $normalization->progression_dataset_checksum;
            $reviewedPayload = $this->validator->validate($kind, $payload, $datasetId, $datasetChecksum);
            $fingerprint = $this->fingerprint(
                $allianceId,
                $rosterEntryId,
                $entry->playerId,
                $kind,
                $schema->version,
                $datasetId,
                $datasetChecksum,
                $captured,
                $reviewedPayload,
            );
            $duplicate = GovernorProgressionEvidenceReview::query()
                ->where('alliance_id', $allianceId)
                ->where('roster_entry_id', $rosterEntryId)
                ->where('evidence_kind', $kind->value)
                ->where('semantic_fingerprint', $fingerprint)
                ->where('evidence_id', '!=', $evidenceId)
                ->whereIn('status', [EvidenceReviewStatus::Approved->value, EvidenceReviewStatus::DuplicateBlocked->value])
                ->orderBy('reviewed_at')
                ->first();
            $revision = ((int) GovernorProgressionEvidenceReview::query()->where('evidence_id', $evidenceId)->max('revision_number')) + 1;
            $status = $duplicate instanceof GovernorProgressionEvidenceReview
                ? EvidenceReviewStatus::DuplicateBlocked
                : EvidenceReviewStatus::Approved;
            $review = GovernorProgressionEvidenceReview::query()->create([
                'evidence_id' => $evidenceId,
                'normalization_attempt_id' => $normalization->id,
                'alliance_id' => $allianceId,
                'roster_entry_id' => $rosterEntryId,
                'player_id' => $entry->playerId,
                'evidence_kind' => $kind,
                'schema_version' => $schema->version,
                'progression_dataset_id' => $datasetId,
                'progression_dataset_checksum' => $datasetChecksum,
                'revision_number' => $revision,
                'status' => $status,
                'captured_at' => $captured,
                'payload' => $reviewedPayload,
                'semantic_fingerprint' => $fingerprint,
                'semantic_duplicate_review_id' => $duplicate instanceof GovernorProgressionEvidenceReview ? (string) $duplicate->id : null,
                'reviewed_by_player_id' => $actorPlayerId,
                'reviewed_at' => now(),
            ]);
            $evidence->forceFill([
                'lifecycle_status' => $status === EvidenceReviewStatus::Approved
                    ? EvidenceLifecycleStatus::Approved
                    : EvidenceLifecycleStatus::NeedsReview,
            ])->save();

            $metadata = [
                'evidence_id' => $evidenceId,
                'review_id' => (string) $review->id,
                'roster_entry_id' => $rosterEntryId,
                'target_player_id' => $entry->playerId,
                'evidence_kind' => $kind->value,
                'schema_version' => $schema->version,
                'revision_number' => $revision,
                'progression_dataset_id' => $datasetId,
                'progression_dataset_checksum' => $datasetChecksum,
                'semantic_duplicate' => $duplicate instanceof GovernorProgressionEvidenceReview,
            ];
            $event = $duplicate instanceof GovernorProgressionEvidenceReview
                ? 'evidence.semantic_duplicate_detected'
                : 'evidence.governor_progression_review_approved';
            $this->audit->record($event, $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record($event, $allianceId, $evidence, $metadata);

            return (string) $review->id;
        });
    }

    /** @param array<string,mixed> $payload */
    private function fingerprint(
        string $allianceId,
        string $rosterEntryId,
        string $playerId,
        EvidenceKind $kind,
        string $schemaVersion,
        string $datasetId,
        string $datasetChecksum,
        CarbonImmutable $captured,
        array $payload,
    ): string {
        try {
            return hash('sha256', json_encode([
                'alliance_id' => $allianceId,
                'roster_entry_id' => $rosterEntryId,
                'player_id' => $playerId,
                'kind' => $kind->value,
                'schema_version' => $schemaVersion,
                'progression_dataset_id' => $datasetId,
                'progression_dataset_checksum' => $datasetChecksum,
                'captured_at' => $captured->format('Y-m-d\TH:i:s.u\Z'),
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw ValidationException::withMessages(['payload' => 'The reviewed Governor Progression meaning could not be fingerprinted.']);
        }
    }
}
