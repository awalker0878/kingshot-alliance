<script setup lang="ts">
defineProps<{
  id?: string | undefined;
  message?: string | null | undefined;
}>();
</script>

<template>
  <p
    v-if="message"
    :id="id"
    class="mt-1.5 text-sm text-[var(--ks-red)]"
    role="alert"
    aria-live="polite"
  >
    {{ message }}
  </p>
</template>
