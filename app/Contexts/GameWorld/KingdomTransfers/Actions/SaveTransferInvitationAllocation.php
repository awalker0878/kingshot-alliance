<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationAllocationState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferInvitationAllocation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomCapacityObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveTransferInvitationAllocation
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $planId,
        string $participantId,
        TransferInvitationKind $kind,
        TransferInvitationAllocationState $state,
        ?string $notes = null,
    ): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $participantId, $kind, $state, $notes): string {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);
            $plan = TransferPlan::query()->where('alliance_id', $allianceId)->whereKey($planId)->sharedLock()->firstOrFail();
            if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
                throw ValidationException::withMessages(['invitation' => 'Invitation allocations can only change while the transfer cycle is Draft or Open.']);
            }
            $participant = TransferParticipant::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_plan_id', $planId)
                ->whereKey($participantId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($participant->withdrawn_at !== null || $participant->direction->value === 'staying') {
                throw ValidationException::withMessages(['invitation' => 'Only active transfer participants can receive an invitation allocation.']);
            }
            $targetId = $participant->direction->value === 'incoming'
                ? (string) $plan->home_kingdom_id
                : ($participant->destination_kingdom_id === null ? null : (string) $participant->destination_kingdom_id);
            if ($targetId === null) {
                throw ValidationException::withMessages(['invitation' => 'Set the target Kingdom before allocating an invitation.']);
            }

            $existing = TransferInvitationAllocation::query()->where('transfer_participant_id', $participantId)->lockForUpdate()->first();
            if ($kind === TransferInvitationKind::Special && $state->consumesPlannedInventory()) {
                $condition = TransferKingdomConditionObservation::query()
                    ->where('alliance_id', $allianceId)->where('transfer_window_id', $plan->transfer_window_id)->where('kingdom_id', $targetId)
                    ->orderByDesc('observed_at')->orderByDesc('id')->get()
                    ->first(static fn (TransferKingdomConditionObservation $row): bool => $row->source_type->isAuthoritative());
                if (! $condition instanceof TransferKingdomConditionObservation || $condition->classification !== TransferKingdomClassification::Ordinary) {
                    throw ValidationException::withMessages(['invitation' => 'Special Invites require an authoritative Ordinary Kingdom classification; Leading Kingdoms cannot issue them.']);
                }
                $capacity = TransferKingdomCapacityObservation::query()
                    ->where('alliance_id', $allianceId)->where('transfer_window_id', $plan->transfer_window_id)->where('kingdom_id', $targetId)
                    ->orderByDesc('observed_at')->orderByDesc('id')->get()
                    ->first(static fn (TransferKingdomCapacityObservation $row): bool => $row->source_type->isAuthoritative());
                if (! $capacity instanceof TransferKingdomCapacityObservation || $capacity->special_invites_available === null) {
                    throw ValidationException::withMessages(['invitation' => 'Verify current Special Invite inventory before reserving one.']);
                }
                $activeStates = array_map(
                    static fn (TransferInvitationAllocationState $candidate): string => $candidate->value,
                    array_filter(
                        TransferInvitationAllocationState::cases(),
                        static fn (TransferInvitationAllocationState $candidate): bool => $candidate->consumesPlannedInventory(),
                    ),
                );
                $otherReserved = TransferInvitationAllocation::query()
                    ->where('alliance_id', $allianceId)
                    ->where('transfer_window_id', $plan->transfer_window_id)
                    ->where('target_kingdom_id', $targetId)
                    ->where('kind', TransferInvitationKind::Special->value)
                    ->whereIn('state', $activeStates)
                    ->when($existing instanceof TransferInvitationAllocation, static fn (Builder $query): Builder => $query->where('id', '!=', (string) $existing->id))
                    ->lockForUpdate()
                    ->count();
                if ($capacity->special_invites_available - $otherReserved <= 0) {
                    throw ValidationException::withMessages(['invitation' => 'No verified Special Invite inventory remains after Alliance allocations.']);
                }
            }

            $row = $existing ?? new TransferInvitationAllocation;
            $row->fill([
                'alliance_id' => $allianceId,
                'transfer_window_id' => $plan->transfer_window_id,
                'transfer_plan_id' => $planId,
                'transfer_participant_id' => $participantId,
                'target_kingdom_id' => $targetId,
                'kind' => $kind,
                'state' => $state,
                'notes' => $notes === null ? null : trim($notes),
                'created_by_player_id' => $row->exists ? $row->created_by_player_id : $context->actor->playerId,
            ]);
            $row->save();

            $metadata = [
                'alliance_id' => $allianceId,
                'transfer_window_id' => (string) $plan->transfer_window_id,
                'transfer_plan_id' => $planId,
                'transfer_participant_id' => $participantId,
                'target_kingdom_id' => $targetId,
                'invitation_allocation_id' => (string) $row->id,
                'kind' => $kind->value,
                'state' => $state->value,
            ];
            $this->audit->record('kingdoms.transfer_invitation_allocation_saved', $context->actor, $row, null, $metadata);
            $this->outbox->record('kingdoms.transfer_invitation_allocation_saved', $allianceId, $row, $metadata);

            return (string) $row->id;
        });
    }
}