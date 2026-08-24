<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Queries;

use App\Contexts\GameWorld\Progression\Enums\ProgressionFactKind;
use App\Contexts\GameWorld\Progression\Enums\ProgressionFactResolution;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionFactRequest;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionFactResult;

final readonly class ProgressionFactQuery
{
    public function __construct(private ProgressionDatasetQuery $datasets) {}

    public function answer(ProgressionFactRequest $request): ProgressionFactResult
    {
        $dataset = $this->datasets->latest();

        return match ($request->kind) {
            ProgressionFactKind::HeroGeneration => $this->heroFact($dataset, $request, 'generation', 'Generation'),
            ProgressionFactKind::HeroTroopClass => $this->heroFact($dataset, $request, 'troop_class', 'Troop class'),
            ProgressionFactKind::SystemMaxLevel => $this->systemMaxLevel($dataset, $request),
            ProgressionFactKind::GovernorGearRequirement => $this->governorGear($dataset, $request),
            ProgressionFactKind::TroopTierStats => $this->troopTier($dataset, $request),
            ProgressionFactKind::AcademyResearchLevel => $this->academyLevel($dataset, $request),
        };
    }

    private function heroFact(
        ProgressionDataset $dataset,
        ProgressionFactRequest $request,
        string $field,
        string $label,
    ): ProgressionFactResult {
        $needle = $this->normalize($request->subject);
        $matches = array_values(array_filter(
            $dataset->heroes,
            fn (array $hero): bool => in_array($needle, [
                $this->normalize((string) ($hero['id'] ?? '')),
                $this->normalize((string) ($hero['name'] ?? '')),
            ], true),
        ));

        if (count($matches) > 1 || $this->hasUnresolvedFamilyConflict($dataset, 'heroes')) {
            return $this->emptyResult(
                $dataset,
                ProgressionFactResolution::Conflicting,
                'heroes',
                'heroes.'.$needle,
                $request->subject,
                evidenceStatus: 'conflicting',
            );
        }

        $hero = $matches[0] ?? null;
        if (! is_array($hero)) {
            return $this->emptyResult($dataset, ProgressionFactResolution::Unknown, 'heroes', 'heroes.'.$needle, $request->subject);
        }

        $value = $hero[$field] ?? null;
        $name = (string) ($hero['name'] ?? $request->subject);
        $path = 'heroes.'.(string) ($hero['id'] ?? $needle).'.'.$field;
        if (! is_scalar($value)) {
            return $this->emptyResult(
                $dataset,
                ProgressionFactResolution::Unknown,
                'heroes',
                $path,
                $name,
                $this->stringList($hero['source_ids'] ?? []),
                $this->firstString($hero, ['confidence', 'source_confidence']),
                $this->firstString($hero, ['evidence_status', 'source_status', 'skill_source_status']),
            );
        }

        return new ProgressionFactResult(
            ProgressionFactResolution::Known,
            'heroes',
            $path,
            $name,
            ['fact' => $label, 'value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value],
            $dataset->id,
            $dataset->datasetVersion,
            $dataset->checksum,
            $dataset->observedAt,
            $this->stringList($hero['source_ids'] ?? []),
            $this->firstString($hero, ['confidence', 'source_confidence']),
            $this->firstString($hero, ['evidence_status', 'source_status', 'skill_source_status']),
        );
    }

    private function systemMaxLevel(ProgressionDataset $dataset, ProgressionFactRequest $request): ProgressionFactResult
    {
        $needle = $this->normalize($request->subject);
        $systemKey = match ($needle) {
            'widget', 'widgets', 'exclusive equipment', 'exclusive weapon', 'hero exclusive equipment' => 'exclusive_equipment',
            'hero', 'hero level', 'heroes' => 'hero_progression',
            default => null,
        };

        if ($systemKey === null) {
            return $this->emptyResult($dataset, ProgressionFactResolution::Unknown, 'max_levels', 'systems.'.$needle, $request->subject);
        }

        $system = $dataset->systems[$systemKey] ?? null;
        $maxLevel = is_array($system) ? ($system['max_level'] ?? null) : null;
        if (! is_scalar($maxLevel)) {
            return $this->emptyResult(
                $dataset,
                ProgressionFactResolution::Unknown,
                'max_levels',
                'systems.'.$systemKey.'.max_level',
                $request->subject,
                is_array($system) ? $this->stringList($system['source_ids'] ?? []) : [],
            );
        }

        return new ProgressionFactResult(
            ProgressionFactResolution::Known,
            'max_levels',
            'systems.'.$systemKey.'.max_level',
            $systemKey === 'exclusive_equipment' ? 'Widgets' : 'Hero level',
            ['maxLevel' => (string) $maxLevel],
            $dataset->id,
            $dataset->datasetVersion,
            $dataset->checksum,
            $dataset->observedAt,
            is_array($system) ? $this->stringList($system['source_ids'] ?? []) : [],
            evidenceStatus: 'canonicalized_summary',
        );
    }

    private function governorGear(ProgressionDataset $dataset, ProgressionFactRequest $request): ProgressionFactResult
    {
        $document = $dataset->catalogue('governor_gear');
        $pathBase = 'governor_gear.upgradeSteps';
        if ($document === null || $request->level === null) {
            return $this->emptyResult(
                $dataset,
                ProgressionFactResolution::Unknown,
                'governor_gear',
                $pathBase,
                trim($request->subject.' '.(string) $request->level),
                evidenceStatus: $request->level === null ? 'level_required' : null,
            );
        }

        $steps = $document['data']['upgradeSteps'] ?? null;
        if (! is_array($steps)) {
            return $this->emptyResult($dataset, ProgressionFactResolution::Unknown, 'governor_gear', $pathBase, $request->subject);
        }

        $needle = $this->normalize($request->subject);
        /** @var list<array{index:int,row:array<string,mixed>}> $matches */
        $matches = [];
        foreach ($steps as $index => $row) {
            if (! is_array($row)
                || $this->normalize((string) ($row['tier'] ?? '')) !== $needle
                || (int) ($row['stars'] ?? -1) !== $request->level) {
                continue;
            }
            $matches[] = ['index' => (int) $index, 'row' => $row];
        }

        if (count($matches) > 1 || $this->hasUnresolvedFamilyConflict($dataset, 'governor_gear')) {
            return $this->emptyResult(
                $dataset,
                ProgressionFactResolution::Conflicting,
                'governor_gear',
                $pathBase,
                trim($request->subject.' '.$request->level),
                evidenceStatus: 'conflicting',
            );
        }

        $match = $matches[0] ?? null;
        if ($match === null) {
            return $this->emptyResult(
                $dataset,
                ProgressionFactResolution::Unknown,
                'governor_gear',
                $pathBase,
                trim($request->subject.' '.$request->level),
                $this->documentSourceIds($document),
                $this->sourceMetaConfidence($document),
            );
        }

        $index = $match['index'];
        $row = $match['row'];
        $materials = is_array($row['materials'] ?? null) ? $row['materials'] : [];
        $bonuses = is_array($row['bonuses'] ?? null) ? $row['bonuses'] : [];
        $sourceIds = $this->stringList($row['source_ids'] ?? []);
        if ($sourceIds === []) {
            $sourceIds = $this->documentSourceIds($document);
        }

        return new ProgressionFactResult(
            ProgressionFactResolution::Known,
            'governor_gear',
            $pathBase.'.'.($index + 1),
            trim((string) ($row['tier'] ?? $request->subject).' '.(string) ($row['stars'] ?? $request->level)),
            [
                'tier' => $this->scalarString($row['tier'] ?? null),
                'stars' => $this->scalarString($row['stars'] ?? null),
                'satin' => $this->scalarString($materials['satin'] ?? null),
                'gildedThreads' => $this->scalarString($materials['gilded_threads'] ?? null),
                'artisansVision' => $this->scalarString($materials['artisans_vision'] ?? null),
                'attack' => $this->scalarString($bonuses['attack'] ?? null),
                'defense' => $this->scalarString($bonuses['defense'] ?? null),
                'powerTotal' => $this->scalarString($row['power_total'] ?? null),
            ],
            $dataset->id,
            $dataset->datasetVersion,
            $dataset->checksum,
            $dataset->observedAt,
            $sourceIds,
            $this->scalarString($row['confidence'] ?? null) ?? $this->sourceMetaConfidence($document),
            $this->scalarString($row['evidence_status'] ?? null),
        );
    }

    private function troopTier(ProgressionDataset $dataset, ProgressionFactRequest $request): ProgressionFactResult
    {
        $document = $dataset->catalogue('troops');
        $classKey = match ($this->normalize($request->subject)) {
            'infantry' => 'infantry',
            'cavalry', 'lancer', 'lancers' => 'lancer',
            'archer', 'archers' => 'archer',
            default => null,
        };
        if ($document === null || $classKey === null || $request->level === null || $request->level < 1) {
            return $this->emptyResult(
                $dataset,
                ProgressionFactResolution::Unknown,
                'troops',
                'troops.'.($classKey ?? $this->normalize($request->subject)),
                $request->subject,
                $document === null ? [] : $this->documentSourceIds($document),
                $document === null ? null : $this->sourceMetaConfidence($document),
                $request->level === null ? 'tier_required' : null,
            );
        }

        $troops = $document['data']['troops'] ?? null;
        $class = is_array($troops) ? ($troops[$classKey] ?? null) : null;
        $tiers = is_array($class) ? ($class['tiers'] ?? null) : null;
        $tierKey = 't'.$request->level;
        $row = is_array($tiers) ? ($tiers[$tierKey] ?? null) : null;
        if (! is_array($row)) {
            return $this->emptyResult(
                $dataset,
                ProgressionFactResolution::Unknown,
                'troops',
                'troops.'.$classKey.'.'.$tierKey,
                ucfirst($classKey).' T'.$request->level,
                $this->documentSourceIds($document),
                $this->sourceMetaConfidence($document),
            );
        }

        $points = is_array($row['pts'] ?? null) ? $row['pts'] : [];

        return new ProgressionFactResult(
            ProgressionFactResolution::Known,
            'troops',
            'troops.'.$classKey.'.'.$tierKey,
            (string) ($row['label'] ?? ucfirst($classKey).' T'.$request->level),
            [
                'food' => $this->scalarString($row['food'] ?? null),
                'wood' => $this->scalarString($row['wood'] ?? null),
                'stone' => $this->scalarString($row['stone'] ?? null),
                'iron' => $this->scalarString($row['iron'] ?? null),
                'timeSec' => $this->scalarString($row['timeSec'] ?? null),
                'hogPoints' => $this->scalarString($points['hog'] ?? null),
                'kvkPoints' => $this->scalarString($points['kvk'] ?? null),
                'tsgPoints' => $this->scalarString($points['tsg'] ?? null),
                'status' => $this->scalarString($row['status'] ?? null),
            ],
            $dataset->id,
            $dataset->datasetVersion,
            $dataset->checksum,
            $dataset->observedAt,
            $this->documentSourceIds($document),
            $this->sourceMetaConfidence($document),
            $this->scalarString($row['status'] ?? null),
        );
    }

    private function academyLevel(ProgressionDataset $dataset, ProgressionFactRequest $request): ProgressionFactResult
    {
        $document = $dataset->catalogue('academy_research');
        $needle = $this->normalize($request->subject);
        if ($document === null || $needle === '') {
            return $this->emptyResult($dataset, ProgressionFactResolution::Unknown, 'academy_research', 'academy_research.'.$needle, $request->subject);
        }

        $technologies = $document['technologies'] ?? null;
        if (! is_array($technologies)) {
            return $this->emptyResult($dataset, ProgressionFactResolution::Unknown, 'academy_research', 'academy_research.'.$needle, $request->subject);
        }

        $matches = array_values(array_filter(
            $technologies,
            fn (mixed $technology): bool => is_array($technology) && in_array($needle, [
                $this->normalize((string) ($technology['id'] ?? '')),
                $this->normalize((string) ($technology['name'] ?? '')),
            ], true),
        ));

        if (count($matches) > 1 || $this->hasUnresolvedFamilyConflict($dataset, 'academy_research')) {
            return $this->emptyResult(
                $dataset,
                ProgressionFactResolution::Conflicting,
                'academy_research',
                'academy_research.'.$needle,
                $request->subject,
                evidenceStatus: 'conflicting',
            );
        }

        $technology = $matches[0] ?? null;
        if (! is_array($technology)) {
            return $this->emptyResult(
                $dataset,
                ProgressionFactResolution::Unknown,
                'academy_research',
                'academy_research.'.$needle,
                $request->subject,
                $this->documentSourceIds($document),
            );
        }

        $id = (string) ($technology['id'] ?? $needle);
        $name = (string) ($technology['name'] ?? $request->subject);
        $sourceIds = $this->stringList(isset($technology['source_id']) ? [$technology['source_id']] : []);
        if ($sourceIds === []) {
            $sourceIds = $this->documentSourceIds($document);
        }
        $levelsStatus = $this->scalarString($technology['levels_status'] ?? null);

        if ($request->level === null) {
            return $this->emptyResult(
                $dataset,
                ProgressionFactResolution::Unknown,
                'academy_research',
                'academy_research.'.$id,
                $name,
                $sourceIds,
                evidenceStatus: 'level_required',
            );
        }

        $levels = $technology['levels'] ?? null;
        if (! is_array($levels) || $levels === []) {
            $gap = $this->sourceGap($dataset, 'academy_research', $name);

            return $this->emptyResult(
                $dataset,
                ProgressionFactResolution::Unknown,
                'academy_research',
                'academy_research.'.$id.'.level.'.$request->level,
                $name,
                $sourceIds,
                evidenceStatus: is_array($gap)
                    ? $this->scalarString($gap['status'] ?? null)
                    : ($levelsStatus ?? 'unknown_level_table'),
            );
        }

        foreach ($levels as $index => $level) {
            if (! is_array($level) || (int) ($level['Lv'] ?? 0) !== $request->level) {
                continue;
            }

            return new ProgressionFactResult(
                ProgressionFactResolution::Known,
                'academy_research',
                'academy_research.'.$id.'.level.'.($index + 1),
                $name.' Lv.'.$request->level,
                [
                    'level' => (string) $request->level,
                    'effect' => $this->scalarString($level['Effect'] ?? null),
                    'requirement' => $this->scalarString($level['Requirement'] ?? null),
                    'researchCost' => $this->scalarString($level['Research cost'] ?? null),
                    'time' => $this->scalarString($level['Time'] ?? null),
                    'power' => $this->scalarString($level['Power'] ?? null),
                    'tree' => $this->scalarString($technology['tree'] ?? null),
                    'maxLevel' => $this->scalarString($technology['max_level'] ?? null),
                ],
                $dataset->id,
                $dataset->datasetVersion,
                $dataset->checksum,
                $dataset->observedAt,
                $sourceIds,
                $this->sourceMetaConfidence($document),
                $levelsStatus,
            );
        }

        return $this->emptyResult(
            $dataset,
            ProgressionFactResolution::Unknown,
            'academy_research',
            'academy_research.'.$id.'.level.'.$request->level,
            $name,
            $sourceIds,
            $this->sourceMetaConfidence($document),
            $levelsStatus,
        );
    }

    /** @param list<string> $sourceIds */
    private function emptyResult(
        ProgressionDataset $dataset,
        ProgressionFactResolution $resolution,
        string $family,
        string $path,
        string $title,
        array $sourceIds = [],
        ?string $confidence = null,
        ?string $evidenceStatus = null,
    ): ProgressionFactResult {
        return new ProgressionFactResult(
            $resolution,
            $family,
            $path,
            $title,
            [],
            $dataset->id,
            $dataset->datasetVersion,
            $dataset->checksum,
            $dataset->observedAt,
            $sourceIds,
            $confidence,
            $evidenceStatus,
        );
    }

    private function hasUnresolvedFamilyConflict(ProgressionDataset $dataset, string $family): bool
    {
        foreach ($dataset->conflicts() as $conflict) {
            if (($conflict['family'] ?? null) !== $family) {
                continue;
            }
            $status = $this->scalarString($conflict['resolution_status'] ?? null);
            if ($status === null || ! str_starts_with($status, 'resolved')) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,mixed>|null */
    private function sourceGap(ProgressionDataset $dataset, string $family, string $entity): ?array
    {
        $needle = $this->normalize($entity);
        foreach ($dataset->sourceGaps() as $gap) {
            if (($gap['family'] ?? null) === $family
                && $this->normalize((string) ($gap['entity'] ?? '')) === $needle) {
                return $gap;
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $document
     * @return list<string>
     */
    private function documentSourceIds(array $document): array
    {
        $sourceId = $document['source_id'] ?? null;

        return is_string($sourceId) && $sourceId !== '' ? [$sourceId] : [];
    }

    /** @param array<string,mixed> $document */
    private function sourceMetaConfidence(array $document): ?string
    {
        $meta = $document['source_meta'] ?? null;

        return is_array($meta) ? $this->scalarString($meta['confidence'] ?? null) : null;
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  list<string>  $keys
     */
    private function firstString(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->scalarString($row[$key] ?? null);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private function scalarString(mixed $value): ?string
    {
        if ($value === null || (! is_scalar($value))) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('/[^\pL\pN]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
