import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import {
  ArtworkSourceError,
  DEFAULT_LIMITS,
  contentAddressedPath,
  decodePng,
  detectMime,
  encodePng,
  prepareRepresentation,
  readDimensions,
  scaleRgba,
  sha256Hex,
  trimTransparent,
} from '../../../../scripts/kingdom-map-art-import.mjs';
import { validateArtworkManifest } from '../../../../resources/js/features/territory-planner/engine/artwork.ts';
import type { ArtworkManifest } from '../../../../resources/js/features/territory-planner/engine/artwork.ts';

const read = (path: string) =>
  JSON.parse(readFileSync(new URL(path, import.meta.url), 'utf8')) as unknown;

const manifest = read(
  '../../../../resources/data/kingdom-map-art/manifest.v1.json',
) as ArtworkManifest;

/** Builds a genuine PNG source with an opaque inset block so trimming and scaling can be exercised. */
function pngOf(width: number, height: number, inset = 2): Buffer {
  const rgba = Buffer.alloc(width * height * 4);
  for (let y = inset; y < height - inset; y += 1)
    for (let x = inset; x < width - inset; x += 1) {
      const offset = (y * width + x) * 4;
      rgba[offset] = 200;
      rgba[offset + 1] = 60;
      rgba[offset + 2] = 30;
      rgba[offset + 3] = 255;
    }
  return encodePng({ width, height, rgba });
}

const SOURCE = pngOf(40, 40);

test('source MIME sniffing accepts only the authorized raster formats and real SVG text', () => {
  assert.equal(detectMime(SOURCE), 'image/png');
  assert.equal(detectMime(Buffer.from([0xff, 0xd8, 0xff, 0xe0, 0x00])), 'image/jpeg');
  assert.equal(
    detectMime(Buffer.concat([Buffer.from('RIFF'), Buffer.alloc(4), Buffer.from('WEBP')])),
    'image/webp',
  );
  assert.equal(detectMime(Buffer.from('  <svg viewBox="0 0 1 1"></svg>')), 'image/svg+xml');
  assert.equal(detectMime(Buffer.from('  <?xml version="1.0"?><svg/>')), 'image/svg+xml');
  for (const value of ['', 'GIF89a', 'not an image at all', 'PNG'])
    assert.equal(detectMime(Buffer.from(value)), null);
});

test('the preparation tool rejects unauthorised, oversized, unsupported and empty sources', () => {
  const prepare = (overrides: Record<string, unknown>) =>
    prepareRepresentation({
      key: 'governor_city.default',
      kind: 'sprite',
      filename: 'source.png',
      bytes: SOURCE,
      ...overrides,
    } as Parameters<typeof prepareRepresentation>[0]);

  assert.throws(() => prepare({ key: 'Not A Key' }), ArtworkSourceError);
  assert.throws(() => prepare({ key: 'governor_city' }), ArtworkSourceError);
  assert.throws(() => prepare({ kind: 'hero' }), ArtworkSourceError);
  assert.throws(() => prepare({ bytes: Buffer.alloc(0) }), /empty/);
  assert.throws(() => prepare({ bytes: Buffer.from('GIF89a') }), /unsupported or ambiguous/);
  assert.throws(
    () => prepare({ limits: { ...DEFAULT_LIMITS, max_source_bytes: 8 } }),
    /max_source_bytes/,
  );
  assert.throws(
    () => prepare({ limits: { ...DEFAULT_LIMITS, max_dimension: 32 } }),
    /max_dimension/,
  );
  assert.throws(
    () => prepare({ limits: { ...DEFAULT_LIMITS, source_mime_types: ['image/gif'] } }),
    /not authorized/,
  );
  // A vector source may not be delivered directly; it must be rasterized by an approved rasterizer.
  assert.throws(
    () => prepare({ bytes: Buffer.from('<svg viewBox="0 0 10 10"></svg>') }),
    /must be rasterized/,
  );
  assert.throws(() => prepare({ bytes: pngOf(20, 20, 10) }), /fully transparent/);
});

test('prepared representations are content-addressed, trimmed, bounded and never upscaled', () => {
  const prepared = prepareRepresentation({
    key: 'governor_city.default',
    kind: 'sprite',
    filename: 'C:/art/governor_city.png',
    bytes: SOURCE,
  });
  assert.equal(prepared.source_filename, 'governor_city.png');
  assert.equal(prepared.source.mime_type, 'image/png');
  assert.equal(prepared.source.byte_size, SOURCE.length);
  assert.equal(prepared.source.width, 40);
  assert.equal(prepared.source.height, 40);
  assert.equal(prepared.source.sha256, sha256Hex(SOURCE));

  // The 2-tile transparent border is trimmed before any delivery bytes are written.
  assert.equal(prepared.representation.width, 36);
  assert.equal(prepared.representation.height, 36);
  assert.equal(prepared.representation.byte_size, prepared.delivery_bytes.length);
  assert.equal(prepared.representation.sha256, sha256Hex(prepared.delivery_bytes));
  assert.equal(
    prepared.representation.path,
    contentAddressedPath(prepared.representation.sha256, prepared.key, 'image/png'),
  );
  assert.ok(prepared.representation.path.includes(prepared.representation.sha256));
  assert.deepEqual(prepared.representation.anchor, { x: 0.5, y: 1 });
  assert.equal(prepared.representation.orientation, 'upright');
  assert.equal(prepared.representation.mime_type, 'image/png');

  const decoded = decodePng(prepared.delivery_bytes);
  assert.equal(decoded.width, prepared.representation.width);
  assert.equal(decoded.height, prepared.representation.height);
  assert.deepEqual(readDimensions(prepared.delivery_bytes, 'image/png'), {
    width: prepared.representation.width,
    height: prepared.representation.height,
  });

  // A small source is never upscaled, and an explicit --width can only shrink it further.
  const shrink = prepareRepresentation({
    key: 'banner.default',
    kind: 'detail',
    filename: 'banner.png',
    bytes: SOURCE,
    targetWidth: 16,
  });
  assert.equal(shrink.representation.width, 16);
  assert.equal(shrink.representation.height, 16);
  const enlarge = prepareRepresentation({
    key: 'banner.default',
    kind: 'detail',
    filename: 'banner.png',
    bytes: SOURCE,
    targetWidth: 4096,
  });
  assert.equal(enlarge.representation.width, 36);
  assert.equal(scaleRgba(decodePng(SOURCE), 40).width, 40);
});

test('a prepared representation is accepted by the versioned registry validator unchanged', () => {
  const prepared = prepareRepresentation({
    key: 'governor_city.default',
    kind: 'sprite',
    filename: 'governor_city.png',
    bytes: SOURCE,
  });
  const candidate = structuredClone(manifest);
  const entry = candidate.entries.find((item) => item.key === prepared.key);
  assert.ok(entry);
  entry.review_state = 'reviewed';
  entry.source = { ...prepared.source, source_filename: prepared.source_filename };
  entry.representations.sprite = prepared.representation;
  assert.deepEqual(validateArtworkManifest(candidate), []);

  // The tool records bytes and geometry only; it never asserts rights or authorization on its own.
  assert.equal('rights_basis' in prepared.source, false);
  assert.equal('review_state' in prepared.source, false);
  assert.equal('provenance' in prepared.source, false);
  assert.equal('rights_basis' in prepared.representation, false);
});

test('the shipped manifest keeps every raster entry awaiting source until real bytes are supplied', () => {
  assert.deepEqual(validateArtworkManifest(manifest), []);
  const raster = manifest.entries.filter((entry) => entry.renderer === 'raster');
  assert.ok(raster.length > 0);
  for (const entry of raster) {
    assert.equal(entry.review_state, 'awaiting_source', `${entry.key} must not claim a review`);
    assert.equal(entry.source, null, `${entry.key} must not claim a verified source`);
    assert.equal(entry.rights_basis, 'kingshot_supplied_artwork');
    for (const kind of entry.required_representations)
      assert.equal(entry.representations[kind], null, `${entry.key}.${kind} must not exist yet`);
  }
  // Trimming may only ever crop to real opaque pixels; a blank source has no representation.
  assert.throws(() => trimTransparent(decodePng(pngOf(8, 8, 4))), /fully transparent/);
});