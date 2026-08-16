<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Actions;

use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareState;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShare;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RevokeKingdomIntelligenceShare
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $sourceAlliance, Player $actor, string $shareId): KingdomIntelligenceShare
    {
        if (! $this->authorization->allows($actor, $sourceAlliance, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($sourceAlliance, $actor, $shareId): KingdomIntelligenceShare {
            $source = Alliance::query()->whereKey($sourceAlliance->id)->lockForUpdate()->firstOrFail();
            $share = KingdomIntelligenceShare::query()
                ->whereKey($shareId)
                ->where('source_alliance_id', $source->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($share->state === KingdomIntelligenceShareState::Revoked
                || $share->state === KingdomIntelligenceShareState::Declined) {
                return $share;
            }

            if (! in_array($share->state, [
                KingdomIntelligenceShareState::Pending,
                KingdomIntelligenceShareState::Active,
            ], true)) {
                throw ValidationException::withMessages([
                    'sharing' => 'Only pending or active sharing agreements can be revoked.',
                ]);
            }

            $share->forceFill([
                'state' => KingdomIntelligenceShareState::Revoked,
                'revoked_by_player_id' => $actor->id,
                'invitation_token_hash' => null,
                'revoked_at' => now(),
            ])->save();

            $metadata = [
                'share_id' => (string) $share->id,
                'source_alliance_id' => (string) $share->source_alliance_id,
                'recipient_alliance_id' => $share->recipient_alliance_id === null ? null : (string) $share->recipient_alliance_id,
                'kingdom_id' => (string) $share->kingdom_id,
                'state' => $share->state->value,
            ];

            $event = 'kingdoms.shared_intelligence_revoked';
            $this->audit->record($event, $actor, $share, $source, $metadata);
            $this->outbox->record(
                $event,
                (string) $source->id,
                $share,
                $metadata,
                $event.':'.$share->id.':'.$source->id,
            );

            return $share->refresh();
        });
    }
}
