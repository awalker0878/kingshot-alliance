import { Buffer } from 'node:buffer';
import { createHash } from 'node:crypto';
import { inflateSync } from 'node:zlib';

const SIDE = 1200;
const CELLS = SIDE * SIDE;
const RESOURCE_TYPES = new Set(['bread', 'woodmill', 'quarry', 'ironmine']);

export function sha256(bytes) {
  return createHash('sha256').update(bytes).digest('hex');
}

function requireFact(condition, message) {
  if (!condition) throw new Error(`Kingdom map source: ${message}`);
}

/** Read one explicit literal, never evaluate any upstream application code. */
function literal(source, name, kind) {
  const expression =
    kind === 'array'
      ? String.raw`(\[[^;\r\n]*\])`
      : kind === 'string'
        ? String.raw`'([A-Za-z0-9+/=]+)'`
        : String.raw`(\d+)`;
  const matches = [...source.matchAll(new RegExp(`^const ${name}\\s*=\\s*${expression};`, 'gm'))];
  requireFact(matches.length === 1, `expected exactly one ${name} literal`);
  const value = matches[0][1];
  return kind === 'string' ? value : JSON.parse(value);
}

/**
 * Materialize Kingshot's pinned 2-bit row-major bitmap into exact horizontal
 * unit-height spans. Bounding boxes remain selection envelopes, never substitute
 * blocking geometry. Whiteout data and upstream renderer/optimizer code are ignored.
 */
export function extractKingshotTerrain(source) {
  requireFact(typeof source === 'string' && Buffer.byteLength(source) <= 8_000_000, 'source size');
  requireFact(literal(source, 'GRID_SIZE', 'integer') === SIDE, 'unsupported source grid');
  const encoded = literal(source, '_TERRAIN_B64_KINGSHOT', 'string');
  requireFact(encoded.length <= 1_000_000, 'compressed terrain size');
  const compressed = Buffer.from(encoded, 'base64');
  requireFact(compressed.toString('base64') === encoded, 'non-canonical terrain base64');
  // The reviewed Kingshot contract is zlib-wrapped deflate. No raw/other-game fallback.
  const packed = inflateSync(compressed, { maxOutputLength: CELLS / 4 });
  requireFact(packed.length === CELLS / 4, 'terrain bitmap must have exactly 360000 bytes');
  const mask = new Uint8Array(CELLS);
  const labels = new Uint16Array(CELLS);
  for (let index = 0; index < packed.length; index += 1) {
    for (let bit = 0; bit < 4; bit += 1) {
      const value = (packed[index] >> (bit * 2)) & 3;
      requireFact(value !== 3, 'unsupported terrain cell value');
      mask[index * 4 + bit] = value;
    }
  }
  const queue = new Uint32Array(CELLS);
  const features = [];
  const counts = { lake: 0, mountain: 0 };
  const cellCounts = { lake: 0, mountain: 0 };
  for (const [family, code, name] of [
    ['lake', 2, 'LAKE'],
    ['mountain', 1, 'MT'],
  ]) {
    const metadata = literal(source, `_${name}_META_KINGSHOT`, 'array');
    const expected = literal(source, `_${name}_COUNT_KINGSHOT`, 'integer');
    requireFact(
      Array.isArray(metadata) &&
        metadata.length === expected * 7 &&
        expected <= 10_000 &&
        metadata.every((value) => typeof value === 'number' && Number.isFinite(value)),
      `${family} metadata count or values`,
    );
    for (let start = 0; start < CELLS; start += 1) {
      if (mask[start] !== code || labels[start] !== 0) continue;
      counts[family] += 1;
      requireFact(counts[family] <= expected, `${family} component count exceeds its source`);
      const label = features.length + 1;
      let head = 0;
      let tail = 1;
      queue[0] = start;
      labels[start] = label;
      let minX = SIDE;
      let minY = SIDE;
      let maxX = -1;
      let maxY = -1;
      let sumX = 0;
      let sumY = 0;
      while (head < tail) {
        const index = queue[head++];
        const x = index % SIDE;
        const y = Math.floor(index / SIDE);
        minX = Math.min(minX, x);
        minY = Math.min(minY, y);
        maxX = Math.max(maxX, x);
        maxY = Math.max(maxY, y);
        sumX += x;
        sumY += y;
        for (const next of [
          x > 0 ? index - 1 : -1,
          x + 1 < SIDE ? index + 1 : -1,
          y > 0 ? index - SIDE : -1,
          y + 1 < SIDE ? index + SIDE : -1,
        ]) {
          if (next >= 0 && mask[next] === code && labels[next] === 0) {
            labels[next] = label;
            queue[tail++] = next;
          }
        }
      }
      const fact = metadata.slice((counts[family] - 1) * 7, counts[family] * 7);
      requireFact(
        [minX, minY, maxX + 1, maxY + 1, tail].every(
          (value, index) => value === fact[index < 4 ? index : 6],
        ),
        `${family} component ${counts[family]} differs from source bounds/count`,
      );
      requireFact(
        Math.abs(sumX / tail - fact[4]) <= 0.051 && Math.abs(sumY / tail - fact[5]) <= 0.051,
        `${family} component ${counts[family]} differs from source centroid`,
      );
      const cells = queue.slice(0, tail).sort();
      const spans = [];
      for (let index = 0; index < cells.length;) {
        const cell = cells[index++];
        const x = cell % SIDE;
        const y = Math.floor(cell / SIDE);
        let width = 1;
        while (
          index < cells.length &&
          cells[index] === cell + width &&
          Math.floor(cells[index] / SIDE) === y
        ) {
          index += 1;
          width += 1;
        }
        spans.push([x, y, width]);
      }
      features.push({
        key: `${family}_${String(counts[family]).padStart(4, '0')}`,
        family,
        bounds: { x: minX, y: minY, width: maxX - minX + 1, height: maxY - minY + 1 },
        centroid: { x: fact[4], y: fact[5] },
        cell_count: tail,
        spans,
      });
      cellCounts[family] += tail;
    }
    requireFact(counts[family] === expected, `${family} component count does not match source`);
  }
  return { features, counts, cellCounts, mask, labels, bitmapSha256: sha256(packed) };
}

export function extractKingshotResources(raw) {
  requireFact(Buffer.byteLength(raw) <= 8_000_000, 'resource source size');
  const response = JSON.parse(raw);
  requireFact(
    response !== null &&
      !Array.isArray(response) &&
      Object.keys(response).sort().join(',') === 'game,nodes,total' &&
      response.game === 'kingshot' &&
      Array.isArray(response.nodes) &&
      Number.isInteger(response.total) &&
      response.total === response.nodes.length &&
      response.total <= 25_000,
    'resource response must identify the complete Kingshot node list',
  );
  const keys = new Set();
  const positions = new Set();
  const nodes = response.nodes.map((node) => {
    requireFact(
      node !== null &&
        !Array.isArray(node) &&
        Object.keys(node).sort().join(',') === 'id,type,x,y' &&
        Number.isInteger(node.x) &&
        Number.isInteger(node.y) &&
        node.x >= 0 &&
        node.y >= 0 &&
        node.x + 2 <= SIDE &&
        node.y + 2 <= SIDE &&
        node.id === `r_${node.x}_${node.y}` &&
        RESOURCE_TYPES.has(node.type),
      'invalid resource identity, type, coordinate or footprint',
    );
    requireFact(!keys.has(node.id), 'duplicate resource identity');
    requireFact(!positions.has(`${node.x},${node.y}`), 'duplicate resource position');
    keys.add(node.id);
    positions.add(`${node.x},${node.y}`);
    return {
      key: node.id,
      resource_type: node.type,
      x: node.x,
      y: node.y,
      footprint: { width: 2, height: 2 },
    };
  });
  return nodes.sort((left, right) => left.y - right.y || left.x - right.x);
}

/** Preserve observed conflicts as diagnostics, not fabricated corrections or deletions. */
export function spatialSourceDiagnostics(terrain, nodes) {
  const occupied = new Uint16Array(CELLS);
  const diagnostics = [];
  const resourcePairs = new Set();
  nodes.forEach((node, index) => {
    const terrainKeys = new Set();
    for (let y = node.y; y < node.y + node.footprint.height; y += 1) {
      for (let x = node.x; x < node.x + node.footprint.width; x += 1) {
        const cell = y * SIDE + x;
        if (terrain.labels[cell]) terrainKeys.add(terrain.features[terrain.labels[cell] - 1].key);
        if (occupied[cell]) {
          const previous = nodes[occupied[cell] - 1];
          resourcePairs.add([previous.key, node.key].sort().join('|'));
        }
        occupied[cell] = index + 1;
      }
    }
    if (terrainKeys.size) {
      diagnostics.push({
        code: 'resource_terrain_overlap',
        resource_keys: [node.key],
        terrain_keys: [...terrainKeys].sort(),
      });
    }
  });
  for (const pair of [...resourcePairs].sort()) {
    diagnostics.push({
      code: 'resource_footprint_overlap',
      resource_keys: pair.split('|'),
      terrain_keys: [],
    });
  }
  return diagnostics;
}
