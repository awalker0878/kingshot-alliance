<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransitionTransferPlan
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<TransferPlanState> $allowedFrom */
    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $planId,
        TransferPlanState $target,
        array $allowedFrom,
        string $event,
    ): void {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $target, $allowedFrom, $event): void {
            $authority = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->mutations->authorizeContext($authority, TransferPermission::Manage);

            $plan = TransferPlan::query()
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->findOrFail($planId);

            Kingdom::query()->whereKey($plan->home_kingdom_id)->sharedLock()->firstOrFail();

            if ($plan->state === $target) {
                return;
            }

            if (! in_array($plan->state, $allowedFrom, true)) {
                throw ValidationException::withMessages([
                    'plan' => sprintf('A transfer cycle cannot move from %s to %s.', $plan->state->value, $target->value),
                ]);
            }

            if ($authority->kingdomId() !== (string) $plan->home_kingdom_id) {
                throw ValidationException::withMessages([
                    'plan' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.',
                ]);
            }

            if ($target === TransferPlanState::Open) {
                $conflict = TransferPlan::query()
                    ->where('alliance_id', $allianceId)
                    ->where('state', TransferPlanState::Open->value)
                    ->where('id', '<>', $plan->id)
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'plan' => 'This alliance already has an open transfer cycle.',
                    ]);
                }
            }

            if ($target === TransferPlanState::Closed) {
                $incomplete = TransferParticipant::query()
                    ->where('alliance_id', $allianceId)
                    ->where('transfer_plan_id', $plan->id)
                    ->whereNull('withdrawn_at')
                    ->whereDoesntHave('completion')
                    ->exists();

                if ($incomplete) {
                    throw ValidationException::withMessages([
                        'plan' => 'Complete or withdraw every active transfer participant before closing this cycle.',
                    ]);
                }
            }

            $previous = $plan->state;
            $plan->forceFill(['state' => $target])->save();

            $metadata = [
                'alliance_id' => $allianceId,
                'transfer_plan_id' => (string) $plan->id,
                'home_kingdom_id' => (string) $plan->home_kingdom_id,
                'previous_state' => $previous->value,
                'state' => $target->value,
            ];

            $this->audit->record($event, $authority->actor, $plan, null, $metadata);
            $this->outbox->record($event, $allianceId, $plan, $metadata);
        });
    }
}
