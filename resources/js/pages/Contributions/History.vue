<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

type EventMetric = {
  key: string;
  labelKey: string;
  unit: string | null;
  dimensionKey: string | null;
  value: string | number;
};

type EventHistory = {
  occurrenceId: string;
  eventType: { slug: string; nameKey: string };
  scope: string;
  title: string | null;
  startsAt: string;
  target: { displayName: string };
  playerContext: {
    playerName: string;
    representedAllianceName: string | null;
    kingdomIdAtEvent: string;
  };
  participation: { outcome: string | null };
  result: {
    outcome: string | null;
    score: number | null;
    rank: number | null;
    metrics: EventMetric[];
  } | null;
};

type ContributionHistory = {
  recordId: string;
  allianceId: string;
  categoryName: string;
  unit: string;
  value: string;
  source: string;
  dataClass: string;
  status: string;
  periodStart: string;
  periodEnd: string;
  recordedAt: string;
};

type TimelineRow = {
  kind: 'event' | 'contribution';
  occurredAt: string;
  event: EventHistory | null;
  contribution: ContributionHistory | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  player: { id: string; name: string };
  filters: {
    from: string | null;
    until: string | null;
    allianceId: string | null;
    eventScope: string | null;
    eventTypeSlug: string | null;
    eventMetricKey: string | null;
    participationOutcome: string | null;
    limit: number;
  };
  history: TimelineRow[];
}>();

const { formatDate, formatNumber } = useLocale();
const filter = reactive({
  from: props.filters.from ?? '',
  until: props.filters.until ?? '',
  alliance_id: props.filters.allianceId ?? '',
  event_scope: props.filters.eventScope ?? '',
  event_type_slug: props.filters.eventTypeSlug ?? '',
  event_metric_key: props.filters.eventMetricKey ?? '',
  participation_outcome: props.filters.participationOutcome ?? '',
  limit: String(props.filters.limit),
});

function applyFilters(): void {
  router.get('/contributions/history', { ...filter }, { preserveState: true, replace: true });
}

function clearFilters(): void {
  Object.assign(filter, {
    from: '',
    until: '',
    alliance_id: '',
    event_scope: '',
    event_type_slug: '',
    event_metric_key: '',
    participation_outcome: '',
    limit: '100',
  });
  applyFilters();
}

function dateTime(value: string): string {
  return formatDate(value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

function humanize(value: string | null): string {
  if (!value) return 'Unresolved';
  return value.replaceAll('_', ' ');
}

function scoreLabel(event: EventHistory): string | null {
  if (event.result?.score === null || event.result?.score === undefined) return null;
  return formatNumber(event.result.score);
}
</script>

<template>
  <Head :title="`Contribution history · ${player.name}`" />

  <AppLayout :user="user">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <header class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">Player history</p>
        <h1 class="text-3xl font-semibold text-white">{{ player.name }}</h1>
        <p class="max-w-3xl text-sm text-slate-300">
          A single exact-Player timeline across Event participation/results and genuine contribution records.
          Historical Alliance and Kingdom context stays attached to the occurrence where it was recorded.
        </p>
      </header>

      <form class="grid gap-3 rounded-2xl border border-white/10 bg-slate-950/50 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
        <label class="space-y-1 text-xs text-slate-300">
          <span>From</span>
          <input v-model="filter.from" type="date" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white" />
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Until</span>
          <input v-model="filter.until" type="date" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white" />
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Event scope</span>
          <select v-model="filter.event_scope" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white">
            <option value="">All scopes</option>
            <option value="player">Player</option>
            <option value="alliance">Alliance</option>
            <option value="kingdom">Kingdom</option>
          </select>
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Participation</span>
          <select v-model="filter.participation_outcome" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white">
            <option value="">Any outcome</option>
            <option value="completed">Completed</option>
            <option value="absent">Absent</option>
            <option value="excused">Excused</option>
            <option value="unresolved">Unresolved</option>
          </select>
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Historical Alliance ID</span>
          <input v-model="filter.alliance_id" type="text" placeholder="Optional ULID" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white" />
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Event type</span>
          <input v-model="filter.event_type_slug" type="text" placeholder="e.g. custom" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white" />
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Event metric</span>
          <input v-model="filter.event_metric_key" type="text" placeholder="Metric key" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white" />
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Rows</span>
          <select v-model="filter.limit" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white">
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="200">200</option>
          </select>
        </label>
        <div class="flex gap-2 md:col-span-4">
          <button type="submit" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950">Apply filters</button>
          <button type="button" class="rounded-lg border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200" @click="clearFilters">Clear</button>
        </div>
      </form>

      <div v-if="history.length === 0" class="rounded-2xl border border-dashed border-white/15 p-10 text-center text-sm text-slate-400">
        No historical records match these filters.
      </div>

      <ol v-else class="space-y-4">
        <li v-for="row in history" :key="`${row.kind}-${row.event?.occurrenceId ?? row.contribution?.recordId}`" class="rounded-2xl border border-white/10 bg-slate-950/60 p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <span class="inline-flex rounded-full border border-white/10 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
                {{ row.kind }}
              </span>
              <h2 v-if="row.event" class="mt-3 text-lg font-semibold text-white">
                {{ row.event.title || row.event.eventType.slug }}
              </h2>
              <h2 v-else-if="row.contribution" class="mt-3 text-lg font-semibold text-white">
                {{ row.contribution.categoryName }}
              </h2>
            </div>
            <time class="text-xs text-slate-400">{{ dateTime(row.occurredAt) }}</time>
          </div>

          <div v-if="row.event" class="mt-4 grid gap-3 text-sm md:grid-cols-4">
            <div><span class="text-slate-500">Scope</span><p class="capitalize text-slate-200">{{ row.event.scope }}</p></div>
            <div><span class="text-slate-500">Target</span><p class="text-slate-200">{{ row.event.target.displayName }}</p></div>
            <div><span class="text-slate-500">Participation</span><p class="capitalize text-slate-200">{{ humanize(row.event.participation.outcome) }}</p></div>
            <div><span class="text-slate-500">Score</span><p class="text-slate-200">{{ scoreLabel(row.event) ?? '—' }}</p></div>
            <div v-if="row.event.playerContext.representedAllianceName" class="md:col-span-2">
              <span class="text-slate-500">Represented Alliance</span>
              <p class="text-slate-200">{{ row.event.playerContext.representedAllianceName }}</p>
            </div>
            <div v-if="row.event.result?.metrics.length" class="md:col-span-4">
              <span class="text-slate-500">Metrics</span>
              <div class="mt-2 flex flex-wrap gap-2">
                <span v-for="metric in row.event.result.metrics" :key="`${metric.key}-${metric.dimensionKey ?? ''}`" class="rounded-lg bg-white/5 px-3 py-1.5 text-xs text-slate-200">
                  {{ metric.key }}<template v-if="metric.dimensionKey"> · {{ metric.dimensionKey }}</template>: {{ metric.value }}<template v-if="metric.unit"> {{ metric.unit }}</template>
                </span>
              </div>
            </div>
          </div>

          <div v-else-if="row.contribution" class="mt-4 grid gap-3 text-sm md:grid-cols-4">
            <div><span class="text-slate-500">Value</span><p class="text-slate-200">{{ row.contribution.value }} {{ row.contribution.unit }}</p></div>
            <div><span class="text-slate-500">Status</span><p class="capitalize text-slate-200">{{ humanize(row.contribution.status) }}</p></div>
            <div><span class="text-slate-500">Source</span><p class="capitalize text-slate-200">{{ humanize(row.contribution.source) }}</p></div>
            <div><span class="text-slate-500">Period</span><p class="text-slate-200">{{ row.contribution.periodStart }} – {{ row.contribution.periodEnd }}</p></div>
          </div>
        </li>
      </ol>
    </div>
  </AppLayout>
</template>
