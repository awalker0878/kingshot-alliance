<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Actions;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveTrackedKingdomAlliance
{
    public function __construct(
        private AllianceIntelligenceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, string $trackingId): TrackedKingdomAlliance
    {
        if (! $this->authorization->allows($actor, $alliance, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $trackingId): TrackedKingdomAlliance {
            $lockedAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $lockedAlliance->id)
                ->lockForUpdate()
                ->findOrFail($trackingId);

            if ($tracking->state === TrackedKingdomAllianceState::Archived) {
                return $tracking->load(['kingdomAlliance', 'kingdom']);
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

            $this->audit->record(
                'kingdoms.alliance_intelligence_tracking_archived',
                $actor,
                $tracking,
                $lockedAlliance,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.alliance_intelligence_tracking_archived',
                (string) $lockedAlliance->id,
                $tracking,
                $metadata,
            );

            return $tracking->refresh()->load(['kingdomAlliance', 'kingdom']);
        });
    }
}
