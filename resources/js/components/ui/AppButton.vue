<script setup lang="ts">
withDefaults(
  defineProps<{
    type?: 'button' | 'submit' | 'reset';
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
    disabled?: boolean;
    busy?: boolean;
    busyLabel?: string;
  }>(),
  { type: 'button', variant: 'primary', disabled: false, busy: false, busyLabel: '' },
);

const classes = {
  primary:
    'border-[rgba(32,178,163,.42)] bg-[linear-gradient(180deg,#0e8178,#07544f)] text-[#f7f1e6] shadow-[inset_0_1px_rgba(255,255,255,.08),0_8px_20px_rgba(0,0,0,.26)] hover:border-[var(--ks-gold-bright)] hover:brightness-110',
  secondary:
    'border-[var(--ks-border-strong)] bg-[var(--ks-gold-soft)] text-[var(--ks-gold-bright)] hover:bg-[rgba(201,154,71,.18)]',
  ghost:
    'border-[var(--ks-border)] bg-black/15 text-[var(--ks-text-secondary)] hover:border-[var(--ks-border-strong)] hover:bg-white/[0.025] hover:text-[var(--ks-text)]',
  danger:
    'border-red-400/30 bg-red-500/10 text-red-200 hover:border-red-300/50 hover:bg-red-500/16',
} as const;
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || busy"
    :aria-busy="busy ? 'true' : undefined"
    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-[var(--ks-radius-sm)] border px-4 py-2.5 text-sm font-[var(--ks-font-display)] font-semibold transition disabled:cursor-not-allowed disabled:opacity-45"
    :class="classes[variant ?? 'primary']"
  >
    <span
      v-if="busy"
      class="h-4 w-4 animate-spin rounded-full border-2 border-current border-e-transparent motion-reduce:animate-none"
      aria-hidden="true"
    />
    <span v-if="busy && busyLabel">{{ busyLabel }}</span>
    <slot v-else />
  </button>
</template>
