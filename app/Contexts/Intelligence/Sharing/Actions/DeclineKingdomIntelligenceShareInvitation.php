<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareState;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShare;
use App\Contexts\Intelligence\Sharing\Services\KingdomIntelligenceShareTokenService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeclineKingdomIntelligenceShareInvitation
{
    public function __construct(private AllianceIntelligenceWriteState $writeState, private KingdomIntelligenceShareTokenService $tokens, private AuditRecorder $audit, private OutboxRecorder $outbox) {}

    public function handle(string $recipientAllianceId, string $actorPlayerId, string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            throw $this->invalidToken();
        }

        return DB::transaction(function () use ($recipientAllianceId, $actorPlayerId, $token): string {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $recipientAllianceId, IntelligencePermission::KingdomManage);
            $tokenHash = $this->tokens->hash($token);
            $share = KingdomIntelligenceShare::query()->where('invitation_token_hash', $tokenHash)->where('state', KingdomIntelligenceShareState::Pending->value)->lockForUpdate()->first();
            if (! $share instanceof KingdomIntelligenceShare || $share->invitation_used_at !== null || ! $share->invitation_expires_at->isFuture()) {
                throw $this->invalidToken();
            }
            $share->forceFill(['recipient_alliance_id' => $recipientAllianceId, 'state' => KingdomIntelligenceShareState::Declined, 'declined_by_player_id' => $actor->playerId, 'invitation_token_hash' => null, 'invitation_used_at' => now(), 'declined_at' => now()])->save();
            $metadata = ['share_id' => (string) $share->id, 'source_alliance_id' => (string) $share->source_alliance_id, 'recipient_alliance_id' => $recipientAllianceId, 'kingdom_id' => (string) $share->kingdom_id, 'state' => $share->state->value, 'reason' => 'invitation_declined'];
            $event = 'kingdoms.shared_intelligence_declined';
            $this->audit->record($event, $actor, $share, $recipientAllianceId, $metadata);
            $this->outbox->record($event, $recipientAllianceId, $share, $metadata, $event.':'.$share->id.':'.$recipientAllianceId);

            return (string) $share->id;
        });
    }

    private function invalidToken(): ValidationException
    {
        return ValidationException::withMessages(['token' => 'The sharing invitation is invalid, expired, or already used.']);
    }
}
