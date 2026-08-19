<script setup lang="ts">
import { useGovernorNavigation } from '@/composables/useGovernorNavigation';

const props = defineProps<{
  governorId: string;
  href: string;
}>();

const { visit } = useGovernorNavigation();

function navigate(event: MouseEvent): void {
  if (
    event.defaultPrevented ||
    event.button !== 0 ||
    event.metaKey ||
    event.ctrlKey ||
    event.shiftKey ||
    event.altKey
  ) {
    return;
  }

  event.preventDefault();
  visit({ governorId: props.governorId, path: props.href });
}
</script>

<template>
  <a :href="href" @click="navigate">
    <slot />
  </a>
</template>
