<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Actions;

use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareState;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShare;
use App\Contexts\Intelligence\Sharing\Services\KingdomIntelligenceShareTokenService;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeclineKingdomIntelligenceShareInvitation
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private KingdomIntelligenceShareTokenService $tokens,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $recipientAlliance, Player $actor, string $token): KingdomIntelligenceShare
    {
        if (! $this->authorization->allows($actor, $recipientAlliance, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        $token = trim($token);
        if ($token === '') {
            throw $this->invalidToken();
        }

        return DB::transaction(function () use ($recipientAlliance, $actor, $token): KingdomIntelligenceShare {
            $tokenHash = $this->tokens->hash($token);
            $candidate = KingdomIntelligenceShare::query()
                ->where('invitation_token_hash', $tokenHash)
                ->where('state', KingdomIntelligenceShareState::Pending->value)
                ->first();

            if (! $candidate instanceof KingdomIntelligenceShare) {
                throw $this->invalidToken();
            }

            $recipient = Alliance::query()->whereKey($recipientAlliance->id)->lockForUpdate()->firstOrFail();
            $share = KingdomIntelligenceShare::query()
                ->whereKey($candidate->id)
                ->where('invitation_token_hash', $tokenHash)
                ->where('state', KingdomIntelligenceShareState::Pending->value)
                ->lockForUpdate()
                ->first();

            if (! $share instanceof KingdomIntelligenceShare
                || $share->invitation_used_at !== null
                || ! $share->invitation_expires_at->isFuture()) {
                throw $this->invalidToken();
            }

            $share->forceFill([
                'recipient_alliance_id' => $recipient->id,
                'state' => KingdomIntelligenceShareState::Declined,
                'declined_by_player_id' => $actor->id,
                'invitation_token_hash' => null,
                'invitation_used_at' => now(),
                'declined_at' => now(),
            ])->save();

            $metadata = [
                'share_id' => (string) $share->id,
                'source_alliance_id' => (string) $share->source_alliance_id,
                'recipient_alliance_id' => (string) $recipient->id,
                'kingdom_id' => (string) $share->kingdom_id,
                'state' => $share->state->value,
                'reason' => 'invitation_declined',
            ];

            $event = 'kingdoms.shared_intelligence_declined';
            $this->audit->record($event, $actor, $share, $recipient, $metadata);
            $this->outbox->record(
                $event,
                (string) $recipient->id,
                $share,
                $metadata,
                $event.':'.$share->id.':'.$recipient->id,
            );

            return $share->refresh();
        });
    }

    private function invalidToken(): ValidationException
    {
        return ValidationException::withMessages([
            'token' => 'The sharing invitation is invalid, expired, or already used.',
        ]);
    }
}
