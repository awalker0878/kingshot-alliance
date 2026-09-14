#!/usr/bin/env node
/**
 * Kingdom Map artwork preparation tooling.
 *
 * Accepts only explicitly selected local source files, validates MIME/dimensions/byte limits,
 * strips unsafe input, trims transparent borders, produces bounded resolution variants and
 * writes content-addressed delivery representations. It never invents authorization and never
 * substitutes unrelated artwork.
 *
 * Usage:
 *   node scripts/kingdom-map-art-import.mjs <file> --key <registry.key> --kind sprite [--width 512]
 */
import { createHash } from 'node:crypto';
import { deflateSync, inflateSync } from 'node:zlib';
import { readFileSync } from 'node:fs';
import { basename, relative } from 'node:path';
import process from 'node:process';
import { pathToFileURL } from 'node:url';
import { Buffer } from 'node:buffer';

export const DEFAULT_LIMITS = {
  max_source_bytes: 8388608,
  max_dimension: 4096,
  source_mime_types: ['image/png', 'image/webp', 'image/jpeg', 'image/svg+xml'],
  delivery_mime_types: ['image/png', 'image/webp', 'image/jpeg'],
};

const PNG_SIGNATURE = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
const EXTENSIONS = { 'image/png': 'png', 'image/webp': 'webp', 'image/jpeg': 'jpg' };

export class ArtworkSourceError extends Error {
  constructor(message) {
    super(message);
    this.name = 'ArtworkSourceError';
  }
}

/** Sniffs the source MIME from magic bytes. Unsupported or ambiguous input is rejected. */
export function detectMime(bytes) {
  if (bytes.length >= 8 && bytes.subarray(0, 8).equals(PNG_SIGNATURE)) return 'image/png';
  if (bytes.length >= 3 && bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) return 'image/jpeg';
  if (
    bytes.length >= 12 &&
    bytes.subarray(0, 4).toString('ascii') === 'RIFF' &&
    bytes.subarray(8, 12).toString('ascii') === 'WEBP'
  )
    return 'image/webp';
  const head = bytes.subarray(0, 512).toString('utf8').trimStart();
  if (head.startsWith('<svg') || head.startsWith('<?xml')) return 'image/svg+xml';
  return null;
}

export function sha256Hex(bytes) {
  return createHash('sha256').update(bytes).digest('hex');
}

function pngChunks(bytes) {
  if (!bytes.subarray(0, 8).equals(PNG_SIGNATURE))
    throw new ArtworkSourceError('PNG signature is invalid');
  const chunks = [];
  let offset = 8;
  while (offset + 12 <= bytes.length) {
    const length = bytes.readUInt32BE(offset);
    const type = bytes.subarray(offset + 4, offset + 8).toString('ascii');
    if (offset + 12 + length > bytes.length)
      throw new ArtworkSourceError(`PNG chunk ${type} is truncated`);
    chunks.push({ type, data: bytes.subarray(offset + 8, offset + 8 + length) });
    offset += 12 + length;
    if (type === 'IEND') break;
  }
  return chunks;
}

/** Reads the intrinsic dimensions of a PNG, JPEG or WebP source. */
export function readDimensions(bytes, mime) {
  if (mime === 'image/png') {
    const ihdr = pngChunks(bytes).find((chunk) => chunk.type === 'IHDR');
    if (!ihdr || ihdr.data.length < 8) throw new ArtworkSourceError('PNG IHDR is missing');
    return { width: ihdr.data.readUInt32BE(0), height: ihdr.data.readUInt32BE(4) };
  }
  if (mime === 'image/jpeg') {
    let offset = 2;
    while (offset + 9 < bytes.length) {
      if (bytes[offset] !== 0xff) {
        offset += 1;
        continue;
      }
      const marker = bytes[offset + 1];
      if (marker === 0xd8 || marker === 0x01 || (marker >= 0xd0 && marker <= 0xd7)) {
        offset += 2;
        continue;
      }
      const length = bytes.readUInt16BE(offset + 2);
      if ((marker >= 0xc0 && marker <= 0xc3) || (marker >= 0xc5 && marker <= 0xc7) ||
          (marker >= 0xc9 && marker <= 0xcb) || (marker >= 0xcd && marker <= 0xcf))
        return { height: bytes.readUInt16BE(offset + 5), width: bytes.readUInt16BE(offset + 7) };
      offset += 2 + length;
    }
    throw new ArtworkSourceError('JPEG frame header is missing');
  }
  if (mime === 'image/webp') {
    const fourCc = bytes.subarray(12, 16).toString('ascii');
    if (fourCc === 'VP8X')
      return {
        width: 1 + bytes.readUIntLE(24, 3),
        height: 1 + bytes.readUIntLE(27, 3),
      };
    if (fourCc === 'VP8 ')
      return {
        width: bytes.readUInt16LE(26) & 0x3fff,
        height: bytes.readUInt16LE(28) & 0x3fff,
      };
    if (fourCc === 'VP8L') {
      const bits = bytes.readUInt32LE(21);
      return { width: 1 + (bits & 0x3fff), height: 1 + ((bits >> 14) & 0x3fff) };
    }
    throw new ArtworkSourceError('Unsupported WebP bitstream');
  }
  throw new ArtworkSourceError(`Dimensions are unknown for ${mime}`);
}

/**
 * Strict SVG acceptance. Anything that could execute, exfiltrate or reference external
 * content is rejected outright; this environment has no rasterizer, so SVG sources are
 * never promoted to delivery representations.
 */
export function sanitizeSvg(text) {
  const body = text.replace(/^\uFEFF/, '').replace(/^\s*<\?xml[^>]*\?>/i, '');
  const rejections = [
    ['script element', /<script/i],
    ['event handler attribute', /\son[a-z]+\s*=/i],
    ['foreignObject', /<foreignobject/i],
    [
      'external or inline reference',
      /(?:xlink:href|href)\s*=\s*["']?\s*(?:https?:|\/\/|data:|javascript:|file:)/i,
    ],
    ['javascript URI', /javascript:/i],
    ['entity declaration', /<!entity/i],
    ['internal DTD subset', /<!doctype[^>]*\[/i],
    ['non-standard entity reference', /&(?!(?:amp|lt|gt|quot|apos|#\d+|#x[0-9a-f]+);)[a-z_][a-z0-9_]*\s*;/i],
    ['stylesheet import', /@import/i],
    ['active nested document', /<(?:iframe|embed|object|audio|video|canvas|base|meta)\b/i],
    ['smil animation', /<(?:animate|set|animatetransform|animatemotion)\b/i],
    ['processing instruction', /<\?/i],
  ];
  for (const [label, pattern] of rejections)
    if (pattern.test(body)) throw new ArtworkSourceError(`SVG contains rejected ${label}`);
  if (!/^<svg[\s>]/i.test(body.trim())) throw new ArtworkSourceError('SVG root element is missing');
  const width = /<svg[^>]*\swidth\s*=\s*["']?([0-9.]+)/i.exec(body);
  const height = /<svg[^>]*\sheight\s*=\s*["']?([0-9.]+)/i.exec(body);
  const viewBox =
    /viewbox\s*=\s*["']\s*(-?[0-9.]+)[\s,]+(-?[0-9.]+)[\s,]+([0-9.]+)[\s,]+([0-9.]+)/i.exec(body);
  const resolved = {
    width: width ? Number(width[1]) : viewBox ? Number(viewBox[3]) : null,
    height: height ? Number(height[1]) : viewBox ? Number(viewBox[4]) : null,
  };
  if (!resolved.width || !resolved.height)
    throw new ArtworkSourceError('SVG declares no usable width/height or viewBox');
  return resolved;
}

const CRC_TABLE = (() => {
  const table = new Int32Array(256);
  for (let index = 0; index < 256; index += 1) {
    let value = index;
    for (let bit = 0; bit < 8; bit += 1)
      value = value & 1 ? 0xedb88320 ^ (value >>> 1) : value >>> 1;
    table[index] = value;
  }
  return table;
})();

function crc32(buffer) {
  let crc = -1;
  for (const byte of buffer) crc = CRC_TABLE[(crc ^ byte) & 0xff] ^ (crc >>> 8);
  return (crc ^ -1) >>> 0;
}

function pngChunk(type, data) {
  const header = Buffer.alloc(8);
  header.writeUInt32BE(data.length, 0);
  header.write(type, 4, 'ascii');
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(Buffer.concat([header.subarray(4), data])), 0);
  return Buffer.concat([header, data, crc]);
}

function paeth(a, b, c) {
  const p = a + b - c;
  const pa = Math.abs(p - a);
  const pb = Math.abs(p - b);
  const pc = Math.abs(p - c);
  return pa <= pb && pa <= pc ? a : pb <= pc ? b : c;
}

/** Decodes non-interlaced 8-bit PNG sources (gray, RGB, palette, gray+alpha, RGBA). */
export function decodePng(bytes) {
  const chunks = pngChunks(bytes);
  const ihdr = chunks.find((chunk) => chunk.type === 'IHDR');
  if (!ihdr) throw new ArtworkSourceError('PNG IHDR is missing');
  const width = ihdr.data.readUInt32BE(0);
  const height = ihdr.data.readUInt32BE(4);
  const bitDepth = ihdr.data[8];
  const colorType = ihdr.data[9];
  const interlace = ihdr.data[12];
  if (interlace !== 0) throw new ArtworkSourceError('Interlaced PNG is unsupported');
  if (bitDepth !== 8) throw new ArtworkSourceError(`PNG bit depth ${bitDepth} is unsupported`);
  const channels = { 0: 1, 2: 3, 3: 1, 4: 2, 6: 4 }[colorType];
  if (!channels) throw new ArtworkSourceError(`PNG color type ${colorType} is unsupported`);
  const palette = chunks.find((chunk) => chunk.type === 'PLTE')?.data;
  if (colorType === 3 && !palette) throw new ArtworkSourceError('Palette PNG has no PLTE chunk');
  const transparency = chunks.find((chunk) => chunk.type === 'tRNS')?.data;
  const idat = Buffer.concat(chunks.filter((chunk) => chunk.type === 'IDAT').map((chunk) => chunk.data));
  const raw = inflateSync(idat);
  const stride = width * channels;
  if (raw.length < (stride + 1) * height) throw new ArtworkSourceError('PNG image data is truncated');
  const lines = Buffer.alloc(stride * height);
  for (let row = 0; row < height; row += 1) {
    const filter = raw[row * (stride + 1)];
    const source = raw.subarray(row * (stride + 1) + 1, (row + 1) * (stride + 1));
    const target = lines.subarray(row * stride, (row + 1) * stride);
    const previous = row ? lines.subarray((row - 1) * stride, row * stride) : Buffer.alloc(stride);
    for (let index = 0; index < stride; index += 1) {
      const left = index >= channels ? target[index - channels] : 0;
      const up = previous[index];
      const upLeft = index >= channels ? previous[index - channels] : 0;
      const value = source[index];
      target[index] =
        filter === 0
          ? value
          : filter === 1
            ? (value + left) & 0xff
            : filter === 2
              ? (value + up) & 0xff
              : filter === 3
                ? (value + ((left + up) >> 1)) & 0xff
                : filter === 4
                  ? (value + paeth(left, up, upLeft)) & 0xff
                  : (() => {
                      throw new ArtworkSourceError(`PNG filter ${filter} is unsupported`);
                    })();
    }
  }
  const rgba = Buffer.alloc(width * height * 4);
  for (let pixel = 0; pixel < width * height; pixel += 1) {
    const source = pixel * channels;
    let r;
    let g;
    let b;
    let a = 255;
    if (colorType === 0 || colorType === 4) {
      r = g = b = lines[source];
      if (colorType === 4) a = lines[source + 1];
      if (colorType === 0 && transparency && lines[source] === transparency[1]) a = 0;
    } else if (colorType === 2 || colorType === 6) {
      r = lines[source];
      g = lines[source + 1];
      b = lines[source + 2];
      if (colorType === 6) a = lines[source + 3];
    } else {
      const index = lines[source];
      r = palette[index * 3];
      g = palette[index * 3 + 1];
      b = palette[index * 3 + 2];
      a = transparency && index < transparency.length ? transparency[index] : 255;
    }
    rgba[pixel * 4] = r;
    rgba[pixel * 4 + 1] = g;
    rgba[pixel * 4 + 2] = b;
    rgba[pixel * 4 + 3] = a;
  }
  return { width, height, rgba };
}

/** Encodes RGBA pixels as a deterministic 8-bit PNG (no ancillary metadata). */
export function encodePng({ width, height, rgba }) {
  const stride = width * 4;
  const raw = Buffer.alloc((stride + 1) * height);
  for (let row = 0; row < height; row += 1) {
    raw[row * (stride + 1)] = 0;
    rgba.copy(raw, row * (stride + 1) + 1, row * stride, (row + 1) * stride);
  }
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(width, 0);
  ihdr.writeUInt32BE(height, 4);
  ihdr[8] = 8;
  ihdr[9] = 6;
  return Buffer.concat([
    PNG_SIGNATURE,
    pngChunk('IHDR', ihdr),
    pngChunk('IDAT', deflateSync(raw, { level: 9 })),
    pngChunk('IEND', Buffer.alloc(0)),
  ]);
}

/** Bounding box of non-transparent pixels; fully transparent input is rejected. */
export function trimTransparent({ width, height, rgba }) {
  let minX = width;
  let minY = height;
  let maxX = -1;
  let maxY = -1;
  for (let y = 0; y < height; y += 1) {
    for (let x = 0; x < width; x += 1) {
      if (rgba[(y * width + x) * 4 + 3] === 0) continue;
      if (x < minX) minX = x;
      if (y < minY) minY = y;
      if (x > maxX) maxX = x;
      if (y > maxY) maxY = y;
    }
  }
  if (maxX < 0) throw new ArtworkSourceError('Source image is fully transparent');
  return { x: minX, y: minY, width: maxX - minX + 1, height: maxY - minY + 1 };
}

export function cropRgba({ width, rgba }, box) {
  const target = Buffer.alloc(box.width * box.height * 4);
  for (let y = 0; y < box.height; y += 1) {
    const sourceStart = ((box.y + y) * width + box.x) * 4;
    rgba.copy(target, y * box.width * 4, sourceStart, sourceStart + box.width * 4);
  }
  return { width: box.width, height: box.height, rgba: target };
}

/** Box-filter downscale. Upscaling is never performed; bounded variants only shrink. */
export function scaleRgba({ width, height, rgba }, targetWidth) {
  if (targetWidth >= width) return { width, height, rgba };
  const targetHeight = Math.max(1, Math.round((height * targetWidth) / width));
  const target = Buffer.alloc(targetWidth * targetHeight * 4);
  for (let y = 0; y < targetHeight; y += 1) {
    const y0 = Math.floor((y * height) / targetHeight);
    const y1 = Math.max(y0 + 1, Math.floor(((y + 1) * height) / targetHeight));
    for (let x = 0; x < targetWidth; x += 1) {
      const x0 = Math.floor((x * width) / targetWidth);
      const x1 = Math.max(x0 + 1, Math.floor(((x + 1) * width) / targetWidth));
      const sums = [0, 0, 0, 0];
      let count = 0;
      for (let sy = y0; sy < y1; sy += 1)
        for (let sx = x0; sx < x1; sx += 1) {
          const source = (sy * width + sx) * 4;
          sums[0] += rgba[source];
          sums[1] += rgba[source + 1];
          sums[2] += rgba[source + 2];
          sums[3] += rgba[source + 3];
          count += 1;
        }
      const target0 = (y * targetWidth + x) * 4;
      for (let channel = 0; channel < 4; channel += 1)
        target[target0 + channel] = Math.round(sums[channel] / count);
    }
  }
  return { width: targetWidth, height: targetHeight, rgba: target };
}

/** Content-addressed delivery path inside the art pack. */
export function contentAddressedPath(sha256, key, mime) {
  const extension = EXTENSIONS[mime];
  if (!extension) throw new ArtworkSourceError(`No delivery extension for ${mime}`);
  return `assets/sha256-${sha256}/${key}.${extension}`;
}

/**
 * Validates one explicitly selected source file and returns the representation record
 * that belongs in resources/data/kingdom-map-art/manifest.v1.json.
 */
export function prepareRepresentation({
  key,
  kind,
  filename,
  bytes,
  limits = DEFAULT_LIMITS,
  anchor = { x: 0.5, y: 1 },
  orientation = 'upright',
  targetWidth,
  variantWidths = { icon: 64, sprite: 256, detail: 1024 },
}) {
  if (!/^[a-z0-9]+(?:_[a-z0-9]+)*\.[a-z0-9]+(?:_[a-z0-9]+)*$/.test(key))
    throw new ArtworkSourceError(`Registry key ${key} is not a stable lowercase key`);
  if (!['icon', 'sprite', 'detail'].includes(kind))
    throw new ArtworkSourceError(`Representation kind ${kind} is unsupported`);
  if (!bytes.length) throw new ArtworkSourceError('Source file is empty');
  if (bytes.length > limits.max_source_bytes)
    throw new ArtworkSourceError(`Source file exceeds max_source_bytes (${limits.max_source_bytes})`);
  const mime = detectMime(bytes);
  if (!mime) throw new ArtworkSourceError('Source MIME type is unsupported or ambiguous');
  if (!limits.source_mime_types.includes(mime))
    throw new ArtworkSourceError(`Source MIME type ${mime} is not authorized`);
  if (!limits.delivery_mime_types.includes(mime))
    throw new ArtworkSourceError(
      `${mime} cannot be delivered; SVG sources must be rasterized by an approved rasterizer first`,
    );
  let dimensions;
  if (mime === 'image/svg+xml') {
    dimensions = sanitizeSvg(bytes.toString('utf8'));
  } else {
    dimensions = readDimensions(bytes, mime);
  }
  if (
    dimensions.width > limits.max_dimension ||
    dimensions.height > limits.max_dimension
  )
    throw new ArtworkSourceError(`Source dimensions exceed max_dimension (${limits.max_dimension})`);

  let delivery = bytes;
  if (mime === 'image/png') {
    const decoded = decodePng(bytes);
    const trimmed = cropRgba(decoded, trimTransparent(decoded));
    const width = Math.min(targetWidth ?? variantWidths[kind], trimmed.width, limits.max_dimension);
    delivery = encodePng(scaleRgba(trimmed, width));
  }
  const deliveryDimensions = readDimensions(delivery, mime);
  const sha256 = sha256Hex(delivery);
  return {
    key,
    kind,
    source_filename: basename(filename),
    source: {
      mime_type: mime,
      byte_size: bytes.length,
      width: dimensions.width,
      height: dimensions.height,
      sha256: sha256Hex(bytes),
    },
    representation: {
      path: contentAddressedPath(sha256, key, mime),
      sha256,
      mime_type: mime,
      byte_size: delivery.length,
      width: deliveryDimensions.width,
      height: deliveryDimensions.height,
      anchor,
      orientation,
    },
    delivery_bytes: delivery,
  };
}

function main(argv) {
  const [file, ...rest] = argv;
  if (!file) {
    console.error(
      'Usage: node scripts/kingdom-map-art-import.mjs <file> --key <registry.key> --kind <icon|sprite|detail> [--width N]',
    );
    return 1;
  }
  const option = (name) => {
    const index = rest.indexOf(`--${name}`);
    return index === -1 ? undefined : rest[index + 1];
  };
  try {
    const bytes = readFileSync(file);
    const prepared = prepareRepresentation({
      key: option('key') ?? '',
      kind: option('kind') ?? 'sprite',
      filename: file,
      bytes,
      targetWidth: option('width') ? Number(option('width')) : undefined,
    });
    console.log(
      JSON.stringify(
        {
          key: prepared.key,
          kind: prepared.kind,
          source: prepared.source,
          representation: prepared.representation,
          delivery_file: relative(process.cwd(), file),
          delivery_bytes: prepared.delivery_bytes.length,
        },
        null,
        2,
      ),
    );
    console.log(
      'Apply this representation to manifest.v1.json, write the bytes to the content-addressed path, then re-run the strict registry check.',
    );
    return 0;
  } catch (error) {
    console.error(`Artwork import rejected: ${error instanceof Error ? error.message : String(error)}`);
    return 1;
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href)
  process.exit(main(process.argv.slice(2)));