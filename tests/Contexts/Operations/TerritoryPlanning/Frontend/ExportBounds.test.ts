import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
  EXPORT_FONT_FAMILY,
  MAX_EXPORT_DIMENSION,
  buildSvg,
  estimateExportSize,
  exportScopeBounds,
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
  for (const zone of Object.values(shifted.zones)) {
    zone.x += 117;
    zone.y -= 83;
  }
  for (const facility of shifted.facilities ?? []) {
    facility.x += 117;
    facility.y -= 83;
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

test('a bounded sub-region export projects geometry relative to the requested region, not the world origin', () => {
  const region = { x: 480, y: 460, width: 260, height: 220 };
  const inset = { ...object, x: 500, y: 480 };
  const original = buildSvg(map, [alliance], [inset], metadata, { bounds: region });

  // The object sits 20 tiles into the region on both axes, so the region origin — not (0, 0) — is
  // what the projection subtracts. Screen y is flipped, so it is measured from the region's bottom.
  assert.match(original, /<rect x="20" y="197" width="3" height="3"/);
  assert.match(original, /<defs><clipPath id="territory-map-clip"><rect width="260" height="220"\/>/);
  assert.match(original, /width="520"/);

  const shiftX = 7000;
  const shiftY = 5000;
  const shifted = structuredClone(map);
  shifted.bounds.x += shiftX;
  shifted.bounds.y += shiftY;
  for (const structure of shifted.structures) {
    structure.x += shiftX;
    structure.y += shiftY;
  }
  for (const zone of Object.values(shifted.zones)) {
    zone.x += shiftX;
    zone.y += shiftY;
  }
  assert.equal(
    buildSvg(
      shifted,
      [alliance],
      [{ ...inset, x: inset.x + shiftX, y: inset.y + shiftY }],
      metadata,
      { bounds: { ...region, x: region.x + shiftX, y: region.y + shiftY } },
    ),
    original,
  );
});

test('export scopes resolve to bounded world regions that never leave the released map', () => {
  const objects: PlanObject[] = [
    { ...object, key: 'a', x: 100, y: 100 },
    { ...object, key: 'b', x: 300, y: 220 },
    { ...object, key: 'c', alliance_key: 'beta', x: 700, y: 640 },
  ];
  assert.deepEqual(exportScopeBounds({ scope: 'map', map, objects }), map.bounds);
  assert.deepEqual(exportScopeBounds({ scope: 'selection', map, objects }), map.bounds);
  assert.deepEqual(exportScopeBounds({ scope: 'selection', map, objects, selectedKeys: ['a'] }), {
    x: 98,
    y: 98,
    width: 7,
    height: 7,
  });
  assert.deepEqual(
    exportScopeBounds({ scope: 'selection', map, objects, selectedKeys: ['a', 'b'] }),
    { x: 98, y: 98, width: 207, height: 127 },
  );
  assert.deepEqual(
    exportScopeBounds({ scope: 'alliance', map, objects, activeAllianceKey: 'alpha' }),
    { x: 98, y: 98, width: 207, height: 127 },
  );
  assert.deepEqual(exportScopeBounds({ scope: 'alliance', map, objects, activeAllianceKey: null }), {
    x: 98,
    y: 98,
    width: 607,
    height: 547,
  });
  assert.deepEqual(
    exportScopeBounds({
      scope: 'viewport',
      map,
      objects,
      viewport: { x: 600, y: 600, width: 400, height: 300, zoom: 2 },
    }),
    { x: 498, y: 523, width: 204, height: 154 },
  );
  // An unprojected viewport scope has no region to offer, so the whole released map is exported.
  assert.deepEqual(exportScopeBounds({ scope: 'viewport', map, objects }), map.bounds);
  // Padding is clamped so a scope at the world edge can never widen the authorized map.
  assert.deepEqual(
    exportScopeBounds({
      scope: 'selection',
      map,
      objects: [{ ...object, key: 'corner', x: 0, y: 0 }],
      selectedKeys: ['corner'],
    }),
    { x: 0, y: 0, width: 5, height: 5 },
  );
  assert.throws(
    () =>
      exportScopeBounds({
        scope: 'viewport',
        map,
        objects,
        viewport: { x: 600, y: 600, width: 400, height: 300, zoom: 0 },
      }),
    /viewport/,
  );
  assert.throws(
    () =>
      exportScopeBounds({
        scope: 'selection',
        map,
        objects: [{ ...object, type: 'legacy_city' as PlanObject['type'] }],
        selectedKeys: [object.key],
      }),
    /Unsupported/,
  );
});

test('export sizes are estimated deterministically and rejected before allocation', () => {
  assert.deepEqual(estimateExportSize({ x: 0, y: 0, width: 200, height: 150 }), {
    width: 200,
    height: 150,
    pixels: 30_000,
  });
  assert.deepEqual(estimateExportSize({ x: 0, y: 0, width: 200, height: 150 }, 2), {
    width: 400,
    height: 300,
    pixels: 120_000,
  });
  for (const scale of [0, -1, NaN, Infinity])
    assert.throws(() => estimateExportSize({ x: 0, y: 0, width: 200, height: 150 }, scale), RangeError);
  for (const bounds of [
    { x: 0, y: 0, width: 0, height: 150 },
    { x: 0, y: 0, width: 200, height: Infinity },
  ])
    assert.throws(() => estimateExportSize(bounds), RangeError);
  assert.throws(
    () => estimateExportSize({ x: 0, y: 0, width: MAX_EXPORT_DIMENSION + 1, height: 10 }),
    /dimension/,
  );
  assert.throws(() => estimateExportSize({ x: 0, y: 0, width: 5000, height: 5000 }), /allocation/);
});

test('export metadata records locale, font and artwork provenance so renditions stay reproducible', () => {
  const svg = buildSvg(map, [alliance], [object], {
    ...metadata,
    locale: 'fr',
    fontFamily: EXPORT_FONT_FAMILY,
    artworkVersion: '1.0.0',
  });
  assert.match(svg, /Locale fr · Font sans-serif/);
  assert.match(svg, /Artwork 1\.0\.0/);
  assert.equal([...svg.matchAll(/font-family="sans-serif"/g)].length, [...svg.matchAll(/font-family=/g)].length);
  assert.match(buildSvg(map, [alliance], [], metadata), /Locale en · Font sans-serif/);
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
