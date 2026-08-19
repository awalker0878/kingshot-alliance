<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Actions;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareState;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShare;
use App\Contexts\Intelligence\Sharing\Services\KingdomIntelligenceShareTokenService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AcceptKingdomIntelligenceShareInvitation
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private AllianceReferenceQuery $alliances,
        private KingdomIntelligenceShareTokenService $tokens,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $recipientAllianceId, string $actorPlayerId, string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            throw $this->invalidToken();
        }

        return DB::transaction(function () use ($recipientAllianceId, $actorPlayerId, $token): string {
            [$recipientScope, $actor] = $this->writeState->authorize($actorPlayerId, $recipientAllianceId, IntelligencePermission::KingdomManage);
            $tokenHash = $this->tokens->hash($token);
            $share = KingdomIntelligenceShare::query()
                ->where('invitation_token_hash', $tokenHash)
                ->where('state', KingdomIntelligenceShareState::Pending->value)
                ->lockForUpdate()->first();
            if (! $share instanceof KingdomIntelligenceShare || $share->invitation_used_at !== null || ! $share->invitation_expires_at->isFuture()) {
                throw $this->invalidToken();
            }
            $sourceAllianceId = (string) $share->source_alliance_id;
            if ($sourceAllianceId === $recipientAllianceId) {
                throw ValidationException::withMessages(['sharing' => 'An alliance cannot accept its own sharing invitation.']);
            }
            $source = $this->alliances->lockCurrent($sourceAllianceId);
            if ($source->kingdomId !== (string) $share->kingdom_id || $recipientScope->kingdomId !== (string) $share->kingdom_id) {
                throw ValidationException::withMessages(['sharing' => 'Both alliances must still be in the invitation Kingdom before sharing can be accepted.']);
            }
            $existing = KingdomIntelligenceShare::query()
                ->where('source_alliance_id', $sourceAllianceId)
                ->where('recipient_alliance_id', $recipientAllianceId)
                ->where('kingdom_id', $share->kingdom_id)
                ->where('state', KingdomIntelligenceShareState::Active->value)
                ->where('id', '!=', $share->id)->lockForUpdate()->first();
            if ($existing instanceof KingdomIntelligenceShare) {
                throw ValidationException::withMessages(['sharing' => 'An active directional sharing agreement already exists for these alliances in this Kingdom.']);
            }
            $share->forceFill([
                'recipient_alliance_id' => $recipientAllianceId,
                'state' => KingdomIntelligenceShareState::Active,
                'accepted_by_player_id' => $actor->playerId,
                'invitation_token_hash' => null,
                'invitation_used_at' => now(),
                'accepted_at' => now(),
            ])->save();
            $metadata = $this->metadata($share);
            $this->recordForAlliance($sourceAllianceId, null, $share, $metadata);
            $this->recordForAlliance($recipientAllianceId, $actor, $share, $metadata);

            return (string) $share->id;
        });
    }

    /** @param array<string,mixed> $metadata */
    private function recordForAlliance(string $allianceId, ?PlayerReference $actor, KingdomIntelligenceShare $share, array $metadata): void
    {
        $event = 'kingdoms.shared_intelligence_accepted';
        $this->audit->record($event, $actor, $share, $allianceId, $metadata);
        $this->outbox->record($event, $allianceId, $share, $metadata, $event.':'.$share->id.':'.$allianceId);
    }

    /** @return array<string,mixed> */
    private function metadata(KingdomIntelligenceShare $share): array
    {
        return ['share_id' => (string) $share->id, 'source_alliance_id' => (string) $share->source_alliance_id, 'recipient_alliance_id' => (string) $share->recipient_alliance_id, 'kingdom_id' => (string) $share->kingdom_id, 'state' => $share->state->value];
    }

    private function invalidToken(): ValidationException
    {
        return ValidationException::withMessages(['token' => 'The sharing invitation is invalid, expired, or already used.']);
    }
}
