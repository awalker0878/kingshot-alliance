<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\PlayerSnapshot;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class RecordPlayerSnapshot
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
     *   power: string,
     *   progression_level?: string|null,
     *   observed_alliance_tag?: string|null,
     *   captured_at: string
     * } $attributes
     */
    public function handle(
        Alliance $alliance,
        User $actor,
        string $entryId,
        array $attributes,
        string $source = 'manual',
        ?string $importId = null,
    ): PlayerSnapshot {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        if (! in_array($source, ['manual', 'csv'], true)) {
            throw new InvalidArgumentException('Unsupported snapshot source.');
        }

        return DB::transaction(function () use ($alliance, $actor, $entryId, $attributes, $source, $importId): PlayerSnapshot {
            $entry = AllianceRosterEntry::query()
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->findOrFail($entryId);

            $capturedAt = Carbon::parse($attributes['captured_at'])->utc();
            if ($capturedAt->isAfter(now()->addMinutes(5))) {
                throw ValidationException::withMessages([
                    'captured_at' => 'The snapshot capture time cannot be more than five minutes in the future.',
                ]);
            }

            $observedName = trim($attributes['observed_name']);
            $power = $this->power($attributes['power']);
            $progressionLevel = $this->nullableLine($attributes['progression_level'] ?? null);
            $observedAllianceTag = $this->nullableLine($attributes['observed_alliance_tag'] ?? null);
            $idempotencyKey = hash('sha256', json_encode([
                'alliance_id' => (string) $alliance->id,
                'roster_entry_id' => (string) $entry->id,
                'kingdom_player_id' => (string) $entry->kingdom_player_id,
                'observed_name' => $observedName,
                'power' => $power,
                'progression_level' => $progressionLevel,
                'observed_alliance_tag' => $observedAllianceTag,
                'captured_at' => $capturedAt->format('Y-m-d\TH:i:s.u\Z'),
                'source' => $source,
            ], JSON_THROW_ON_ERROR));

            $snapshot = PlayerSnapshot::query()->firstOrCreate(
                [
                    'alliance_id' => $alliance->id,
                    'idempotency_key' => $idempotencyKey,
                ],
                [
                    'roster_entry_id' => $entry->id,
                    'kingdom_player_id' => $entry->kingdom_player_id,
                    'actor_user_id' => $actor->id,
                    'roster_import_id' => $importId,
                    'observed_name' => $observedName,
                    'power' => $power,
                    'progression_level' => $progressionLevel,
                    'observed_alliance_tag' => $observedAllianceTag,
                    'captured_at' => $capturedAt,
                    'source' => $source,
                ],
            );

            if ($snapshot->wasRecentlyCreated) {
                $metadata = [
                    'snapshot_id' => (string) $snapshot->id,
                    'roster_entry_id' => (string) $entry->id,
                    'kingdom_player_id' => (string) $entry->kingdom_player_id,
                    'captured_at' => $capturedAt->toIso8601String(),
                    'source' => $source,
                    'import_id' => $importId,
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

            return $snapshot->load('actor:id,name');
        });
    }

    private function power(string $value): string
    {
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

    private function nullableLine(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
