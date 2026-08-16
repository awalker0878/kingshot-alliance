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

final readonly class LeaveKingdomIntelligenceShare
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $recipientAlliance, Player $actor, string $shareId): KingdomIntelligenceShare
    {
        if (! $this->authorization->allows($actor, $recipientAlliance, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($recipientAlliance, $actor, $shareId): KingdomIntelligenceShare {
            $recipient = Alliance::query()->whereKey($recipientAlliance->id)->lockForUpdate()->firstOrFail();
            $share = KingdomIntelligenceShare::query()
                ->whereKey($shareId)
                ->where('recipient_alliance_id', $recipient->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($share->state === KingdomIntelligenceShareState::Declined
                || $share->state === KingdomIntelligenceShareState::Revoked) {
                return $share;
            }

            if ($share->state !== KingdomIntelligenceShareState::Active) {
                abort(404);
            }

            $share->forceFill([
                'state' => KingdomIntelligenceShareState::Declined,
                'declined_by_player_id' => $actor->id,
                'declined_at' => now(),
            ])->save();

            $metadata = [
                'share_id' => (string) $share->id,
                'source_alliance_id' => (string) $share->source_alliance_id,
                'recipient_alliance_id' => (string) $recipient->id,
                'kingdom_id' => (string) $share->kingdom_id,
                'state' => $share->state->value,
                'reason' => 'recipient_left',
            ];

            $event = 'kingdoms.shared_intelligence_left';
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
}
