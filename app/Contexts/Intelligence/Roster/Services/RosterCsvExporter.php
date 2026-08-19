<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Services;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use RuntimeException;

final readonly class RosterCsvExporter
{
    public function __construct(
        private AllianceReferenceQuery $alliances,
        private RosterEntryQuery $roster,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
    ) {}

    /** @return array{content:string,filename:string,row_count:int,checksum:string} */
    public function export(string $allianceId, string $actorPlayerId, bool $includePrivate): array
    {
        $alliance = $this->alliances->require($allianceId);
        $actor = $this->players->require($actorPlayerId);
        $entries = $this->roster->activeOrTracked($allianceId);
        $playerRefs = $this->players->byIds(array_map(static fn (RosterEntryReference $entry): string => $entry->playerId, $entries));
        $latest = $this->latestSnapshots($allianceId, array_map(static fn (RosterEntryReference $entry): string => $entry->rosterEntryId, $entries));

        $headers = RosterCsvParser::HEADERS;
        if ($includePrivate) {
            $headers[] = 'player_id';
            $headers[] = 'manager_notes';
        }

        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            throw new RuntimeException('Unable to allocate roster export buffer.');
        }
        fputcsv($handle, $headers, ',', '"', '');

        foreach ($entries as $entry) {
            $row = $this->row($entry, $playerRefs[$entry->playerId] ?? null, $latest[$entry->rosterEntryId] ?? null);
            if ($includePrivate) {
                $row['player_id'] = $entry->playerId;
                $row['manager_notes'] = $entry->managerNotes ?? '';
            }
            fputcsv($handle, array_map(fn (string $header): string => $this->safeCell((string) ($row[$header] ?? '')), $headers), ',', '"', '');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        if (! is_string($content)) {
            throw new RuntimeException('Unable to read roster export buffer.');
        }

        $checksum = hash('sha256', $content);
        $metadata = [
            'schema_version' => RosterCsvParser::SCHEMA_VERSION,
            'row_count' => count($entries),
            'include_private' => $includePrivate,
            'checksum' => $checksum,
        ];
        $this->audit->record('intelligence.roster_exported', $actor, null, $allianceId, $metadata);

        return [
            'content' => $content,
            'filename' => sprintf('%s-roster%s.csv', $alliance->slug, $includePrivate ? '-management' : ''),
            'row_count' => count($entries),
            'checksum' => $checksum,
        ];
    }

    /**
     * @param  list<string>  $entryIds
     * @return array<string, PlayerSnapshot>
     */
    private function latestSnapshots(string $allianceId, array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }
        $snapshots = PlayerSnapshot::query()
            ->where('alliance_id', $allianceId)
            ->whereIn('roster_entry_id', $entryIds)
            ->whereRaw(
                'player_snapshots.id = (select latest.id from player_snapshots as latest '
                .'where latest.alliance_id = player_snapshots.alliance_id '
                .'and latest.roster_entry_id = player_snapshots.roster_entry_id '
                .'order by latest.captured_at desc, latest.id desc limit 1)'
            )->get();
        $result = [];
        foreach ($snapshots as $snapshot) {
            $result[(string) $snapshot->roster_entry_id] = $snapshot;
        }

        return $result;
    }

    /** @return array<string,string> */
    private function row(RosterEntryReference $entry, ?PlayerReference $player, ?PlayerSnapshot $snapshot): array
    {
        return [
            'game_player_id' => $player instanceof PlayerReference ? ($player->gamePlayerId ?? '') : '',
            'name' => $snapshot?->observed_name === null ? $entry->observedName : (string) $snapshot->observed_name,
            'power' => $snapshot === null ? '' : (string) $snapshot->power,
            'progression_level' => $snapshot instanceof PlayerSnapshot ? ($snapshot->progression_level ?? '') : '',
            'alliance_tag' => $snapshot instanceof PlayerSnapshot ? ($snapshot->observed_alliance_tag ?? '') : '',
            'game_role' => $entry->gameRole ?? '',
            'state' => $entry->stateObservedAtRead->value,
            'joined_at' => $entry->joinedAt ?? '',
            'captured_at' => $snapshot?->captured_at?->toIso8601String() ?? '',
        ];
    }

    private function safeCell(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $trimmed = ltrim($value, " \t\r\n");
        $first = $trimmed === '' ? '' : $trimmed[0];
        if (in_array($first, ['=', '+', '-', '@'], true) || str_starts_with($value, "\t") || str_starts_with($value, "\r") || str_starts_with($value, "\n")) {
            return "'".$value;
        }

        return $value;
    }
}
