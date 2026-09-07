<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCapacityBucket;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCapacityReservationState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCapacityReservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomCapacityObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferOfficialRulebook;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveTransferCapacityReservation
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authority,
        private TransferOfficialRulebook $rules,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $planId,
        string $participantId,
        TransferCapacityBucket $bucket,
        TransferCapacityReservationState $state,
        ?string $notes = null,
    ): string {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $participantId, $bucket, $state, $notes): string {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);
            $plan = TransferPlan::query()->where('alliance_id', $allianceId)->whereKey($planId)->sharedLock()->firstOrFail();
            if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
                throw ValidationException::withMessages(['reservation' => 'Capacity reservations can only change while the transfer cycle is Draft or Open.']);
            }
            $participant = TransferParticipant::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_plan_id', $planId)
                ->whereKey($participantId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($participant->withdrawn_at !== null || $participant->direction->value === 'staying') {
                throw ValidationException::withMessages(['reservation' => 'Only active incoming or outgoing transfer participants can reserve capacity.']);
            }
            $targetId = $participant->direction->value === 'incoming'
                ? (string) $plan->home_kingdom_id
                : ($participant->destination_kingdom_id === null ? null : (string) $participant->destination_kingdom_id);
            if ($targetId === null) {
                throw ValidationException::withMessages(['reservation' => 'Set the target Kingdom before reserving capacity.']);
            }

            $existing = TransferCapacityReservation::query()->where('transfer_participant_id', $participantId)->lockForUpdate()->first();
            if ($state->consumesPlannedCapacity()) {
                $condition = TransferKingdomConditionObservation::query()
                    ->where('alliance_id', $allianceId)
                    ->where('transfer_window_id', $plan->transfer_window_id)
                    ->where('kingdom_id', $targetId)
                    ->orderByDesc('observed_at')->orderByDesc('id')->get()
                    ->first(static fn (TransferKingdomConditionObservation $row): bool => $row->source_type->isAuthoritative());
                $capacity = TransferKingdomCapacityObservation::query()
                    ->where('alliance_id', $allianceId)
                    ->where('transfer_window_id', $plan->transfer_window_id)
                    ->where('kingdom_id', $targetId)
                    ->orderByDesc('observed_at')->orderByDesc('id')->get()
                    ->first(static fn (TransferKingdomCapacityObservation $row): bool => $row->source_type->isAuthoritative());
                $official = $condition instanceof TransferKingdomConditionObservation && $condition->classification !== null
                    ? $this->rules->capacity($condition->classification)
                    : null;
                if (! $capacity instanceof TransferKingdomCapacityObservation || $official === null || $capacity->ordinary_invites_used === null || $capacity->transfer_opens_used === null) {
                    throw ValidationException::withMessages(['reservation' => 'Verify authoritative target capacity before reserving an Alliance planning slot.']);
                }
                $activeStates = array_map(
                    static fn (TransferCapacityReservationState $candidate): string => $candidate->value,
                    array_filter(
                        TransferCapacityReservationState::cases(),
                        static fn (TransferCapacityReservationState $candidate): bool => $candidate->consumesPlannedCapacity(),
                    ),
                );
                $otherReservations = TransferCapacityReservation::query()
                    ->where('alliance_id', $allianceId)
                    ->where('transfer_window_id', $plan->transfer_window_id)
                    ->where('target_kingdom_id', $targetId)
                    ->whereIn('state', $activeStates)
                    ->when($existing instanceof TransferCapacityReservation, static fn (Builder $query): Builder => $query->where('id', '!=', (string) $existing->id))
                    ->lockForUpdate()
                    ->get();
                $plannedTotal = $otherReservations->count();
                $plannedBucket = $otherReservations->where('bucket', $bucket)->count();
                $observedTotalUsed = $capacity->ordinary_invites_used + $capacity->transfer_opens_used;
                $bucketCapacity = $bucket === TransferCapacityBucket::OrdinaryInvite ? $official['ordinary_invites'] : $official['transfer_opens'];
                $bucketUsed = $bucket === TransferCapacityBucket::OrdinaryInvite ? $capacity->ordinary_invites_used : $capacity->transfer_opens_used;
                if ($official['total'] - $observedTotalUsed - $plannedTotal <= 0 || $bucketCapacity - $bucketUsed - $plannedBucket <= 0) {
                    throw ValidationException::withMessages(['reservation' => 'No verified transfer capacity remains in the selected planning bucket.']);
                }
            }

            $row = $existing ?? new TransferCapacityReservation;
            $row->fill([
                'alliance_id' => $allianceId,
                'transfer_window_id' => $plan->transfer_window_id,
                'transfer_plan_id' => $planId,
                'transfer_participant_id' => $participantId,
                'target_kingdom_id' => $targetId,
                'bucket' => $bucket,
                'state' => $state,
                'reserved_at' => $state === TransferCapacityReservationState::Reserved ? ($row->reserved_at ?? now()) : $row->reserved_at,
                'released_at' => in_array($state, [TransferCapacityReservationState::Released, TransferCapacityReservationState::Failed], true) ? now() : null,
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
                'reservation_id' => (string) $row->id,
                'bucket' => $bucket->value,
                'state' => $state->value,
            ];
            $this->audit->record('kingdoms.transfer_capacity_reservation_saved', $context->actor, $row, null, $metadata);
            $this->outbox->record('kingdoms.transfer_capacity_reservation_saved', $allianceId, $row, $metadata);

            return (string) $row->id;
        });
    }
}
