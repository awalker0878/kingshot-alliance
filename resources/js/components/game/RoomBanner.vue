<script setup lang="ts">
import PageCommandExtension from '@/components/navigation/PageCommandExtension.vue';

withDefaults(
  defineProps<{
    eyebrow: string;
    title: string;
    subtitle?: string;
    image: string;
    compact?: boolean;
  }>(),
  { subtitle: '', compact: false },
);
</script>

<template>
  <section class="ks-room-banner" :class="{ 'ks-room-banner--compact': compact }">
    <img class="ks-room-banner__art" :src="image" alt="" aria-hidden="true" />
    <div class="ks-room-banner__content">
      <p class="ks-room-banner__eyebrow">{{ eyebrow }}</p>
      <h1 class="ks-room-banner__title">{{ title }}</h1>
      <div class="mt-4 flex items-center gap-2" aria-hidden="true">
        <span class="h-px w-14 bg-[var(--ks-gold)]" />
        <span class="h-1.5 w-1.5 rotate-45 border border-[var(--ks-gold)]" />
        <span class="h-px w-28 bg-[linear-gradient(90deg,var(--ks-gold),transparent)]" />
      </div>
      <p v-if="subtitle" class="ks-room-banner__subtitle">{{ subtitle }}</p>
      <div v-if="$slots.actions" class="ks-room-banner__actions"><slot name="actions" /></div>
    </div>
    <div v-if="$slots.aside" class="ks-room-banner__aside"><slot name="aside" /></div>
  </section>
  <PageCommandExtension />
</template>
