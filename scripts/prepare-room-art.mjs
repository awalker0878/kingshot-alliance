#!/usr/bin/env node
// Optional asset preparation only. Runtime builds consume the committed WebP files.
// Requires ImageMagick 6+ with WebP support; no network or image-generation calls.
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
const manifestPath = resolve(root, 'resources/data/room-art/manifest.v1.json');
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
const digest = (bytes) => createHash('sha256').update(bytes).digest('hex');

for (const scene of manifest.scenes) {
  const master = resolve(root, scene.master.path);
  const masterBytes = readFileSync(master);
  const [width, height] = execFileSync('identify', ['-format', '%w %h', master], {
    encoding: 'utf8',
  })
    .trim()
    .split(' ')
    .map(Number);
  if (scene.master.sha256 && scene.master.sha256 !== digest(masterBytes)) {
    throw new Error(`${scene.key}: master changed; review the source before preparing exports`);
  }
  Object.assign(scene.master, {
    width,
    height,
    byte_size: masterBytes.length,
    sha256: digest(masterBytes),
  });
  for (const kind of ['desktop', 'mobile']) {
    const target = scene[kind];
    const crop = target.crop;
    if (
      crop.x < 0 ||
      crop.y < 0 ||
      crop.x + crop.width > width ||
      crop.y + crop.height > height ||
      target.width > crop.width ||
      target.height > crop.height
    ) {
      throw new Error(`${scene.key}.${kind}: invalid crop or upscaling requested`);
    }
    const path = `public/images/kingshot/generated/${scene.key}-${kind}.webp`;
    const output = resolve(root, path);
    mkdirSync(dirname(output), { recursive: true });
    execFileSync('convert', [
      master,
      '-crop',
      `${crop.width}x${crop.height}+${crop.x}+${crop.y}`,
      '+repage',
      '-resize',
      `${target.width}x${target.height}!`,
      '-strip',
      '-quality',
      '82',
      '-define',
      'webp:method=6',
      output,
    ]);
    const bytes = readFileSync(output);
    if (bytes.length > target.max_bytes)
      throw new Error(`${scene.key}.${kind}: delivery budget exceeded`);
    Object.assign(target, { path, byte_size: bytes.length, sha256: digest(bytes) });
    console.log(`${scene.key}.${kind}: ${target.width}×${target.height}, ${bytes.length} bytes`);
  }
}
writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);
