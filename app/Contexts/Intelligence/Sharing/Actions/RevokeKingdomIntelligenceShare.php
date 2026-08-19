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
use Illuminate\Validation\ValidationException;

final readonly class RevokeKingdomIntelligenceShare
{
    public function __construct(private AllianceIntelligenceWriteState $writeState, private AuditRecorder $audit, private OutboxRecorder $outbox) {}

    public function handle(string $sourceAllianceId, string $actorPlayerId, string $shareId): string
    {
        return DB::transaction(function () use ($sourceAllianceId, $actorPlayerId, $shareId): string {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $sourceAllianceId, IntelligencePermission::KingdomManage);
            $share = KingdomIntelligenceShare::query()->whereKey($shareId)->where('source_alliance_id', $sourceAllianceId)->lockForUpdate()->firstOrFail();
            if (in_array($share->state, [KingdomIntelligenceShareState::Revoked, KingdomIntelligenceShareState::Declined], true)) {
                return (string) $share->id;
            }
            if (! in_array($share->state, [KingdomIntelligenceShareState::Pending, KingdomIntelligenceShareState::Active], true)) {
                throw ValidationException::withMessages(['sharing' => 'Only pending or active sharing agreements can be revoked.']);
            }
            $share->forceFill(['state' => KingdomIntelligenceShareState::Revoked, 'revoked_by_player_id' => $actor->playerId, 'invitation_token_hash' => null, 'revoked_at' => now()])->save();
            $metadata = ['share_id' => (string) $share->id, 'source_alliance_id' => (string) $share->source_alliance_id, 'recipient_alliance_id' => $share->recipient_alliance_id === null ? null : (string) $share->recipient_alliance_id, 'kingdom_id' => (string) $share->kingdom_id, 'state' => $share->state->value];
            $event = 'kingdoms.shared_intelligence_revoked';
            $this->audit->record($event, $actor, $share, $sourceAllianceId, $metadata);
            $this->outbox->record($event, $sourceAllianceId, $share, $metadata, $event.':'.$share->id.':'.$sourceAllianceId);

            return (string) $share->id;
        });
    }
}
