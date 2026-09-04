<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Actions;

use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Roster\Models\AllianceRosterObservation;
use App\Contexts\Intelligence\Roster\Models\AllianceRosterObservationBatch;
use App\Contexts\Intelligence\Roster\ValueObjects\AllianceRosterObservationBatchReceipt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordAllianceRosterObservationBatch
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<array<string,mixed>> $rows */
    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $sourceEvidenceId,
        string $sourceReviewId,
        string $schemaVersion,
        string $capturedAt,
        array $rows,
        string $idempotencyKey,
    ): AllianceRosterObservationBatchReceipt {
        return DB::transaction(function () use ($actorPlayerId, $allianceId, $sourceEvidenceId, $sourceReviewId, $schemaVersion, $capturedAt, $rows, $idempotencyKey): AllianceRosterObservationBatchReceipt {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);

            $existing = AllianceRosterObservationBatch::query()
                ->where('destination_idempotency_key', $idempotencyKey)
                ->withCount('observations')
                ->lockForUpdate()
                ->first();
            if ($existing instanceof AllianceRosterObservationBatch) {
                if ((string) $existing->alliance_id !== $allianceId) {
                    throw ValidationException::withMessages(['evidence' => 'The destination receipt belongs to another Alliance.']);
                }
                return new AllianceRosterObservationBatchReceipt((string) $existing->id, (int) $existing->observations_count);
            }

            $captured = Carbon::parse($capturedAt);
            $batch = AllianceRosterObservationBatch::query()->create([
                'alliance_id' => $allianceId,
                'source_evidence_id' => $sourceEvidenceId,
                'source_review_id' => $sourceReviewId,
                'schema_version' => $schemaVersion,
                'captured_at' => $captured,
                'destination_idempotency_key' => $idempotencyKey,
                'accepted_by_player_id' => $actorPlayerId,
                'accepted_at' => now(),
            ]);

            foreach ($rows as $index => $row) {
                $rosterEntryId = isset($row['roster_entry_id']) && $row['roster_entry_id'] !== null ? (string) $row['roster_entry_id'] : null;
                if ($rosterEntryId !== null && ! AllianceRosterEntry::query()->whereKey($rosterEntryId)->where('alliance_id', $allianceId)->exists()) {
                    throw ValidationException::withMessages(["rows.{$index}.roster_entry_id" => 'The reviewed roster link no longer belongs to this Alliance.']);
                }

                AllianceRosterObservation::query()->create([
                    'batch_id' => $batch->id,
                    'alliance_id' => $allianceId,
                    'roster_entry_id' => $rosterEntryId,
                    'observed_name' => (string) $row['observed_name'],
                    'game_player_id' => $row['game_player_id'] ?? null,
                    'observed_rank' => $row['observed_rank'] ?? null,
                    'power' => $row['power'] ?? null,
                    'source_metadata' => $row['source_metadata'] ?? null,
                ]);
            }

            $metadata = [
                'batch_id' => (string) $batch->id,
                'source_evidence_id' => $sourceEvidenceId,
                'source_review_id' => $sourceReviewId,
                'captured_at' => $captured->toIso8601String(),
                'row_count' => count($rows),
            ];
            $this->audit->record('roster.observation_batch_recorded', $actor, $batch, $allianceId, $metadata);
            $this->outbox->record('roster.observation_batch_recorded', $allianceId, $batch, $metadata);

            return new AllianceRosterObservationBatchReceipt((string) $batch->id, count($rows));
        });
    }
}
