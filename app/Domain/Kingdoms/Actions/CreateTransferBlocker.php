<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Domain\Kingdoms\Enums\TransferBlockerState;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\TransferBlocker;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateTransferBlocker
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
        string $summary,
        ?string $details = null,
    ): TransferBlocker {
        return DB::transaction(function () use ($alliance, $actor, $planId, $participantId, $summary, $details): TransferBlocker {
            $context = $this->authority->require($actor, $alliance, IntelligencePermission::KingdomManage);

            $plan = TransferPlan::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($planId)
                ->sharedLock()
                ->firstOrFail();

            $this->assertMutable($context->alliance, $plan);

            // Blocker creation only depends on participant eligibility; a shared
            // participant lock prevents withdrawal/completion while allowing other reads.
            $participant = TransferParticipant::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('transfer_plan_id', $plan->id)
                ->whereKey($participantId)
                ->sharedLock()
                ->firstOrFail();

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
                'alliance_id' => $context->alliance->id,
                'transfer_plan_id' => $plan->id,
                'transfer_participant_id' => $participant->id,
                'state' => TransferBlockerState::Active,
                'summary' => $summary,
                'details' => $details,
                'created_by_player_id' => $context->actor->id,
            ]);

            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'transfer_participant_id' => (string) $participant->id,
                'transfer_blocker_id' => (string) $blocker->id,
                'state' => $blocker->state->value,
            ];

            $this->audit->record('kingdoms.transfer_blocker_created', $context->actor, $blocker, $context->alliance, $metadata);
            $this->outbox->record('kingdoms.transfer_blocker_created', (string) $context->alliance->id, $blocker, $metadata);

            return $blocker->refresh()->load('createdBy:id,current_name');
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
