from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    target = Path(path)
    text = target.read_text()
    if old not in text:
        raise SystemExit(f"expected source shape not found in {path}: {old[:120]!r}")
    if text.count(old) != 1:
        raise SystemExit(f"expected one source match in {path}, found {text.count(old)}")
    target.write_text(text.replace(old, new, 1))


path = 'app/Contexts/GameWorld/Progression/Queries/ProgressionTopologyQuery.php'
replace_once(
    path,
    "            ['id' => 'hero_level', 'label' => 'Hero Level', 'calculatorFamily' => null],",
    """            ['id' => 'hero_level', 'label' => 'Hero Level', 'calculatorFamily' => null],\n            ['id' => 'hero_widget', 'label' => 'Hero Exclusive Equipment / Widget', 'calculatorFamily' => null],""",
)
replace_once(
    path,
    "            ['id' => 'buildings', 'label' => 'Buildings', 'calculatorFamily' => 'buildings_truegold'],",
    """            ['id' => 'buildings', 'label' => 'Buildings', 'calculatorFamily' => 'buildings_truegold'],\n            ['id' => 'troop_tier', 'label' => 'Troop Tier', 'calculatorFamily' => 'troop_training_promotion'],\n            ['id' => 'vip', 'label' => 'VIP Level', 'calculatorFamily' => null],""",
)
replace_once(
    path,
    "            'hero_level' => array_map(static fn (array $hero): array => [\n                'id' => (string) ($hero['id'] ?? ''),\n                'label' => (string) ($hero['name'] ?? 'Hero'),\n                'context' => [],\n            ], $dataset->heroes),",
    """            'hero_level', 'hero_widget' => array_map(static fn (array $hero): array => [\n                'id' => (string) ($hero['id'] ?? ''),\n                'label' => (string) ($hero['name'] ?? 'Hero'),\n                'context' => [],\n            ], $dataset->heroes),""",
)
replace_once(
    path,
    "            'buildings' => $this->buildingSubjects($dataset),",
    """            'buildings' => $this->buildingSubjects($dataset),\n            'troop_tier' => $this->troopSubjects($dataset),\n            'vip' => [['id' => 'vip', 'label' => 'VIP', 'context' => []]],""",
)
replace_once(
    path,
    "            'hero_level' => $this->levelStates((int) ($dataset->systems['hero_progression']['max_level'] ?? 0), ['kingshotdata']),",
    """            'hero_level' => $this->levelStates((int) ($dataset->systems['hero_progression']['max_level'] ?? 0), $this->stringList($dataset->systems['hero_progression']['source_ids'] ?? [])),\n            'hero_widget' => $this->levelStatesWithZero((int) ($dataset->systems['exclusive_equipment']['max_level'] ?? 0), $this->stringList($dataset->systems['exclusive_equipment']['source_ids'] ?? [])),""",
)
replace_once(
    path,
    "            'buildings' => $this->buildingStates($dataset, $subjectId),",
    """            'buildings' => $this->buildingStates($dataset, $subjectId),\n            'troop_tier' => $this->troopTierStates($dataset, $subjectId),\n            'vip' => $this->vipStates($dataset),""",
)
replace_once(
    path,
    "    /** @return list<string> */\n    private function stringList(mixed $value): array",
    r'''    /**
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
    private function stringList(mixed $value): array''',
)

# Hero Widget observations already exist in the Hero detail/roster evidence model.
planner = 'app/ReadModels/Progression/Queries/ProgressionPlannerQuery.php'
replace_once(
    planner,
    "        } elseif ($family === 'hero_level') {\n            $hero = is_array($current['heroes'][$subject['id']] ?? null) ? $current['heroes'][$subject['id']] : [];\n            $heroFacts = is_array($hero['facts'] ?? null) ? $hero['facts'] : [];\n            $facts = $heroFacts;\n            $level = $this->factValue($heroFacts['level'] ?? null);\n            if (is_numeric($level)) {\n                $stateId = 'level:'.(int) $level;\n            }\n        } elseif (in_array($family, ['hero_gear_level', 'hero_mastery'], true)) {",
    """        } elseif (in_array($family, ['hero_level', 'hero_widget'], true)) {\n            $hero = is_array($current['heroes'][$subject['id']] ?? null) ? $current['heroes'][$subject['id']] : [];\n            $heroFacts = is_array($hero['facts'] ?? null) ? $hero['facts'] : [];\n            $facts = $heroFacts;\n            $factKey = $family === 'hero_widget' ? 'widget_level' : 'level';\n            $level = $this->factValue($heroFacts[$factKey] ?? null);\n            if (is_numeric($level)) {\n                $stateId = 'level:'.(int) $level;\n            }\n        } elseif (in_array($family, ['hero_gear_level', 'hero_mastery'], true)) {""",
)

print('Progression topology expansion edits applied successfully.')
