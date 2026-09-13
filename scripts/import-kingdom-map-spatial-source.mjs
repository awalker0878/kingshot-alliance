import { Buffer } from 'node:buffer';
import process from 'node:process';
import assert from 'node:assert/strict';
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { format } from 'prettier';
import {
  extractKingshotResources,
  extractKingshotTerrain,
  sha256,
  spatialSourceDiagnostics,
} from './lib/kingdom-map-spatial-source.mjs';

// Source pins are reviewed evidence identities. Changed upstream bytes require a
// new reviewed import/release, never silent repair of an immutable release.
const CORE_SHA = 'ffc0270f3ff00dc11b2b04ccc171e996b4086c2183f12a96b35b2ad23dc5e566';
const RESOURCES_SHA = 'f74dec9784d0afca00f4cc7a4d32ac397a07f38b7ec531843b8acf6629cbe04c';
const OBSERVED_AT = '2026-09-13T02:58:26Z';
const [corePath, resourcesPath, outputDirectory] = process.argv.slice(2);
assert(
  corePath && resourcesPath && outputDirectory && process.argv.length === 5,
  'Usage: node scripts/import-kingdom-map-spatial-source.mjs <reviewed-core.js> <reviewed-resources.json> <empty-output-directory>',
);
const core = await readFile(corePath);
const resources = await readFile(resourcesPath);
assert.equal(sha256(core), CORE_SHA, 'Kingdom map core source identity changed');
assert.equal(sha256(resources), RESOURCES_SHA, 'Kingdom map resource source identity changed');
const terrain = extractKingshotTerrain(core.toString('utf8'));
const nodes = extractKingshotResources(resources.toString('utf8'));
assert.deepEqual(terrain.counts, { lake: 501, mountain: 1948 });
assert.deepEqual(terrain.cellCounts, { lake: 23246, mountain: 67215 });
assert.equal(nodes.length, 6499);
const diagnostics = spatialSourceDiagnostics(terrain, nodes);
const common = {
  schema_version: 1,
  game: 'kingshot',
  observed_at: OBSERVED_AT,
  confidence: 'community_observed',
  bounds: { x: 0, y: 0, width: 1200, height: 1200 },
  coordinate_system: 'kingshot_xy_south_west',
  completeness: 'complete_captured_source',
};
const terrainArtifact = {
  id: 'kingshot-terrain-2026-09-13',
  ...common,
  provenance: ['ksmapper_terrain_2026_09_13'],
  encoding: 'horizontal_unit_height_spans',
  source_sha256: CORE_SHA,
  bitmap_sha256: terrain.bitmapSha256,
  feature_counts: terrain.counts,
  cell_counts: terrain.cellCounts,
  features: terrain.features,
};
const resourceArtifact = {
  id: 'kingshot-resources-2026-09-13',
  ...common,
  provenance: ['ksmapper_resources_2026_09_13', 'ksmapper_terrain_2026_09_13'],
  source_sha256: RESOURCES_SHA,
  footprint_source_sha256: CORE_SHA,
  ownership_semantics: 'unqualified_resource_node',
  nodes,
  diagnostics,
};
const directory = resolve(outputDirectory);
await mkdir(directory, { recursive: true });
const receipt = [];
for (const artifact of [terrainArtifact, resourceArtifact]) {
  const filename = artifact.id + '.json';
  const raw = await format(JSON.stringify(artifact), {
    parser: 'json',
    printWidth: 100,
    tabWidth: 2,
  });
  // Exclusive creation prevents an import from rewriting historical evidence.
  await writeFile(resolve(directory, filename), raw, { flag: 'wx' });
  receipt.push({ file: filename, sha256: sha256(raw), bytes: Buffer.byteLength(raw) });
}
console.log(JSON.stringify({ observed_at: OBSERVED_AT, outputs: receipt, diagnostics }, null, 2));
