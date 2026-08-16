<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Actions;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\KingdomAlliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class InvalidateKingdomAllianceObservation
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $trackingId,
        string $observationId,
        ?string $reason,
    ): KingdomAllianceObservation {
        return DB::transaction(function () use ($alliance, $actor, $trackingId, $observationId, $reason): KingdomAllianceObservation {
            $context = $this->authority->require($actor, $alliance, IntelligencePermission::KingdomManage);

            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($trackingId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($tracking->state !== TrackedKingdomAllianceState::Active) {
                throw ValidationException::withMessages([
                    'observation' => 'Only observations for actively tracked game-side alliances can be invalidated.',
                ]);
            }
            if ($context->alliance->kingdom_id === null
                || (string) $tracking->kingdom_id !== (string) $context->alliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'observation' => 'Historical Kingdom context is read-only. Archive the tracking relationship instead.',
                ]);
            }

            // Match RecordKingdomAllianceObservation exactly: tracking -> reference -> history.
            $reference = KingdomAlliance::query()
                ->whereKey($tracking->kingdom_alliance_id)
                ->lockForUpdate()
                ->firstOrFail();

            $observation = KingdomAllianceObservation::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('tracked_kingdom_alliance_id', $tracking->id)
                ->where('kingdom_alliance_id', $reference->id)
                ->whereKey($observationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($observation->invalidated_at !== null) {
                return $observation->load(['actor:id,current_name', 'invalidatedBy:id,current_name']);
            }

            $observation->forceFill([
                'invalidated_at' => now(),
                'invalidated_by_player_id' => $context->actor->id,
                'invalidation_reason' => $this->nullableText($reason),
            ])->save();

            $latest = KingdomAllianceObservation::query()
                ->where('kingdom_alliance_id', $reference->id)
                ->whereNull('invalidated_at')
                ->orderByDesc('captured_at')
                ->orderByDesc('id')
                ->first();
            if ($latest instanceof KingdomAllianceObservation) {
                $reference->forceFill([
                    'current_name' => $latest->observed_name,
                    'current_tag' => $latest->observed_tag,
                ])->save();
            }

            $metadata = [
                'observation_id' => (string) $observation->id,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $reference->id,
                'origin' => 'player',
            ];
            $event = 'kingdoms.alliance_intelligence_observation_invalidated';
            $this->audit->record($event, $context->actor, $observation, $context->alliance, $metadata);
            $this->outbox->record(
                $event,
                (string) $context->alliance->id,
                $observation,
                $metadata,
                $event.':'.$observation->id,
            );

            return $observation->load(['actor:id,current_name', 'invalidatedBy:id,current_name']);
        });
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
