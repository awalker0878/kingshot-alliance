import assert from 'node:assert/strict';
import test from 'node:test';
import { stableViewportRaster } from '../Support/stableViewportRaster.ts';

test('a stable viewport needs two identical frames', async () => {
  const pixels = Buffer.from('current pixels');
  let calls = 0;
  assert.equal(await stableViewportRaster(async () => (++calls, pixels)), pixels);
  assert.equal(calls, 2);
});

test('an initial transient frame does not become the captured result', async () => {
  const frames = [Buffer.from('transient'), Buffer.from('stable'), Buffer.from('stable')];
  let calls = 0;
  const result = await stableViewportRaster(async () => frames[calls++]!);
  assert.equal(result.toString(), 'stable');
  assert.equal(calls, 3);
});

test('a consistently changed viewport is returned, not hidden or matched against a baseline', async () => {
  assert.equal(
    (await stableViewportRaster(async () => Buffer.from('changed business content'))).toString(),
    'changed business content',
  );
});

test('never-stable frames fail after a bounded number of captures', async () => {
  let calls = 0;
  await assert.rejects(
    stableViewportRaster(async () => Buffer.from(String(++calls))),
    /two consecutive identical rasters/,
  );
  assert.equal(calls, 6);
});

test('capture failures are not swallowed or retried', async () => {
  let calls = 0;
  await assert.rejects(
    stableViewportRaster(async () => {
      calls++;
      throw new Error('Raster acquisition failed');
    }),
    /Raster acquisition failed/,
  );
  assert.equal(calls, 1);
});
