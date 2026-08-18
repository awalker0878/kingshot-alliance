<script setup lang="ts">
withDefaults(defineProps<{ title?: string; royal?: boolean; parchment?: boolean }>(), {
  title: '',
  royal: false,
  parchment: false,
});
</script>
<template>
  <section class="ks-panel" :class="{ 'ks-panel-royal': royal, 'ks-parchment': parchment }">
    <header v-if="title || $slots.actions" class="ks-panel__header">
      <h2 v-if="title">{{ title }}</h2>
      <slot name="actions" />
    </header>
    <div class="ks-panel__body"><slot /></div>
  </section>
</template>
