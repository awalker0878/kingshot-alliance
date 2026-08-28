<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\SpatialEvidenceReview;
use App\Contexts\Intelligence\Evidence\Services\TerritoryEvidenceSchemaRegistry;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCompleteness;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCoverageKind;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedIdentityState;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedObjectType;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

final readonly class SaveSpatialEvidenceReview
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private KingdomMapDatasetQuery $datasets,
        private TerritoryEvidenceSchemaRegistry $schemas,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  array{x:int,y:int,width:int,height:int}|null  $coverageBounds
     * @param  list<array<string,mixed>>  $objects
     */
    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $kingdomId,
        string $evidenceId,
        string $capturedAt,
        SpatialObservationCoverageKind $coverageKind,
        SpatialObservationCompleteness $completeness,
        ?array $coverageBounds,
        array $objects,
    ): string {
        try {
            $captured = CarbonImmutable::parse($capturedAt)->utc();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'captured_at' => 'The screenshot capture time is invalid.',
            ]);
        }
        if ($captured->gt(CarbonImmutable::now('UTC')->addMinutes(5))) {
            throw ValidationException::withMessages([
                'captured_at' => 'The screenshot capture time cannot be more than five minutes in the future.',
            ]);
        }

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $kingdomId, $evidenceId, $captured, $coverageKind, $completeness, $coverageBounds, $objects): string {
            [$scope, $actor] = $this->writeState->authorize(
                $actorPlayerId,
                $allianceId,
                IntelligencePermission::KingdomManage,
            );
            if ($scope->kingdomId !== $kingdomId) {
                throw ValidationException::withMessages([
                    'kingdom_id' => 'The reviewed Territory screenshot belongs to historical Kingdom context.',
                ]);
            }

            $evidence = GameEvidence::query()
                ->whereKey($evidenceId)
                ->where('alliance_id', $allianceId)
                ->where('kingdom_id', $kingdomId)
                ->whereNull('occurrence_id')
                ->whereNull('roster_entry_id')
                ->whereNull('transfer_plan_id')
                ->whereNull('transfer_participant_id')
                ->lockForUpdate()
                ->firstOrFail();
            if ($evidence->kind !== EvidenceKind::TerritoryMapObservation
                || $evidence->expected_kind !== EvidenceKind::TerritoryMapObservation) {
                throw ValidationException::withMessages([
                    'evidence' => 'The screenshot class has not been safely verified as a Territory map observation.',
                ]);
            }

            $schema = $this->schemas->require(EvidenceKind::TerritoryMapObservation);
            $extraction = EvidenceExtractionAttempt::query()
                ->where('evidence_id', $evidenceId)
                ->where('status', EvidenceAttemptStatus::Completed->value)
                ->where('schema_version', $schema->version)
                ->orderByDesc('completed_at')
                ->orderByDesc('id')
                ->sharedLock()
                ->firstOrFail();
            $classification = EvidenceClassificationAttempt::query()
                ->whereKey((string) $extraction->classification_attempt_id)
                ->where('evidence_id', $evidenceId)
                ->where('status', EvidenceAttemptStatus::Completed->value)
                ->sharedLock()
                ->firstOrFail();
            if ((float) $classification->confidence < $schema->minimumClassificationConfidence) {
                throw ValidationException::withMessages([
                    'evidence' => 'Classification confidence is below the supported Territory schema threshold.',
                ]);
            }

            $datasetId = (string) $evidence->map_dataset_id;
            $datasetChecksum = (string) $evidence->map_dataset_checksum;
            $this->datasets->require($datasetId, $datasetChecksum);
            $payload = ['objects' => $this->reviewedObjects($objects)];
            $this->validateCoverage(
                $coverageKind,
                $completeness,
                $coverageBounds,
                count($payload['objects']),
            );
            $fingerprint = $this->fingerprint(
                $allianceId,
                $kingdomId,
                $schema->version,
                $datasetId,
                $datasetChecksum,
                $captured,
                $coverageKind,
                $completeness,
                $coverageBounds,
                $payload,
            );
            $duplicate = SpatialEvidenceReview::query()
                ->where('alliance_id', $allianceId)
                ->where('kingdom_id', $kingdomId)
                ->where('semantic_fingerprint', $fingerprint)
                ->where('evidence_id', '!=', $evidenceId)
                ->whereIn('status', [
                    EvidenceReviewStatus::Approved->value,
                    EvidenceReviewStatus::DuplicateBlocked->value,
                ])
                ->orderBy('reviewed_at')
                ->first();
            $revision = ((int) SpatialEvidenceReview::query()
                ->where('evidence_id', $evidenceId)
                ->max('revision_number')) + 1;
            $status = $duplicate instanceof SpatialEvidenceReview
                ? EvidenceReviewStatus::DuplicateBlocked
                : EvidenceReviewStatus::Approved;
            $review = SpatialEvidenceReview::query()->create([
                'evidence_id' => $evidenceId,
                'alliance_id' => $allianceId,
                'kingdom_id' => $kingdomId,
                'schema_version' => $schema->version,
                'revision_number' => $revision,
                'status' => $status,
                'captured_at' => $captured,
                'coverage_kind' => $coverageKind,
                'completeness' => $completeness,
                'coverage_bounds' => $coverageBounds,
                'map_dataset_id' => $datasetId,
                'map_dataset_checksum' => $datasetChecksum,
                'payload' => $payload,
                'semantic_fingerprint' => $fingerprint,
                'semantic_duplicate_review_id' => $duplicate?->id,
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
                'kingdom_id' => $kingdomId,
                'coverage_kind' => $coverageKind->value,
                'completeness' => $completeness->value,
                'object_count' => count($payload['objects']),
                'semantic_duplicate' => $duplicate instanceof SpatialEvidenceReview,
            ];
            $event = $duplicate instanceof SpatialEvidenceReview
                ? 'evidence.semantic_duplicate_detected'
                : 'evidence.territory_spatial_review_approved';
            $this->audit->record($event, $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record($event, $allianceId, $evidence, $metadata);

            return (string) $review->id;
        });
    }

    /**
     * @param  list<array<string,mixed>>  $objects
     * @return list<array<string,mixed>>
     */
    private function reviewedObjects(array $objects): array
    {
        if (count($objects) > 5000) {
            throw ValidationException::withMessages([
                'objects' => 'A review may contain at most 5,000 supported spatial objects.',
            ]);
        }

        $result = [];
        $keys = [];
        foreach ($objects as $index => $object) {
            if (! is_array($object)) {
                throw ValidationException::withMessages([
                    "objects.{$index}" => 'Each reviewed object must be structured data.',
                ]);
            }

            $key = trim((string) ($object['key'] ?? ''));
            $type = SpatialObservedObjectType::tryFrom((string) ($object['type'] ?? ''));
            $identity = SpatialObservedIdentityState::tryFrom(
                (string) ($object['identity_state'] ?? SpatialObservedIdentityState::Unresolved->value),
            );
            if ($key === ''
                || mb_strlen($key) > 120
                || isset($keys[$key])
                || ! $type instanceof SpatialObservedObjectType
                || ! $identity instanceof SpatialObservedIdentityState
                || ! is_int($object['x'] ?? null)
                || ! is_int($object['y'] ?? null)) {
                throw ValidationException::withMessages([
                    "objects.{$index}" => 'The reviewed spatial object is invalid or unsupported.',
                ]);
            }

            $keys[$key] = true;
            $result[] = [
                'key' => $key,
                'type' => $type->value,
                'x' => (int) $object['x'],
                'y' => (int) $object['y'],
                'player_id' => is_string($object['player_id'] ?? null)
                    && trim($object['player_id']) !== ''
                        ? trim($object['player_id'])
                        : null,
                'plan_local_identity' => is_string($object['plan_local_identity'] ?? null)
                    && trim($object['plan_local_identity']) !== ''
                        ? trim($object['plan_local_identity'])
                        : null,
                'observed_label' => is_string($object['observed_label'] ?? null)
                    && trim($object['observed_label']) !== ''
                        ? mb_substr(trim($object['observed_label']), 0, 191)
                        : null,
                'identity_state' => $identity->value,
                'confidence' => isset($object['confidence'])
                    ? max(0.0, min(1.0, (float) $object['confidence']))
                    : null,
                'source_metadata' => is_array($object['source_metadata'] ?? null)
                    ? $object['source_metadata']
                    : null,
            ];
        }

        return $result;
    }

    /** @param  array{x:int,y:int,width:int,height:int}|null  $bounds */
    private function validateCoverage(
        SpatialObservationCoverageKind $kind,
        SpatialObservationCompleteness $completeness,
        ?array $bounds,
        int $objectCount,
    ): void {
        if (in_array($kind, [
            SpatialObservationCoverageKind::CompleteHive,
            SpatialObservationCoverageKind::CompleteVisibleRegion,
        ], true) && $completeness !== SpatialObservationCompleteness::Complete) {
            throw ValidationException::withMessages([
                'completeness' => 'A complete-hive or complete-visible-region observation must be explicitly complete.',
            ]);
        }
        if ($kind === SpatialObservationCoverageKind::CompleteVisibleRegion && $bounds === null) {
            throw ValidationException::withMessages([
                'coverage_bounds' => 'Complete visible-region observations require explicit bounds.',
            ]);
        }
        if ($kind === SpatialObservationCoverageKind::SingleObject && $objectCount !== 1) {
            throw ValidationException::withMessages([
                'objects' => 'Single-object coverage requires exactly one reviewed object.',
            ]);
        }
    }

    /**
     * @param  array{x:int,y:int,width:int,height:int}|null  $bounds
     * @param  array<string,mixed>  $payload
     */
    private function fingerprint(
        string $allianceId,
        string $kingdomId,
        string $schema,
        string $datasetId,
        string $checksum,
        CarbonImmutable $captured,
        SpatialObservationCoverageKind $coverage,
        SpatialObservationCompleteness $completeness,
        ?array $bounds,
        array $payload,
    ): string {
        try {
            return hash('sha256', json_encode([
                'alliance_id' => $allianceId,
                'kingdom_id' => $kingdomId,
                'schema' => $schema,
                'dataset_id' => $datasetId,
                'dataset_checksum' => $checksum,
                'captured_at' => $captured->format('Y-m-d\TH:i:s.u\Z'),
                'coverage_kind' => $coverage->value,
                'completeness' => $completeness->value,
                'coverage_bounds' => $bounds,
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'payload' => 'The reviewed Territory observation could not be fingerprinted.',
            ]);
        }
    }
}
