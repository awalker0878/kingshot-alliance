<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\TransferGroupState;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\TransferGroup;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveTransferGroup
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        User $actor,
        string $planId,
        string $groupId,
    ): TransferGroup {
        if ($this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage) === false) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $planId, $groupId): TransferGroup {
            $currentAlliance = Alliance::query()
                ->lockForUpdate()
                ->findOrFail($alliance->id);

            $plan = TransferPlan::query()
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->findOrFail($planId);

            $this->assertMutable($currentAlliance, $plan);

            $group = TransferGroup::query()
                ->where('alliance_id', $currentAlliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->lockForUpdate()
                ->findOrFail($groupId);

            if ($group->state === TransferGroupState::Archived) {
                return $group->load(['coordinator.user:id,name,email', 'destinationKingdom:id,number']);
            }

            $activeParticipant = TransferParticipant::query()
                ->where('alliance_id', $currentAlliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->where('transfer_group_id', $group->id)
                ->whereNull('withdrawn_at')
                ->lockForUpdate()
                ->first();

            if ($activeParticipant instanceof TransferParticipant) {
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
                $actor,
                $group,
                $currentAlliance,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.transfer_group_archived',
                (string) $currentAlliance->id,
                $group,
                $metadata,
            );

            return $group->refresh()->load([
                'coordinator.user:id,name,email',
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
                'group' => 'The alliance Kingdom changed after this transfer cycle was created. Cancel the stale cycle before changing groups.',
            ]);
        }
    }
}
