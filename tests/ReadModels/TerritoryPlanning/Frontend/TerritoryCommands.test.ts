import assert from 'node:assert/strict';
import test from 'node:test';
import {
  assignCanonicalGovernorIdentity,
  assignExternalGovernorIdentity,
  materializeHiveProposal,
  objectIsLocked,
  rotateObjectsAtomic,
  selectionPivot,
  translateObjectsAtomic,
} from '../../../../resources/js/features/territory-planner/engine/commands.ts';
import type { MapData, PlanObject } from '../../../../resources/js/features/territory-planner/engine/types.ts';

const map = {
  object_types: {
    headquarters: { footprint: { width: 3, height: 3 } },
    banner: { footprint: { width: 1, height: 1 } },
    governor_city: { footprint: { width: 2, height: 2 } },
    bear_trap: { footprint: { width: 3, height: 3 } },
  },
} as MapData;
function object(
  key: string,
  x: number,
  y: number,
  type: PlanObject['type'] = 'governor_city',
  metadata: Record<string, unknown> = {},
): PlanObject {
  return {
    key, alliance_key: 'a', group_key: 'g', type, player_id: null, external_player_name: null,
    label: null, x, y, rotation: 0, sort_order: 0, metadata,
  };
}
const editable = (candidate: PlanObject) => !objectIsLocked(candidate, false);

test('mixed locked selections refuse atomically instead of moving an editable subset', () => {
  const a = object('a', 10, 10);
  const b = { ...object('b', 20, 20), metadata: { locked: true } };
  const result = translateObjectsAtomic([a, b], ['a', 'b'], 1, 0, editable);
  assert.deepEqual(result, { ok: false, reason: 'locked_selection', blockedKeys: ['b'] });
});

test('translations require integer tile deltas', () => {
  assert.throws(() => translateObjectsAtomic([object('a', 1, 1)], ['a'], 0.5, 0, editable));
});

test('group rotation transforms member positions around the declared pivot', () => {
  const objects = [object('a', 0, 0), object('b', 4, 0)];
  const pivot = selectionPivot(map, objects);
  assert.deepEqual(pivot, { x: 3, y: 1 });
  const result = rotateObjectsAtomic(map, objects, ['a', 'b'], 1, pivot, editable);
  assert.equal(result.ok, true);
  if (!result.ok) return;
  assert.deepEqual(result.objects.map(({ key, x, y, rotation }) => ({ key, x, y, rotation })), [
    { key: 'a', x: 2, y: 2, rotation: 90 },
    { key: 'b', x: 2, y: -2, rotation: 90 },
  ]);
});

test('rectangular footprint uses its post-rotation dimensions and remains integer snapped', () => {
  const rectangularMap = structuredClone(map);
  rectangularMap.object_types.governor_city.footprint = { width: 2, height: 3 };
  const source = object('a', 5, 7);
  const result = rotateObjectsAtomic(rectangularMap, [source], ['a'], 1, { x: 6, y: 8.5 }, editable);
  assert.equal(result.ok, true);
  if (!result.ok) return;
  assert.deepEqual(result.objects[0], { ...source, x: 5, y: 8, rotation: 90 });
  assert.equal(Number.isInteger(result.objects[0]!.x) && Number.isInteger(result.objects[0]!.y), true);
});

test('Governor identity transitions preserve slot-state invariants', () => {
  const city = object('city', 10, 10, 'governor_city', { slot_state: 'open' });
  const canonical = assignCanonicalGovernorIdentity(city, '01PLAYER');
  assert.equal(canonical.player_id, '01PLAYER');
  assert.equal(canonical.external_player_name, null);
  assert.equal(canonical.metadata.slot_state, 'assigned');
  assert.equal(canonical.metadata.external_identity_key, undefined);

  const external = assignExternalGovernorIdentity(city, 'North Star');
  assert.equal(external.player_id, null);
  assert.equal(external.external_player_name, 'North Star');
  assert.equal(external.metadata.slot_state, 'assigned');
  assert.equal(external.metadata.external_identity_key, 'external-city');

  const cleared = assignExternalGovernorIdentity(external, '  ');
  assert.equal(cleared.external_player_name, null);
  assert.equal(cleared.metadata.slot_state, 'open');
  assert.equal(cleared.metadata.external_identity_key, undefined);
});

test('hive proposal materialization preserves deterministic keys, groups and slot metadata', () => {
  const proposal = [
    { ...object('hive-a-city-1', 10, 10, 'governor_city', { slot_state: 'open' }), group_key: 'hive-a' },
    { ...object('hive-a-city-2', 13, 10, 'governor_city', { slot_state: 'open' }), group_key: 'hive-a' },
  ];
  const installed = materializeHiveProposal(proposal, 7);
  assert.deepEqual(installed.map((item) => item.key), ['hive-a-city-1', 'hive-a-city-2']);
  assert.deepEqual(installed.map((item) => item.group_key), ['hive-a', 'hive-a']);
  assert.deepEqual(installed.map((item) => item.sort_order), [7, 8]);
  assert.equal(installed[0]?.metadata.slot_state, 'open');
  assert.throws(() => materializeHiveProposal([proposal[0]!, proposal[0]!], 0), /unique/);
});

