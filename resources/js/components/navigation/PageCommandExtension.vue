<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import EventCommandCard from '@/components/events/EventCommandCard.vue';
import type { EventCommandProjection } from '@/types/event-command';

const page = usePage();
const eventCommand = computed<EventCommandProjection | null>(() => {
  const value = (page.props as Record<string, unknown>).eventCommand;
  if (!value || typeof value !== 'object') return null;
  const candidate = value as Partial<EventCommandProjection>;
  if (typeof candidate.eventId !== 'string' || !Array.isArray(candidate.sections)) return null;

  return value as EventCommandProjection;
});
</script>

<template>
  <div v-if="eventCommand" class="mt-4">
    <EventCommandCard :command="eventCommand" />
  </div>
</template>
