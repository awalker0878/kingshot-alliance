<script setup lang="ts">
withDefaults(
  defineProps<{
    type?: 'button' | 'submit' | 'reset';
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
    disabled?: boolean;
  }>(),
  {
    type: 'button',
    variant: 'primary',
    disabled: false,
  },
);

const classes = {
  primary:
    'border border-[var(--ks-border-strong)] bg-gradient-to-b from-blue-500 to-blue-700 text-[var(--ks-ivory)] shadow-lg shadow-blue-950/30 hover:from-blue-400 hover:to-blue-600',
  secondary:
    'border border-[var(--ks-border-strong)] bg-[var(--ks-gold-soft)] text-[var(--ks-gold-strong)] hover:bg-[rgba(226,180,77,0.18)]',
  ghost:
    'border border-[var(--ks-border)] bg-[var(--ks-surface-1)]/70 text-[var(--ks-text)] hover:border-[var(--ks-border-strong)] hover:bg-[var(--ks-surface-2)]',
  danger: 'border border-red-400/30 bg-red-500/10 text-red-200 hover:bg-red-500/20',
} as const;
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-[var(--ks-radius-sm)] px-4 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50"
    :class="classes[variant]"
  >
    <slot />
  </button>
</template>
