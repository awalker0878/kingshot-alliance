<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Services;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationStatus;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferMutationContext;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class TransferObservationWriter
{
    public function __construct(
        private TransferEvidenceReferenceGuard $evidence,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function append(
        TransferMutationContext $context,
        string $allianceId,
        string $planId,
        string $participantId,
        TransferObservationKind $kind,
        int|string|bool $value,
        TransferSourceType $sourceType,
        string $sourceReference,
        string $observedAt,
        ?string $validUntil,
        ?string $details = null,
        ?string $evidenceId = null,
    ): string {
        $plan = TransferPlan::query()
            ->where('alliance_id', $allianceId)
            ->whereKey($planId)
            ->sharedLock()
            ->firstOrFail();
        $participant = TransferParticipant::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $planId)
            ->whereKey($participantId)
            ->lockForUpdate()
            ->firstOrFail();
        if ($participant->withdrawn_at !== null) {
            throw ValidationException::withMessages([
                'participant' => 'Withdrawn participants cannot receive current transfer observations.',
            ]);
        }

        $targetId = $participant->direction->value === 'incoming'
            ? (string) $plan->home_kingdom_id
            : ($participant->destination_kingdom_id === null ? null : (string) $participant->destination_kingdom_id);
        if ($kind->requiresTargetKingdom() && $targetId === null) {
            throw ValidationException::withMessages([
                'target' => 'Set a target Kingdom before recording this observation.',
            ]);
        }
        $observationTarget = $kind->requiresTargetKingdom() ? $targetId : null;

        $sourceReference = trim($sourceReference);
        if ($sourceReference === '') {
            throw ValidationException::withMessages([
                'source_reference' => 'A source reference is required.',
            ]);
        }
        $evidenceId = $this->evidence->assertUsable($allianceId, $sourceType, $evidenceId);
        if ($kind->usesNumericValue() && (! is_int($value) || $value < 0)) {
            throw ValidationException::withMessages([
                'value' => 'This observation requires a non-negative integer value.',
            ]);
        }
        if ($kind === TransferObservationKind::InGameRulesVerified && ! is_bool($value)) {
            throw ValidationException::withMessages([
                'value' => 'In-game rule verification requires a boolean value.',
            ]);
        }
        if ($kind === TransferObservationKind::InvitationStatus && (! is_string($value) || TransferInvitationStatus::tryFrom($value) === null)) {
            throw ValidationException::withMessages([
                'value' => 'Invitation status is invalid.',
            ]);
        }

        $observed = CarbonImmutable::parse($observedAt)->utc();
        $valid = $validUntil === null || trim($validUntil) === ''
            ? null
            : CarbonImmutable::parse($validUntil)->utc();
        if ($valid !== null && $valid->lt($observed)) {
            throw ValidationException::withMessages([
                'valid_until' => 'Validity must end on or after the observation time.',
            ]);
        }
        $details = $details === null ? null : trim($details);
        $serialized = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        $fingerprint = hash('sha256', implode('|', [
            $allianceId,
            $plan->transfer_window_id,
            $planId,
            $participantId,
            (string) $observationTarget,
            $kind->value,
            $serialized,
            $sourceType->value,
            $sourceReference,
            $observed->toIso8601String(),
            $valid?->toIso8601String() ?? '',
            $details ?? '',
            $evidenceId ?? '',
        ]));
        $existing = TransferObservation::query()->where('fingerprint', $fingerprint)->first();
        if ($existing instanceof TransferObservation) {
            return (string) $existing->id;
        }

        $row = TransferObservation::query()->create([
            'alliance_id' => $allianceId,
            'transfer_window_id' => $plan->transfer_window_id,
            'transfer_plan_id' => $planId,
            'transfer_participant_id' => $participantId,
            'target_kingdom_id' => $observationTarget,
            'kind' => $kind,
            'numeric_value' => $kind->usesNumericValue() ? $value : null,
            'text_value' => $kind === TransferObservationKind::InvitationStatus ? $value : null,
            'boolean_value' => $kind === TransferObservationKind::InGameRulesVerified ? $value : null,
            'details' => $details,
            'source_type' => $sourceType,
            'source_reference' => $sourceReference,
            'observed_at' => $observed,
            'valid_until' => $valid,
            'evidence_id' => $evidenceId,
            'fingerprint' => $fingerprint,
            'recorded_by_player_id' => $context->actor->playerId,
        ]);
        $metadata = [
            'alliance_id' => $allianceId,
            'transfer_window_id' => (string) $plan->transfer_window_id,
            'transfer_plan_id' => $planId,
            'transfer_participant_id' => $participantId,
            'transfer_observation_id' => (string) $row->id,
            'kind' => $kind->value,
            'source_type' => $sourceType->value,
            'observed_at' => $observed->toIso8601String(),
            'has_validity' => $valid !== null,
        ];
        $this->audit->record('kingdoms.transfer_observation_recorded', $context->actor, $row, null, $metadata);
        $this->outbox->record('kingdoms.transfer_observation_recorded', $allianceId, $row, $metadata);

        return (string) $row->id;
    }
}
