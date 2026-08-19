<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Actions;

use App\Contexts\GameWorld\Kingdoms\Actions\UpdateKingdomAllianceIdentity;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class InvalidateKingdomAllianceObservation
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private KingdomAllianceReferenceQuery $references,
        private UpdateKingdomAllianceIdentity $updateIdentity,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $trackingId,
        string $observationId,
        ?string $reason,
    ): string {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $trackingId, $observationId, $reason): string {
            [$scope, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);

            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $allianceId)
                ->whereKey($trackingId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($tracking->state !== TrackedKingdomAllianceState::Active) {
                throw ValidationException::withMessages(['observation' => 'Only observations for actively tracked game-side alliances can be invalidated.']);
            }
            if ((string) $tracking->kingdom_id !== $scope->kingdomId) {
                throw ValidationException::withMessages(['observation' => 'Historical Kingdom context is read-only. Archive the tracking relationship instead.']);
            }

            $reference = $this->references->require((string) $tracking->kingdom_alliance_id);
            if ($reference->kingdomId !== (string) $tracking->kingdom_id) {
                throw ValidationException::withMessages(['observation' => 'The tracked alliance reference no longer matches its captured Kingdom context.']);
            }

            $observation = KingdomAllianceObservation::query()
                ->where('alliance_id', $allianceId)
                ->where('tracked_kingdom_alliance_id', $tracking->id)
                ->where('kingdom_alliance_id', $reference->kingdomAllianceId)
                ->whereKey($observationId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($observation->invalidated_at !== null) {
                return (string) $observation->id;
            }

            $observation->forceFill([
                'invalidated_at' => now(),
                'invalidated_by_player_id' => $actor->playerId,
                'invalidation_reason' => $this->nullableText($reason),
            ])->save();

            $latest = KingdomAllianceObservation::query()
                ->where('kingdom_alliance_id', $reference->kingdomAllianceId)
                ->whereNull('invalidated_at')
                ->orderByDesc('captured_at')
                ->orderByDesc('id')
                ->first();
            if ($latest instanceof KingdomAllianceObservation) {
                $this->updateIdentity->handle(
                    $reference->kingdomAllianceId,
                    $scope->kingdomId,
                    (string) $latest->observed_name,
                    $latest->observed_tag === null ? null : (string) $latest->observed_tag,
                    $reference->gameAllianceId,
                );
            }

            $metadata = [
                'observation_id' => (string) $observation->id,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => $reference->kingdomAllianceId,
                'origin' => 'player',
            ];
            $event = 'kingdoms.alliance_intelligence_observation_invalidated';
            $this->audit->record($event, $actor, $observation, $allianceId, $metadata);
            $this->outbox->record($event, $allianceId, $observation, $metadata, $event.':'.$observation->id);

            return (string) $observation->id;
        });
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
