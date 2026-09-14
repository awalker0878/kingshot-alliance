import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import {
  ARTWORK_OUTPOST_FALLBACK_KEY,
  ArtworkManifestError,
  createArtworkRegistry,
  parseArtworkManifest,
  validateArtworkManifest,
} from '../../../../resources/js/features/territory-planner/engine/artwork.ts';
import { buildTerritoryScene } from '../../../../resources/js/features/territory-planner/engine/scene.ts';
import type { ArtworkManifest, ArtworkRepresentation } from '../../../../resources/js/features/territory-planner/engine/artwork.ts';
import type { MapData } from '../../../../resources/js/features/territory-planner/engine/types.ts';

const read = (path: string) =>
  JSON.parse(readFileSync(new URL(path, import.meta.url), 'utf8')) as unknown;

const manifest = parseArtworkManifest(read('../../../../resources/data/kingdom-map-art/manifest.v1.json'));
const map = read('../../../../resources/data/kingdom-maps/kingshot-evidence-backed-2026-09-06-v2.json') as MapData;

const SHA = 'a'.repeat(64);
const OTHER_SHA = 'b'.repeat(64);

function representation(kind: string, width: number, height: number, sha = SHA): ArtworkRepresentation {
  return {
    path: `assets/sha256-${sha}/${kind}.png`,
    sha256: sha,
    mime_type: 'image/png',
    byte_size: 2048,
    width,
    height,
    anchor: { x: 0.5, y: 1 },
    orientation: 'upright',
  };
}

/** Publishes verified bytes for one entry so registry behaviour can be exercised without a real art pack. */
function withReviewedEntry(key: string): ArtworkManifest {
  const clone = structuredClone(manifest);
  const entry = clone.entries.find((candidate) => candidate.key === key);
  assert.ok(entry, `${key} must exist in the shipped registry`);
  entry.review_state = 'reviewed';
  entry.required_representations = ['icon', 'sprite', 'detail'];
  entry.representations = {
    icon: representation('icon', 64, 64),
    sprite: representation('sprite', 128, 128),
    detail: representation('detail', 640, 640),
  };
  return clone;
}

function diagnosticsFor(mutate: (value: Record<string, unknown>) => void): string[] {
  const value = structuredClone(manifest) as unknown as Record<string, unknown>;
  mutate(value);
  return validateArtworkManifest(value);
}

test('every asset key the released scene can emit exists in the versioned artwork registry', () => {
  const scene = buildTerritoryScene({
    map,
    mapChecksum: map.checksum ?? 'c'.repeat(64),
    alliances: [],
    objects: [],
  });
  const keys = [...new Set(scene.entities.map((entity) => entity.assetKey).filter((key): key is string => Boolean(key)))];
  assert.ok(keys.length > 0);
  const registry = createArtworkRegistry(manifest);
  for (const key of keys) {
    assert.ok(registry.entry(key), `${key} is emitted by the scene but absent from the registry`);
    assert.ok(registry.status(key).resolvedKey, `${key} must resolve to a registry entry`);
  }
});

test('unknown outpost slugs degrade to the typed unknown marker and never invent artwork', () => {
  const registry = createArtworkRegistry(manifest);
  const status = registry.status('outpost.made_up_installation');
  assert.equal(status.resolvedKey, ARTWORK_OUTPOST_FALLBACK_KEY);
  assert.equal(status.available, false);
  assert.deepEqual(status.missing, ['icon', 'sprite', 'detail']);
  assert.equal(registry.entry('outpost.made_up_installation')?.key, ARTWORK_OUTPOST_FALLBACK_KEY);
  assert.equal(registry.entry('nonexistent.family'), null);
  assert.equal(registry.status('nonexistent.family').reviewState, null);
  assert.equal(registry.image('outpost.made_up_installation', 64), null);
  assert.equal(registry.image('nonexistent.family', 64), null);
});

test('the shipped registry reports honest coverage while the rights-cleared source pack is absent', async () => {
  const registry = createArtworkRegistry(manifest);
  const rasterKeys = manifest.entries
    .filter((entry) => entry.renderer === 'raster')
    .map((entry) => entry.key);
  assert.ok(rasterKeys.length > 0);
  for (const key of rasterKeys) {
    assert.equal(registry.entry(key)?.source, null, `${key} must not claim an unverified source`);
    assert.equal(registry.entry(key)?.review_state, 'awaiting_source');
    assert.equal(registry.resolve(key, 'sprite'), null);
    assert.equal(registry.image(key, 1024), null);
  }
  const coverage = registry.coverage([...rasterKeys, 'not.a.key']);
  assert.equal(coverage.covered, 0);
  assert.equal(coverage.awaitingSource.length, rasterKeys.length);
  assert.deepEqual(coverage.missing, [...rasterKeys, 'not.a.key']);
  assert.deepEqual(await registry.ensure(rasterKeys), []);
  assert.deepEqual(registry.loaded(), []);
  assert.deepEqual(registry.unavailable().sort(), [...rasterKeys].sort());
});

test('verified bytes are served only after a bounded load, and exports receive data URLs', async () => {
  const registry = createArtworkRegistry(withReviewedEntry('governor_city.default'), {
    resolveUrl: (path) => `/art/${path}`,
    loader: async (url) => ({ href: `object-url:${url}`, revoke: () => undefined }),
  });
  assert.equal(registry.resolve('governor_city.default', 'sprite')?.width, 128);
  assert.equal(registry.image('governor_city.default', 128), null);
  assert.deepEqual(await registry.ensure(['governor_city.default']), [
      `assets/sha256-${SHA}/icon.png`,
      `assets/sha256-${SHA}/sprite.png`,
      `assets/sha256-${SHA}/detail.png`,
    ]);
  assert.deepEqual(registry.image('governor_city.default', 128), {
    href: `object-url:/art/assets/sha256-${SHA}/sprite.png`,
    width: 128,
    height: 128,
    anchor: { x: 0.5, y: 1 },
    worldWidth: registry.entry('governor_city.default')!.world_width_tiles,
  });
  assert.deepEqual(registry.image('governor_city.default', 1024), {
    href: `object-url:/art/assets/sha256-${SHA}/detail.png`,
    width: 640,
    height: 640,
    anchor: { x: 0.5, y: 1 },
    worldWidth: registry.entry('governor_city.default')!.world_width_tiles,
  });

  const exported = createArtworkRegistry(withReviewedEntry('governor_city.default'), {
    mode: 'data-url',
    resolveUrl: (path) => `/art/${path}`,
    loader: async () => ({ href: 'data:image/png;base64,AAAA' }),
  });
  await exported.ensure(['governor_city.default']);
  assert.match(
    exported.image('governor_city.default', 64)!.href,
    /^data:image\/png;base64,[a-zA-Z0-9+/=]+$/,
  );
  registry.dispose();
  assert.deepEqual(registry.loaded(), []);
  assert.equal(registry.image('governor_city.default', 128), null);
});

test('the decoded-artwork cache is bounded and revokes what it evicts', async () => {
  const clone = withReviewedEntry('governor_city.default');
  const target = clone.entries.find((entry) => entry.key === 'governor_city.default')!;
  const second = structuredClone(target);
  second.key = 'banner.default';
  second.family = 'banner';
  second.variant = 'default';
  second.layer = 'planned';
    second.representations = {
      icon: representation('icon', 64, 64, OTHER_SHA),
      sprite: representation('sprite', 128, 128, OTHER_SHA),
      detail: representation('detail', 640, 640, OTHER_SHA),
    };
    clone.entries = [target, second];
    const revoked: string[] = [];
    const registry = createArtworkRegistry(clone, {
      cacheLimit: 1,
      resolveUrl: (path) => path,
      loader: async (url) => ({ href: url, revoke: () => revoked.push(url) }),
    });
    await registry.ensure(['governor_city.default']);
    assert.equal(registry.loaded().length, 1);
    await registry.ensure(['banner.default']);
    assert.equal(registry.loaded().length, 1);
    assert.deepEqual(revoked.sort(), [
      `assets/sha256-${SHA}/detail.png`,
      `assets/sha256-${SHA}/icon.png`,
      `assets/sha256-${SHA}/sprite.png`,
      `assets/sha256-${OTHER_SHA}/icon.png`,
      `assets/sha256-${OTHER_SHA}/sprite.png`,
    ]);
    assert.equal(registry.image('governor_city.default', 64), null);
    assert.ok(registry.image('banner.default', 64));
  });

test('unresolvable and failing loads are recorded as unavailable instead of as imagery', async () => {
  const reasons: string[] = [];
  const registry = createArtworkRegistry(withReviewedEntry('governor_city.default'), {
    resolveUrl: () => null,
    onUnavailable: (_key, reason) => reasons.push(reason),
  });
  assert.deepEqual(await registry.ensure(['governor_city.default', 'not.a.key']), []);
  assert.equal(registry.image('governor_city.default', 64), null);
  assert.ok(reasons.some((reason) => reason.includes('no delivered URL')));
  assert.ok(reasons.some((reason) => reason.includes('no registry entry')));

  const failing = createArtworkRegistry(withReviewedEntry('governor_city.default'), {
    resolveUrl: (path) => path,
    loader: async () => {
      throw new Error('artwork request failed with status 404');
    },
  });
  assert.deepEqual(await failing.ensure(['governor_city.default']), []);
  assert.equal(failing.image('governor_city.default', 64), null);
  assert.deepEqual(failing.unavailable().sort(), [
      `assets/sha256-${SHA}/detail.png`,
      `assets/sha256-${SHA}/icon.png`,
      `assets/sha256-${SHA}/sprite.png`,
    ]);
  });

test('structural validation rejects fabricated, mis-addressed and out-of-range artwork metadata', () => {
  assert.deepEqual(validateArtworkManifest(structuredClone(manifest)), []);
  assert.ok(
    diagnosticsFor((value) => {
      value.schema_version = 99;
    }).some((item) => item.includes('schema_version')),
  );
  assert.ok(
    diagnosticsFor((value) => {
      (value.limits as Record<string, unknown>).delivery_mime_types = ['image/gif'];
    }).some((item) => item.includes('subset of source_mime_types')),
  );
  const mutateEntry = (mutate: (entry: Record<string, unknown>) => void) =>
    diagnosticsFor((value) => {
      const entries = value.entries as Record<string, unknown>[];
      entries[0]!.review_state = 'reviewed';
      entries[0]!.required_representations = ['sprite'];
      entries[0]!.representations = { sprite: representation('sprite', 128, 128) };
      mutate((entries[0]!.representations as Record<string, unknown>).sprite as Record<string, unknown>);
    });
  assert.ok(mutateEntry((sprite) => (sprite.sha256 = 'not-a-hash')).some((item) => item.includes('64 lowercase hex')));
  assert.ok(
    mutateEntry((sprite) => (sprite.path = 'assets/icon.png')).some((item) =>
      item.includes('content addressed'),
    ),
  );
  assert.ok(
    mutateEntry((sprite) => (sprite.mime_type = 'image/gif')).some((item) =>
      item.includes('delivery MIME type'),
    ),
  );
  assert.ok(
    mutateEntry((sprite) => (sprite.byte_size = 0)).some((item) => item.includes('byte_size')),
  );
  assert.ok(
    mutateEntry((sprite) => (sprite.width = 9000)).some((item) => item.includes('max_dimension')),
  );
  assert.ok(
    mutateEntry((sprite) => (sprite.anchor = { x: 2, y: 0.5 })).some((item) =>
      item.includes('anchor.x'),
    ),
  );
  assert.ok(
    mutateEntry((sprite) => (sprite.orientation = 'diagonal')).some((item) =>
      item.includes('orientation'),
    ),
  );
  assert.ok(
    diagnosticsFor((value) => {
      const entries = value.entries as Record<string, unknown>[];
      entries[1]!.key = entries[0]!.key;
    }).some((item) => item.includes('duplicate entry key')),
  );
  assert.ok(
    diagnosticsFor((value) => {
      (value.entries as Record<string, unknown>[])[0]!.layer = 'not_a_layer';
    }).some((item) => item.includes('layer must be a scene layer')),
  );
  assert.ok(
    diagnosticsFor((value) => {
      (value.limits as Record<string, unknown>).max_registry_entries = 1;
    }).some((item) => item.includes('max_registry_entries')),
  );
  assert.ok(
    diagnosticsFor((value) => {
      (value.entries as Record<string, unknown>[])[0]!.world_width_tiles = 0;
    }).some((item) => item.includes('world_width_tiles')),
  );
  assert.throws(
    () => parseArtworkManifest({ ...structuredClone(manifest), schema_version: 99 }),
    ArtworkManifestError,
  );
});

test('the shipped registry keeps every entry free of fabricated provenance and authorization claims', () => {
  for (const entry of manifest.entries) {
    assert.equal(entry.source, null);
    assert.deepEqual(entry.provenance, entry.renderer === 'vector' ? entry.provenance : []);
    assert.ok(entry.rights_basis.length > 0);
    for (const kind of ['icon', 'sprite', 'detail'] as const)
      assert.equal(entry.representations[kind], null);
  }
  assert.equal(manifest.entries.length, new Set(manifest.entries.map((entry) => entry.key)).size);
});