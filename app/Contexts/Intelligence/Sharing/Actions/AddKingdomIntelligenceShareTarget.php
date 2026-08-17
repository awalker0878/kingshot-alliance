<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Actions;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareState;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareTargetState;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShare;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShareTarget;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AddKingdomIntelligenceShareTarget
{
    public function __construct(private AllianceIntelligenceWriteState $writeState,private AllianceReferenceQuery $alliances,private AuditRecorder $audit,private OutboxRecorder $outbox) {}
    public function handle(string $sourceAllianceId,string $actorPlayerId,string $shareId,string $trackingId): string
    {
        return DB::transaction(function() use($sourceAllianceId,$actorPlayerId,$shareId,$trackingId): string {
            [$scope,$actor]=$this->writeState->authorize($actorPlayerId,$sourceAllianceId,IntelligencePermission::KingdomManage);
            $share=KingdomIntelligenceShare::query()->whereKey($shareId)->where('source_alliance_id',$sourceAllianceId)->where('state',KingdomIntelligenceShareState::Active->value)->lockForUpdate()->firstOrFail();
            if($share->recipient_alliance_id===null){throw ValidationException::withMessages(['sharing'=>'Only an active sharing agreement with a recipient can receive shared targets.']);}
            $recipientId=(string)$share->recipient_alliance_id;
            $recipient=$this->alliances->lockCurrent($recipientId);
            if(!$recipient->active() || $scope->kingdomId!==(string)$share->kingdom_id || $recipient->kingdomId!==(string)$share->kingdom_id){throw ValidationException::withMessages(['sharing'=>'Both alliances must be active in the captured Kingdom before a target can be shared.']);}
            $tracking=TrackedKingdomAlliance::query()->whereKey($trackingId)->where('alliance_id',$sourceAllianceId)->lockForUpdate()->firstOrFail();
            if($tracking->state!==TrackedKingdomAllianceState::Active || (string)$tracking->kingdom_id!==(string)$share->kingdom_id){throw ValidationException::withMessages(['tracking'=>'Only an actively tracked game alliance in the captured Kingdom can be shared.']);}
            $target=KingdomIntelligenceShareTarget::query()->where('kingdom_intelligence_share_id',$share->id)->where('tracked_kingdom_alliance_id',$tracking->id)->lockForUpdate()->first();
            if($target instanceof KingdomIntelligenceShareTarget && $target->state===KingdomIntelligenceShareTargetState::Active){return (string) $target->id;}
            $sharedAt=now();
            if($target instanceof KingdomIntelligenceShareTarget){$target->forceFill(['state'=>KingdomIntelligenceShareTargetState::Active,'shared_by_player_id'=>$actor->playerId,'removed_by_player_id'=>null,'shared_at'=>$sharedAt,'removed_at'=>null])->save();}
            else{$target=KingdomIntelligenceShareTarget::query()->create(['kingdom_intelligence_share_id'=>$share->id,'tracked_kingdom_alliance_id'=>$tracking->id,'state'=>KingdomIntelligenceShareTargetState::Active,'shared_by_player_id'=>$actor->playerId,'shared_at'=>$sharedAt]);}
            $metadata=$this->metadata($share,$target);
            $this->recordForAlliance($sourceAllianceId,$actor,$target,$metadata,$sharedAt);
            $this->recordForAlliance($recipientId,null,$target,$metadata,$sharedAt);
            return (string) $target->id;
        });
    }
    /** @return array<string,mixed> */
    private function metadata(KingdomIntelligenceShare $share,KingdomIntelligenceShareTarget $target): array {return ['share_target_id'=>(string)$target->id,'share_id'=>(string)$share->id,'source_alliance_id'=>(string)$share->source_alliance_id,'recipient_alliance_id'=>(string)$share->recipient_alliance_id,'kingdom_id'=>(string)$share->kingdom_id,'state'=>$target->state->value];}
    /** @param array<string,mixed> $metadata */
    private function recordForAlliance(string $allianceId,?PlayerReference $actor,KingdomIntelligenceShareTarget $target,array $metadata,\DateTimeInterface $occurredAt): void {$event='kingdoms.shared_intelligence_target_shared';$this->audit->record($event,$actor,$target,$allianceId,$metadata);$this->outbox->record($event,$allianceId,$target,$metadata,$event.':'.$target->id.':'.$allianceId.':'.$occurredAt->format('YmdHis.u'));}
}
