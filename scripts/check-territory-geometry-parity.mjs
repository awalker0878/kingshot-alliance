import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

import {
  analyzeLayout,
  validatePlacement,
} from '../resources/js/features/territory-planner/engine/geometry.ts';

const fixtureUrl = new URL('../tests/v3/Fixtures/territory-geometry.json', import.meta.url);
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
