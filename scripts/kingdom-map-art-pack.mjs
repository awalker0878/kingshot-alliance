#!/usr/bin/env node
/**
 * Kingdom Map artwork master-pack ingestion.
 *
 * Turns a supplied Kingshot artwork master pack into the versioned registry:
 *
 *   node scripts/kingdom-map-art-pack.mjs --pack <dir> [--dry-run]
 *   node scripts/kingdom-map-art-pack.mjs --approve <reviewer> [--date YYYY-MM-DD]
 *
 * The pack directory declares its own contents in pack.json:
 *
 *   {
 *     "pack_schema_version": 1,
 *     "pack_id": "kingshot-kingdom-map-artwork-2026-09",
 *     "supplied_at": "2026-09-14",
 *     "supplied_by": "<rights holder or supplier>",
 *     "entries": {
 *       "headquarters.badland": { "icon": "hq-badland-icon.png", "sprite": "hq-badland.png" }
 *     }
 *   }
 *
 * Every declared file is trimmed, bounded, content-addressed and written under
 * resources/data/kingdom-map-art/assets. The registry is only rewritten after the whole pack
 * prepares and validates, so a rejected pack never leaves a partial registry behind. Imagery is
 * never fabricated: an entry without a declared source stays awaiting_source.
 *
 * Ingestion records bytes and geometry only. It never asserts rights, and it never marks an entry
 * reviewed on its own; `--approve <reviewer>` is a separate, named human action.
 */
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { dirname, isAbsolute, join, relative, resolve, win32 } from 'node:path';
import process from 'node:process';
import { fileURLToPath, pathToFileURL } from 'node:url';

import {
  ARTWORK_REPRESENTATION_KINDS,
  validateArtworkManifest,
} from '../resources/js/features/territory-planner/engine/artwork.ts';
import { DEFAULT_LIMITS, prepareRepresentation } from './kingdom-map-art-import.mjs';

export const PACK_SCHEMA_VERSION = 1;

const repositoryRoot = fileURLToPath(new URL('..', import.meta.url));
export const DEFAULT_MANIFEST_PATH = join(
  repositoryRoot,
  'resources/data/kingdom-map-art/manifest.v1.json',
);
/**
 * Delivery root of the art pack. Registry representation paths are already pack-relative
 * ("assets/sha256-.../key.png"), so this must not repeat the `assets` segment.
 */
export const DEFAULT_DELIVERY_ROOT = join(repositoryRoot, 'resources/data/kingdom-map-art');

export class ArtworkPackError extends Error {
  constructor(message) {
    super(message);
    this.name = 'ArtworkPackError';
  }
}

function isRecord(value) {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** Reject absolute paths using both POSIX/native and Windows semantics on every CI host. */
function isAbsolutePackPath(value) {
  return isAbsolute(value) || win32.isAbsolute(value);
}

/** Resolves a pack-relative source filename, rejecting absolute and directory-escaping paths. */
export function resolvePackFile(packDir, filename) {
  if (typeof filename !== 'string' || filename.trim().length === 0)
    throw new ArtworkPackError('pack source filename must be a non-empty string');
  if (isAbsolutePackPath(filename))
    throw new ArtworkPackError(`pack source ${filename} must be relative to the pack directory`);
  const root = resolve(packDir);
  const target = resolve(root, filename);
  const rel = relative(root, target);
  if (rel.length === 0 || rel.startsWith('..') || isAbsolutePackPath(rel))
    throw new ArtworkPackError(`pack source ${filename} escapes the pack directory`);
  return target;
}

/** Validates the pack declaration against the registry keys it is allowed to target. */
export function parsePackDeclaration(value, registryKeys) {
  if (!isRecord(value)) throw new ArtworkPackError('pack.json must be an object');
  if (value.pack_schema_version !== PACK_SCHEMA_VERSION)
    throw new ArtworkPackError(`pack.json pack_schema_version must be ${PACK_SCHEMA_VERSION}`);
  for (const field of ['pack_id', 'supplied_at', 'supplied_by'])
    if (typeof value[field] !== 'string' || value[field].trim().length === 0)
      throw new ArtworkPackError(`pack.json ${field} must be a non-empty string`);
  if (!isRecord(value.entries)) throw new ArtworkPackError('pack.json entries must be an object');

  const declared = Object.keys(value.entries);
  if (declared.length === 0)
    throw new ArtworkPackError('pack.json entries must declare at least one registry key');
  const unknown = declared.filter((key) => !registryKeys.has(key));
  if (unknown.length)
    throw new ArtworkPackError(`pack.json declares unknown registry key(s): ${unknown.join(', ')}`);

  const entries = new Map();
  for (const key of declared) {
    const kinds = value.entries[key];
    if (!isRecord(kinds)) throw new ArtworkPackError(`pack.json entries.${key} must be an object`);
    const declaredKinds = Object.keys(kinds);
    if (declaredKinds.length === 0)
      throw new ArtworkPackError(
        `pack.json entries.${key} must declare at least one representation kind`,
      );
    const unknownKinds = declaredKinds.filter(
      (kind) => !ARTWORK_REPRESENTATION_KINDS.includes(kind),
    );
    if (unknownKinds.length)
      throw new ArtworkPackError(
        `pack.json entries.${key} declares unknown representation kind(s): ${unknownKinds.join(', ')}`,
      );
    const ordered = {};
    for (const kind of ARTWORK_REPRESENTATION_KINDS) if (kind in kinds) ordered[kind] = kinds[kind];
    entries.set(key, ordered);
  }
  return {
    pack_id: value.pack_id,
    supplied_at: value.supplied_at,
    supplied_by: value.supplied_by,
    ...(typeof value.source === 'string' ? { source: value.source } : {}),
    entries,
  };
}

/** Reads and validates pack.json from a pack directory. */
export function readPackDeclaration(packDir, registryKeys) {
  let raw;
  try {
    raw = readFileSync(join(packDir, 'pack.json'), 'utf8');
  } catch {
    throw new ArtworkPackError(`pack.json could not be read from ${packDir}`);
  }
  try {
    return parsePackDeclaration(JSON.parse(raw), registryKeys);
  } catch (error) {
    if (error instanceof ArtworkPackError) throw error;
    throw new ArtworkPackError(`pack.json is not valid JSON: ${String(error)}`);
  }
}

/**
 * Prepares every declared representation in memory. Nothing is written here, so a dry run and a
 * real run agree exactly and a rejected pack cannot leave a partial registry behind.
 */
export function planPack({ packDir, manifest, declaration, limits = DEFAULT_LIMITS }) {
  const byKey = new Map(manifest.entries.map((entry) => [entry.key, entry]));
  const files = [];
  const prepared = new Map();

  for (const [key, kinds] of declaration.entries) {
    const entry = byKey.get(key);
    const result = {};
    for (const kind of ARTWORK_REPRESENTATION_KINDS) {
      const filename = kinds[kind];
      if (filename === undefined) continue;
      const source = resolvePackFile(packDir, filename);
      let bytes;
      try {
        bytes = readFileSync(source);
      } catch {
        throw new ArtworkPackError(`pack source ${filename} for ${key}.${kind} could not be read`);
      }
      let record;
      try {
        record = prepareRepresentation({
          key,
          kind,
          filename,
          bytes,
          limits,
          anchor: entry.anchor,
          orientation: entry.orientation,
        });
      } catch (error) {
        throw new ArtworkPackError(
          `${key}.${kind} rejected: ${error instanceof Error ? error.message : String(error)}`,
        );
      }
      result[kind] = record;
      files.push({ path: record.representation.path, bytes: record.delivery_bytes, key, kind });
    }
    prepared.set(key, result);
  }

  const updated = structuredClone(manifest);
  const changes = [];
  for (const [key, kinds] of prepared) {
    const entry = updated.entries.find((candidate) => candidate.key === key);
    const delivered = ARTWORK_REPRESENTATION_KINDS.filter((kind) => kind in kinds);
    if (delivered.length === 0) continue;

    entry.source = {
      pack_id: declaration.pack_id,
      supplied_at: declaration.supplied_at,
      supplied_by: declaration.supplied_by,
      ...(declaration.source ? { pack_source: declaration.source } : {}),
      representations: delivered.map((kind) => ({
        kind,
        source_filename: kinds[kind].source_filename,
        mime_type: kinds[kind].source.mime_type,
        byte_size: kinds[kind].source.byte_size,
        width: kinds[kind].source.width,
        height: kinds[kind].source.height,
        sha256: kinds[kind].source.sha256,
      })),
    };
    for (const kind of delivered) entry.representations[kind] = kinds[kind].representation;
    entry.provenance = delivered.map(
      (kind) =>
        `${declaration.pack_id} ${key} ${kind} ${kinds[kind].source_filename} sha256:${kinds[kind].source.sha256}`,
    );

    const required = entry.required_representations;
    const complete =
      required.length > 0 && required.every((kind) => entry.representations[kind] !== null);
    // A complete entry is delivered but not yet reviewed; an incomplete one is still awaiting
    // source. A human approval is the only thing that may set `reviewed`.
    if (entry.review_state !== 'reviewed')
      entry.review_state = complete ? 'delivered_unreviewed' : 'awaiting_source';

    changes.push({ key, kinds: delivered, reviewState: entry.review_state });
  }

  return {
    manifest: updated,
    files,
    changes,
    diagnostics: validateArtworkManifest(updated),
  };
}

/**
 * Ingests a pack into the registry. `apply: false` reports exactly what a real run would do.
 * The registry file is only rewritten after the whole pack prepares and validates.
 */
export function ingestPack({
  packDir,
  manifestPath = DEFAULT_MANIFEST_PATH,
  deliveryRoot = DEFAULT_DELIVERY_ROOT,
  apply = false,
  limits = DEFAULT_LIMITS,
}) {
  let manifest;
  try {
    manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
  } catch (error) {
    throw new ArtworkPackError(`registry ${manifestPath} could not be read: ${String(error)}`);
  }
  const registryKeys = new Set(manifest.entries.map((entry) => entry.key));
  const declaration = readPackDeclaration(packDir, registryKeys);
  const plan = planPack({ packDir, manifest, declaration, limits });
  if (plan.diagnostics.length)
    throw new ArtworkPackError(
      `ingested registry would be invalid: ${plan.diagnostics.join('; ')}`,
    );

  if (apply) {
    for (const file of plan.files) {
      const target = join(deliveryRoot, file.path);
      mkdirSync(dirname(target), { recursive: true });
      writeFileSync(target, file.bytes);
    }
    writeFileSync(manifestPath, `${JSON.stringify(plan.manifest, null, 2)}\n`);
  }
  return { ...plan, applied: apply, declaration };
}

/**
 * Records a named human review of the delivered artwork. Only entries whose required
 * representations are all present may be approved, and the reviewer is part of the provenance.
 */
export function approvePack({
  manifestPath = DEFAULT_MANIFEST_PATH,
  reviewedBy,
  approvedAt,
  apply = false,
}) {
  if (typeof reviewedBy !== 'string' || reviewedBy.trim().length === 0)
    throw new ArtworkPackError('--approve requires a non-empty reviewer name');
  if (typeof approvedAt !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(approvedAt))
    throw new ArtworkPackError('--date must be an ISO YYYY-MM-DD date');

  const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
  const updated = structuredClone(manifest);
  const approved = [];
  const incomplete = [];
  for (const entry of updated.entries) {
    if (entry.required_representations.length === 0) continue;
    const complete = entry.required_representations.every(
      (kind) => entry.representations[kind] !== null,
    );
    if (!complete) {
      incomplete.push(entry.key);
      continue;
    }
    if (entry.review_state !== 'reviewed') approved.push(entry.key);
    entry.review_state = 'reviewed';
    const note = `reviewed by ${reviewedBy} at ${approvedAt}`;
    if (!entry.provenance.includes(note)) entry.provenance.push(note);
  }
  const diagnostics = validateArtworkManifest(updated);
  if (diagnostics.length)
    throw new ArtworkPackError(`approved registry would be invalid: ${diagnostics.join('; ')}`);
  if (apply) writeFileSync(manifestPath, `${JSON.stringify(updated, null, 2)}\n`);
  return { manifest: updated, approved, incomplete, applied: apply };
}

function main(argv) {
  const option = (name) => {
    const index = argv.indexOf(`--${name}`);
    return index === -1 ? undefined : argv[index + 1];
  };
  const manifestPath = option('manifest') ?? DEFAULT_MANIFEST_PATH;
  const apply = !argv.includes('--dry-run');
  try {
    const reviewer = option('approve');
    if (reviewer !== undefined) {
      const result = approvePack({
        manifestPath,
        reviewedBy: reviewer,
        approvedAt: option('date') ?? new Date().toISOString().slice(0, 10),
        apply,
      });
      console.log(
        `${apply ? 'Approved' : 'Would approve'} ${result.approved.length} reviewed entr${result.approved.length === 1 ? 'y' : 'ies'}${apply ? '' : ' (dry run)'}.`,
      );
      for (const key of result.approved) console.log(`  reviewed  ${key}`);
      for (const key of result.incomplete)
        console.log(`  awaiting  ${key} (source representations are still absent)`);
      return 0;
    }

    const packDir = option('pack');
    if (!packDir) {
      console.error(
        'Usage: node scripts/kingdom-map-art-pack.mjs --pack <dir> [--dry-run] [--manifest <file>] [--delivery-root <dir>]\n' +
          '       node scripts/kingdom-map-art-pack.mjs --approve <reviewer> [--date YYYY-MM-DD] [--manifest <file>]',
      );
      return 1;
    }
    const result = ingestPack({
      packDir,
      manifestPath,
      deliveryRoot: option('delivery-root') ?? DEFAULT_DELIVERY_ROOT,
      apply,
    });
    console.log(
      `${apply ? 'Ingested' : 'Would ingest'} pack ${result.declaration.pack_id} (${result.declaration.supplied_at}, supplied by ${result.declaration.supplied_by}): ${result.files.length} representation(s) across ${result.changes.length} entr${result.changes.length === 1 ? 'y' : 'ies'}${apply ? '' : ' (dry run)'}.`,
    );
    for (const change of result.changes)
      console.log(`  ${change.reviewState.padEnd(20)} ${change.key} [${change.kinds.join(', ')}]`);
    console.log(
      apply
        ? 'Re-run npm run check:kingdom-map-art:strict, then approve the delivered art with --approve <reviewer>.'
        : 'Nothing was written. Re-run without --dry-run to apply.',
    );
    return 0;
  } catch (error) {
    console.error(
      `Artwork pack ingestion rejected: ${error instanceof Error ? error.message : String(error)}`,
    );
    return 1;
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href)
  process.exit(main(process.argv.slice(2)));
