<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\KingdomAlliance;
use App\Contexts\GameWorld\Models\KingdomAllianceObservation;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Kingdoms\Enums\TrackedKingdomAllianceState;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class RecordKingdomAllianceObservation
{
    private const MAX_POWER = '9223372036854775807';

    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  array{observed_name:string,observed_tag?:string|null,power?:string|null,member_count?:int|string|null,captured_at:string,corrects_observation_id?:string|null,correction_reason?:string|null}  $attributes
     * @param  array{subscription_id:string,batch_id:string,adapter_key:string,adapter_version:string,source_record_id?:string|null,identity_hash:string,payload_hash:string}|null  $machineProvenance
     */
    public function handle(
        Alliance $alliance,
        ?Player $actor,
        string $trackingId,
        array $attributes,
        string $source = 'manual',
        ?array $machineProvenance = null,
    ): KingdomAllianceObservation {
        if (! in_array($source, ['manual', 'ingestion'], true)) {
            throw new InvalidArgumentException('Unsupported game-alliance observation source.');
        }

        if ($source === 'ingestion') {
            if ($actor !== null || $machineProvenance === null) {
                throw new InvalidArgumentException('Automated observations require machine provenance and no Player actor.');
            }
            if (($attributes['corrects_observation_id'] ?? null) !== null
                || ($attributes['correction_reason'] ?? null) !== null) {
                throw new InvalidArgumentException('Automated observations cannot correct or invalidate existing history.');
            }
        } elseif (! $actor instanceof Player || $machineProvenance !== null) {
            throw new InvalidArgumentException('Manual observations require a Player actor and no machine provenance.');
        }

        $provenance = $source === 'ingestion'
            ? $this->machineProvenance($machineProvenance)
            : $this->emptyMachineProvenance();

        return DB::transaction(function () use ($alliance, $actor, $trackingId, $attributes, $source, $provenance): KingdomAllianceObservation {
            if ($source === 'ingestion') {
                // Automated promotion has no human authority principal. It still uses
                // the same Alliance lifecycle boundary as manual intelligence writes.
                $currentAlliance = Alliance::query()->whereKey($alliance->id)->sharedLock()->firstOrFail();
                $currentActor = null;
            } else {
                /** @var Player $actor */
                $context = $this->authority->require($actor, $alliance, PermissionKey::KingdomManage);
                $currentAlliance = $context->alliance;
                $currentActor = $context->actor;
            }

            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $currentAlliance->id)
                ->whereKey($trackingId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($tracking->state !== TrackedKingdomAllianceState::Active) {
                throw ValidationException::withMessages([
                    'observation' => 'Observations can only be recorded for actively tracked game-side alliances.',
                ]);
            }
            if ($currentAlliance->kingdom_id === null
                || (string) $tracking->kingdom_id !== (string) $currentAlliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'observation' => 'The tracked alliance belongs to historical Kingdom context. Archive it or restore the matching Kingdom context before recording observations.',
                ]);
            }

            // Observation-family order is tracking -> neutral reference -> history row.
            // The reference is also the current-name/tag synchronization anchor.
            $reference = KingdomAlliance::query()
                ->whereKey($tracking->kingdom_alliance_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ((string) $reference->kingdom_id !== (string) $tracking->kingdom_id) {
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
                'alliance_id' => (string) $currentAlliance->id,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $reference->id,
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
                ->where('alliance_id', $currentAlliance->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing instanceof KingdomAllianceObservation) {
                return $existing->load(['actor:id,current_name', 'invalidatedBy:id,current_name']);
            }

            $corrects = null;
            if ($correctsId !== null) {
                $corrects = KingdomAllianceObservation::query()
                    ->where('alliance_id', $currentAlliance->id)
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
                'alliance_id' => $currentAlliance->id,
                'tracked_kingdom_alliance_id' => $tracking->id,
                'kingdom_alliance_id' => $reference->id,
                'actor_player_id' => $currentActor?->id,
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
                    'invalidated_by_player_id' => $currentActor?->id,
                    'invalidation_reason' => $correctionReason,
                ])->save();
            }

            $this->syncNeutralIdentity($reference);

            $metadata = [
                'observation_id' => (string) $observation->id,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $reference->id,
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
            $this->audit->record($event, $currentActor, $observation, $currentAlliance, $metadata);
            $this->outbox->record(
                $event,
                (string) $currentAlliance->id,
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
                $this->audit->record($correctionEvent, $currentActor, $corrects, $currentAlliance, $correctionMetadata);
                $this->outbox->record(
                    $correctionEvent,
                    (string) $currentAlliance->id,
                    $corrects,
                    $correctionMetadata,
                    $correctionEvent.':'.$corrects->id.':'.$observation->id,
                );
            }

            return $observation->load(['actor:id,current_name', 'invalidatedBy:id,current_name']);
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

        if ($latest instanceof KingdomAllianceObservation) {
            $reference->forceFill([
                'current_name' => $latest->observed_name,
                'current_tag' => $latest->observed_tag,
            ])->save();
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

    /** @param array<string,mixed> $provenance @return array<string,string|null> */
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
