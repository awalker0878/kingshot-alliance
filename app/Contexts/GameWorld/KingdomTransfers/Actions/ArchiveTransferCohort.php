<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCohortState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveTransferCohort
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $planId, string $cohortId): void
    {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $cohortId): void {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);

            $plan = TransferPlan::query()
                ->where('alliance_id', $allianceId)
                ->whereKey($planId)
                ->sharedLock()
                ->firstOrFail();

            if (
                ! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)
                || $context->kingdomId() !== (string) $plan->home_kingdom_id
            ) {
                throw ValidationException::withMessages([
                    'cohort' => 'This transfer cohort is not mutable.',
                ]);
            }

            $cohort = TransferCohort::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_plan_id', $planId)
                ->whereKey($cohortId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cohort->state === TransferCohortState::Archived) {
                return;
            }

            if (
                TransferParticipant::query()
                    ->where('alliance_id', $allianceId)
                    ->where('transfer_plan_id', $planId)
                    ->where('transfer_cohort_id', $cohortId)
                    ->whereNull('withdrawn_at')
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'cohort' => 'Unassign or move active Governors before archiving this transfer cohort.',
                ]);
            }

            $cohort->forceFill(['state' => TransferCohortState::Archived])->save();

            $metadata = [
                'alliance_id' => $allianceId,
                'transfer_plan_id' => $planId,
                'transfer_cohort_id' => $cohortId,
            ];
            $this->audit->record(
                'kingdoms.transfer_cohort_archived',
                $context->actor,
                $cohort,
                null,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.transfer_cohort_archived',
                $allianceId,
                $cohort,
                $metadata,
            );
        });
    }
}
