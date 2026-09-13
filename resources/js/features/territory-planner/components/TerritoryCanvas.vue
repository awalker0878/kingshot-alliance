<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';

import {
  advanceGesture,
  completeGesture,
  distance,
  fitBounds,
  gestureDelta,
  midpoint,
  panFrom,
  screenPoint,
  zoomAt,
} from '../engine/viewport';
import type { Point, PointerGesture, Viewport, WorldBounds } from '../engine/viewport';

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
const drag = shallowRef<PointerGesture | null>(null);
const pointers = new Map<number, Point>();
let pinch: { ids: [number, number]; start: [Point, Point]; view: Viewport } | null = null;
let resizeObserver: ResizeObserver | null = null;
let animationFrame: number | null = null;
let fitted = false;

function viewport(): Viewport {
  return {
    x: cameraX.value,
    y: cameraY.value,
    zoom: zoom.value,
    width: width.value,
    height: height.value,
  };
}
function setViewport(view: Viewport): void {
  cameraX.value = view.x;
  cameraY.value = view.y;
  zoom.value = view.zoom;
  draw();
}

const allianceColor = computed(
  () => new Map(props.alliances.map((alliance) => [alliance.key, alliance.presentation_color])),
);
const visibleAlliances = computed(
  () =>
    new Set(props.alliances.filter((alliance) => alliance.visible).map((alliance) => alliance.key)),
);

function fitMap(): void {
  cancelGesture();
  setViewport(fitBounds(props.map.bounds, viewport()));
}
function focusBounds(bounds: WorldBounds): void {
  cancelGesture();
  setViewport(fitBounds(bounds, viewport()));
}
function jumpTo(point: Point): void {
  if (!Number.isFinite(point.x) || !Number.isFinite(point.y)) return;
  cancelGesture();
  setViewport({ ...viewport(), x: point.x, y: point.y });
}
function zoomBy(factor: number): void {
  cancelGesture();
  setViewport(zoomAt(viewport(), factor, { x: width.value / 2, y: height.value / 2 }));
}
function toScreen(x: number, y: number): [number, number] {
  const point = screenPoint({ x, y }, viewport());
  return [point.x, point.y];
}
function eventPoint(event: PointerEvent | WheelEvent): Point {
  const rect = canvas.value?.getBoundingClientRect();
  return {
    x: ((event.clientX - (rect?.left ?? 0)) * width.value) / (rect?.width || width.value),
    y: ((event.clientY - (rect?.top ?? 0)) * height.value) / (rect?.height || height.value),
  };
}
function movableKeys(keys: string[]): string[] {
  if (props.readOnly) return [];
  const allowed = new Set(
    props.alliances.filter((layer) => layer.visible && !layer.locked).map((layer) => layer.key),
  );
  const selected = new Set(keys);
  return props.objects
    .filter((object) => selected.has(object.key) && allowed.has(object.alliance_key))
    .map((object) => object.key);
}
function canPlace(): boolean {
  return (
    !props.readOnly &&
    props.alliances.some(
      (layer) => layer.key === props.activeAllianceKey && layer.visible && !layer.locked,
    )
  );
}
function releasePointer(pointerId: number): void {
  if (canvas.value?.hasPointerCapture(pointerId)) canvas.value.releasePointerCapture(pointerId);
}
function cancelGesture(): void {
  drag.value = null;
  pinch = null;
  const ids = [...pointers.keys()];
  pointers.clear();
  ids.forEach(releasePointer);
  draw();
}
function objectAt(screenX: number, screenY: number): PlanObject | null {
  const visible = props.objects.filter((object) => visibleAlliances.value.has(object.alliance_key));
  for (let index = visible.length - 1; index >= 0; index -= 1) {
    const object = visible[index];
    if (!object) continue;
    const definition = props.map.object_types[object.type];
    const [x, yBottom] = toScreen(object.x, object.y);
    const objectWidth = definition.footprint.width * zoom.value;
    const objectHeight = definition.footprint.height * zoom.value;
    if (
      screenX >= x &&
      screenX <= x + objectWidth &&
      screenY <= yBottom &&
      screenY >= yBottom - objectHeight
    )
      return object;
  }
  return null;
}

function onPointerDown(event: PointerEvent): void {
  if (!canvas.value || ![0, 1, 2].includes(event.button) || pointers.size >= 2) return;
  const point = eventPoint(event);
  pointers.set(event.pointerId, point);
  canvas.value.setPointerCapture(event.pointerId);
  canvas.value.focus({ preventScroll: true });
  if (pointers.size === 2) {
    const entries = [...pointers.entries()];
    const first = entries[0];
    const second = entries[1];
    if (first && second)
      pinch = { ids: [first[0], second[0]], start: [first[1], second[1]], view: viewport() };
    drag.value = null;
    draw();
    return;
  }
  const gesture: PointerGesture = {
    kind: 'box',
    pointerId: event.pointerId,
    start: point,
    current: point,
    view: viewport(),
    keys: [],
    additive: event.shiftKey,
    moved: false,
  };
  // Navigation buttons must never place objects, even with the placement tool selected.
  if (props.tool === 'pan' || event.button !== 0) {
    drag.value = { ...gesture, kind: 'pan' };
    return;
  }
  if (props.tool === 'place' && canPlace()) {
    // Commit on pointer-up so a cancelled touch or pinch cannot create objects.
    drag.value = { ...gesture, kind: 'place' };
    return;
  }
  const hit = objectAt(point.x, point.y);
  if (hit) {
    const selected = event.shiftKey
      ? props.selectedKeys.includes(hit.key)
        ? props.selectedKeys.filter((key) => key !== hit.key)
        : [...props.selectedKeys, hit.key]
      : props.selectedKeys.includes(hit.key)
        ? [...props.selectedKeys]
        : [hit.key];
    emit('update:selectedKeys', selected);
    const keys = movableKeys(selected);
    // Shift-deselecting an object must not start a drag of the remaining selection.
    if (selected.includes(hit.key) && keys.includes(hit.key))
      drag.value = { ...gesture, kind: 'object', keys };
    return;
  }
  if (!event.shiftKey) emit('update:selectedKeys', []);
  drag.value = gesture;
  draw();
}
function onPointerMove(event: PointerEvent): void {
  if (!pointers.has(event.pointerId)) return;
  const point = eventPoint(event);
  pointers.set(event.pointerId, point);
  if (pinch) {
    const a = pointers.get(pinch.ids[0]);
    const b = pointers.get(pinch.ids[1]);
    if (a && b)
      setViewport(
        zoomAt(
          pinch.view,
          distance(a, b) / Math.max(1, distance(...pinch.start)),
          midpoint(...pinch.start),
          midpoint(a, b),
        ),
      );
    return;
  }
  if (!drag.value || drag.value.pointerId !== event.pointerId) return;
  drag.value = advanceGesture(drag.value, point);
  if (drag.value.kind === 'pan') setViewport(panFrom(drag.value.view, drag.value.start, point));
  else draw();
}
function onPointerUp(event: PointerEvent): void {
  if (!pointers.has(event.pointerId)) return;
  if (pinch) {
    cancelGesture();
    return;
  }
  const result = completeGesture(drag.value, event.pointerId, eventPoint(event), false);
  cancelGesture();
  if (result?.kind === 'move') {
    const keys = movableKeys(result.keys);
    if (keys.length) emit('move', { ...result, keys });
  } else if (result?.kind === 'place' && canPlace()) {
    emit('place', result.point);
  } else if (result?.kind === 'box') {
    const left = Math.min(result.start.x, result.end.x);
    const right = Math.max(result.start.x, result.end.x);
    const top = Math.min(result.start.y, result.end.y);
    const bottom = Math.max(result.start.y, result.end.y);
    const selected = props.objects
      .filter((object) => {
        if (!visibleAlliances.value.has(object.alliance_key)) return false;
        const definition = props.map.object_types[object.type];
        const [x, yBottom] = toScreen(object.x, object.y);
        return (
          x >= left &&
          x + definition.footprint.width * zoom.value <= right &&
          yBottom - definition.footprint.height * zoom.value >= top &&
          yBottom <= bottom
        );
      })
      .map((object) => object.key);
    emit(
      'update:selectedKeys',
      result.additive ? [...new Set([...props.selectedKeys, ...selected])] : selected,
    );
  }
}
function onPointerCancel(event: PointerEvent): void {
  if (pointers.has(event.pointerId)) cancelGesture();
}
function onWheel(event: WheelEvent): void {
  event.preventDefault();
  const point = eventPoint(event);
  const units = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? height.value : 1;
  cancelGesture();
  setViewport(
    zoomAt(
      viewport(),
      Math.exp(-Math.max(-500, Math.min(500, event.deltaY * units)) * 0.002),
      point,
    ),
  );
}
function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    cancelGesture();
    emit('update:selectedKeys', []);
  } else if (event.key === '+' || event.key === '=' || event.key === 'PageUp') zoomBy(1.25);
  else if (event.key === '-' || event.key === 'PageDown') zoomBy(0.8);
  else if (event.key === 'Home') fitMap();
  else if (props.tool === 'pan' || props.readOnly || props.selectedKeys.length === 0) {
    const directions: Record<string, Point> = {
      ArrowLeft: { x: 48, y: 0 },
      ArrowRight: { x: -48, y: 0 },
      ArrowUp: { x: 0, y: 48 },
      ArrowDown: { x: 0, y: -48 },
    };
    const delta = directions[event.key];
    if (!delta) return;
    cancelGesture();
    setViewport(panFrom(viewport(), { x: 0, y: 0 }, delta));
  } else return;
  event.preventDefault();
  event.stopPropagation();
}

function draw(): void {
  if (animationFrame !== null || !canvas.value) return;
  animationFrame = requestAnimationFrame(() => {
    animationFrame = null;
    render();
  });
}

function render(): void {
  const element = canvas.value;
  if (!element) return;
  const context = element.getContext('2d');
  if (!context) return;
  const ratio = window.devicePixelRatio || 1;
  const pixelWidth = Math.round(width.value * ratio);
  const pixelHeight = Math.round(height.value * ratio);
  if (element.width !== pixelWidth) element.width = pixelWidth;
  if (element.height !== pixelHeight) element.height = pixelHeight;
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
      const structureWidth = structure.footprint.width * zoom.value;
      const structureHeight = structure.footprint.height * zoom.value;
      context.fillStyle = 'rgba(139, 125, 107, .82)';
      context.fillRect(x, yBottom - structureHeight, structureWidth, structureHeight);
    }
  }
  const previewDelta = drag.value?.kind === 'object' ? gestureDelta(drag.value) : { x: 0, y: 0 };
  const previewKeys = new Set(drag.value?.kind === 'object' ? drag.value.keys : []);
  for (const stored of props.objects) {
    const object = previewKeys.has(stored.key)
      ? { ...stored, x: stored.x + previewDelta.x, y: stored.y + previewDelta.y }
      : stored;
    if (!visibleAlliances.value.has(object.alliance_key)) continue;
    const definition = props.map.object_types[object.type];
    const color = allianceColor.value.get(object.alliance_key) ?? '#4da3ff';
    const [x, yBottom] = toScreen(object.x, object.y);
    const objectWidth = definition.footprint.width * zoom.value;
    const objectHeight = definition.footprint.height * zoom.value;
    if (props.showCoverage && definition.coverage) {
      const coverageOffsetX = Math.trunc(
        (definition.coverage.width - definition.footprint.width) / 2,
      );
      const coverageOffsetY = Math.trunc(
        (definition.coverage.height - definition.footprint.height) / 2,
      );
      const [coverageX, coverageBottom] = toScreen(
        object.x - coverageOffsetX,
        object.y - coverageOffsetY,
      );
      const coverageWidth = definition.coverage.width * zoom.value;
      const coverageHeight = definition.coverage.height * zoom.value;
      context.globalAlpha = 0.12;
      context.fillStyle = color;
      context.fillRect(coverageX, coverageBottom - coverageHeight, coverageWidth, coverageHeight);
      context.globalAlpha = 1;
    }
    context.fillStyle = color;
    context.fillRect(
      x,
      yBottom - objectHeight,
      Math.max(objectWidth, 2),
      Math.max(objectHeight, 2),
    );
    context.strokeStyle = props.selectedKeys.includes(object.key)
      ? '#fff4b8'
      : 'rgba(255,255,255,.55)';
    context.lineWidth = props.selectedKeys.includes(object.key) ? 2 : 1;
    context.strokeRect(
      x,
      yBottom - objectHeight,
      Math.max(objectWidth, 2),
      Math.max(objectHeight, 2),
    );
    if (zoom.value > 1.2 && object.label) {
      context.fillStyle = '#f8fafc';
      context.font = '11px sans-serif';
      context.fillText(object.label, x + 3, yBottom - objectHeight - 4);
    }
  }
  if (drag.value?.kind === 'box') {
    context.strokeStyle = '#e8c978';
    context.setLineDash([5, 4]);
    const left = Math.min(drag.value.start.x, drag.value.current.x);
    const top = Math.min(drag.value.start.y, drag.value.current.y);
    context.strokeRect(
      left,
      top,
      Math.abs(drag.value.current.x - drag.value.start.x),
      Math.abs(drag.value.current.y - drag.value.start.y),
    );
    context.setLineDash([]);
  }
}

defineExpose({ fitMap, focusBounds, jumpTo, viewport });
onMounted(() => {
  resizeObserver = new ResizeObserver(([entry]) => {
    if (!entry) return;
    cancelGesture();
    width.value = Math.max(1, Math.floor(entry.contentRect.width));
    height.value = Math.max(420, Math.min(760, Math.floor(window.innerHeight * 0.68)));
    if (!fitted) {
      fitted = true;
      fitMap();
    } else draw();
  });
  if (host.value) resizeObserver.observe(host.value);
  nextTick(draw);
  window.addEventListener('blur', cancelGesture);
});
onBeforeUnmount(() => {
  resizeObserver?.disconnect();
  cancelGesture();
  if (animationFrame !== null) cancelAnimationFrame(animationFrame);
  window.removeEventListener('blur', cancelGesture);
});
watch(
  () => [
    props.objects,
    props.alliances,
    props.map,
    props.readOnly,
    props.tool,
    props.activeAllianceKey,
  ],
  cancelGesture,
  { deep: true },
);
watch(() => [props.selectedKeys, props.showCoverage, props.showStructures, props.showZones], draw, {
  deep: true,
});
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
      @pointercancel="onPointerCancel"
      @lostpointercapture="onPointerCancel"
      @keydown="onKey"
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
