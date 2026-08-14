<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\PlayerSnapshot;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

final readonly class RecordPlayerSnapshot
{
    private const MAX_POWER = '9223372036854775807';

    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  array{
     *   observed_name: string,
     *   power: string,
     *   progression_level?: string|null,
     *   observed_alliance_tag?: string|null,
     *   captured_at: string
     * }  $attributes
     * @param  array{
     *   subscription_id: string,
     *   batch_id: string,
     *   adapter_key: string,
     *   adapter_version: string,
     *   source_record_id?: string|null,
     *   identity_hash: string,
     *   payload_hash: string
     * }|null  $machineProvenance
     */
    public function handle(
        Alliance $alliance,
        ?Player $actor,
        string $entryId,
        array $attributes,
        string $source = 'manual',
        ?string $importId = null,
        ?array $machineProvenance = null,
    ): PlayerSnapshot {
        if (! in_array($source, ['manual', 'csv', 'ingestion'], true)) {
            throw new InvalidArgumentException('Unsupported snapshot source.');
        }

        if ($source === 'ingestion') {
            if ($actor !== null || $importId !== null || $machineProvenance === null) {
                throw new InvalidArgumentException('Automated snapshots require machine provenance and no Player/import actor.');
            }
        } else {
            if (! $actor instanceof Player || $machineProvenance !== null) {
                throw new InvalidArgumentException('Human snapshot sources require a Player actor and no machine provenance.');
            }

            if (! $this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage)) {
                throw new AuthorizationException;
            }
        }

        $provenance = $source === 'ingestion'
            ? $this->machineProvenance($machineProvenance)
            : $this->emptyMachineProvenance();

        return DB::transaction(function () use (
            $alliance,
            $actor,
            $entryId,
            $attributes,
            $source,
            $importId,
            $provenance,
        ): PlayerSnapshot {
            $entry = AllianceRosterEntry::query()
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->findOrFail($entryId);

            $capturedAt = $this->capturedAt($attributes['captured_at']);
            $observedName = $this->requiredLine($attributes['observed_name'], 160, 'observed_name');
            $power = $this->power($attributes['power']);
            $progressionLevel = $this->nullableLine($attributes['progression_level'] ?? null, 64, 'progression_level');
            $observedAllianceTag = $this->nullableLine($attributes['observed_alliance_tag'] ?? null, 32, 'observed_alliance_tag');
            $idempotencyPayload = [
                'alliance_id' => (string) $alliance->id,
                'roster_entry_id' => (string) $entry->id,
                'player_id' => (string) $entry->player_id,
                'observed_name' => $observedName,
                'power' => $power,
                'progression_level' => $progressionLevel,
                'observed_alliance_tag' => $observedAllianceTag,
                'captured_at' => $capturedAt->format('Y-m-d\\TH:i:s.u\\Z'),
                'source' => $source,
            ];
            if ($source === 'ingestion') {
                $idempotencyPayload['source_identity_hash'] = $provenance['identity_hash'];
            }
            $idempotencyKey = hash('sha256', json_encode($idempotencyPayload, JSON_THROW_ON_ERROR));

            $snapshot = PlayerSnapshot::query()->firstOrCreate(
                [
                    'alliance_id' => $alliance->id,
                    'idempotency_key' => $idempotencyKey,
                ],
                [
                    'roster_entry_id' => $entry->id,
                    'player_id' => $entry->player_id,
                    'actor_player_id' => $actor?->id,
                    'roster_import_id' => $importId,
                    'observed_name' => $observedName,
                    'power' => $power,
                    'progression_level' => $progressionLevel,
                    'observed_alliance_tag' => $observedAllianceTag,
                    'captured_at' => $capturedAt,
                    'source' => $source,
                    'source_subscription_id' => $provenance['subscription_id'],
                    'source_batch_id' => $provenance['batch_id'],
                    'source_adapter_key' => $provenance['adapter_key'],
                    'source_adapter_version' => $provenance['adapter_version'],
                    'source_record_id' => $provenance['source_record_id'],
                    'source_identity_hash' => $provenance['identity_hash'],
                    'source_payload_hash' => $provenance['payload_hash'],
                ],
            );

            if ($snapshot->wasRecentlyCreated) {
                $metadata = [
                    'snapshot_id' => (string) $snapshot->id,
                    'roster_entry_id' => (string) $entry->id,
                    'player_id' => (string) $entry->player_id,
                    'captured_at' => $capturedAt->toIso8601String(),
                    'source' => $source,
                    'import_id' => $importId,
                    'source_subscription_id' => $provenance['subscription_id'],
                    'source_batch_id' => $provenance['batch_id'],
                    'source_adapter_key' => $provenance['adapter_key'],
                    'source_adapter_version' => $provenance['adapter_version'],
                    'source_record_id' => $provenance['source_record_id'],
                    'source_identity_hash' => $provenance['identity_hash'],
                    'source_payload_hash' => $provenance['payload_hash'],
                ];

                $event = 'kingdoms.player_snapshot_recorded';
                $this->audit->record($event, $actor, $snapshot, $alliance, $metadata);
                $this->outbox->record(
                    $event,
                    (string) $alliance->id,
                    $snapshot,
                    $metadata,
                    $event.':'.$snapshot->id,
                );
            }

            return $snapshot->load('actor:id,current_name');
        });
    }

    private function capturedAt(string $value): Carbon
    {
        try {
            $capturedAt = Carbon::parse($value)->utc();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'captured_at' => 'The snapshot capture time is invalid.',
            ]);
        }

        if ($capturedAt->isAfter(now()->addMinutes(5))) {
            throw ValidationException::withMessages([
                'captured_at' => 'The snapshot capture time cannot be more than five minutes in the future.',
            ]);
        }

        return $capturedAt;
    }

    private function power(string $value): string
    {
        if (preg_match('/^\\d{1,19}$/', $value) !== 1) {
            throw ValidationException::withMessages([
                'power' => 'Power must contain 1-19 digits.',
            ]);
        }

        $canonical = ltrim($value, '0');
        $canonical = $canonical === '' ? '0' : $canonical;

        if (
            strlen($canonical) > strlen(self::MAX_POWER)
            || (strlen($canonical) === strlen(self::MAX_POWER) && strcmp($canonical, self::MAX_POWER) > 0)
        ) {
            throw ValidationException::withMessages([
                'power' => 'Power exceeds the supported signed 64-bit integer range.',
            ]);
        }

        return $canonical;
    }

    private function requiredLine(string $value, int $max, string $field): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw ValidationException::withMessages([
                $field => 'The snapshot text is missing or too long.',
            ]);
        }

        return $value;
    }

    private function nullableLine(?string $value, int $max, string $field): ?string
    {
        $value = $value === null ? null : trim($value);
        if ($value === '' || $value === null) {
            return null;
        }

        if (mb_strlen($value) > $max) {
            throw ValidationException::withMessages([
                $field => 'The snapshot text is too long.',
            ]);
        }

        return $value;
    }

    /**
     * @param  array{
     *   subscription_id: string,
     *   batch_id: string,
     *   adapter_key: string,
     *   adapter_version: string,
     *   source_record_id?: string|null,
     *   identity_hash: string,
     *   payload_hash: string
     * }  $provenance
     * @return array{
     *   subscription_id: string,
     *   batch_id: string,
     *   adapter_key: string,
     *   adapter_version: string,
     *   source_record_id: string|null,
     *   identity_hash: string,
     *   payload_hash: string
     * }
     */
    private function machineProvenance(array $provenance): array
    {
        $subscriptionId = $this->provenanceText($provenance['subscription_id'] ?? null, 26, 'subscription_id');
        $batchId = $this->provenanceText($provenance['batch_id'] ?? null, 26, 'batch_id');
        $adapterKey = $this->provenanceText($provenance['adapter_key'] ?? null, 80, 'adapter_key');
        $adapterVersion = $this->provenanceText($provenance['adapter_version'] ?? null, 40, 'adapter_version');
        $sourceRecordId = $this->nullableProvenanceText($provenance['source_record_id'] ?? null, 191, 'source_record_id');
        $identityHash = $this->hash($provenance['identity_hash'] ?? null, 'identity_hash');
        $payloadHash = $this->hash($provenance['payload_hash'] ?? null, 'payload_hash');

        return [
            'subscription_id' => $subscriptionId,
            'batch_id' => $batchId,
            'adapter_key' => $adapterKey,
            'adapter_version' => $adapterVersion,
            'source_record_id' => $sourceRecordId,
            'identity_hash' => $identityHash,
            'payload_hash' => $payloadHash,
        ];
    }

    /**
     * @return array{
     *   subscription_id: null,
     *   batch_id: null,
     *   adapter_key: null,
     *   adapter_version: null,
     *   source_record_id: null,
     *   identity_hash: null,
     *   payload_hash: null
     * }
     */
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
            throw new InvalidArgumentException('Automated snapshot provenance '.$field.' must be text.');
        }

        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException('Automated snapshot provenance '.$field.' is missing or too long.');
        }

        return $value;
    }

    private function nullableProvenanceText(mixed $value, int $max, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->provenanceText($value, $max, $field);
    }

    private function hash(mixed $value, string $field): string
    {
        if (! is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new InvalidArgumentException('Automated snapshot provenance '.$field.' must be a SHA-256 hex digest.');
        }

        return $value;
    }
}
