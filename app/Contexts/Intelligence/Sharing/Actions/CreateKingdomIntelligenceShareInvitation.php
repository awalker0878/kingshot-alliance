<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Actions;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareState;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShare;
use App\Contexts\Intelligence\Sharing\Services\KingdomIntelligenceShareTokenService;
use App\Contexts\Intelligence\Sharing\ValueObjects\IssuedKingdomIntelligenceShareInvitation;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateKingdomIntelligenceShareInvitation
{
    public function __construct(
        private AllianceIntelligenceAuthorization $authorization,
        private KingdomIntelligenceShareTokenService $tokens,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $sourceAlliance, Player $actor): IssuedKingdomIntelligenceShareInvitation
    {
        if (! $this->authorization->allows($actor, $sourceAlliance, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($sourceAlliance, $actor): IssuedKingdomIntelligenceShareInvitation {
            $source = Alliance::query()->whereKey($sourceAlliance->id)->firstOrFail();

            if ($source->kingdom_id === null) {
                throw ValidationException::withMessages([
                    'sharing' => 'The source alliance must have a current Kingdom before sharing can be invited.',
                ]);
            }

            $token = $this->tokens->issue();
            $ttlHours = max(1, min(168, (int) config('intelligence.shared_intelligence.invitation_ttl_hours', 72)));

            $share = KingdomIntelligenceShare::query()->create([
                'source_alliance_id' => $source->id,
                'kingdom_id' => $source->kingdom_id,
                'invitation_token_hash' => $this->tokens->hash($token),
                'state' => KingdomIntelligenceShareState::Pending,
                'invited_by_player_id' => $actor->id,
                'invitation_expires_at' => now()->addHours($ttlHours),
            ]);

            $metadata = [
                'share_id' => (string) $share->id,
                'kingdom_id' => (string) $share->kingdom_id,
                'state' => $share->state->value,
                'invitation_expires_at' => $share->invitation_expires_at->toIso8601String(),
            ];

            $this->audit->record(
                'kingdoms.shared_intelligence_invitation_created',
                $actor,
                $share,
                $source,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.shared_intelligence_invitation_created',
                (string) $source->id,
                $share,
                $metadata,
                'kingdoms.shared_intelligence_invitation_created:'.$share->id,
            );

            return new IssuedKingdomIntelligenceShareInvitation((string) $share->id, $token);
        });
    }
}
