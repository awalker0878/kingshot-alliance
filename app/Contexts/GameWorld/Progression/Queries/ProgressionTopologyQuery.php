<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Queries;

use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;
use Illuminate\Validation\ValidationException;

final class ProgressionTopologyQuery
{
    /** @return list<array{id:string,label:string,calculatorFamily:?string}> */
    public function families(): array
    {
        return [
            ['id' => 'governor_gear', 'label' => 'Governor Gear', 'calculatorFamily' => 'governor_gear'],
            ['id' => 'governor_charms', 'label' => 'Governor Charms', 'calculatorFamily' => 'governor_charms'],
            ['id' => 'hero_level', 'label' => 'Hero Level', 'calculatorFamily' => null],
            ['id' => 'hero_widget', 'label' => 'Hero Exclusive Equipment / Widget', 'calculatorFamily' => null],
            ['id' => 'hero_gear_level', 'label' => 'Hero Gear Level', 'calculatorFamily' => 'hero_gear_mastery'],
            ['id' => 'hero_mastery', 'label' => 'Hero Gear Mastery', 'calculatorFamily' => 'hero_gear_mastery'],
            ['id' => 'academy_research', 'label' => 'Academy Research', 'calculatorFamily' => 'research'],
            ['id' => 'war_academy_research', 'label' => 'War Academy Research', 'calculatorFamily' => 'research'],
            ['id' => 'buildings', 'label' => 'Buildings', 'calculatorFamily' => 'buildings_truegold'],
            ['id' => 'troop_tier', 'label' => 'Troop Tier', 'calculatorFamily' => 'troop_training_promotion'],
            ['id' => 'vip', 'label' => 'VIP Level', 'calculatorFamily' => null],
        ];
    }

    public function calculatorFamily(string $family): ?string
    {
        foreach ($this->families() as $definition) {
            if ($definition['id'] === $family) {
                return $definition['calculatorFamily'];
            }
        }

        return null;
    }

    /** @return list<array{id:string,label:string,context:array<string,mixed>}> */
    public function subjects(ProgressionDataset $dataset, string $family): array
    {
        return match ($family) {
            'governor_gear' => array_map(static fn (array $slot): array => [
                'id' => (string) ($slot['slot'] ?? ''),
                'label' => ucfirst((string) ($slot['slot'] ?? 'Gear')),
                'context' => [],
            ], array_values(array_filter($dataset->systems['governor_gear']['slots'] ?? [], 'is_array'))),
            'governor_charms' => [['id' => 'charm', 'label' => 'Governor Charm', 'context' => []]],
            'hero_level', 'hero_widget' => array_map(static fn (array $hero): array => [
                'id' => (string) ($hero['id'] ?? ''),
                'label' => (string) ($hero['name'] ?? 'Hero'),
                'context' => [],
            ], $dataset->heroes),
            'hero_gear_level' => $this->heroGearQualitySubjects($dataset),
            'hero_mastery' => [['id' => 'mastery', 'label' => 'Mastery Forge', 'context' => []]],
            'academy_research' => $this->academySubjects($dataset),
            'war_academy_research' => $this->warAcademySubjects($dataset),
            'buildings' => $this->buildingSubjects($dataset),
            'troop_tier' => $this->troopSubjects($dataset),
            'vip' => [['id' => 'vip', 'label' => 'VIP', 'context' => []]],
            default => throw ValidationException::withMessages(['family' => 'The selected progression planner family is unsupported.']),
        };
    }

    /**
     * @param  array<string,mixed>  $context
     * @return list<array{id:string,label:string,ordinal:int,sourceIds:list<string>,evidenceStatus:string,prerequisites:list<string>,attributes:array<string,mixed>}>
     */
    public function states(ProgressionDataset $dataset, string $family, string $subjectId, array $context = []): array
    {
        return match ($family) {
            'governor_gear' => $this->governorGearStates($dataset),
            'governor_charms' => $this->governorCharmStates($dataset),
            'hero_level' => $this->levelStates((int) ($dataset->systems['hero_progression']['max_level'] ?? 0), $this->stringList($dataset->systems['hero_progression']['source_ids'] ?? [])),
            'hero_widget' => $this->levelStatesWithZero((int) ($dataset->systems['exclusive_equipment']['max_level'] ?? 0), $this->stringList($dataset->systems['exclusive_equipment']['source_ids'] ?? [])),
            'hero_gear_level' => $this->heroGearLevelStates($dataset, (string) ($context['quality'] ?? $subjectId)),
            'hero_mastery' => $this->masteryStates($dataset),
            'academy_research' => $this->academyStates($dataset, $subjectId),
            'war_academy_research' => $this->warAcademyStates($dataset, $subjectId),
            'buildings' => $this->buildingStates($dataset, $subjectId),
            'troop_tier' => $this->troopTierStates($dataset, $subjectId),
            'vip' => $this->vipStates($dataset),
            default => throw ValidationException::withMessages(['family' => 'The selected progression planner family is unsupported.']),
        };
    }

    /**
     * @param  list<array{id:string,label:string,ordinal:int,sourceIds:list<string>,evidenceStatus:string,prerequisites:list<string>,attributes:array<string,mixed>}>  $states
     * @return array{status:string,current:?array<string,mixed>,target:?array<string,mixed>,path:list<array<string,mixed>>,remainingTransitions:?int,reason:?string}
     */
    public function compare(array $states, ?string $currentStateId, string $targetStateId): array
    {
        $byId = [];
        foreach ($states as $state) {
            $byId[$state['id']] = $state;
        }
        $target = $byId[$targetStateId] ?? null;
        if ($target === null) {
            return ['status' => 'invalid', 'current' => null, 'target' => null, 'path' => [], 'remainingTransitions' => null, 'reason' => 'Target state is unavailable in the pinned dataset.'];
        }
        if ($currentStateId === null) {
            return ['status' => 'unknown_current', 'current' => null, 'target' => $target, 'path' => [], 'remainingTransitions' => null, 'reason' => 'No authorized observed current state is available.'];
        }
        $current = $byId[$currentStateId] ?? null;
        if ($current === null) {
            return ['status' => 'unsupported_current', 'current' => null, 'target' => $target, 'path' => [], 'remainingTransitions' => null, 'reason' => 'The observed current state cannot be resolved in the pinned dataset.'];
        }
        if ($target['ordinal'] < $current['ordinal']) {
            return ['status' => 'invalid_reverse', 'current' => $current, 'target' => $target, 'path' => [], 'remainingTransitions' => null, 'reason' => 'The selected target is behind the observed current state.'];
        }

        $path = array_values(array_filter(
            $states,
            static fn (array $state): bool => $state['ordinal'] >= $current['ordinal'] && $state['ordinal'] <= $target['ordinal'],
        ));

        return [
            'status' => 'comparable',
            'current' => $current,
            'target' => $target,
            'path' => $path,
            'remainingTransitions' => $target['ordinal'] - $current['ordinal'],
            'reason' => null,
        ];
    }

    /** @return list<array{id:string,label:string,ordinal:int,sourceIds:list<string>,evidenceStatus:string,prerequisites:list<string>,attributes:array<string,mixed>}> */
    private function governorGearStates(ProgressionDataset $dataset): array
    {
        $document = $dataset->catalogue('governor_gear');
        $data = is_array($document) && is_array($document['data'] ?? null) ? $document['data'] : [];
        $rows = is_array($data['upgradeSteps'] ?? null) ? $data['upgradeSteps'] : [];
        $states = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row) || ! is_string($row['tier'] ?? null) || ! is_int($row['stars'] ?? null)) {
                continue;
            }
            $states[] = [
                'id' => 'step:'.$index,
                'label' => $row['tier'].($row['stars'] > 0 ? ' ★'.$row['stars'] : ''),
                'ordinal' => $index,
                'sourceIds' => $this->stringList($row['source_ids'] ?? []),
                'evidenceStatus' => is_string($row['evidence_status'] ?? null) ? $row['evidence_status'] : 'unknown',
                'prerequisites' => [],
                'attributes' => ['tier' => $row['tier'], 'stars' => $row['stars']],
            ];
        }

        return $states;
    }

    /** @return list<array{id:string,label:string,ordinal:int,sourceIds:list<string>,evidenceStatus:string,prerequisites:list<string>,attributes:array<string,mixed>}> */
    private function governorCharmStates(ProgressionDataset $dataset): array
    {
        $document = $dataset->catalogue('governor_charms');
        $data = is_array($document) && is_array($document['data'] ?? null) ? $document['data'] : [];
        $rows = is_array($data['charmLevels'] ?? null) ? $data['charmLevels'] : [];
        $states = [[
            'id' => 'level:0', 'label' => 'Level 0', 'ordinal' => 0, 'sourceIds' => [],
            'evidenceStatus' => 'explicit_unupgraded_boundary', 'prerequisites' => [], 'attributes' => ['level' => 0],
        ]];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_int($row['level'] ?? null)) {
                continue;
            }
            $level = $row['level'];
            $states[] = [
                'id' => 'level:'.$level,
                'label' => 'Level '.$level,
                'ordinal' => $level,
                'sourceIds' => $this->stringList($row['source_ids'] ?? []),
                'evidenceStatus' => is_string($row['evidence_status'] ?? null) ? $row['evidence_status'] : 'unknown',
                'prerequisites' => [],
                'attributes' => ['level' => $level],
            ];
        }

        return $states;
    }

    /**
     * @param  list<string>  $sourceIds
     * @return list<array{id:string,label:string,ordinal:int,sourceIds:list<string>,evidenceStatus:string,prerequisites:list<string>,attributes:array<string,mixed>}>
     */
    private function levelStates(int $maxLevel, array $sourceIds): array
    {
        $states = [];
        for ($level = 1; $level <= $maxLevel; $level++) {
            $states[] = [
                'id' => 'level:'.$level, 'label' => 'Level '.$level, 'ordinal' => $level,
                'sourceIds' => $sourceIds, 'evidenceStatus' => 'factual', 'prerequisites' => [], 'attributes' => ['level' => $level],
            ];
        }

        return $states;
    }

    /** @return list<array{id:string,label:string,context:array<string,mixed>}> */
    private function heroGearQualitySubjects(ProgressionDataset $dataset): array
    {
        $caps = $dataset->systems['hero_gear']['quality_level_caps'] ?? [];
        if (! is_array($caps)) {
            return [];
        }
        $subjects = [];
        foreach ($caps as $quality => $cap) {
            if (is_string($quality) && is_int($cap)) {
                $subjects[] = ['id' => $quality, 'label' => ucfirst($quality).' Hero Gear', 'context' => ['quality' => $quality]];
            }
        }

        return $subjects;
    }

    /** @return list<array{id:string,label:string,ordinal:int,sourceIds:list<string>,evidenceStatus:string,prerequisites:list<string>,attributes:array<string,mixed>}> */
    private function heroGearLevelStates(ProgressionDataset $dataset, string $quality): array
    {
        $cap = $dataset->systems['hero_gear']['quality_level_caps'][$quality] ?? null;
        if (! is_int($cap)) {
            return [];
        }

        return $this->levelStates($cap, $this->stringList($dataset->systems['hero_gear']['source_ids'] ?? []));
    }

    /** @return list<array{id:string,label:string,ordinal:int,sourceIds:list<string>,evidenceStatus:string,prerequisites:list<string>,attributes:array<string,mixed>}> */
    private function masteryStates(ProgressionDataset $dataset): array
    {
        $rows = $dataset->systems['hero_gear']['mastery_forging']['levels'] ?? [];
        $sourceIds = $this->stringList($dataset->systems['hero_gear']['source_ids'] ?? []);
        $states = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row) || ! is_int($row['level'] ?? null)) {
                continue;
            }
            $level = $row['level'];
            $states[] = [
                'id' => 'level:'.$level, 'label' => 'Mastery '.$level, 'ordinal' => $level,
                'sourceIds' => $sourceIds, 'evidenceStatus' => 'factual', 'prerequisites' => [], 'attributes' => ['level' => $level],
            ];
        }

        return $states;
    }

    /** @return list<array{id:string,label:string,context:array<string,mixed>}> */
    private function academySubjects(ProgressionDataset $dataset): array
    {
        $document = $dataset->catalogue('academy_research');
        $technologies = is_array($document) && is_array($document['technologies'] ?? null)
            ? $document['technologies']
            : [];
        $subjects = [];
        foreach ($technologies as $row) {
            if (is_array($row) && is_string($row['id'] ?? null) && is_string($row['name'] ?? null)) {
                $subjects[] = ['id' => $row['id'], 'label' => $row['name'], 'context' => ['tree' => $row['tree'] ?? null]];
            }
        }

        return $subjects;
    }

    /** @return list<array{id:string,label:string,ordinal:int,sourceIds:list<string>,evidenceStatus:string,prerequisites:list<string>,attributes:array<string,mixed>}> */
    private function academyStates(ProgressionDataset $dataset, string $subjectId): array
    {
        $document = $dataset->catalogue('academy_research');
        $technologies = is_array($document) && is_array($document['technologies'] ?? null)
            ? $document['technologies']
            : [];
        foreach ($technologies as $technology) {
            if (! is_array($technology) || ($technology['id'] ?? null) !== $subjectId) {
                continue;
            }
            if (($technology['levels_status'] ?? null) !== 'complete_visible_table') {
                return [];
            }
            $states = [];
            foreach (is_array($technology['levels'] ?? null) ? $technology['levels'] : [] as $row) {
                if (! is_array($row) || ! is_numeric($row['Lv'] ?? null)) {
                    continue;
                }
                $level = (int) $row['Lv'];
                $requirements = is_string($row['Requirement'] ?? null) && trim($row['Requirement']) !== ''
                    ? array_values(array_filter(array_map('trim', explode('·', $row['Requirement']))))
                    : [];
                $states[] = [
                    'id' => 'level:'.$level, 'label' => 'Level '.$level, 'ordinal' => $level,
                    'sourceIds' => is_string($technology['source_id'] ?? null) ? [$technology['source_id']] : [],
                    'evidenceStatus' => 'factual', 'prerequisites' => $requirements,
                    'attributes' => ['level' => $level, 'tree' => $technology['tree'] ?? null],
                ];
            }

            return $states;
        }

        return [];
    }

    /** @return list<array{id:string,label:string,context:array<string,mixed>}> */
    private function warAcademySubjects(ProgressionDataset $dataset): array
    {
        $document = $dataset->catalogue('war_academy');
        $data = is_array($document) && is_array($document['data'] ?? null) ? $document['data'] : [];
        $subjects = [];
        foreach (is_array($data['technologies'] ?? null) ? $data['technologies'] : [] as $row) {
            if (is_array($row) && is_string($row['id'] ?? null) && is_string($row['name'] ?? null)) {
                $subjects[] = ['id' => $row['id'], 'label' => $row['name'], 'context' => ['category' => $row['category'] ?? null]];
            }
        }

        return $subjects;
    }

    /** @return list<array{id:string,label:string,ordinal:int,sourceIds:list<string>,evidenceStatus:string,prerequisites:list<string>,attributes:array<string,mixed>}> */
    private function warAcademyStates(ProgressionDataset $dataset, string $subjectId): array
    {
        $document = $dataset->catalogue('war_academy');
        $data = is_array($document) && is_array($document['data'] ?? null) ? $document['data'] : [];
        $sourceId = is_array($document) && is_string($document['source_id'] ?? null) ? $document['source_id'] : null;
        foreach (is_array($data['technologies'] ?? null) ? $data['technologies'] : [] as $technology) {
            if (! is_array($technology) || ($technology['id'] ?? null) !== $subjectId) {
                continue;
            }
            $states = [];
            foreach (is_array($technology['levels'] ?? null) ? $technology['levels'] : [] as $row) {
                if (! is_array($row) || ! is_int($row['level'] ?? null)) {
                    continue;
                }
                $level = $row['level'];
                $states[] = [
                    'id' => 'level:'.$level, 'label' => 'Level '.$level, 'ordinal' => $level,
                    'sourceIds' => $sourceId !== null ? [$sourceId] : [], 'evidenceStatus' => 'factual',
                    'prerequisites' => [], 'attributes' => ['level' => $level, 'category' => $technology['category'] ?? null],
                ];
            }

            return $states;
        }

        return [];
    }

    /** @return list<array{id:string,label:string,context:array<string,mixed>}> */
    private function buildingSubjects(ProgressionDataset $dataset): array
    {
        $document = $dataset->catalogue('buildings_core');
        $data = is_array($document) && is_array($document['data'] ?? null) ? $document['data'] : [];
        $subjects = [];
        foreach (is_array($data['buildings'] ?? null) ? $data['buildings'] : [] as $row) {
            if (is_array($row) && is_string($row['key'] ?? null) && is_string($row['name'] ?? null)) {
                $subjects[] = ['id' => $row['key'], 'label' => $row['name'], 'context' => []];
            }
        }

        return $subjects;
    }

    /** @return list<array{id:string,label:string,ordinal:int,sourceIds:list<string>,evidenceStatus:string,prerequisites:list<string>,attributes:array<string,mixed>}> */
    private function buildingStates(ProgressionDataset $dataset, string $subjectId): array
    {
        $document = $dataset->catalogue('buildings_core');
        $data = is_array($document) && is_array($document['data'] ?? null) ? $document['data'] : [];
        $sourceId = is_array($document) && is_string($document['source_id'] ?? null) ? $document['source_id'] : null;
        foreach (is_array($data['buildings'] ?? null) ? $data['buildings'] : [] as $building) {
            if (! is_array($building) || ($building['key'] ?? null) !== $subjectId || ! is_int($building['maxLevel'] ?? null)) {
                continue;
            }

            return $this->levelStates($building['maxLevel'], $sourceId !== null ? [$sourceId] : []);
        }

        return [];
    }

    /**
     * @param list<string> $sourceIds
     * @return list<array{id:string,label:string,ordinal:int,sourceIds:list<string>,evidenceStatus:string,prerequisites:list<string>,attributes:array<string,mixed>}>
     */
    private function levelStatesWithZero(int $maxLevel, array $sourceIds): array
    {
        $states = [[
            'id' => 'level:0', 'label' => 'Level 0', 'ordinal' => 0,
            'sourceIds' => $sourceIds, 'evidenceStatus' => 'explicit_unupgraded_boundary',
            'prerequisites' => [], 'attributes' => ['level' => 0],
        ]];
        foreach ($this->levelStates($maxLevel, $sourceIds) as $state) {
            $states[] = $state;
        }

        return $states;
    }

    /** @return list<array{id:string,label:string,context:array<string,mixed>}> */
    private function troopSubjects(ProgressionDataset $dataset): array
    {
        $document = $dataset->catalogue('troops');
        $data = is_array($document) && is_array($document['data'] ?? null) ? $document['data'] : [];
        $troops = is_array($data['troops'] ?? null) ? $data['troops'] : [];
        $subjects = [];
        foreach ($troops as $id => $troop) {
            if (! is_string($id) || ! is_array($troop)) {
                continue;
            }
            $subjects[] = [
                'id' => $id,
                'label' => is_string($troop['name'] ?? null) ? $troop['name'] : ucfirst($id),
                'context' => [],
            ];
        }

        return $subjects;
    }

    /** @return list<array{id:string,label:string,ordinal:int,sourceIds:list<string>,evidenceStatus:string,prerequisites:list<string>,attributes:array<string,mixed>}> */
    private function troopTierStates(ProgressionDataset $dataset, string $subjectId): array
    {
        $document = $dataset->catalogue('troops');
        $data = is_array($document) && is_array($document['data'] ?? null) ? $document['data'] : [];
        $troops = is_array($data['troops'] ?? null) ? $data['troops'] : [];
        $troop = is_array($troops[$subjectId] ?? null) ? $troops[$subjectId] : null;
        if ($troop === null || ! is_array($troop['tiers'] ?? null)) {
            return [];
        }
        $sourceIds = is_array($document) && is_string($document['source_id'] ?? null) ? [$document['source_id']] : [];
        $states = [];
        foreach ($troop['tiers'] as $tierId => $row) {
            if (! is_string($tierId) || preg_match('/^t(?<tier>\d+)$/', $tierId, $matches) !== 1 || ! is_array($row)) {
                continue;
            }
            $tier = (int) $matches['tier'];
            $states[] = [
                'id' => $tierId,
                'label' => is_string($row['label'] ?? null) ? $row['label'] : strtoupper($tierId),
                'ordinal' => $tier,
                'sourceIds' => $sourceIds,
                'evidenceStatus' => is_string($row['status'] ?? null) ? mb_strtolower($row['status']) : 'unknown',
                'prerequisites' => [],
                'attributes' => ['tier' => $tier],
            ];
        }
        usort($states, static fn (array $a, array $b): int => $a['ordinal'] <=> $b['ordinal']);

        return $states;
    }

    /** @return list<array{id:string,label:string,ordinal:int,sourceIds:list<string>,evidenceStatus:string,prerequisites:list<string>,attributes:array<string,mixed>}> */
    private function vipStates(ProgressionDataset $dataset): array
    {
        $document = $dataset->catalogue('vip');
        $data = is_array($document) && is_array($document['data'] ?? null) ? $document['data'] : [];
        $rows = is_array($data['vipLevels'] ?? null) ? $data['vipLevels'] : [];
        $sourceIds = is_array($document) && is_string($document['source_id'] ?? null) ? [$document['source_id']] : [];
        $states = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_int($row['level'] ?? null)) {
                continue;
            }
            $level = $row['level'];
            $states[] = [
                'id' => 'level:'.$level,
                'label' => 'VIP '.$level,
                'ordinal' => $level,
                'sourceIds' => $sourceIds,
                'evidenceStatus' => 'factual',
                'prerequisites' => [],
                'attributes' => ['level' => $level],
            ];
        }

        return $states;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
