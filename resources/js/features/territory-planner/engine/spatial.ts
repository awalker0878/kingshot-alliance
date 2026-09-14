import type { MapData, MapWorldRectangle } from './types';

type CollisionCode = 'terrain_collision' | 'resource_collision';
type Interval = [number, number];
type Rows = Map<number, Interval[]>;
const indexes = new WeakMap<MapData, Map<CollisionCode, Rows>>();

/** Immutable map identity owns this cache; presentation toggles never disable validation. */
function build(map: MapData): Map<CollisionCode, Rows> {
  const result = new Map<CollisionCode, Rows>();
  for (const layer of ['terrain', 'resources'] as const) {
    const declaration = map.resource_layers?.[layer];
    if (
      !declaration ||
      typeof declaration !== 'object' ||
      !('data_state' in declaration) ||
      declaration.data_state !== 'materialized' ||
      !('placement_blocking' in declaration) ||
      declaration.placement_blocking !== true
    )
      continue;
    const rows: Rows = new Map();
    function add(x: number, y: number, width: number): void {
      const row = rows.get(y) ?? [];
      row.push([x, x + width]);
      rows.set(y, row);
    }
    if (layer === 'terrain') {
      if (!Array.isArray(map.terrain_features))
        throw new Error('Materialized Kingdom map placement geometry has not been hydrated.');
      for (const feature of map.terrain_features) {
        for (const [x, y, width] of feature.spans) add(x, y, width);
      }
    } else {
      if (!Array.isArray(map.resource_nodes))
        throw new Error('Materialized Kingdom map placement geometry has not been hydrated.');
      for (const node of map.resource_nodes) {
        for (let y = node.y; y < node.y + node.footprint.height; y += 1)
          add(node.x, y, node.footprint.width);
      }
    }
    for (const [y, intervals] of rows) {
      intervals.sort((a, b) => a[0] - b[0] || a[1] - b[1]);
      const merged: Interval[] = [];
      for (const [start, end] of intervals) {
        const previous = merged.at(-1);
        if (previous && start <= previous[1]) previous[1] = Math.max(end, previous[1]);
        else merged.push([start, end]);
      }
      rows.set(y, merged);
    }
    result.set(layer === 'terrain' ? 'terrain_collision' : 'resource_collision', rows);
  }
  return result;
}

/** The queried shape is the rotated logical footprint, never the artwork's visual bounds. */
export function spatialCollisionCodes(map: MapData, footprint: MapWorldRectangle): CollisionCode[] {
  if (footprint.width <= 0 || footprint.height <= 0) return [];
  let index = indexes.get(map);
  if (!index) {
    index = build(map);
    indexes.set(map, index);
  }
  const first = Math.max(footprint.y, map.bounds.y);
  const last = Math.min(footprint.y + footprint.height, map.bounds.y + map.bounds.height);
  const codes: CollisionCode[] = [];
  for (const [code, rows] of index) {
    for (let y = first; y < last; y += 1) {
      const intervals = rows.get(y) ?? [];
      let low = 0;
      let high = intervals.length;
      while (low < high) {
        const middle = Math.floor((low + high) / 2);
        const candidate = intervals[middle]!;
        if (candidate[1] <= footprint.x) low = middle + 1;
        else high = middle;
      }
      const candidate = intervals[low];
      if (candidate && candidate[0] < footprint.x + footprint.width) {
        codes.push(code);
        break;
      }
    }
  }
  return codes;
}
