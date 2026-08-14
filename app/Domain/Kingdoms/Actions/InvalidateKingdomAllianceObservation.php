<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Enums\TrackedKingdomAllianceState;
use App\Domain\Kingdoms\Models\KingdomAlliance;
use App\Domain\Kingdoms\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class InvalidateKingdomAllianceObservation
{
    public function __construct(
        private AllianceAuthorization $authorization,
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
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $trackingId, $observationId, $reason): KingdomAllianceObservation {
            $lockedAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $lockedAlliance->id)
                ->lockForUpdate()
                ->findOrFail($trackingId);

            if ($tracking->state !== TrackedKingdomAllianceState::Active) {
                throw ValidationException::withMessages([
                    'observation' => 'Only observations for actively tracked game-side alliances can be invalidated.',
                ]);
            }

            if ($lockedAlliance->kingdom_id === null || $tracking->kingdom_id !== $lockedAlliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'observation' => 'Historical Kingdom context is read-only. Archive the tracking relationship instead.',
                ]);
            }

            $observation = KingdomAllianceObservation::query()
                ->where('alliance_id', $lockedAlliance->id)
                ->where('tracked_kingdom_alliance_id', $tracking->id)
                ->lockForUpdate()
                ->findOrFail($observationId);

            if ($observation->invalidated_at !== null) {
                return $observation->load(['actor:id,current_name', 'invalidatedBy:id,current_name']);
            }

            $observation->forceFill([
                'invalidated_at' => now(),
                'invalidated_by_player_id' => $actor->id,
                'invalidation_reason' => $this->nullableText($reason),
            ])->save();

            $reference = KingdomAlliance::query()->lockForUpdate()->findOrFail($tracking->kingdom_alliance_id);
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
                'kingdom_alliance_id' => (string) $observation->kingdom_alliance_id,
            ];
            $event = 'kingdoms.alliance_intelligence_observation_invalidated';
            $this->audit->record($event, $actor, $observation, $lockedAlliance, $metadata);
            $this->outbox->record($event, (string) $lockedAlliance->id, $observation, $metadata, $event.':'.$observation->id);

            return $observation->load(['actor:id,current_name', 'invalidatedBy:id,current_name']);
        });
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
