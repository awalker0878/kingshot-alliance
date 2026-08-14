<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Enums\TransferBlockerState;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Enums\TransferReadinessState;
use App\Domain\Kingdoms\Models\TransferBlocker;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Kingdoms\Models\TransferReadinessTransition;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransitionTransferReadiness
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $planId,
        string $participantId,
        TransferReadinessState $target,
    ): TransferParticipant {
        if ($this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage) === false) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $planId, $participantId, $target): TransferParticipant {
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

            $current = $participant->readiness_state;
            if ($current === $target) {
                return $participant->refresh();
            }

            if ($participant->withdrawn_at !== null || $current === TransferReadinessState::Withdrawn) {
                throw ValidationException::withMessages([
                    'readiness' => 'Withdrawn transfer participants cannot change readiness.',
                ]);
            }

            if (! $this->isAllowed($current, $target)) {
                throw ValidationException::withMessages([
                    'readiness' => sprintf(
                        'Readiness cannot transition directly from %s to %s.',
                        $current->value,
                        $target->value,
                    ),
                ]);
            }

            // Blocker mutations take the same participant row lock first, so this
            // count is serialized with blocker creation/resolution without placing
            // FOR UPDATE on a PostgreSQL aggregate query.
            $activeBlockerCount = TransferBlocker::query()
                ->where('alliance_id', $currentAlliance->id)
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
                'alliance_id' => $currentAlliance->id,
                'transfer_plan_id' => $plan->id,
                'transfer_participant_id' => $participant->id,
                'from_state' => $current,
                'to_state' => $target,
                'actor_player_id' => $actor->id,
                'created_at' => now(),
            ]);

            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'transfer_participant_id' => (string) $participant->id,
                'from_state' => $current->value,
                'to_state' => $target->value,
                'active_blocker_count' => $activeBlockerCount,
            ];

            $this->audit->record(
                'kingdoms.transfer_readiness_changed',
                $actor,
                $participant,
                $currentAlliance,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.transfer_readiness_changed',
                (string) $currentAlliance->id,
                $participant,
                $metadata,
            );

            if ($target === TransferReadinessState::Withdrawn) {
                $withdrawMetadata = [
                    'transfer_plan_id' => (string) $plan->id,
                    'transfer_participant_id' => (string) $participant->id,
                    'direction' => $participant->direction->value,
                ];
                $this->audit->record(
                    'kingdoms.transfer_participant_withdrawn',
                    $actor,
                    $participant,
                    $currentAlliance,
                    $withdrawMetadata,
                );
                $this->outbox->record(
                    'kingdoms.transfer_participant_withdrawn',
                    (string) $currentAlliance->id,
                    $participant,
                    $withdrawMetadata,
                );
            }

            return $participant->refresh();
        });
    }

    private function assertMutable(Alliance $alliance, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages([
                'readiness' => 'Readiness can only change while the transfer cycle is Draft or Open.',
            ]);
        }

        if ($alliance->kingdom_id !== $plan->home_kingdom_id) {
            throw ValidationException::withMessages([
                'readiness' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.',
            ]);
        }
    }

    private function isAllowed(TransferReadinessState $from, TransferReadinessState $to): bool
    {
        $allowed = match ($from) {
            TransferReadinessState::NotStarted => [
                TransferReadinessState::Preparing,
                TransferReadinessState::Blocked,
                TransferReadinessState::Withdrawn,
            ],
            TransferReadinessState::Preparing => [
                TransferReadinessState::Ready,
                TransferReadinessState::Blocked,
                TransferReadinessState::Withdrawn,
            ],
            TransferReadinessState::Ready => [
                TransferReadinessState::Preparing,
                TransferReadinessState::Blocked,
                TransferReadinessState::Confirmed,
                TransferReadinessState::Withdrawn,
            ],
            TransferReadinessState::Blocked => [
                TransferReadinessState::Preparing,
                TransferReadinessState::Ready,
                TransferReadinessState::Withdrawn,
            ],
            TransferReadinessState::Confirmed => [
                TransferReadinessState::Ready,
                TransferReadinessState::Blocked,
                TransferReadinessState::Withdrawn,
            ],
            TransferReadinessState::Withdrawn => [],
        };

        return in_array($to, $allowed, true);
    }
}
