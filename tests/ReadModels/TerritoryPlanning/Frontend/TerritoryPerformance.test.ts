import assert from 'node:assert/strict';
import { performance } from 'node:perf_hooks';
import test from 'node:test';

import {
  MAX_EXPORT_DIMENSION,
  MAX_EXPORT_PIXELS,
  estimateExportSize,
} from '../../../../resources/js/features/territory-planner/engine/export.ts';
import { buildPresentation } from '../../../../resources/js/features/territory-planner/engine/presentation.ts';
import { buildTerritoryScene } from '../../../../resources/js/features/territory-planner/engine/scene.ts';
import { sceneEntitiesInBounds } from '../../../../resources/js/features/territory-planner/engine/scene-index.ts';
import type {
  MapData,
  PlanAlliance,
  PlanObject,
} from '../../../../resources/js/features/territory-planner/engine/types.ts';

const RESOURCE_COUNT = 6500;
const PLAN_OBJECT_COUNT = 5000;
const MAX_SCENE_BUILD_MS = 2500;
const MAX_POINTER_QUERY_P95_MS = 20;
const MAX_FRAME_BUILD_P95_MS = 120;
const MAX_HEAP_DELTA_BYTES = 256 * 1024 * 1024;

function percentile(samples: number[], fraction: number): number {
  const sorted = [...samples].sort((a, b) => a - b);
  return sorted[Math.min(sorted.length - 1, Math.floor(sorted.length * fraction))] ?? 0;
}

function denseFixture(): { map: MapData; alliances: PlanAlliance[]; objects: PlanObject[] } {
  const map: MapData = {
    id: 'km17-dense-reference',
    schema_version: 2,
    release_status: 'released',
    released_at: '2026-09-14',
    observed_at: '2026-09-14',
    game_version: null,
    season: null,
    title: 'KM-17 dense reference scene',
    confidence: 'reviewed',
    coordinate_system: { name: 'km17-grid', origin: 'southwest', tile_size: 1 },
    bounds: { x: 0, y: 0, width: 1600, height: 1600 },
    object_types: {
      headquarters: {
        footprint: { width: 3, height: 3 },
        coverage: { width: 15, height: 15 },
      },
      banner: { footprint: { width: 1, height: 1 }, coverage: { width: 7, height: 7 } },
      governor_city: { footprint: { width: 2, height: 2 } },
      bear_trap: { footprint: { width: 3, height: 3 } },
    },
    zones: { plains: { x: 0, y: 0, width: 1600, height: 1600, blocked_types: [] } },
    structures: [],
    placement_rules: [],
    facilities: [],
    terrain_features: [],
    resource_nodes: Array.from({ length: RESOURCE_COUNT }, (_, index) => ({
      key: `resource-${index}`,
      resource_type: ['bread', 'wood', 'stone', 'iron'][index % 4]!,
      x: (index * 17) % 1590,
      y: (Math.floor(index / 94) * 23) % 1590,
      footprint: { width: 1, height: 1 },
    })),
  };
  const alliances: PlanAlliance[] = [
    {
      key: 'alpha',
      alliance_id: null,
      external_name: 'Alpha',
      external_tag: 'A',
      display_name: 'Alpha',
      presentation_color: '#123456',
      sort_order: 0,
      visible: true,
      locked: false,
    },
  ];
  const objects: PlanObject[] = Array.from({ length: PLAN_OBJECT_COUNT }, (_, index) => ({
    key: `city-${index}`,
    alliance_key: 'alpha',
    group_key: null,
    type: 'governor_city' as const,
    player_id: null,
    external_player_name: `Governor ${index}`,
    label: null,
    x: (index * 29) % 1590,
    y: (Math.floor(index / 77) * 31) % 1590,
    rotation: index % 2 === 0 ? 0 : 90,
    sort_order: index,
    metadata: { slot_state: 'assigned' },
  }));
  return { map, alliances, objects };
}

test('KM-17 dense reference scene records bounded build, pointer, frame and memory evidence', () => {
  const fixture = denseFixture();
  const heapBefore = process.memoryUsage().heapUsed;
  const buildStarted = performance.now();
  const scene = buildTerritoryScene({
    map: fixture.map,
    mapChecksum: 'a'.repeat(64),
    alliances: fixture.alliances,
    objects: fixture.objects,
  });
  const sceneBuildMs = performance.now() - buildStarted;
  const heapDeltaBytes = Math.max(0, process.memoryUsage().heapUsed - heapBefore);

  assert.equal(scene.entities.length, RESOURCE_COUNT + PLAN_OBJECT_COUNT);
  assert.ok(sceneBuildMs <= MAX_SCENE_BUILD_MS, `scene build ${sceneBuildMs.toFixed(2)} ms`);
  assert.ok(
    heapDeltaBytes <= MAX_HEAP_DELTA_BYTES,
    `heap delta ${(heapDeltaBytes / 1024 / 1024).toFixed(1)} MiB`,
  );

  // Warm the spatial index once; pointer evidence measures steady-state hit-region queries.
  sceneEntitiesInBounds(scene, { x: 0, y: 0, width: 32, height: 32 });
  const pointerSamples: number[] = [];
  for (let index = 0; index < 250; index++) {
    const x = (index * 37) % 1550;
    const y = (index * 53) % 1550;
    const started = performance.now();
    sceneEntitiesInBounds(scene, { x, y, width: 12, height: 12 });
    pointerSamples.push(performance.now() - started);
  }
  const pointerP95Ms = percentile(pointerSamples, 0.95);
  assert.ok(
    pointerP95Ms <= MAX_POINTER_QUERY_P95_MS,
    `pointer query p95 ${pointerP95Ms.toFixed(2)} ms`,
  );

  const deviceProfiles = [
    { name: 'desktop-1440x1000', width: 1440, height: 1000, zoom: 2 },
    { name: 'tablet-1024x768', width: 1024, height: 768, zoom: 1.4 },
    { name: 'mobile-390x844', width: 390, height: 844, zoom: 0.8 },
  ] as const;
  const frameEvidence: Record<string, { p95_ms: number; max_commands: number }> = {};
  for (const profile of deviceProfiles) {
    const frameSamples: number[] = [];
    let maxCommands = 0;
    for (let index = 0; index < 24; index++) {
      const started = performance.now();
      const commands = buildPresentation(scene, {
        x: 300 + index * 31,
        y: 300 + index * 29,
        width: profile.width,
        height: profile.height,
        zoom: profile.zoom,
      });
      frameSamples.push(performance.now() - started);
      maxCommands = Math.max(maxCommands, commands.length);
    }
    const p95 = percentile(frameSamples, 0.95);
    assert.ok(p95 <= MAX_FRAME_BUILD_P95_MS, `${profile.name} frame p95 ${p95.toFixed(2)} ms`);
    assert.ok(maxCommands < scene.entities.length, `${profile.name} must cull the dense scene`);
    frameEvidence[profile.name] = { p95_ms: Number(p95.toFixed(3)), max_commands: maxCommands };
  }

  const evidence = {
    scene_entities: scene.entities.length,
    scene_build_ms: Number(sceneBuildMs.toFixed(3)),
    pointer_query_p95_ms: Number(pointerP95Ms.toFixed(3)),
    heap_delta_bytes: heapDeltaBytes,
    device_profiles: frameEvidence,
    budgets: {
      scene_build_ms: MAX_SCENE_BUILD_MS,
      pointer_query_p95_ms: MAX_POINTER_QUERY_P95_MS,
      frame_build_p95_ms: MAX_FRAME_BUILD_P95_MS,
      heap_delta_bytes: MAX_HEAP_DELTA_BYTES,
    },
  };
  console.info(`KM17_EVIDENCE ${JSON.stringify(evidence)}`);
});

test('KM-17 export allocation limits fail closed at explicit pixel and dimension boundaries', () => {
  assert.deepEqual(estimateExportSize({ x: 0, y: 0, width: 4096, height: 4096 }), {
    width: 4096,
    height: 4096,
    pixels: MAX_EXPORT_PIXELS,
  });
  assert.throws(
    () => estimateExportSize({ x: 0, y: 0, width: MAX_EXPORT_DIMENSION + 1, height: 1 }),
    /dimension/,
  );
  assert.throws(
    () => estimateExportSize({ x: 0, y: 0, width: 4097, height: 4096 }),
    /allocation/,
  );
});
