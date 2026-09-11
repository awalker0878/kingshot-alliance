import { inflateSync } from 'node:zlib';

/** Contiguous source intervals, never a sampled set of screenshots. */
export function viewportTiles(
  top: number,
  height: number,
  available: number,
): { top: number; height: number }[] {
  if (
    ![top, height, available].every(Number.isSafeInteger) ||
    top < 0 ||
    height < 1 ||
    available < 1 ||
    height > 100_000
  ) {
    throw new Error('Invalid or excessive readiness capture dimensions.');
  }
  const result: { top: number; height: number }[] = [];
  for (let offset = 0; offset < height; offset += available) {
    result.push({ top: top + offset, height: Math.min(available, height - offset) });
  }
  return result;
}

/** Fail if Chromium returns a clipped or single-background raster for populated content. */
export function assertViewportRaster(png: Buffer, width: number, height: number): void {
  if (
    !png.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10])) ||
    png.length < 33
  ) {
    throw new Error('Readiness capture is not PNG.');
  }
  if (
    png.readUInt32BE(16) !== width ||
    png.readUInt32BE(20) !== height ||
    width < 1 ||
    height < 1 ||
    width * height > 4_000_000
  ) {
    throw new Error('Readiness raster dimensions do not match the bounded viewport tile.');
  }
  const channels = png[25] === 2 ? 3 : png[25] === 6 ? 4 : 0;
  if (png[24] !== 8 || !channels || png[26] !== 0 || png[27] !== 0 || png[28] !== 0) {
    throw new Error('Unsupported readiness PNG format.');
  }
  const chunks: Buffer[] = [];
  for (let offset = 8; offset + 12 <= png.length;) {
    const size = png.readUInt32BE(offset);
    if (offset + size + 12 > png.length) throw new Error('Truncated readiness PNG.');
    if (png.toString('ascii', offset + 4, offset + 8) === 'IDAT')
      chunks.push(png.subarray(offset + 8, offset + 8 + size));
    offset += size + 12;
  }
  const stride = width * channels;
  const expected = (stride + 1) * height;
  const bytes = inflateSync(Buffer.concat(chunks), { maxOutputLength: expected });
  if (bytes.length !== expected) throw new Error('Readiness raster data is incomplete.');
  let previous = Buffer.alloc(stride);
  let first: number[] | null = null;
  for (let y = 0; y < height; y++) {
    const filter = bytes[y * (stride + 1)];
    if (filter > 4) throw new Error('Unsupported readiness PNG row filter.');
    const row = Buffer.alloc(stride);
    for (let x = 0; x < stride; x++) {
      const left = x >= channels ? row[x - channels] : 0;
      const up = previous[x];
      const corner = x >= channels ? previous[x - channels] : 0;
      let delta = 0;
      if (filter === 1) delta = left;
      else if (filter === 2) delta = up;
      else if (filter === 3) delta = Math.floor((left + up) / 2);
      else if (filter === 4) {
        const prediction = left + up - corner;
        const a = Math.abs(prediction - left),
          b = Math.abs(prediction - up),
          c = Math.abs(prediction - corner);
        delta = a <= b && a <= c ? left : b <= c ? up : corner;
      }
      row[x] = (bytes[y * (stride + 1) + 1 + x] + delta) & 255;
    }
    for (let x = 0; x < stride; x += channels) {
      first ??= [row[x], row[x + 1], row[x + 2]];
      if (row[x] !== first[0] || row[x + 1] !== first[1] || row[x + 2] !== first[2]) return;
    }
    previous = row;
  }
  throw new Error('Populated readiness tile rendered as a single background color.');
}
