<script setup lang="ts">
withDefaults(
  defineProps<{
    label: string;
    value: string | number;
    icon?: string;
    tone?: 'gold' | 'teal' | 'stone';
    detail?: string;
  }>(),
  { icon: '◆', tone: 'gold', detail: '' },
);
</script>

<template>
  <article class="ks-stat-seal" :data-tone="tone">
    <div class="ks-stat-seal__icon" aria-hidden="true">{{ icon }}</div>
    <div class="ks-stat-seal__copy">
      <small>{{ label }}</small>
      <strong>{{ value }}</strong>
      <span v-if="detail" class="mt-1 block truncate text-xs text-[var(--ks-text-muted)]">{{ detail }}</span>
      <slot />
    </div>
  </article>
</template>
