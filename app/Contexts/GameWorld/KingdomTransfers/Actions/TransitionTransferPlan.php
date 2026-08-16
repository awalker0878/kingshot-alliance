<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransitionTransferPlan
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private TransferAuthorization $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  list<TransferPlanState>  $allowedFrom
     */
    public function handle(
        Alliance $alliance,
        Player $actor,
        string $planId,
        TransferPlanState $target,
        array $allowedFrom,
        string $event,
    ): TransferPlan {
        return DB::transaction(function () use (
            $alliance,
            $actor,
            $planId,
            $target,
            $allowedFrom,
            $event,
        ): TransferPlan {
            // Opening enforces the Alliance-wide singleton-open-plan invariant.
            $authority = $target === TransferPlanState::Open
                ? $this->allianceWriteState->lockExclusiveScope($actor, $alliance)
                : $this->allianceWriteState->lockActiveScope($actor, $alliance);
            $this->mutations->authorizeContext($authority, TransferPermission::Manage);
            $currentAlliance = $authority->alliance;
            $currentActor = $authority->actor;

            $plan = TransferPlan::query()
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->findOrFail($planId);

            Kingdom::query()->findOrFail($plan->home_kingdom_id);

            if ($plan->state === $target) {
                return $plan->load('homeKingdom');
            }

            if (in_array($plan->state, $allowedFrom, true) === false) {
                throw ValidationException::withMessages([
                    'plan' => sprintf(
                        'A transfer cycle cannot move from %s to %s.',
                        $plan->state->value,
                        $target->value,
                    ),
                ]);
            }

            if ($currentAlliance->kingdom_id !== $plan->home_kingdom_id) {
                throw ValidationException::withMessages([
                    'plan' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.',
                ]);
            }

            if ($target === TransferPlanState::Open) {
                $conflict = TransferPlan::query()
                    ->where('alliance_id', $currentAlliance->id)
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
                    ->where('alliance_id', $currentAlliance->id)
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
                'transfer_plan_id' => (string) $plan->id,
                'home_kingdom_id' => (string) $plan->home_kingdom_id,
                'previous_state' => $previous->value,
                'state' => $target->value,
            ];

            $this->audit->record($event, $currentActor, $plan, $currentAlliance, $metadata);
            $this->outbox->record($event, (string) $currentAlliance->id, $plan, $metadata);

            return $plan->refresh()->load('homeKingdom');
        });
    }
}
