<script setup lang="ts">
withDefaults(defineProps<{ title: string; subtitle?: string; eyebrow?: string; image?: string; compact?: boolean }>(), { subtitle: '', eyebrow: '', image: '/images/kingshot/realm-command.svg', compact: false });
</script>
<template>
  <section class="ks-room-banner" :class="{ 'ks-room-banner--compact': compact }">
    <img v-if="image" class="ks-room-banner__art" :src="image" alt="" aria-hidden="true" />
    <div class="ks-room-banner__veil"></div>
    <div class="ks-room-banner__content">
      <p v-if="eyebrow" class="ks-room-banner__eyebrow">{{ eyebrow }}</p>
      <h1 class="ks-room-banner__title">{{ title }}</h1>
      <p v-if="subtitle" class="ks-room-banner__subtitle">{{ subtitle }}</p>
      <div v-if="$slots.actions" class="ks-room-banner__actions"><slot name="actions" /></div>
    </div>
    <div v-if="$slots.default" class="ks-room-banner__aside"><slot /></div>
  </section>
</template>
