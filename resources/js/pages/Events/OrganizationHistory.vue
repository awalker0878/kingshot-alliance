<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

type Metric = {
  key: string;
  unit: string | null;
  dimensionKey: string | null;
  value: string | number;
};

type Participant = {
  playerId: string;
  playerName: string;
  kingdomIdAtEvent: string;
  representedAllianceId: string | null;
  representedAllianceName: string | null;
  representedAllianceTag: string | null;
  contextFrozenAt: string;
  result: {
    outcome: string | null;
    score: number | null;
    rank: number | null;
    metrics: Metric[];
  } | null;
};

type AllianceResult = {
  allianceId: string;
  allianceName: string;
  allianceTag: string | null;
  outcome: string | null;
  score: number | null;
  rank: number | null;
  metrics: Metric[];
};

type HistoryRow = {
  occurrenceId: string;
  eventType: { slug: string; nameKey: string };
  scope: 'alliance' | 'kingdom';
  targetId: string;
  targetDisplayName: string;
  targetSecondaryLabel: string | null;
  title: string | null;
  startsAt: string;
  endsAt: string;
  occurrenceStatus: string;
  result: {
    outcome: string | null;
    score: number | null;
    opponentScore: number | null;
    rank: number | null;
    metrics: Metric[];
  } | null;
  participants: Participant[];
  allianceResults: AllianceResult[];
};

type IntelligenceSeries = {
  eventTypeScopeId: string;
  eventTypeSlug: string;
  eventScope: string;
  metricKey: string;
  dimensionKey: string | null;
  labelKey: string;
  unit: string | null;
  aggregation: string;
  higherIsBetter: boolean | null;
  samples: number;
  average: number;
  minimum: number;
  maximum: number;
  best: number | null;
  latest: { occurrenceId: string; startsAt: string; value: number };
};

type IntelligenceLeaderboard = {
  eventTypeScopeId: string;
  eventTypeSlug: string;
  metricKey: string;
  dimensionKey: string | null;
  unit: string | null;
  aggregation: string;
  entries: Array<{
    playerId: string;
    playerName: string;
    samples: number;
    value: number;
    average: number;
    best: number;
    latest: number;
  }>;
};

const props = defineProps<{
  user: { name: string; email: string };
  organization: {
    id: string;
    scope: 'alliance' | 'kingdom';
    name: string;
    secondaryLabel: string | null;
  };
  filters: { eventTypeSlug: string | null; from: string | null; until: string | null; limit: number };
  intelligence: { series: IntelligenceSeries[]; leaderboards: IntelligenceLeaderboard[] };
  history: HistoryRow[];
}>();

const { formatDate, formatNumber } = useLocale();
const filter = reactive({
  event_type_slug: props.filters.eventTypeSlug ?? '',
  from: props.filters.from ?? '',
  until: props.filters.until ?? '',
  limit: String(props.filters.limit),
});

function routePath(): string {
  return props.organization.scope === 'alliance'
    ? `/alliances/${props.organization.id}/events/history`
    : `/kingdoms/${props.organization.id}/events/history`;
}

function applyFilters(): void {
  router.get(routePath(), { ...filter }, { preserveState: true, replace: true });
}

function clearFilters(): void {
  Object.assign(filter, { event_type_slug: '', from: '', until: '', limit: '100' });
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
  if (!value) return '—';
  return value.replaceAll('_', ' ');
}

function number(value: number | null): string {
  return value === null ? '—' : formatNumber(value);
}

function metricName(series: { eventTypeSlug: string; metricKey: string; dimensionKey: string | null }): string {
  const dimension = series.dimensionKey ? ` · ${series.dimensionKey}` : '';
  return `${series.eventTypeSlug} · ${series.metricKey}${dimension}`;
}
</script>

<template>
  <Head :title="`Event history · ${organization.name}`" />

  <AppLayout
    :user="user"
    :player-alliance-name="organization.scope === 'alliance' ? organization.name : null"
    :has-player-alliance="organization.scope === 'alliance'"
  >
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <header class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">
          {{ organization.scope }} Event history
        </p>
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-3xl font-semibold text-white">{{ organization.name }}</h1>
          <span v-if="organization.secondaryLabel" class="text-sm text-slate-400">{{ organization.secondaryLabel }}</span>
        </div>
        <p class="max-w-3xl text-sm text-slate-300">
          History is owned by the original Event target. Current authority controls access, while participant names,
          represented Alliances, and Kingdom context remain frozen at the occurrence where they were recorded.
        </p>
      </header>

      <form class="grid gap-3 rounded-2xl border border-white/10 bg-slate-950/50 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
        <label class="space-y-1 text-xs text-slate-300">
          <span>Event type</span>
          <input v-model="filter.event_type_slug" type="text" placeholder="All types" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white" />
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>From</span>
          <input v-model="filter.from" type="date" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white" />
        </label>
        <label class="space-y-1 text-xs text-slate-300">
          <span>Until</span>
          <input v-model="filter.until" type="date" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white" />
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

      <section v-if="intelligence.series.length" class="space-y-3">
        <div>
          <h2 class="text-lg font-semibold text-white">Compatible metric trends</h2>
          <p class="mt-1 text-xs text-slate-400">Metrics are compared only within the same Event Type/scope and dimension. There is no universal cross-Event score.</p>
        </div>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          <article v-for="series in intelligence.series" :key="`${series.eventTypeScopeId}-${series.metricKey}-${series.dimensionKey ?? ''}`" class="rounded-xl border border-white/10 bg-slate-950/60 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-300">{{ metricName(series) }}</p>
            <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
              <div><span class="text-slate-500">Average</span><p class="font-semibold text-white">{{ formatNumber(series.average) }}<span v-if="series.unit"> {{ series.unit }}</span></p></div>
              <div><span class="text-slate-500">Best</span><p class="font-semibold text-white">{{ number(series.best) }}<span v-if="series.best !== null && series.unit"> {{ series.unit }}</span></p></div>
              <div><span class="text-slate-500">Samples</span><p class="font-semibold text-white">{{ formatNumber(series.samples) }}</p></div>
            </div>
            <p class="mt-3 text-xs text-slate-500">Latest {{ dateTime(series.latest.startsAt) }} · {{ formatNumber(series.latest.value) }}<span v-if="series.unit"> {{ series.unit }}</span></p>
          </article>
        </div>
      </section>

      <section v-if="intelligence.leaderboards.length" class="space-y-3">
        <div>
          <h2 class="text-lg font-semibold text-white">Event-specific leaderboards</h2>
          <p class="mt-1 text-xs text-slate-400">Each board uses the metric's own aggregation and never mixes incompatible Event families.</p>
        </div>
        <div class="grid gap-3 xl:grid-cols-2">
          <article v-for="board in intelligence.leaderboards" :key="`${board.eventTypeScopeId}-${board.metricKey}-${board.dimensionKey ?? ''}`" class="rounded-xl border border-white/10 bg-slate-950/60 p-4">
            <div class="flex items-center justify-between gap-3">
              <h3 class="font-semibold text-white">{{ metricName(board) }}</h3>
              <span class="text-xs uppercase tracking-wide text-slate-500">{{ board.aggregation }}</span>
            </div>
            <ol class="mt-3 space-y-2">
              <li v-for="(entry, index) in board.entries.slice(0, 10)" :key="entry.playerId" class="flex items-center justify-between gap-3 rounded-lg bg-white/5 px-3 py-2 text-sm">
                <span class="text-slate-200">{{ index + 1 }}. {{ entry.playerName }}</span>
                <span class="font-semibold text-amber-200">{{ formatNumber(entry.value) }}<span v-if="board.unit"> {{ board.unit }}</span></span>
              </li>
            </ol>
          </article>
        </div>
      </section>

      <div v-if="history.length === 0" class="rounded-2xl border border-dashed border-white/15 p-10 text-center text-sm text-slate-400">
        No historical Events match these filters.
      </div>

      <template v-else>
        <section v-for="event in history" :key="event.occurrenceId" class="rounded-2xl border border-white/10 bg-slate-950/60 p-5">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div class="flex flex-wrap gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                <span>{{ event.eventType.slug }}</span>
                <span>·</span>
                <span>{{ humanize(event.occurrenceStatus) }}</span>
              </div>
              <h2 class="mt-2 text-xl font-semibold text-white">{{ event.title || event.targetDisplayName }}</h2>
              <p class="mt-1 text-sm text-slate-400">{{ dateTime(event.startsAt) }} – {{ dateTime(event.endsAt) }}</p>
            </div>
            <div v-if="event.result" class="rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-right">
              <p class="text-xs uppercase tracking-wide text-slate-500">Result</p>
              <p class="mt-1 text-lg font-semibold text-white">{{ number(event.result.score) }}</p>
              <p class="text-xs capitalize text-slate-400">{{ humanize(event.result.outcome) }}</p>
            </div>
          </div>

          <div v-if="event.result?.metrics.length" class="mt-4 flex flex-wrap gap-2">
            <span v-for="metric in event.result.metrics" :key="`${metric.key}-${metric.dimensionKey ?? ''}`" class="rounded-lg bg-white/5 px-3 py-1.5 text-xs text-slate-200">
              {{ metric.key }}<template v-if="metric.dimensionKey"> · {{ metric.dimensionKey }}</template>: {{ metric.value }}<template v-if="metric.unit"> {{ metric.unit }}</template>
            </span>
          </div>

          <div v-if="organization.scope === 'kingdom' && event.allianceResults.length" class="mt-6">
            <h3 class="text-sm font-semibold text-white">Alliance results</h3>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              <article v-for="result in event.allianceResults" :key="result.allianceId" class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <p class="font-medium text-white">{{ result.allianceName }}</p>
                    <p v-if="result.allianceTag" class="text-xs text-slate-500">{{ result.allianceTag }}</p>
                  </div>
                  <p class="font-semibold text-amber-200">{{ number(result.score) }}</p>
                </div>
                <p class="mt-2 text-xs capitalize text-slate-400">{{ humanize(result.outcome) }}</p>
              </article>
            </div>
          </div>

          <div class="mt-6">
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-sm font-semibold text-white">Historical participants</h3>
              <span class="text-xs text-slate-500">{{ event.participants.length }} Players</span>
            </div>
            <div v-if="event.participants.length" class="mt-3 overflow-x-auto rounded-xl border border-white/10">
              <table class="min-w-full divide-y divide-white/10 text-sm">
                <thead class="bg-white/5 text-left text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th class="px-4 py-3">Player</th>
                    <th class="px-4 py-3">Represented Alliance</th>
                    <th class="px-4 py-3">Score</th>
                    <th class="px-4 py-3">Outcome</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                  <tr v-for="participant in event.participants" :key="participant.playerId">
                    <td class="px-4 py-3 text-white">{{ participant.playerName }}</td>
                    <td class="px-4 py-3 text-slate-300">{{ participant.representedAllianceName ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-300">{{ number(participant.result?.score ?? null) }}</td>
                    <td class="px-4 py-3 capitalize text-slate-300">{{ humanize(participant.result?.outcome ?? null) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="mt-3 text-sm text-slate-500">No Player context was captured for this occurrence.</p>
          </div>
        </section>
      </template>
    </div>
  </AppLayout>
</template>
