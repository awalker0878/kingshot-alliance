<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

type TrendComparison = {
  baselinePower: string;
  baselineCapturedAt: string;
  currentPower: string;
  currentCapturedAt: string;
  change: string;
};

type PlayerComparison = {
  entryId: string;
  name: string;
  state: string;
  membershipLinked: boolean;
  snapshotState: string;
  current: { power: string; capturedAt: string } | null;
  sevenDay: TrendComparison | null;
  thirtyDay: TrendComparison | null;
};

type Metrics = {
  asOf: string;
  trackedPlayers: number;
  recordedPowerPlayers: number;
  totalPower: string | null;
  averagePower: string | null;
  medianPower: string | null;
  snapshotQuality: { current: number; stale: number; missing: number; staleAfterDays: number };
  recentRoster: { days: number; joins: number; departures: number };
  linkage: { linked: number; total: number; percent: string | null };
  sevenDayTrend: { days: number; change: string | null; comparablePlayers: number };
  thirtyDayTrend: { days: number; change: string | null; comparablePlayers: number };
  comparisons: PlayerComparison[];
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  canManage: boolean;
  metrics: Metrics;
}>();

const { t, formatDate, formatNumber } = useLocale();

function formatDecimal(value: string | null): string {
  if (value === null) return '—';
  const negative = value.startsWith('-');
  const unsigned = negative ? value.slice(1) : value;
  const [whole, fraction] = unsigned.split('.');
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const formatted = fraction === undefined ? grouped : `${grouped}.${fraction}`;
  return negative ? `-${formatted}` : formatted;
}

function formatSigned(value: string | null): string {
  if (value === null) return '—';
  if (value.startsWith('-') || value === '0' || value.startsWith('+')) return formatDecimal(value);
  return `+${formatDecimal(value)}`;
}

function formatCaptured(value: string): string {
  return formatDate(value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

function stateLabel(value: string): string {
  const key = `roster.${value}`;
  const translated = t(key);
  return translated === key ? value.replaceAll('_', ' ') : translated;
}

function comparisonText(comparison: TrendComparison | null): string {
  if (comparison === null) return t('roster.insufficientHistory');
  return t('roster.changeFrom', {
    change: formatSigned(comparison.change),
    date: formatCaptured(comparison.baselineCapturedAt),
  });
}

function qualityPercent(value: number): string {
  const total =
    props.metrics.snapshotQuality.current +
    props.metrics.snapshotQuality.stale +
    props.metrics.snapshotQuality.missing;
  if (total === 0) return '0%';
  return `${Math.round((value / total) * 100)}%`;
}

function trendTone(value: string | null): string {
  if (value === null || value === '0') return 'text-[var(--ks-text)]';
  return value.startsWith('-') ? 'text-red-300' : 'text-green-300';
}
</script>

<template>
  <Head :title="`${t('roster.intelligenceTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div class="max-w-3xl">
        <Link
          class="inline-flex min-h-10 items-center text-sm font-semibold text-[var(--ks-blue-strong)] hover:text-white"
          href="/alliance/roster"
        >
          ← {{ t('roster.title') }}
        </Link>
        <p class="mt-4 text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('roster.eyebrow', { kingdom: alliance.kingdom ?? t('roster.kingdomNotSet') }) }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('roster.intelligenceTitle') }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('roster.intelligenceSubtitle') }}
        </p>
        <p class="mt-2 text-xs text-[var(--ks-text-muted)]">
          {{ t('roster.asOf', { date: formatCaptured(metrics.asOf) }) }}
        </p>
      </div>
      <Link
        v-if="canManage"
        class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-border-strong)] hover:text-white"
        href="/alliance/roster/manage"
      >
        {{ t('roster.manage') }}
      </Link>
    </header>

    <section
      class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
      :aria-label="t('roster.intelligenceTitle')"
    >
      <article class="ks-surface p-5">
        <p class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
          {{ t('roster.trackedPlayers') }}
        </p>
        <p class="ks-display mt-2 text-3xl font-semibold">
          {{ formatNumber(metrics.trackedPlayers) }}
        </p>
      </article>
      <article class="ks-surface p-5">
        <p class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
          {{ t('roster.totalRecordedPower') }}
        </p>
        <p class="ks-display mt-2 text-3xl font-semibold">
          {{ formatDecimal(metrics.totalPower) }}
        </p>
        <p class="mt-2 text-xs text-[var(--ks-text-muted)]">
          {{
            t('roster.recordedPlayers', {
              recorded: metrics.recordedPowerPlayers,
              total: metrics.trackedPlayers,
            })
          }}
        </p>
      </article>
      <article class="ks-surface p-5">
        <p class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
          {{ t('roster.averagePower') }}
        </p>
        <p class="ks-display mt-2 text-3xl font-semibold">
          {{ formatDecimal(metrics.averagePower) }}
        </p>
        <p class="mt-2 text-xs text-[var(--ks-text-muted)]">{{ t('roster.roundedPower') }}</p>
      </article>
      <article class="ks-surface p-5">
        <p class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
          {{ t('roster.medianPower') }}
        </p>
        <p class="ks-display mt-2 text-3xl font-semibold">
          {{ formatDecimal(metrics.medianPower) }}
        </p>
        <p class="mt-2 text-xs text-[var(--ks-text-muted)]">{{ t('roster.latestSnapshotOnly') }}</p>
      </article>
    </section>

    <section class="mt-6 grid gap-4 xl:grid-cols-3">
      <article class="ks-surface p-5 sm:p-6">
        <h2 class="ks-display text-xl font-semibold">{{ t('roster.snapshotQuality') }}</h2>
        <div class="mt-5 space-y-4">
          <div>
            <div class="flex items-center justify-between gap-3 text-sm">
              <span class="text-green-200">{{ t('roster.current') }}</span
              ><strong>{{ formatNumber(metrics.snapshotQuality.current) }}</strong>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-black/30">
              <div
                class="h-full rounded-full bg-green-400"
                :style="{ width: qualityPercent(metrics.snapshotQuality.current) }"
              />
            </div>
          </div>
          <div>
            <div class="flex items-center justify-between gap-3 text-sm">
              <span class="text-amber-200">{{ t('roster.stale') }}</span
              ><strong>{{ formatNumber(metrics.snapshotQuality.stale) }}</strong>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-black/30">
              <div
                class="h-full rounded-full bg-amber-400"
                :style="{ width: qualityPercent(metrics.snapshotQuality.stale) }"
              />
            </div>
          </div>
          <div>
            <div class="flex items-center justify-between gap-3 text-sm">
              <span class="text-red-200">{{ t('roster.missing') }}</span
              ><strong>{{ formatNumber(metrics.snapshotQuality.missing) }}</strong>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-black/30">
              <div
                class="h-full rounded-full bg-red-400"
                :style="{ width: qualityPercent(metrics.snapshotQuality.missing) }"
              />
            </div>
          </div>
        </div>
        <p class="mt-5 text-xs leading-5 text-[var(--ks-text-muted)]">
          {{ t('roster.qualityWindow', { days: metrics.snapshotQuality.staleAfterDays }) }}
        </p>
      </article>

      <article class="ks-surface p-5 sm:p-6">
        <h2 class="ks-display text-xl font-semibold">{{ t('roster.recentMovement') }}</h2>
        <div class="mt-5 grid grid-cols-2 gap-3">
          <div
            class="rounded-[var(--ks-radius-md)] border border-green-400/20 bg-green-500/5 p-4 text-center"
          >
            <p class="text-xs font-bold tracking-[0.1em] text-green-300 uppercase">
              {{ t('roster.joined') }}
            </p>
            <p class="ks-display mt-2 text-3xl font-semibold">
              {{ formatNumber(metrics.recentRoster.joins) }}
            </p>
          </div>
          <div
            class="rounded-[var(--ks-radius-md)] border border-red-400/20 bg-red-500/5 p-4 text-center"
          >
            <p class="text-xs font-bold tracking-[0.1em] text-red-300 uppercase">
              {{ t('roster.departed') }}
            </p>
            <p class="ks-display mt-2 text-3xl font-semibold">
              {{ formatNumber(metrics.recentRoster.departures) }}
            </p>
          </div>
        </div>
        <p class="mt-5 text-xs text-[var(--ks-text-muted)]">
          {{ t('roster.windowDays', { days: metrics.recentRoster.days }) }}
        </p>
      </article>

      <article class="ks-surface p-5 sm:p-6">
        <h2 class="ks-display text-xl font-semibold">{{ t('roster.membershipLinkage') }}</h2>
        <p class="ks-display mt-5 text-4xl font-semibold text-[var(--ks-gold-strong)]">
          {{ metrics.linkage.percent === null ? '—' : `${metrics.linkage.percent}%` }}
        </p>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{
            t('roster.linkedOfTotal', {
              linked: metrics.linkage.linked,
              total: metrics.linkage.total,
            })
          }}
        </p>
      </article>
    </section>

    <section class="mt-6 grid gap-4 lg:grid-cols-2" :aria-label="t('roster.powerTrends')">
      <article class="ks-surface-gold p-5 sm:p-6">
        <p class="text-xs font-bold tracking-[0.12em] text-[var(--ks-gold)] uppercase">
          {{ t('roster.sevenDayChange') }}
        </p>
        <p
          class="ks-display mt-3 text-4xl font-semibold"
          :class="trendTone(metrics.sevenDayTrend.change)"
        >
          {{ formatSigned(metrics.sevenDayTrend.change) }}
        </p>
        <p class="mt-3 text-sm text-[var(--ks-text-secondary)]">
          {{ t('roster.comparablePlayers', { count: metrics.sevenDayTrend.comparablePlayers }) }}
        </p>
      </article>
      <article class="ks-surface-gold p-5 sm:p-6">
        <p class="text-xs font-bold tracking-[0.12em] text-[var(--ks-gold)] uppercase">
          {{ t('roster.thirtyDayChange') }}
        </p>
        <p
          class="ks-display mt-3 text-4xl font-semibold"
          :class="trendTone(metrics.thirtyDayTrend.change)"
        >
          {{ formatSigned(metrics.thirtyDayTrend.change) }}
        </p>
        <p class="mt-3 text-sm text-[var(--ks-text-secondary)]">
          {{ t('roster.comparablePlayers', { count: metrics.thirtyDayTrend.comparablePlayers }) }}
        </p>
      </article>
    </section>

    <section class="ks-surface mt-6 p-5 sm:p-6">
      <h2 class="ks-display text-xl font-semibold">{{ t('roster.trendMethodTitle') }}</h2>
      <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('roster.trendMethodBody') }}
      </p>
      <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('roster.currentTotalsBody') }}
      </p>
    </section>

    <section
      v-if="canManage"
      class="ks-surface mt-6 overflow-hidden"
      aria-labelledby="player-comparisons-heading"
    >
      <div class="border-b border-[var(--ks-border)] p-5 sm:p-6">
        <h2 id="player-comparisons-heading" class="ks-display text-xl font-semibold">
          {{ t('roster.managerDetail') }}
        </h2>
        <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
          {{ t('roster.managerDetailBody') }}
        </p>
      </div>
      <div v-if="metrics.comparisons.length" class="overflow-x-auto">
        <table class="w-full min-w-[68rem] text-sm">
          <thead class="border-b border-[var(--ks-border)] bg-black/20 text-[var(--ks-text-muted)]">
            <tr>
              <th class="px-4 py-3 text-start">{{ t('roster.player') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.state') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.snapshotState') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.currentPower') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.sevenDayChange') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.thirtyDayChange') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.linkage') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr
              v-for="comparison in metrics.comparisons"
              :key="comparison.entryId"
              class="hover:bg-white/[0.025]"
            >
              <td class="px-4 py-4 font-semibold">
                <Link
                  class="text-[var(--ks-blue-strong)] hover:text-white"
                  :href="`/alliance/roster/${comparison.entryId}/history`"
                  >{{ comparison.name }}</Link
                >
              </td>
              <td class="px-4 py-4 capitalize">{{ stateLabel(comparison.state) }}</td>
              <td class="px-4 py-4 capitalize">{{ stateLabel(comparison.snapshotState) }}</td>
              <td class="px-4 py-4">
                {{ comparison.current ? formatDecimal(comparison.current.power) : '—' }}
              </td>
              <td class="px-4 py-4 text-[var(--ks-text-secondary)]">
                {{ comparisonText(comparison.sevenDay) }}
              </td>
              <td class="px-4 py-4 text-[var(--ks-text-secondary)]">
                {{ comparisonText(comparison.thirtyDay) }}
              </td>
              <td class="px-4 py-4">
                {{ comparison.membershipLinked ? t('roster.linked') : t('roster.unlinked') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="p-6 text-sm text-[var(--ks-text-muted)]">
        {{ t('roster.noComparisonRows') }}
      </p>
    </section>
  </AppLayout>
</template>
