<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

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

function statusTone(status: string): 'success' | 'warning' | 'danger' | 'info' {
  if (status === 'approved') return 'success';
  if (status === 'pending') return 'warning';
  if (status === 'reversed') return 'danger';
  return 'info';
}

function dataClassTone(value: string): 'warning' | 'info' {
  return value === 'subjective_assessment' ? 'warning' : 'info';
}

function explanation(row: RecordRow): string {
  if (row.correctionReason) {
    return t('contributions.correctedReason', { reason: row.correctionReason });
  }
  if (row.reversalReason) return t('contributions.reversedReason', { reason: row.reversalReason });
  if (row.calculationVersion) {
    return t('contributions.calculationVersion', { version: row.calculationVersion });
  }
  return row.evidence || '—';
}
</script>

<template>
  <Head :title="`${t('contributions.title')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('contributions.eyebrow')"
      :title="t('contributions.title')"
      :subtitle="t('contributions.subtitle', { alliance: alliance.name })"
      image="/images/kingshot/v4/glory-ledger.svg"
    >
      <template #actions>
        <Link
          v-if="canManage"
          href="/alliance/contributions/manage"
          class="ks-command-link"
        >
          {{ t('contributions.manageReporting') }}
        </Link>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
      <StatSeal
        :label="t('contributions.activeCategories')"
        :value="formatNumber(reporting.progress.length)"
        icon="✦"
      />
      <StatSeal
        :label="t('contributions.categoriesWithGoals')"
        :value="formatNumber(goalCategories)"
        icon="◎"
        tone="teal"
      />
      <StatSeal
        :label="t('contributions.selfReportCategories')"
        :value="formatNumber(selfReportCategories.length)"
        icon="✎"
        tone="stone"
      />
      <StatSeal
        :label="t('contributions.recentRecordsShown')"
        :value="formatNumber(reporting.history.length)"
        icon="▤"
      />
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,.6fr)]">
      <div class="min-w-0 space-y-5">
        <section aria-labelledby="contribution-progress-heading">
          <div class="flex flex-wrap items-end justify-between gap-3 px-1">
            <div>
              <p class="ks-kicker">{{ t('contributions.progress') }}</p>
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
                  <h3 class="ks-display truncate text-xl font-semibold">{{ item.name }}</h3>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <span class="ks-status" :data-tone="dataClassTone(item.dataClass)">
                      {{ humanize(item.dataClass) }}
                    </span>
                    <span class="ks-chip">{{ humanize(item.period) }}</span>
                  </div>
                </div>
                <span class="shrink-0 font-[var(--ks-font-display)] text-lg text-[var(--ks-gold-bright)]">
                  {{ percent(item.progress) }}
                </span>
              </div>

              <div class="mt-6 flex items-end justify-between gap-4">
                <p>
                  <span class="ks-display text-3xl font-semibold">
                    {{ formatNumber(item.approved, { maximumFractionDigits: 2 }) }}
                  </span>
                  <span class="ms-1 text-sm text-[var(--ks-muted)]">{{ item.unit }}</span>
                </p>
                <p class="text-end text-xs text-[var(--ks-muted)]">
                  {{ t('contributions.goal') }}
                  <strong class="mt-1 block text-[var(--ks-text-secondary)]">
                    {{
                      item.goal === null
                        ? t('contributions.notConfigured')
                        : formatNumber(item.goal, { maximumFractionDigits: 2 })
                    }}
                  </strong>
                </p>
              </div>

              <div v-if="item.progress !== null" class="mt-4 h-2 overflow-hidden rounded-full bg-white/[.05]">
                <div
                  class="h-full rounded-full bg-[linear-gradient(90deg,var(--ks-teal),var(--ks-gold-bright))]"
                  :style="{ width: progressWidth(item.progress) }"
                />
              </div>

              <p class="mt-4 text-xs text-[var(--ks-muted)]">
                {{ dateOnly(item.periodStart) }} – {{ dateOnly(item.periodEnd) }}
              </p>
              <p
                v-if="item.calculationDescription"
                class="mt-4 border-t border-[var(--ks-border)] pt-4 text-sm leading-6 text-[var(--ks-text-secondary)]"
              >
                {{ item.calculationDescription }}
                <span v-if="item.calculationVersion" class="text-[var(--ks-muted)]">
                  · {{ t('contributions.version') }} {{ item.calculationVersion }}
                </span>
              </p>
              <span v-if="item.evidenceRequired" class="ks-status mt-3" data-tone="warning">
                {{ t('contributions.evidenceRequired') }}
              </span>
            </article>
          </div>
          <div v-else class="ks-fantasy-empty mt-4">{{ t('contributions.noCategories') }}</div>
        </section>

        <section class="ks-surface overflow-hidden" aria-labelledby="contribution-history-heading">
          <div class="flex flex-wrap items-end justify-between gap-3 border-b border-[var(--ks-border)] p-5">
            <div>
              <p class="ks-kicker">{{ t('contributions.history') }}</p>
              <h2 id="contribution-history-heading" class="ks-display mt-1 text-xl font-semibold">
                {{ t('contributions.yourHistory') }}
              </h2>
            </div>
            <p class="text-xs text-[var(--ks-muted)]">{{ t('contributions.historyHelp') }}</p>
          </div>

          <div v-if="reporting.history.length" class="lg:hidden">
            <article
              v-for="row in reporting.history"
              :key="row.id"
              class="border-b border-[var(--ks-border)] p-4 last:border-b-0"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate font-[var(--ks-font-display)] text-lg font-semibold">
                    {{ row.categoryName ?? '—' }}
                  </p>
                  <p class="mt-1 text-xs text-[var(--ks-muted)]">{{ dateTime(row.recordedAt) }}</p>
                </div>
                <div class="shrink-0 text-end">
                  <strong>{{ valueLabel(row.value, row.unit) }}</strong>
                  <span class="ks-status mt-2" :data-tone="statusTone(row.status)">
                    {{ humanize(row.status) }}
                  </span>
                </div>
              </div>
              <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('contributions.source') }}</dt>
                  <dd class="mt-1">{{ humanize(row.source) }}</dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('contributions.dataClass') }}</dt>
                  <dd class="mt-1">{{ humanize(row.dataClass) }}</dd>
                </div>
                <div class="col-span-2">
                  <dt class="text-[var(--ks-muted)]">{{ t('contributions.explanation') }}</dt>
                  <dd class="mt-1 text-[var(--ks-text-secondary)]">{{ explanation(row) }}</dd>
                </div>
              </dl>
            </article>
          </div>

          <div v-if="reporting.history.length" class="hidden overflow-x-auto lg:block">
            <table class="w-full min-w-[64rem] text-sm">
              <thead class="bg-black/20 text-[.66rem] font-extrabold tracking-[.08em] text-[var(--ks-muted)] uppercase">
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
                <tr v-for="row in reporting.history" :key="row.id" class="transition hover:bg-white/[0.018]">
                  <td class="px-4 py-4 font-semibold">{{ row.categoryName ?? '—' }}</td>
                  <td class="px-4 py-4">{{ valueLabel(row.value, row.unit) }}</td>
                  <td class="px-4 py-4"><span class="ks-status" :data-tone="statusTone(row.status)">{{ humanize(row.status) }}</span></td>
                  <td class="px-4 py-4 text-[var(--ks-text-secondary)]">{{ humanize(row.source) }}</td>
                  <td class="px-4 py-4 text-xs text-[var(--ks-muted)]">{{ dateTime(row.recordedAt) }}</td>
                  <td class="px-4 py-4 text-[var(--ks-text-secondary)]">{{ explanation(row) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="!reporting.history.length" class="ks-fantasy-empty m-5">
            {{ t('contributions.noRecords') }}
          </div>
        </section>
      </div>

      <aside class="space-y-5">
        <section
          v-if="selfReportCategories.length"
          class="ks-surface p-5 2xl:sticky 2xl:top-[6.5rem]"
          aria-labelledby="self-report-heading"
        >
          <p class="ks-kicker">{{ t('contributions.selfReport') }}</p>
          <h2 id="self-report-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ t('contributions.selfReportTitle') }}
          </h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{ t('contributions.selfReportHelp') }}
          </p>

          <form class="mt-5 space-y-4" @submit.prevent="submit">
            <div>
              <label class="text-xs font-semibold" for="contribution-category">
                {{ t('contributions.category') }}
              </label>
              <select id="contribution-category" v-model="form.category_id" required class="ks-input mt-1.5">
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
              <label class="text-xs font-semibold" for="contribution-value">
                {{ t('contributions.value') }}
              </label>
              <input id="contribution-value" v-model.number="form.value" required min="0" step="0.01" type="number" class="ks-input mt-1.5" />
            </div>
            <div>
              <label class="text-xs font-semibold" for="contribution-evidence">
                {{ t('contributions.evidenceNote') }}
              </label>
              <textarea id="contribution-evidence" v-model="form.evidence" maxlength="4000" class="ks-input mt-1.5 min-h-24" />
            </div>
            <AppButton class="w-full" type="submit" :disabled="form.processing">
              {{ t('contributions.submitApproval') }}
            </AppButton>
          </form>
        </section>
      </aside>
    </div>

    <section v-if="reporting.leaderboards.length" class="mt-5" :aria-label="t('contributions.leaderboards')">
      <div class="mb-4 px-1">
        <p class="ks-kicker">{{ t('contributions.leaderboards') }}</p>
        <h2 class="ks-display mt-1 text-2xl font-semibold">
          {{ t('contributions.approvedCategoryLeaderboards') }}
        </h2>
      </div>
      <div class="grid gap-4 xl:grid-cols-2">
        <article v-for="board in reporting.leaderboards" :key="board.categoryId" class="ks-surface overflow-hidden">
          <div class="border-b border-[var(--ks-border)] p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 class="ks-display text-xl font-semibold">{{ board.name }}</h3>
                <p class="mt-1 text-xs text-[var(--ks-muted)]">
                  {{ dateOnly(board.periodStart) }} – {{ dateOnly(board.periodEnd) }}
                </p>
              </div>
              <span v-if="board.calculationVersion" class="ks-chip">
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
              class="flex items-center gap-4 px-5 py-3"
            >
              <span
                class="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-[var(--ks-border)] font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
              >
                {{ index + 1 }}
              </span>
              <span class="min-w-0 flex-1 truncate">{{ entry.name }}</span>
              <strong class="shrink-0">{{ valueLabel(entry.value, board.unit) }}</strong>
            </li>
          </ol>
          <div v-else class="ks-fantasy-empty m-4">{{ t('contributions.noApprovedRecords') }}</div>
        </article>
      </div>
    </section>
  </AppLayout>
</template>
