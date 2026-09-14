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

import { buildTerritoryScene } from '../engine/scene';
import { buildPresentation, layerIsVisible, paintPresentation } from '../engine/presentation';
import type { PresentationOptions } from '../engine/presentation';
import { sceneEntitiesInBounds, sceneEntityContains } from '../engine/scene-index';
import { requireAtomicEditableSelection } from '../engine/commands';
import { MAX_ZOOM, MIN_ZOOM, worldPoint } from '../engine/viewport';
import type { ObservedSceneObject } from '../engine/scene';
import type { TerritorySceneEntity } from '../engine/scene-types';
import type { MapData, PlanAlliance, PlanObject, TerritoryObjectType } from '../engine/types';

type Tool = 'select' | 'pan' | 'place';

const props = withDefaults(
  defineProps<{
    map: MapData;
    mapChecksum?: string;
    alliances: PlanAlliance[];
    objects: PlanObject[];
    observedObjects?: ObservedSceneObject[];
    selectedKeys: string[];
    tool: Tool;
    placementType: TerritoryObjectType;
    activeAllianceKey: string | null;
    label: string;
    readOnly?: boolean;
    showCoverage?: boolean;
    showStructures?: boolean;
    showZones?: boolean;
    showTerrain?: boolean;
    showFacilities?: boolean;
    showResources?: boolean;
    showObserved?: boolean;
    showGrid?: boolean;
    showLabels?: boolean;
  }>(),
  {
    mapChecksum: '',
    observedObjects: () => [],
    readOnly: false,
    showCoverage: true,
    showStructures: true,
    showZones: true,
    showTerrain: true,
    showFacilities: true,
    showResources: true,
    showObserved: true,
    showGrid: false,
    showLabels: true,
  },
);

const emit = defineEmits<{
  (event: 'update:selectedKeys', value: string[]): void;
  (event: 'move', value: { keys: string[]; dx: number; dy: number }): void;
  (event: 'place', value: { x: number; y: number }): void;
  (event: 'inspectReference', value: string): void;
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

const visibleAlliances = computed(
  () =>
    new Set(props.alliances.filter((alliance) => alliance.visible).map((alliance) => alliance.key)),
);
const scene = computed(() =>
  buildTerritoryScene({
    map: props.map,
    mapChecksum: props.mapChecksum,
    alliances: props.alliances,
    objects: props.objects,
    observedObjects: props.observedObjects ?? [],
  }),
);

const presentationOptions = computed<PresentationOptions>(() => ({
  selectedKeys: props.selectedKeys,
  showGrid: props.showGrid,
  showLabels: props.showLabels,
  layers: {
    regions: { visible: props.showZones, opacity: 1 },
    restrictions: { visible: props.showZones, opacity: 1 },
    terrain: { visible: props.showTerrain, opacity: 0.62 },
    structures: { visible: props.showStructures, opacity: 1 },
    resources: { visible: props.showResources, opacity: 0.8 },
    facilities: { visible: props.showFacilities, opacity: 1 },
    observed: { visible: props.showObserved, opacity: 1 },
    coverage: { visible: props.showCoverage, opacity: 1 },
  },
}));

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
function setCamera(view: { x: number; y: number; zoom: number }): void {
  if (![view.x, view.y, view.zoom].every(Number.isFinite) || view.zoom <= 0) return;
  cancelGesture();
  setViewport({
    ...viewport(),
    x: view.x,
    y: view.y,
    zoom: Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, view.zoom)),
  });
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
  const selection = requireAtomicEditableSelection(
    props.objects,
    keys,
    (object) => allowed.has(object.alliance_key) && object.metadata.locked !== true,
  );
  return selection.ok ? selection.selected.map((object) => object.key) : [];
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

function rotatedRectangle(
  rectangle: { width: number; height: number },
  rotation: number,
): { width: number; height: number } {
  return rotation === 90 || rotation === 270
    ? { width: rectangle.height, height: rectangle.width }
    : rectangle;
}

function entitiesAt(screenX: number, screenY: number): TerritorySceneEntity[] {
  const point = worldPoint({ x: screenX, y: screenY }, viewport());
  const tolerance = 4 / zoom.value;
  return sceneEntitiesInBounds(scene.value, {
    x: point.x - tolerance,
    y: point.y - tolerance,
    width: tolerance * 2,
    height: tolerance * 2,
  })
    .filter(
      (entity) => entity.selectable && layerIsVisible(entity.layer, presentationOptions.value),
    )
    .filter((entity) =>
      entity.spans
        ? sceneEntityContains(entity, point.x, point.y)
        : point.x >= entity.bounds.x - tolerance &&
          point.x < entity.bounds.x + entity.bounds.width + tolerance &&
          point.y >= entity.bounds.y - tolerance &&
          point.y < entity.bounds.y + entity.bounds.height + tolerance,
    );
}
function objectAt(screenX: number, screenY: number): PlanObject | null {
  const entity = entitiesAt(screenX, screenY)
    .filter((candidate) => candidate.layer === 'planned')
    .at(-1);
  return entity
    ? (props.objects.find((object) => object.key === entity.planObjectKey) ?? null)
    : null;
}
function referenceAt(screenX: number, screenY: number): TerritorySceneEntity | null {
  return (
    entitiesAt(screenX, screenY)
      .filter((entity) => entity.kind === 'factual')
      .at(-1) ?? null
  );
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
  const reference = referenceAt(point.x, point.y);
  if (reference) {
    emit('inspectReference', reference.key);
    if (!event.shiftKey) emit('update:selectedKeys', []);
    drag.value = null;
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
        const footprint = rotatedRectangle(definition.footprint, object.rotation);
        const [x, yBottom] = toScreen(object.x, object.y);
        return (
          x >= left &&
          x + footprint.width * zoom.value <= right &&
          yBottom - footprint.height * zoom.value >= top &&
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
  const ratio = Math.min(2, window.devicePixelRatio || 1);
  const pixelWidth = Math.round(width.value * ratio);
  const pixelHeight = Math.round(height.value * ratio);
  if (element.width !== pixelWidth) element.width = pixelWidth;
  if (element.height !== pixelHeight) element.height = pixelHeight;
  context.setTransform(ratio, 0, 0, ratio, 0, 0);
  context.clearRect(0, 0, width.value, height.value);
  context.fillStyle = '#101821';
  context.fillRect(0, 0, width.value, height.value);

  const options = {
    ...presentationOptions.value,
    ...(drag.value?.kind === 'object'
      ? { preview: { keys: drag.value.keys, delta: gestureDelta(drag.value) } }
      : {}),
  };
  context.save();
  context.beginPath();
  const northwest = screenPoint(
    { x: props.map.bounds.x, y: props.map.bounds.y + props.map.bounds.height },
    viewport(),
  );
  context.rect(
    northwest.x,
    northwest.y,
    props.map.bounds.width * zoom.value,
    props.map.bounds.height * zoom.value,
  );
  context.clip();
  paintPresentation(context, buildPresentation(scene.value, viewport(), options));
  context.restore();
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

function exportOptions(): PresentationOptions {
  return { ...presentationOptions.value, selectedKeys: [] };
}
defineExpose({ fitMap, focusBounds, jumpTo, setCamera, viewport, exportOptions });
onMounted(() => {
  resizeObserver = new ResizeObserver(([entry]) => {
    if (!entry) return;
    cancelGesture();
    width.value = Math.max(1, Math.floor(entry.contentRect.width));
    height.value = document.fullscreenElement?.contains(host.value)
      ? Math.max(420, window.innerHeight - 100)
      : Math.max(320, Math.min(760, Math.floor(window.innerHeight * 0.68)));
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
watch(
  () => [
    props.selectedKeys,
    props.showCoverage,
    props.showStructures,
    props.showZones,
    props.showTerrain,
    props.showFacilities,
    props.showResources,
    props.showObserved,
    props.showGrid,
    props.showLabels,
    props.observedObjects,
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
