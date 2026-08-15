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

type HistorySummary = {
  events: number;
  player_events: number;
  alliance_events: number;
  kingdom_events: number;
  completed: number;
  absent: number;
  excused: number;
  unresolved: number;
  reliability_percent: number | null;
  contribution_records: number;
};

const props = defineProps<{
  user: { name: string; email: string };
  player: { id: string; name: string };
  summary: HistorySummary;
  filters: {
    from: string | null;
    until: string | null;
    allianceId: string | null;
    kingdomIdAtEvent: string | null;
    eventScope: string | null;
    eventTypeSlug: string | null;
    eventMetricKey: string | null;
    participationOutcome: string | null;
    contributionCategorySlug: string | null;
    limit: number;
  };
  history: TimelineRow[];
}>();

const { formatDate, formatNumber } = useLocale();
const scopeTabs = [
  { label: 'All', value: '' },
  { label: 'Player Events', value: 'player' },
  { label: 'Alliance Events', value: 'alliance' },
  { label: 'Kingdom Events', value: 'kingdom' },
] as const;

const filter = reactive({
  from: props.filters.from ?? '',
  until: props.filters.until ?? '',
  alliance_id: props.filters.allianceId ?? '',
  kingdom_id_at_event: props.filters.kingdomIdAtEvent ?? '',
  event_scope: props.filters.eventScope ?? '',
  event_type_slug: props.filters.eventTypeSlug ?? '',
  event_metric_key: props.filters.eventMetricKey ?? '',
  participation_outcome: props.filters.participationOutcome ?? '',
  contribution_category_slug: props.filters.contributionCategorySlug ?? '',
  limit: String(props.filters.limit),
});

function applyFilters(): void {
  router.get('/contributions/history', { ...filter }, { preserveState: true, replace: true });
}

function selectScope(scope: string): void {
  filter.event_scope = scope;
  applyFilters();
}

function clearFilters(): void {
  Object.assign(filter, {
    from: '',
    until: '',
    alliance_id: '',
    kingdom_id_at_event: '',
    event_scope: '',
    event_type_slug: '',
    event_metric_key: '',
    participation_outcome: '',
    contribution_category_slug: '',
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

function reliabilityLabel(): string {
  if (props.summary.reliability_percent === null) return '—';
  return `${formatNumber(props.summary.reliability_percent)}%`;
}
</script>

<template>
  <Head :title="`Contribution history · ${player.name}`" />

  <AppLayout :user="user">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <header class="space-y-2">
        <p class="text-xs font-semibold tracking-[0.2em] text-amber-300 uppercase">
          My contributions
        </p>
        <h1 class="text-3xl font-semibold text-white">{{ player.name }}</h1>
        <p class="max-w-3xl text-sm text-slate-300">
          Lifetime history for this exact Player across personal, Alliance and Kingdom Events plus
          genuine contribution records. Historical Alliance and Kingdom context stays attached to
          the occurrence where it was recorded.
        </p>
      </header>

      <section
        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6"
        aria-label="Lifetime contribution summary"
      >
        <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
          <p class="text-xs tracking-wide text-slate-500 uppercase">Events</p>
          <p class="mt-2 text-2xl font-semibold text-white">{{ formatNumber(summary.events) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
          <p class="text-xs tracking-wide text-slate-500 uppercase">Reliability</p>
          <p class="mt-2 text-2xl font-semibold text-white">{{ reliabilityLabel() }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
          <p class="text-xs tracking-wide text-slate-500 uppercase">Player</p>
          <p class="mt-2 text-2xl font-semibold text-white">
            {{ formatNumber(summary.player_events) }}
          </p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
          <p class="text-xs tracking-wide text-slate-500 uppercase">Alliance</p>
          <p class="mt-2 text-2xl font-semibold text-white">
            {{ formatNumber(summary.alliance_events) }}
          </p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
          <p class="text-xs tracking-wide text-slate-500 uppercase">Kingdom</p>
          <p class="mt-2 text-2xl font-semibold text-white">
            {{ formatNumber(summary.kingdom_events) }}
          </p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
          <p class="text-xs tracking-wide text-slate-500 uppercase">Other records</p>
          <p class="mt-2 text-2xl font-semibold text-white">
            {{ formatNumber(summary.contribution_records) }}
          </p>
        </div>
      </section>

      <div class="flex flex-wrap gap-2" role="tablist" aria-label="History scope">
        <button
          v-for="tab in scopeTabs"
          :key="tab.value || 'all'"
          type="button"
          class="rounded-full border px-4 py-2 text-sm font-semibold transition"
          :class="
            filter.event_scope === tab.value
              ? 'border-amber-300 bg-amber-300 text-slate-950'
              : 'border-white/10 bg-slate-950/60 text-slate-300 hover:border-white/25'
          "
          :aria-selected="filter.event_scope === tab.value"
          role="tab"
          @click="selectScope(tab.value)"
        >
          {{ tab.label }}
        </button>
      </div>

      <form
        class="grid gap-3 rounded-2xl border border-white/10 bg-slate-950/50 p-4 md:grid-cols-4"
        @submit.prevent="applyFilters"
      >
        <label class="space-y-1 text-xs text-slate-300">
          <span>From</span>
          <input
            v-model="filter.from"
            type="date"
            class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
          />
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Until</span>
          <input
            v-model="filter.until"
            type="date"
            class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
          />
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Participation</span>
          <select
            v-model="filter.participation_outcome"
            class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
          >
            <option value="">Any outcome</option>
            <option value="completed">Completed</option>
            <option value="absent">Absent</option>
            <option value="excused">Excused</option>
            <option value="unresolved">Unresolved</option>
          </select>
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Rows</span>
          <select
            v-model="filter.limit"
            class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
          >
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="200">200</option>
          </select>
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Historical Alliance ID</span>
          <input
            v-model="filter.alliance_id"
            type="text"
            placeholder="Optional ULID"
            class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
          />
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Historical Kingdom ID</span>
          <input
            v-model="filter.kingdom_id_at_event"
            type="text"
            placeholder="Optional ULID"
            class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
          />
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Event type</span>
          <input
            v-model="filter.event_type_slug"
            type="text"
            placeholder="e.g. bear-hunt"
            class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
          />
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Event metric</span>
          <input
            v-model="filter.event_metric_key"
            type="text"
            placeholder="Metric key"
            class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
          />
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Contribution category</span>
          <input
            v-model="filter.contribution_category_slug"
            type="text"
            placeholder="Category slug"
            class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
          />
        </label>
        <div class="flex items-end gap-2 md:col-span-3">
          <button
            type="submit"
            class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950"
          >
            Apply filters
          </button>
          <button
            type="button"
            class="rounded-lg border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200"
            @click="clearFilters"
          >
            Clear
          </button>
        </div>
      </form>

      <div
        v-if="history.length === 0"
        class="rounded-2xl border border-dashed border-white/15 p-10 text-center text-sm text-slate-400"
      >
        No historical records match these filters.
      </div>

      <ol v-else class="space-y-4">
        <li
          v-for="row in history"
          :key="`${row.kind}-${row.event?.occurrenceId ?? row.contribution?.recordId}`"
          class="rounded-2xl border border-white/10 bg-slate-950/60 p-5"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <span
                class="inline-flex rounded-full border border-white/10 px-2.5 py-1 text-[11px] font-semibold tracking-wide text-slate-300 uppercase"
              >
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
            <div>
              <span class="text-slate-500">Scope</span>
              <p class="text-slate-200 capitalize">{{ row.event.scope }}</p>
            </div>
            <div>
              <span class="text-slate-500">Target</span>
              <p class="text-slate-200">{{ row.event.target.displayName }}</p>
            </div>
            <div>
              <span class="text-slate-500">Participation</span>
              <p class="text-slate-200 capitalize">
                {{ humanize(row.event.participation.outcome) }}
              </p>
            </div>
            <div>
              <span class="text-slate-500">Score</span>
              <p class="text-slate-200">{{ scoreLabel(row.event) ?? '—' }}</p>
            </div>
            <div>
              <span class="text-slate-500">Kingdom at Event</span>
              <p class="text-slate-200">{{ row.event.playerContext.kingdomIdAtEvent }}</p>
            </div>
            <div v-if="row.event.playerContext.representedAllianceName" class="md:col-span-2">
              <span class="text-slate-500">Represented Alliance</span>
              <p class="text-slate-200">{{ row.event.playerContext.representedAllianceName }}</p>
            </div>
            <div v-if="row.event.result?.metrics.length" class="md:col-span-4">
              <span class="text-slate-500">Metrics</span>
              <div class="mt-2 flex flex-wrap gap-2">
                <span
                  v-for="metric in row.event.result.metrics"
                  :key="`${metric.key}-${metric.dimensionKey ?? ''}`"
                  class="rounded-lg bg-white/5 px-3 py-1.5 text-xs text-slate-200"
                >
                  {{ metric.key
                  }}<template v-if="metric.dimensionKey"> · {{ metric.dimensionKey }}</template
                  >: {{ metric.value }}<template v-if="metric.unit"> {{ metric.unit }}</template>
                </span>
              </div>
            </div>
          </div>

          <div v-else-if="row.contribution" class="mt-4 grid gap-3 text-sm md:grid-cols-4">
            <div>
              <span class="text-slate-500">Value</span>
              <p class="text-slate-200">{{ row.contribution.value }} {{ row.contribution.unit }}</p>
            </div>
            <div>
              <span class="text-slate-500">Status</span>
              <p class="text-slate-200 capitalize">{{ humanize(row.contribution.status) }}</p>
            </div>
            <div>
              <span class="text-slate-500">Source</span>
              <p class="text-slate-200 capitalize">{{ humanize(row.contribution.source) }}</p>
            </div>
            <div>
              <span class="text-slate-500">Period</span>
              <p class="text-slate-200">
                {{ row.contribution.periodStart }} – {{ row.contribution.periodEnd }}
              </p>
            </div>
          </div>
        </li>
      </ol>
    </div>
  </AppLayout>
</template>
