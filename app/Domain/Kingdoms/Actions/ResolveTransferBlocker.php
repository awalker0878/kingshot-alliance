<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Kingdoms\Enums\TransferBlockerState;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Models\TransferBlocker;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ResolveTransferBlocker
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
        string $participantId,
        string $blockerId,
    ): TransferBlocker {
        return DB::transaction(function () use ($alliance, $actor, $planId, $participantId, $blockerId): TransferBlocker {
            $context = $this->authority->require($actor, $alliance, PermissionKey::KingdomManage);

            $plan = TransferPlan::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($planId)
                ->sharedLock()
                ->firstOrFail();

            $this->assertMutable($context->alliance, $plan);

            // Existing blockers remain resolvable after participant withdrawal so
            // manual blocker history can be closed cleanly. New blockers are still
            // forbidden for withdrawn participants by CreateTransferBlocker.
            $participant = TransferParticipant::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->whereKey($participantId)
                ->sharedLock()
                ->firstOrFail();

            $blocker = TransferBlocker::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->where('transfer_participant_id', $participant->id)
                ->whereKey($blockerId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($blocker->state === TransferBlockerState::Resolved) {
                return $blocker->refresh();
            }

            $blocker->forceFill([
                'state' => TransferBlockerState::Resolved,
                'resolved_by_player_id' => $context->actor->id,
                'resolved_at' => now(),
            ])->save();

            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'transfer_participant_id' => (string) $participant->id,
                'transfer_blocker_id' => (string) $blocker->id,
                'state' => $blocker->state->value,
            ];

            $this->audit->record('kingdoms.transfer_blocker_resolved', $context->actor, $blocker, $context->alliance, $metadata);
            $this->outbox->record('kingdoms.transfer_blocker_resolved', (string) $context->alliance->id, $blocker, $metadata);

            return $blocker->refresh()->load(['createdBy:id,current_name', 'resolvedBy:id,current_name']);
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
                'blocker' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.',
            ]);
        }
    }
}
