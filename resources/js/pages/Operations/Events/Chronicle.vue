<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

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

type StatusEvidence = {
  total: number;
  byStatus: Record<string, number>;
};

type OperationalEvidence = {
  attendance: StatusEvidence;
  roster: StatusEvidence;
  rallies: StatusEvidence;
  objectives: StatusEvidence & { assignments: number };
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
  evidence: OperationalEvidence;
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
  filters: {
    eventTypeSlug: string | null;
    from: string | null;
    until: string | null;
    limit: number;
  };
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

function metricName(series: {
  eventTypeSlug: string;
  metricKey: string;
  dimensionKey: string | null;
}): string {
  const dimension = series.dimensionKey ? ` · ${series.dimensionKey}` : '';
  return `${series.eventTypeSlug} · ${series.metricKey}${dimension}`;
}

function evidenceDetail(evidence: StatusEvidence): string {
  const details = Object.entries(evidence.byStatus)
    .filter(([, count]) => count > 0)
    .map(([status, count]) => `${humanize(status)} ${formatNumber(count)}`);

  return details.length > 0 ? details.join(' · ') : 'No recorded evidence';
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
        <p class="text-xs font-semibold tracking-[0.2em] text-amber-300 uppercase">
          {{ organization.scope }} Event history
        </p>
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-3xl font-semibold text-[var(--ks-ivory)]">{{ organization.name }}</h1>
          <span v-if="organization.secondaryLabel" class="text-sm text-[var(--ks-muted)]">{{
            organization.secondaryLabel
          }}</span>
        </div>
        <p class="max-w-3xl text-sm text-[var(--ks-muted)]">
          History is owned by the original Event target. Current authority controls access, while
          Governor names, represented Alliances, and Kingdom remain frozen at the occurrence where
          they were recorded.
        </p>
      </header>

      <form
        class="grid gap-3 rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.70)] p-4 md:grid-cols-4"
        @submit.prevent="applyFilters"
      >
        <label class="space-y-1 text-xs text-[var(--ks-muted)]">
          <span>Event type</span>
          <input
            v-model="filter.event_type_slug"
            type="text"
            placeholder="All types"
            class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
          />
        </label>
        <label class="space-y-1 text-xs text-[var(--ks-muted)]">
          <span>From</span>
          <input
            v-model="filter.from"
            type="date"
            class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
          />
        </label>
        <label class="space-y-1 text-xs text-[var(--ks-muted)]">
          <span>Until</span>
          <input
            v-model="filter.until"
            type="date"
            class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
          />
        </label>
        <label class="space-y-1 text-xs text-[var(--ks-muted)]">
          <span>Rows</span>
          <select
            v-model="filter.limit"
            class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
          >
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="200">200</option>
          </select>
        </label>
        <div class="flex gap-2 md:col-span-4">
          <button
            type="submit"
            class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-[var(--ks-ink)]"
          >
            Apply filters
          </button>
          <button
            type="button"
            class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-ivory)]"
            @click="clearFilters"
          >
            Clear
          </button>
        </div>
      </form>

      <section v-if="intelligence.series.length" class="space-y-3">
        <div>
          <h2 class="text-lg font-semibold text-[var(--ks-ivory)]">Compatible metric trends</h2>
          <p class="mt-1 text-xs text-[var(--ks-muted)]">
            Metrics are compared only within the same Event Type/scope and dimension. There is no
            universal cross-Event score.
          </p>
        </div>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="series in intelligence.series"
            :key="`${series.eventTypeScopeId}-${series.metricKey}-${series.dimensionKey ?? ''}`"
            class="rounded-xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.78)] p-4"
          >
            <p class="text-xs font-semibold tracking-wide text-amber-300 uppercase">
              {{ metricName(series) }}
            </p>
            <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
              <div>
                <span class="text-[var(--ks-muted)]">Average</span>
                <p class="font-semibold text-[var(--ks-ivory)]">
                  {{ formatNumber(series.average)
                  }}<span v-if="series.unit"> {{ series.unit }}</span>
                </p>
              </div>
              <div>
                <span class="text-[var(--ks-muted)]">Best</span>
                <p class="font-semibold text-[var(--ks-ivory)]">
                  {{ number(series.best)
                  }}<span v-if="series.best !== null && series.unit"> {{ series.unit }}</span>
                </p>
              </div>
              <div>
                <span class="text-[var(--ks-muted)]">Samples</span>
                <p class="font-semibold text-[var(--ks-ivory)]">
                  {{ formatNumber(series.samples) }}
                </p>
              </div>
            </div>
            <p class="mt-3 text-xs text-[var(--ks-muted)]">
              Latest {{ dateTime(series.latest.startsAt) }} · {{ formatNumber(series.latest.value)
              }}<span v-if="series.unit"> {{ series.unit }}</span>
            </p>
          </article>
        </div>
      </section>

      <section v-if="intelligence.leaderboards.length" class="space-y-3">
        <div>
          <h2 class="text-lg font-semibold text-[var(--ks-ivory)]">Event-specific leaderboards</h2>
          <p class="mt-1 text-xs text-[var(--ks-muted)]">
            Each board uses the metric's own aggregation and never mixes incompatible Event
            families.
          </p>
        </div>
        <div class="grid gap-3 xl:grid-cols-2">
          <article
            v-for="board in intelligence.leaderboards"
            :key="`${board.eventTypeScopeId}-${board.metricKey}-${board.dimensionKey ?? ''}`"
            class="rounded-xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.78)] p-4"
          >
            <div class="flex items-center justify-between gap-3">
              <h3 class="font-semibold text-[var(--ks-ivory)]">{{ metricName(board) }}</h3>
              <span class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">{{
                board.aggregation
              }}</span>
            </div>
            <ol class="mt-3 space-y-2">
              <li
                v-for="(entry, index) in board.entries.slice(0, 10)"
                :key="entry.playerId"
                class="flex items-center justify-between gap-3 rounded-lg bg-[rgba(210,163,75,.05)] px-3 py-2 text-sm"
              >
                <span class="text-[var(--ks-ivory)]">{{ index + 1 }}. {{ entry.playerName }}</span>
                <span class="font-semibold text-amber-200"
                  >{{ formatNumber(entry.value)
                  }}<span v-if="board.unit"> {{ board.unit }}</span></span
                >
              </li>
            </ol>
          </article>
        </div>
      </section>

      <div
        v-if="history.length === 0"
        class="rounded-2xl border border-dashed border-[rgba(210,163,75,.30)] p-10 text-center text-sm text-[var(--ks-muted)]"
      >
        No historical Events match these filters.
      </div>

      <template v-else>
        <section
          v-for="event in history"
          :key="event.occurrenceId"
          class="rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.78)] p-5"
        >
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div
                class="flex flex-wrap gap-2 text-[11px] font-semibold tracking-wide text-[var(--ks-muted)] uppercase"
              >
                <span>{{ event.eventType.slug }}</span>
                <span>·</span>
                <span>{{ humanize(event.occurrenceStatus) }}</span>
              </div>
              <h2 class="mt-2 text-xl font-semibold text-[var(--ks-ivory)]">
                {{ event.title || event.targetDisplayName }}
              </h2>
              <p class="mt-1 text-sm text-[var(--ks-muted)]">
                {{ dateTime(event.startsAt) }} – {{ dateTime(event.endsAt) }}
              </p>
            </div>
            <div
              v-if="event.result"
              class="rounded-xl border border-[var(--ks-border)] bg-[rgba(210,163,75,.05)] px-4 py-3 text-right"
            >
              <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">Result</p>
              <p class="mt-1 text-lg font-semibold text-[var(--ks-ivory)]">
                {{ number(event.result.score) }}
              </p>
              <p class="text-xs text-[var(--ks-muted)] capitalize">
                {{ humanize(event.result.outcome) }}
              </p>
            </div>
          </div>

          <div v-if="event.result?.metrics.length" class="mt-4 flex flex-wrap gap-2">
            <span
              v-for="metric in event.result.metrics"
              :key="`${metric.key}-${metric.dimensionKey ?? ''}`"
              class="rounded-lg bg-[rgba(210,163,75,.05)] px-3 py-1.5 text-xs text-[var(--ks-ivory)]"
            >
              {{ metric.key
              }}<template v-if="metric.dimensionKey"> · {{ metric.dimensionKey }}</template
              >: {{ metric.value }}<template v-if="metric.unit"> {{ metric.unit }}</template>
            </span>
          </div>

          <div
            class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            aria-label="Historical operational evidence"
          >
            <article
              class="rounded-xl border border-[var(--ks-border)] bg-[rgba(210,163,75,.05)] p-4"
            >
              <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">Attendance</p>
              <p class="mt-1 text-xl font-semibold text-[var(--ks-ivory)]">
                {{ formatNumber(event.evidence.attendance.total) }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)] capitalize">
                {{ evidenceDetail(event.evidence.attendance) }}
              </p>
            </article>
            <article
              class="rounded-xl border border-[var(--ks-border)] bg-[rgba(210,163,75,.05)] p-4"
            >
              <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">Roster</p>
              <p class="mt-1 text-xl font-semibold text-[var(--ks-ivory)]">
                {{ formatNumber(event.evidence.roster.total) }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)] capitalize">
                {{ evidenceDetail(event.evidence.roster) }}
              </p>
            </article>
            <article
              class="rounded-xl border border-[var(--ks-border)] bg-[rgba(210,163,75,.05)] p-4"
            >
              <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
                Rally assignments
              </p>
              <p class="mt-1 text-xl font-semibold text-[var(--ks-ivory)]">
                {{ formatNumber(event.evidence.rallies.total) }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)] capitalize">
                {{ evidenceDetail(event.evidence.rallies) }}
              </p>
            </article>
            <article
              class="rounded-xl border border-[var(--ks-border)] bg-[rgba(210,163,75,.05)] p-4"
            >
              <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">Objectives</p>
              <p class="mt-1 text-xl font-semibold text-[var(--ks-ivory)]">
                {{ formatNumber(event.evidence.objectives.total) }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ formatNumber(event.evidence.objectives.assignments) }} assignments
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)] capitalize">
                {{ evidenceDetail(event.evidence.objectives) }}
              </p>
            </article>
          </div>

          <div v-if="organization.scope === 'kingdom' && event.allianceResults.length" class="mt-6">
            <h3 class="text-sm font-semibold text-[var(--ks-ivory)]">Alliance results</h3>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              <article
                v-for="result in event.allianceResults"
                :key="result.allianceId"
                class="rounded-xl border border-[var(--ks-border)] bg-[rgba(210,163,75,.05)] p-4"
              >
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <p class="font-medium text-[var(--ks-ivory)]">{{ result.allianceName }}</p>
                    <p v-if="result.allianceTag" class="text-xs text-[var(--ks-muted)]">
                      {{ result.allianceTag }}
                    </p>
                  </div>
                  <p class="font-semibold text-amber-200">{{ number(result.score) }}</p>
                </div>
                <p class="mt-2 text-xs text-[var(--ks-muted)] capitalize">
                  {{ humanize(result.outcome) }}
                </p>
              </article>
            </div>
          </div>

          <div class="mt-6">
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-sm font-semibold text-[var(--ks-ivory)]">Historical participants</h3>
              <span class="text-xs text-[var(--ks-muted)]"
                >{{ event.participants.length }} Governors</span
              >
            </div>
            <div
              v-if="event.participants.length"
              class="mt-3 overflow-x-auto rounded-xl border border-[var(--ks-border)]"
            >
              <table class="min-w-full divide-y divide-[var(--ks-border)] text-sm">
                <thead
                  class="bg-[rgba(210,163,75,.05)] text-left text-xs tracking-wide text-[var(--ks-muted)] uppercase"
                >
                  <tr>
                    <th class="px-4 py-3">Governor</th>
                    <th class="px-4 py-3">Represented Alliance</th>
                    <th class="px-4 py-3">Score</th>
                    <th class="px-4 py-3">Outcome</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[var(--ks-border)]">
                  <tr v-for="participant in event.participants" :key="participant.playerId">
                    <td class="px-4 py-3 text-[var(--ks-ivory)]">{{ participant.playerName }}</td>
                    <td class="px-4 py-3 text-[var(--ks-muted)]">
                      {{ participant.representedAllianceName ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-[var(--ks-muted)]">
                      {{ number(participant.result?.score ?? null) }}
                    </td>
                    <td class="px-4 py-3 text-[var(--ks-muted)] capitalize">
                      {{ humanize(participant.result?.outcome ?? null) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="mt-3 text-sm text-[var(--ks-muted)]">
              No Governor was recorded for this occurrence.
            </p>
          </div>
        </section>
      </template>
    </div>
  </AppLayout>
</template>
