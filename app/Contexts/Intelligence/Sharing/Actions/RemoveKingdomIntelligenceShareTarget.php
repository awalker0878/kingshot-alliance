<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Actions;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareTargetState;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShare;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShareTarget;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RemoveKingdomIntelligenceShareTarget
{
    public function __construct(private AllianceIntelligenceWriteState $writeState,private AuditRecorder $audit,private OutboxRecorder $outbox) {}
    public function handle(string $sourceAllianceId,string $actorPlayerId,string $shareId,string $targetId): string
    {
        return DB::transaction(function() use($sourceAllianceId,$actorPlayerId,$shareId,$targetId): string {
            [, $actor]=$this->writeState->authorize($actorPlayerId,$sourceAllianceId,IntelligencePermission::KingdomManage);
            $share=KingdomIntelligenceShare::query()->whereKey($shareId)->where('source_alliance_id',$sourceAllianceId)->lockForUpdate()->firstOrFail();
            $target=KingdomIntelligenceShareTarget::query()->whereKey($targetId)->where('kingdom_intelligence_share_id',$share->id)->lockForUpdate()->firstOrFail();
            if($target->state===KingdomIntelligenceShareTargetState::Removed){return (string) $target->id;}
            $removedAt=now();
            $target->forceFill(['state'=>KingdomIntelligenceShareTargetState::Removed,'removed_by_player_id'=>$actor->playerId,'removed_at'=>$removedAt])->save();
            $metadata=['share_target_id'=>(string)$target->id,'share_id'=>(string)$share->id,'source_alliance_id'=>(string)$share->source_alliance_id,'recipient_alliance_id'=>$share->recipient_alliance_id===null?null:(string)$share->recipient_alliance_id,'kingdom_id'=>(string)$share->kingdom_id,'state'=>$target->state->value];
            $this->recordForAlliance($sourceAllianceId,$actor,$target,$metadata,$removedAt);
            if($share->recipient_alliance_id!==null){$this->recordForAlliance((string)$share->recipient_alliance_id,null,$target,$metadata,$removedAt);}
            return (string) $target->id;
        });
    }
    /** @param array<string,mixed> $metadata */
    private function recordForAlliance(string $allianceId,?PlayerReference $actor,KingdomIntelligenceShareTarget $target,array $metadata,\DateTimeInterface $occurredAt): void {$event='kingdoms.shared_intelligence_target_removed';$this->audit->record($event,$actor,$target,$allianceId,$metadata);$this->outbox->record($event,$allianceId,$target,$metadata,$event.':'.$target->id.':'.$allianceId.':'.$occurredAt->format('YmdHis.u'));}
}
