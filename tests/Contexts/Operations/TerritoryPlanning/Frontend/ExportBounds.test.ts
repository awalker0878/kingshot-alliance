import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
  buildSvg,
  pngDimensions,
} from '../../../../../resources/js/features/territory-planner/engine/export.ts';
import type {
  MapData,
  PlanAlliance,
  PlanObject,
} from '../../../../../resources/js/features/territory-planner/engine/types.ts';

const map: MapData = JSON.parse(
  readFileSync(
    new URL(
      '../../../../../resources/data/kingdom-maps/kingshot-evidence-backed-2026-09-06-v2.json',
      import.meta.url,
    ),
    'utf8',
  ),
);
const alliance: PlanAlliance = {
  key: 'alpha',
  alliance_id: null,
  external_name: 'Alpha',
  external_tag: null,
  display_name: 'Alpha',
  presentation_color: '#4da3ff',
  sort_order: 0,
  visible: true,
  locked: false,
};
const object: PlanObject = {
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
};
const metadata = {
  title: 'Planning & review <not markup>',
  mapProfile: 'Community-observed map',
  observedAt: '2026-09-06',
  confidence: 'community_observed',
  exportedAt: '2026-09-13T00:00:00Z',
};

test('translating the entire world preserves exported fixed structures, footprints and coverage exactly', () => {
  const original = buildSvg(map, [alliance], [object], metadata);
  const shifted = structuredClone(map);
  shifted.bounds.x += 117;
  shifted.bounds.y -= 83;
  for (const structure of shifted.structures) {
    structure.x += 117;
    structure.y -= 83;
  }
  assert.equal(
    buildSvg(shifted, [alliance], [{ ...object, x: object.x + 117, y: object.y - 83 }], metadata),
    original,
  );
  assert.match(original, /clip-path="url\(#territory-map-clip\)"/);
});

test('long legends are wrapped and the footer begins below every legend row', () => {
  const small = { ...map, bounds: { x: 0, y: 0, width: 100, height: 100 }, structures: [] };
  const alliances = Array.from({ length: 50 }, (_, index) => ({
    ...alliance,
    key: `layer-${index}`,
    display_name: `Long translated Alliance label ${index} — Gouverneurs et territoires`,
  }));
  const svg = buildSvg(small, alliances, [], metadata);
  const height = Number(svg.match(/height="([\d.]+)"/)![1]);
  const textRows = [...svg.matchAll(/<text x="([\d.]+)" y="([\d.]+)"[^>]*>([^<]*)<\/text>/g)];
  const legendYs = textRows.filter((row) => Number(row[1]) === 148).map((row) => Number(row[2]));
  const footerYs = textRows.filter((row) => Number(row[1]) === 20).map((row) => Number(row[2]));
  assert.ok(legendYs.length > 50);
  assert.ok(Math.min(...footerYs) > Math.max(...legendYs) + 20);
  assert.ok(Math.max(...footerYs) < height);
  assert.match(svg, /Planning &amp; review/);
  assert.doesNotMatch(svg, /<not markup>/);
});

test('unsafe colors, unsupported objects and non-finite geometry cannot become SVG attributes', () => {
  assert.throws(
    () =>
      buildSvg(map, [{ ...alliance, presentation_color: 'red" onload="alert(1)' }], [], metadata),
    /color/,
  );
  assert.throws(
    () =>
      buildSvg({ ...map, bounds: { ...map.bounds, width: Infinity } }, [alliance], [], metadata),
    /bounds/,
  );
  assert.throws(() => buildSvg(map, [alliance], [{ ...object, x: NaN }], metadata), /bounds/);
  assert.throws(
    () =>
      buildSvg(
        map,
        [alliance],
        [{ ...object, type: 'legacy_city' as PlanObject['type'] }],
        metadata,
      ),
    /Unsupported/,
  );
  assert.throws(
    () =>
      buildSvg(
        map,
        Array.from({ length: 51 }, () => alliance),
        [],
        metadata,
      ),
    /limits/,
  );
});

test('PNG size is validated before allocation with explicit dimensions and a bounded pixel count', () => {
  assert.deepEqual(pngDimensions(1800, 1460, 1400), { width: 1800, height: 1726 });
  for (const width of [0, -1, NaN, Infinity, 1.5, 8193])
    assert.throws(() => pngDimensions(width, 1200, 1200), RangeError);
  for (const dimensions of [
    [0, 100],
    [100, 0],
    [Infinity, 100],
    [100, NaN],
    [-100, 100],
  ]) {
    assert.throws(() => pngDimensions(1800, dimensions[0], dimensions[1]), RangeError);
  }
  assert.throws(() => pngDimensions(8192, 100, 100), /megapixel/);
  assert.throws(() => pngDimensions(1800, 1, 100), /megapixel/);
});

test('all SVG text, including the root accessible label, rejects invalid XML code points', () => {
  const svg = buildSvg(map, [alliance], [], { ...metadata, title: 'safe\u0000\ud800\ufffe<&' });
  assert.ok(!svg.includes('\u0000') && !svg.includes('\ud800') && !svg.includes('\ufffe'));
  assert.match(svg, /aria-label="safe {3}&lt;&amp;"/);
  assert.throws(
    () => buildSvg(map, [alliance], [], { ...metadata, title: 'x'.repeat(1025) }),
    /too long/,
  );
});
