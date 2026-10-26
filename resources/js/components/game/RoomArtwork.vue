<script setup lang="ts">
import { computed } from 'vue';

export type RoomScene =
  'territory-command' | 'gift-code-vault' | 'progression-forge' | 'assistant-archive';

const props = defineProps<{ scene: RoomScene }>();
const source = computed(() => `/images/kingshot/generated/${props.scene}`);
</script>

<template>
  <picture class="ks-room-artwork" :data-room-scene="scene" aria-hidden="true">
    <source media="(max-width: 639px)" :srcset="`${source}-mobile.webp`" />
    <img
      :src="`${source}-desktop.webp`"
      alt=""
      width="1280"
      height="480"
      decoding="async"
      draggable="false"
    />
  </picture>
</template>
