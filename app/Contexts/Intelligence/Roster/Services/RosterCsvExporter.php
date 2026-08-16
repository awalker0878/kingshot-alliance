<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Services;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\Contexts\Intelligence\Roster\Queries\PlayerSnapshotQuery;
use App\Contexts\Intelligence\Roster\Queries\RosterQuery;
use App\Shared\Audit\Services\AuditRecorder;
use RuntimeException;

final readonly class RosterCsvExporter
{
    public function __construct(
        private RosterQuery $roster,
        private PlayerSnapshotQuery $snapshots,
        private AuditRecorder $audit,
    ) {}

    /** @return array{content: string, filename: string, row_count: int, checksum: string} */
    public function export(Alliance $alliance, Player $actor, bool $includePrivate): array
    {
        $entries = $this->roster->forAlliance($alliance)
            ->filter(static fn (AllianceRosterEntry $entry): bool => in_array(
                $entry->state,
                [RosterState::Active, RosterState::Tracked],
                true,
            ))
            ->values();
        $latest = $this->snapshots->latestForEntries($alliance, $entries);

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
            $snapshot = $latest[(string) $entry->id] ?? null;
            $row = $this->row($entry, $snapshot);

            if ($includePrivate) {
                $row['player_id'] = (string) $entry->player_id;
                $row['manager_notes'] = $entry->manager_notes ?? '';
            }

            fputcsv(
                $handle,
                array_map(fn (string $header): string => $this->safeCell((string) ($row[$header] ?? '')), $headers),
                ',',
                '"',
                '',
            );
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
            'row_count' => $entries->count(),
            'include_private' => $includePrivate,
            'checksum' => $checksum,
        ];
        $this->audit->record('intelligence.roster_exported', $actor, $alliance, $alliance, $metadata);

        return [
            'content' => $content,
            'filename' => sprintf('%s-roster%s.csv', $alliance->slug, $includePrivate ? '-management' : ''),
            'row_count' => $entries->count(),
            'checksum' => $checksum,
        ];
    }

    /** @return array<string, string> */
    private function row(AllianceRosterEntry $entry, ?PlayerSnapshot $snapshot): array
    {
        if ($snapshot === null) {
            $name = (string) $entry->observed_name;
            $power = '';
            $progressionLevel = '';
            $allianceTag = '';
            $capturedAt = '';
        } else {
            $name = (string) $snapshot->observed_name;
            $power = (string) $snapshot->power;
            $progressionLevel = $snapshot->progression_level ?? '';
            $allianceTag = $snapshot->observed_alliance_tag ?? '';
            $capturedAt = $snapshot->captured_at->toIso8601String();
        }

        return [
            'game_player_id' => $entry->player->game_player_id ?? '',
            'name' => $name,
            'power' => $power,
            'progression_level' => $progressionLevel,
            'alliance_tag' => $allianceTag,
            'game_role' => $entry->game_role ?? '',
            'state' => $entry->state->value,
            'joined_at' => $entry->joined_at?->toDateString() ?? '',
            'captured_at' => $capturedAt,
        ];
    }

    private function safeCell(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $trimmed = ltrim($value, " \t\r\n");
        $first = $trimmed === '' ? '' : $trimmed[0];

        if (
            in_array($first, ['=', '+', '-', '@'], true)
            || str_starts_with($value, "\t")
            || str_starts_with($value, "\r")
            || str_starts_with($value, "\n")
        ) {
            return "'".$value;
        }

        return $value;
    }
}
