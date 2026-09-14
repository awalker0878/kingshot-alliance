import type { MapData, PlanObject } from './types';

export type TerritoryCommandRefusal = {
  ok: false;
  reason: 'empty_selection' | 'locked_selection';
  blockedKeys: string[];
};
export type TerritoryCommandResult =
  | TerritoryCommandRefusal
  | { ok: true; objects: PlanObject[]; affectedKeys: string[] };

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
  if (!Number.isInteger(dx) || !Number.isInteger(dy))
    throw new TypeError('Territory translations must use integer tile deltas.');
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
  if (![pivot.x, pivot.y].every(Number.isFinite)) throw new TypeError('Invalid Territory rotation pivot.');
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
      const after = rotation === 90 || rotation === 270
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
    if (!object.key || keys.has(object.key)) throw new TypeError('Hive proposal keys must be unique.');
    keys.add(object.key);
    const metadata = { ...(object.metadata ?? {}) };
    if (object.type === 'governor_city' && metadata.slot_state === undefined)
      metadata.slot_state = 'open';
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

