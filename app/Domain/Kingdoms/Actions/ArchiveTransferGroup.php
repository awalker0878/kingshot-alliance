<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Kingdoms\Enums\TransferGroupState;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Models\TransferGroup;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveTransferGroup
{
    public function __construct(
        private AllianceMutationAuthority $authority,
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
            $context = $this->authority->require($actor, $alliance, PermissionKey::KingdomManage);

            $plan = TransferPlan::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($planId)
                ->lockForUpdate()
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

            // Keep the transfer-family order plan -> group -> participants. Lock all
            // matching participants deterministically before deciding the group is empty.
            $activeParticipants = TransferParticipant::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->where('transfer_group_id', $group->id)
                ->whereNull('withdrawn_at')
                ->orderBy('id')
                ->lockForUpdate()
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

            $this->audit->record(
                'kingdoms.transfer_group_archived',
                $context->actor,
                $group,
                $context->alliance,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.transfer_group_archived',
                (string) $context->alliance->id,
                $group,
                $metadata,
            );

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
