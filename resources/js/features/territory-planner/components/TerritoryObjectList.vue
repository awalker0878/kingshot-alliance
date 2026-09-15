<script setup lang="ts">
import type { TerritorySceneEntity } from '../engine/scene-types';

const props = defineProps<{
  entities: TerritorySceneEntity[];
  selectedKey: string | null;
  label: string;
}>();

const emit = defineEmits<{
  (event: 'inspect', entity: TerritorySceneEntity): void;
}>();
</script>

<template>
  <ul class="m-0 max-h-[32rem] list-none overflow-auto p-0" :aria-label="label">
    <li v-for="entity in props.entities" :key="entity.key">
      <button
        type="button"
        class="block min-h-11 w-full border-b border-[var(--ks-border)] px-2 py-2 text-start text-sm focus-visible:outline-2 focus-visible:outline-offset-[-2px]"
        :aria-pressed="selectedKey === entity.key"
        @click="emit('inspect', entity)"
      >
        <span class="block font-semibold">{{ entity.label }}</span>
        <span class="text-xs text-[var(--ks-muted)]">
          {{ entity.layer }} · X{{ entity.bounds.x }} Y{{ entity.bounds.y }} ·
          {{ entity.bounds.width }}×{{ entity.bounds.height }}
        </span>
      </button>
    </li>
  </ul>
</template>
