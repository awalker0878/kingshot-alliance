import assert from 'node:assert/strict';
import test from 'node:test';

import {
  buildTerritoryScene,
  rotatedFootprint,
  sceneEntitiesForQuery,
} from '../../../../resources/js/features/territory-planner/engine/scene.ts';
import type {
  MapData,
  PlanAlliance,
  PlanObject,
} from '../../../../resources/js/features/territory-planner/engine/types.ts';

const map: MapData = {
  id: 'scene-test',
  schema_version: 2,
  release_status: 'released',
  released_at: '2026-09-13',
  observed_at: '2026-09-13',
  game_version: null,
  season: null,
  title: 'Scene',
  confidence: 'reviewed',
  coordinate_system: { name: 'test', origin: 'southwest', tile_size: 1 },
  bounds: { x: 100, y: 200, width: 100, height: 100 },
  object_types: {
    headquarters: { footprint: { width: 3, height: 3 }, coverage: { width: 15, height: 15 } },
    banner: { footprint: { width: 1, height: 1 }, coverage: { width: 7, height: 7 } },
    governor_city: { footprint: { width: 2, height: 3 } },
    bear_trap: { footprint: { width: 3, height: 3 } },
  },
  zones: { plains: { x: 100, y: 200, width: 100, height: 100, blocked_types: [] } },
  structures: [
    {
      key: 'fortress_1',
      name: 'Fortress 1',
      category: 'fortress',
      x: 120,
      y: 220,
      footprint: { width: 6, height: 6 },
      exclusion_tiles: 60,
      city_exempt: true,
      provenance: ['map'],
    },
  ],
  placement_rules: [],
  facilities: [
    {
      key: 'facility-fortress',
      name: 'Fortress Alpha',
      category: 'fortress',
      x: 120,
      y: 220,
      confidence: 'reviewed',
      provenance: ['facility'],
    },
    {
      key: 'outpost',
      name: 'Builders Guild',
      category: 'outpost',
      level: 1,
      x: 130,
      y: 230,
      confidence: 'reviewed',
      provenance: ['facility'],
    },
  ],
  terrain_features: [
    {
      key: 'lake-1',
      family: 'lake',
      bounds: { x: 140, y: 240, width: 2, height: 2 },
      centroid: { x: 141, y: 241 },
      cell_count: 4,
      spans: [
        [140, 240, 2],
        [140, 241, 2],
      ],
    },
  ],
  resource_nodes: [
    { key: 'bread-1', resource_type: 'bread', x: 150, y: 250, footprint: { width: 1, height: 1 } },
  ],
};
const alliances: PlanAlliance[] = [
  {
    key: 'a',
    alliance_id: null,
    external_name: 'A',
    external_tag: null,
    display_name: 'Alliance A',
    presentation_color: '#123456',
    sort_order: 0,
    visible: true,
    locked: false,
  },
];
const objects: PlanObject[] = [
  {
    key: 'city',
    alliance_key: 'a',
    group_key: null,
    type: 'governor_city',
    player_id: null,
    external_player_name: 'Governor',
    label: null,
    x: 160,
    y: 260,
    rotation: 90,
    sort_order: 0,
    metadata: {},
  },
];

test('scene joins structural facility identity and preserves unknown outpost marker semantics', () => {
  const scene = buildTerritoryScene({ map, mapChecksum: 'a'.repeat(64), alliances, objects });
  const fortress = scene.entities.filter(
    (entity) => entity.metadata.facility_key === 'facility-fortress',
  );
  assert.equal(fortress.length, 1);
  assert.deepEqual(fortress[0]?.provenance, ['map', 'facility']);
  const outpost = scene.entities.find((entity) => entity.sourceKey === 'outpost');
  assert.deepEqual(outpost?.bounds, { x: 130, y: 230, width: 1, height: 1 });
  assert.equal(outpost?.metadata.reference_marker, true);
});

test('scene carries terrain, resources and rotated planned geometry without altering map truth', () => {
  const scene = buildTerritoryScene({ map, mapChecksum: 'b'.repeat(64), alliances, objects });
  assert.equal(
    scene.entities.some((entity) => entity.key === 'terrain:lake-1'),
    true,
  );
  assert.equal(
    scene.entities.some((entity) => entity.key === 'resource:bread-1'),
    true,
  );
  assert.deepEqual(scene.entities.find((entity) => entity.key === 'planned:city')?.bounds, {
    x: 160,
    y: 260,
    width: 3,
    height: 2,
  });
  assert.deepEqual(rotatedFootprint(map, 'governor_city', 270), { width: 3, height: 2 });
});

test('semantic scene query is bounded and searches labels, stable keys, layers and assets', () => {
  const scene = buildTerritoryScene({ map, mapChecksum: 'c'.repeat(64), alliances, objects });
  assert.equal(
    sceneEntitiesForQuery(scene, 'builders')
      .map((entity) => entity.sourceKey)
      .join(','),
    'outpost',
  );
  assert.equal(
    sceneEntitiesForQuery(scene, 'resource.bread')
      .map((entity) => entity.sourceKey)
      .join(','),
    'bread-1',
  );
  assert.ok(sceneEntitiesForQuery(scene, '', 2).length <= 2);
});

test('observed reality is projected as a distinct shared-scene layer without changing plan authority', () => {
  const planned: PlanObject = { ...objects[0]!, key: 'planned-city', x: 160, y: 260, rotation: 0 };
  const scene = buildTerritoryScene({
    map,
    mapChecksum: 'f'.repeat(64),
    alliances,
    objects: [planned],
    observedObjects: [{
      key: 'observed-city',
      type: 'governor_city',
      x: 163,
      y: 261,
      identity_state: 'resolved_player',
      confidence: 0.9,
    }],
  });
  const observed = scene.entities.find((entity) => entity.key === 'observed:observed-city');
  assert.equal(observed?.kind, 'observed');
  assert.equal(observed?.layer, 'observed');
  assert.deepEqual(observed?.bounds, { x: 163, y: 261, width: 2, height: 3 });
  assert.equal(observed?.metadata.identity_state, 'resolved_player');
  assert.equal(scene.entities.find((entity) => entity.key === 'planned:planned-city')?.kind, 'planned');
});
