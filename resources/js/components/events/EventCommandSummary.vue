<script setup lang="ts">
import StatSeal from '@/components/game/StatSeal.vue';
import { useLocale } from '@/localization';

defineProps<{
  occurrenceCount: number;
  participantCount: number;
  reminderCount: number;
  capabilityCount: number;
}>();

const { t } = useLocale();
</script>

<template>
  <section
    class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4"
    aria-label="Event command summary"
  >
    <StatSeal
      :label="t('events.manage.occurrences')"
      :value="occurrenceCount"
      icon="▦"
    />
    <StatSeal
      :label="t('events.manage.participants')"
      :value="participantCount"
      icon="♟"
      tone="teal"
    />
    <StatSeal
      :label="t('events.manage.reminders')"
      :value="reminderCount"
      icon="⌛"
      tone="stone"
    />
    <StatSeal
      :label="t('events.calendar.viewOptions')"
      :value="capabilityCount"
      icon="✦"
    />
  </section>
</template>
