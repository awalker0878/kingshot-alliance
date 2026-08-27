<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Services;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use Illuminate\Validation\ValidationException;

final readonly class GovernorProgressionObservationValidator
{
    public function __construct(private ProgressionDatasetQuery $progression) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validate(
        EvidenceKind $kind,
        array $payload,
        string $datasetId,
        string $datasetChecksum,
    ): array {
        if (! $kind->isGovernorProgression()) {
            throw ValidationException::withMessages(['kind' => 'The observation kind is not Governor Progression Evidence.']);
        }
        $dataset = $this->progression->require($datasetId, $datasetChecksum);

        return match ($kind) {
            EvidenceKind::GovernorProfile => $this->profile($payload),
            EvidenceKind::GovernorHeroRoster => $this->heroRoster($payload, $dataset),
            EvidenceKind::GovernorHeroDetail => $this->heroDetail($payload, $dataset),
            EvidenceKind::GovernorHeroGear => $this->heroGear($payload, $dataset),
            EvidenceKind::GovernorGear => $this->governorGear($payload, $dataset),
            EvidenceKind::GovernorCharms => $this->charms($payload, $dataset),
            default => throw ValidationException::withMessages(['kind' => 'Unsupported Governor Progression observation kind.']),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function profile(array $payload): array
    {
        $this->closedKeys($payload, ['observed_name', 'power', 'progression_level', 'observed_alliance_tag', 'kingdom_number'], 'payload');
        $result = [];
        if (array_key_exists('observed_name', $payload) && $payload['observed_name'] !== null && $payload['observed_name'] !== '') {
            $result['observed_name'] = $this->line($payload['observed_name'], 160, 'payload.observed_name');
        }
        if (array_key_exists('power', $payload) && $payload['power'] !== null && $payload['power'] !== '') {
            $result['power'] = $this->unsignedIntegerString($payload['power'], 'payload.power');
        }
        if (array_key_exists('progression_level', $payload) && $payload['progression_level'] !== null && $payload['progression_level'] !== '') {
            $result['progression_level'] = $this->line($payload['progression_level'], 64, 'payload.progression_level');
        }
        if (array_key_exists('observed_alliance_tag', $payload) && $payload['observed_alliance_tag'] !== null && $payload['observed_alliance_tag'] !== '') {
            $result['observed_alliance_tag'] = $this->line($payload['observed_alliance_tag'], 32, 'payload.observed_alliance_tag');
        }
        if (array_key_exists('kingdom_number', $payload) && $payload['kingdom_number'] !== null && $payload['kingdom_number'] !== '') {
            $result['kingdom_number'] = $this->integer($payload['kingdom_number'], 1, 999999, 'payload.kingdom_number');
        }
        if ($result === []) {
            throw ValidationException::withMessages(['payload' => 'A Governor profile observation must contain at least one reviewed fact.']);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function heroRoster(array $payload, ProgressionDataset $dataset): array
    {
        $this->closedKeys($payload, ['heroes', 'complete_roster_capture'], 'payload');
        if (! is_array($payload['heroes'] ?? null) || $payload['heroes'] === [] || count($payload['heroes']) > 34) {
            throw ValidationException::withMessages(['payload.heroes' => 'A Hero roster observation must contain 1-34 reviewed Heroes.']);
        }
        $heroes = [];
        $seen = [];
        foreach (array_values($payload['heroes']) as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(["payload.heroes.$index" => 'A reviewed Hero row must be an object.']);
            }
            $this->closedKeys($row, ['hero_id', 'level', 'star', 'widget_level'], "payload.heroes.$index");
            $heroId = $this->heroId($row['hero_id'] ?? null, $dataset, "payload.heroes.$index.hero_id");
            if (isset($seen[$heroId])) {
                throw ValidationException::withMessages(["payload.heroes.$index.hero_id" => 'A Hero may appear only once in one roster observation.']);
            }
            $seen[$heroId] = true;
            $item = ['hero_id' => $heroId];
            $this->optionalInteger($item, 'level', $row, 0, 80, "payload.heroes.$index.level");
            $this->optionalInteger($item, 'star', $row, 0, 5, "payload.heroes.$index.star");
            $this->optionalInteger($item, 'widget_level', $row, 0, 10, "payload.heroes.$index.widget_level");
            $heroes[] = $item;
        }
        usort($heroes, static fn (array $a, array $b): int => strcmp((string) $a['hero_id'], (string) $b['hero_id']));

        return [
            'heroes' => $heroes,
            'complete_roster_capture' => ($payload['complete_roster_capture'] ?? false) === true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function heroDetail(array $payload, ProgressionDataset $dataset): array
    {
        $this->closedKeys($payload, ['hero_id', 'level', 'star', 'substar', 'widget_level'], 'payload');
        $result = ['hero_id' => $this->heroId($payload['hero_id'] ?? null, $dataset, 'payload.hero_id')];
        $this->optionalInteger($result, 'level', $payload, 0, 80, 'payload.level');
        $this->optionalInteger($result, 'star', $payload, 0, 5, 'payload.star');
        $this->optionalInteger($result, 'substar', $payload, 0, 5, 'payload.substar');
        $this->optionalInteger($result, 'widget_level', $payload, 0, 10, 'payload.widget_level');
        if (count($result) === 1) {
            throw ValidationException::withMessages(['payload' => 'A Hero detail observation must contain at least one observed progression fact in addition to Hero identity.']);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function heroGear(array $payload, ProgressionDataset $dataset): array
    {
        $this->closedKeys($payload, ['hero_id', 'gear'], 'payload');
        $heroId = $this->heroId($payload['hero_id'] ?? null, $dataset, 'payload.hero_id');
        $gear = $this->gearRows(
            $payload['gear'] ?? null,
            true,
            'payload.gear',
            $this->publishedDatabaseMaximum($dataset, 'hero-gear-enhancement-chart'),
            $this->publishedDatabaseMaximum($dataset, 'mastery-forging'),
        );

        return ['hero_id' => $heroId, 'gear' => $gear];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function governorGear(array $payload, ProgressionDataset $dataset): array
    {
        $this->closedKeys($payload, ['gear'], 'payload');
        $steps = $this->governorGearSteps($dataset);
        $maxStar = 0;
        foreach ($steps as $step) {
            if (is_int($step['stars'] ?? null)) {
                $maxStar = max($maxStar, $step['stars']);
            }
        }

        return ['gear' => $this->gearRows($payload['gear'] ?? null, false, 'payload.gear', null, $maxStar > 0 ? $maxStar : null, $dataset)];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function charms(array $payload, ProgressionDataset $dataset): array
    {
        $this->closedKeys($payload, ['charms'], 'payload');
        if (! is_array($payload['charms'] ?? null) || $payload['charms'] === [] || count($payload['charms']) > 18) {
            throw ValidationException::withMessages(['payload.charms' => 'A Charm observation must contain 1-18 reviewed Charm slots.']);
        }
        $maxLevel = $this->maximumCharmLevel($dataset);
        $rows = [];
        $seen = [];
        foreach (array_values($payload['charms']) as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(["payload.charms.$index" => 'A reviewed Charm row must be an object.']);
            }
            $this->closedKeys($row, ['slot_id', 'level'], "payload.charms.$index");
            $slotId = $this->identifier($row['slot_id'] ?? null, 64, "payload.charms.$index.slot_id");
            if (isset($seen[$slotId])) {
                throw ValidationException::withMessages(["payload.charms.$index.slot_id" => 'A Charm slot may appear only once in one observation.']);
            }
            $seen[$slotId] = true;
            $item = ['slot_id' => $slotId];
            $this->optionalInteger($item, 'level', $row, 0, $maxLevel, "payload.charms.$index.level");
            if (count($item) === 1) {
                throw ValidationException::withMessages(["payload.charms.$index" => 'A Charm slot must contain a reviewed level.']);
            }
            $rows[] = $item;
        }
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['slot_id'], (string) $b['slot_id']));

        return ['charms' => $rows];
    }

    /** @return list<array<string,mixed>> */
    private function gearRows(
        mixed $value,
        bool $allowMastery,
        string $field,
        ?int $maxLevel = null,
        ?int $maxSecondary = null,
        ?ProgressionDataset $dataset = null,
    ): array {
        if (! is_array($value) || $value === [] || count($value) > 12) {
            throw ValidationException::withMessages([$field => 'A Gear observation must contain 1-12 reviewed slots.']);
        }
        $rows = [];
        $seen = [];
        foreach (array_values($value) as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(["$field.$index" => 'A reviewed Gear row must be an object.']);
            }
            $allowed = $allowMastery
                ? ['slot_id', 'quality', 'level', 'mastery_level']
                : ['slot_id', 'quality', 'level', 'star'];
            $this->closedKeys($row, $allowed, "$field.$index");
            $slotId = $this->identifier($row['slot_id'] ?? null, 64, "$field.$index.slot_id");
            if (isset($seen[$slotId])) {
                throw ValidationException::withMessages(["$field.$index.slot_id" => 'A Gear slot may appear only once in one observation.']);
            }
            $seen[$slotId] = true;
            $item = ['slot_id' => $slotId];
            if (array_key_exists('quality', $row) && $row['quality'] !== null && $row['quality'] !== '') {
                $quality = $this->line($row['quality'], 48, "$field.$index.quality");
                $item['quality'] = ! $allowMastery && $dataset !== null
                    ? $this->canonicalGovernorGearTier($quality, $dataset, "$field.$index.quality")
                    : $quality;
            }
            if (array_key_exists('level', $row) && $row['level'] !== null && $row['level'] !== '') {
                $item['level'] = $this->boundedByPinnedMaximum($row['level'], $maxLevel, "$field.$index.level", $allowMastery);
            }
            if ($allowMastery) {
                if (array_key_exists('mastery_level', $row) && $row['mastery_level'] !== null && $row['mastery_level'] !== '') {
                    $item['mastery_level'] = $this->boundedByPinnedMaximum($row['mastery_level'], $maxSecondary, "$field.$index.mastery_level", true);
                }
            } elseif (array_key_exists('star', $row) && $row['star'] !== null && $row['star'] !== '') {
                $item['star'] = $this->boundedByPinnedMaximum($row['star'], $maxSecondary, "$field.$index.star", true);
            }
            if (! $allowMastery && $dataset !== null && isset($item['quality'], $item['star'])) {
                $this->assertGovernorGearStep($dataset, (string) $item['quality'], (int) $item['star'], "$field.$index");
            }
            if (count($item) === 1) {
                throw ValidationException::withMessages(["$field.$index" => 'A Gear slot must contain at least one reviewed progression fact.']);
            }
            $rows[] = $item;
        }
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['slot_id'], (string) $b['slot_id']));

        return $rows;
    }

    private function boundedByPinnedMaximum(mixed $value, ?int $max, string $field, bool $requirePinnedBound): int
    {
        if ($max === null || $max < 1) {
            if ($requirePinnedBound) {
                throw ValidationException::withMessages([$field => 'The pinned Progression dataset does not expose a factual bound for this reviewed field.']);
            }

            return $this->integer($value, 0, 1000, $field);
        }

        return $this->integer($value, 0, $max, $field);
    }

    private function heroId(mixed $value, ProgressionDataset $dataset, string $field): string
    {
        if (! is_string($value) || ($canonical = $this->progression->canonicalHeroId($value, $dataset)) === null) {
            throw ValidationException::withMessages([$field => 'Hero identity must exist in the pinned factual Progression dataset.']);
        }

        return $canonical;
    }

    private function maximumCharmLevel(ProgressionDataset $dataset): int
    {
        $catalogue = $dataset->catalogue('governor_charms');
        if (! is_array($catalogue)) {
            throw ValidationException::withMessages(['payload.charms' => 'The pinned Progression dataset does not expose the Governor Charm level ladder.']);
        }
        $data = $catalogue['data'] ?? null;
        $levels = is_array($data) && is_array($data['charmLevels'] ?? null) ? $data['charmLevels'] : [];
        $max = 0;
        foreach ($levels as $row) {
            if (is_array($row) && is_int($row['level'] ?? null)) {
                $max = max($max, $row['level']);
            }
        }
        if ($max < 1) {
            throw ValidationException::withMessages(['payload.charms' => 'The pinned Progression dataset does not expose the Governor Charm level ladder.']);
        }

        return $max;
    }

    private function publishedDatabaseMaximum(ProgressionDataset $dataset, string $pageId): ?int
    {
        $catalogue = $dataset->catalogue('database_tables');
        if (! is_array($catalogue) || ! is_array($catalogue['pages'] ?? null)) {
            return null;
        }
        foreach ($catalogue['pages'] as $page) {
            if (! is_array($page) || ($page['id'] ?? null) !== $pageId || ! is_array($page['tables'] ?? null)) {
                continue;
            }
            $max = 0;
            foreach ($page['tables'] as $table) {
                if (! is_array($table) || ! is_array($table['rows'] ?? null)) {
                    continue;
                }
                foreach ($table['rows'] as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $raw = $row['Level'] ?? null;
                    $parsed = is_int($raw) ? $raw : (is_string($raw) && ctype_digit($raw) ? (int) $raw : null);
                    if ($parsed !== null) {
                        $max = max($max, $parsed);
                    }
                }
            }

            return $max > 0 ? $max : null;
        }

        return null;
    }

    /** @return list<array<string,mixed>> */
    private function governorGearSteps(ProgressionDataset $dataset): array
    {
        $catalogue = $dataset->catalogue('governor_gear');
        $data = is_array($catalogue) ? ($catalogue['data'] ?? null) : null;
        $steps = is_array($data) && is_array($data['upgradeSteps'] ?? null) ? $data['upgradeSteps'] : [];

        return array_values(array_filter($steps, 'is_array'));
    }

    private function canonicalGovernorGearTier(string $value, ProgressionDataset $dataset, string $field): string
    {
        $needle = mb_strtolower(trim($value));
        foreach ($this->governorGearSteps($dataset) as $step) {
            $tier = $step['tier'] ?? null;
            if (is_string($tier) && mb_strtolower($tier) === $needle) {
                return $tier;
            }
        }

        throw ValidationException::withMessages([$field => 'Governor Gear tier/quality must exist in the pinned factual Progression dataset.']);
    }

    private function assertGovernorGearStep(ProgressionDataset $dataset, string $tier, int $star, string $field): void
    {
        foreach ($this->governorGearSteps($dataset) as $step) {
            if (($step['tier'] ?? null) === $tier && ($step['stars'] ?? null) === $star) {
                return;
            }
        }

        throw ValidationException::withMessages([$field => 'Governor Gear tier/star state must exist in the pinned factual Progression dataset.']);
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $allowed
     */
    private function closedKeys(array $value, array $allowed, string $field): void
    {
        $unknown = array_values(array_diff(array_keys($value), $allowed));
        if ($unknown !== []) {
            throw ValidationException::withMessages([$field => 'Unsupported reviewed field(s): '.implode(', ', $unknown).'.']);
        }
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $source
     */
    private function optionalInteger(array &$target, string $key, array $source, int $min, int $max, string $field): void
    {
        if (! array_key_exists($key, $source) || $source[$key] === null || $source[$key] === '') {
            return;
        }
        $target[$key] = $this->integer($source[$key], $min, $max, $field);
    }

    private function integer(mixed $value, int $min, int $max, string $field): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < $min || $parsed > $max) {
            throw ValidationException::withMessages([$field => "The reviewed integer must be between $min and $max."]);
        }

        return $parsed;
    }

    private function unsignedIntegerString(mixed $value, string $field): string
    {
        $value = is_int($value) ? (string) $value : (is_string($value) ? trim($value) : '');
        if (preg_match('/^\d{1,19}$/', $value) !== 1) {
            throw ValidationException::withMessages([$field => 'The reviewed value must contain 1-19 digits.']);
        }
        $canonical = ltrim($value, '0');

        return $canonical === '' ? '0' : $canonical;
    }

    private function line(mixed $value, int $max, string $field): string
    {
        if (! is_string($value) || ($value = trim($value)) === '' || mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => 'The reviewed text is missing or too long.']);
        }

        return $value;
    }

    private function identifier(mixed $value, int $max, string $field): string
    {
        $value = $this->line($value, $max, $field);
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $value) !== 1) {
            throw ValidationException::withMessages([$field => 'The reviewed identifier is invalid.']);
        }

        return $value;
    }
}
