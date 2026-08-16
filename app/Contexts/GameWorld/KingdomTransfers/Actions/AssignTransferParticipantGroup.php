<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferGroupState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignTransferParticipantGroup
{
    public function __construct(
        private TransferAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $planId,
        string $participantId,
        ?string $groupId,
    ): TransferParticipant {
        return DB::transaction(function () use ($alliance, $actor, $planId, $participantId, $groupId): TransferParticipant {
            $context = $this->authority->require($actor, $alliance, TransferPermission::Manage);

            $plan = TransferPlan::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($planId)
                ->sharedLock()
                ->firstOrFail();

            $this->assertMutable($context->alliance, $plan);

            $groupId = $groupId === null ? null : trim($groupId);
            if ($groupId === '') {
                $groupId = null;
            }

            // The destination group is read-only compatibility state for this action,
            // so share-lock it before the participant. Group edits/archives are exclusive.
            $group = $groupId === null
                ? null
                : TransferGroup::query()
                    ->where('alliance_id', $context->alliance->id)
                    ->where('transfer_plan_id', $plan->id)
                    ->where('state', TransferGroupState::Active->value)
                    ->whereKey($groupId)
                    ->sharedLock()
                    ->first();

            if ($groupId !== null && ! $group instanceof TransferGroup) {
                throw ValidationException::withMessages([
                    'transfer_group_id' => 'The selected transfer group must be active in this transfer cycle.',
                ]);
            }

            $participant = TransferParticipant::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->whereKey($participantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($participant->withdrawn_at !== null) {
                throw ValidationException::withMessages([
                    'participant' => 'Withdrawn transfer participants cannot be moved between groups.',
                ]);
            }

            if ($group instanceof TransferGroup) {
                $this->assertCompatible($participant, $group);
            }

            $oldGroupId = $participant->transfer_group_id;
            $newGroupId = $group === null ? null : (string) $group->id;

            if ($oldGroupId === $newGroupId) {
                return $participant->load([
                    'group.coordinator:id,current_name',
                    'group.destinationKingdom:id,number',
                ]);
            }

            $participant->forceFill(['transfer_group_id' => $newGroupId])->save();

            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'transfer_participant_id' => (string) $participant->id,
                'previous_transfer_group_id' => $oldGroupId,
                'transfer_group_id' => $newGroupId,
            ];

            $this->audit->record(
                'kingdoms.transfer_participant_group_changed',
                $context->actor,
                $participant,
                $context->alliance,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.transfer_participant_group_changed',
                (string) $context->alliance->id,
                $participant,
                $metadata,
            );

            return $participant->refresh()->load([
                'group.coordinator:id,current_name',
                'group.destinationKingdom:id,number',
            ]);
        });
    }

    private function assertCompatible(TransferParticipant $participant, TransferGroup $group): void
    {
        if ($participant->direction === TransferDirection::Staying) {
            throw ValidationException::withMessages([
                'transfer_group_id' => 'Staying participants cannot be assigned to moving transfer groups.',
            ]);
        }

        if ($participant->direction !== $group->direction) {
            throw ValidationException::withMessages([
                'transfer_group_id' => 'The participant direction must match the transfer group direction.',
            ]);
        }

        if ($group->direction === TransferDirection::Outgoing
            && $group->destination_kingdom_id !== null
            && $participant->destination_kingdom_id !== $group->destination_kingdom_id) {
            throw ValidationException::withMessages([
                'transfer_group_id' => 'The outgoing participant destination must match the transfer group destination.',
            ]);
        }
    }

    private function assertMutable(Alliance $alliance, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages([
                'participant' => 'Participant groups can only be changed while the transfer cycle is Draft or Open.',
            ]);
        }

        if ($alliance->kingdom_id !== $plan->home_kingdom_id) {
            throw ValidationException::withMessages([
                'participant' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.',
            ]);
        }
    }
}
