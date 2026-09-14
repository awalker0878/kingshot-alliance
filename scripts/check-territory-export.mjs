import assert from 'node:assert/strict';

import { buildSvg } from '../resources/js/features/territory-planner/engine/export.ts';

const map = {
  id: 'export-fixture',
  schema_version: 2,
  release_status: 'released',
  released_at: '2026-08-22',
  observed_at: '2026-08-22',
  game_version: null,
  season: null,
  title: 'Export fixture',
  confidence: 'verified_observation',
  coordinate_system: { name: 'xy', origin: 'south_west', tile_size: 1 },
  bounds: { x: 0, y: 0, width: 100, height: 100 },
  object_types: {
    headquarters: { footprint: { width: 3, height: 3 }, coverage: { width: 6, height: 6 } },
    banner: { footprint: { width: 1, height: 1 }, coverage: { width: 3, height: 3 } },
    governor_city: { footprint: { width: 2, height: 2 } },
    bear_trap: { footprint: { width: 3, height: 3 } },
  },
  zones: {},
  structures: [],
  placement_rules: [],
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
// Metadata wraps visually; assert its rendered reading order rather than one text node.
const renderedText = svg.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ');
assert.match(renderedText, /observed 2026-08-22/);
assert.match(svg, /community_observed/);
assert.match(
  renderedText,
  /coordinates are planning data, not an official Century Games map claim/,
);
assert.match(svg, /x="20"/);
assert.doesNotMatch(svg, /x="80" y="17"/);

console.log('Territory SVG source contract passed; browser PNG execution is a separate gate.');
