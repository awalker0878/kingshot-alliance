import type { MapData, PlanObject } from './types';

export type TerritoryCommandRefusal = {
  ok: false;
  reason: 'empty_selection' | 'locked_selection';
  blockedKeys: string[];
};
export type TerritoryCommandResult =
  TerritoryCommandRefusal | { ok: true; objects: PlanObject[]; affectedKeys: string[] };

export type TerritoryAlignment = 'left' | 'center_x' | 'right' | 'top' | 'center_y' | 'bottom';
export type TerritoryDistribution = 'horizontal' | 'vertical';

export function objectIsLocked(object: PlanObject, allianceLocked = false): boolean {
  return allianceLocked || object.metadata.locked === true;
}

export function requireAtomicEditableSelection(
  objects: PlanObject[],
  keys: string[],
  isEditable: (object: PlanObject) => boolean,
): { ok: true; selected: PlanObject[] } | TerritoryCommandRefusal {
  const keySet = new Set(keys);
  const selected = objects.filter((object) => keySet.has(object.key));
  if (!selected.length) return { ok: false, reason: 'empty_selection', blockedKeys: [] };
  const blockedKeys = selected.filter((object) => !isEditable(object)).map((object) => object.key);
  if (blockedKeys.length) return { ok: false, reason: 'locked_selection', blockedKeys };
  return { ok: true, selected };
}

export function translateObjectsAtomic(
  objects: PlanObject[],
  keys: string[],
  dx: number,
  dy: number,
  isEditable: (object: PlanObject) => boolean,
): TerritoryCommandResult {
  const selection = requireAtomicEditableSelection(objects, keys, isEditable);
  if (!selection.ok) return selection;
  if (!Number.isInteger(dx) || !Number.isInteger(dy)) {
    throw new TypeError('Territory translations must use integer tile deltas.');
  }
  const affected = new Set(selection.selected.map((object) => object.key));
  return {
    ok: true,
    affectedKeys: [...affected],
    objects: objects.map((object) =>
      affected.has(object.key) ? { ...object, x: object.x + dx, y: object.y + dy } : object,
    ),
  };
}

function footprint(map: MapData, object: PlanObject): { width: number; height: number } {
  const base = map.object_types[object.type].footprint;
  return object.rotation === 90 || object.rotation === 270
    ? { width: base.height, height: base.width }
    : base;
}

function bounds(map: MapData, object: PlanObject) {
  const size = footprint(map, object);
  return {
    left: object.x,
    top: object.y,
    right: object.x + size.width,
    bottom: object.y + size.height,
    centerX: object.x + size.width / 2,
    centerY: object.y + size.height / 2,
    width: size.width,
    height: size.height,
  };
}

export function selectionPivot(map: MapData, selected: PlanObject[]): { x: number; y: number } {
  if (!selected.length) throw new TypeError('A pivot requires at least one object.');
  let minX = Number.POSITIVE_INFINITY;
  let minY = Number.POSITIVE_INFINITY;
  let maxX = Number.NEGATIVE_INFINITY;
  let maxY = Number.NEGATIVE_INFINITY;
  for (const object of selected) {
    const size = footprint(map, object);
    minX = Math.min(minX, object.x);
    minY = Math.min(minY, object.y);
    maxX = Math.max(maxX, object.x + size.width);
    maxY = Math.max(maxY, object.y + size.height);
  }
  return { x: (minX + maxX) / 2, y: (minY + maxY) / 2 };
}

export function rotateObjectsAtomic(
  map: MapData,
  objects: PlanObject[],
  keys: string[],
  direction: 1 | -1,
  pivot: { x: number; y: number },
  isEditable: (object: PlanObject) => boolean,
): TerritoryCommandResult {
  const selection = requireAtomicEditableSelection(objects, keys, isEditable);
  if (!selection.ok) return selection;
  if (![pivot.x, pivot.y].every(Number.isFinite)) {
    throw new TypeError('Invalid Territory rotation pivot.');
  }
  const affected = new Set(selection.selected.map((object) => object.key));
  return {
    ok: true,
    affectedKeys: [...affected],
    objects: objects.map((object) => {
      if (!affected.has(object.key)) return object;
      const before = footprint(map, object);
      const centerX = object.x + before.width / 2;
      const centerY = object.y + before.height / 2;
      const relativeX = centerX - pivot.x;
      const relativeY = centerY - pivot.y;
      const rotatedCenterX = pivot.x + (direction === 1 ? relativeY : -relativeY);
      const rotatedCenterY = pivot.y + (direction === 1 ? -relativeX : relativeX);
      const rotation = (object.rotation + direction * 90 + 360) % 360;
      const base = map.object_types[object.type].footprint;
      const after =
        rotation === 90 || rotation === 270
          ? { width: base.height, height: base.width }
          : base;
      return {
        ...object,
        rotation,
        x: Math.round(rotatedCenterX - after.width / 2),
        y: Math.round(rotatedCenterY - after.height / 2),
      };
    }),
  };
}

/**
 * Aligns a complete editable selection to its own outer bounds. Alignment is footprint-aware and
 * atomic: one locked object refuses the whole operation. Half-tile centers are rounded so the
 * persisted coordinate contract remains integer-only.
 */
export function alignObjectsAtomic(
  map: MapData,
  objects: PlanObject[],
  keys: string[],
  alignment: TerritoryAlignment,
  isEditable: (object: PlanObject) => boolean,
): TerritoryCommandResult {
  const selection = requireAtomicEditableSelection(objects, keys, isEditable);
  if (!selection.ok) return selection;
  const selectedBounds = selection.selected.map((object) => bounds(map, object));
  const target = {
    left: Math.min(...selectedBounds.map((item) => item.left)),
    right: Math.max(...selectedBounds.map((item) => item.right)),
    top: Math.min(...selectedBounds.map((item) => item.top)),
    bottom: Math.max(...selectedBounds.map((item) => item.bottom)),
  };
  const centerX = (target.left + target.right) / 2;
  const centerY = (target.top + target.bottom) / 2;
  const affected = new Set(selection.selected.map((object) => object.key));

  return {
    ok: true,
    affectedKeys: [...affected],
    objects: objects.map((object) => {
      if (!affected.has(object.key)) return object;
      const box = bounds(map, object);
      switch (alignment) {
        case 'left':
          return { ...object, x: target.left };
        case 'center_x':
          return { ...object, x: Math.round(centerX - box.width / 2) };
        case 'right':
          return { ...object, x: target.right - box.width };
        case 'top':
          return { ...object, y: target.top };
        case 'center_y':
          return { ...object, y: Math.round(centerY - box.height / 2) };
        case 'bottom':
          return { ...object, y: target.bottom - box.height };
        default: {
          const neverAlignment: never = alignment;
          throw new TypeError(`Unsupported Territory alignment: ${String(neverAlignment)}`);
        }
      }
    }),
  };
}

/**
 * Evenly distributes object centres between the two outer selected centres. The first and last
 * object stay anchored, making the command deterministic and predictable for undo/redo history.
 */
export function distributeObjectsAtomic(
  map: MapData,
  objects: PlanObject[],
  keys: string[],
  direction: TerritoryDistribution,
  isEditable: (object: PlanObject) => boolean,
): TerritoryCommandResult {
  const selection = requireAtomicEditableSelection(objects, keys, isEditable);
  if (!selection.ok) return selection;
  const axis = direction === 'horizontal' ? 'centerX' : 'centerY';
  const selected = [...selection.selected].sort(
    (a, b) => bounds(map, a)[axis] - bounds(map, b)[axis],
  );
  const affected = new Set(selected.map((object) => object.key));
  if (selected.length < 3) {
    return { ok: true, affectedKeys: [...affected], objects: [...objects] };
  }

  const first = bounds(map, selected[0]!)[axis];
  const last = bounds(map, selected[selected.length - 1]!)[axis];
  const step = (last - first) / (selected.length - 1);
  const coordinateByKey = new Map<string, number>();
  selected.forEach((object, index) => coordinateByKey.set(object.key, first + step * index));

  return {
    ok: true,
    affectedKeys: [...affected],
    objects: objects.map((object) => {
      const coordinate = coordinateByKey.get(object.key);
      if (coordinate === undefined) return object;
      const box = bounds(map, object);
      return direction === 'horizontal'
        ? { ...object, x: Math.round(coordinate - box.width / 2) }
        : { ...object, y: Math.round(coordinate - box.height / 2) };
    }),
  };
}

/** Applies an explicit bulk coordinate table as one atomic command. */
export function setObjectCoordinatesAtomic(
  objects: PlanObject[],
  coordinates: ReadonlyArray<{ key: string; x: number; y: number }>,
  isEditable: (object: PlanObject) => boolean,
): TerritoryCommandResult {
  const duplicateKeys = new Set<string>();
  const seen = new Set<string>();
  for (const row of coordinates) {
    if (!Number.isInteger(row.x) || !Number.isInteger(row.y)) {
      throw new TypeError('Territory bulk coordinates must use integer tile coordinates.');
    }
    if (seen.has(row.key)) duplicateKeys.add(row.key);
    seen.add(row.key);
  }
  if (duplicateKeys.size) {
    throw new TypeError(
      `Territory bulk coordinates contain duplicate keys: ${[...duplicateKeys].join(', ')}`,
    );
  }

  const selection = requireAtomicEditableSelection(
    objects,
    coordinates.map((row) => row.key),
    isEditable,
  );
  if (!selection.ok) return selection;
  if (selection.selected.length !== coordinates.length) {
    throw new TypeError('Territory bulk coordinates reference an unknown object key.');
  }

  const byKey = new Map(coordinates.map((row) => [row.key, row]));
  return {
    ok: true,
    affectedKeys: coordinates.map((row) => row.key),
    objects: objects.map((object) => {
      const row = byKey.get(object.key);
      return row ? { ...object, x: row.x, y: row.y } : object;
    }),
  };
}

export function assignCanonicalGovernorIdentity(object: PlanObject, playerId: string): PlanObject {
  if (object.type !== 'governor_city') throw new TypeError('Only Governor cities can be assigned.');
  const metadata = { ...object.metadata };
  delete metadata.external_identity_key;
  metadata.slot_state = playerId ? 'assigned' : object.external_player_name ? 'assigned' : 'open';
  return {
    ...object,
    player_id: playerId || null,
    external_player_name: playerId ? null : object.external_player_name,
    metadata,
  };
}

export function assignExternalGovernorIdentity(object: PlanObject, name: string): PlanObject {
  if (object.type !== 'governor_city') throw new TypeError('Only Governor cities can be assigned.');
  const normalized = name.trim();
  const metadata = { ...object.metadata };
  if (normalized) {
    metadata.external_identity_key =
      typeof metadata.external_identity_key === 'string' && metadata.external_identity_key.trim()
        ? metadata.external_identity_key
        : `external-${object.key}`;
    metadata.slot_state = 'assigned';
  } else {
    delete metadata.external_identity_key;
    metadata.slot_state = 'open';
  }
  return {
    ...object,
    player_id: null,
    external_player_name: normalized || null,
    metadata,
  };
}

export function materializeHiveProposal(
  proposal: PlanObject[],
  existingObjectCount: number,
): PlanObject[] {
  const keys = new Set<string>();
  return proposal.map((object, index) => {
    if (!object.key || keys.has(object.key)) {
      throw new TypeError('Hive proposal keys must be unique.');
    }
    keys.add(object.key);
    const metadata = { ...(object.metadata ?? {}) };
    if (object.type === 'governor_city' && metadata.slot_state === undefined) {
      metadata.slot_state = 'open';
    }
    return {
      ...object,
      rotation: object.rotation ?? 0,
      player_id: object.player_id ?? null,
      external_player_name: object.external_player_name ?? null,
      label: object.label ?? null,
      group_key: object.group_key ?? null,
      sort_order: existingObjectCount + index,
      metadata,
    };
  });
}
