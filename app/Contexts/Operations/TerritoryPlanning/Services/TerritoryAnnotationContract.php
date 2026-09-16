<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use Illuminate\Validation\ValidationException;

final class TerritoryAnnotationContract
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>|null  $allianceKeys
     * @return list<array{key:string,kind:string,alliance_key:?string,text:?string,x:int,y:int,target_x:?int,target_y:?int,sort_order:int}>
     */
    public function normalize(array $rows, ?array $allianceKeys = null): array
    {
        if (! array_is_list($rows) || count($rows) > 500) {
            throw ValidationException::withMessages(['annotations' => 'Annotations must be a list of at most 500 entries.']);
        }

        $allowedAlliances = $allianceKeys === null ? null : array_fill_keys($allianceKeys, true);
        $keys = [];
        $normalized = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw ValidationException::withMessages(['annotations' => 'Every annotation must be an object.']);
            }
            if (array_diff(array_keys($row), ['key', 'kind', 'alliance_key', 'text', 'x', 'y', 'target_x', 'target_y', 'sort_order']) !== []) {
                throw ValidationException::withMessages(['annotations' => 'An annotation contains unsupported fields.']);
            }

            $key = trim((string) ($row['key'] ?? ''));
            $kind = (string) ($row['kind'] ?? '');
            $text = isset($row['text']) ? trim((string) $row['text']) : null;
            $allianceKey = isset($row['alliance_key']) ? trim((string) $row['alliance_key']) : null;
            $x = $row['x'] ?? null;
            $y = $row['y'] ?? null;
            $targetX = $row['target_x'] ?? null;
            $targetY = $row['target_y'] ?? null;
            $sortOrder = $row['sort_order'] ?? $index;

            if ($key === '' || mb_strlen($key) > 120 || isset($keys[$key])) {
                throw ValidationException::withMessages(['annotations' => 'Annotation keys must be unique bounded strings.']);
            }
            if (! in_array($kind, ['label', 'line', 'arrow', 'rectangle'], true)) {
                throw ValidationException::withMessages(['annotations' => 'Annotation kind is unsupported.']);
            }
            if (! is_int($x) || ! is_int($y) || abs($x) > 1_000_000 || abs($y) > 1_000_000) {
                throw ValidationException::withMessages(['annotations' => 'Annotation coordinates must be bounded integers.']);
            }
            if (($targetX !== null && (! is_int($targetX) || abs($targetX) > 1_000_000))
                || ($targetY !== null && (! is_int($targetY) || abs($targetY) > 1_000_000))) {
                throw ValidationException::withMessages(['annotations' => 'Annotation target coordinates must be bounded integers.']);
            }
            if (in_array($kind, ['line', 'arrow', 'rectangle'], true) && ($targetX === null || $targetY === null)) {
                throw ValidationException::withMessages(['annotations' => 'Shape annotations require a target coordinate.']);
            }
            if ($text !== null && mb_strlen($text) > 500) {
                throw ValidationException::withMessages(['annotations' => 'Annotation text must be at most 500 characters.']);
            }
            if ($allianceKey === '') {
                $allianceKey = null;
            }
            if ($allianceKey !== null && mb_strlen($allianceKey) > 120) {
                throw ValidationException::withMessages(['annotations' => 'Annotation Alliance keys must be bounded strings.']);
            }
            if ($allowedAlliances !== null && $allianceKey !== null && ! isset($allowedAlliances[$allianceKey])) {
                throw ValidationException::withMessages(['annotations' => 'Annotation Alliance keys must reference a layout Alliance layer.']);
            }
            if (! is_int($sortOrder) || $sortOrder < 0 || $sortOrder > 500) {
                throw ValidationException::withMessages(['annotations' => 'Annotation sort order is out of range.']);
            }

            $keys[$key] = true;
            $normalized[] = [
                'key' => $key,
                'kind' => $kind,
                'alliance_key' => $allianceKey,
                'text' => $text === '' ? null : $text,
                'x' => $x,
                'y' => $y,
                'target_x' => $targetX,
                'target_y' => $targetY,
                'sort_order' => $sortOrder,
            ];
        }

        return $normalized;
    }
}
