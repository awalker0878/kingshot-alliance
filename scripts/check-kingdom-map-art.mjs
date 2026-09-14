#!/usr/bin/env node
/**
 * Kingdom Map artwork registry gate.
 *
 * Two modes:
 *   node scripts/check-kingdom-map-art.mjs                  # strict: also fails while source art is absent
 *   node scripts/check-kingdom-map-art.mjs --structure-only # structure only, safe for the aggregate `npm run check`
 *
 * The strict mode is the documented acceptance gate from docs/product/kingdom-map-workspace-assets.md:
 * every source-required entry must carry real, reviewed representations. It therefore fails by design
 * while the rights-cleared artwork master pack has not been supplied. Missing art is never substituted.
 */
import { readFileSync } from 'node:fs';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import {
  ARTWORK_REPRESENTATION_KINDS,
  validateArtworkManifest,
} from '../resources/js/features/territory-planner/engine/artwork.ts';

const manifestPath = fileURLToPath(
  new URL('../resources/data/kingdom-map-art/manifest.v1.json', import.meta.url),
);

/**
 * Presentation contract: every key the shared scene can emit must exist in the registry.
 * Mirrors resources/js/features/territory-planner/engine/scene.ts.
 */
const REQUIRED_ENTRY_KEYS = [
  'headquarters.badland',
  'headquarters.plains',
  'banner.default',
  'governor_city.default',
  'bear_trap.default',
  'castle.kings_castle',
  'turret.default',
  'fortress.default',
  'sanctuary.default',
  'outpost.builders_guild',
  'outpost.armory',
  'outpost.scholars_tower',
  'outpost.arsenal',
  'outpost.forager_grove',
  'outpost.harvest_altar',
  'outpost.drill_camp',
  'outpost.frontier_lodge',
  'outpost.unknown',
  'resource.bread',
  'resource.woodmill',
  'resource.quarry',
  'resource.ironmine',
  'terrain.lake',
  'terrain.mountain',
  'region.default',
  'restriction.default',
  'annotation.default',
];

const structureOnly = process.argv.includes('--structure-only');
const failures = [];

let manifest;
try {
  manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
} catch (error) {
  console.error(`FAIL  ${error instanceof Error ? error.message : String(error)}`);
  process.exit(1);
}

const structural = validateArtworkManifest(manifest);
for (const diagnostic of structural) failures.push(`structure: ${diagnostic}`);

const keys = new Set(Array.isArray(manifest.entries) ? manifest.entries.map((entry) => entry.key) : []);
for (const key of REQUIRED_ENTRY_KEYS)
  if (!keys.has(key)) failures.push(`missing required entry: ${key}`);

const sourceRequired = [];
if (Array.isArray(manifest.entries)) {
  for (const entry of manifest.entries) {
    const required = Array.isArray(entry.required_representations) ? entry.required_representations : [];
    if (!required.length) continue;
    const absent = required.filter((kind) => !entry.representations?.[kind]);
    if (absent.length || entry.review_state !== 'reviewed')
      sourceRequired.push({ key: entry.key, reviewState: entry.review_state, absent });
    for (const kind of ARTWORK_REPRESENTATION_KINDS) {
      if (required.includes(kind) && !entry.representations?.[kind] && !absent.includes(kind))
        failures.push(`${entry.key}: ${kind} is required but absent`);
    }
  }
}

if (!structureOnly) {
  for (const entry of sourceRequired)
    failures.push(
      `incomplete source artwork: ${entry.key} (review_state=${entry.reviewState}, absent=${entry.absent.join('/') || 'review'})`,
    );
}

if (failures.length) {
  console.error(`Kingdom Map artwork registry check FAILED with ${failures.length} issue(s):`);
  for (const failure of failures) console.error(`  - ${failure}`);
  if (!structureOnly && sourceRequired.length) {
    console.error(
      'BLOCKED_INPUT: the rights-cleared Kingshot artwork master pack has not been supplied. ' +
        'Registry entries legitimately remain awaiting_source; no imagery is fabricated.',
    );
  }
  process.exit(1);
}

const raster = manifest.entries.filter((entry) => entry.renderer === 'raster').length;
const vector = manifest.entries.filter((entry) => entry.renderer === 'vector').length;
console.log(
  structureOnly
    ? `Kingdom Map artwork registry structure passed (${manifest.entries.length} entries: ${raster} raster, ${vector} vector).`
    : `Kingdom Map artwork registry passed (${manifest.entries.length} reviewed entries).`,
);