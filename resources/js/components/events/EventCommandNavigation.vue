<script setup lang="ts">
import { useLocale } from '@/localization';

const props = defineProps<{ capabilities: string[] }>();
const { t } = useLocale();

function has(capability: string): boolean {
  return props.capabilities.includes(capability);
}
</script>

<template>
  <nav class="ks-tab-strip mt-4" aria-label="Event organizer command sections">
    <a href="#schedule" class="ks-tab">{{ t('events.manage.title') }}</a>
    <a v-if="has('phases')" href="#phases" class="ks-tab">
      {{ t('events.phases.manageTitle') }}
    </a>
    <a v-if="has('polls')" href="#polls" class="ks-tab">
      {{ t('events.polls.manageTitle') }}
    </a>
    <a v-if="has('rosters')" href="#rosters" class="ks-tab">
      {{ t('events.rosters.manageTitle') }}
    </a>
    <a v-if="has('rally_guidance') || has('formations')" href="#rallies" class="ks-tab">
      {{ t('events.rallies.manageTitle') }}
    </a>
    <a v-if="has('objectives')" href="#battle-plan" class="ks-tab">
      {{ t('events.objectives.manageTitle') }}
    </a>
    <a v-if="has('results')" href="#results" class="ks-tab">
      {{ t('events.results.manageTitle') }}
    </a>
    <a
      v-if="has('responses') || has('attendance')"
      href="#participants"
      class="ks-tab"
    >
      {{ t('events.manage.participants') }}
    </a>
    <a href="#reminders" class="ks-tab">{{ t('events.manage.reminders') }}</a>
  </nav>
</template>
