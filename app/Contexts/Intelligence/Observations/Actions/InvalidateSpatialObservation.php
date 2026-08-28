<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Observations\Models\SpatialObservation;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class InvalidateSpatialObservation
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $kingdomId,
        string $observationId,
        string $reason,
    ): void {
        $reason = trim($reason);
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'An invalidation reason between 8 and 1,000 characters is required.']);
        }

        DB::transaction(function () use ($actorPlayerId, $allianceId, $kingdomId, $observationId, $reason): void {
            [$scope, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            if ($scope->kingdomId !== $kingdomId) {
                throw ValidationException::withMessages(['kingdom_id' => 'The observation belongs to historical Kingdom context.']);
            }
            $observation = SpatialObservation::query()
                ->where('alliance_id', $allianceId)
                ->where('kingdom_id', $kingdomId)
                ->whereKey($observationId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($observation->invalidated_at !== null) {
                return;
            }
            $observation->forceFill([
                'invalidated_at' => now(),
                'invalidated_by_player_id' => $actorPlayerId,
                'invalidation_reason' => $reason,
            ])->save();

            $metadata = ['observation_id' => $observationId, 'kingdom_id' => $kingdomId, 'reason' => $reason];
            $event = 'intelligence.spatial_observation_invalidated';
            $this->audit->record($event, $actor, $observation, $allianceId, $metadata);
            $this->outbox->record($event, $allianceId, $observation, $metadata, $event.':'.$observationId);
        });
    }
}
