<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferBlockerState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateTransferBlocker
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $planId,
        string $participantId,
        string $summary,
        ?string $details = null,
    ): void {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $participantId, $summary, $details): void {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);

            $plan = TransferPlan::query()
                ->where('alliance_id', $allianceId)
                ->whereKey($planId)
                ->sharedLock()
                ->firstOrFail();
            $this->assertMutable($context->kingdomId(), $plan);

            $participant = TransferParticipant::query()
                ->where('alliance_id', $allianceId)
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
                throw ValidationException::withMessages(['summary' => 'A blocker summary is required.']);
            }
            $details = $details === null ? null : trim($details);
            $details = $details === '' ? null : $details;

            $blocker = TransferBlocker::query()->create([
                'alliance_id' => $allianceId,
                'transfer_plan_id' => $plan->id,
                'transfer_participant_id' => $participant->id,
                'state' => TransferBlockerState::Active,
                'summary' => $summary,
                'details' => $details,
                'created_by_player_id' => $context->actor->playerId,
            ]);

            $metadata = [
                'alliance_id' => $allianceId,
                'transfer_plan_id' => (string) $plan->id,
                'transfer_participant_id' => (string) $participant->id,
                'transfer_blocker_id' => (string) $blocker->id,
                'state' => $blocker->state->value,
            ];

            $this->audit->record('kingdoms.transfer_blocker_created', $context->actor, $blocker, null, $metadata);
            $this->outbox->record('kingdoms.transfer_blocker_created', $allianceId, $blocker, $metadata);
        });
    }

    private function assertMutable(string $allianceKingdomId, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages(['blocker' => 'Blockers can only change while the transfer cycle is Draft or Open.']);
        }
        if ($allianceKingdomId !== (string) $plan->home_kingdom_id) {
            throw ValidationException::withMessages(['blocker' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.']);
        }
    }
}
