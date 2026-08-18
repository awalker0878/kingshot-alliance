<script setup lang="ts">
import { computed } from 'vue';
const props = defineProps<{ name: string }>();
const sigil = computed(() => {
  const n = props.name.toLowerCase();
  if (n.includes('bear')) return '🐾';
  if (n.includes('viking')) return '⚔';
  if (n.includes('mobil')) return '⚑';
  if (n.includes('swordland')) return '⚔';
  if (n.includes('castle')) return '♜';
  if (n.includes('power')) return '♛';
  return '◆';
});
</script>
<template>
  <span class="ks-event-sigil" aria-hidden="true">{{ sigil }}</span>
</template>
