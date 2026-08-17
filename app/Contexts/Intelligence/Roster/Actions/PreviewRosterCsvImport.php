<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Actions;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Roster\Models\RosterImport;
use App\Contexts\Intelligence\Roster\Services\RosterCsvParser;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PreviewRosterCsvImport
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private AllianceReferenceQuery $alliances,
        private RosterEntryQuery $roster,
        private PlayerReferenceQuery $players,
        private RosterCsvParser $parser,
        private AuditRecorder $audit,
    ) {}

    /** @return array{importId:string,status:string} */
    public function handle(string $allianceId, string $actorPlayerId, UploadedFile $file): array
    {
        $parsed = $this->parser->parse($file, now());

        return DB::transaction(function () use ($allianceId, $actorPlayerId, $parsed): array {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $alliance = $this->alliances->lockCurrent($allianceId);
            if ($alliance->kingdomId === '') {
                throw ValidationException::withMessages(['file' => 'The alliance must have a Kingdom before roster data can be imported.']);
            }

            $entries = $this->roster->all($allianceId);
            $playerRefs = $this->players->byIds(array_values(array_unique(array_map(
                static fn ($entry): string => $entry->playerId,
                $entries,
            ))));
            $byStableId = [];
            $byName = [];
            foreach ($entries as $entry) {
                $player = $playerRefs[$entry->playerId] ?? null;
                if ($player?->gamePlayerId !== null && $player->gamePlayerId !== '') {
                    $byStableId[$player->gamePlayerId] = $entry;
                }
                $byName[$this->normalizedName($entry->observedName)][] = $entry;
            }

            $previewRows = [];
            $createCount = $updateCount = $ambiguousCount = $rejectedCount = 0;
            foreach ($parsed['rows'] as $row) {
                $data = $row['data'];
                $errors = $row['errors'];
                $outcome = 'create';
                $targetEntryId = null;
                $candidates = [];

                if ($errors !== []) {
                    $outcome = 'rejected';
                    $rejectedCount++;
                } elseif ($data['game_player_id'] !== null && isset($byStableId[$data['game_player_id']])) {
                    $target = $byStableId[$data['game_player_id']];
                    $outcome = 'update';
                    $targetEntryId = $target->rosterEntryId;
                    $updateCount++;
                } elseif ($data['game_player_id'] !== null) {
                    $createCount++;
                } else {
                    $nameCandidates = $byName[$this->normalizedName($data['name'])] ?? [];
                    if ($nameCandidates === []) {
                        $createCount++;
                    } else {
                        $outcome = 'ambiguous';
                        $ambiguousCount++;
                        foreach ($nameCandidates as $candidate) {
                            $candidatePlayer = $playerRefs[$candidate->playerId] ?? null;
                            $candidates[] = [
                                'entry_id' => $candidate->rosterEntryId,
                                'name' => $candidate->observedName,
                                'game_player_id' => $candidatePlayer?->gamePlayerId,
                                'state' => $candidate->stateObservedAtRead->value,
                            ];
                        }
                    }
                }

                $previewRows[] = [
                    'row' => $row['row'], 'data' => $data, 'outcome' => $outcome,
                    'target_entry_id' => $targetEntryId, 'candidates' => $candidates, 'errors' => $errors,
                ];
            }

            $payload = ['schema_version' => RosterCsvParser::SCHEMA_VERSION, 'headers' => RosterCsvParser::HEADERS, 'rows' => $previewRows];
            $import = RosterImport::query()->firstOrCreate(
                ['alliance_id' => $allianceId, 'schema_version' => RosterCsvParser::SCHEMA_VERSION, 'checksum' => $parsed['checksum']],
                [
                    'created_by_player_id' => $actorPlayerId, 'status' => RosterImport::STATUS_PREVIEWED,
                    'original_filename' => $parsed['filename'], 'row_count' => count($previewRows),
                    'create_count' => $createCount, 'update_count' => $updateCount,
                    'ambiguous_count' => $ambiguousCount, 'rejected_count' => $rejectedCount,
                    'preview_payload' => $payload,
                ],
            );

            if ($import->status !== RosterImport::STATUS_COMMITTED) {
                $import->forceFill([
                    'status' => RosterImport::STATUS_PREVIEWED, 'original_filename' => $parsed['filename'],
                    'row_count' => count($previewRows), 'create_count' => $createCount, 'update_count' => $updateCount,
                    'ambiguous_count' => $ambiguousCount, 'rejected_count' => $rejectedCount, 'preview_payload' => $payload,
                    'resolution_payload' => null, 'committed_summary' => null, 'committed_by_player_id' => null, 'committed_at' => null,
                ])->save();
            }

            $this->audit->record('intelligence.roster_import_previewed', $actor, $import, $allianceId, [
                'import_id' => (string) $import->id, 'schema_version' => RosterCsvParser::SCHEMA_VERSION,
                'checksum' => $parsed['checksum'], 'row_count' => count($previewRows), 'create_count' => $createCount,
                'update_count' => $updateCount, 'ambiguous_count' => $ambiguousCount, 'rejected_count' => $rejectedCount,
            ]);

            return ['importId' => (string) $import->id, 'status' => (string) $import->status];
        });
    }

    private function normalizedName(string $name): string
    {
        return mb_strtolower(trim($name), 'UTF-8');
    }
}
