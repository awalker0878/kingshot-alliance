<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryObjectType;
use Illuminate\Validation\ValidationException;

/**
 * Strict, bounded CSV interchange for plan coordinates.
 *
 * This adapter intentionally owns only the neutral coordinate table format. Import preview,
 * authorization, map pinning and persistence remain with TerritoryPlanImport / write actions.
 */
final class TerritoryCoordinateTableAdapter
{
    public const MAX_BYTES = 1_000_000;

    public const MAX_ROWS = 10_000;

    /** @var list<string> */
    public const HEADER = [
        'key',
        'type',
        'variant_key',
        'x',
        'y',
        'rotation',
        'alliance_key',
        'group_key',
        'player_id',
        'external_player_name',
        'label',
        'slot_state',
        'external_identity_key',
    ];

    /**
     * @return list<array{key:string,type:string,x:int,y:int,rotation:int,alliance_key:?string,group_key:?string,player_id:?string,external_player_name:?string,label:?string,metadata:array<string,mixed>}>
     */
    public function decode(string $csv): array
    {
        if (strlen($csv) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['coordinates' => 'The coordinate table exceeds the one-megabyte limit.']);
        }

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw ValidationException::withMessages(['coordinates' => 'The coordinate table could not be opened.']);
        }

        try {
            fwrite($stream, $csv);
            rewind($stream);
            $header = fgetcsv($stream, 0, ',', '"', '');
            if ($header === false) {
                throw ValidationException::withMessages(['coordinates' => 'The coordinate table must contain a header row.']);
            }
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
            }
            if ($header !== self::HEADER) {
                throw ValidationException::withMessages(['coordinates' => 'The coordinate table header is unsupported or out of order.']);
            }

            $rows = [];
            while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                if ($this->blankRow($row)) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages(['coordinates' => 'The coordinate table exceeds the 10,000-row limit.']);
                }
                if (count($row) !== count(self::HEADER)) {
                    throw ValidationException::withMessages(['coordinates' => 'Every coordinate row must contain exactly '.count(self::HEADER).' columns.']);
                }
                /** @var array<string, string|null> $record */
                $record = array_combine(self::HEADER, array_map(static fn ($value): string => (string) $value, $row));
                $rows[] = $this->normalizeRow($record);
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    /** @param list<array<string,mixed>> $objects */
    public function encode(array $objects): string
    {
        if (count($objects) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['coordinates' => 'The coordinate table exceeds the 10,000-row limit.']);
        }

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw ValidationException::withMessages(['coordinates' => 'The coordinate table could not be opened.']);
        }

        try {
            fputcsv($stream, self::HEADER, ',', '"', '');
            foreach ($objects as $object) {
                $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
                $normalized = $this->normalizeRow([
                    'key' => (string) ($object['key'] ?? ''),
                    'type' => (string) ($object['type'] ?? ''),
                    'variant_key' => (string) ($metadata['variant_key'] ?? ''),
                    'x' => (string) ($object['x'] ?? ''),
                    'y' => (string) ($object['y'] ?? ''),
                    'rotation' => (string) ($object['rotation'] ?? 0),
                    'alliance_key' => (string) ($object['alliance_key'] ?? ''),
                    'group_key' => (string) ($object['group_key'] ?? ''),
                    'player_id' => (string) ($object['player_id'] ?? ''),
                    'external_player_name' => (string) ($object['external_player_name'] ?? ''),
                    'label' => (string) ($object['label'] ?? ''),
                    'slot_state' => (string) ($metadata['slot_state'] ?? ''),
                    'external_identity_key' => (string) ($metadata['external_identity_key'] ?? ''),
                ]);
                $normalizedMetadata = $normalized['metadata'];
                fputcsv($stream, [
                    $this->safeCell($normalized['key']),
                    $normalized['type'],
                    $this->safeCell((string) ($normalizedMetadata['variant_key'] ?? '')),
                    (string) $normalized['x'],
                    (string) $normalized['y'],
                    (string) $normalized['rotation'],
                    $this->safeCell((string) ($normalized['alliance_key'] ?? '')),
                    $this->safeCell((string) ($normalized['group_key'] ?? '')),
                    $this->safeCell((string) ($normalized['player_id'] ?? '')),
                    $this->safeCell((string) ($normalized['external_player_name'] ?? '')),
                    $this->safeCell((string) ($normalized['label'] ?? '')),
                    $this->safeCell((string) ($normalizedMetadata['slot_state'] ?? '')),
                    $this->safeCell((string) ($normalizedMetadata['external_identity_key'] ?? '')),
                ], ',', '"', '');
            }
            rewind($stream);
            $result = stream_get_contents($stream);
            if ($result === false || strlen($result) > self::MAX_BYTES) {
                throw ValidationException::withMessages(['coordinates' => 'The encoded coordinate table exceeds the one-megabyte limit.']);
            }

            return $result;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  array<string,string|null>  $row
     * @return array{key:string,type:string,x:int,y:int,rotation:int,alliance_key:?string,group_key:?string,player_id:?string,external_player_name:?string,label:?string,metadata:array<string,mixed>}
     */
    private function normalizeRow(array $row): array
    {
        $key = $this->boundedRequired($row['key'] ?? '', 'key');
        $type = TerritoryObjectType::tryFrom((string) ($row['type'] ?? ''));
        if ($type === null) {
            throw ValidationException::withMessages(['coordinates' => 'Every coordinate row must use a supported object type.']);
        }

        $x = $this->integer($row['x'] ?? '', 'x');
        $y = $this->integer($row['y'] ?? '', 'y');
        $rotation = $this->integer($row['rotation'] ?? '0', 'rotation');
        if (! in_array($rotation, [0, 90, 180, 270], true)) {
            throw ValidationException::withMessages(['coordinates' => 'Rotation must be 0, 90, 180 or 270 degrees.']);
        }

        $variant = $this->boundedOptional($row['variant_key'] ?? '', 'variant_key');
        $allowedVariants = $type === TerritoryObjectType::Headquarters
            ? ['badland_headquarters', 'plains_headquarters']
            : ['default'];
        if ($variant !== null && ! in_array($variant, $allowedVariants, true)) {
            throw ValidationException::withMessages(['coordinates' => 'The coordinate row contains an unsupported object variant.']);
        }

        $playerId = $this->boundedOptional($row['player_id'] ?? '', 'player_id');
        $externalName = $this->boundedOptional($row['external_player_name'] ?? '', 'external_player_name');
        $externalIdentity = $this->boundedOptional($row['external_identity_key'] ?? '', 'external_identity_key');
        $slotState = $this->boundedOptional($row['slot_state'] ?? '', 'slot_state');
        if ($slotState !== null && ($type !== TerritoryObjectType::GovernorCity || ! in_array($slotState, ['open', 'reserved', 'assigned'], true))) {
            throw ValidationException::withMessages(['coordinates' => 'Only Governor cities may use open, reserved or assigned slot states.']);
        }
        if ($externalIdentity !== null && ($type !== TerritoryObjectType::GovernorCity || $playerId !== null)) {
            throw ValidationException::withMessages(['coordinates' => 'Plan-local identities are only valid for unlinked Governor cities.']);
        }
        $assigned = $playerId !== null || $externalName !== null || $externalIdentity !== null;
        if (($slotState === 'open' && $assigned) || ($slotState === 'assigned' && ! $assigned)) {
            throw ValidationException::withMessages(['coordinates' => 'Governor slot state and identity must agree.']);
        }

        $metadata = [];
        if ($variant !== null) {
            $metadata['variant_key'] = $variant;
        }
        if ($slotState !== null) {
            $metadata['slot_state'] = $slotState;
        }
        if ($externalIdentity !== null) {
            $metadata['external_identity_key'] = $externalIdentity;
        }

        return [
            'key' => $key,
            'type' => $type->value,
            'x' => $x,
            'y' => $y,
            'rotation' => $rotation,
            'alliance_key' => $this->boundedOptional($row['alliance_key'] ?? '', 'alliance_key'),
            'group_key' => $this->boundedOptional($row['group_key'] ?? '', 'group_key'),
            'player_id' => $playerId,
            'external_player_name' => $externalName,
            'label' => $this->boundedOptional($row['label'] ?? '', 'label'),
            'metadata' => $metadata,
        ];
    }

    private function integer(?string $value, string $field): int
    {
        $value = trim((string) $value);
        if (! preg_match('/^-?\d+$/', $value)) {
            throw ValidationException::withMessages(['coordinates' => "Coordinate field {$field} must be an integer."]);
        }
        $integer = (int) $value;
        if (abs($integer) > 1_000_000) {
            throw ValidationException::withMessages(['coordinates' => "Coordinate field {$field} is outside the supported range."]);
        }

        return $integer;
    }

    private function boundedRequired(?string $value, string $field): string
    {
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value) > 160) {
            throw ValidationException::withMessages(['coordinates' => "Coordinate field {$field} must be a non-empty string of at most 160 characters."]);
        }

        return $value;
    }

    private function boundedOptional(?string $value, string $field): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > 160) {
            throw ValidationException::withMessages(['coordinates' => "Coordinate field {$field} must be at most 160 characters."]);
        }

        return $value;
    }

    /** @param list<string|null> $row */
    private function blankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /** Neutralize formula-capable prefixes before a CSV is opened in a spreadsheet. */
    private function safeCell(string $value): string
    {
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/u', $value) === 1) {
            return "'{$value}";
        }

        return $value;
    }
}
