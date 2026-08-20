<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    message?: string | null;
    tone?: 'success' | 'warning' | 'danger' | 'info';
  }>(),
  { message: null, tone: 'info' },
);

const tones = {
  success: 'border-emerald-400/25 bg-emerald-500/10 text-emerald-100',
  warning: 'border-amber-400/25 bg-amber-500/10 text-amber-100',
  danger: 'border-red-400/25 bg-red-500/10 text-red-100',
  info: 'border-[var(--ks-border)] bg-[var(--ks-teal-soft)] text-[var(--ks-text-secondary)]',
} as const;

const toneClass = computed(() => tones[props.tone ?? 'info']);
const isDanger = computed(() => props.tone === 'danger');
</script>

<template>
  <p
    v-if="message"
    class="rounded-[var(--ks-radius-md)] border px-4 py-3 text-sm"
    :class="toneClass"
    :role="isDanger ? 'alert' : 'status'"
    :aria-live="isDanger ? 'assertive' : 'polite'"
  >
    {{ message }}
  </p>
</template>
