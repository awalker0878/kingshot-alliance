from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    target = Path(path)
    text = target.read_text()
    if old not in text:
        raise SystemExit(f"expected source shape not found in {path}: {old[:120]!r}")
    if text.count(old) != 1:
        raise SystemExit(f"expected one source match in {path}, found {text.count(old)}")
    target.write_text(text.replace(old, new, 1))


path = 'app/ReadModels/Progression/Queries/ProgressionPlannerQuery.php'
replace_once(
    path,
    '''        private readonly ProgressionTopologyQuery $topology,\n        private readonly CalculatorEligibilityQuery $eligibility,\n        private readonly ProgressionCalculator $calculator,''',
    '''        private readonly ProgressionTopologyQuery $topology,\n        private readonly CalculatorEligibilityQuery $eligibility,\n        private readonly ProgressionCalculator $calculator,\n        private readonly ProgressionPrerequisiteEvaluator $prerequisiteEvaluator,''',
)
replace_once(
    path,
    '''        $prerequisites = [];\n        foreach (is_array($target['prerequisites'] ?? null) ? $target['prerequisites'] : [] as $requirement) {\n            if (is_string($requirement) && trim($requirement) !== '') {\n                $prerequisites[] = ['label' => $requirement, 'status' => 'unknown'];\n            }\n        }''',
    '''        $prerequisites = $this->prerequisiteEvaluator->evaluate(\n            $dataset,\n            $observationState,\n            array_values(array_filter(\n                is_array($target['prerequisites'] ?? null) ? $target['prerequisites'] : [],\n                'is_string',\n            )),\n        );''',
)
replace_once(
    path,
    '''        } elseif (in_array($family, ['hero_gear_level', 'hero_mastery'], true)) {\n            $heroId = $subject['context']['heroId'] ?? null;\n            $slotId = $subject['context']['slotId'] ?? null;\n            $slot = is_string($heroId)\n                && is_string($slotId)\n                && is_array($current['heroes'][$heroId]['gear'][$slotId] ?? null)\n                ? $current['heroes'][$heroId]['gear'][$slotId]\n                : [];\n            $facts = $slot;\n            $value = $this->factValue($slot[$family === 'hero_mastery' ? 'mastery_level' : 'level'] ?? null);\n            if (is_numeric($value)) {\n                $stateId = 'level:'.(int) $value;\n            }\n        }''',
    '''        } elseif (in_array($family, ['hero_gear_level', 'hero_mastery'], true)) {\n            $heroId = $subject['context']['heroId'] ?? null;\n            $slotId = $subject['context']['slotId'] ?? null;\n            $slot = is_string($heroId)\n                && is_string($slotId)\n                && is_array($current['heroes'][$heroId]['gear'][$slotId] ?? null)\n                ? $current['heroes'][$heroId]['gear'][$slotId]\n                : [];\n            $facts = $slot;\n            $value = $this->factValue($slot[$family === 'hero_mastery' ? 'mastery_level' : 'level'] ?? null);\n            if (is_numeric($value)) {\n                $stateId = 'level:'.(int) $value;\n            }\n        } elseif (in_array($family, ['buildings', 'academy_research', 'war_academy_research'], true)) {\n            $projection = match ($family) {\n                'buildings' => 'buildings',\n                'academy_research' => 'academyResearch',\n                'war_academy_research' => 'warAcademyResearch',\n            };\n            $facts = is_array($current[$projection][$subject['id']] ?? null)\n                ? $current[$projection][$subject['id']]\n                : [];\n            $observedStateId = $this->factValue($facts['state_id'] ?? null);\n            $observedLevel = $this->factValue($facts['level'] ?? null);\n            if (is_string($observedStateId) && $observedStateId !== '') {\n                $stateId = $observedStateId;\n            } elseif (is_numeric($observedLevel)) {\n                $stateId = 'level:'.(int) $observedLevel;\n            }\n        }''',
)

print('Progression planner current-state and prerequisite edits applied successfully.')
