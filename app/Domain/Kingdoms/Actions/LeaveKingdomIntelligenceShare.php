<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareState;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShare;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class LeaveKingdomIntelligenceShare
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $recipientAlliance, User $actor, string $shareId): KingdomIntelligenceShare
    {
        if (! $this->authorization->allows($actor, $recipientAlliance, PermissionKey::KingdomManage)) {
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
                'declined_by_user_id' => $actor->id,
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
