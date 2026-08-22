from pathlib import Path
import json


def replace_once(source: str, old: str, new: str, label: str) -> str:
    if old not in source:
        raise RuntimeError(f"Missing expected Territory domain anchor: {label}")
    return source.replace(old, new, 1)


php = Path('app/Contexts/GameWorld/KingdomMaps/Services/PlacementValidator.php')
source = php.read_text()
if '$countsByAlliance = [];' not in source:
    source = replace_once(
        source,
        '        $rectangles = [];\n\n        foreach ($objects as $object) {',
        '        $rectangles = [];\n        $countsByAlliance = [];\n\n        foreach ($objects as $object) {',
        'PHP count storage',
    )
    anchor = """            $size = (int) ($definition['size'] ?? 0);
            if ($size < 1) {
                $violations[] = $this->issue('invalid_object_footprint', 'The selected map dataset has no valid footprint for this object.', $object['key']);

                continue;
            }
"""
    addition = anchor + """            $countKey = $object['alliance_key'].'|'.$object['type'];
            $countsByAlliance[$countKey] = ($countsByAlliance[$countKey] ?? 0) + 1;
            $maximum = $definition['max_per_alliance'] ?? null;
            if (is_int($maximum) && $maximum > 0 && $countsByAlliance[$countKey] > $maximum) {
                $violations[] = $this->issue(
                    'alliance_object_cap',
                    'This Alliance exceeds the selected map dataset object cap.',
                    $object['key'],
                );
            }
"""
    source = replace_once(source, anchor, addition, 'PHP dataset cap validation')
php.write_text(source)


ts = Path('resources/js/features/territory-planner/engine/geometry.ts')
source = ts.read_text()
if 'const countsByAlliance = new Map<string, number>();' not in source:
    source = replace_once(
        source,
        '  const rectangles = new Map<string, Rect>();\n  const bounds = map.bounds;\n',
        '  const rectangles = new Map<string, Rect>();\n  const countsByAlliance = new Map<string, number>();\n  const bounds = map.bounds;\n',
        'TypeScript count storage',
    )
    anchor = """    rectangles.set(object.key, rect);
    if (!inside(rect, bounds)) {
"""
    addition = """    const definition = map.object_types[object.type];
    const countKey = `${object.alliance_key}|${object.type}`;
    const count = (countsByAlliance.get(countKey) ?? 0) + 1;
    countsByAlliance.set(countKey, count);
    if (definition.max_per_alliance && count > definition.max_per_alliance) {
      violations.push(
        issue(
          'alliance_object_cap',
          'This Alliance exceeds the selected map dataset object cap.',
          object.key,
        ),
      );
    }
    rectangles.set(object.key, rect);
    if (!inside(rect, bounds)) {
"""
    source = replace_once(source, anchor, addition, 'TypeScript dataset cap validation')
ts.write_text(source)


save = Path('app/Contexts/Operations/TerritoryPlanning/Actions/SaveTerritoryPlan.php')
source = save.read_text()
source = source.replace('        $counts = [];\n', '', 1)
cap_block = """            $counts[$allianceKey][$type] = ($counts[$allianceKey][$type] ?? 0) + 1;
            if (
                ($type === TerritoryObjectType::Banner->value && $counts[$allianceKey][$type] > 285)
                || ($type === TerritoryObjectType::GovernorCity->value && $counts[$allianceKey][$type] > 100)
                || ($type === TerritoryObjectType::Headquarters->value && $counts[$allianceKey][$type] > 1)
                || ($type === TerritoryObjectType::BearTrap->value && $counts[$allianceKey][$type] > 2)
            ) {
                throw ValidationException::withMessages([
                    'objects' => 'A planned Alliance exceeds the supported object cap.',
                ]);
            }

"""
if cap_block in source:
    source = source.replace(cap_block, '', 1)
elif 'A planned Alliance exceeds the supported object cap.' in source:
    raise RuntimeError('Hard-coded Operations object cap remains but expected block changed.')
save.write_text(source)


fixture = Path('tests/v3/Fixtures/territory-geometry.json')
data = json.loads(fixture.read_text())
cases = data['validation_cases']
if not any(case.get('name') == 'dataset-object-cap' for case in cases):
    cases.append({
        'name': 'dataset-object-cap',
        'preferences': {},
        'objects': [
            {'key': 'hq-one', 'type': 'headquarters', 'x': 20, 'y': 20, 'alliance_key': 'alpha'},
            {'key': 'hq-two', 'type': 'headquarters', 'x': 30, 'y': 20, 'alliance_key': 'alpha'},
        ],
        'expected_violations': ['alliance_object_cap:hq-two'],
        'expected_warnings': [],
        'expected_suggestions': [],
    })
fixture.write_text(json.dumps(data, indent=2, ensure_ascii=False) + '\n')

print('Territory dataset-owned object caps finalized.')
