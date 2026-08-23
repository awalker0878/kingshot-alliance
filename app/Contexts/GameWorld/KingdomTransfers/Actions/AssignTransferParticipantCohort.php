<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCohortState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignTransferParticipantCohort
{
    public function __construct(private TransferWriteState $writeState,private TransferAuthorization $authority,private AuditRecorder $audit,private OutboxRecorder $outbox) {}
    public function handle(string $allianceId,string $actorPlayerId,string $planId,string $participantId,?string $cohortId): void
    {
        DB::transaction(function()use($allianceId,$actorPlayerId,$planId,$participantId,$cohortId):void{$context=$this->writeState->lockAuthority($actorPlayerId,$allianceId);$this->authority->authorizeContext($context,TransferPermission::Manage);$plan=TransferPlan::query()->where('alliance_id',$allianceId)->whereKey($planId)->sharedLock()->firstOrFail();if(!in_array($plan->state,[TransferPlanState::Draft,TransferPlanState::Open],true)||$context->kingdomId()!==(string)$plan->home_kingdom_id)throw ValidationException::withMessages(['participant'=>'This transfer plan is not mutable.']);$id=$cohortId===null?null:trim($cohortId);$id=$id===''?null:$id;$cohort=$id===null?null:TransferCohort::query()->where('alliance_id',$allianceId)->where('transfer_plan_id',$planId)->where('state',TransferCohortState::Active->value)->whereKey($id)->sharedLock()->first();if($id!==null&&!$cohort instanceof TransferCohort)throw ValidationException::withMessages(['transfer_cohort_id'=>'The selected transfer cohort must be active in this plan.']);$participant=TransferParticipant::query()->where('alliance_id',$allianceId)->where('transfer_plan_id',$planId)->whereKey($participantId)->lockForUpdate()->firstOrFail();if($participant->withdrawn_at!==null)throw ValidationException::withMessages(['participant'=>'Withdrawn Governors cannot be moved between cohorts.']);if($cohort instanceof TransferCohort){if($participant->direction===TransferDirection::Staying||$participant->direction!==$cohort->direction)throw ValidationException::withMessages(['transfer_cohort_id'=>'The cohort direction is incompatible with this Governor.']);if($cohort->direction===TransferDirection::Outgoing&&$cohort->destination_kingdom_id!==null&&$participant->destination_kingdom_id!==$cohort->destination_kingdom_id)throw ValidationException::withMessages(['transfer_cohort_id'=>'The cohort destination is incompatible with this Governor.']);}$old=$participant->transfer_cohort_id;$new=$cohort?->id===null?null:(string)$cohort->id;if($old===$new)return;$participant->forceFill(['transfer_cohort_id'=>$new])->save();$metadata=['alliance_id'=>$allianceId,'transfer_plan_id'=>$planId,'transfer_participant_id'=>$participantId,'previous_transfer_cohort_id'=>$old,'transfer_cohort_id'=>$new];$this->audit->record('kingdoms.transfer_participant_cohort_changed',$context->actor,$participant,null,$metadata);$this->outbox->record('kingdoms.transfer_participant_cohort_changed',$allianceId,$participant,$metadata);});
    }
}
