<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareState;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShare;
use App\Contexts\Intelligence\Sharing\Services\KingdomIntelligenceShareTokenService;
use App\Contexts\Intelligence\Sharing\ValueObjects\IssuedKingdomIntelligenceShareInvitation;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class CreateKingdomIntelligenceShareInvitation
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private KingdomIntelligenceShareTokenService $tokens,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $sourceAllianceId, string $actorPlayerId): IssuedKingdomIntelligenceShareInvitation
    {
        return DB::transaction(function () use ($sourceAllianceId, $actorPlayerId): IssuedKingdomIntelligenceShareInvitation {
            [$scope, $actor] = $this->writeState->authorize($actorPlayerId, $sourceAllianceId, IntelligencePermission::KingdomManage);
            $token = $this->tokens->issue();
            $ttlHours = max(1, min(168, (int) config('intelligence.shared_intelligence.invitation_ttl_hours', 72)));
            $share = KingdomIntelligenceShare::query()->create([
                'source_alliance_id' => $sourceAllianceId,
                'kingdom_id' => $scope->kingdomId,
                'invitation_token_hash' => $this->tokens->hash($token),
                'state' => KingdomIntelligenceShareState::Pending,
                'invited_by_player_id' => $actor->playerId,
                'invitation_expires_at' => now()->addHours($ttlHours),
            ]);
            $metadata = [
                'share_id' => (string) $share->id,
                'kingdom_id' => $scope->kingdomId,
                'state' => $share->state->value,
                'invitation_expires_at' => $share->invitation_expires_at->toIso8601String(),
            ];
            $event = 'kingdoms.shared_intelligence_invitation_created';
            $this->audit->record($event, $actor, $share, $sourceAllianceId, $metadata);
            $this->outbox->record($event, $sourceAllianceId, $share, $metadata, $event.':'.$share->id);
            return new IssuedKingdomIntelligenceShareInvitation((string) $share->id, $token);
        });
    }
}
