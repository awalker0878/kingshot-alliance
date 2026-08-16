<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Actions;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareTargetState;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShare;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShareTarget;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class RemoveKingdomIntelligenceShareTarget
{
    public function __construct(
        private AllianceIntelligenceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $sourceAlliance,
        Player $actor,
        string $shareId,
        string $targetId,
    ): KingdomIntelligenceShareTarget {
        if (! $this->authorization->allows($actor, $sourceAlliance, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($sourceAlliance, $actor, $shareId, $targetId): KingdomIntelligenceShareTarget {
            $source = Alliance::query()->whereKey($sourceAlliance->id)->firstOrFail();
            $share = KingdomIntelligenceShare::query()
                ->whereKey($shareId)
                ->where('source_alliance_id', $source->id)
                ->lockForUpdate()
                ->firstOrFail();
            $target = KingdomIntelligenceShareTarget::query()
                ->whereKey($targetId)
                ->where('kingdom_intelligence_share_id', $share->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($target->state === KingdomIntelligenceShareTargetState::Removed) {
                return $target;
            }

            $removedAt = now();
            $target->forceFill([
                'state' => KingdomIntelligenceShareTargetState::Removed,
                'removed_by_player_id' => $actor->id,
                'removed_at' => $removedAt,
            ])->save();

            $metadata = [
                'share_target_id' => (string) $target->id,
                'share_id' => (string) $share->id,
                'source_alliance_id' => (string) $share->source_alliance_id,
                'recipient_alliance_id' => $share->recipient_alliance_id === null
                    ? null
                    : (string) $share->recipient_alliance_id,
                'kingdom_id' => (string) $share->kingdom_id,
                'state' => $target->state->value,
            ];

            $this->recordForAlliance($source, $actor, $target, $metadata, $removedAt);

            if ($share->recipient_alliance_id !== null) {
                $recipient = Alliance::query()->find($share->recipient_alliance_id);
                if ($recipient instanceof Alliance) {
                    $this->recordForAlliance($recipient, null, $target, $metadata, $removedAt);
                }
            }

            return $target->refresh();
        });
    }

    /** @param array<string, mixed> $metadata */
    private function recordForAlliance(
        Alliance $alliance,
        ?Player $actor,
        KingdomIntelligenceShareTarget $target,
        array $metadata,
        \DateTimeInterface $occurredAt,
    ): void {
        $event = 'kingdoms.shared_intelligence_target_removed';
        $this->audit->record($event, $actor, $target, $alliance, $metadata);
        $this->outbox->record(
            $event,
            (string) $alliance->id,
            $target,
            $metadata,
            $event.':'.$target->id.':'.$alliance->id.':'.$occurredAt->format('YmdHis.u'),
        );
    }
}
