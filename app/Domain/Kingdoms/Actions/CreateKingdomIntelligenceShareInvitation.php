<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareState;
use App\Contexts\GameWorld\Models\KingdomIntelligenceShare;
use App\Domain\Kingdoms\Services\KingdomIntelligenceShareTokenService;
use App\Domain\Kingdoms\ValueObjects\IssuedKingdomIntelligenceShareInvitation;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateKingdomIntelligenceShareInvitation
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private KingdomIntelligenceShareTokenService $tokens,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $sourceAlliance, Player $actor): IssuedKingdomIntelligenceShareInvitation
    {
        if (! $this->authorization->allows($actor, $sourceAlliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($sourceAlliance, $actor): IssuedKingdomIntelligenceShareInvitation {
            $source = Alliance::query()->whereKey($sourceAlliance->id)->lockForUpdate()->firstOrFail();

            if ($source->kingdom_id === null) {
                throw ValidationException::withMessages([
                    'sharing' => 'The source alliance must have a current Kingdom before sharing can be invited.',
                ]);
            }

            $token = $this->tokens->issue();
            $ttlHours = max(1, min(168, (int) config('kingdoms.shared_intelligence.invitation_ttl_hours', 72)));

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
