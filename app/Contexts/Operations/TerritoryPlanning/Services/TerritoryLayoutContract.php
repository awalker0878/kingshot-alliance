<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryObjectType;
use Illuminate\Validation\ValidationException;
use JsonException;
use ValueError;

final class TerritoryLayoutContract
{
    public const SCHEMA_VERSION = 2;

    public const MAX_DOCUMENT_BYTES = 5000000;

    /** @return array{schema_version:2,plan:array<string,mixed>,alliances:list<array<string,mixed>>,groups:list<array<string,mixed>>,objects:list<array<string,mixed>>} */
    public function decode(string $json): array
    {
        if (strlen($json) > self::MAX_DOCUMENT_BYTES) {
            throw ValidationException::withMessages(['import' => 'The layout document exceeds the five-megabyte limit.']);
        }
        try {
            $document = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['import' => 'The layout must be valid JSON with at most 32 levels of nesting.']);
        }
        if (! is_array($document) || ($document['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw ValidationException::withMessages(['import' => 'Only Territory Layout schema version 2 is supported.']);
        }
        $this->fields($document, ['schema_version', 'plan', 'alliances', 'groups', 'objects'], 'import');
        $plan = $document['plan'] ?? null;
        if (! is_array($plan)) {
            throw ValidationException::withMessages(['import' => 'A layout document must contain plan data.']);
        }
        $this->fields($plan, ['id', 'scope', 'kingdom_id', 'owner_alliance_id', 'name', 'head_revision', 'map_dataset_id', 'map_dataset_checksum', 'planning_preferences'], 'import');
        if (! is_string($plan['map_dataset_id'] ?? null) || $plan['map_dataset_id'] === '' || strlen($plan['map_dataset_id']) > 120
            || ! is_string($plan['map_dataset_checksum'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $plan['map_dataset_checksum'])) {
            throw ValidationException::withMessages(['import' => 'An exact map dataset ID and SHA-256 checksum are required.']);
        }
        $preferences = $plan['planning_preferences'] ?? [];
        if (! is_array($preferences)) {
            throw ValidationException::withMessages(['planning_preferences' => 'Planning preferences must be an object.']);
        }
        $layout = $this->normalize($this->rows($document['alliances'] ?? null), $this->rows($document['groups'] ?? null), $this->rows($document['objects'] ?? null), $preferences);
        $plan['planning_preferences'] = $layout['planning_preferences'];

        return ['schema_version' => self::SCHEMA_VERSION, 'plan' => $plan,
            'alliances' => $layout['alliances'], 'groups' => $layout['groups'], 'objects' => $layout['objects']];
    }

    /** @return list<array<string,mixed>> */
    private function rows(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw ValidationException::withMessages(['import' => 'Layout collections must be JSON lists.']);
        }
        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(['import' => 'Every layout entry must be an object.']);
            }
            $entry = [];
            foreach ($row as $key => $item) {
                if (! is_string($key)) {
                    throw ValidationException::withMessages(['import' => 'Layout entry field names must be strings.']);
                }
                $entry[$key] = $item;
            }
            $rows[] = $entry;
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $alliances
     * @param  list<array<string, mixed>>  $groups
     * @param  list<array<string, mixed>>  $objects
     * @param  array<string, mixed>  $preferences
     */
    private function assertInputTypes(array $alliances, array $groups, array $objects, array $preferences): void
    {
        foreach (['alliances' => $alliances, 'groups' => $groups, 'objects' => $objects] as $field => $rows) {
            if (! array_is_list($rows)) {
                throw ValidationException::withMessages([$field => 'Layout collections must be JSON lists.']);
            }
            foreach ($rows as $row) {
                if (! is_array($row) || array_is_list($row)) {
                    throw ValidationException::withMessages([$field => 'Every layout entry must be an object.']);
                }
                $allowed = match ($field) {
                    'alliances' => ['key', 'alliance_id', 'external_name', 'external_tag', 'display_name', 'presentation_color', 'sort_order', 'visible', 'locked'],
                    'groups' => ['key', 'label'],
                    default => ['key', 'alliance_key', 'group_key', 'type', 'player_id', 'external_player_name', 'label', 'x', 'y', 'rotation', 'sort_order', 'metadata'],
                };
                $this->fields($row, $allowed, $field);
                foreach ($row as $key => $value) {
                    if (in_array($key, ['x', 'y', 'rotation', 'sort_order'], true)) {
                        if (! is_int($value) || abs($value) > 1000000) {
                            throw ValidationException::withMessages([$field => 'Coordinates, rotations and sort orders must be bounded JSON integers.']);
                        }
                    } elseif (in_array($key, ['visible', 'locked'], true)) {
                        if (! is_bool($value)) {
                            throw ValidationException::withMessages([$field => 'Layer visibility and locks must be JSON booleans.']);
                        }
                    } elseif ($key !== 'metadata' && $value !== null && (! is_string($value) || mb_strlen($value) > 160)) {
                        throw ValidationException::withMessages([$field => 'Identity and label values must be bounded strings.']);
                    }
                }
                if ($field === 'objects') {
                    if (! is_int($row['x'] ?? null) || ! is_int($row['y'] ?? null)) {
                        throw ValidationException::withMessages(['objects' => 'Every planned object needs integer X and Y coordinates.']);
                    }
                    $this->metadata($row);
                }
            }
        }
        $this->fields($preferences, ['preferred_bear_radius_tiles', 'march_seconds_per_tile', 'selected_bear_trap_by_alliance'], 'planning_preferences');
        foreach (['preferred_bear_radius_tiles', 'march_seconds_per_tile'] as $field) {
            if (array_key_exists($field, $preferences) && (! is_int($preferences[$field]) && ! is_float($preferences[$field]) || ! is_finite((float) $preferences[$field]))) {
                throw ValidationException::withMessages(['planning_preferences' => 'Planning distances and times must be finite JSON numbers.']);
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function metadata(array $row): void
    {
        $metadata = $row['metadata'] ?? [];
        if (! is_array($metadata)) {
            throw ValidationException::withMessages(['objects' => 'Object metadata must be an object.']);
        }
        $this->fields($metadata, ['locked', 'variant_key', 'slot_state', 'external_identity_key'], 'objects');
        if (array_key_exists('locked', $metadata) && ! is_bool($metadata['locked'])) {
            throw ValidationException::withMessages(['objects' => 'Object locks must be booleans.']);
        }
        $type = $row['type'] ?? null;
        $variant = $metadata['variant_key'] ?? null;
        if ($variant !== null && ! in_array($variant, $type === 'headquarters' ? ['badland_headquarters', 'plains_headquarters'] : ['default'], true)) {
            throw ValidationException::withMessages(['objects' => 'This object variant is unsupported.']);
        }
        $state = $metadata['slot_state'] ?? null;
        if ($state !== null && ($type !== 'governor_city' || ! in_array($state, ['open', 'reserved', 'assigned'], true))) {
            throw ValidationException::withMessages(['objects' => 'Only Governor cities can have a supported slot state.']);
        }
        $identity = $metadata['external_identity_key'] ?? null;
        if ($identity !== null && ($type !== 'governor_city' || ! is_string($identity) || trim($identity) === '' || mb_strlen($identity) > 120 || ($row['player_id'] ?? null) !== null)) {
            throw ValidationException::withMessages(['objects' => 'A plan-local Governor identity must be a bounded city identity without a linked Governor.']);
        }
        $assigned = ($row['player_id'] ?? null) !== null || $identity !== null || ($row['external_player_name'] ?? null) !== null;
        if (($state === 'open' && $assigned) || ($state === 'assigned' && ! $assigned)) {
            throw ValidationException::withMessages(['objects' => 'The Governor slot state and identity must agree.']);
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $allowed
     */
    private function fields(array $value, array $allowed, string $field): void
    {
        if (array_diff(array_keys($value), $allowed) !== []) {
            throw ValidationException::withMessages([$field => 'The document contains unsupported fields.']);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $alliances
     * @param  list<array<string, mixed>>  $groups
     * @param  list<array<string, mixed>>  $objects
     * @param  array<string, mixed>  $preferences
     * @return array{alliances:list<array<string,mixed>>,groups:list<array<string,mixed>>,objects:list<array<string,mixed>>,planning_preferences:array<string,mixed>}
     */
    public function normalize(array $alliances, array $groups, array $objects, array $preferences = []): array
    {
        $this->assertInputTypes($alliances, $groups, $objects, $preferences);
        $alliances = $this->normalizeAlliances($alliances);
        $groups = $this->normalizeGroups($groups);
        $objects = $this->normalizeObjects($objects, $alliances, $groups);
        $preferences = $this->normalizePreferences($preferences, $alliances, $objects);

        return ['alliances' => $alliances, 'groups' => $groups, 'objects' => $objects, 'planning_preferences' => $preferences];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeAlliances(array $items): array
    {
        if ($items === [] || count($items) > 50) {
            throw ValidationException::withMessages([
                'alliances' => 'A plan requires between 1 and 50 Alliance layers.',
            ]);
        }

        $keys = [];
        $linkedAllianceIds = [];
        $result = [];

        foreach ($items as $index => $item) {
            $key = trim((string) ($item['key'] ?? ''));
            $allianceId = $this->nullableString($item['alliance_id'] ?? null);
            $externalName = $this->nullableString($item['external_name'] ?? null);
            $externalTag = $this->nullableString($item['external_tag'] ?? null);
            $displayName = trim((string) ($item['display_name'] ?? $externalName ?? ''));

            if (
                $key === ''
                || mb_strlen($key) > 120
                || isset($keys[$key])
                || $displayName === ''
                || mb_strlen($displayName) > 160
                || ($externalName !== null && mb_strlen($externalName) > 160)
                || ($externalTag !== null && mb_strlen($externalTag) > 32)
                || ($allianceId === null && $externalName === null)
                || ($allianceId !== null && $externalName !== null)
            ) {
                throw ValidationException::withMessages([
                    'alliances' => 'Every Alliance layer needs a unique valid key, display name, and exactly one linked or external identity.',
                ]);
            }

            if ($allianceId !== null && isset($linkedAllianceIds[$allianceId])) {
                throw ValidationException::withMessages([
                    'alliances' => 'A linked Alliance can appear only once in a plan.',
                ]);
            }

            $keys[$key] = true;
            if ($allianceId !== null) {
                $linkedAllianceIds[$allianceId] = true;
            }

            $color = strtolower(trim((string) ($item['presentation_color'] ?? '#4da3ff')));
            if (! preg_match('/^#[0-9a-f]{6}$/', $color)) {
                throw ValidationException::withMessages([
                    'alliances' => 'Alliance presentation colors must use #RRGGBB.',
                ]);
            }

            $result[] = [
                'key' => $key,
                'alliance_id' => $allianceId,
                'external_name' => $externalName,
                'external_tag' => $externalTag,
                'display_name' => $displayName,
                'presentation_color' => $color,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
                'visible' => (bool) ($item['visible'] ?? true),
                'locked' => (bool) ($item['locked'] ?? false),
            ];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{key: string, label: ?string}>
     */
    private function normalizeGroups(array $items): array
    {
        if (count($items) > 500) {
            throw ValidationException::withMessages([
                'groups' => 'A plan may contain at most 500 groups.',
            ]);
        }

        $keys = [];
        $result = [];

        foreach ($items as $item) {
            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '' || mb_strlen($key) > 120 || isset($keys[$key])) {
                throw ValidationException::withMessages([
                    'groups' => 'Every group requires a unique valid key.',
                ]);
            }

            $keys[$key] = true;
            $label = $this->nullableString($item['label'] ?? null);
            if ($label !== null && mb_strlen($label) > 160) {
                throw ValidationException::withMessages([
                    'groups' => 'Group labels must be 160 characters or fewer.',
                ]);
            }

            $result[] = ['key' => $key, 'label' => $label];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $alliances
     * @param  list<array{key: string, label: ?string}>  $groups
     * @return list<array<string, mixed>>
     */
    private function normalizeObjects(array $items, array $alliances, array $groups): array
    {
        if (count($items) > 5000) {
            throw ValidationException::withMessages([
                'objects' => 'A plan may contain at most 5000 planned objects.',
            ]);
        }

        $allianceKeys = array_fill_keys(array_column($alliances, 'key'), true);
        $groupKeys = array_fill_keys(array_column($groups, 'key'), true);
        $keys = [];
        $result = [];

        foreach ($items as $index => $item) {
            $key = trim((string) ($item['key'] ?? ''));
            $allianceKey = trim((string) ($item['alliance_key'] ?? ''));
            $groupKey = $this->nullableString($item['group_key'] ?? null);
            $type = trim((string) ($item['type'] ?? ''));

            if (
                $key === ''
                || mb_strlen($key) > 120
                || isset($keys[$key])
                || ! isset($allianceKeys[$allianceKey])
                || ($groupKey !== null && ! isset($groupKeys[$groupKey]))
            ) {
                throw ValidationException::withMessages([
                    'objects' => 'Every object needs unique valid identity and valid Alliance/group references.',
                ]);
            }

            $keys[$key] = true;
            try {
                TerritoryObjectType::from($type);
            } catch (ValueError) {
                throw ValidationException::withMessages([
                    'objects' => 'A planned object uses an unsupported type.',
                ]);
            }

            $rotation = (int) ($item['rotation'] ?? 0);
            if (! in_array($rotation, [0, 90, 180, 270], true)) {
                throw ValidationException::withMessages([
                    'objects' => 'Object rotation must be 0, 90, 180, or 270 degrees.',
                ]);
            }

            $playerId = $this->nullableString($item['player_id'] ?? null);
            $externalPlayerName = $this->nullableString($item['external_player_name'] ?? null);
            $label = $this->nullableString($item['label'] ?? null);
            if ($externalPlayerName !== null && mb_strlen($externalPlayerName) > 160) {
                throw ValidationException::withMessages([
                    'objects' => 'External Governor names must be 160 characters or fewer.',
                ]);
            }
            if ($label !== null && mb_strlen($label) > 160) {
                throw ValidationException::withMessages([
                    'objects' => 'Object labels must be 160 characters or fewer.',
                ]);
            }
            if (
                $type !== TerritoryObjectType::GovernorCity->value
                && ($playerId !== null || $externalPlayerName !== null)
            ) {
                throw ValidationException::withMessages([
                    'objects' => 'Only Governor cities may carry Governor identity.',
                ]);
            }
            if ($playerId !== null && $externalPlayerName !== null) {
                throw ValidationException::withMessages([
                    'objects' => 'A Governor city must use either a linked Governor or an external Governor label, not both.',
                ]);
            }

            $result[] = [
                'key' => $key,
                'alliance_key' => $allianceKey,
                'group_key' => $groupKey,
                'type' => $type,
                'player_id' => $playerId,
                'external_player_name' => $externalPlayerName,
                'label' => $label,
                'x' => (int) ($item['x'] ?? -1),
                'y' => (int) ($item['y'] ?? -1),
                'rotation' => $rotation,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
                'metadata' => is_array($item['metadata'] ?? null) ? $item['metadata'] : [],
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $preferences
     * @param  list<array<string, mixed>>  $alliances
     * @param  list<array<string, mixed>>  $objects
     * @return array<string, mixed>
     */
    private function normalizePreferences(array $preferences, array $alliances, array $objects): array
    {
        $allowed = [];

        if (
            isset($preferences['preferred_bear_radius_tiles'])
            && $preferences['preferred_bear_radius_tiles'] !== ''
        ) {
            $radius = (float) $preferences['preferred_bear_radius_tiles'];
            if ($radius <= 0 || $radius > 1200) {
                throw ValidationException::withMessages([
                    'planning_preferences' => 'Preferred Bear radius must be between 0 and 1200 tiles.',
                ]);
            }
            $allowed['preferred_bear_radius_tiles'] = $radius;
        }

        if (
            isset($preferences['march_seconds_per_tile'])
            && $preferences['march_seconds_per_tile'] !== ''
        ) {
            $seconds = (float) $preferences['march_seconds_per_tile'];
            if ($seconds <= 0 || $seconds > 60) {
                throw ValidationException::withMessages([
                    'planning_preferences' => 'March-time planning assumption must be between 0 and 60 seconds per tile.',
                ]);
            }
            $allowed['march_seconds_per_tile'] = $seconds;
        }

        $selection = $preferences['selected_bear_trap_by_alliance'] ?? null;
        if ($selection !== null) {
            if (! is_array($selection)) {
                throw ValidationException::withMessages([
                    'planning_preferences' => 'Selected Bear Traps must be keyed by Alliance layer.',
                ]);
            }

            $allianceKeys = array_fill_keys(array_column($alliances, 'key'), true);
            $trapKeysByAlliance = [];
            foreach ($objects as $object) {
                if ($object['type'] === TerritoryObjectType::BearTrap->value) {
                    $trapKeysByAlliance[$object['alliance_key']][$object['key']] = true;
                }
            }

            $normalizedSelection = [];
            foreach ($selection as $allianceKey => $trapKey) {
                if (
                    ! is_string($allianceKey)
                    || ! is_string($trapKey)
                    || ! isset($allianceKeys[$allianceKey])
                    || ! isset($trapKeysByAlliance[$allianceKey][$trapKey])
                ) {
                    throw ValidationException::withMessages([
                        'planning_preferences' => 'A selected Bear Trap must belong to the selected Alliance layer.',
                    ]);
                }
                $normalizedSelection[$allianceKey] = $trapKey;
            }
            ksort($normalizedSelection);
            if ($normalizedSelection !== []) {
                $allowed['selected_bear_trap_by_alliance'] = $normalizedSelection;
            }
        }

        return $allowed;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
