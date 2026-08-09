<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class WithdrawTransferParticipant
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
    ): TransferParticipant {
        if ($this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage) === false) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $planId, $participantId): TransferParticipant {
            $currentAlliance = Alliance::query()
                ->lockForUpdate()
                ->findOrFail($alliance->id);

            $plan = TransferPlan::query()
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->findOrFail($planId);

            if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
                throw ValidationException::withMessages([
                    'participant' => 'Participants can only be withdrawn while the transfer cycle is Draft or Open.',
                ]);
            }

            if ($currentAlliance->kingdom_id !== $plan->home_kingdom_id) {
                throw ValidationException::withMessages([
                    'participant' => 'The alliance Kingdom changed after this transfer cycle was created. Cancel the stale cycle before changing participants.',
                ]);
            }

            $participant = TransferParticipant::query()
                ->where('alliance_id', $currentAlliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->lockForUpdate()
                ->findOrFail($participantId);

            if ($participant->withdrawn_at !== null) {
                return $participant;
            }

            $participant->forceFill(['withdrawn_at' => now()])->save();

            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'transfer_participant_id' => (string) $participant->id,
                'direction' => $participant->direction->value,
            ];

            $this->audit->record(
                'kingdoms.transfer_participant_withdrawn',
                $actor,
                $participant,
                $currentAlliance,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.transfer_participant_withdrawn',
                (string) $currentAlliance->id,
                $participant,
                $metadata,
            );

            return $participant->refresh();
        });
    }
}
