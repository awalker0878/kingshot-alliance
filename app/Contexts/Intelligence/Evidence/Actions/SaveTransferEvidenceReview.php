<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationStatus;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEvidenceTargetQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceReviewKingdom;
use App\Contexts\Intelligence\Evidence\Services\TransferEvidenceSchemaRegistry;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveTransferEvidenceReview
{
    public function __construct(
        private TransferEvidenceTargetQuery $targets,
        private KingdomReferenceQuery $kingdoms,
        private TransferEvidenceSchemaRegistry $schemas,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param  list<int>  $officialGroupKingdomNumbers */
    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $planId,
        string $participantId,
        string $evidenceId,
        string $extractionAttemptId,
        string $observedAt,
        ?string $validUntil = null,
        ?int $governorPower = null,
        ?int $transferScore = null,
        ?int $transferPassesAvailable = null,
        ?int $transferPassesRequired = null,
        ?string $invitationStatus = null,
        ?int $targetKingdomNumber = null,
        ?int $targetPowerCap = null,
        ?string $kingdomClassification = null,
        ?string $officialGroupIdentifier = null,
        array $officialGroupKingdomNumbers = [],
    ): string {
        $target = $this->targets->authorizeManage($actorPlayerId, $allianceId, $planId, $participantId);
        $observed = CarbonImmutable::parse($observedAt)->utc();
        $valid = $validUntil === null || trim($validUntil) === '' ? null : CarbonImmutable::parse($validUntil)->utc();
        if ($valid !== null && $valid->lt($observed)) {
            throw ValidationException::withMessages(['valid_until' => 'Validity must end on or after the observation time.']);
        }

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $planId, $participantId, $evidenceId, $extractionAttemptId, $observed, $valid, $governorPower, $transferScore, $transferPassesAvailable, $transferPassesRequired, $invitationStatus, $targetKingdomNumber, $targetPowerCap, $kingdomClassification, $officialGroupIdentifier, $officialGroupKingdomNumbers, $target): string {
            $currentTarget = $this->targets->authorizeManage($actorPlayerId, $allianceId, $planId, $participantId);
            if ($currentTarget->transferWindowId !== $target->transferWindowId || $currentTarget->direction !== $target->direction || $currentTarget->targetKingdomId !== $target->targetKingdomId) {
                throw ValidationException::withMessages(['evidence' => 'Transfer scope changed while the Evidence review was being saved. Review the current participant state again.']);
            }
            $actor = $this->players->lockCurrent($actorPlayerId);
            $evidence = GameEvidence::query()
                ->whereKey($evidenceId)
                ->where('alliance_id', $allianceId)
                ->whereNull('occurrence_id')
                ->where('transfer_plan_id', $planId)
                ->where('transfer_participant_id', $participantId)
                ->lockForUpdate()
                ->firstOrFail();
            $kind = EvidenceKind::from((string) $evidence->getRawOriginal('kind'));
            if (! $kind->isTransfer() || $kind !== EvidenceKind::from((string) $evidence->getRawOriginal('expected_kind'))) {
                throw ValidationException::withMessages(['evidence' => 'The screenshot class has not been safely verified.']);
            }
            $schema = $this->schemas->require($kind);
            $extraction = EvidenceExtractionAttempt::query()
                ->whereKey($extractionAttemptId)
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

            $sourceFields = EvidenceExtractedField::query()
                ->where('extraction_attempt_id', $extraction->id)
                ->get();
            foreach ($sourceFields as $field) {
                if (! in_array((string) $field->field_key, $schema->supportedFields, true)) {
                    throw ValidationException::withMessages(['evidence' => 'Extraction contained a field that is not supported by this schema version.']);
                }
            }

            [$values, $kingdomNumbers, $reviewTargetId] = $this->validatedMeaning(
                kind: $kind,
                targetKingdomId: $currentTarget->targetKingdomId,
                governorPower: $governorPower,
                transferScore: $transferScore,
                transferPassesAvailable: $transferPassesAvailable,
                transferPassesRequired: $transferPassesRequired,
                invitationStatus: $invitationStatus,
                targetKingdomNumber: $targetKingdomNumber,
                targetPowerCap: $targetPowerCap,
                kingdomClassification: $kingdomClassification,
                officialGroupIdentifier: $officialGroupIdentifier,
                officialGroupKingdomNumbers: $officialGroupKingdomNumbers,
                validUntil: $valid,
            );

            $fingerprint = $this->fingerprint(
                $kind,
                $schema->version,
                $currentTarget->transferWindowId,
                $participantId,
                $reviewTargetId,
                $observed,
                $values,
                $kingdomNumbers,
            );
            $duplicateQuery = TransferEvidenceReview::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_window_id', $currentTarget->transferWindowId)
                ->where('evidence_kind', $kind->value)
                ->where('semantic_fingerprint', $fingerprint)
                ->where('evidence_id', '!=', $evidenceId)
                ->whereIn('status', [EvidenceReviewStatus::Approved->value, EvidenceReviewStatus::DuplicateBlocked->value]);
            if (in_array($kind, [EvidenceKind::TransferGovernorStatus, EvidenceKind::TransferScorePasses, EvidenceKind::TransferInvitation], true)) {
                $duplicateQuery->where('transfer_participant_id', $participantId);
            }
            $duplicate = $duplicateQuery->orderBy('reviewed_at')->first();
            $revision = ((int) TransferEvidenceReview::query()->where('evidence_id', $evidenceId)->max('revision_number')) + 1;
            $status = $duplicate instanceof TransferEvidenceReview ? EvidenceReviewStatus::DuplicateBlocked : EvidenceReviewStatus::Approved;
            $review = TransferEvidenceReview::query()->create([
                'evidence_id' => $evidenceId,
                'extraction_attempt_id' => $extraction->id,
                'alliance_id' => $allianceId,
                'transfer_plan_id' => $planId,
                'transfer_participant_id' => $participantId,
                'transfer_window_id' => $currentTarget->transferWindowId,
                'target_kingdom_id' => $reviewTargetId,
                'evidence_kind' => $kind,
                'schema_version' => $schema->version,
                'revision_number' => $revision,
                'status' => $status,
                'observed_at' => $observed,
                'valid_until' => $valid,
                ...$values,
                'semantic_fingerprint' => $fingerprint,
                'semantic_duplicate_review_id' => $duplicate instanceof TransferEvidenceReview ? (string) $duplicate->id : null,
                'reviewed_by_player_id' => $actorPlayerId,
                'reviewed_at' => now(),
            ]);
            foreach ($kingdomNumbers as $index => $number) {
                TransferEvidenceReviewKingdom::query()->create([
                    'review_id' => $review->id,
                    'kingdom_number' => $number,
                    'ordinal' => $index + 1,
                ]);
            }
            $evidence->forceFill([
                'lifecycle_status' => $status === EvidenceReviewStatus::Approved
                    ? EvidenceLifecycleStatus::Approved
                    : EvidenceLifecycleStatus::NeedsReview,
            ])->save();

            $sourceMap = $sourceFields
                ->toBase()
                ->groupBy(static fn (EvidenceExtractedField $field): string => (string) $field->field_key);
            $correctedFields = $this->correctedFieldCount($values, $kingdomNumbers, $sourceMap);
            $metadata = [
                'evidence_id' => $evidenceId,
                'review_id' => (string) $review->id,
                'evidence_kind' => $kind->value,
                'schema_version' => $schema->version,
                'revision_number' => $revision,
                'semantic_duplicate' => $duplicate instanceof TransferEvidenceReview,
                'corrected_field_count' => $correctedFields,
            ];
            $event = $duplicate instanceof TransferEvidenceReview ? 'evidence.semantic_duplicate_detected' : 'evidence.transfer_review_approved';
            $this->audit->record($event, $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record($event, $allianceId, $evidence, $metadata);

            return (string) $review->id;
        });
    }

    /**
     * @param  list<int>  $officialGroupKingdomNumbers
     * @return array{0:array<string,int|string|null>,1:list<int>,2:?string}
     */
    private function validatedMeaning(
        EvidenceKind $kind,
        ?string $targetKingdomId,
        ?int $governorPower,
        ?int $transferScore,
        ?int $transferPassesAvailable,
        ?int $transferPassesRequired,
        ?string $invitationStatus,
        ?int $targetKingdomNumber,
        ?int $targetPowerCap,
        ?string $kingdomClassification,
        ?string $officialGroupIdentifier,
        array $officialGroupKingdomNumbers,
        ?CarbonImmutable $validUntil,
    ): array {
        $empty = [
            'governor_power' => null,
            'transfer_score' => null,
            'transfer_passes_available' => null,
            'transfer_passes_required' => null,
            'invitation_status' => null,
            'target_power_cap' => null,
            'kingdom_classification' => null,
            'official_group_identifier' => null,
        ];

        return match ($kind) {
            EvidenceKind::TransferGovernorStatus => [
                [...$empty, 'governor_power' => $this->nonNegative($governorPower, 'Governor Power')],
                [],
                $this->noTargetWithValidity($validUntil),
            ],
            EvidenceKind::TransferScorePasses => [
                [...$empty,
                    'transfer_score' => $this->nonNegative($transferScore, 'Transfer Score'),
                    'transfer_passes_available' => $this->nonNegative($transferPassesAvailable, 'available Transfer Passes'),
                    'transfer_passes_required' => $this->nonNegative($transferPassesRequired, 'required Transfer Passes'),
                ],
                [],
                $this->requireTarget($targetKingdomId, $validUntil),
            ],
            EvidenceKind::TransferInvitation => [
                [...$empty, 'invitation_status' => $this->invitation($invitationStatus)],
                [],
                $this->verifiedTargetWithValidity($targetKingdomId, $targetKingdomNumber, $validUntil),
            ],
            EvidenceKind::TransferTargetKingdomRules => [
                [...$empty,
                    'target_power_cap' => $this->nonNegative($targetPowerCap, 'Power Cap'),
                    'kingdom_classification' => $this->classification($kingdomClassification),
                ],
                [],
                $this->verifiedTarget($targetKingdomId, $targetKingdomNumber, true),
            ],
            EvidenceKind::TransferOfficialGroup => [
                [...$empty, 'official_group_identifier' => $this->groupIdentifier($officialGroupIdentifier)],
                $this->kingdomNumbers($officialGroupKingdomNumbers),
                null,
            ],
            default => throw ValidationException::withMessages(['evidence' => 'Unsupported Transfer Evidence schema.']),
        };
    }

    private function nonNegative(?int $value, string $label): int
    {
        if ($value === null || $value < 0) {
            throw ValidationException::withMessages(['evidence' => $label.' is required and must be a non-negative integer.']);
        }

        return $value;
    }

    private function noTargetWithValidity(?CarbonImmutable $validUntil): null
    {
        $this->requireValidity($validUntil);

        return null;
    }

    private function requireValidity(?CarbonImmutable $validUntil): void
    {
        if (! $validUntil instanceof CarbonImmutable) {
            throw ValidationException::withMessages(['valid_until' => 'A validity boundary is required for this mutable Transfer observation.']);
        }
    }

    private function requireTarget(?string $targetKingdomId, ?CarbonImmutable $validUntil): string
    {
        $this->requireValidity($validUntil);
        if ($targetKingdomId === null) {
            throw ValidationException::withMessages(['participant' => 'This screenshot class requires a current target Kingdom.']);
        }

        return $targetKingdomId;
    }

    private function verifiedTargetWithValidity(?string $targetKingdomId, ?int $observedNumber, ?CarbonImmutable $validUntil): string
    {
        $this->requireValidity($validUntil);

        return $this->verifiedTarget($targetKingdomId, $observedNumber, false);
    }

    private function verifiedTarget(?string $targetKingdomId, ?int $observedNumber, bool $requiredObservedNumber): string
    {
        if ($targetKingdomId === null) {
            throw ValidationException::withMessages(['participant' => 'This screenshot class requires a current target Kingdom.']);
        }
        if ($requiredObservedNumber && $observedNumber === null) {
            throw ValidationException::withMessages(['target_kingdom_number' => 'The target Kingdom number must be visible and reviewed for this screenshot class.']);
        }
        if ($observedNumber !== null) {
            $observed = $this->kingdoms->findByNumber($observedNumber);
            if ($observed === null || $observed->kingdomId !== $targetKingdomId) {
                throw ValidationException::withMessages(['target_kingdom_number' => 'The reviewed screenshot target does not match the participant current target Kingdom.']);
            }
        }

        return $targetKingdomId;
    }

    private function invitation(?string $status): string
    {
        $status = $status === null ? '' : trim($status);
        if (TransferInvitationStatus::tryFrom($status) === null) {
            throw ValidationException::withMessages(['invitation_status' => 'Review the invitation into a supported in-game invitation state.']);
        }

        return $status;
    }

    private function classification(?string $classification): string
    {
        $classification = $classification === null || trim($classification) === '' ? 'unknown' : trim($classification);
        if (TransferKingdomClassification::tryFrom($classification) === null) {
            throw ValidationException::withMessages(['kingdom_classification' => 'Kingdom classification is invalid.']);
        }

        return $classification;
    }

    private function groupIdentifier(?string $identifier): string
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '' || mb_strlen($identifier) > 96) {
            throw ValidationException::withMessages(['official_group_identifier' => 'An official Transfer Group identifier of 96 characters or fewer is required.']);
        }

        return $identifier;
    }

    /**
     * @param  list<int>  $numbers
     * @return list<int>
     */
    private function kingdomNumbers(array $numbers): array
    {
        $normalized = [];
        foreach ($numbers as $number) {
            if ($number < 1 || $number > 999999) {
                throw ValidationException::withMessages(['kingdom_numbers' => 'Official Transfer Group Kingdom numbers must be positive supported Kingdom numbers.']);
            }
            $normalized[$number] = $number;
        }
        ksort($normalized, SORT_NUMERIC);
        $values = array_values($normalized);
        if ($values === []) {
            throw ValidationException::withMessages(['kingdom_numbers' => 'Review at least one explicitly visible Kingdom for the official Transfer Group.']);
        }

        return $values;
    }

    /**
     * @param  array<string, int|string|null>  $values
     * @param  list<int>  $kingdomNumbers
     */
    private function fingerprint(EvidenceKind $kind, string $schemaVersion, string $windowId, string $participantId, ?string $targetId, CarbonImmutable $observedAt, array $values, array $kingdomNumbers): string
    {
        $payload = match ($kind) {
            EvidenceKind::TransferGovernorStatus => [$schemaVersion, $windowId, $participantId, $values['governor_power'], $observedAt->toIso8601String()],
            EvidenceKind::TransferScorePasses => [$schemaVersion, $windowId, $participantId, $targetId, $values['transfer_score'], $values['transfer_passes_available'], $values['transfer_passes_required'], $observedAt->toIso8601String()],
            EvidenceKind::TransferInvitation => [$schemaVersion, $windowId, $participantId, $targetId, $values['invitation_status'], $observedAt->toIso8601String()],
            EvidenceKind::TransferTargetKingdomRules => [$schemaVersion, $windowId, $targetId, $values['target_power_cap'], $values['kingdom_classification'], $observedAt->toIso8601String()],
            EvidenceKind::TransferOfficialGroup => [$schemaVersion, $windowId, $values['official_group_identifier'], $kingdomNumbers, $observedAt->toIso8601String()],
            default => throw ValidationException::withMessages(['evidence' => 'Unsupported Transfer Evidence fingerprint schema.']),
        };

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, int|string|null>  $values
     * @param  list<int>  $kingdomNumbers
     * @param  Collection<int|string, Collection<int, EvidenceExtractedField>>  $sourceMap
     */
    private function correctedFieldCount(array $values, array $kingdomNumbers, Collection $sourceMap): int
    {
        $count = 0;
        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }
            $sources = $sourceMap->get($key, collect());
            $source = $sources->first();
            if ($sources->count() !== 1 || ! $source instanceof EvidenceExtractedField || (string) $source->normalized_value !== (string) $value) {
                $count++;
            }
        }
        if ($kingdomNumbers !== []) {
            $sourceNumbers = $sourceMap->get('kingdom_number', collect())
                ->map(static fn (EvidenceExtractedField $field): int => (int) $field->normalized_value)
                ->sort()
                ->values()
                ->all();
            if ($sourceNumbers !== $kingdomNumbers) {
                $count++;
            }
        }

        return $count;
    }
}
