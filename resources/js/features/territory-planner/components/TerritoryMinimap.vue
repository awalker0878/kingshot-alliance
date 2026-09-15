<script setup lang="ts">
import { computed } from 'vue';
import type { Viewport, WorldBounds } from '../engine/viewport';

const props = defineProps<{
  bounds: WorldBounds;
  viewport: Viewport | null;
  label: string;
}>();

const emit = defineEmits<{
  (event: 'navigate', point: { x: number; y: number }): void;
}>();

const viewBox = computed(
  () => `${props.bounds.x} ${props.bounds.y} ${props.bounds.width} ${props.bounds.height}`,
);
const viewportBounds = computed<WorldBounds | null>(() => {
  if (!props.viewport) return null;
  const width = props.viewport.width / props.viewport.zoom;
  const height = props.viewport.height / props.viewport.zoom;
  return {
    x: props.viewport.x - width / 2,
    y: props.viewport.y - height / 2,
    width,
    height,
  };
});
function navigate(event: MouseEvent): void {
  const svg = event.currentTarget as SVGSVGElement;
  const rect = svg.getBoundingClientRect();
  if (!rect.width || !rect.height) return;
  const x = props.bounds.x + ((event.clientX - rect.left) / rect.width) * props.bounds.width;
  const y = props.bounds.y + (1 - (event.clientY - rect.top) / rect.height) * props.bounds.height;
  emit('navigate', { x: Math.round(x), y: Math.round(y) });
}
</script>

<template>
  <section class="ks-surface mt-3 p-3" :aria-label="label">
    <svg
      class="block h-32 w-full cursor-crosshair rounded border border-[var(--ks-border)] bg-[#101821]"
      :viewBox="viewBox"
      role="img"
      :aria-label="label"
      preserveAspectRatio="none"
      @click="navigate"
    >
      <rect
        :x="bounds.x"
        :y="bounds.y"
        :width="bounds.width"
        :height="bounds.height"
        fill="transparent"
        stroke="currentColor"
        vector-effect="non-scaling-stroke"
      />
      <rect
        v-if="viewportBounds"
        :x="viewportBounds.x"
        :y="viewportBounds.y"
        :width="viewportBounds.width"
        :height="viewportBounds.height"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        vector-effect="non-scaling-stroke"
      />
    </svg>
  </section>
</template>
