<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEvidenceTarget;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransferEvidenceTargetQuery
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authorization,
    ) {}

    public function authorizeAllianceManage(string $actorPlayerId, string $allianceId): void
    {
        DB::transaction(function () use ($actorPlayerId, $allianceId): void {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authorization->authorizeContext($context, TransferPermission::Manage);
        });
    }

    public function authorizeManage(
        string $actorPlayerId,
        string $allianceId,
        string $planId,
        string $participantId,
    ): TransferEvidenceTarget {
        return DB::transaction(function () use ($actorPlayerId, $allianceId, $planId, $participantId): TransferEvidenceTarget {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authorization->authorizeContext($context, TransferPermission::Manage);

            $plan = TransferPlan::query()
                ->where('alliance_id', $allianceId)
                ->whereKey($planId)
                ->sharedLock()
                ->firstOrFail();
            if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
                throw ValidationException::withMessages(['plan' => 'Transfer Evidence can be added only while the Transfer Plan is mutable.']);
            }

            $participant = TransferParticipant::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_plan_id', $planId)
                ->whereKey($participantId)
                ->sharedLock()
                ->firstOrFail();
            if ($participant->withdrawn_at !== null) {
                throw ValidationException::withMessages(['participant' => 'Withdrawn participants cannot receive new Transfer Evidence.']);
            }

            $targetKingdomId = match ($participant->direction->value) {
                'incoming' => (string) $plan->home_kingdom_id,
                'outgoing' => $participant->destination_kingdom_id === null ? null : (string) $participant->destination_kingdom_id,
                default => null,
            };

            return new TransferEvidenceTarget(
                allianceId: $allianceId,
                transferPlanId: (string) $plan->id,
                transferParticipantId: (string) $participant->id,
                transferWindowId: (string) $plan->transfer_window_id,
                direction: $participant->direction->value,
                targetKingdomId: $targetKingdomId,
            );
        });
    }
}
