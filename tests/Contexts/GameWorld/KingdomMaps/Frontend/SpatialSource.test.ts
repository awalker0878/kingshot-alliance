import assert from 'node:assert/strict';
import test from 'node:test';
import { deflateSync, deflateRawSync } from 'node:zlib';
import {
  extractKingshotResources,
  extractKingshotTerrain,
  spatialSourceDiagnostics,
} from '../../../../../scripts/lib/kingdom-map-spatial-source.mjs';

function fixture(): { source: string; packed: Buffer } {
  const packed = Buffer.alloc(360000);
  for (const [x, y, value] of [
    [5, 5, 2],
    [6, 5, 2],
    [5, 6, 2],
    [20, 10, 1],
    [21, 10, 1],
  ]) {
    const cell = y * 1200 + x;
    packed[Math.floor(cell / 4)] |= value << ((cell % 4) * 2);
  }
  return {
    packed,
    source: `const GRID_SIZE = 1200;
const _TERRAIN_B64_KINGSHOT = '${deflateSync(packed).toString('base64')}';
const _LAKE_META_KINGSHOT = [5,5,7,7,5.3,5.3,3];
const _MT_META_KINGSHOT = [20,10,22,11,20.5,10,2];
const _LAKE_COUNT_KINGSHOT = 1;
const _MT_COUNT_KINGSHOT = 1;
const _TERRAIN_B64_WHITEOUT = 'this unrelated literal is deliberately never read';
globalThis.__untrusted_source_executed = true;`,
  };
}

function resources(nodes: unknown[], overrides: Record<string, unknown> = {}): string {
  return JSON.stringify({ game: 'kingshot', total: nodes.length, nodes, ...overrides });
}

test('exact connected-cell shapes, half-open bounds, centroids and row spans are retained without executing source', () => {
  const result = extractKingshotTerrain(fixture().source);
  assert.deepEqual(result.counts, { lake: 1, mountain: 1 });
  assert.deepEqual(result.cellCounts, { lake: 3, mountain: 2 });
  assert.deepEqual(result.features[0], {
    key: 'lake_0001',
    family: 'lake',
    bounds: { x: 5, y: 5, width: 2, height: 2 },
    centroid: { x: 5.3, y: 5.3 },
    cell_count: 3,
    spans: [
      [5, 5, 2],
      [5, 6, 1],
    ],
  });
  assert.equal(result.mask[6 * 1200 + 6], 0, 'empty corner of the bounding box is not terrain');
  assert.equal(Reflect.get(globalThis, '__untrusted_source_executed'), undefined);
});

test('a missing Kingshot literal never falls back to another game and duplicate definitions fail', () => {
  const { source } = fixture();
  assert.throws(
    () => extractKingshotTerrain(source.replace('_TERRAIN_B64_KINGSHOT', '_TERRAIN_B64_OTHER')),
    /exactly one/,
  );
  assert.throws(
    () => extractKingshotTerrain(source + '\nconst _MT_COUNT_KINGSHOT = 1;'),
    /exactly one/,
  );
  assert.throws(
    () => extractKingshotTerrain(source.replace('GRID_SIZE = 1200', 'GRID_SIZE = 1000')),
    /grid/,
  );
});

test('bitmap byte length, supported codes, compression contract and metadata are all checked', () => {
  const { source, packed } = fixture();
  const replaceBitmap = (data: Buffer) =>
    source.replace(
      /const _TERRAIN_B64_KINGSHOT = '[^']+';/,
      `const _TERRAIN_B64_KINGSHOT = '${data.toString('base64')}';`,
    );
  assert.throws(
    () => extractKingshotTerrain(replaceBitmap(deflateSync(Buffer.alloc(359999)))),
    /360000/,
  );
  assert.throws(() => extractKingshotTerrain(replaceBitmap(deflateSync(Buffer.alloc(360001)))));
  assert.throws(() => extractKingshotTerrain(replaceBitmap(deflateRawSync(packed))));
  packed[0] = 3;
  assert.throws(() => extractKingshotTerrain(replaceBitmap(deflateSync(packed))), /cell value/);
  assert.throws(
    () => extractKingshotTerrain(source.replace('5,5,7,7,5.3,5.3,3', '5,5,6,7,5.3,5.3,3')),
    /bounds\/count/,
  );
  assert.throws(() => extractKingshotTerrain(source.replace('5.3,5.3', '5.9,5.3')), /centroid/);
  assert.throws(
    () =>
      extractKingshotTerrain(
        source.replace('_LAKE_COUNT_KINGSHOT = 1', '_LAKE_COUNT_KINGSHOT = 2'),
      ),
    /metadata/,
  );
});

test('resource import is game-specific, exact, bounded and does not silently normalize old or malformed records', () => {
  const node = { id: 'r_10_20', type: 'woodmill', x: 10, y: 20 };
  const result = extractKingshotResources(resources([node]));
  assert.deepEqual(result, [
    { key: 'r_10_20', resource_type: 'woodmill', x: 10, y: 20, footprint: { width: 2, height: 2 } },
  ]);
  for (const invalid of [
    { ...node, x: '10' },
    { ...node, y: 20.5 },
    { ...node, type: 'coal' },
    { ...node, id: 'wrong' },
    { ...node, x: 1199, id: 'r_1199_20' },
    { ...node, size: 2 },
  ]) {
    assert.throws(() => extractKingshotResources(resources([invalid])), /invalid resource/);
  }
  assert.throws(() => extractKingshotResources(resources([node, node])), /duplicate/);
  assert.throws(
    () => extractKingshotResources(resources([node], { game: 'whiteout' })),
    /Kingshot/,
  );
  assert.throws(() => extractKingshotResources(resources([node], { total: 2 })), /complete/);
  assert.throws(() => extractKingshotResources(resources([node], { legacy: true })), /complete/);
});

test('source overlaps are retained and disclosed, not silently deleted or moved', () => {
  const terrain = extractKingshotTerrain(fixture().source);
  const nodes = extractKingshotResources(
    resources([
      { id: 'r_5_5', type: 'bread', x: 5, y: 5 },
      { id: 'r_10_20', type: 'quarry', x: 10, y: 20 },
      { id: 'r_11_20', type: 'ironmine', x: 11, y: 20 },
    ]),
  );
  const before = JSON.stringify(nodes);
  assert.deepEqual(spatialSourceDiagnostics(terrain, nodes), [
    { code: 'resource_terrain_overlap', resource_keys: ['r_5_5'], terrain_keys: ['lake_0001'] },
    { code: 'resource_footprint_overlap', resource_keys: ['r_10_20', 'r_11_20'], terrain_keys: [] },
  ]);
  assert.equal(JSON.stringify(nodes), before);
});
