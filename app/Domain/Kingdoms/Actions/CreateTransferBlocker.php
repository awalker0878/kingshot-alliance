<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\TransferBlockerState;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\TransferBlocker;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateTransferBlocker
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
        string $summary,
        ?string $details = null,
    ): TransferBlocker {
        if ($this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage) === false) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $planId, $participantId, $summary, $details): TransferBlocker {
            $currentAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
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
                    'blocker' => 'Withdrawn transfer participants cannot receive new blockers.',
                ]);
            }

            $summary = trim($summary);
            if ($summary === '') {
                throw ValidationException::withMessages([
                    'summary' => 'A blocker summary is required.',
                ]);
            }

            $details = $details === null ? null : trim($details);
            if ($details === '') {
                $details = null;
            }

            $blocker = TransferBlocker::query()->create([
                'alliance_id' => $currentAlliance->id,
                'transfer_plan_id' => $plan->id,
                'transfer_participant_id' => $participant->id,
                'state' => TransferBlockerState::Active,
                'summary' => $summary,
                'details' => $details,
                'created_by_user_id' => $actor->id,
            ]);

            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'transfer_participant_id' => (string) $participant->id,
                'transfer_blocker_id' => (string) $blocker->id,
                'state' => $blocker->state->value,
            ];

            $this->audit->record(
                'kingdoms.transfer_blocker_created',
                $actor,
                $blocker,
                $currentAlliance,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.transfer_blocker_created',
                (string) $currentAlliance->id,
                $blocker,
                $metadata,
            );

            return $blocker->refresh()->load('createdBy:id,name');
        });
    }

    private function assertMutable(Alliance $alliance, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages([
                'blocker' => 'Blockers can only change while the transfer cycle is Draft or Open.',
            ]);
        }

        if ($alliance->kingdom_id !== $plan->home_kingdom_id) {
            throw ValidationException::withMessages([
                'blocker' => 'The alliance Kingdom changed after this transfer cycle was created. Cancel the stale cycle before changing blockers.',
            ]);
        }
    }
}
