import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { spatialCollisionCodes } from '../../../../../resources/js/features/territory-planner/engine/spatial.ts';
import { validatePlacement } from '../../../../../resources/js/features/territory-planner/engine/geometry.ts';
import type {
  MapData,
  MapWorldRectangle,
  PlanObject,
} from '../../../../../resources/js/features/territory-planner/engine/types.ts';

type Fixture = {
  dataset: { data: MapData };
  queries: Array<{ name: string; footprint: MapWorldRectangle; expected_codes: string[] }>;
  validation_cases: Array<{ name: string; object: PlanObject; expected_codes: string[] }>;
};
function fixture(): Fixture {
  return JSON.parse(
    readFileSync(new URL('../Fixtures/spatial-geometry.json', import.meta.url), 'utf8'),
  );
}

test('exact cells/resources, nonzero origins, shared edges and 300 seeded queries match the PHP fixture', () => {
  const data = fixture();
  for (const query of data.queries)
    assert.deepEqual(
      spatialCollisionCodes(data.dataset.data, query.footprint),
      query.expected_codes,
      query.name,
    );
});

test('browser validation uses rotated logical footprints and retains independent source conflicts', () => {
  const data = fixture();
  for (const query of data.validation_cases)
    assert.deepEqual(
      validatePlacement(data.dataset.data, [query.object], {}).violations.map(
        (issue) => issue.code,
      ),
      query.expected_codes,
      query.name,
    );
  const outside = { ...data.validation_cases[0].object, x: 99, y: 199 };
  assert.deepEqual(
    validatePlacement(data.dataset.data, [outside], {}).violations.map((issue) => issue.code),
    ['map_bounds'],
  );
});

test('visibility cannot disable authoritative facts and immutable map objects have isolated caches', () => {
  const map = fixture().dataset.data;
  const rect = { x: 110, y: 210, width: 1, height: 1 };
  assert.deepEqual(spatialCollisionCodes(map, rect), ['terrain_collision', 'resource_collision']);
  const hidden = { ...map, layers: { terrain: { visible: false }, resources: { visible: false } } };
  assert.deepEqual(spatialCollisionCodes(hidden, rect), [
    'terrain_collision',
    'resource_collision',
  ]);
  const different = { ...map, terrain_features: [], resource_nodes: [] };
  assert.deepEqual(spatialCollisionCodes(different, rect), []);
  assert.deepEqual(spatialCollisionCodes(map, rect), ['terrain_collision', 'resource_collision']);
});

test('only sourced blocking declarations create constraints and incomplete hydration is an error', () => {
  const map = fixture().dataset.data;
  map.resource_layers!.terrain = { data_state: 'materialized', placement_blocking: false };
  assert.deepEqual(spatialCollisionCodes(map, { x: 110, y: 210, width: 1, height: 1 }), [
    'resource_collision',
  ]);
  const incomplete = fixture().dataset.data;
  delete incomplete.terrain_features;
  assert.throws(
    () => spatialCollisionCodes(incomplete, { x: 110, y: 210, width: 1, height: 1 }),
    /not been hydrated/,
  );
});
