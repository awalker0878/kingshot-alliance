<script setup lang="ts">
import { useGovernorNavigation } from '@/composables/useGovernorNavigation';

const props = defineProps<{
  governorId: string;
  href: string;
}>();

const { visit } = useGovernorNavigation();

function navigate(event: MouseEvent): void {
  if (event.defaultPrevented) return;

  // A context-bound destination cannot safely be opened directly in a second tab:
  // the target Governor must first be activated through the server-owned switch path.
  event.preventDefault();
  if (event.button !== 0) return;

  visit({ governorId: props.governorId, path: props.href });
}
</script>

<template>
  <a :href="href" @click="navigate" @auxclick.prevent>
    <slot />
  </a>
</template>
