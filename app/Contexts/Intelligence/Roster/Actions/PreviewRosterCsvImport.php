<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Actions;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Roster\Models\RosterImport;
use App\Contexts\Intelligence\Roster\Services\RosterCsvParser;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final readonly class PreviewRosterCsvImport
{
    public function __construct(
        private AllianceIntelligenceAuthorization $authorization,
        private RosterCsvParser $parser,
        private AuditRecorder $audit,
    ) {}

    public function handle(Alliance $alliance, Player $actor, UploadedFile $file): RosterImport
    {
        if (! $this->authorization->allows($actor, $alliance, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        if ($alliance->kingdom_id === null) {
            throw ValidationException::withMessages([
                'file' => 'The alliance must have a Kingdom before roster data can be imported.',
            ]);
        }

        $parsed = $this->parser->parse($file, now());
        $entries = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->with('player:id,current_kingdom_id,game_player_id,current_name')
            ->get();

        $byStableId = [];
        $byName = [];

        foreach ($entries as $entry) {
            $stableId = $entry->player->game_player_id;
            if ($stableId !== null && $stableId !== '') {
                $byStableId[$stableId] = $entry;
            }

            $byName[$this->normalizedName((string) $entry->observed_name)][] = $entry;
        }

        $previewRows = [];
        $createCount = 0;
        $updateCount = 0;
        $ambiguousCount = 0;
        $rejectedCount = 0;

        foreach ($parsed['rows'] as $row) {
            $data = $row['data'];
            $errors = $row['errors'];
            $outcome = 'create';
            $targetEntryId = null;
            $candidates = [];

            if ($errors !== []) {
                $outcome = 'rejected';
                $rejectedCount++;
            } elseif ($data['game_player_id'] !== null) {
                $target = $byStableId[$data['game_player_id']] ?? null;
                if ($target instanceof AllianceRosterEntry) {
                    $outcome = 'update';
                    $targetEntryId = (string) $target->id;
                    $updateCount++;
                } else {
                    $createCount++;
                }
            } else {
                $nameCandidates = $byName[$this->normalizedName($data['name'])] ?? [];
                if ($nameCandidates === []) {
                    $createCount++;
                } else {
                    $outcome = 'ambiguous';
                    $ambiguousCount++;

                    foreach ($nameCandidates as $candidate) {
                        $candidates[] = [
                            'entry_id' => (string) $candidate->id,
                            'name' => (string) $candidate->observed_name,
                            'game_player_id' => $candidate->player->game_player_id,
                            'state' => $candidate->state->value,
                        ];
                    }
                }
            }

            $previewRows[] = [
                'row' => $row['row'],
                'data' => $data,
                'outcome' => $outcome,
                'target_entry_id' => $targetEntryId,
                'candidates' => $candidates,
                'errors' => $errors,
            ];
        }

        $payload = [
            'schema_version' => RosterCsvParser::SCHEMA_VERSION,
            'headers' => RosterCsvParser::HEADERS,
            'rows' => $previewRows,
        ];

        $import = RosterImport::query()->firstOrCreate(
            [
                'alliance_id' => $alliance->id,
                'schema_version' => RosterCsvParser::SCHEMA_VERSION,
                'checksum' => $parsed['checksum'],
            ],
            [
                'created_by_player_id' => $actor->id,
                'status' => RosterImport::STATUS_PREVIEWED,
                'original_filename' => $parsed['filename'],
                'row_count' => count($previewRows),
                'create_count' => $createCount,
                'update_count' => $updateCount,
                'ambiguous_count' => $ambiguousCount,
                'rejected_count' => $rejectedCount,
                'preview_payload' => $payload,
            ],
        );

        if ($import->status === RosterImport::STATUS_COMMITTED) {
            return $import->refresh();
        }

        $import->forceFill([
            'status' => RosterImport::STATUS_PREVIEWED,
            'original_filename' => $parsed['filename'],
            'row_count' => count($previewRows),
            'create_count' => $createCount,
            'update_count' => $updateCount,
            'ambiguous_count' => $ambiguousCount,
            'rejected_count' => $rejectedCount,
            'preview_payload' => $payload,
            'resolution_payload' => null,
            'committed_summary' => null,
            'committed_by_player_id' => null,
            'committed_at' => null,
        ])->save();

        $this->audit->record('intelligence.roster_import_previewed', $actor, $import, $alliance, [
            'import_id' => (string) $import->id,
            'schema_version' => RosterCsvParser::SCHEMA_VERSION,
            'checksum' => $parsed['checksum'],
            'row_count' => count($previewRows),
            'create_count' => $createCount,
            'update_count' => $updateCount,
            'ambiguous_count' => $ambiguousCount,
            'rejected_count' => $rejectedCount,
        ]);

        return $import->refresh();
    }

    private function normalizedName(string $name): string
    {
        return mb_strtolower(trim($name), 'UTF-8');
    }
}
