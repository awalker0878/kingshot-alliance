<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Roster\Models\RosterImport;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class CommitRosterCsvImport
{
    public function __construct(
        private AllianceIntelligenceAuthorization $authorization,
        private SaveRosterEntry $saveRosterEntry,
        private RecordPlayerSnapshot $recordSnapshot,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<int|string, string> $resolutions */
    public function handle(Alliance $alliance, Player $actor, string $importId, array $resolutions): RosterImport
    {
        if (! $this->authorization->allows($actor, $alliance, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $importId, $resolutions): RosterImport {
            $import = RosterImport::query()
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->findOrFail($importId);

            if ($import->status === RosterImport::STATUS_COMMITTED) {
                return $import->refresh();
            }

            if ($import->rejected_count > 0) {
                throw ValidationException::withMessages([
                    'file' => 'Fix every rejected CSV row and create a new preview before committing.',
                ]);
            }

            $rows = $import->preview_payload['rows'] ?? null;
            if (! is_array($rows)) {
                throw new RuntimeException('Stored roster import preview is invalid.');
            }

            $normalizedResolutions = [];
            $created = 0;
            $updated = 0;
            $snapshotsCreated = 0;

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
                } elseif ($outcome === 'create') {
                    $this->assertCreateStillValid($alliance, $this->nullableString($data['game_player_id'] ?? null));
                } elseif ($outcome === 'ambiguous') {
                    $resolution = $resolutions[$rowNumber] ?? $resolutions[(string) $rowNumber] ?? null;
                    if (! is_string($resolution) || trim($resolution) === '') {
                        throw ValidationException::withMessages([
                            'resolutions.'.$rowNumber => 'Choose whether this row creates a new player or updates one previewed candidate.',
                        ]);
                    }

                    $resolution = trim($resolution);
                    if ($resolution === 'create') {
                        $normalizedResolutions[(string) $rowNumber] = 'create';
                    } else {
                        $candidateIds = $this->candidateIds($storedRow['candidates'] ?? null);
                        if (! in_array($resolution, $candidateIds, true)) {
                            throw ValidationException::withMessages([
                                'resolutions.'.$rowNumber => 'The selected roster match is not one of the previewed candidates.',
                            ]);
                        }

                        $entryId = $resolution;
                        $normalizedResolutions[(string) $rowNumber] = $resolution;
                    }
                } elseif ($outcome === 'rejected') {
                    throw ValidationException::withMessages([
                        'file' => 'Rejected rows cannot be committed.',
                    ]);
                } else {
                    throw new RuntimeException('Stored roster import outcome is invalid.');
                }

                $existing = null;
                if ($entryId !== null) {
                    $existing = AllianceRosterEntry::query()
                        ->where('alliance_id', $alliance->id)
                        ->with('player:id,current_kingdom_id,game_player_id,current_name')
                        ->lockForUpdate()
                        ->find($entryId);

                    if (! $existing instanceof AllianceRosterEntry) {
                        throw ValidationException::withMessages([
                            'file' => sprintf('Roster row %d changed after preview. Preview the CSV again.', $rowNumber),
                        ]);
                    }

                    $stableId = $this->nullableString($data['game_player_id'] ?? null);
                    if ($stableId !== null && $existing->player->game_player_id !== $stableId) {
                        throw ValidationException::withMessages([
                            'file' => sprintf('Roster row %d no longer matches the previewed stable game ID.', $rowNumber),
                        ]);
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
                    'manager_notes' => $existing?->manager_notes,
                ];

                if ($entryId === null) {
                    $attributes['game_player_id'] = $this->nullableString($data['game_player_id'] ?? null);
                }

                $entry = $this->saveRosterEntry->handle(
                    $alliance,
                    $actor,
                    $attributes,
                    $entryId,
                    'csv',
                    (string) $import->id,
                );

                if ($entryId === null) {
                    $created++;
                } else {
                    $updated++;
                }

                $snapshot = $this->recordSnapshot->handle(
                    $alliance,
                    $actor,
                    (string) $entry->id,
                    [
                        'observed_name' => (string) ($data['name'] ?? ''),
                        'power' => (string) ($data['power'] ?? ''),
                        'progression_level' => $this->nullableString($data['progression_level'] ?? null),
                        'observed_alliance_tag' => $this->nullableString($data['alliance_tag'] ?? null),
                        'captured_at' => (string) ($data['captured_at'] ?? ''),
                    ],
                    'csv',
                    (string) $import->id,
                );

                if ($snapshot->wasRecentlyCreated) {
                    $snapshotsCreated++;
                }
            }

            $summary = [
                'rows_committed' => count($rows),
                'roster_entries_created' => $created,
                'roster_entries_updated' => $updated,
                'snapshots_created' => $snapshotsCreated,
            ];

            $import->forceFill([
                'status' => RosterImport::STATUS_COMMITTED,
                'committed_by_player_id' => $actor->id,
                'resolution_payload' => $normalizedResolutions,
                'committed_summary' => $summary,
                'committed_at' => now(),
            ])->save();

            $metadata = [
                'import_id' => (string) $import->id,
                'schema_version' => (string) $import->schema_version,
                'checksum' => (string) $import->checksum,
            ] + $summary;

            $event = 'intelligence.roster_import_committed';
            $this->audit->record($event, $actor, $import, $alliance, $metadata);
            $this->outbox->record(
                $event,
                (string) $alliance->id,
                $import,
                $metadata,
                $event.':'.$import->id,
            );

            return $import->refresh();
        });
    }

    private function assertCreateStillValid(Alliance $alliance, ?string $stableId): void
    {
        if ($stableId === null || $alliance->kingdom_id === null) {
            return;
        }

        $player = Player::query()
            ->where('current_kingdom_id', $alliance->kingdom_id)
            ->where('game_player_id', $stableId)
            ->first();

        if ($player instanceof Player && AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $player->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'file' => 'The roster changed after preview. Preview the CSV again before committing.',
            ]);
        }
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
