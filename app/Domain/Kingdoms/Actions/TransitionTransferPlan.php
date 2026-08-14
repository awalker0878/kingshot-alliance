<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransitionTransferPlan
{
    public function __construct(
        private AllianceAuthorization $authorization,
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
        if ($this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage) === false) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use (
            $alliance,
            $actor,
            $planId,
            $target,
            $allowedFrom,
            $event,
        ): TransferPlan {
            $currentAlliance = Alliance::query()
                ->lockForUpdate()
                ->findOrFail($alliance->id);
            $lockedActor = Player::query()->lockForUpdate()->findOrFail($actor->id);
            if ($this->authorization->allows($lockedActor, $currentAlliance, PermissionKey::KingdomManage) === false) {
                throw new AuthorizationException;
            }

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

            $this->audit->record($event, $lockedActor, $plan, $currentAlliance, $metadata);
            $this->outbox->record($event, (string) $currentAlliance->id, $plan, $metadata);

            return $plan->refresh()->load('homeKingdom');
        });
    }
}
