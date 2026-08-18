<script setup lang="ts">
withDefaults(defineProps<{ title: string; paper?: boolean }>(), { paper: false });
</script>
<template>
  <section class="ks-war-table" :class="{ 'ks-war-table--paper': paper }">
    <header class="ks-war-table__header">
      <h2>{{ title }}</h2>
      <div v-if="$slots.actions"><slot name="actions" /></div>
    </header>
    <div class="ks-war-table__body"><slot /></div>
  </section>
</template>
