<script setup lang="ts">
withDefaults(
  defineProps<{ tag?: string | null; name?: string | null; size?: 'sm' | 'md' | 'lg' }>(),
  { tag: 'KS', name: 'Alliance', size: 'md' },
);
</script>
<template>
  <div class="ks-crest" :data-size="size">
    <span class="ks-crest__crown">♛</span
    ><span class="ks-crest__tag">{{ (tag || name || 'KS').slice(0, 3).toUpperCase() }}</span>
  </div>
</template>
