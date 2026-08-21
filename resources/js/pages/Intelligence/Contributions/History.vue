<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

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

const { t, formatDate, formatNumber } = useLocale();
const scopeTabs = [
  { labelKey: 'common.all', value: '' },
  { labelKey: 'events.scope.player', value: 'player' },
  { labelKey: 'events.scope.alliance', value: 'alliance' },
  { labelKey: 'events.scope.kingdom', value: 'kingdom' },
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

function outcomeLabel(value: string | null): string {
  const keys: Record<string, string> = {
    completed: 'events.eventStatuses.completed',
    absent: 'events.attendanceStatuses.absent',
    excused: 'events.attendanceStatuses.excused',
    unresolved: 'events.attendanceStatuses.unknown',
  };
  if (!value) return t('events.attendanceStatuses.unknown');
  const key = keys[value];
  return key ? t(key) : value.replaceAll('_', ' ');
}

function scopeLabel(value: string): string {
  const key = `events.scope.${value}`;
  const translated = t(key);
  return translated === key ? value.replaceAll('_', ' ') : translated;
}

function kindLabel(kind: TimelineRow['kind']): string {
  return kind === 'event' ? t('events.calendar.title') : t('contributions.title');
}

function metricLabel(metric: EventMetric): string {
  const translated = t(metric.labelKey);
  return translated === metric.labelKey ? metric.key : translated;
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
  <Head :title="`${t('contributions.yourHistory')} · ${player.name}`" />

  <AppLayout :user="user">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <RoomBanner
        :eyebrow="t('contributions.history')"
        :title="player.name"
        :subtitle="t('contributions.yourHistory')"
        image="/images/kingshot/noticeboard.svg"
        compact
      />

      <section
        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6"
        :aria-label="t('contributions.history')"
      >
        <div class="rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.78)] p-4">
          <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
            {{ t('events.calendar.title') }}
          </p>
          <p class="mt-2 text-2xl font-semibold text-[var(--ks-ivory)]">
            {{ formatNumber(summary.events) }}
          </p>
        </div>
        <div class="rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.78)] p-4">
          <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
            {{ t('events.history.evidenceAria') }}
          </p>
          <p class="mt-2 text-2xl font-semibold text-[var(--ks-ivory)]">{{ reliabilityLabel() }}</p>
        </div>
        <div class="rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.78)] p-4">
          <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
            {{ t('events.scope.player') }}
          </p>
          <p class="mt-2 text-2xl font-semibold text-[var(--ks-ivory)]">
            {{ formatNumber(summary.player_events) }}
          </p>
        </div>
        <div class="rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.78)] p-4">
          <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
            {{ t('events.scope.alliance') }}
          </p>
          <p class="mt-2 text-2xl font-semibold text-[var(--ks-ivory)]">
            {{ formatNumber(summary.alliance_events) }}
          </p>
        </div>
        <div class="rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.78)] p-4">
          <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
            {{ t('events.scope.kingdom') }}
          </p>
          <p class="mt-2 text-2xl font-semibold text-[var(--ks-ivory)]">
            {{ formatNumber(summary.kingdom_events) }}
          </p>
        </div>
        <div class="rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.78)] p-4">
          <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
            {{ t('contributions.recentRecords') }}
          </p>
          <p class="mt-2 text-2xl font-semibold text-[var(--ks-ivory)]">
            {{ formatNumber(summary.contribution_records) }}
          </p>
        </div>
      </section>

      <div class="flex flex-wrap gap-2" role="tablist" :aria-label="t('contributions.history')">
        <button
          v-for="tab in scopeTabs"
          :key="tab.value || 'all'"
          type="button"
          class="rounded-full border px-4 py-2 text-sm font-semibold transition"
          :class="
            filter.event_scope === tab.value
              ? 'border-amber-300 bg-amber-300 text-[var(--ks-ink)]'
              : 'border-[var(--ks-border)] bg-[rgba(7,12,13,.78)] text-[var(--ks-muted)] hover:border-[rgba(210,163,75,.45)]'
          "
          :aria-selected="filter.event_scope === tab.value"
          role="tab"
          @click="selectScope(tab.value)"
        >
          {{ t(tab.labelKey) }}
        </button>
      </div>

      <form
        class="grid gap-3 rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.70)] p-4 md:grid-cols-4"
        @submit.prevent="applyFilters"
      >
        <label class="space-y-1 text-xs text-[var(--ks-muted)]">
          <span>{{ t('events.history.from') }}</span>
          <input
            v-model="filter.from"
            type="date"
            class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
          />
        </label>
        <label class="space-y-1 text-xs text-[var(--ks-muted)]">
          <span>{{ t('events.history.until') }}</span>
          <input
            v-model="filter.until"
            type="date"
            class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
          />
        </label>
        <label class="space-y-1 text-xs text-[var(--ks-muted)]">
          <span>{{ t('events.history.evidenceAria') }}</span>
          <select
            v-model="filter.participation_outcome"
            class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
          >
            <option value="">{{ t('common.all') }}</option>
            <option value="completed">{{ t('events.eventStatuses.completed') }}</option>
            <option value="absent">{{ t('events.attendanceStatuses.absent') }}</option>
            <option value="excused">{{ t('events.attendanceStatuses.excused') }}</option>
            <option value="unresolved">{{ t('events.attendanceStatuses.unknown') }}</option>
          </select>
        </label>
        <label class="space-y-1 text-xs text-[var(--ks-muted)]">
          <span>{{ t('events.history.rows') }}</span>
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
        <label class="space-y-1 text-xs text-[var(--ks-muted)]">
          <span>{{ t('events.scope.alliance') }} ID</span>
          <input
            v-model="filter.alliance_id"
            type="text"
            class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
          />
        </label>
        <label class="space-y-1 text-xs text-[var(--ks-muted)]">
          <span>{{ t('events.scope.kingdom') }} ID</span>
          <input
            v-model="filter.kingdom_id_at_event"
            type="text"
            class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
          />
        </label>
        <label class="space-y-1 text-xs text-[var(--ks-muted)]">
          <span>{{ t('events.create.eventType') }}</span>
          <input
            v-model="filter.event_type_slug"
            type="text"
            class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
          />
        </label>
        <label class="space-y-1 text-xs text-[var(--ks-muted)]">
          <span>{{ t('events.history.metricTrends') }}</span>
          <input
            v-model="filter.event_metric_key"
            type="text"
            class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
          />
        </label>
        <label class="space-y-1 text-xs text-[var(--ks-muted)]">
          <span>{{ t('contributions.category') }}</span>
          <input
            v-model="filter.contribution_category_slug"
            type="text"
            class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
          />
        </label>
        <div class="flex items-end gap-2 md:col-span-3">
          <button
            type="submit"
            class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-[var(--ks-ink)]"
          >
            {{ t('events.history.applyFilters') }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-ivory)]"
            @click="clearFilters"
          >
            {{ t('events.history.clearFilters') }}
          </button>
        </div>
      </form>

      <div
        v-if="history.length === 0"
        class="rounded-2xl border border-dashed border-[rgba(210,163,75,.30)] p-10 text-center text-sm text-[var(--ks-muted)]"
      >
        {{ t('contributions.noRecords') }}
      </div>

      <ol v-else class="space-y-4">
        <li
          v-for="row in history"
          :key="`${row.kind}-${row.event?.occurrenceId ?? row.contribution?.recordId}`"
          class="rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.78)] p-5"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <span
                class="inline-flex rounded-full border border-[var(--ks-border)] px-2.5 py-1 text-[11px] font-semibold tracking-wide text-[var(--ks-muted)] uppercase"
              >
                {{ kindLabel(row.kind) }}
              </span>
              <h2 v-if="row.event" class="mt-3 text-lg font-semibold text-[var(--ks-ivory)]">
                {{ row.event.title || row.event.eventType.slug }}
              </h2>
              <h2
                v-else-if="row.contribution"
                class="mt-3 text-lg font-semibold text-[var(--ks-ivory)]"
              >
                {{ row.contribution.categoryName }}
              </h2>
            </div>
            <time class="text-xs text-[var(--ks-muted)]">{{ dateTime(row.occurredAt) }}</time>
          </div>

          <div v-if="row.event" class="mt-4 grid gap-3 text-sm md:grid-cols-4">
            <div>
              <span class="text-[var(--ks-muted)]">{{ t('events.create.context') }}</span>
              <p class="text-[var(--ks-ivory)]">{{ scopeLabel(row.event.scope) }}</p>
            </div>
            <div>
              <span class="text-[var(--ks-muted)]">{{ t('events.reminderAudiences.target') }}</span>
              <p class="text-[var(--ks-ivory)]">{{ row.event.target.displayName }}</p>
            </div>
            <div>
              <span class="text-[var(--ks-muted)]">{{ t('events.history.evidenceAria') }}</span>
              <p class="text-[var(--ks-ivory)]">{{ outcomeLabel(row.event.participation.outcome) }}</p>
            </div>
            <div>
              <span class="text-[var(--ks-muted)]">{{ t('events.capabilities.scoring') }}</span>
              <p class="text-[var(--ks-ivory)]">{{ scoreLabel(row.event) ?? '—' }}</p>
            </div>
            <div>
              <span class="text-[var(--ks-muted)]">{{ t('events.scope.kingdom') }}</span>
              <p class="text-[var(--ks-ivory)]">{{ row.event.playerContext.kingdomIdAtEvent }}</p>
            </div>
            <div v-if="row.event.playerContext.representedAllianceName" class="md:col-span-2">
              <span class="text-[var(--ks-muted)]">{{ t('events.history.representedAlliance') }}</span>
              <p class="text-[var(--ks-ivory)]">
                {{ row.event.playerContext.representedAllianceName }}
              </p>
            </div>
            <div v-if="row.event.result?.metrics.length" class="md:col-span-4">
              <span class="text-[var(--ks-muted)]">{{ t('events.history.metricTrends') }}</span>
              <div class="mt-2 flex flex-wrap gap-2">
                <span
                  v-for="metric in row.event.result.metrics"
                  :key="`${metric.key}-${metric.dimensionKey ?? ''}`"
                  class="rounded-lg bg-[rgba(210,163,75,.05)] px-3 py-1.5 text-xs text-[var(--ks-ivory)]"
                >
                  {{ metricLabel(metric) }}<template v-if="metric.dimensionKey"> · {{ metric.dimensionKey }}</template>:
                  {{ metric.value }}<template v-if="metric.unit"> {{ metric.unit }}</template>
                </span>
              </div>
            </div>
          </div>

          <div v-else-if="row.contribution" class="mt-4 grid gap-3 text-sm md:grid-cols-4">
            <div>
              <span class="text-[var(--ks-muted)]">{{ t('contributions.value') }}</span>
              <p class="text-[var(--ks-ivory)]">
                {{ row.contribution.value }} {{ row.contribution.unit }}
              </p>
            </div>
            <div>
              <span class="text-[var(--ks-muted)]">{{ t('contributions.status') }}</span>
              <p class="text-[var(--ks-ivory)] capitalize">
                {{ outcomeLabel(row.contribution.status) }}
              </p>
            </div>
            <div>
              <span class="text-[var(--ks-muted)]">{{ t('contributions.source') }}</span>
              <p class="text-[var(--ks-ivory)] capitalize">
                {{ row.contribution.source.replaceAll('_', ' ') }}
              </p>
            </div>
            <div>
              <span class="text-[var(--ks-muted)]">{{ t('contributions.period') }}</span>
              <p class="text-[var(--ks-ivory)]">
                {{ row.contribution.periodStart }} – {{ row.contribution.periodEnd }}
              </p>
            </div>
          </div>
        </li>
      </ol>
    </div>
  </AppLayout>
</template>