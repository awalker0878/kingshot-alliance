<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveTrackedKingdomAlliance
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $trackingId): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $trackingId): string {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);

            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->findOrFail($trackingId);
            if ($tracking->state === TrackedKingdomAllianceState::Archived) {
                return (string) $tracking->id;
            }

            $tracking->forceFill([
                'state' => TrackedKingdomAllianceState::Archived,
                'archived_at' => now(),
            ])->save();

            $metadata = [
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $tracking->kingdom_alliance_id,
                'kingdom_id' => (string) $tracking->kingdom_id,
                'state' => $tracking->state->value,
            ];
            $this->audit->record('kingdoms.alliance_intelligence_tracking_archived', $actor, $tracking, $allianceId, $metadata);
            $this->outbox->record('kingdoms.alliance_intelligence_tracking_archived', $allianceId, $tracking, $metadata);

            return (string) $tracking->id;
        });
    }
}
