<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Actions;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Contracts\SpatialEvidenceReferenceLookup;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCompleteness;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCoverageKind;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedIdentityState;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedObjectType;
use App\Contexts\Intelligence\Observations\Models\SpatialObservation;
use App\Contexts\Intelligence\Observations\Models\SpatialObservationEvidenceReceipt;
use App\Contexts\Intelligence\Observations\Models\SpatialObservedObject;
use App\Contexts\Intelligence\Observations\ValueObjects\SpatialObservationRecordResult;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class RecordSpatialObservationEvidence
{
    public const SCHEMA_VERSION = 'territory-map-observation/1';

    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private SpatialEvidenceReferenceLookup $evidence,
        private KingdomMapDatasetQuery $datasets,
        private PlayerReferenceQuery $players,
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
        string $reviewId,
        string $schemaVersion,
        string $mapDatasetId,
        string $mapDatasetChecksum,
        string $capturedAt,
        SpatialObservationCoverageKind $coverageKind,
        SpatialObservationCompleteness $completeness,
        ?array $coverageBounds,
        array $objects,
        string $idempotencyKey,
        ?string $correctsObservationId = null,
        ?string $correctionReason = null,
    ): SpatialObservationRecordResult {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw ValidationException::withMessages([
                'schema_version' => 'The reviewed Territory screenshot schema version is not supported.',
            ]);
        }
        if (preg_match('/^[a-f0-9]{64}$/', $idempotencyKey) !== 1) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The spatial-observation destination idempotency key is invalid.',
            ]);
        }

        try {
            $captured = CarbonImmutable::parse($capturedAt)->utc();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'captured_at' => 'The observation capture time is invalid.',
            ]);
        }
        if ($captured->isAfter(now()->addMinutes(5))) {
            throw ValidationException::withMessages([
                'captured_at' => 'The observation capture time cannot be more than five minutes in the future.',
            ]);
        }

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $kingdomId, $evidenceId, $reviewId, $schemaVersion, $mapDatasetId, $mapDatasetChecksum, $captured, $coverageKind, $completeness, $coverageBounds, $objects, $idempotencyKey, $correctsObservationId, $correctionReason): SpatialObservationRecordResult {
            [$scope, $actor] = $this->writeState->authorize(
                $actorPlayerId,
                $allianceId,
                IntelligencePermission::KingdomManage,
            );
            if ($scope->kingdomId !== $kingdomId) {
                throw ValidationException::withMessages([
                    'kingdom_id' => 'The observed Kingdom no longer matches the active Alliance scope.',
                ]);
            }

            $existing = SpatialObservationEvidenceReceipt::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof SpatialObservationEvidenceReceipt) {
                if ((string) $existing->alliance_id !== $allianceId
                    || (string) $existing->kingdom_id !== $kingdomId
                    || (string) $existing->evidence_id !== $evidenceId
                    || (string) $existing->review_id !== $reviewId
                    || (string) $existing->schema_version !== $schemaVersion
                    || (string) $existing->map_dataset_id !== $mapDatasetId
                    || (string) $existing->map_dataset_checksum !== $mapDatasetChecksum) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'The destination idempotency key is already bound to different spatial evidence.',
                    ]);
                }

                return new SpatialObservationRecordResult(
                    receiptId: (string) $existing->id,
                    observationId: (string) $existing->observation_id,
                    idempotentReplay: true,
                );
            }

            if (! $this->evidence->isApprovedSpatialReview(
                evidenceId: $evidenceId,
                reviewId: $reviewId,
                allianceId: $allianceId,
                kingdomId: $kingdomId,
                schemaVersion: $schemaVersion,
                mapDatasetId: $mapDatasetId,
                mapDatasetChecksum: $mapDatasetChecksum,
            )) {
                throw ValidationException::withMessages([
                    'evidence' => 'The exact approved Territory observation review is no longer valid for this destination scope.',
                ]);
            }

            $dataset = $this->datasets->require($mapDatasetId, $mapDatasetChecksum);
            $bounds = $this->validateCoverage(
                $dataset->data,
                $coverageKind,
                $completeness,
                $coverageBounds,
                count($objects),
            );
            $validatedObjects = $this->validateObjects($dataset->data, $kingdomId, $objects);

            $corrects = null;
            if ($correctsObservationId !== null) {
                $reason = trim((string) $correctionReason);
                if (mb_strlen($reason) < 8 || mb_strlen($reason) > 1000) {
                    throw ValidationException::withMessages([
                        'correction_reason' => 'A correction reason between 8 and 1,000 characters is required.',
                    ]);
                }

                $corrects = SpatialObservation::query()
                    ->where('alliance_id', $allianceId)
                    ->where('kingdom_id', $kingdomId)
                    ->whereKey($correctsObservationId)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($corrects->invalidated_at !== null) {
                    throw ValidationException::withMessages([
                        'corrects_observation_id' => 'Only a current accepted observation may be corrected.',
                    ]);
                }
            }

            $observation = SpatialObservation::query()->create([
                'alliance_id' => $allianceId,
                'kingdom_id' => $kingdomId,
                'captured_at' => $captured,
                'coverage_kind' => $coverageKind,
                'completeness' => $completeness,
                'coverage_bounds' => $bounds,
                'map_dataset_id' => $mapDatasetId,
                'map_dataset_checksum' => $mapDatasetChecksum,
                'source' => 'screenshot_evidence',
                'source_evidence_id' => $evidenceId,
                'source_review_id' => $reviewId,
                'destination_idempotency_key' => $idempotencyKey,
                'accepted_by_player_id' => $actorPlayerId,
                'accepted_at' => now(),
                'corrects_observation_id' => $corrects?->id,
            ]);

            foreach ($validatedObjects as $object) {
                SpatialObservedObject::query()->create([
                    'spatial_observation_id' => $observation->id,
                    'object_key' => $object['key'],
                    'object_type' => $object['type'],
                    'coordinate_x' => $object['x'],
                    'coordinate_y' => $object['y'],
                    'player_id' => $object['player_id'],
                    'plan_local_identity' => $object['plan_local_identity'],
                    'observed_label' => $object['observed_label'],
                    'identity_state' => $object['identity_state'],
                    'confidence' => $object['confidence'],
                    'source_metadata' => $object['source_metadata'],
                ]);
            }

            if ($corrects instanceof SpatialObservation) {
                $corrects->forceFill([
                    'invalidated_at' => now(),
                    'invalidated_by_player_id' => $actorPlayerId,
                    'invalidation_reason' => trim((string) $correctionReason),
                ])->save();
            }

            $receipt = SpatialObservationEvidenceReceipt::query()->create([
                'alliance_id' => $allianceId,
                'kingdom_id' => $kingdomId,
                'observation_id' => $observation->id,
                'evidence_id' => $evidenceId,
                'review_id' => $reviewId,
                'schema_version' => $schemaVersion,
                'map_dataset_id' => $mapDatasetId,
                'map_dataset_checksum' => $mapDatasetChecksum,
                'idempotency_key' => $idempotencyKey,
                'accepted_by_player_id' => $actorPlayerId,
                'accepted_at' => now(),
            ]);

            $metadata = [
                'observation_id' => (string) $observation->id,
                'receipt_id' => (string) $receipt->id,
                'kingdom_id' => $kingdomId,
                'captured_at' => $captured->toIso8601String(),
                'coverage_kind' => $coverageKind->value,
                'completeness' => $completeness->value,
                'object_count' => count($validatedObjects),
                'evidence_id' => $evidenceId,
                'review_id' => $reviewId,
                'map_dataset_id' => $mapDatasetId,
                'map_dataset_checksum' => $mapDatasetChecksum,
                'corrects_observation_id' => $corrects?->id === null ? null : (string) $corrects->id,
            ];
            $event = 'intelligence.spatial_observation_recorded';
            $this->audit->record($event, $actor, $observation, $allianceId, $metadata);
            $this->outbox->record(
                $event,
                $allianceId,
                $observation,
                $metadata,
                $event.':'.$observation->id,
            );

            return new SpatialObservationRecordResult(
                receiptId: (string) $receipt->id,
                observationId: (string) $observation->id,
                idempotentReplay: false,
            );
        });
    }

    /**
     * @param  array<string,mixed>  $dataset
     * @param  array{x:int,y:int,width:int,height:int}|null  $bounds
     * @return array{x:int,y:int,width:int,height:int}|null
     */
    private function validateCoverage(
        array $dataset,
        SpatialObservationCoverageKind $kind,
        SpatialObservationCompleteness $completeness,
        ?array $bounds,
        int $objectCount,
    ): ?array {
        if ($kind === SpatialObservationCoverageKind::CompleteHive
            && $completeness !== SpatialObservationCompleteness::Complete) {
            throw ValidationException::withMessages([
                'completeness' => 'A complete-hive observation must be explicitly complete.',
            ]);
        }
        if ($kind === SpatialObservationCoverageKind::CompleteVisibleRegion
            && $completeness !== SpatialObservationCompleteness::Complete) {
            throw ValidationException::withMessages([
                'completeness' => 'A complete visible-region observation must be explicitly complete.',
            ]);
        }
        if ($kind === SpatialObservationCoverageKind::SingleObject && $objectCount !== 1) {
            throw ValidationException::withMessages([
                'objects' => 'A single-object observation must contain exactly one reviewed object.',
            ]);
        }
        if ($kind === SpatialObservationCoverageKind::CompleteVisibleRegion && $bounds === null) {
            throw ValidationException::withMessages([
                'coverage_bounds' => 'Complete visible-region observations require explicit visible bounds.',
            ]);
        }
        if ($bounds === null) {
            return null;
        }

        foreach (['x', 'y', 'width', 'height'] as $field) {
            if (! isset($bounds[$field]) || ! is_int($bounds[$field])) {
                throw ValidationException::withMessages([
                    'coverage_bounds' => 'Coverage bounds require integer x, y, width and height values.',
                ]);
            }
        }
        if ($bounds['width'] < 1 || $bounds['height'] < 1) {
            throw ValidationException::withMessages([
                'coverage_bounds' => 'Coverage bounds must have positive width and height.',
            ]);
        }

        $map = $dataset['bounds'] ?? null;
        if (! is_array($map)) {
            throw ValidationException::withMessages([
                'map_dataset_id' => 'The pinned map dataset does not expose coordinate bounds.',
            ]);
        }

        $mapX = (int) ($map['x'] ?? 0);
        $mapY = (int) ($map['y'] ?? 0);
        $mapWidth = (int) ($map['width'] ?? 0);
        $mapHeight = (int) ($map['height'] ?? 0);
        if ($bounds['x'] < $mapX
            || $bounds['y'] < $mapY
            || $mapX + $mapWidth < $bounds['x'] + $bounds['width']
            || $mapY + $mapHeight < $bounds['y'] + $bounds['height']) {
            throw ValidationException::withMessages([
                'coverage_bounds' => 'Coverage bounds must stay inside the pinned Kingdom map.',
            ]);
        }

        return $bounds;
    }

    /**
     * @param  array<string,mixed>  $dataset
     * @param  list<array<string,mixed>>  $objects
     * @return list<array{key:string,type:SpatialObservedObjectType,x:int,y:int,player_id:?string,plan_local_identity:?string,observed_label:?string,identity_state:SpatialObservedIdentityState,confidence:?float,source_metadata:?array<string,mixed>}>
     */
    private function validateObjects(array $dataset, string $kingdomId, array $objects): array
    {
        if (count($objects) > 5000) {
            throw ValidationException::withMessages([
                'objects' => 'A spatial observation may contain at most 5,000 reviewed objects.',
            ]);
        }

        $map = is_array($dataset['bounds'] ?? null) ? $dataset['bounds'] : [];
        $minX = (int) ($map['x'] ?? 0);
        $minY = (int) ($map['y'] ?? 0);
        $maxX = $minX + (int) ($map['width'] ?? 0);
        $maxY = $minY + (int) ($map['height'] ?? 0);
        $definitions = is_array($dataset['object_types'] ?? null) ? $dataset['object_types'] : [];
        $seen = [];
        $playerIds = [];

        foreach ($objects as $index => $object) {
            if (! is_array($object)) {
                throw ValidationException::withMessages([
                    "objects.{$index}" => 'Each reviewed object must be structured data.',
                ]);
            }

            $key = trim((string) ($object['key'] ?? ''));
            if ($key === '' || mb_strlen($key) > 120 || isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "objects.{$index}.key" => 'Observed object keys must be unique and no longer than 120 characters.',
                ]);
            }
            $seen[$key] = true;

            $type = SpatialObservedObjectType::tryFrom((string) ($object['type'] ?? ''));
            if (! $type instanceof SpatialObservedObjectType) {
                throw ValidationException::withMessages([
                    "objects.{$index}.type" => 'The observed object type is unsupported.',
                ]);
            }
            if (! is_int($object['x'] ?? null) || ! is_int($object['y'] ?? null)) {
                throw ValidationException::withMessages([
                    "objects.{$index}" => 'Observed coordinates must be whole-number game coordinates.',
                ]);
            }

            $definition = is_array($definitions[$type->value] ?? null)
                ? $definitions[$type->value]
                : [];
            $size = max(1, (int) ($definition['size'] ?? 1));
            $x = $object['x'];
            $y = $object['y'];
            if ($x < $minX || $y < $minY || $x + $size > $maxX || $y + $size > $maxY) {
                throw ValidationException::withMessages([
                    "objects.{$index}" => 'An observed object lies outside the pinned Kingdom map bounds.',
                ]);
            }

            $identity = SpatialObservedIdentityState::tryFrom(
                (string) ($object['identity_state'] ?? SpatialObservedIdentityState::Unresolved->value),
            );
            if (! $identity instanceof SpatialObservedIdentityState) {
                throw ValidationException::withMessages([
                    "objects.{$index}.identity_state" => 'The observed identity state is unsupported.',
                ]);
            }

            $playerId = is_string($object['player_id'] ?? null)
                && trim($object['player_id']) !== ''
                    ? trim($object['player_id'])
                    : null;
            $planLocal = is_string($object['plan_local_identity'] ?? null)
                && trim($object['plan_local_identity']) !== ''
                    ? trim($object['plan_local_identity'])
                    : null;
            if ($type !== SpatialObservedObjectType::GovernorCity) {
                if ($playerId !== null
                    || $planLocal !== null
                    || $identity !== SpatialObservedIdentityState::Unresolved) {
                    throw ValidationException::withMessages([
                        "objects.{$index}.identity_state" => 'Only Governor-city observations may carry Governor identity mapping.',
                    ]);
                }
            } elseif ($identity === SpatialObservedIdentityState::ResolvedPlayer) {
                if ($playerId === null || $planLocal !== null) {
                    throw ValidationException::withMessages([
                        "objects.{$index}.player_id" => 'Resolved Player identity requires exactly one Player reference.',
                    ]);
                }
                $playerIds[$playerId] = true;
            } elseif ($identity === SpatialObservedIdentityState::ResolvedPlanLocal) {
                if ($planLocal === null || $playerId !== null || mb_strlen($planLocal) > 191) {
                    throw ValidationException::withMessages([
                        "objects.{$index}.plan_local_identity" => 'Resolved plan-local identity requires exactly one reviewed plan-local identity.',
                    ]);
                }
            } elseif ($playerId !== null || $planLocal !== null) {
                throw ValidationException::withMessages([
                    "objects.{$index}.identity_state" => 'Ambiguous or unresolved identity cannot retain a definitive Player/plan-local mapping.',
                ]);
            }
        }

        $references = $this->players->byIds(array_keys($playerIds));
        foreach (array_keys($playerIds) as $playerId) {
            $reference = $references[$playerId] ?? null;
            if ($reference === null || $reference->kingdomId !== $kingdomId) {
                throw ValidationException::withMessages([
                    'objects' => 'A resolved Governor identity must reference a current Player in the observed Kingdom.',
                ]);
            }
        }

        $validated = [];
        foreach ($objects as $object) {
            $confidence = isset($object['confidence']) ? (float) $object['confidence'] : null;
            if ($confidence !== null && ($confidence < 0 || $confidence > 1)) {
                throw ValidationException::withMessages([
                    'objects' => 'Observed-object confidence must be between 0 and 1.',
                ]);
            }
            $validated[] = [
                'key' => trim((string) $object['key']),
                'type' => SpatialObservedObjectType::from((string) $object['type']),
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
                'identity_state' => SpatialObservedIdentityState::from(
                    (string) ($object['identity_state'] ?? SpatialObservedIdentityState::Unresolved->value),
                ),
                'confidence' => $confidence,
                'source_metadata' => is_array($object['source_metadata'] ?? null)
                    ? $object['source_metadata']
                    : null,
            ];
        }

        return $validated;
    }
}
