<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\TransferGroup;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignTransferParticipantGroup
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
        string $participantId,
        ?string $groupId,
    ): TransferParticipant {
        if ($this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage) === false) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $planId, $participantId, $groupId): TransferParticipant {
            $currentAlliance = Alliance::query()
                ->lockForUpdate()
                ->findOrFail($alliance->id);

            $plan = TransferPlan::query()
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->findOrFail($planId);

            $this->assertMutable($currentAlliance, $plan);

            $participant = TransferParticipant::query()
                ->where('alliance_id', $currentAlliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->lockForUpdate()
                ->findOrFail($participantId);

            if ($participant->withdrawn_at !== null) {
                throw ValidationException::withMessages([
                    'participant' => 'Withdrawn transfer participants cannot be moved between groups.',
                ]);
            }

            $groupId = $groupId === null ? null : trim($groupId);
            if ($groupId === '') {
                $groupId = null;
            }

            $group = null;
            if ($groupId !== null) {
                $group = TransferGroup::query()
                    ->where('alliance_id', $currentAlliance->id)
                    ->where('transfer_plan_id', $plan->id)
                    ->whereNull('archived_at')
                    ->lockForUpdate()
                    ->find($groupId);

                if (! $group instanceof TransferGroup) {
                    throw ValidationException::withMessages([
                        'transfer_group_id' => 'The selected transfer group must be active in this transfer cycle.',
                    ]);
                }
            }

            $oldGroupId = $participant->transfer_group_id;
            $newGroupId = $group === null ? null : (string) $group->id;

            if ($oldGroupId === $newGroupId) {
                return $participant->load('group.coordinators.user:id,name,email');
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
                $actor,
                $participant,
                $currentAlliance,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.transfer_participant_group_changed',
                (string) $currentAlliance->id,
                $participant,
                $metadata,
            );

            return $participant->refresh()->load('group.coordinators.user:id,name,email');
        });
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
                'participant' => 'The alliance Kingdom changed after this transfer cycle was created. Cancel the stale cycle before changing participant groups.',
            ]);
        }
    }
}
