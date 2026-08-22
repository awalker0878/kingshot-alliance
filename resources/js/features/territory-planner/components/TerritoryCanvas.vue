<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import type { MapData, PlanAlliance, PlanObject, TerritoryObjectType } from '../engine/types';

type Tool = 'select' | 'pan' | 'place';

const props = withDefaults(
  defineProps<{
    map: MapData;
    alliances: PlanAlliance[];
    objects: PlanObject[];
    selectedKeys: string[];
    tool: Tool;
    placementType: TerritoryObjectType;
    activeAllianceKey: string | null;
    label: string;
    readOnly?: boolean;
    showCoverage?: boolean;
    showStructures?: boolean;
    showZones?: boolean;
  }>(),
  { readOnly: false, showCoverage: true, showStructures: true, showZones: true },
);

const emit = defineEmits<{
  (event: 'update:selectedKeys', value: string[]): void;
  (event: 'move', value: { keys: string[]; dx: number; dy: number }): void;
  (event: 'place', value: { x: number; y: number }): void;
}>();

const canvas = ref<HTMLCanvasElement | null>(null);
const host = ref<HTMLDivElement | null>(null);
const cameraX = ref(props.map.bounds.x + props.map.bounds.width / 2);
const cameraY = ref(props.map.bounds.y + props.map.bounds.height / 2);
const zoom = ref(0.6);
const width = ref(900);
const height = ref(650);
const drag = ref<null | {
  kind: 'pan' | 'object' | 'box';
  startX: number;
  startY: number;
  worldX: number;
  worldY: number;
  lastWorldX: number;
  lastWorldY: number;
}>(null);
const boxEnd = ref<{ x: number; y: number } | null>(null);
let resizeObserver: ResizeObserver | null = null;

const allianceColor = computed(
  () => new Map(props.alliances.map((alliance) => [alliance.key, alliance.presentation_color])),
);
const visibleAlliances = computed(
  () =>
    new Set(props.alliances.filter((alliance) => alliance.visible).map((alliance) => alliance.key)),
);

function fitMap(): void {
  const xScale = width.value / props.map.bounds.width;
  const yScale = height.value / props.map.bounds.height;
  zoom.value = Math.max(0.08, Math.min(xScale, yScale) * 0.92);
  cameraX.value = props.map.bounds.x + props.map.bounds.width / 2;
  cameraY.value = props.map.bounds.y + props.map.bounds.height / 2;
  draw();
}

function zoomBy(factor: number): void {
  zoom.value = Math.min(8, Math.max(0.06, zoom.value * factor));
  draw();
}

function toScreen(x: number, y: number): [number, number] {
  return [
    (x - cameraX.value) * zoom.value + width.value / 2,
    height.value / 2 - (y - cameraY.value) * zoom.value,
  ];
}
function toWorld(screenX: number, screenY: number): [number, number] {
  return [
    (screenX - width.value / 2) / zoom.value + cameraX.value,
    (height.value / 2 - screenY) / zoom.value + cameraY.value,
  ];
}
function eventPoint(event: PointerEvent | WheelEvent): [number, number] {
  const rect = canvas.value?.getBoundingClientRect();
  return [event.clientX - (rect?.left ?? 0), event.clientY - (rect?.top ?? 0)];
}
function objectAt(screenX: number, screenY: number): PlanObject | null {
  const visible = props.objects.filter((object) => visibleAlliances.value.has(object.alliance_key));
  for (let index = visible.length - 1; index >= 0; index -= 1) {
    const object = visible[index];
    if (!object) continue;
    const definition = props.map.object_types[object.type];
    const [x, yBottom] = toScreen(object.x, object.y);
    const size = definition.size * zoom.value;
    if (
      screenX >= x &&
      screenX <= x + size &&
      screenY <= yBottom &&
      screenY >= yBottom - size
    )
      return object;
  }
  return null;
}

function onPointerDown(event: PointerEvent): void {
  if (!canvas.value) return;
  canvas.value.setPointerCapture(event.pointerId);
  const [screenX, screenY] = eventPoint(event);
  const [worldX, worldY] = toWorld(screenX, screenY);
  if (!props.readOnly && props.tool === 'place' && props.activeAllianceKey) {
    emit('place', { x: Math.round(worldX), y: Math.round(worldY) });
    return;
  }
  if (props.tool === 'pan' || event.button === 1 || event.button === 2) {
    drag.value = {
      kind: 'pan',
      startX: screenX,
      startY: screenY,
      worldX,
      worldY,
      lastWorldX: worldX,
      lastWorldY: worldY,
    };
    return;
  }
  const hit = objectAt(screenX, screenY);
  if (hit) {
    const selected = event.shiftKey
      ? props.selectedKeys.includes(hit.key)
        ? props.selectedKeys.filter((key) => key !== hit.key)
        : [...props.selectedKeys, hit.key]
      : props.selectedKeys.includes(hit.key)
        ? props.selectedKeys
        : [hit.key];
    emit('update:selectedKeys', selected);
    const layer = props.alliances.find((alliance) => alliance.key === hit.alliance_key);
    if (!props.readOnly && !layer?.locked)
      drag.value = {
        kind: 'object',
        startX: screenX,
        startY: screenY,
        worldX,
        worldY,
        lastWorldX: worldX,
        lastWorldY: worldY,
      };
    return;
  }
  if (!event.shiftKey) emit('update:selectedKeys', []);
  drag.value = {
    kind: 'box',
    startX: screenX,
    startY: screenY,
    worldX,
    worldY,
    lastWorldX: worldX,
    lastWorldY: worldY,
  };
  boxEnd.value = { x: screenX, y: screenY };
}
function onPointerMove(event: PointerEvent): void {
  if (!drag.value) return;
  const [screenX, screenY] = eventPoint(event);
  const [worldX, worldY] = toWorld(screenX, screenY);
  if (drag.value.kind === 'pan') {
    cameraX.value -= worldX - drag.value.lastWorldX;
    cameraY.value -= worldY - drag.value.lastWorldY;
  } else if (drag.value.kind === 'box') {
    boxEnd.value = { x: screenX, y: screenY };
  }
  drag.value.lastWorldX = worldX;
  drag.value.lastWorldY = worldY;
  draw();
}
function onPointerUp(event: PointerEvent): void {
  if (!drag.value) return;
  const current = drag.value;
  const [screenX, screenY] = eventPoint(event);
  const [worldX, worldY] = toWorld(screenX, screenY);
  if (current.kind === 'object') {
    const dx = Math.round(worldX - current.worldX);
    const dy = Math.round(worldY - current.worldY);
    if (dx || dy) emit('move', { keys: props.selectedKeys, dx, dy });
  } else if (current.kind === 'box') {
    const left = Math.min(current.startX, screenX);
    const right = Math.max(current.startX, screenX);
    const top = Math.min(current.startY, screenY);
    const bottom = Math.max(current.startY, screenY);
    const selected = props.objects
      .filter((object) => {
        if (!visibleAlliances.value.has(object.alliance_key)) return false;
        const definition = props.map.object_types[object.type];
        const [x, yBottom] = toScreen(object.x, object.y);
        const size = definition.size * zoom.value;
        return x >= left && x + size <= right && yBottom - size >= top && yBottom <= bottom;
      })
      .map((object) => object.key);
    emit(
      'update:selectedKeys',
      event.shiftKey ? [...new Set([...props.selectedKeys, ...selected])] : selected,
    );
  }
  drag.value = null;
  boxEnd.value = null;
  draw();
}
function onWheel(event: WheelEvent): void {
  event.preventDefault();
  const [screenX, screenY] = eventPoint(event);
  const [beforeX, beforeY] = toWorld(screenX, screenY);
  zoom.value = Math.min(8, Math.max(0.06, zoom.value * (event.deltaY < 0 ? 1.12 : 0.89)));
  const [afterX, afterY] = toWorld(screenX, screenY);
  cameraX.value += beforeX - afterX;
  cameraY.value += beforeY - afterY;
  draw();
}

function draw(): void {
  const element = canvas.value;
  if (!element) return;
  const context = element.getContext('2d');
  if (!context) return;
  const ratio = window.devicePixelRatio || 1;
  element.width = Math.round(width.value * ratio);
  element.height = Math.round(height.value * ratio);
  context.setTransform(ratio, 0, 0, ratio, 0, 0);
  context.clearRect(0, 0, width.value, height.value);
  context.fillStyle = '#101821';
  context.fillRect(0, 0, width.value, height.value);

  if (props.showZones) {
    for (const [zoneName, zone] of Object.entries(props.map.zones)) {
      if (zoneName === 'badlands') continue;
      const [x, yTop] = toScreen(zone.x, zone.y + zone.height);
      context.strokeStyle = 'rgba(225, 195, 120, .18)';
      context.strokeRect(x, yTop, zone.width * zoom.value, zone.height * zoom.value);
    }
  }
  if (props.showStructures) {
    for (const structure of props.map.structures) {
      const [x, yBottom] = toScreen(structure.x, structure.y);
      const size = structure.size * zoom.value;
      context.fillStyle = 'rgba(139, 125, 107, .82)';
      context.fillRect(x, yBottom - size, size, size);
    }
  }
  for (const object of props.objects) {
    if (!visibleAlliances.value.has(object.alliance_key)) continue;
    const definition = props.map.object_types[object.type];
    const color = allianceColor.value.get(object.alliance_key) ?? '#4da3ff';
    const [x, yBottom] = toScreen(object.x, object.y);
    const size = definition.size * zoom.value;
    if (props.showCoverage && definition.coverage > 0) {
      context.globalAlpha = 0.12;
      context.fillStyle = color;
      const coverage = definition.coverage * zoom.value;
      context.fillRect(
        x - coverage,
        yBottom - size - coverage,
        size + coverage * 2,
        size + coverage * 2,
      );
      context.globalAlpha = 1;
    }
    context.fillStyle = color;
    context.fillRect(x, yBottom - size, Math.max(size, 2), Math.max(size, 2));
    context.strokeStyle = props.selectedKeys.includes(object.key)
      ? '#fff4b8'
      : 'rgba(255,255,255,.55)';
    context.lineWidth = props.selectedKeys.includes(object.key) ? 2 : 1;
    context.strokeRect(x, yBottom - size, Math.max(size, 2), Math.max(size, 2));
    if (zoom.value > 1.2 && object.label) {
      context.fillStyle = '#f8fafc';
      context.font = '11px sans-serif';
      context.fillText(object.label, x + 3, yBottom - size - 4);
    }
  }
  if (drag.value?.kind === 'box' && boxEnd.value) {
    context.strokeStyle = '#e8c978';
    context.setLineDash([5, 4]);
    const left = Math.min(drag.value.startX, boxEnd.value.x);
    const top = Math.min(drag.value.startY, boxEnd.value.y);
    context.strokeRect(
      left,
      top,
      Math.abs(boxEnd.value.x - drag.value.startX),
      Math.abs(boxEnd.value.y - drag.value.startY),
    );
    context.setLineDash([]);
  }
}

defineExpose({ fitMap });
onMounted(() => {
  resizeObserver = new ResizeObserver(([entry]) => {
    if (!entry) return;
    width.value = Math.max(320, Math.floor(entry.contentRect.width));
    height.value = Math.max(420, Math.min(760, Math.floor(window.innerHeight * 0.68)));
    fitMap();
  });
  if (host.value) resizeObserver.observe(host.value);
  nextTick(fitMap);
});
onBeforeUnmount(() => resizeObserver?.disconnect());
watch(
  () => [
    props.objects,
    props.alliances,
    props.selectedKeys,
    props.showCoverage,
    props.showStructures,
    props.showZones,
  ],
  draw,
  { deep: true },
);
</script>

<template>
  <div
    ref="host"
    class="relative min-h-[26rem] w-full overflow-hidden rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[#101821]"
  >
    <canvas
      ref="canvas"
      class="block h-full w-full touch-none"
      :style="{ width: `${width}px`, height: `${height}px` }"
      :aria-label="label"
      tabindex="0"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
      @wheel="onWheel"
      @contextmenu.prevent
    />
    <div class="absolute top-3 right-3 flex gap-1 rounded bg-black/60 p-1">
      <button
        type="button"
        class="rounded px-2 py-1 text-sm font-semibold text-white hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
        :aria-label="`${label} −`"
        @click="zoomBy(0.8)"
      >
        −
      </button>
      <button
        type="button"
        class="rounded px-2 py-1 text-sm font-semibold text-white hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
        :aria-label="`${label} +`"
        @click="zoomBy(1.25)"
      >
        +
      </button>
      <button
        type="button"
        class="rounded px-2 py-1 text-sm font-semibold text-white hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
        :aria-label="`${label} 100%`"
        @click="fitMap"
      >
        ⤢
      </button>
    </div>
    <div
      class="pointer-events-none absolute right-3 bottom-3 rounded bg-black/60 px-2 py-1 text-xs text-white/70"
      aria-live="polite"
    >
      {{ Math.round(zoom * 100) }}%
    </div>
  </div>
</template>
