<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferBlockerState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferReadinessTransition;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransitionTransferReadiness
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
        TransferReadinessState $target,
    ): void {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $participantId, $target): void {
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
                ->lockForUpdate()
                ->firstOrFail();

            $current = $participant->readiness_state;
            if ($current === $target) {
                return;
            }

            if ($participant->withdrawn_at !== null || $current === TransferReadinessState::Withdrawn) {
                throw ValidationException::withMessages([
                    'readiness' => 'Withdrawn transfer participants cannot change readiness.',
                ]);
            }

            if (! $this->isAllowed($current, $target)) {
                throw ValidationException::withMessages([
                    'readiness' => sprintf('Readiness cannot transition directly from %s to %s.', $current->value, $target->value),
                ]);
            }

            $activeBlockerCount = TransferBlocker::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_plan_id', $plan->id)
                ->where('transfer_participant_id', $participant->id)
                ->where('state', TransferBlockerState::Active->value)
                ->count();

            if ($target === TransferReadinessState::Blocked && $activeBlockerCount === 0) {
                throw ValidationException::withMessages([
                    'readiness' => 'Add an active blocker before marking a participant blocked.',
                ]);
            }

            if ($current === TransferReadinessState::Blocked
                && $target !== TransferReadinessState::Withdrawn
                && $activeBlockerCount > 0) {
                throw ValidationException::withMessages([
                    'readiness' => 'Resolve all active blockers before leaving the blocked state.',
                ]);
            }

            if (in_array($target, [TransferReadinessState::Ready, TransferReadinessState::Confirmed], true)
                && $activeBlockerCount > 0) {
                throw ValidationException::withMessages([
                    'readiness' => 'Ready or confirmed participants cannot have active blockers.',
                ]);
            }

            $participant->forceFill([
                'readiness_state' => $target,
                'withdrawn_at' => $target === TransferReadinessState::Withdrawn ? now() : null,
            ])->save();

            TransferReadinessTransition::query()->create([
                'alliance_id' => $allianceId,
                'transfer_plan_id' => $plan->id,
                'transfer_participant_id' => $participant->id,
                'from_state' => $current,
                'to_state' => $target,
                'actor_player_id' => $context->actor->playerId,
                'created_at' => now(),
            ]);

            $metadata = [
                'alliance_id' => $allianceId,
                'transfer_plan_id' => (string) $plan->id,
                'transfer_participant_id' => (string) $participant->id,
                'from_state' => $current->value,
                'to_state' => $target->value,
                'active_blocker_count' => $activeBlockerCount,
            ];

            $this->audit->record('kingdoms.transfer_readiness_changed', $context->actor, $participant, null, $metadata);
            $this->outbox->record('kingdoms.transfer_readiness_changed', $allianceId, $participant, $metadata);

            if ($target === TransferReadinessState::Withdrawn) {
                $withdrawMetadata = [
                    'alliance_id' => $allianceId,
                    'transfer_plan_id' => (string) $plan->id,
                    'transfer_participant_id' => (string) $participant->id,
                    'direction' => $participant->direction->value,
                ];
                $this->audit->record('kingdoms.transfer_participant_withdrawn', $context->actor, $participant, null, $withdrawMetadata);
                $this->outbox->record('kingdoms.transfer_participant_withdrawn', $allianceId, $participant, $withdrawMetadata);
            }
        });
    }

    private function assertMutable(string $allianceKingdomId, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages([
                'readiness' => 'Readiness can only change while the transfer cycle is Draft or Open.',
            ]);
        }

        if ($allianceKingdomId !== (string) $plan->home_kingdom_id) {
            throw ValidationException::withMessages([
                'readiness' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.',
            ]);
        }
    }

    private function isAllowed(TransferReadinessState $from, TransferReadinessState $to): bool
    {
        $allowed = match ($from) {
            TransferReadinessState::NotStarted => [TransferReadinessState::Preparing, TransferReadinessState::Blocked, TransferReadinessState::Withdrawn],
            TransferReadinessState::Preparing => [TransferReadinessState::Ready, TransferReadinessState::Blocked, TransferReadinessState::Withdrawn],
            TransferReadinessState::Ready => [TransferReadinessState::Preparing, TransferReadinessState::Blocked, TransferReadinessState::Confirmed, TransferReadinessState::Withdrawn],
            TransferReadinessState::Blocked => [TransferReadinessState::Preparing, TransferReadinessState::Ready, TransferReadinessState::Withdrawn],
            TransferReadinessState::Confirmed => [TransferReadinessState::Ready, TransferReadinessState::Blocked, TransferReadinessState::Withdrawn],
            TransferReadinessState::Withdrawn => [],
        };

        return in_array($to, $allowed, true);
    }
}
