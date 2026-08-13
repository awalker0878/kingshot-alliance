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

function snapshotTone(value: string): string {
  if (value === 'current') return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (value === 'stale') return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
  return 'border-red-400/25 bg-red-500/10 text-red-200';
}

function rosterStateTone(value: string): string {
  if (value === 'active') return 'border-green-400/20 bg-green-500/10 text-green-200';
  if (value === 'tracked') return 'border-blue-400/20 bg-blue-500/10 text-blue-200';
  return 'border-slate-400/20 bg-slate-500/10 text-slate-300';
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
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <p class="text-xs text-[var(--ks-text-muted)]">
          {{ t('roster.asOf', { date: formatCaptured(metrics.asOf) }) }}
        </p>
        <Link
          v-if="canManage"
          class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-gold)] hover:text-white"
          href="/alliance/roster/manage"
        >
          {{ t('roster.manage') }}
        </Link>
      </div>
    </header>

    <section
      class="ks-surface-gold mt-6 overflow-hidden"
      :aria-label="t('roster.intelligenceTitle')"
    >
      <div class="border-b border-[var(--ks-border)] px-4 py-3 sm:px-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
            {{ t('roster.intelligenceTitle') }}
          </p>
          <p class="text-xs text-[var(--ks-text-muted)]">
            {{
              t('roster.recordedPlayers', {
                recorded: metrics.recordedPowerPlayers,
                total: metrics.trackedPlayers,
              })
            }}
          </p>
        </div>
      </div>
      <div
        class="grid grid-cols-2 divide-x divide-y divide-[var(--ks-border)] lg:grid-cols-4 lg:divide-y-0"
      >
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('roster.trackedPlayers') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold">
            {{ formatNumber(metrics.trackedPlayers) }}
          </p>
        </article>
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('roster.totalRecordedPower') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold text-[var(--ks-gold-strong)]">
            {{ formatDecimal(metrics.totalPower) }}
          </p>
        </article>
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('roster.averagePower') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold">
            {{ formatDecimal(metrics.averagePower) }}
          </p>
          <p class="mt-1 text-xs text-[var(--ks-text-muted)]">{{ t('roster.roundedPower') }}</p>
        </article>
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('roster.medianPower') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold">
            {{ formatDecimal(metrics.medianPower) }}
          </p>
          <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
            {{ t('roster.latestSnapshotOnly') }}
          </p>
        </article>
      </div>
    </section>

    <div class="mt-6 grid gap-5 xl:grid-cols-3">
      <div class="min-w-0 space-y-5 xl:col-span-2">
        <section class="grid gap-4 md:grid-cols-2" :aria-label="t('roster.powerTrends')">
          <article class="ks-surface-gold p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-xs font-bold tracking-[0.12em] text-[var(--ks-gold)] uppercase">
                  {{ t('roster.sevenDayChange') }}
                </p>
                <p
                  class="ks-display mt-3 text-4xl font-semibold"
                  :class="trendTone(metrics.sevenDayTrend.change)"
                >
                  {{ formatSigned(metrics.sevenDayTrend.change) }}
                </p>
              </div>
              <span
                class="rounded-full border border-[var(--ks-border)] bg-black/20 px-3 py-1 text-xs text-[var(--ks-text-muted)]"
              >
                {{ metrics.sevenDayTrend.days }}
              </span>
            </div>
            <p class="mt-4 text-sm text-[var(--ks-text-secondary)]">
              {{
                t('roster.comparablePlayers', { count: metrics.sevenDayTrend.comparablePlayers })
              }}
            </p>
          </article>

          <article class="ks-surface-gold p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-xs font-bold tracking-[0.12em] text-[var(--ks-gold)] uppercase">
                  {{ t('roster.thirtyDayChange') }}
                </p>
                <p
                  class="ks-display mt-3 text-4xl font-semibold"
                  :class="trendTone(metrics.thirtyDayTrend.change)"
                >
                  {{ formatSigned(metrics.thirtyDayTrend.change) }}
                </p>
              </div>
              <span
                class="rounded-full border border-[var(--ks-border)] bg-black/20 px-3 py-1 text-xs text-[var(--ks-text-muted)]"
              >
                {{ metrics.thirtyDayTrend.days }}
              </span>
            </div>
            <p class="mt-4 text-sm text-[var(--ks-text-secondary)]">
              {{
                t('roster.comparablePlayers', { count: metrics.thirtyDayTrend.comparablePlayers })
              }}
            </p>
          </article>
        </section>

        <section
          v-if="canManage"
          class="ks-surface overflow-hidden"
          aria-labelledby="player-comparisons-heading"
        >
          <div class="border-b border-[var(--ks-border)] p-4 sm:p-5">
            <h2 id="player-comparisons-heading" class="ks-display text-xl font-semibold">
              {{ t('roster.managerDetail') }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
              {{ t('roster.managerDetailBody') }}
            </p>
          </div>

          <div v-if="metrics.comparisons.length" class="lg:hidden">
            <article
              v-for="comparison in metrics.comparisons"
              :key="comparison.entryId"
              class="border-b border-[var(--ks-border)] p-4 last:border-b-0"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <Link
                    class="block truncate text-base font-semibold text-[var(--ks-blue-strong)] hover:text-white"
                    :href="`/alliance/roster/${comparison.entryId}/history`"
                  >
                    {{ comparison.name }}
                  </Link>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <span
                      :class="rosterStateTone(comparison.state)"
                      class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                    >
                      {{ stateLabel(comparison.state) }}
                    </span>
                    <span
                      :class="snapshotTone(comparison.snapshotState)"
                      class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                    >
                      {{ stateLabel(comparison.snapshotState) }}
                    </span>
                  </div>
                </div>
                <p class="shrink-0 text-end">
                  <span
                    class="block text-[0.65rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
                  >
                    {{ t('roster.currentPower') }}
                  </span>
                  <strong class="mt-1 block text-base">
                    {{ comparison.current ? formatDecimal(comparison.current.power) : '—' }}
                  </strong>
                </p>
              </div>

              <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                <div
                  class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3"
                >
                  <dt class="text-xs font-semibold text-[var(--ks-text-muted)]">
                    {{ t('roster.sevenDayChange') }}
                  </dt>
                  <dd class="mt-1 text-sm text-[var(--ks-text-secondary)]">
                    {{ comparisonText(comparison.sevenDay) }}
                  </dd>
                </div>
                <div
                  class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3"
                >
                  <dt class="text-xs font-semibold text-[var(--ks-text-muted)]">
                    {{ t('roster.thirtyDayChange') }}
                  </dt>
                  <dd class="mt-1 text-sm text-[var(--ks-text-secondary)]">
                    {{ comparisonText(comparison.thirtyDay) }}
                  </dd>
                </div>
              </dl>
              <p class="mt-3 text-xs text-[var(--ks-text-muted)]">
                {{ comparison.membershipLinked ? t('roster.linked') : t('roster.unlinked') }}
                <template v-if="comparison.current">
                  · {{ formatCaptured(comparison.current.capturedAt) }}
                </template>
              </p>
            </article>
          </div>

          <div v-if="metrics.comparisons.length" class="hidden overflow-x-auto lg:block">
            <table class="w-full min-w-[68rem] text-sm">
              <thead
                class="bg-black/25 text-[0.68rem] font-bold tracking-[0.08em] text-[var(--ks-text-muted)] uppercase"
              >
                <tr>
                  <th class="px-4 py-3 text-start">{{ t('roster.player') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('roster.state') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('roster.snapshotState') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('roster.linkage') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('roster.currentPower') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('roster.sevenDayChange') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('roster.thirtyDayChange') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[var(--ks-border)]">
                <tr
                  v-for="comparison in metrics.comparisons"
                  :key="comparison.entryId"
                  class="transition hover:bg-white/[0.025]"
                >
                  <td class="px-4 py-3.5 font-semibold">
                    <Link
                      class="text-[var(--ks-blue-strong)] hover:text-white"
                      :href="`/alliance/roster/${comparison.entryId}/history`"
                    >
                      {{ comparison.name }}
                    </Link>
                  </td>
                  <td class="px-4 py-3.5">
                    <span
                      :class="rosterStateTone(comparison.state)"
                      class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                    >
                      {{ stateLabel(comparison.state) }}
                    </span>
                  </td>
                  <td class="px-4 py-3.5">
                    <span
                      :class="snapshotTone(comparison.snapshotState)"
                      class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                    >
                      {{ stateLabel(comparison.snapshotState) }}
                    </span>
                  </td>
                  <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                    {{ comparison.membershipLinked ? t('roster.linked') : t('roster.unlinked') }}
                  </td>
                  <td class="px-4 py-3.5">
                    <template v-if="comparison.current">
                      <strong>{{ formatDecimal(comparison.current.power) }}</strong>
                      <span class="mt-1 block text-xs text-[var(--ks-text-muted)]">
                        {{ formatCaptured(comparison.current.capturedAt) }}
                      </span>
                    </template>
                    <template v-else>—</template>
                  </td>
                  <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                    {{ comparisonText(comparison.sevenDay) }}
                  </td>
                  <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                    {{ comparisonText(comparison.thirtyDay) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p
            v-if="!metrics.comparisons.length"
            class="p-8 text-center text-sm text-[var(--ks-text-muted)]"
          >
            {{ t('roster.noComparisonRows') }}
          </p>
        </section>
      </div>

      <aside class="space-y-4 xl:col-span-1" :aria-label="t('roster.snapshotQuality')">
        <section class="ks-surface p-5 xl:sticky xl:top-24">
          <h2 class="ks-display text-xl font-semibold">{{ t('roster.snapshotQuality') }}</h2>
          <div class="mt-5 space-y-4">
            <div>
              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="text-green-200">{{ t('roster.current') }}</span>
                <strong>{{ formatNumber(metrics.snapshotQuality.current) }}</strong>
              </div>
              <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/30">
                <div
                  class="h-full rounded-full bg-green-400"
                  :style="{ width: qualityPercent(metrics.snapshotQuality.current) }"
                />
              </div>
            </div>
            <div>
              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="text-amber-200">{{ t('roster.stale') }}</span>
                <strong>{{ formatNumber(metrics.snapshotQuality.stale) }}</strong>
              </div>
              <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/30">
                <div
                  class="h-full rounded-full bg-amber-400"
                  :style="{ width: qualityPercent(metrics.snapshotQuality.stale) }"
                />
              </div>
            </div>
            <div>
              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="text-red-200">{{ t('roster.missing') }}</span>
                <strong>{{ formatNumber(metrics.snapshotQuality.missing) }}</strong>
              </div>
              <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/30">
                <div
                  class="h-full rounded-full bg-red-400"
                  :style="{ width: qualityPercent(metrics.snapshotQuality.missing) }"
                />
              </div>
            </div>
          </div>
          <p class="mt-4 text-xs leading-5 text-[var(--ks-text-muted)]">
            {{ t('roster.qualityWindow', { days: metrics.snapshotQuality.staleAfterDays }) }}
          </p>

          <div class="my-5 border-t border-[var(--ks-border)]" />

          <h2 class="ks-display text-xl font-semibold">{{ t('roster.recentMovement') }}</h2>
          <div class="mt-4 grid grid-cols-2 gap-3">
            <div
              class="rounded-[var(--ks-radius-md)] border border-green-400/20 bg-green-500/5 p-4 text-center"
            >
              <p class="text-[0.68rem] font-bold tracking-[0.1em] text-green-300 uppercase">
                {{ t('roster.joined') }}
              </p>
              <p class="ks-display mt-2 text-3xl font-semibold">
                {{ formatNumber(metrics.recentRoster.joins) }}
              </p>
            </div>
            <div
              class="rounded-[var(--ks-radius-md)] border border-red-400/20 bg-red-500/5 p-4 text-center"
            >
              <p class="text-[0.68rem] font-bold tracking-[0.1em] text-red-300 uppercase">
                {{ t('roster.departed') }}
              </p>
              <p class="ks-display mt-2 text-3xl font-semibold">
                {{ formatNumber(metrics.recentRoster.departures) }}
              </p>
            </div>
          </div>
          <p class="mt-3 text-xs text-[var(--ks-text-muted)]">
            {{ t('roster.windowDays', { days: metrics.recentRoster.days }) }}
          </p>

          <div class="my-5 border-t border-[var(--ks-border)]" />

          <h2 class="ks-display text-xl font-semibold">{{ t('roster.membershipLinkage') }}</h2>
          <p class="ks-display mt-3 text-4xl font-semibold text-[var(--ks-gold-strong)]">
            {{ metrics.linkage.percent === null ? '—' : `${metrics.linkage.percent}%` }}
          </p>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{
              t('roster.linkedOfTotal', {
                linked: metrics.linkage.linked,
                total: metrics.linkage.total,
              })
            }}
          </p>
        </section>
      </aside>
    </div>

    <section class="ks-surface mt-5 p-5 sm:p-6">
      <h2 class="ks-display text-xl font-semibold">{{ t('roster.trendMethodTitle') }}</h2>
      <div class="mt-3 grid gap-3 lg:grid-cols-2">
        <p class="text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('roster.trendMethodBody') }}
        </p>
        <p class="text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('roster.currentTotalsBody') }}
        </p>
      </div>
    </section>
  </AppLayout>
</template>
