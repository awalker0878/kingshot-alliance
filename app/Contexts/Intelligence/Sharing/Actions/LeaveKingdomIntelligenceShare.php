<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareState;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShare;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class LeaveKingdomIntelligenceShare
{
    public function __construct(private AllianceIntelligenceWriteState $writeState, private AuditRecorder $audit, private OutboxRecorder $outbox) {}
    public function handle(string $recipientAllianceId,string $actorPlayerId,string $shareId): string
    {
        return DB::transaction(function() use($recipientAllianceId,$actorPlayerId,$shareId): string {
            [, $actor]=$this->writeState->authorize($actorPlayerId,$recipientAllianceId,IntelligencePermission::KingdomManage);
            $share=KingdomIntelligenceShare::query()->whereKey($shareId)->where('recipient_alliance_id',$recipientAllianceId)->lockForUpdate()->firstOrFail();
            if(in_array($share->state,[KingdomIntelligenceShareState::Declined,KingdomIntelligenceShareState::Revoked],true)){return (string) $share->id;}
            if($share->state!==KingdomIntelligenceShareState::Active){abort(404);}
            $share->forceFill(['state'=>KingdomIntelligenceShareState::Declined,'declined_by_player_id'=>$actor->playerId,'declined_at'=>now()])->save();
            $metadata=['share_id'=>(string)$share->id,'source_alliance_id'=>(string)$share->source_alliance_id,'recipient_alliance_id'=>$recipientAllianceId,'kingdom_id'=>(string)$share->kingdom_id,'state'=>$share->state->value,'reason'=>'recipient_left'];
            $event='kingdoms.shared_intelligence_left';
            $this->audit->record($event,$actor,$share,$recipientAllianceId,$metadata);
            $this->outbox->record($event,$recipientAllianceId,$share,$metadata,$event.':'.$share->id.':'.$recipientAllianceId);
            return (string) $share->id;
        });
    }
}
