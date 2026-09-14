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
import { readFileSync, existsSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import {
  ARTWORK_REPRESENTATION_KINDS,
  validateArtworkManifest,
} from '../resources/js/features/territory-planner/engine/artwork.ts';

const manifestPath = fileURLToPath(
  new URL('../resources/data/kingdom-map-art/manifest.v1.json', import.meta.url),
);
const assetsDir = fileURLToPath(new URL('../resources/data/kingdom-map-art', import.meta.url));

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

const keys = new Set(
  Array.isArray(manifest.entries) ? manifest.entries.map((entry) => entry.key) : [],
);
for (const key of REQUIRED_ENTRY_KEYS)
  if (!keys.has(key)) failures.push(`missing required entry: ${key}`);

const sourceRequired = [];
if (Array.isArray(manifest.entries)) {
  for (const entry of manifest.entries) {
    const required = Array.isArray(entry.required_representations)
      ? entry.required_representations
      : [];
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

  // A declared representation is only real when its delivery bytes exist and still hash to the
  // registry value. Without this the registry could claim artwork that was never written.
  for (const entry of Array.isArray(manifest.entries) ? manifest.entries : []) {
    for (const kind of ARTWORK_REPRESENTATION_KINDS) {
      const declared = entry.representations?.[kind];
      if (!declared) continue;
      const file = resolve(join(assetsDir, declared.path));
      if (file !== assetsDir && !file.startsWith(assetsDir))
        failures.push(`${entry.key}.${kind}: delivery path escapes the art pack`);
      else if (!existsSync(file))
        failures.push(`${entry.key}.${kind}: delivery bytes are absent (${declared.path})`);
      else {
        const bytes = readFileSync(file);
        const digest = createHash('sha256').update(bytes).digest('hex');
        if (digest !== declared.sha256)
          failures.push(`${entry.key}.${kind}: delivery bytes do not match the recorded sha256`);
        if (bytes.length !== declared.byte_size)
          failures.push(`${entry.key}.${kind}: delivery byte_size does not match the file`);
      }
    }
  }
}

if (failures.length) {
  console.error(`Kingdom Map artwork registry check FAILED with ${failures.length} issue(s):`);
  for (const failure of failures) console.error(`  - ${failure}`);
  if (!structureOnly && sourceRequired.length) {
    const absent = sourceRequired.filter((entry) => entry.absent.length > 0);
    if (absent.length)
      console.error(
        'BLOCKED_INPUT: the rights-cleared Kingshot artwork master pack has not been supplied. ' +
          `${absent.length} entr${absent.length === 1 ? 'y is' : 'ies are'} still awaiting source representations; ` +
          'no imagery is fabricated. Ingest the pack with: npm run art:pack -- <pack-dir>',
      );
    else
      console.error(
        `REVIEW_PENDING: all ${sourceRequired.length} source-required entr${sourceRequired.length === 1 ? 'y has' : 'ies have'} ` +
          'delivered representations, but none is reviewed yet. Inspect the rendered output, then record the ' +
          'named review with: npm run art:approve -- <reviewer>',
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
