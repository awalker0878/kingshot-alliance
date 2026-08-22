import assert from 'node:assert/strict';

import { buildSvg } from '../resources/js/features/territory-planner/engine/export.ts';

const map = {
  id: 'export-fixture',
  schema_version: 1,
  observed_at: '2026-08-22',
  source_label: 'Export fixture',
  source_uri: null,
  confidence: 'verified_observation',
  coordinate_system: { name: 'xy', origin: 'south_west', tile_size: 1 },
  bounds: { x: 0, y: 0, width: 100, height: 100 },
  object_types: {
    headquarters: { size: 3, coverage: 6 },
    banner: { size: 1, coverage: 3 },
    governor_city: { size: 2, coverage: 0 },
    bear_trap: { size: 3, coverage: 0 },
  },
  zones: {},
  structures: [],
};
const alliances = [
  {
    key: 'alpha',
    alliance_id: null,
    external_name: 'A & B',
    external_tag: null,
    display_name: 'A & B <Guard>',
    presentation_color: '#4da3ff',
    sort_order: 0,
    visible: true,
    locked: false,
  },
  {
    key: 'hidden',
    alliance_id: null,
    external_name: 'Hidden',
    external_tag: null,
    display_name: 'Hidden',
    presentation_color: '#ff0000',
    sort_order: 1,
    visible: false,
    locked: false,
  },
];
const objects = [
  {
    key: 'hq',
    alliance_key: 'alpha',
    group_key: null,
    type: 'headquarters',
    player_id: null,
    external_player_name: null,
    label: 'HQ',
    x: 20,
    y: 20,
    rotation: 0,
    sort_order: 0,
    metadata: {},
  },
  {
    key: 'hidden-hq',
    alliance_key: 'hidden',
    group_key: null,
    type: 'headquarters',
    player_id: null,
    external_player_name: null,
    label: 'Hidden HQ',
    x: 80,
    y: 80,
    rotation: 0,
    sort_order: 1,
    metadata: {},
  },
];
const svg = buildSvg(map, alliances, objects, {
  title: 'Plan <Alpha> & "Bravo"',
  mapProfile: 'Observed & reviewed',
  observedAt: '2026-08-22',
  confidence: 'community_observed',
  exportedAt: '2026-08-22T12:00:00Z',
});

assert.match(svg, /^<svg /);
assert.match(svg, /role="img"/);
assert.match(svg, /Plan &lt;Alpha&gt; &amp; &quot;Bravo&quot;/);
assert.match(svg, /A &amp; B &lt;Guard&gt;/);
assert.match(svg, /Map: Observed &amp; reviewed/);
assert.match(svg, /observed 2026-08-22/);
assert.match(svg, /community_observed/);
assert.match(svg, /coordinates are planning data, not an official Century Games map claim/);
assert.match(svg, /x="20"/);
assert.doesNotMatch(svg, /x="80" y="17"/);

console.log('Territory SVG/PNG source contract passed.');
