<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use App\Workflows\KingdomTransfer\Access\Enums\TransferPermission;
use App\Workflows\KingdomTransfer\Access\Services\TransferMutationAuthority;
use App\Workflows\KingdomTransfer\Enums\TransferGroupState;
use App\Workflows\KingdomTransfer\Enums\TransferPlanState;
use App\Workflows\KingdomTransfer\Models\TransferGroup;
use App\Workflows\KingdomTransfer\Models\TransferParticipant;
use App\Workflows\KingdomTransfer\Models\TransferPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveTransferGroup
{
    public function __construct(
        private TransferMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $planId,
        string $groupId,
    ): TransferGroup {
        return DB::transaction(function () use ($alliance, $actor, $planId, $groupId): TransferGroup {
            $context = $this->authority->require($actor, $alliance, TransferPermission::Manage);

            $plan = TransferPlan::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($planId)
                ->sharedLock()
                ->firstOrFail();

            $this->assertMutable($context->alliance, $plan);

            $group = TransferGroup::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->whereKey($groupId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($group->state === TransferGroupState::Archived) {
                return $group->load(['coordinator:id,current_name', 'destinationKingdom:id,number']);
            }

            // Participant writers take exclusive participant locks; shared locks here
            // are enough to keep the membership of this group stable during the empty check.
            $activeParticipants = TransferParticipant::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->where('transfer_group_id', $group->id)
                ->whereNull('withdrawn_at')
                ->orderBy('id')
                ->sharedLock()
                ->get(['id']);

            if ($activeParticipants->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'group' => 'Unassign or move active participants before archiving this transfer group.',
                ]);
            }

            $group->forceFill(['state' => TransferGroupState::Archived])->save();

            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'transfer_group_id' => (string) $group->id,
                'direction' => $group->direction->value,
                'destination_kingdom_id' => $group->destination_kingdom_id,
            ];

            $this->audit->record('kingdoms.transfer_group_archived', $context->actor, $group, $context->alliance, $metadata);
            $this->outbox->record('kingdoms.transfer_group_archived', (string) $context->alliance->id, $group, $metadata);

            return $group->refresh()->load([
                'coordinator:id,current_name',
                'destinationKingdom:id,number',
            ]);
        });
    }

    private function assertMutable(Alliance $alliance, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages([
                'group' => 'Transfer groups can only be archived while the transfer cycle is Draft or Open.',
            ]);
        }

        if ($alliance->kingdom_id !== $plan->home_kingdom_id) {
            throw ValidationException::withMessages([
                'group' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.',
            ]);
        }
    }
}
