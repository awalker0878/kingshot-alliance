<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Actions;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Actions\UpdateKingdomAllianceIdentity;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class RecordKingdomAllianceObservation
{
    private const MAX_POWER = '9223372036854775807';

    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private AllianceReferenceQuery $alliances,
        private KingdomAllianceReferenceQuery $kingdomAlliances,
        private UpdateKingdomAllianceIdentity $updateIdentity,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  array{observed_name:string,observed_tag?:string|null,power?:string|null,member_count?:int|string|null,captured_at:string,corrects_observation_id?:string|null,correction_reason?:string|null}  $attributes
     * @param  array{subscription_id:string,batch_id:string,adapter_key:string,adapter_version:string,source_record_id?:string|null,identity_hash:string,payload_hash:string}|null  $machineProvenance
     */
    public function handle(
        string $allianceId,
        ?string $actorPlayerId,
        string $trackingId,
        array $attributes,
        string $source = 'manual',
        ?array $machineProvenance = null,
    ): string {
        if (! in_array($source, ['manual', 'ingestion'], true)) {
            throw new InvalidArgumentException('Unsupported game-alliance observation source.');
        }

        if ($source === 'ingestion') {
            if ($actorPlayerId !== null || $machineProvenance === null) {
                throw new InvalidArgumentException('Automated observations require machine provenance and no Player actor.');
            }
            if (($attributes['corrects_observation_id'] ?? null) !== null
                || ($attributes['correction_reason'] ?? null) !== null) {
                throw new InvalidArgumentException('Automated observations cannot correct or invalidate existing history.');
            }
        } elseif ($actorPlayerId === null || $machineProvenance !== null) {
            throw new InvalidArgumentException('Manual observations require a Player actor and no machine provenance.');
        }

        $provenance = $source === 'ingestion'
            ? $this->machineProvenance($machineProvenance)
            : $this->emptyMachineProvenance();

        return DB::transaction(function () use ($allianceId, $actorPlayerId, $trackingId, $attributes, $source, $provenance): string {
            if ($source === 'ingestion') {
                $alliance = $this->alliances->lockCurrent($allianceId);
                $currentActor = null;
                $kingdomId = $alliance->kingdomId;
            } else {
                /** @var string $actorPlayerId */
                [$scope, $currentActor] = $this->writeState->authorize(
                    $actorPlayerId,
                    $allianceId,
                    IntelligencePermission::KingdomManage,
                );
                $kingdomId = $scope->kingdomId;
            }

            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $allianceId)
                ->whereKey($trackingId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($tracking->state !== TrackedKingdomAllianceState::Active) {
                throw ValidationException::withMessages([
                    'observation' => 'Observations can only be recorded for actively tracked game-side alliances.',
                ]);
            }
            if ((string) $tracking->kingdom_id !== $kingdomId) {
                throw ValidationException::withMessages([
                    'observation' => 'The tracked alliance belongs to historical Kingdom context. Archive it or restore the matching Kingdom context before recording observations.',
                ]);
            }

            // Observation-family order is tracking -> neutral reference -> history row.
            // The reference is also the current-name/tag synchronization anchor.
            $reference = $this->kingdomAlliances->require((string) $tracking->kingdom_alliance_id);
            if ($reference->kingdomId !== (string) $tracking->kingdom_id) {
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
            if ($observedName === '' || mb_strlen($observedName) > 160) {
                throw ValidationException::withMessages([
                    'observed_name' => 'The observed alliance name is required and must be 160 characters or fewer.',
                ]);
            }
            $observedTag = $this->nullableLine($attributes['observed_tag'] ?? null);
            $power = $this->power($attributes['power'] ?? null);
            $memberCount = $this->memberCount($attributes['member_count'] ?? null);
            $correctsId = $source === 'manual'
                ? $this->nullableLine($attributes['corrects_observation_id'] ?? null)
                : null;
            $correctionReason = $source === 'manual'
                ? $this->nullableText($attributes['correction_reason'] ?? null)
                : null;

            $idempotencyPayload = [
                'alliance_id' => $allianceId,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => $reference->kingdomAllianceId,
                'observed_name' => $observedName,
                'observed_tag' => $observedTag,
                'power' => $power,
                'member_count' => $memberCount,
                'captured_at' => $capturedAt->format('Y-m-d\TH:i:s.u\Z'),
                'source' => $source,
                'corrects_observation_id' => $correctsId,
            ];
            if ($source === 'ingestion') {
                $idempotencyPayload['source_identity_hash'] = $provenance['identity_hash'];
            }
            $idempotencyKey = hash('sha256', json_encode($idempotencyPayload, JSON_THROW_ON_ERROR));

            $existing = KingdomAllianceObservation::query()
                ->where('alliance_id', $allianceId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing instanceof KingdomAllianceObservation) {
                return (string) $existing->id;
            }

            $corrects = null;
            if ($correctsId !== null) {
                $corrects = KingdomAllianceObservation::query()
                    ->where('alliance_id', $allianceId)
                    ->where('tracked_kingdom_alliance_id', $tracking->id)
                    ->whereKey($correctsId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($corrects->invalidated_at !== null) {
                    throw ValidationException::withMessages([
                        'corrects_observation_id' => 'Only an accepted observation can be corrected.',
                    ]);
                }
            }

            $observation = KingdomAllianceObservation::query()->create([
                'alliance_id' => $allianceId,
                'tracked_kingdom_alliance_id' => $tracking->id,
                'kingdom_alliance_id' => $reference->kingdomAllianceId,
                'actor_player_id' => $currentActor?->playerId,
                'observed_name' => $observedName,
                'observed_tag' => $observedTag,
                'power' => $power,
                'member_count' => $memberCount,
                'captured_at' => $capturedAt,
                'source' => $source,
                'source_subscription_id' => $provenance['subscription_id'],
                'source_batch_id' => $provenance['batch_id'],
                'source_adapter_key' => $provenance['adapter_key'],
                'source_adapter_version' => $provenance['adapter_version'],
                'source_record_id' => $provenance['source_record_id'],
                'source_identity_hash' => $provenance['identity_hash'],
                'source_payload_hash' => $provenance['payload_hash'],
                'idempotency_key' => $idempotencyKey,
                'corrects_observation_id' => $corrects?->id,
            ]);

            if ($corrects instanceof KingdomAllianceObservation) {
                $corrects->forceFill([
                    'invalidated_at' => now(),
                    'invalidated_by_player_id' => $currentActor?->playerId,
                    'invalidation_reason' => $correctionReason,
                ])->save();
            }

            $this->syncNeutralIdentity($reference->kingdomAllianceId, $reference->kingdomId, $reference->gameAllianceId);

            $metadata = [
                'observation_id' => (string) $observation->id,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => $reference->kingdomAllianceId,
                'captured_at' => $capturedAt->toIso8601String(),
                'source' => $source,
                'corrects_observation_id' => $corrects?->id === null ? null : (string) $corrects->id,
                'source_subscription_id' => $provenance['subscription_id'],
                'source_batch_id' => $provenance['batch_id'],
                'source_adapter_key' => $provenance['adapter_key'],
                'source_adapter_version' => $provenance['adapter_version'],
                'source_record_id' => $provenance['source_record_id'],
                'source_identity_hash' => $provenance['identity_hash'],
                'source_payload_hash' => $provenance['payload_hash'],
                'origin' => $source === 'ingestion' ? 'system' : 'player',
            ];
            $event = 'kingdoms.alliance_intelligence_observation_recorded';
            $this->audit->record($event, $currentActor, $observation, $allianceId, $metadata);
            $this->outbox->record(
                $event,
                $allianceId,
                $observation,
                $metadata,
                $event.':'.$observation->id,
            );

            if ($corrects instanceof KingdomAllianceObservation) {
                $correctionMetadata = [
                    'invalidated_observation_id' => (string) $corrects->id,
                    'replacement_observation_id' => (string) $observation->id,
                    'tracked_kingdom_alliance_id' => (string) $tracking->id,
                    'origin' => 'player',
                ];
                $correctionEvent = 'kingdoms.alliance_intelligence_observation_corrected';
                $this->audit->record($correctionEvent, $currentActor, $corrects, $allianceId, $correctionMetadata);
                $this->outbox->record(
                    $correctionEvent,
                    $allianceId,
                    $corrects,
                    $correctionMetadata,
                    $correctionEvent.':'.$corrects->id.':'.$observation->id,
                );
            }

            return (string) $observation->id;
        });
    }

    private function syncNeutralIdentity(string $kingdomAllianceId, string $kingdomId, ?string $gameAllianceId): void
    {
        $latest = KingdomAllianceObservation::query()
            ->where('kingdom_alliance_id', $kingdomAllianceId)
            ->whereNull('invalidated_at')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();

        if ($latest instanceof KingdomAllianceObservation) {
            $this->updateIdentity->handle(
                $kingdomAllianceId,
                $kingdomId,
                (string) $latest->observed_name,
                $latest->observed_tag === null ? null : (string) $latest->observed_tag,
                $gameAllianceId,
            );
        }
    }

    private function power(?string $value): ?string
    {
        $value = $this->nullableLine($value);
        if ($value === null) {
            return null;
        }
        if (! preg_match('/^\d+$/', $value)) {
            throw ValidationException::withMessages(['power' => 'Power must be a non-negative whole number.']);
        }

        $canonical = ltrim($value, '0');
        $canonical = $canonical === '' ? '0' : $canonical;
        if (strlen($canonical) > strlen(self::MAX_POWER)
            || (strlen($canonical) === strlen(self::MAX_POWER) && strcmp($canonical, self::MAX_POWER) > 0)) {
            throw ValidationException::withMessages(['power' => 'Power exceeds the supported signed 64-bit integer range.']);
        }

        return $canonical;
    }

    private function memberCount(int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $count = (int) $value;
        if ($count < 0 || $count > 1000000) {
            throw ValidationException::withMessages([
                'member_count' => 'Member count must be between 0 and 1,000,000.',
            ]);
        }

        return $count;
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

    /**
     * @param  array<string, mixed>  $provenance
     * @return array<string, string|null>
     */
    private function machineProvenance(array $provenance): array
    {
        return [
            'subscription_id' => $this->provenanceText($provenance['subscription_id'] ?? null, 26, 'subscription_id'),
            'batch_id' => $this->provenanceText($provenance['batch_id'] ?? null, 26, 'batch_id'),
            'adapter_key' => $this->provenanceText($provenance['adapter_key'] ?? null, 80, 'adapter_key'),
            'adapter_version' => $this->provenanceText($provenance['adapter_version'] ?? null, 40, 'adapter_version'),
            'source_record_id' => $this->nullableProvenanceText($provenance['source_record_id'] ?? null, 191, 'source_record_id'),
            'identity_hash' => $this->hash($provenance['identity_hash'] ?? null, 'identity_hash'),
            'payload_hash' => $this->hash($provenance['payload_hash'] ?? null, 'payload_hash'),
        ];
    }

    /** @return array<string,null> */
    private function emptyMachineProvenance(): array
    {
        return [
            'subscription_id' => null,
            'batch_id' => null,
            'adapter_key' => null,
            'adapter_version' => null,
            'source_record_id' => null,
            'identity_hash' => null,
            'payload_hash' => null,
        ];
    }

    private function provenanceText(mixed $value, int $max, string $field): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Automated observation provenance '.$field.' must be text.');
        }

        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException('Automated observation provenance '.$field.' is missing or too long.');
        }

        return $value;
    }

    private function nullableProvenanceText(mixed $value, int $max, string $field): ?string
    {
        return $value === null ? null : $this->provenanceText($value, $max, $field);
    }

    private function hash(mixed $value, string $field): string
    {
        if (! is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new InvalidArgumentException('Automated observation provenance '.$field.' must be a SHA-256 hex digest.');
        }

        return $value;
    }
}
