import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

import { encodePng, sha256Hex } from '../../../../scripts/kingdom-map-art-import.mjs';
import {
  ArtworkPackError,
  approvePack,
  ingestPack,
  parsePackDeclaration,
  planPack,
  resolvePackFile,
} from '../../../../scripts/kingdom-map-art-pack.mjs';
import { validateArtworkManifest } from '../../../../resources/js/features/territory-planner/engine/artwork.ts';
import type { ArtworkManifest } from '../../../../resources/js/features/territory-planner/engine/artwork.ts';

const readManifest = () =>
  JSON.parse(
    readFileSync(
      new URL('../../../../resources/data/kingdom-map-art/manifest.v1.json', import.meta.url),
      'utf8',
    ),
  ) as ArtworkManifest;

/** Builds a genuine PNG source with an opaque inset block so trimming can be exercised. */
function pngOf(width: number, height: number, inset = 2): Buffer {
  const rgba = Buffer.alloc(width * height * 4);
  for (let y = inset; y < height - inset; y += 1)
    for (let x = inset; x < width - inset; x += 1) {
      const offset = (y * width + x) * 4;
      rgba[offset] = 40;
      rgba[offset + 1] = 120;
      rgba[offset + 2] = 200;
      rgba[offset + 3] = 255;
    }
  return encodePng({ width, height, rgba });
}

const PACK_ID = 'kingshot-kingdom-map-artwork-test';
const SUPPLIED_AT = '2026-09-14';

/** Lays out a throwaway pack plus a registry copy so ingestion never touches the shipped files. */
function scaffold({ entries, files }: { entries: Record<string, unknown>; files?: string[] }) {
  const root = mkdtempSync(join(tmpdir(), 'kingdom-map-art-pack-'));
  const packDir = join(root, 'pack');
  mkdirSync(packDir, { recursive: true });
  writeFileSync(
    join(packDir, 'pack.json'),
    JSON.stringify({
      pack_schema_version: 1,
      pack_id: PACK_ID,
      supplied_at: SUPPLIED_AT,
      supplied_by: 'rights holder',
      entries,
    }),
  );
  for (const name of files ?? []) writeFileSync(join(packDir, name), pngOf(40, 40));
  const manifestPath = join(root, 'manifest.v1.json');
  writeFileSync(manifestPath, JSON.stringify(readManifest(), null, 2) + '\n');
  return { root, packDir, manifestPath, deliveryRoot: join(root, 'art-pack') };
}

const fullPack = {
  'headquarters.badland': {
    icon: 'hq-badland-icon.png',
    sprite: 'hq-badland-sprite.png',
    detail: 'hq-badland-detail.png',
  },
  'banner.default': { sprite: 'banner-sprite.png' },
};

const fullPackFiles = [
  'hq-badland-icon.png',
  'hq-badland-sprite.png',
  'hq-badland-detail.png',
  'banner-sprite.png',
];

test('a supplied pack is prepared, content-addressed and written under the art pack', () => {
  const { root, packDir, manifestPath, deliveryRoot } = scaffold({
    entries: fullPack,
    files: fullPackFiles,
  });
  try {
    // A dry run must report the work without writing anything.
    const dry = ingestPack({ packDir, manifestPath, deliveryRoot, apply: false });
    assert.equal(dry.applied, false);
    assert.equal(dry.files.length, 4);
    assert.equal(existsSync(deliveryRoot), false);
    assert.equal(
      readFileSync(manifestPath, 'utf8'),
      JSON.stringify(readManifest(), null, 2) + '\n',
    );

    const result = ingestPack({ packDir, manifestPath, deliveryRoot, apply: true });
    assert.equal(result.applied, true);
    assert.deepEqual(result.diagnostics, []);

    for (const file of result.files) {
      assert.ok(file.path.startsWith('assets/sha256-'), `${file.path} is not content addressed`);
      const written = join(deliveryRoot, file.path);
      assert.ok(existsSync(written), `${file.path} was not written`);
      assert.equal(
        sha256Hex(readFileSync(written)),
        file.bytes.length ? sha256Hex(file.bytes) : '',
      );
    }

    const updated = JSON.parse(readFileSync(manifestPath, 'utf8')) as ArtworkManifest;
    assert.deepEqual(validateArtworkManifest(updated), []);

    const hq = updated.entries.find((entry) => entry.key === 'headquarters.badland');
    assert.ok(hq);
    assert.equal(hq.review_state, 'delivered_unreviewed');
    for (const kind of hq.required_representations) {
      const declared = hq.representations[kind];
      assert.ok(declared, `${kind} was not recorded`);
      assert.ok(declared.path.includes(declared.sha256));
      assert.equal(declared.byte_size, readFileSync(join(deliveryRoot, declared.path)).length);
      // The 2-pixel transparent border is trimmed, and the source is never upscaled.
      assert.equal(declared.width, 36);
      assert.equal(declared.height, 36);
      assert.deepEqual(declared.anchor, hq.anchor);
      assert.equal(declared.orientation, hq.orientation);
    }
    assert.equal(hq.source?.pack_id, PACK_ID);
    assert.equal(hq.source?.supplied_at, SUPPLIED_AT);
    assert.equal(hq.source?.representations.length, 3);
    assert.equal(hq.provenance.length, 3);
    for (const note of hq.provenance) assert.ok(note.startsWith(`${PACK_ID} headquarters.badland`));

    // The pack only ever records bytes; it never asserts rights or a review on its own.
    assert.equal(hq.rights_basis, 'kingshot_supplied_artwork');
    assert.equal('review_state' in (hq.source ?? {}), false);

    // A partially supplied entry stays awaiting source rather than overclaiming delivery.
    const banner = updated.entries.find((entry) => entry.key === 'banner.default');
    assert.ok(banner);
    assert.equal(banner.review_state, 'awaiting_source');
    assert.ok(banner.representations.sprite);
    assert.equal(banner.representations.icon, null);
    assert.equal(banner.representations.detail, null);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('re-ingesting the same pack is idempotent for both the bytes and the registry', () => {
  const { root, packDir, manifestPath, deliveryRoot } = scaffold({
    entries: fullPack,
    files: fullPackFiles,
  });
  try {
    const first = ingestPack({ packDir, manifestPath, deliveryRoot, apply: true });
    const firstManifest = readFileSync(manifestPath, 'utf8');
    const firstFiles = first.files.map((file) => [
      file.path,
      sha256Hex(readFileSync(join(deliveryRoot, file.path))),
    ]);

    const second = ingestPack({ packDir, manifestPath, deliveryRoot, apply: true });
    assert.equal(readFileSync(manifestPath, 'utf8'), firstManifest);
    assert.deepEqual(
      second.files.map((file) => [
        file.path,
        sha256Hex(readFileSync(join(deliveryRoot, file.path))),
      ]),
      firstFiles,
    );
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('a rejected pack leaves the registry and the art pack untouched', () => {
  const { root, packDir, manifestPath, deliveryRoot } = scaffold({
    entries: { 'headquarters.badland': { sprite: 'missing.png' } },
  });
  try {
    const before = readFileSync(manifestPath, 'utf8');
    assert.throws(
      () => ingestPack({ packDir, manifestPath, deliveryRoot, apply: true }),
      /could not be read/,
    );
    assert.equal(readFileSync(manifestPath, 'utf8'), before);
    assert.equal(existsSync(deliveryRoot), false);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('pack declarations and source paths are validated before any bytes are read', () => {
  const registryKeys = new Set(readManifest().entries.map((entry) => entry.key));
  const parse = (value: unknown) => parsePackDeclaration(value, registryKeys);
  const base = {
    pack_schema_version: 1,
    pack_id: PACK_ID,
    supplied_at: SUPPLIED_AT,
    supplied_by: 'rights holder',
    entries: { 'banner.default': { sprite: 'banner.png' } },
  };

  assert.throws(() => parse(null), /must be an object/);
  assert.throws(() => parse({ ...base, pack_schema_version: 2 }), /pack_schema_version/);
  assert.throws(() => parse({ ...base, pack_id: '' }), /pack_id/);
  assert.throws(() => parse({ ...base, supplied_at: '  ' }), /supplied_at/);
  assert.throws(() => parse({ ...base, supplied_by: undefined }), /supplied_by/);
  assert.throws(() => parse({ ...base, entries: {} }), /at least one registry key/);
  assert.throws(
    () => parse({ ...base, entries: { 'banner.made_up': { sprite: 'x.png' } } }),
    /unknown registry key/,
  );
  assert.throws(
    () => parse({ ...base, entries: { 'banner.default': {} } }),
    /at least one representation kind/,
  );
  assert.throws(
    () => parse({ ...base, entries: { 'banner.default': { hero: 'x.png' } } }),
    /unknown representation kind/,
  );

  // Representation kinds are normalised into registry order so output stays deterministic.
  const ordered = parse({
    ...base,
    entries: { 'headquarters.badland': { detail: 'd.png', icon: 'i.png' } },
  });
  assert.deepEqual(Object.keys(ordered.entries.get('headquarters.badland')), ['icon', 'detail']);
  assert.equal(ordered.pack_id, PACK_ID);
  assert.equal(ordered.source, undefined);

  const root = mkdtempSync(join(tmpdir(), 'kingdom-map-art-path-'));
  try {
    assert.throws(() => resolvePackFile(root, ''), /non-empty string/);
    assert.throws(() => resolvePackFile(root, 7), /non-empty string/);
    assert.throws(() => resolvePackFile(root, 'C:/art/banner.png'), /must be relative/);
    assert.throws(() => resolvePackFile(root, '../banner.png'), /escapes the pack directory/);
    assert.throws(() => resolvePackFile(root, 'nested/../../banner.png'), /escapes/);
    assert.equal(resolvePackFile(root, 'nested/banner.png'), join(root, 'nested/banner.png'));
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('unsupported and unauthorised sources are rejected by the pack ingestion path', () => {
  const root = mkdtempSync(join(tmpdir(), 'kingdom-map-art-reject-'));
  const packDir = join(root, 'pack');
  mkdirSync(packDir, { recursive: true });
  writeFileSync(join(packDir, 'vector.svg'), '<svg viewBox="0 0 10 10"></svg>');
  writeFileSync(join(packDir, 'blank.png'), pngOf(20, 20, 10));
  writeFileSync(join(packDir, 'noise.png'), Buffer.from('not an image at all'));
  try {
    const plan = (filename: string) =>
      planPack({
        packDir,
        manifest: readManifest(),
        declaration: {
          pack_id: PACK_ID,
          supplied_at: SUPPLIED_AT,
          supplied_by: 'rights holder',
          entries: new Map([['banner.default', { sprite: filename }]]),
        },
      });

    assert.throws(() => plan('vector.svg'), /must be rasterized/);
    assert.throws(() => plan('blank.png'), /fully transparent/);
    assert.throws(() => plan('noise.png'), /unsupported or ambiguous/);

    // Nothing is prepared when any declared source is rejected.
    assert.throws(() => plan('missing.png'), ArtworkPackError);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('approval requires a named reviewer and only covers entries with every representation', () => {
  const { root, packDir, manifestPath, deliveryRoot } = scaffold({
    entries: fullPack,
    files: fullPackFiles,
  });
  try {
    ingestPack({ packDir, manifestPath, deliveryRoot, apply: true });

    assert.throws(
      () => approvePack({ manifestPath, reviewedBy: '  ', approvedAt: SUPPLIED_AT }),
      /reviewer name/,
    );
    assert.throws(
      () => approvePack({ manifestPath, reviewedBy: 'awalker0878', approvedAt: 'yesterday' }),
      /YYYY-MM-DD/,
    );

    const dry = approvePack({
      manifestPath,
      reviewedBy: 'awalker0878',
      approvedAt: SUPPLIED_AT,
      apply: false,
    });
    assert.deepEqual(dry.approved, ['headquarters.badland']);
    // Approval reports the whole remaining backlog, which is the operator's checklist.
    const pending = (JSON.parse(readFileSync(manifestPath, 'utf8')) as ArtworkManifest).entries
      .filter(
        (entry) =>
          entry.required_representations.length > 0 &&
          !entry.required_representations.every((kind) => entry.representations[kind] !== null),
      )
      .map((entry) => entry.key);
    assert.deepEqual(dry.incomplete, pending);
    assert.ok(dry.incomplete.includes('banner.default'));
    assert.equal(dry.incomplete.includes('headquarters.badland'), false);
    assert.equal(
      (JSON.parse(readFileSync(manifestPath, 'utf8')) as ArtworkManifest).entries.find(
        (entry) => entry.key === 'headquarters.badland',
      )?.review_state,
      'delivered_unreviewed',
    );

    const applied = approvePack({
      manifestPath,
      reviewedBy: 'awalker0878',
      approvedAt: SUPPLIED_AT,
      apply: true,
    });
    assert.deepEqual(applied.approved, ['headquarters.badland']);
    const updated = JSON.parse(readFileSync(manifestPath, 'utf8')) as ArtworkManifest;
    assert.deepEqual(validateArtworkManifest(updated), []);
    const hq = updated.entries.find((entry) => entry.key === 'headquarters.badland');
    assert.equal(hq?.review_state, 'reviewed');
    assert.ok(hq?.provenance.includes(`reviewed by awalker0878 at ${SUPPLIED_AT}`));
    // A second approval is a no-op, so the recorded provenance stays stable.
    const again = approvePack({
      manifestPath,
      reviewedBy: 'awalker0878',
      approvedAt: SUPPLIED_AT,
      apply: true,
    });
    assert.deepEqual(again.approved, []);
    assert.equal(hq?.provenance.filter((note) => note.startsWith('reviewed by')).length, 1);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('the shipped registry still declares no imagery until a pack is actually supplied', () => {
  const manifest = readManifest();
  assert.deepEqual(validateArtworkManifest(manifest), []);
  const supplied = manifest.entries.filter(
    (entry) => entry.source !== null || entry.review_state === 'reviewed',
  );
  assert.deepEqual(
    supplied.map((entry) => entry.key),
    [],
    'the shipped registry must not claim supplied artwork',
  );
  // A pack may never target a key the registry does not define.
  assert.throws(
    () =>
      parsePackDeclaration(
        {
          pack_schema_version: 1,
          pack_id: 'x',
          supplied_at: 'y',
          supplied_by: 'z',
          entries: { 'nope.key': { sprite: 'a.png' } },
        },
        new Set(['banner.default']),
      ),
    /unknown registry key/,
  );
});
