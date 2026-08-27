<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Queries;

use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;

final class ProgressionGoalPlannerQuery
{
    /** @var array<string,array{label:string,calculatorFamily:?string}> */
    private const FAMILIES = [
        'governor_gear' => ['label' => 'Governor Gear', 'calculatorFamily' => 'governor_gear'],
        'governor_charms' => ['label' => 'Governor Charms', 'calculatorFamily' => 'governor_charms'],
        'hero_level' => ['label' => 'Hero level', 'calculatorFamily' => null],
        'hero_gear' => ['label' => 'Hero Gear', 'calculatorFamily' => 'hero_gear'],
        'academy_research' => ['label' => 'Academy research', 'calculatorFamily' => 'research'],
        'buildings' => ['label' => 'Buildings', 'calculatorFamily' => 'buildings'],
    ];

    /**
     * @param  array<string,mixed>  $observedCurrent
     * @return array<string,mixed>
     */
    public function plan(
        ProgressionDataset $dataset,
        array $observedCurrent,
        ?string $family = null,
        ?string $subjectId = null,
        ?string $targetStateId = null,
    ): array {
        $family = is_string($family) && isset(self::FAMILIES[$family]) ? $family : null;
        $subjects = $family === null ? [] : $this->subjects($dataset, $observedCurrent, $family);
        $subject = $this->findById($subjects, $subjectId);
        $states = $family === null || $subject === null
            ? []
            : $this->states($dataset, $observedCurrent, $family, (string) $subject['id']);
        $target = $this->findById($states, $targetStateId);
        $current = $family === null || $subject === null
            ? null
            : $this->currentState($dataset, $observedCurrent, $family, (string) $subject['id'], $states);
        $comparison = $family === null || $subject === null || $target === null
            ? null
            : $this->compare($current, $target, $states);

        return [
            'availability' => 'available',
            'dataset' => [
                'id' => $dataset->id,
                'version' => $dataset->datasetVersion,
                'schemaVersion' => $dataset->schemaVersion,
                'checksum' => $dataset->checksum,
                'observedAt' => $dataset->observedAt,
            ],
            'families' => array_map(
                static fn (string $id, array $definition): array => [
                    'id' => $id,
                    'label' => $definition['label'],
                    'calculatorFamily' => $definition['calculatorFamily'],
                ],
                array_keys(self::FAMILIES),
                array_values(self::FAMILIES),
            ),
            'selection' => [
                'family' => $family,
                'subjectId' => $subject['id'] ?? null,
                'targetStateId' => $target['id'] ?? null,
            ],
            'subjects' => $subjects,
            'states' => $states,
            'current' => $current,
            'target' => $target,
            'comparison' => $comparison,
            'conflicts' => $family === null ? [] : $this->familyConflicts($dataset, $family),
        ];
    }

    /**
     * @param  array<string,mixed>  $observedCurrent
     * @return list<array{id:string,label:string,status:string}>
     */
    private function subjects(ProgressionDataset $dataset, array $observedCurrent, string $family): array
    {
        return match ($family) {
            'governor_gear' => $this->governorGearSubjects($dataset, $observedCurrent),
            'governor_charms' => $this->charmSubjects($observedCurrent),
            'hero_level' => array_values(array_map(static fn (array $hero): array => [
                'id' => (string) ($hero['id'] ?? ''),
                'label' => (string) ($hero['name'] ?? $hero['id'] ?? ''),
                'status' => 'supported',
            ], $dataset->heroes)),
            'hero_gear' => $this->heroGearSubjects($dataset, $observedCurrent),
            'academy_research' => $this->researchSubjects($dataset),
            'buildings' => $this->buildingSubjects($dataset),
            default => [],
        };
    }

    /**
     * @param  array<string,mixed>  $observedCurrent
     * @return list<array{id:string,label:string,status:string}>
     */
    private function governorGearSubjects(ProgressionDataset $dataset, array $observedCurrent): array
    {
        $subjects = [];
        $slots = $dataset->systems['governor_gear']['slots'] ?? [];
        if (is_array($slots)) {
            foreach ($slots as $slot) {
                if (! is_array($slot) || ! is_string($slot['slot'] ?? null)) {
                    continue;
                }
                $id = $slot['slot'];
                $subjects[$id] = ['id' => $id, 'label' => $this->headline($id), 'status' => 'supported'];
            }
        }
        $observed = $observedCurrent['governorGear'] ?? [];
        if (is_array($observed)) {
            foreach (array_keys($observed) as $id) {
                if (is_string($id) && ! isset($subjects[$id])) {
                    $subjects[$id] = ['id' => $id, 'label' => $this->headline($id), 'status' => 'supported'];
                }
            }
        }

        return array_values($subjects);
    }

    /**
     * @param  array<string,mixed>  $observedCurrent
     * @return list<array{id:string,label:string,status:string}>
     */
    private function charmSubjects(array $observedCurrent): array
    {
        $subjects = [];
        $observed = $observedCurrent['charms'] ?? [];
        if (is_array($observed)) {
            foreach (array_keys($observed) as $id) {
                if (is_string($id)) {
                    $subjects[] = ['id' => $id, 'label' => $this->headline($id), 'status' => 'supported'];
                }
            }
        }
        if ($subjects === []) {
            $subjects[] = ['id' => 'governor_charms', 'label' => 'Governor Charms', 'status' => 'not_observed'];
        }

        return $subjects;
    }

    /**
     * @param  array<string,mixed>  $observedCurrent
     * @return list<array{id:string,label:string,status:string}>
     */
    private function heroGearSubjects(ProgressionDataset $dataset, array $observedCurrent): array
    {
        $heroNames = [];
        foreach ($dataset->heroes as $hero) {
            if (is_string($hero['id'] ?? null)) {
                $heroNames[$hero['id']] = (string) ($hero['name'] ?? $hero['id']);
            }
        }

        $subjects = [];
        $heroes = $observedCurrent['heroes'] ?? [];
        if (! is_array($heroes)) {
            return [];
        }
        foreach ($heroes as $heroId => $hero) {
            if (! is_string($heroId) || ! is_array($hero) || ! is_array($hero['gear'] ?? null)) {
                continue;
            }
            foreach (array_keys($hero['gear']) as $slotId) {
                if (! is_string($slotId)) {
                    continue;
                }
                $subjects[] = [
                    'id' => $heroId.'|'.$slotId,
                    'label' => ($heroNames[$heroId] ?? $heroId).' · '.$this->headline($slotId),
                    'status' => 'supported_observed_quality_only',
                ];
            }
        }

        return $subjects;
    }

    /** @return list<array{id:string,label:string,status:string}> */
    private function researchSubjects(ProgressionDataset $dataset): array
    {
        $document = $dataset->catalogue('academy_research');
        $technologies = is_array($document) ? ($document['technologies'] ?? []) : [];
        if (! is_array($technologies)) {
            return [];
        }

        $subjects = [];
        foreach ($technologies as $technology) {
            if (! is_array($technology) || ! is_string($technology['id'] ?? null)) {
                continue;
            }
            $subjects[] = [
                'id' => $technology['id'],
                'label' => (string) ($technology['name'] ?? $technology['id']),
                'status' => ($technology['levels_status'] ?? null) === 'source_table_missing' ? 'source_gap' : 'supported',
            ];
        }

        return $subjects;
    }

    /** @return list<array{id:string,label:string,status:string}> */
    private function buildingSubjects(ProgressionDataset $dataset): array
    {
        $document = $dataset->catalogue('buildings_tables');
        $pages = is_array($document) ? ($document['pages'] ?? []) : [];
        if (! is_array($pages)) {
            return [];
        }

        $subjects = [];
        foreach ($pages as $page) {
            if (! is_array($page) || ! is_string($page['id'] ?? null)) {
                continue;
            }
            $subjects[] = [
                'id' => $page['id'],
                'label' => (string) ($page['name'] ?? $page['id']),
                'status' => 'supported',
            ];
        }

        return $subjects;
    }

    /**
     * @param  array<string,mixed>  $observedCurrent
     * @return list<array<string,mixed>>
     */
    private function states(ProgressionDataset $dataset, array $observedCurrent, string $family, string $subjectId): array
    {
        return match ($family) {
            'governor_gear' => $this->governorGearStates($dataset),
            'governor_charms' => $this->charmStates($dataset),
            'hero_level' => $this->levelStates((int) ($dataset->systems['hero_progression']['max_level'] ?? 0), 'Level'),
            'hero_gear' => $this->heroGearStates($dataset, $observedCurrent, $subjectId),
            'academy_research' => $this->researchStates($dataset, $subjectId),
            'buildings' => $this->buildingStates($dataset, $subjectId),
            default => [],
        };
    }

    /** @return list<array<string,mixed>> */
    private function governorGearStates(ProgressionDataset $dataset): array
    {
        $document = $dataset->catalogue('governor_gear');
        $steps = is_array($document) ? ($document['data']['upgradeSteps'] ?? []) : [];
        if (! is_array($steps)) {
            return [];
        }

        $states = [];
        foreach ($steps as $index => $row) {
            if (! is_array($row) || ! is_string($row['tier'] ?? null) || ! is_numeric($row['stars'] ?? null)) {
                continue;
            }
            $stars = (int) $row['stars'];
            $states[] = [
                'id' => 'step-'.($index + 1),
                'label' => $row['tier'].' · ★'.$stars,
                'ordinal' => count($states),
                'facts' => ['tier' => $row['tier'], 'stars' => $stars],
                'sourceIds' => $this->stringList($row['source_ids'] ?? []),
                'evidenceStatus' => is_string($row['evidence_status'] ?? null) ? $row['evidence_status'] : null,
                'prerequisites' => [],
            ];
        }

        return $states;
    }

    /** @return list<array<string,mixed>> */
    private function charmStates(ProgressionDataset $dataset): array
    {
        $document = $dataset->catalogue('governor_charms');
        $levels = is_array($document) ? ($document['data']['charmLevels'] ?? []) : [];
        if (! is_array($levels)) {
            return [];
        }

        $states = [];
        foreach ($levels as $row) {
            if (! is_array($row) || ! is_numeric($row['level'] ?? null)) {
                continue;
            }
            $level = (int) $row['level'];
            $states[] = [
                'id' => 'level-'.$level,
                'label' => 'Level '.$level,
                'ordinal' => count($states),
                'facts' => ['level' => $level],
                'sourceIds' => $this->stringList($row['source_ids'] ?? []),
                'evidenceStatus' => is_string($row['evidence_status'] ?? null) ? $row['evidence_status'] : null,
                'prerequisites' => [],
            ];
        }

        return $states;
    }

    /** @return list<array<string,mixed>> */
    private function levelStates(int $maxLevel, string $label): array
    {
        $states = [];
        for ($level = 1; $level <= $maxLevel; $level++) {
            $states[] = [
                'id' => 'level-'.$level,
                'label' => $label.' '.$level,
                'ordinal' => $level - 1,
                'facts' => ['level' => $level],
                'sourceIds' => [],
                'evidenceStatus' => 'canonicalized_summary',
                'prerequisites' => [],
            ];
        }

        return $states;
    }

    /**
     * @param  array<string,mixed>  $observedCurrent
     * @return list<array<string,mixed>>
     */
    private function heroGearStates(ProgressionDataset $dataset, array $observedCurrent, string $subjectId): array
    {
        [$heroId, $slotId] = array_pad(explode('|', $subjectId, 2), 2, null);
        if (! is_string($heroId) || ! is_string($slotId)) {
            return [];
        }
        $gear = $observedCurrent['heroes'][$heroId]['gear'][$slotId] ?? null;
        if (! is_array($gear)) {
            return [];
        }
        $quality = $this->factValue($gear['quality'] ?? null);
        if (! is_string($quality) || $quality === '') {
            return [];
        }
        $qualityKey = mb_strtolower(trim($quality));
        $caps = $dataset->systems['hero_gear']['quality_level_caps'] ?? [];
        $max = is_array($caps) && is_numeric($caps[$qualityKey] ?? null) ? (int) $caps[$qualityKey] : 0;
        if ($max < 1) {
            return [];
        }

        $states = $this->levelStates($max, $this->headline($quality).' level');
        foreach ($states as &$state) {
            $state['id'] = 'quality-'.$this->slug($qualityKey).'-'.$state['id'];
            $state['facts']['quality'] = $qualityKey;
            $state['evidenceStatus'] = 'observed_quality_topology';
        }
        unset($state);

        return $states;
    }

    /** @return list<array<string,mixed>> */
    private function researchStates(ProgressionDataset $dataset, string $subjectId): array
    {
        $document = $dataset->catalogue('academy_research');
        $technologies = is_array($document) ? ($document['technologies'] ?? []) : [];
        if (! is_array($technologies)) {
            return [];
        }
        foreach ($technologies as $technology) {
            if (! is_array($technology) || ($technology['id'] ?? null) !== $subjectId) {
                continue;
            }
            $levels = $technology['levels'] ?? [];
            if (! is_array($levels)) {
                return [];
            }
            $states = [];
            foreach ($levels as $row) {
                if (! is_array($row) || ! is_numeric($row['Lv'] ?? null)) {
                    continue;
                }
                $level = (int) $row['Lv'];
                $requirement = is_string($row['Requirement'] ?? null) ? trim($row['Requirement']) : '';
                $states[] = [
                    'id' => 'level-'.$level,
                    'label' => 'Level '.$level,
                    'ordinal' => count($states),
                    'facts' => ['level' => $level],
                    'sourceIds' => is_string($technology['source_id'] ?? null) ? [$technology['source_id']] : [],
                    'evidenceStatus' => is_string($technology['levels_status'] ?? null) ? $technology['levels_status'] : null,
                    'prerequisites' => $requirement === '' || $requirement === '-'
                        ? []
                        : [['label' => $requirement, 'status' => 'not_observed']],
                ];
            }

            return $states;
        }

        return [];
    }

    /** @return list<array<string,mixed>> */
    private function buildingStates(ProgressionDataset $dataset, string $subjectId): array
    {
        $document = $dataset->catalogue('buildings_tables');
        $pages = is_array($document) ? ($document['pages'] ?? []) : [];
        if (! is_array($pages)) {
            return [];
        }
        foreach ($pages as $page) {
            if (! is_array($page) || ($page['id'] ?? null) !== $subjectId || ! is_array($page['tables'] ?? null)) {
                continue;
            }
            $states = [];
            foreach ($page['tables'] as $table) {
                if (! is_array($table) || ! is_array($table['rows'] ?? null)) {
                    continue;
                }
                foreach ($table['rows'] as $row) {
                    if (! is_array($row) || ! is_numeric($row['Level'] ?? null)) {
                        continue;
                    }
                    $level = (int) $row['Level'];
                    if (isset($states[$level])) {
                        continue;
                    }
                    $townCenter = is_string($row['Town Center Level'] ?? null) ? trim($row['Town Center Level']) : '';
                    $states[$level] = [
                        'id' => 'level-'.$level,
                        'label' => 'Level '.$level,
                        'ordinal' => $level - 1,
                        'facts' => ['level' => $level],
                        'sourceIds' => is_string($document['source_id'] ?? null) ? [$document['source_id']] : [],
                        'evidenceStatus' => 'source_table',
                        'prerequisites' => $townCenter === '' || $townCenter === '-'
                            ? []
                            : [[
                                'label' => 'Town Center Level '.$townCenter,
                                'status' => $subjectId === 'town-center' ? 'not_applicable' : 'not_observed',
                            ]],
                    ];
                }
            }
            ksort($states);

            return array_values($states);
        }

        return [];
    }

    /**
     * @param  array<string,mixed>  $observedCurrent
     * @param  list<array<string,mixed>>  $states
     * @return array<string,mixed>|null
     */
    private function currentState(
        ProgressionDataset $dataset,
        array $observedCurrent,
        string $family,
        string $subjectId,
        array $states,
    ): ?array {
        return match ($family) {
            'governor_gear' => $this->governorGearCurrent($observedCurrent, $subjectId, $states),
            'governor_charms' => $this->numericCurrent($observedCurrent['charms'][$subjectId]['level'] ?? null, $states, 'level'),
            'hero_level' => $this->numericCurrent($observedCurrent['heroes'][$subjectId]['facts']['level'] ?? null, $states, 'level'),
            'hero_gear' => $this->heroGearCurrent($observedCurrent, $subjectId, $states),
            'academy_research', 'buildings' => ['status' => 'not_observed', 'state' => null, 'provenance' => null],
            default => null,
        };
    }

    /** @param list<array<string,mixed>> $states */
    private function governorGearCurrent(array $observedCurrent, string $subjectId, array $states): array
    {
        $gear = $observedCurrent['governorGear'][$subjectId] ?? null;
        if (! is_array($gear) || $gear === []) {
            return ['status' => 'not_observed', 'state' => null, 'provenance' => null];
        }
        $qualityFact = $gear['quality'] ?? null;
        $levelFact = $gear['level'] ?? null;
        $starFact = $gear['star'] ?? null;
        $quality = $this->factValue($qualityFact);
        $level = $this->factValue($levelFact);
        $star = $this->factValue($starFact);
        if (! is_string($quality) || $quality === '') {
            return ['status' => 'unknown_current', 'state' => null, 'provenance' => $this->provenance($qualityFact ?? $levelFact ?? $starFact)];
        }

        $candidates = [$this->normalize($quality)];
        if (is_numeric($level) && (int) $level > 0) {
            $candidates[] = $this->normalize($quality.' T'.(int) $level);
        }
        $matches = array_values(array_filter($states, function (array $state) use ($candidates, $star): bool {
            $tier = $state['facts']['tier'] ?? null;
            $stateStar = $state['facts']['stars'] ?? null;
            if (! is_string($tier) || ! in_array($this->normalize($tier), $candidates, true)) {
                return false;
            }

            return ! is_numeric($star) || (int) $star === (int) $stateStar;
        }));
        if (count($matches) !== 1) {
            return ['status' => 'unknown_current', 'state' => null, 'provenance' => $this->provenance($starFact ?? $levelFact ?? $qualityFact)];
        }

        return ['status' => 'known', 'state' => $matches[0], 'provenance' => $this->provenance($starFact ?? $levelFact ?? $qualityFact)];
    }

    /** @param list<array<string,mixed>> $states */
    private function numericCurrent(mixed $fact, array $states, string $factKey): array
    {
        $value = $this->factValue($fact);
        if ($value === null) {
            return ['status' => 'not_observed', 'state' => null, 'provenance' => null];
        }
        if (! is_numeric($value)) {
            return ['status' => 'unknown_current', 'state' => null, 'provenance' => $this->provenance($fact)];
        }
        foreach ($states as $state) {
            if ((int) ($state['facts'][$factKey] ?? -1) === (int) $value) {
                return ['status' => 'known', 'state' => $state, 'provenance' => $this->provenance($fact)];
            }
        }

        return ['status' => 'unknown_current', 'state' => null, 'provenance' => $this->provenance($fact)];
    }

    /** @param list<array<string,mixed>> $states */
    private function heroGearCurrent(array $observedCurrent, string $subjectId, array $states): array
    {
        [$heroId, $slotId] = array_pad(explode('|', $subjectId, 2), 2, null);
        $gear = is_string($heroId) && is_string($slotId)
            ? ($observedCurrent['heroes'][$heroId]['gear'][$slotId] ?? null)
            : null;
        if (! is_array($gear)) {
            return ['status' => 'not_observed', 'state' => null, 'provenance' => null];
        }
        $qualityFact = $gear['quality'] ?? null;
        $levelFact = $gear['level'] ?? null;
        $quality = $this->factValue($qualityFact);
        $level = $this->factValue($levelFact);
        if (! is_string($quality) || ! is_numeric($level)) {
            return ['status' => 'unknown_current', 'state' => null, 'provenance' => $this->provenance($levelFact ?? $qualityFact)];
        }
        $expected = 'quality-'.$this->slug(mb_strtolower(trim($quality))).'-level-'.(int) $level;
        foreach ($states as $state) {
            if (($state['id'] ?? null) === $expected) {
                return ['status' => 'known', 'state' => $state, 'provenance' => $this->provenance($levelFact)];
            }
        }

        return ['status' => 'unknown_current', 'state' => null, 'provenance' => $this->provenance($levelFact ?? $qualityFact)];
    }

    /**
     * @param  array<string,mixed>|null  $current
     * @param  array<string,mixed>  $target
     * @param  list<array<string,mixed>>  $states
     * @return array<string,mixed>
     */
    private function compare(?array $current, array $target, array $states): array
    {
        if ($current === null || ($current['status'] ?? null) === 'not_observed') {
            return [
                'status' => 'not_observed',
                'remainingTransitions' => null,
                'path' => [],
                'prerequisites' => $target['prerequisites'] ?? [],
            ];
        }
        if (($current['status'] ?? null) !== 'known' || ! is_array($current['state'] ?? null)) {
            return [
                'status' => 'unknown_current',
                'remainingTransitions' => null,
                'path' => [],
                'prerequisites' => $target['prerequisites'] ?? [],
            ];
        }
        $currentOrdinal = $current['state']['ordinal'] ?? null;
        $targetOrdinal = $target['ordinal'] ?? null;
        if (! is_int($currentOrdinal) || ! is_int($targetOrdinal)) {
            return [
                'status' => 'unsupported_topology',
                'remainingTransitions' => null,
                'path' => [],
                'prerequisites' => $target['prerequisites'] ?? [],
            ];
        }
        if ($targetOrdinal === $currentOrdinal) {
            return [
                'status' => 'same_state',
                'remainingTransitions' => 0,
                'path' => [],
                'prerequisites' => $target['prerequisites'] ?? [],
            ];
        }
        if ($targetOrdinal < $currentOrdinal) {
            return [
                'status' => 'unsupported_direction',
                'remainingTransitions' => null,
                'path' => [],
                'prerequisites' => $target['prerequisites'] ?? [],
            ];
        }

        $path = array_values(array_filter(
            $states,
            static fn (array $state): bool => is_int($state['ordinal'] ?? null)
                && $state['ordinal'] > $currentOrdinal
                && $state['ordinal'] <= $targetOrdinal,
        ));

        return [
            'status' => count($path) === $targetOrdinal - $currentOrdinal ? 'comparable' : 'unsupported_topology',
            'remainingTransitions' => count($path) === $targetOrdinal - $currentOrdinal ? count($path) : null,
            'path' => array_map(static fn (array $state): array => [
                'id' => $state['id'],
                'label' => $state['label'],
                'sourceIds' => $state['sourceIds'] ?? [],
                'evidenceStatus' => $state['evidenceStatus'] ?? null,
            ], $path),
            'prerequisites' => $target['prerequisites'] ?? [],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function familyConflicts(ProgressionDataset $dataset, string $family): array
    {
        $aliases = match ($family) {
            'academy_research' => ['academy_research'],
            'buildings' => ['buildings'],
            default => [$family],
        };

        return array_values(array_filter(
            $dataset->conflicts(),
            static fn (array $conflict): bool => is_string($conflict['family'] ?? null)
                && in_array($conflict['family'], $aliases, true),
        ));
    }

    /** @param list<array<string,mixed>> $items */
    private function findById(array $items, ?string $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    private function factValue(mixed $fact): mixed
    {
        return is_array($fact) && array_key_exists('value', $fact) ? $fact['value'] : null;
    }

    /** @return array<string,mixed>|null */
    private function provenance(mixed $fact): ?array
    {
        if (! is_array($fact)) {
            return null;
        }

        return [
            'capturedAt' => is_string($fact['capturedAt'] ?? null) ? $fact['capturedAt'] : null,
            'observationId' => is_string($fact['observationId'] ?? null) ? $fact['observationId'] : null,
            'evidenceId' => is_string($fact['evidenceId'] ?? null) ? $fact['evidenceId'] : null,
            'reviewId' => is_string($fact['reviewId'] ?? null) ? $fact['reviewId'] : null,
            'datasetId' => is_string($fact['datasetId'] ?? null) ? $fact['datasetId'] : null,
            'datasetChecksum' => is_string($fact['datasetChecksum'] ?? null) ? $fact['datasetChecksum'] : null,
        ];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_map('strval', array_filter($value, 'is_string'))));
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }

    private function slug(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower($value)), '-');
    }

    private function headline(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }
}
