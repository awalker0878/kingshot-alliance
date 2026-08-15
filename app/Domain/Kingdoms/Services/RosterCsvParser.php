<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Services;

use App\Contexts\Alliance\Membership\Enums\RosterState;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * @phpstan-type CsvRowData array{
 *   game_player_id: string|null,
 *   name: string,
 *   power: string,
 *   progression_level: string|null,
 *   alliance_tag: string|null,
 *   game_role: string|null,
 *   state: string,
 *   joined_at: string|null,
 *   captured_at: string
 * }
 * @phpstan-type CsvRow array{row: int, data: CsvRowData, errors: list<string>}
 */
final class RosterCsvParser
{
    public const SCHEMA_VERSION = 'kingdoms-roster.v1';

    public const MAX_BYTES = 1_048_576;

    public const MAX_ROWS = 500;

    public const MAX_POWER = '9223372036854775807';

    /** @var list<string> */
    public const HEADERS = [
        'game_player_id',
        'name',
        'power',
        'progression_level',
        'alliance_tag',
        'game_role',
        'state',
        'joined_at',
        'captured_at',
    ];

    /** @return array{checksum: string, filename: string, rows: list<CsvRow>} */
    public function parse(UploadedFile $file, Carbon $previewedAt): array
    {
        $filename = trim($file->getClientOriginalName());
        if ($filename === '' || str_ends_with(strtolower($filename), '.csv') === false) {
            throw ValidationException::withMessages([
                'file' => 'Upload a .csv file using the documented Kingdoms roster schema.',
            ]);
        }

        $size = $file->getSize();
        if (is_int($size) === false || $size <= 0 || $size > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => 'The CSV file must be non-empty and no larger than 1 MiB.',
            ]);
        }

        $path = $file->getRealPath();
        if (is_string($path) === false || $path === '') {
            throw ValidationException::withMessages(['file' => 'The uploaded CSV file could not be read.']);
        }

        $content = file_get_contents($path);
        if (is_string($content) === false || $content === '') {
            throw ValidationException::withMessages(['file' => 'The uploaded CSV file is empty.']);
        }

        if (str_contains($content, "\0") || mb_check_encoding($content, 'UTF-8') === false) {
            throw ValidationException::withMessages([
                'file' => 'The CSV file must contain valid UTF-8 text and cannot contain NUL bytes.',
            ]);
        }

        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            throw new RuntimeException('Unable to allocate CSV parsing buffer.');
        }

        fwrite($handle, $content);
        rewind($handle);

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if (is_array($headers) === false) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'The CSV file is missing its header row.']);
        }

        $headers = array_map(static fn (mixed $value): string => trim((string) $value), $headers);
        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
        }

        if ($headers !== self::HEADERS) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'The CSV header does not match the documented kingdoms-roster.v1 schema.',
            ]);
        }

        /** @var list<CsvRow> $rows */
        $rows = [];
        $csvRow = 1;

        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $csvRow++;

            if ($this->blankRow($values)) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);
                throw ValidationException::withMessages([
                    'file' => sprintf('The CSV file can contain at most %d data rows.', self::MAX_ROWS),
                ]);
            }

            $rows[] = $this->row($csvRow, $values, $previewedAt);
        }

        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The CSV file does not contain any data rows.']);
        }

        /** @var array<string, list<int>> $duplicates */
        $duplicates = [];
        foreach ($rows as $index => $row) {
            $stableId = $row['data']['game_player_id'];
            if ($stableId !== null) {
                $duplicates[$stableId][] = $index;
            }
        }

        foreach ($duplicates as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            foreach ($indexes as $index) {
                $rows[$index] = $this->withError(
                    $rows[$index],
                    'The stable game player ID appears more than once in this file.',
                );
            }
        }

        return [
            'checksum' => hash('sha256', $content),
            'filename' => mb_substr($filename, 0, 255),
            'rows' => $rows,
        ];
    }

    /** @param  array<int, mixed>  $values */
    private function blankRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return CsvRow
     */
    private function row(int $rowNumber, array $values, Carbon $previewedAt): array
    {
        $errors = [];

        if (count($values) !== count(self::HEADERS)) {
            $errors[] = sprintf('Expected %d columns but found %d.', count(self::HEADERS), count($values));
        }

        $values = array_pad(array_slice($values, 0, count(self::HEADERS)), count(self::HEADERS), '');
        $record = array_combine(
            self::HEADERS,
            array_map(static fn (mixed $value): string => trim((string) $value), $values),
        );

        $gamePlayerId = $this->nullable($record['game_player_id']);
        $name = $record['name'];
        $power = $record['power'];
        $progression = $this->nullable($record['progression_level']);
        $allianceTag = $this->nullable($record['alliance_tag']);
        $gameRole = $this->nullable($record['game_role']);
        $state = $record['state'];
        $joinedAt = $this->nullable($record['joined_at']);
        $capturedAt = $this->nullable($record['captured_at']);

        if ($gamePlayerId !== null && mb_strlen($gamePlayerId) > 100) {
            $errors[] = 'game_player_id cannot exceed 100 characters.';
        }

        if ($name === '') {
            $errors[] = 'name is required.';
        } elseif (mb_strlen($name) > 160) {
            $errors[] = 'name cannot exceed 160 characters.';
        }

        $canonicalPower = $this->power($power, $errors);

        if ($progression !== null && mb_strlen($progression) > 64) {
            $errors[] = 'progression_level cannot exceed 64 characters.';
        }

        if ($allianceTag !== null && mb_strlen($allianceTag) > 32) {
            $errors[] = 'alliance_tag cannot exceed 32 characters.';
        }

        if ($gameRole !== null && mb_strlen($gameRole) > 64) {
            $errors[] = 'game_role cannot exceed 64 characters.';
        }

        if (in_array($state, array_column(RosterState::cases(), 'value'), true) === false) {
            $errors[] = 'state must be active, tracked, or left.';
        }

        if ($joinedAt !== null && $this->date($joinedAt) === false) {
            $errors[] = 'joined_at must use YYYY-MM-DD format.';
        }

        $normalizedCapture = $previewedAt->copy()->utc();
        if ($capturedAt !== null) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', $capturedAt) !== 1) {
                $errors[] = 'captured_at must be an ISO-8601 timestamp with timezone.';
            } else {
                try {
                    $normalizedCapture = Carbon::parse($capturedAt)->utc();
                } catch (Throwable) {
                    $errors[] = 'captured_at is not a valid timestamp.';
                }
            }
        }

        if ($normalizedCapture->isAfter($previewedAt->copy()->addMinutes(5))) {
            $errors[] = 'captured_at cannot be more than five minutes in the future.';
        }

        return [
            'row' => $rowNumber,
            'data' => [
                'game_player_id' => $gamePlayerId,
                'name' => $name,
                'power' => $canonicalPower,
                'progression_level' => $progression,
                'alliance_tag' => $allianceTag,
                'game_role' => $gameRole,
                'state' => $state,
                'joined_at' => $joinedAt,
                'captured_at' => $normalizedCapture->format('Y-m-d\TH:i:s.u\Z'),
            ],
            'errors' => $errors,
        ];
    }

    /**
     * @param  CsvRow  $row
     * @return CsvRow
     */
    private function withError(array $row, string $error): array
    {
        $row['errors'][] = $error;

        return $row;
    }

    /** @param  list<string>  $errors */
    private function power(string $value, array &$errors): string
    {
        if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
            $errors[] = 'power is required and must be a non-negative whole number.';

            return '0';
        }

        $canonical = ltrim($value, '0');
        $canonical = $canonical === '' ? '0' : $canonical;

        if (
            strlen($canonical) > strlen(self::MAX_POWER)
            || (strlen($canonical) === strlen(self::MAX_POWER) && strcmp($canonical, self::MAX_POWER) > 0)
        ) {
            $errors[] = 'power exceeds the supported signed 64-bit integer range.';
        }

        return $canonical;
    }

    private function date(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
