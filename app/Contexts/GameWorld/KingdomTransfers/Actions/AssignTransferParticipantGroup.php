<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferGroupState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignTransferParticipantGroup
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $planId, string $participantId, ?string $groupId): void
    {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $participantId, $groupId): void {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);
            $plan = TransferPlan::query()->where('alliance_id', $allianceId)->whereKey($planId)->sharedLock()->firstOrFail();
            $this->assertMutable($context->kingdomId(), $plan);

            $normalizedGroupId = $groupId === null ? null : trim($groupId);
            $normalizedGroupId = $normalizedGroupId === '' ? null : $normalizedGroupId;
            $group = $normalizedGroupId === null ? null : TransferGroup::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_plan_id', $plan->id)
                ->where('state', TransferGroupState::Active->value)
                ->whereKey($normalizedGroupId)
                ->sharedLock()
                ->first();

            if ($normalizedGroupId !== null && ! $group instanceof TransferGroup) {
                throw ValidationException::withMessages(['transfer_group_id' => 'The selected transfer group must be active in this transfer cycle.']);
            }

            $participant = TransferParticipant::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_plan_id', $plan->id)
                ->whereKey($participantId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($participant->withdrawn_at !== null) {
                throw ValidationException::withMessages(['participant' => 'Withdrawn transfer participants cannot be moved between groups.']);
            }
            if ($group instanceof TransferGroup) {
                $this->assertCompatible($participant, $group);
            }

            $oldGroupId = $participant->transfer_group_id;
            $newGroupId = $group === null ? null : (string) $group->id;
            if ($oldGroupId === $newGroupId) {
                return;
            }

            $participant->forceFill(['transfer_group_id' => $newGroupId])->save();
            $metadata = [
                'alliance_id' => $allianceId,
                'transfer_plan_id' => (string) $plan->id,
                'transfer_participant_id' => (string) $participant->id,
                'previous_transfer_group_id' => $oldGroupId,
                'transfer_group_id' => $newGroupId,
            ];
            $this->audit->record('kingdoms.transfer_participant_group_changed', $context->actor, $participant, null, $metadata);
            $this->outbox->record('kingdoms.transfer_participant_group_changed', $allianceId, $participant, $metadata);
        });
    }

    private function assertCompatible(TransferParticipant $participant, TransferGroup $group): void
    {
        if ($participant->direction === TransferDirection::Staying || $participant->direction !== $group->direction) {
            throw ValidationException::withMessages(['transfer_group_id' => 'The selected group direction is incompatible with this participant.']);
        }
        if ($group->direction === TransferDirection::Outgoing
            && $group->destination_kingdom_id !== null
            && $participant->destination_kingdom_id !== $group->destination_kingdom_id) {
            throw ValidationException::withMessages(['transfer_group_id' => 'The selected group destination is incompatible with this outgoing participant.']);
        }
    }

    private function assertMutable(string $allianceKingdomId, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages(['participant' => 'Transfer participants can only be changed while the transfer cycle is Draft or Open.']);
        }
        if ($allianceKingdomId !== (string) $plan->home_kingdom_id) {
            throw ValidationException::withMessages(['participant' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.']);
        }
    }
}
