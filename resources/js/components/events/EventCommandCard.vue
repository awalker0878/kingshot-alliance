<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

import { useLocale } from '@/localization';
import type { EventCommandItem, EventCommandProjection } from '@/types/event-command';

const props = defineProps<{ command: EventCommandProjection }>();
const { t, formatDate } = useLocale();

const allItems = computed(() => props.command.sections.flatMap((section) => section.items));
const attentionItems = computed(() =>
  allItems.value.filter(
    (item) =>
      (item.severity === 'blocking' && ['needs_attention', 'unknown'].includes(item.status)) ||
      (item.severity === 'warning' && ['warning', 'unknown'].includes(item.status)),
  ),
);
const settledSections = computed(() =>
  props.command.sections
    .map((section) => ({
      ...section,
      items: section.items.filter(
        (item) => !attentionItems.value.some((attention) => attention.code === item.code),
      ),
    }))
    .filter((section) => section.items.length > 0),
);
const phase = computed<'readiness' | 'closeout'>(() =>
  props.command.sections.some((section) => section.phase === 'closeout') ? 'closeout' : 'readiness',
);

function params(item: EventCommandItem): Record<string, string | number> {
  return Object.fromEntries(
    Object.entries(item.messageParameters).filter(
      (entry): entry is [string, string | number] =>
        typeof entry[1] === 'string' || typeof entry[1] === 'number',
    ),
  );
}
function stateLabel(): string {
  if (props.command.eventStatus === 'cancelled' || props.command.occurrenceStatus === 'cancelled') {
    return t('events.command.states.cancelled');
  }
  if (!props.command.selectedOccurrenceId || !props.command.state) {
    return t('events.command.states.unavailable');
  }
  return t(`events.command.states.${props.command.state}`);
}
function itemStatusLabel(item: EventCommandItem): string {
  return t(`events.command.status.${item.status}`);
}
function ownerLabel(item: EventCommandItem): string {
  return t(`events.command.owners.${item.owner}`);
}
function selectOccurrence(event: Event): void {
  const target = event.target as HTMLSelectElement;
  if (!target.value || target.value === props.command.selectedOccurrenceId) return;
  router.get(
    `/events/${props.command.eventId}/manage`,
    { occurrence: target.value },
    { preserveScroll: true, preserveState: false, replace: true },
  );
}
</script>

<template>
  <section
    id="event-command"
    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-700 dark:bg-slate-900"
    :aria-labelledby="'event-command-title'"
  >
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
          {{ phase === 'closeout' ? t('events.command.closeoutEyebrow') : t('events.command.eyebrow') }}
        </p>
        <h2 id="event-command-title" class="mt-1 text-xl font-semibold text-slate-950 dark:text-white">
          {{ phase === 'closeout' ? t('events.command.closeoutTitle') : t('events.command.title') }}
        </h2>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
          <strong class="font-semibold text-slate-900 dark:text-white">{{ stateLabel() }}</strong>
          <span v-if="command.startsAt" aria-hidden="true">·</span>
          <span v-if="command.startsAt">{{ formatDate(command.startsAt) }}</span>
          <span v-if="command.timezone" class="text-slate-500 dark:text-slate-400">{{ command.timezone }}</span>
        </div>
      </div>

      <label v-if="command.occurrences.length > 1" class="text-sm font-medium text-slate-700 dark:text-slate-200">
        <span class="mb-1 block">{{ t('events.command.occurrence') }}</span>
        <select
          class="min-w-56 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          :value="command.selectedOccurrenceId ?? ''"
          @change="selectOccurrence"
        >
          <option v-for="occurrence in command.occurrences" :key="occurrence.id" :value="occurrence.id">
            {{ formatDate(occurrence.startsAt) }} · {{ t(`events.occurrenceStatuses.${occurrence.status}`) }}
          </option>
        </select>
      </label>
    </div>

    <div
      v-if="!command.selectedOccurrenceId"
      class="mt-5 rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300"
    >
      {{ t('events.command.noOccurrence') }}
    </div>

    <template v-else>
      <div class="mt-5 flex flex-wrap gap-2" aria-live="polite">
        <span
          v-if="command.blockerCount > 0"
          class="rounded-full bg-rose-100 px-3 py-1 text-sm font-semibold text-rose-900 dark:bg-rose-950 dark:text-rose-100"
        >
          {{ t('events.command.blockers', { count: command.blockerCount }) }}
        </span>
        <span
          v-if="command.warningCount > 0"
          class="rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-100"
        >
          {{ t('events.command.warnings', { count: command.warningCount }) }}
        </span>
        <span
          v-if="command.blockerCount === 0 && command.warningCount === 0"
          class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100"
        >
          {{ phase === 'closeout' ? t('events.command.closeoutClear') : t('events.command.readinessClear') }}
        </span>
      </div>

      <ul v-if="attentionItems.length > 0" class="mt-5 space-y-3" :aria-label="t('events.command.attentionItems')">
        <li
          v-for="item in attentionItems"
          :key="item.code"
          class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
        >
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-semibold text-slate-950 dark:text-white">{{ t(item.messageKey, params(item)) }}</span>
                <span class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  {{ itemStatusLabel(item) }}
                </span>
              </div>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ t('events.command.owner', { owner: ownerLabel(item) }) }}
                <template v-if="item.classification === 'alliance_strategy'">
                  · {{ t('events.command.classifications.alliance_strategy') }}
                </template>
                <template v-else-if="item.classification === 'evidence'">
                  · {{ t('events.command.classifications.evidence') }}
                </template>
              </p>
            </div>
            <a
              v-if="item.handoff"
              :href="item.handoff.href"
              class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800"
            >
              {{ t(item.handoff.labelKey) }}
            </a>
          </div>
        </li>
      </ul>

      <details v-if="settledSections.length > 0" class="mt-5 rounded-xl border border-slate-200 dark:border-slate-700">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-800 dark:text-slate-100">
          {{ t('events.command.completedChecks') }}
        </summary>
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">
          <div v-for="section in settledSections" :key="section.key" class="py-2 first:pt-0 last:pb-0">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t(section.labelKey) }}</h3>
            <ul class="mt-1 space-y-1 text-sm text-slate-600 dark:text-slate-300">
              <li v-for="item in section.items" :key="item.code" class="flex items-start gap-2">
                <span aria-hidden="true">{{ item.status === 'complete' ? '✓' : '•' }}</span>
                <span>
                  {{ t(item.messageKey, params(item)) }}
                  <span class="sr-only"> — {{ itemStatusLabel(item) }}</span>
                </span>
              </li>
            </ul>
          </div>
        </div>
      </details>
    </template>
  </section>
</template>
