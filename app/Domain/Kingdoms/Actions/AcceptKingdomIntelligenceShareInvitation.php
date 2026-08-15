<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\KingdomIntelligenceShare;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareState;
use App\Domain\Kingdoms\Services\KingdomIntelligenceShareTokenService;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AcceptKingdomIntelligenceShareInvitation
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private KingdomIntelligenceShareTokenService $tokens,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $recipientAlliance, Player $actor, string $token): KingdomIntelligenceShare
    {
        if (! $this->authorization->allows($actor, $recipientAlliance, PermissionKey::KingdomManage)) {
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

            if ($candidate->source_alliance_id === (string) $recipientAlliance->id) {
                throw ValidationException::withMessages([
                    'sharing' => 'An alliance cannot accept its own sharing invitation.',
                ]);
            }

            $ids = [$candidate->source_alliance_id, (string) $recipientAlliance->id];
            sort($ids, SORT_STRING);
            $locked = Alliance::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(static fn (Alliance $alliance): string => (string) $alliance->id);

            /** @var Alliance|null $source */
            $source = $locked->get($candidate->source_alliance_id);
            /** @var Alliance|null $recipient */
            $recipient = $locked->get((string) $recipientAlliance->id);

            if (! $source instanceof Alliance || ! $recipient instanceof Alliance) {
                throw $this->invalidToken();
            }

            $share = KingdomIntelligenceShare::query()
                ->whereKey($candidate->id)
                ->where('invitation_token_hash', $tokenHash)
                ->where('source_alliance_id', $source->id)
                ->where('state', KingdomIntelligenceShareState::Pending->value)
                ->lockForUpdate()
                ->first();

            if (! $share instanceof KingdomIntelligenceShare
                || $share->invitation_used_at !== null
                || ! $share->invitation_expires_at->isFuture()) {
                throw $this->invalidToken();
            }

            if ($source->kingdom_id === null
                || $recipient->kingdom_id === null
                || $source->kingdom_id !== $share->kingdom_id
                || $recipient->kingdom_id !== $share->kingdom_id) {
                throw ValidationException::withMessages([
                    'sharing' => 'Both alliances must still be in the invitation Kingdom before sharing can be accepted.',
                ]);
            }

            $existing = KingdomIntelligenceShare::query()
                ->where('source_alliance_id', $source->id)
                ->where('recipient_alliance_id', $recipient->id)
                ->where('kingdom_id', $share->kingdom_id)
                ->where('state', KingdomIntelligenceShareState::Active->value)
                ->where('id', '!=', $share->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof KingdomIntelligenceShare) {
                throw ValidationException::withMessages([
                    'sharing' => 'An active directional sharing agreement already exists for these alliances in this Kingdom.',
                ]);
            }

            $share->forceFill([
                'recipient_alliance_id' => $recipient->id,
                'state' => KingdomIntelligenceShareState::Active,
                'accepted_by_player_id' => $actor->id,
                'invitation_token_hash' => null,
                'invitation_used_at' => now(),
                'accepted_at' => now(),
            ])->save();

            $metadata = $this->metadata($share);
            $this->recordForAlliance($source, null, $share, $metadata);
            $this->recordForAlliance($recipient, $actor, $share, $metadata);

            return $share->refresh();
        });
    }

    /** @param array<string, mixed> $metadata */
    private function recordForAlliance(
        Alliance $alliance,
        ?Player $actor,
        KingdomIntelligenceShare $share,
        array $metadata,
    ): void {
        $event = 'kingdoms.shared_intelligence_accepted';
        $this->audit->record($event, $actor, $share, $alliance, $metadata);
        $this->outbox->record(
            $event,
            (string) $alliance->id,
            $share,
            $metadata,
            $event.':'.$share->id.':'.$alliance->id,
        );
    }

    /** @return array<string, mixed> */
    private function metadata(KingdomIntelligenceShare $share): array
    {
        return [
            'share_id' => (string) $share->id,
            'source_alliance_id' => (string) $share->source_alliance_id,
            'recipient_alliance_id' => (string) $share->recipient_alliance_id,
            'kingdom_id' => (string) $share->kingdom_id,
            'state' => $share->state->value,
        ];
    }

    private function invalidToken(): ValidationException
    {
        return ValidationException::withMessages([
            'token' => 'The sharing invitation is invalid, expired, or already used.',
        ]);
    }
}
