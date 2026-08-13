<script setup lang="ts">
defineProps<{
  compact?: boolean;
}>();
</script>

<template>
  <span class="inline-flex items-center gap-3" aria-label="Kingshot Alliance">
    <span
      class="relative grid size-10 place-items-center rounded-xl border border-[var(--ks-border-strong)] bg-[var(--ks-gold-soft)] text-lg font-black text-[var(--ks-gold-strong)] shadow-[var(--ks-shadow-gold)]"
      aria-hidden="true"
    >
      <span class="absolute -top-2 text-[10px]">♛</span>
      K
    </span>
    <span v-if="!compact" class="ks-display text-xl font-semibold tracking-tight">
      Kingshot <span class="text-[var(--ks-gold-strong)]">Alliance</span>
    </span>
  </span>
</template>
