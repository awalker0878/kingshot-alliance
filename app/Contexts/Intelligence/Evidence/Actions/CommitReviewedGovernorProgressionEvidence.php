<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\GovernorProgressionEvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\GovernorProgressionEvidenceReview;
use App\Contexts\Intelligence\Roster\Actions\RecordGovernorCharmsEvidence;
use App\Contexts\Intelligence\Roster\Actions\RecordGovernorGearEvidence;
use App\Contexts\Intelligence\Roster\Actions\RecordGovernorProfileEvidence;
use App\Contexts\Intelligence\Roster\Actions\RecordHeroDetailEvidence;
use App\Contexts\Intelligence\Roster\Actions\RecordHeroGearEvidence;
use App\Contexts\Intelligence\Roster\Actions\RecordHeroRosterEvidence;
use App\Contexts\Intelligence\Roster\ValueObjects\GovernorProgressionEvidenceRecordResult;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final readonly class CommitReviewedGovernorProgressionEvidence
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private RosterEntryQuery $roster,
        private RecordGovernorProfileEvidence $governorProfile,
        private RecordHeroRosterEvidence $heroRoster,
        private RecordHeroDetailEvidence $heroDetail,
        private RecordHeroGearEvidence $heroGear,
        private RecordGovernorGearEvidence $governorGear,
        private RecordGovernorCharmsEvidence $governorCharms,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $rosterEntryId,
        string $reviewId,
    ): GovernorProgressionEvidenceRecordResult {
        $prepared = DB::transaction(function () use ($actorPlayerId, $allianceId, $rosterEntryId, $reviewId): array {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $entry = $this->roster->requireActiveOrTracked($allianceId, $rosterEntryId);
            $review = GovernorProgressionEvidenceReview::query()
                ->whereKey($reviewId)
                ->where('alliance_id', $allianceId)
                ->where('roster_entry_id', $rosterEntryId)
                ->where('player_id', $entry->playerId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($review->status !== EvidenceReviewStatus::Approved) {
                throw ValidationException::withMessages(['review' => 'Only an approved Governor Progression review can be committed.']);
            }
            $latestId = GovernorProgressionEvidenceReview::query()
                ->where('evidence_id', $review->evidence_id)
                ->orderByDesc('revision_number')
                ->orderByDesc('id')
                ->value('id');
            if ((string) $latestId !== (string) $review->id) {
                throw ValidationException::withMessages(['review' => 'Commit the latest approved review revision instead.']);
            }
            $evidence = GameEvidence::query()
                ->whereKey($review->evidence_id)
                ->where('alliance_id', $allianceId)
                ->where('roster_entry_id', $rosterEntryId)
                ->lockForUpdate()
                ->firstOrFail();
            $kind = EvidenceKind::from((string) $review->getRawOriginal('evidence_kind'));
            if (! $kind->isGovernorProgression()
                || $evidence->kind !== $kind
                || $evidence->expected_kind !== $kind
                || $evidence->lifecycle_status === EvidenceLifecycleStatus::Deleted) {
                throw ValidationException::withMessages(['evidence' => 'The approved screenshot provenance is no longer valid for this target.']);
            }
            $idempotencyKey = hash('sha256', implode(':', [
                'governor-progression',
                (string) $review->id,
                (string) $review->semantic_fingerprint,
                (string) $review->progression_dataset_checksum,
            ]));
            $attempt = GovernorProgressionEvidenceCommitAttempt::query()
                ->where('governor_review_id', $review->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($attempt instanceof GovernorProgressionEvidenceCommitAttempt
                && $attempt->status === EvidenceCommitStatus::Succeeded
                && $attempt->destination_receipt_id !== null
                && is_array($attempt->destination_receipt)) {
                return ['completed' => new GovernorProgressionEvidenceRecordResult(
                    receiptId: (string) $attempt->destination_receipt_id,
                    observationId: (string) ($attempt->destination_receipt['observation_id'] ?? ''),
                    idempotentReplay: true,
                )];
            }
            if (! $attempt instanceof GovernorProgressionEvidenceCommitAttempt) {
                $attempt = GovernorProgressionEvidenceCommitAttempt::query()->create([
                    'evidence_id' => $evidence->id,
                    'governor_review_id' => $review->id,
                    'alliance_id' => $allianceId,
                    'status' => EvidenceCommitStatus::Pending,
                    'idempotency_key' => $idempotencyKey,
                    'destination_action' => $this->destinationAction($kind),
                    'started_by_player_id' => $actorPlayerId,
                    'started_at' => now(),
                ]);
            } else {
                $attempt->forceFill([
                    'status' => EvidenceCommitStatus::Pending,
                    'failure_code' => null,
                    'started_by_player_id' => $actorPlayerId,
                    'started_at' => now(),
                    'completed_at' => null,
                ])->save();
            }
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Committing])->save();
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'review_id' => (string) $review->id,
                'commit_attempt_id' => (string) $attempt->id,
                'roster_entry_id' => $rosterEntryId,
                'target_player_id' => $entry->playerId,
                'kind' => $kind->value,
                'destination_action' => $this->destinationAction($kind),
            ];
            $this->audit->record('evidence.governor_progression_commit_started', $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record('evidence.governor_progression_commit_started', $allianceId, $evidence, $metadata);

            return [
                'completed' => null,
                'attempt_id' => (string) $attempt->id,
                'evidence_id' => (string) $evidence->id,
                'review_id' => (string) $review->id,
                'kind' => $kind,
                'schema_version' => (string) $review->schema_version,
                'dataset_id' => (string) $review->progression_dataset_id,
                'dataset_checksum' => (string) $review->progression_dataset_checksum,
                'captured_at' => $review->captured_at->toIso8601String(),
                'payload' => is_array($review->payload) ? $review->payload : [],
                'idempotency_key' => $idempotencyKey,
            ];
        });
        if (($prepared['completed'] ?? null) instanceof GovernorProgressionEvidenceRecordResult) {
            return $prepared['completed'];
        }

        try {
            /** @var EvidenceKind $kind */
            $kind = $prepared['kind'];
            /** @var array<string,mixed> $payload */
            $payload = $prepared['payload'];
            $receipt = $this->commitToRoster(
                kind: $kind,
                actorPlayerId: $actorPlayerId,
                allianceId: $allianceId,
                rosterEntryId: $rosterEntryId,
                evidenceId: (string) $prepared['evidence_id'],
                reviewId: (string) $prepared['review_id'],
                schemaVersion: (string) $prepared['schema_version'],
                datasetId: (string) $prepared['dataset_id'],
                datasetChecksum: (string) $prepared['dataset_checksum'],
                capturedAt: (string) $prepared['captured_at'],
                payload: $payload,
                idempotencyKey: (string) $prepared['idempotency_key'],
            );

            DB::transaction(function () use ($actorPlayerId, $allianceId, $rosterEntryId, $prepared, $receipt): void {
                [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
                $this->roster->requireActiveOrTracked($allianceId, $rosterEntryId);
                $attempt = GovernorProgressionEvidenceCommitAttempt::query()->whereKey($prepared['attempt_id'])->lockForUpdate()->firstOrFail();
                $evidence = GameEvidence::query()->whereKey($prepared['evidence_id'])->lockForUpdate()->firstOrFail();
                $attempt->forceFill([
                    'status' => EvidenceCommitStatus::Succeeded,
                    'destination_receipt_id' => $receipt->receiptId,
                    'destination_receipt' => $receipt->toArray(),
                    'failure_code' => null,
                    'completed_at' => now(),
                ])->save();
                $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Committed])->save();
                $metadata = [
                    'evidence_id' => (string) $evidence->id,
                    'review_id' => (string) $prepared['review_id'],
                    'commit_attempt_id' => (string) $attempt->id,
                    'destination_receipt_id' => $receipt->receiptId,
                    'destination_observation_id' => $receipt->observationId,
                    'destination_replayed' => $receipt->idempotentReplay,
                    'recovered_acknowledgement' => $receipt->idempotentReplay,
                ];
                $event = $receipt->idempotentReplay
                    ? 'evidence.governor_progression_commit_recovered'
                    : 'evidence.governor_progression_committed';
                $this->audit->record($event, $actor, $evidence, $allianceId, $metadata);
                $this->outbox->record($event, $allianceId, $evidence, $metadata);
            });

            return $receipt;
        } catch (Throwable $exception) {
            $this->recordFailure(
                actorPlayerId: $actorPlayerId,
                allianceId: $allianceId,
                rosterEntryId: $rosterEntryId,
                attemptId: (string) $prepared['attempt_id'],
                evidenceId: (string) $prepared['evidence_id'],
                exception: $exception,
            );
            throw $exception;
        }
    }

    /** @param array<string,mixed> $payload */
    private function commitToRoster(
        EvidenceKind $kind,
        string $actorPlayerId,
        string $allianceId,
        string $rosterEntryId,
        string $evidenceId,
        string $reviewId,
        string $schemaVersion,
        string $datasetId,
        string $datasetChecksum,
        string $capturedAt,
        array $payload,
        string $idempotencyKey,
    ): GovernorProgressionEvidenceRecordResult {
        $arguments = [$actorPlayerId, $allianceId, $rosterEntryId, $evidenceId, $reviewId, $schemaVersion, $datasetId, $datasetChecksum, $capturedAt, $payload, $idempotencyKey];

        return match ($kind) {
            EvidenceKind::GovernorProfile => $this->governorProfile->handle(...$arguments),
            EvidenceKind::GovernorHeroRoster => $this->heroRoster->handle(...$arguments),
            EvidenceKind::GovernorHeroDetail => $this->heroDetail->handle(...$arguments),
            EvidenceKind::GovernorHeroGear => $this->heroGear->handle(...$arguments),
            EvidenceKind::GovernorGear => $this->governorGear->handle(...$arguments),
            EvidenceKind::GovernorCharms => $this->governorCharms->handle(...$arguments),
            default => throw new LogicException('Unsupported Governor Progression Evidence destination schema.'),
        };
    }

    private function destinationAction(EvidenceKind $kind): string
    {
        return match ($kind) {
            EvidenceKind::GovernorProfile => 'RecordGovernorProfileEvidence',
            EvidenceKind::GovernorHeroRoster => 'RecordHeroRosterEvidence',
            EvidenceKind::GovernorHeroDetail => 'RecordHeroDetailEvidence',
            EvidenceKind::GovernorHeroGear => 'RecordHeroGearEvidence',
            EvidenceKind::GovernorGear => 'RecordGovernorGearEvidence',
            EvidenceKind::GovernorCharms => 'RecordGovernorCharmsEvidence',
            default => throw new LogicException('Unsupported Governor Progression Evidence destination schema.'),
        };
    }

    private function recordFailure(
        string $actorPlayerId,
        string $allianceId,
        string $rosterEntryId,
        string $attemptId,
        string $evidenceId,
        Throwable $exception,
    ): void {
        try {
            DB::transaction(function () use ($actorPlayerId, $allianceId, $rosterEntryId, $attemptId, $evidenceId, $exception): void {
                [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
                $this->roster->requireActiveOrTracked($allianceId, $rosterEntryId);
                $attempt = GovernorProgressionEvidenceCommitAttempt::query()->whereKey($attemptId)->lockForUpdate()->first();
                $evidence = GameEvidence::query()->whereKey($evidenceId)->lockForUpdate()->first();
                $failureCode = substr(hash('sha256', $exception::class.':'.$exception->getMessage()), 0, 24);
                if ($attempt instanceof GovernorProgressionEvidenceCommitAttempt && $attempt->status !== EvidenceCommitStatus::Succeeded) {
                    $attempt->forceFill([
                        'status' => EvidenceCommitStatus::Failed,
                        'failure_code' => $failureCode,
                        'completed_at' => now(),
                    ])->save();
                }
                if ($evidence instanceof GameEvidence && $evidence->lifecycle_status !== EvidenceLifecycleStatus::Committed) {
                    $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Approved])->save();
                    $metadata = [
                        'evidence_id' => $evidenceId,
                        'commit_attempt_id' => $attemptId,
                        'failure_code' => $failureCode,
                    ];
                    $this->audit->record('evidence.governor_progression_commit_failed', $actor, $evidence, $allianceId, $metadata);
                    $this->outbox->record('evidence.governor_progression_commit_failed', $allianceId, $evidence, $metadata);
                }
            });
        } catch (Throwable) {
            // Preserve the original destination/acknowledgement failure. A normal retry uses the stable key.
        }
    }
}
