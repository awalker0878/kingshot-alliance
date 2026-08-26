<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Services;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferEvidenceReceipt;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEvidenceDestinationReceipt;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEvidenceTarget;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferMutationContext;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class TransferEvidenceDestinationSupport
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function authorize(string $actorPlayerId, string $allianceId): TransferMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Transfer Evidence destination support must run inside a transaction.');
        }
        $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
        $this->authorization->authorizeContext($context, TransferPermission::Manage);

        return $context;
    }

    public function existingReceipt(string $idempotencyKey): ?TransferEvidenceDestinationReceipt
    {
        $receipt = TransferEvidenceReceipt::query()
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();
        if (! $receipt instanceof TransferEvidenceReceipt) {
            return null;
        }

        return new TransferEvidenceDestinationReceipt(
            receiptId: (string) $receipt->id,
            destinationIds: is_array($receipt->destination_ids) ? array_map('strval', $receipt->destination_ids) : [],
            idempotentReplay: true,
        );
    }

    public function lockScope(
        string $allianceId,
        string $planId,
        string $participantId,
        string $expectedWindowId,
        ?string $expectedTargetKingdomId,
        bool $targetSpecific,
    ): TransferEvidenceTarget {
        $plan = TransferPlan::query()
            ->where('alliance_id', $allianceId)
            ->whereKey($planId)
            ->lockForUpdate()
            ->firstOrFail();
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages(['plan' => 'The Transfer Plan is no longer mutable.']);
        }
        if ((string) $plan->transfer_window_id !== $expectedWindowId) {
            throw ValidationException::withMessages(['evidence' => 'The Transfer Window changed after Evidence review. Re-review the screenshot against the current Plan.']);
        }

        $participant = TransferParticipant::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $planId)
            ->whereKey($participantId)
            ->lockForUpdate()
            ->firstOrFail();
        if ($participant->withdrawn_at !== null) {
            throw ValidationException::withMessages(['participant' => 'The participant was withdrawn after Evidence review.']);
        }
        $targetKingdomId = match ($participant->direction->value) {
            'incoming' => (string) $plan->home_kingdom_id,
            'outgoing' => $participant->destination_kingdom_id === null ? null : (string) $participant->destination_kingdom_id,
            default => null,
        };
        if ($targetSpecific && ($expectedTargetKingdomId === null || $targetKingdomId !== $expectedTargetKingdomId)) {
            throw ValidationException::withMessages(['evidence' => 'The participant target Kingdom changed after Evidence review. Re-review the screenshot.']);
        }

        return new TransferEvidenceTarget(
            allianceId: $allianceId,
            transferPlanId: (string) $plan->id,
            transferParticipantId: (string) $participant->id,
            transferWindowId: (string) $plan->transfer_window_id,
            direction: $participant->direction->value,
            targetKingdomId: $targetKingdomId,
        );
    }

    /**
     * @param  array<string,string>  $destinationIds
     */
    public function complete(
        TransferMutationContext $context,
        TransferEvidenceTarget $target,
        string $evidenceId,
        string $reviewId,
        string $evidenceKind,
        string $schemaVersion,
        string $idempotencyKey,
        array $destinationIds,
    ): TransferEvidenceDestinationReceipt {
        $existing = $this->existingReceipt($idempotencyKey);
        if ($existing instanceof TransferEvidenceDestinationReceipt) {
            return $existing;
        }

        $receipt = TransferEvidenceReceipt::query()->create([
            'alliance_id' => $target->allianceId,
            'transfer_window_id' => $target->transferWindowId,
            'transfer_plan_id' => $target->transferPlanId,
            'transfer_participant_id' => $target->transferParticipantId,
            'evidence_id' => $evidenceId,
            'review_id' => $reviewId,
            'evidence_kind' => $evidenceKind,
            'schema_version' => $schemaVersion,
            'idempotency_key' => $idempotencyKey,
            'destination_ids' => $destinationIds,
            'accepted_by_player_id' => $context->actor->playerId,
            'accepted_at' => now(),
        ]);
        $metadata = [
            'alliance_id' => $target->allianceId,
            'transfer_window_id' => $target->transferWindowId,
            'transfer_plan_id' => $target->transferPlanId,
            'transfer_participant_id' => $target->transferParticipantId,
            'transfer_evidence_receipt_id' => (string) $receipt->id,
            'evidence_kind' => $evidenceKind,
            'schema_version' => $schemaVersion,
            'destination_count' => count($destinationIds),
        ];
        $this->audit->record('kingdoms.transfer_evidence_committed', $context->actor, $receipt, null, $metadata);
        $this->outbox->record('kingdoms.transfer_evidence_committed', $target->allianceId, $receipt, $metadata);

        return new TransferEvidenceDestinationReceipt(
            receiptId: (string) $receipt->id,
            destinationIds: $destinationIds,
            idempotentReplay: false,
        );
    }
}
