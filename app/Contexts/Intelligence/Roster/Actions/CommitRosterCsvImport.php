<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Actions;

use App\Contexts\Alliance\Membership\Actions\UpsertRosterEntry;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Roster\Models\RosterImport;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class CommitRosterCsvImport
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private RosterEntryQuery $roster,
        private PlayerReferenceQuery $players,
        private UpsertRosterEntry $upsertRosterEntry,
        private RecordPlayerSnapshot $recordSnapshot,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<int|string, string> $resolutions */
    public function handle(string $allianceId, string $actorPlayerId, string $importId, array $resolutions): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $importId, $resolutions): string {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $import = RosterImport::query()->where('alliance_id', $allianceId)->lockForUpdate()->findOrFail($importId);
            if ($import->status === RosterImport::STATUS_COMMITTED) {
                return (string) $import->id;
            }
            if ($import->rejected_count > 0) {
                throw ValidationException::withMessages(['file' => 'Fix every rejected CSV row and create a new preview before committing.']);
            }

            $rows = $import->preview_payload['rows'] ?? null;
            if (! is_array($rows)) {
                throw new RuntimeException('Stored roster import preview is invalid.');
            }

            $normalizedResolutions = [];
            $created = $updated = $snapshotsCreated = 0;
            foreach ($rows as $storedRow) {
                if (! is_array($storedRow)) {
                    throw new RuntimeException('Stored roster import row is invalid.');
                }
                $rowNumber = (int) ($storedRow['row'] ?? 0);
                $outcome = (string) ($storedRow['outcome'] ?? '');
                $data = $storedRow['data'] ?? null;
                if ($rowNumber < 2 || ! is_array($data)) {
                    throw new RuntimeException('Stored roster import row metadata is invalid.');
                }

                $entryId = null;
                if ($outcome === 'update') {
                    $entryId = (string) ($storedRow['target_entry_id'] ?? '');
                    if ($entryId === '') {
                        throw new RuntimeException('Stored roster update target is missing.');
                    }
                } elseif ($outcome === 'ambiguous') {
                    $resolution = $resolutions[$rowNumber] ?? $resolutions[(string) $rowNumber] ?? null;
                    if (! is_string($resolution) || trim($resolution) === '') {
                        throw ValidationException::withMessages(['resolutions.'.$rowNumber => 'Choose whether this row creates a new player or updates one previewed candidate.']);
                    }
                    $resolution = trim($resolution);
                    if ($resolution === 'create') {
                        $normalizedResolutions[(string) $rowNumber] = 'create';
                    } else {
                        if (! in_array($resolution, $this->candidateIds($storedRow['candidates'] ?? null), true)) {
                            throw ValidationException::withMessages(['resolutions.'.$rowNumber => 'The selected roster match is not one of the previewed candidates.']);
                        }
                        $entryId = $resolution;
                        $normalizedResolutions[(string) $rowNumber] = $resolution;
                    }
                } elseif ($outcome === 'rejected') {
                    throw ValidationException::withMessages(['file' => 'Rejected rows cannot be committed.']);
                } elseif ($outcome !== 'create') {
                    throw new RuntimeException('Stored roster import outcome is invalid.');
                }

                $expectedPlayerId = null;
                $managerNotes = null;
                if ($entryId !== null) {
                    $existing = $this->roster->find($allianceId, $entryId);
                    if ($existing === null) {
                        throw ValidationException::withMessages(['file' => sprintf('Roster row %d changed after preview. Preview the CSV again.', $rowNumber)]);
                    }
                    $expectedPlayerId = $existing->playerId;
                    $managerNotes = $existing->managerNotes;
                    $stableId = $this->nullableString($data['game_player_id'] ?? null);
                    $player = $this->players->lockCurrent($existing->playerId);
                    if ($stableId !== null && $player->gamePlayerId !== $stableId) {
                        throw ValidationException::withMessages(['file' => sprintf('Roster row %d no longer matches the previewed stable game ID.', $rowNumber)]);
                    }
                }

                $state = RosterState::tryFrom((string) ($data['state'] ?? ''));
                if (! $state instanceof RosterState) {
                    throw new RuntimeException('Stored roster import state is invalid.');
                }
                $attributes = [
                    'name' => (string) ($data['name'] ?? ''),
                    'game_role' => $this->nullableString($data['game_role'] ?? null),
                    'state' => $state,
                    'joined_at' => $this->nullableString($data['joined_at'] ?? null),
                    'manager_notes' => $managerNotes,
                ];
                if ($entryId === null) {
                    $attributes['game_player_id'] = $this->nullableString($data['game_player_id'] ?? null);
                }

                $entry = $this->upsertRosterEntry->handle(
                    $actorPlayerId, $allianceId, $attributes, $entryId, 'csv', (string) $import->id, $expectedPlayerId,
                );
                $entryId === null ? $created++ : $updated++;

                $snapshot = $this->recordSnapshot->handle(
                    $allianceId, $actorPlayerId, $entry->rosterEntryId,
                    [
                        'observed_name' => (string) ($data['name'] ?? ''),
                        'power' => (string) ($data['power'] ?? ''),
                        'progression_level' => $this->nullableString($data['progression_level'] ?? null),
                        'observed_alliance_tag' => $this->nullableString($data['alliance_tag'] ?? null),
                        'captured_at' => (string) ($data['captured_at'] ?? ''),
                    ],
                    'csv', (string) $import->id,
                );
                if ($snapshot->created) {
                    $snapshotsCreated++;
                }
            }

            $summary = ['rows_committed' => count($rows), 'roster_entries_created' => $created, 'roster_entries_updated' => $updated, 'snapshots_created' => $snapshotsCreated];
            $import->forceFill([
                'status' => RosterImport::STATUS_COMMITTED,
                'committed_by_player_id' => $actorPlayerId,
                'resolution_payload' => $normalizedResolutions,
                'committed_summary' => $summary,
                'committed_at' => now(),
            ])->save();

            $metadata = ['import_id' => (string) $import->id, 'schema_version' => (string) $import->schema_version, 'checksum' => (string) $import->checksum] + $summary;
            $event = 'intelligence.roster_import_committed';
            $this->audit->record($event, $actor, $import, $allianceId, $metadata);
            $this->outbox->record($event, $allianceId, $import, $metadata, $event.':'.$import->id);

            return (string) $import->id;
        });
    }

    /** @return list<string> */
    private function candidateIds(mixed $candidates): array
    {
        if (! is_array($candidates)) {
            return [];
        }
        $ids = [];
        foreach ($candidates as $candidate) {
            if (is_array($candidate) && isset($candidate['entry_id']) && is_string($candidate['entry_id'])) {
                $ids[] = $candidate['entry_id'];
            }
        }

        return $ids;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
