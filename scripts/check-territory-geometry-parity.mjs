import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

import {
  analyzeLayout,
  rectFor,
  coverageRect,
  unionArea,
  validatePlacement,
} from '../resources/js/features/territory-planner/engine/geometry.ts';

const fixtureUrl = new URL(
  '../tests/Contexts/GameWorld/KingdomMaps/Fixtures/territory-geometry.json',
  import.meta.url,
);
const fixture = JSON.parse(await readFile(fileURLToPath(fixtureUrl), 'utf8'));
const map = fixture.dataset.data;

function issueKeys(issues) {
  return issues.map((issue) => `${issue.code}:${issue.object_key ?? ''}`).sort();
}

for (const testCase of fixture.validation_cases) {
  const result = validatePlacement(map, testCase.objects, testCase.preferences);
  assert.deepStrictEqual(
    issueKeys(result.violations),
    [...testCase.expected_violations].sort(),
    `${testCase.name}: violation contract drifted`,
  );
  assert.deepStrictEqual(
    issueKeys(result.warnings),
    [...testCase.expected_warnings].sort(),
    `${testCase.name}: warning contract drifted`,
  );
  assert.deepStrictEqual(
    issueKeys(result.suggestions),
    [...testCase.expected_suggestions].sort(),
    `${testCase.name}: suggestion contract drifted`,
  );
}

for (const testCase of fixture.geometry_cases) {
  const geometryMap = structuredClone(map);
  geometryMap.object_types.headquarters = {
    footprint: testCase.footprint,
    coverage: testCase.coverage,
  };
  const object = {
    type: 'headquarters',
    x: testCase.x,
    y: testCase.y,
    rotation: testCase.rotation,
  };
  assert.deepStrictEqual(rectFor(object, geometryMap), testCase.expected_footprint);
  assert.deepStrictEqual(coverageRect(object, geometryMap), testCase.expected_coverage);
}
assert.equal(
  unionArea([
    { x: 0, y: 0, width: 3, height: 3 },
    { x: 1, y: 1, width: 3, height: 3 },
  ]),
  14,
);
assert.equal(unionArea([{ x: 0, y: 0, width: 1000000, height: 1000000 }]), 1000000000000);

const analysis = analyzeLayout(
  map,
  fixture.analysis_case.objects,
  fixture.analysis_case.preferences,
);
assert.deepStrictEqual(
  analysis.alpha,
  fixture.analysis_case.expected,
  'Territory analysis contract drifted from the shared golden fixture',
);

console.log(
  `Territory geometry parity: ${fixture.validation_cases.length} validation cases and analysis fixture passed.`,
);
