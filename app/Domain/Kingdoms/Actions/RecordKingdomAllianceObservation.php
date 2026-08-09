<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\TrackedKingdomAllianceState;
use App\Domain\Kingdoms\Models\KingdomAlliance;
use App\Domain\Kingdoms\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordKingdomAllianceObservation
{
    private const MAX_POWER = '9223372036854775807';

    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param array{
     *   observed_name: string,
     *   observed_tag?: string|null,
     *   power?: string|null,
     *   member_count?: int|null,
     *   captured_at: string,
     *   corrects_observation_id?: string|null,
     *   correction_reason?: string|null
     * } $attributes
     */
    public function handle(
        Alliance $alliance,
        User $actor,
        string $trackingId,
        array $attributes,
    ): KingdomAllianceObservation {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $trackingId, $attributes): KingdomAllianceObservation {
            $lockedAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $lockedAlliance->id)
                ->lockForUpdate()
                ->findOrFail($trackingId);

            if ($tracking->state !== TrackedKingdomAllianceState::Active) {
                throw ValidationException::withMessages([
                    'observation' => 'Observations can only be recorded for actively tracked game-side alliances.',
                ]);
            }

            if ($lockedAlliance->kingdom_id === null || $tracking->kingdom_id !== $lockedAlliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'observation' => 'The tracked alliance belongs to historical Kingdom context. Archive it or restore the matching Kingdom context before recording observations.',
                ]);
            }

            $reference = KingdomAlliance::query()->lockForUpdate()->findOrFail($tracking->kingdom_alliance_id);
            if ($reference->kingdom_id !== $tracking->kingdom_id) {
                throw ValidationException::withMessages([
                    'observation' => 'The tracked alliance reference no longer matches its captured Kingdom context.',
                ]);
            }

            $capturedAt = Carbon::parse($attributes['captured_at'])->utc();
            if ($capturedAt->isAfter(now()->addMinutes(5))) {
                throw ValidationException::withMessages([
                    'captured_at' => 'The observation capture time cannot be more than five minutes in the future.',
                ]);
            }

            $observedName = trim($attributes['observed_name']);
            $observedTag = $this->nullableLine($attributes['observed_tag'] ?? null);
            $power = $this->power($attributes['power'] ?? null);
            $memberCount = $attributes['member_count'] ?? null;
            $correctsId = $this->nullableLine($attributes['corrects_observation_id'] ?? null);
            $correctionReason = $this->nullableText($attributes['correction_reason'] ?? null);

            $idempotencyKey = hash('sha256', json_encode([
                'alliance_id' => (string) $lockedAlliance->id,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $reference->id,
                'observed_name' => $observedName,
                'observed_tag' => $observedTag,
                'power' => $power,
                'member_count' => $memberCount,
                'captured_at' => $capturedAt->format('Y-m-d\\TH:i:s.u\\Z'),
                'source' => 'manual',
                'corrects_observation_id' => $correctsId,
            ], JSON_THROW_ON_ERROR));

            $existing = KingdomAllianceObservation::query()
                ->where('alliance_id', $lockedAlliance->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing instanceof KingdomAllianceObservation) {
                return $existing->load(['actor:id,name', 'invalidatedBy:id,name']);
            }

            $corrects = null;
            if ($correctsId !== null) {
                $corrects = KingdomAllianceObservation::query()
                    ->where('alliance_id', $lockedAlliance->id)
                    ->where('tracked_kingdom_alliance_id', $tracking->id)
                    ->lockForUpdate()
                    ->findOrFail($correctsId);

                if ($corrects->invalidated_at !== null) {
                    throw ValidationException::withMessages([
                        'corrects_observation_id' => 'Only an accepted observation can be corrected.',
                    ]);
                }
            }

            $observation = KingdomAllianceObservation::query()->create([
                'alliance_id' => $lockedAlliance->id,
                'tracked_kingdom_alliance_id' => $tracking->id,
                'kingdom_alliance_id' => $reference->id,
                'actor_user_id' => $actor->id,
                'observed_name' => $observedName,
                'observed_tag' => $observedTag,
                'power' => $power,
                'member_count' => $memberCount,
                'captured_at' => $capturedAt,
                'source' => 'manual',
                'idempotency_key' => $idempotencyKey,
                'corrects_observation_id' => $corrects?->id,
            ]);

            if ($corrects instanceof KingdomAllianceObservation) {
                $corrects->forceFill([
                    'invalidated_at' => now(),
                    'invalidated_by_user_id' => $actor->id,
                    'invalidation_reason' => $correctionReason,
                ])->save();
            }

            $this->syncNeutralIdentity($reference);

            $metadata = [
                'observation_id' => (string) $observation->id,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $reference->id,
                'captured_at' => $capturedAt->toIso8601String(),
                'source' => 'manual',
                'corrects_observation_id' => $corrects?->id === null ? null : (string) $corrects->id,
            ];
            $event = 'kingdoms.alliance_intelligence_observation_recorded';
            $this->audit->record($event, $actor, $observation, $lockedAlliance, $metadata);
            $this->outbox->record($event, (string) $lockedAlliance->id, $observation, $metadata, $event.':'.$observation->id);

            if ($corrects instanceof KingdomAllianceObservation) {
                $correctionMetadata = [
                    'invalidated_observation_id' => (string) $corrects->id,
                    'replacement_observation_id' => (string) $observation->id,
                    'tracked_kingdom_alliance_id' => (string) $tracking->id,
                ];
                $correctionEvent = 'kingdoms.alliance_intelligence_observation_corrected';
                $this->audit->record($correctionEvent, $actor, $corrects, $lockedAlliance, $correctionMetadata);
                $this->outbox->record(
                    $correctionEvent,
                    (string) $lockedAlliance->id,
                    $corrects,
                    $correctionMetadata,
                    $correctionEvent.':'.$corrects->id.':'.$observation->id,
                );
            }

            return $observation->load(['actor:id,name', 'invalidatedBy:id,name']);
        });
    }

    private function syncNeutralIdentity(KingdomAlliance $reference): void
    {
        $latest = KingdomAllianceObservation::query()
            ->where('kingdom_alliance_id', $reference->id)
            ->whereNull('invalidated_at')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();

        if (! $latest instanceof KingdomAllianceObservation) {
            return;
        }

        $reference->forceFill([
            'current_name' => $latest->observed_name,
            'current_tag' => $latest->observed_tag,
        ])->save();
    }

    private function power(?string $value): ?string
    {
        $value = $this->nullableLine($value);
        if ($value === null) {
            return null;
        }

        if (! preg_match('/^\\d+$/', $value)) {
            throw ValidationException::withMessages(['power' => 'Power must be a non-negative whole number.']);
        }

        $canonical = ltrim($value, '0');
        $canonical = $canonical === '' ? '0' : $canonical;
        if (
            strlen($canonical) > strlen(self::MAX_POWER)
            || (strlen($canonical) === strlen(self::MAX_POWER) && strcmp($canonical, self::MAX_POWER) > 0)
        ) {
            throw ValidationException::withMessages(['power' => 'Power exceeds the supported signed 64-bit integer range.']);
        }

        return $canonical;
    }

    private function nullableLine(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function nullableText(?string $value): ?string
    {
        return $this->nullableLine($value);
    }
}
