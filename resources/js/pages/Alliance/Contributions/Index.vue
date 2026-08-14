<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLayout from '../../../layouts/AppLayout.vue';
import { useLocale } from '../../../localization';

type Progress = {
  categoryId: string;
  name: string;
  unit: string;
  period: string;
  periodStart: string;
  periodEnd: string;
  approved: number;
  goal: number | null;
  progress: number | null;
  selfReportAllowed: boolean;
  evidenceRequired: boolean;
  dataClass: string;
  calculationKey: string | null;
  calculationVersion: string | null;
  calculationDescription: string | null;
};

type RecordRow = {
  id: string;
  categoryName: string | null;
  unit: string | null;
  value: number;
  source: string;
  dataClass: string;
  status: string;
  evidence: string | null;
  periodStart: string;
  periodEnd: string;
  recordedAt: string;
  correctionReason: string | null;
  reversalReason: string | null;
  calculationVersion: string | null;
};

type Leaderboard = {
  categoryId: string;
  name: string;
  unit: string;
  periodStart: string;
  periodEnd: string;
  calculationKey: string | null;
  calculationVersion: string | null;
  calculationDescription: string;
  entries: Array<{ playerId: string; name: string; value: number }>;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; timezone: string };
  player: { id: string; name: string };
  canManage: boolean;
  reporting: {
    progress: Progress[];
    history: RecordRow[];
    leaderboards: Leaderboard[];
  };
}>();

const { t, formatDate, formatNumber } = useLocale();
const selfReportCategories = computed(() =>
  props.reporting.progress.filter((item) => item.selfReportAllowed),
);
const goalCategories = computed(
  () => props.reporting.progress.filter((item) => item.goal !== null).length,
);
const form = useForm({ category_id: '', value: null as number | null, evidence: '' });

function submit(): void {
  form.post('/alliance/contributions/self-report', {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
}

function percent(value: number | null): string {
  return value === null ? t('contributions.noGoal') : `${Math.round(value * 100)}%`;
}

function progressWidth(value: number | null): string {
  return value === null ? '0%' : `${Math.round(Math.max(0, Math.min(1, value)) * 100)}%`;
}

function dateOnly(value: string): string {
  return formatDate(`${value}T00:00:00`, { year: 'numeric', month: 'short', day: 'numeric' });
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

function valueLabel(value: number, unit: string | null): string {
  return `${formatNumber(value, { maximumFractionDigits: 2 })}${unit ? ` ${unit}` : ''}`;
}

function humanize(value: string): string {
  return value.replaceAll('_', ' ');
}

function statusTone(status: string): string {
  if (status === 'approved') return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (status === 'pending') return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
  if (status === 'reversed') return 'border-red-400/25 bg-red-500/10 text-red-200';
  return 'border-blue-400/20 bg-blue-500/10 text-blue-200';
}

function dataClassTone(value: string): string {
  if (value === 'calculated_metric') return 'border-purple-400/20 bg-purple-500/10 text-purple-200';
  if (value === 'subjective_assessment')
    return 'border-amber-400/20 bg-amber-500/10 text-amber-200';
  return 'border-blue-400/20 bg-blue-500/10 text-blue-200';
}

function explanation(row: RecordRow): string {
  if (row.correctionReason)
    return t('contributions.correctedReason', { reason: row.correctionReason });
  if (row.reversalReason) return t('contributions.reversedReason', { reason: row.reversalReason });
  if (row.calculationVersion)
    return t('contributions.calculationVersion', { version: row.calculationVersion });
  return row.evidence || '—';
}
</script>

<template>
  <Head :title="`${t('contributions.title')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div class="max-w-3xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('contributions.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('contributions.title') }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('contributions.subtitle', { alliance: alliance.name }) }}
        </p>
      </div>
      <Link
        v-if="canManage"
        class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-gold)]/45 bg-[var(--ks-gold-soft)] px-4 py-2 text-sm font-semibold text-[var(--ks-gold-strong)] transition hover:border-[var(--ks-gold)] hover:text-white"
        href="/alliance/contributions/manage"
      >
        {{ t('contributions.manageReporting') }}
      </Link>
    </header>

    <section class="ks-surface-gold mt-6 overflow-hidden" :aria-label="t('contributions.overview')">
      <div
        class="grid grid-cols-2 divide-x divide-y divide-[var(--ks-border)] lg:grid-cols-4 lg:divide-y-0"
      >
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('contributions.activeCategories') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold">
            {{ formatNumber(reporting.progress.length) }}
          </p>
        </article>
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('contributions.categoriesWithGoals') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(goalCategories) }}</p>
        </article>
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('contributions.selfReportCategories') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold">
            {{ formatNumber(selfReportCategories.length) }}
          </p>
        </article>
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('contributions.recentRecordsShown') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold">
            {{ formatNumber(reporting.history.length) }}
          </p>
        </article>
      </div>
    </section>

    <div class="mt-6 grid gap-5 xl:grid-cols-3">
      <div class="min-w-0 space-y-5 xl:col-span-2">
        <section aria-labelledby="contribution-progress-heading">
          <div class="flex flex-wrap items-end justify-between gap-3 px-1">
            <div>
              <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
                {{ t('contributions.progress') }}
              </p>
              <h2 id="contribution-progress-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{ t('contributions.currentPeriods') }}
              </h2>
            </div>
          </div>

          <div v-if="reporting.progress.length" class="mt-4 grid gap-4 md:grid-cols-2">
            <article
              v-for="item in reporting.progress"
              :key="item.categoryId"
              class="ks-surface p-5"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <h3 class="ks-display truncate text-lg font-semibold">{{ item.name }}</h3>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <span
                      :class="dataClassTone(item.dataClass)"
                      class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                    >
                      {{ humanize(item.dataClass) }}
                    </span>
                    <span
                      class="rounded-full border border-[var(--ks-border)] bg-black/15 px-2.5 py-1 text-xs text-[var(--ks-text-secondary)]"
                    >
                      {{ humanize(item.period) }}
                    </span>
                  </div>
                </div>
                <span class="shrink-0 text-sm font-semibold text-[var(--ks-gold-strong)]">{{
                  percent(item.progress)
                }}</span>
              </div>

              <div class="mt-5 flex items-end justify-between gap-4">
                <p>
                  <span class="ks-display text-3xl font-semibold">{{
                    formatNumber(item.approved, { maximumFractionDigits: 2 })
                  }}</span>
                  <span class="ms-1 text-sm text-[var(--ks-text-muted)]">{{ item.unit }}</span>
                </p>
                <p class="text-end text-xs text-[var(--ks-text-muted)]">
                  {{ t('contributions.goal') }}:
                  <strong class="text-[var(--ks-text-secondary)]">
                    {{
                      item.goal === null
                        ? t('contributions.notConfigured')
                        : formatNumber(item.goal, { maximumFractionDigits: 2 })
                    }}
                  </strong>
                </p>
              </div>

              <div
                v-if="item.progress !== null"
                class="mt-3 h-1.5 overflow-hidden rounded-full bg-black/30"
              >
                <div
                  class="h-full rounded-full bg-[var(--ks-gold)]"
                  :style="{ width: progressWidth(item.progress) }"
                />
              </div>

              <p class="mt-4 text-xs text-[var(--ks-text-muted)]">
                {{ dateOnly(item.periodStart) }} – {{ dateOnly(item.periodEnd) }}
              </p>
              <p
                v-if="item.calculationDescription"
                class="mt-3 border-t border-[var(--ks-border)] pt-3 text-sm leading-6 text-[var(--ks-text-secondary)]"
              >
                {{ item.calculationDescription }}
                <span v-if="item.calculationVersion" class="text-[var(--ks-text-muted)]">
                  · {{ t('contributions.version') }} {{ item.calculationVersion }}
                </span>
              </p>
              <p v-if="item.evidenceRequired" class="mt-3 text-xs font-semibold text-amber-300">
                {{ t('contributions.evidenceRequired') }}
              </p>
            </article>
          </div>
          <p v-else class="ks-surface mt-4 p-8 text-center text-sm text-[var(--ks-text-muted)]">
            {{ t('contributions.noCategories') }}
          </p>
        </section>

        <section class="ks-surface overflow-hidden" aria-labelledby="contribution-history-heading">
          <div class="border-b border-[var(--ks-border)] p-4 sm:p-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
              <div>
                <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
                  {{ t('contributions.history') }}
                </p>
                <h2 id="contribution-history-heading" class="ks-display mt-1 text-xl font-semibold">
                  {{ t('contributions.yourHistory') }}
                </h2>
              </div>
              <p class="text-xs text-[var(--ks-text-muted)]">
                {{ t('contributions.historyHelp') }}
              </p>
            </div>
          </div>

          <div v-if="reporting.history.length" class="lg:hidden">
            <article
              v-for="row in reporting.history"
              :key="row.id"
              class="border-b border-[var(--ks-border)] p-4 last:border-b-0"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate font-semibold">{{ row.categoryName ?? '—' }}</p>
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                    {{ dateTime(row.recordedAt) }}
                  </p>
                </div>
                <div class="shrink-0 text-end">
                  <strong>{{ valueLabel(row.value, row.unit) }}</strong>
                  <span
                    :class="statusTone(row.status)"
                    class="mt-2 block rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                  >
                    {{ humanize(row.status) }}
                  </span>
                </div>
              </div>
              <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
                <div>
                  <dt class="text-[var(--ks-text-muted)]">{{ t('contributions.source') }}</dt>
                  <dd class="mt-1 text-[var(--ks-text-secondary)]">{{ humanize(row.source) }}</dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-text-muted)]">{{ t('contributions.dataClass') }}</dt>
                  <dd class="mt-1 text-[var(--ks-text-secondary)]">
                    {{ humanize(row.dataClass) }}
                  </dd>
                </div>
                <div class="col-span-2">
                  <dt class="text-[var(--ks-text-muted)]">{{ t('contributions.explanation') }}</dt>
                  <dd class="mt-1 text-[var(--ks-text-secondary)]">{{ explanation(row) }}</dd>
                </div>
              </dl>
            </article>
          </div>

          <div v-if="reporting.history.length" class="hidden overflow-x-auto lg:block">
            <table class="w-full min-w-[64rem] text-sm">
              <thead
                class="bg-black/25 text-[0.68rem] font-bold tracking-[0.08em] text-[var(--ks-text-muted)] uppercase"
              >
                <tr>
                  <th class="px-4 py-3 text-start">{{ t('contributions.category') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('contributions.value') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('contributions.status') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('contributions.source') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('contributions.recorded') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('contributions.explanation') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[var(--ks-border)]">
                <tr
                  v-for="row in reporting.history"
                  :key="row.id"
                  class="transition hover:bg-white/[0.025]"
                >
                  <td class="px-4 py-3.5 font-semibold">{{ row.categoryName ?? '—' }}</td>
                  <td class="px-4 py-3.5">{{ valueLabel(row.value, row.unit) }}</td>
                  <td class="px-4 py-3.5">
                    <span
                      :class="statusTone(row.status)"
                      class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                    >
                      {{ humanize(row.status) }}
                    </span>
                  </td>
                  <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                    {{ humanize(row.source) }}
                  </td>
                  <td class="px-4 py-3.5 text-xs text-[var(--ks-text-muted)]">
                    {{ dateTime(row.recordedAt) }}
                  </td>
                  <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                    {{ explanation(row) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p
            v-if="!reporting.history.length"
            class="p-8 text-center text-sm text-[var(--ks-text-muted)]"
          >
            {{ t('contributions.noRecords') }}
          </p>
        </section>
      </div>

      <aside class="space-y-5 xl:col-span-1">
        <section
          v-if="selfReportCategories.length"
          class="ks-surface p-5 xl:sticky xl:top-24"
          aria-labelledby="self-report-heading"
        >
          <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
            {{ t('contributions.selfReport') }}
          </p>
          <h2 id="self-report-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ t('contributions.selfReportTitle') }}
          </h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{ t('contributions.selfReportHelp') }}
          </p>

          <form class="mt-5 space-y-4" @submit.prevent="submit">
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="contribution-category"
              >
                {{ t('contributions.category') }}
              </label>
              <select
                id="contribution-category"
                v-model="form.category_id"
                required
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              >
                <option value="" disabled>{{ t('contributions.selectCategory') }}</option>
                <option
                  v-for="category in selfReportCategories"
                  :key="category.categoryId"
                  :value="category.categoryId"
                >
                  {{ category.name }} ({{ category.unit }})
                </option>
              </select>
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="contribution-value"
              >
                {{ t('contributions.value') }}
              </label>
              <input
                id="contribution-value"
                v-model.number="form.value"
                required
                min="0"
                step="0.01"
                type="number"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              />
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="contribution-evidence"
              >
                {{ t('contributions.evidenceNote') }}
              </label>
              <textarea
                id="contribution-evidence"
                v-model="form.evidence"
                maxlength="4000"
                class="mt-1.5 min-h-24 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              />
            </div>
            <button
              class="min-h-11 w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--ks-blue-strong)] disabled:opacity-60"
              :disabled="form.processing"
              type="submit"
            >
              {{ t('contributions.submitApproval') }}
            </button>
          </form>
        </section>
      </aside>
    </div>

    <section
      v-if="reporting.leaderboards.length"
      class="mt-5"
      :aria-label="t('contributions.leaderboards')"
    >
      <div class="mb-4 px-1">
        <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
          {{ t('contributions.leaderboards') }}
        </p>
        <h2 class="ks-display mt-1 text-2xl font-semibold">
          {{ t('contributions.approvedCategoryLeaderboards') }}
        </h2>
      </div>
      <div class="grid gap-4 xl:grid-cols-2">
        <article
          v-for="board in reporting.leaderboards"
          :key="board.categoryId"
          class="ks-surface overflow-hidden"
        >
          <div class="border-b border-[var(--ks-border)] p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 class="ks-display text-lg font-semibold">{{ board.name }}</h3>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  {{ dateOnly(board.periodStart) }} – {{ dateOnly(board.periodEnd) }}
                </p>
              </div>
              <span
                v-if="board.calculationVersion"
                class="rounded-full border border-[var(--ks-border)] bg-black/15 px-2.5 py-1 text-xs text-[var(--ks-text-secondary)]"
              >
                {{ t('contributions.version') }} {{ board.calculationVersion }}
              </span>
            </div>
            <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
              {{ board.calculationDescription }}
            </p>
          </div>
          <ol v-if="board.entries.length" class="divide-y divide-[var(--ks-border)]">
            <li
              v-for="(entry, index) in board.entries"
              :key="entry.playerId"
              class="flex items-center justify-between gap-4 px-4 py-3 sm:px-5"
            >
              <span class="min-w-0 truncate"
                ><strong class="me-2 text-[var(--ks-gold-strong)]">{{ index + 1 }}</strong
                >{{ entry.name }}</span
              >
              <strong class="shrink-0">{{ valueLabel(entry.value, board.unit) }}</strong>
            </li>
          </ol>
          <p v-else class="p-5 text-sm text-[var(--ks-text-muted)]">
            {{ t('contributions.noApprovedRecords') }}
          </p>
        </article>
      </div>
    </section>
  </AppLayout>
</template>
