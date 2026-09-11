import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { deflateSync } from 'node:zlib';
import test from 'node:test';
import { assertViewportRaster, viewportTiles } from '../Support/viewportRaster.ts';

const fixtures: { format: string; filter: number; base64: string }[] = JSON.parse(
  readFileSync(new URL('../Fixtures/viewport-raster.json', import.meta.url), 'utf8'),
);
for (const fixture of fixtures) {
  test(`${fixture.format} row filter ${fixture.filter} preserves a populated bounded raster`, () => {
    const png = Buffer.from(fixture.base64, 'base64');
    assert.doesNotThrow(() => assertViewportRaster(png, 3, 2));
    assert.throws(() => assertViewportRaster(png, 3, 3), /dimensions/);
  });
}

test('every vertical source pixel is captured exactly once, including a short final tile', () => {
  const tiles = viewportTiles(84, 28587, 779);
  assert.equal(tiles[0].top, 84);
  let cursor = 84;
  for (const tile of tiles) {
    assert.equal(tile.top, cursor);
    assert.ok(tile.height > 0 && tile.height <= 779);
    cursor += tile.height;
  }
  assert.equal(cursor, 84 + 28587);
  assert.equal(tiles.at(-1)!.height, 543);
});

test('empty, unsafe and unbounded screenshot dimensions cannot pass', () => {
  for (const values of [
    [-1, 40, 30],
    [1, 0, 30],
    [1, 40, 0],
    [1, 100001, 40],
    [1, 2.5, 1],
  ]) {
    assert.throws(() => viewportTiles(values[0], values[1], values[2]), /dimensions/);
  }
  assert.throws(() => assertViewportRaster(Buffer.from('not png'), 3, 2), /not PNG/);
});

test('a solid-color raster is not accepted as populated readiness content', () => {
  // Real PNG header; replace only image data with two unfiltered uniform RGB rows.
  const original = Buffer.from(fixtures[0].base64, 'base64');
  const compressed = deflateSync(Buffer.from([0, ...Array(9).fill(18), 0, ...Array(9).fill(18)]));
  const idat = Buffer.alloc(12 + compressed.length);
  idat.writeUInt32BE(compressed.length);
  idat.write('IDAT', 4);
  compressed.copy(idat, 8);
  const png = Buffer.concat([original.subarray(0, 33), idat, original.subarray(-12)]);
  assert.throws(() => assertViewportRaster(png, 3, 2), /single background color/);
});
