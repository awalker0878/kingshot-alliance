<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

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
  entries: Array<{ membershipId: string; name: string; value: number }>;
};

const props = defineProps<{
  alliance: { id: string; name: string; timezone: string };
  membership: { id: string };
  canManage: boolean;
  reporting: {
    progress: Progress[];
    history: RecordRow[];
    leaderboards: Leaderboard[];
  };
}>();

const selfReportCategories = computed(() =>
  props.reporting.progress.filter((item) => item.selfReportAllowed),
);
const form = useForm({ category_id: '', value: null as number | null, evidence: '' });

function submit(): void {
  form.post('/alliance/contributions/self-report', {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
}

function percent(value: number | null): string {
  return value === null ? 'No goal' : `${Math.round(value * 100)}%`;
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
    new Date(`${value}T00:00:00`),
  );
}

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(
    new Date(value),
  );
}
</script>

<template>
  <Head :title="`${alliance.name} contributions`" />
  <main class="mx-auto min-h-screen max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/alliance"
          >← Alliance home</Link
        >
        <h1 class="mt-4 text-3xl font-bold sm:text-4xl">Contributions &amp; progress</h1>
        <p class="mt-2 max-w-3xl text-slate-400">
          Your recorded facts, approved progress, corrections, and calculation details for
          {{ alliance.name }}.
        </p>
      </div>
      <Link
        v-if="canManage"
        class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold hover:border-cyan-400"
        href="/alliance/contributions/manage"
        >Manage reporting</Link
      >
    </div>

    <section
      class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3"
      aria-label="Contribution progress"
    >
      <article
        v-for="item in reporting.progress"
        :key="item.categoryId"
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="font-semibold">{{ item.name }}</h2>
            <p class="mt-1 text-xs tracking-wide text-slate-500 uppercase">
              {{ item.dataClass.replaceAll('_', ' ') }}
            </p>
          </div>
          <span class="text-sm font-semibold text-cyan-300">{{ percent(item.progress) }}</span>
        </div>
        <p class="mt-4 text-2xl font-bold">
          {{ item.approved }}
          <span class="text-sm font-medium text-slate-400">{{ item.unit }}</span>
        </p>
        <p class="mt-1 text-sm text-slate-400">Goal: {{ item.goal ?? 'not configured' }}</p>
        <p class="mt-3 text-xs text-slate-500">
          {{ formatDate(item.periodStart) }} – {{ formatDate(item.periodEnd) }}
        </p>
        <p v-if="item.calculationDescription" class="mt-3 text-sm text-slate-300">
          {{ item.calculationDescription }}
          <span v-if="item.calculationVersion" class="text-slate-500">
            · v{{ item.calculationVersion }}</span
          >
        </p>
      </article>
    </section>

    <section
      v-if="selfReportCategories.length"
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
    >
      <h2 class="text-xl font-semibold">Self-report a contribution</h2>
      <p class="mt-1 text-sm text-slate-400">
        Self-reported entries remain pending until an authorized leader approves them.
      </p>
      <form class="mt-5 grid gap-4 md:grid-cols-3" @submit.prevent="submit">
        <label class="text-sm font-medium"
          >Category
          <select
            v-model="form.category_id"
            required
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="" disabled>Select category</option>
            <option
              v-for="category in selfReportCategories"
              :key="category.categoryId"
              :value="category.categoryId"
            >
              {{ category.name }} ({{ category.unit }})
            </option>
          </select>
        </label>
        <label class="text-sm font-medium"
          >Value
          <input
            v-model.number="form.value"
            required
            min="0"
            step="0.01"
            type="number"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          />
        </label>
        <label class="text-sm font-medium"
          >Evidence or note
          <input
            v-model="form.evidence"
            maxlength="4000"
            type="text"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          />
        </label>
        <button
          class="rounded-lg bg-cyan-500 px-4 py-2 font-semibold text-slate-950 md:col-span-3 md:w-fit"
          :disabled="form.processing"
          type="submit"
        >
          Submit for approval
        </button>
      </form>
    </section>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">Your history</h2>
      <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="text-slate-400">
            <tr>
              <th class="px-3 py-2">Category</th>
              <th class="px-3 py-2">Value</th>
              <th class="px-3 py-2">Status</th>
              <th class="px-3 py-2">Source</th>
              <th class="px-3 py-2">Recorded</th>
              <th class="px-3 py-2">Explanation</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in reporting.history" :key="row.id" class="border-t border-slate-800">
              <td class="px-3 py-3">{{ row.categoryName }}</td>
              <td class="px-3 py-3">{{ row.value }} {{ row.unit }}</td>
              <td class="px-3 py-3 capitalize">{{ row.status }}</td>
              <td class="px-3 py-3">{{ row.source.replaceAll('_', ' ') }}</td>
              <td class="px-3 py-3">{{ formatDateTime(row.recordedAt) }}</td>
              <td class="px-3 py-3 text-slate-400">
                <span v-if="row.correctionReason">Corrected: {{ row.correctionReason }}</span>
                <span v-else-if="row.reversalReason">Reversed: {{ row.reversalReason }}</span>
                <span v-else-if="row.calculationVersion"
                  >Calculation v{{ row.calculationVersion }}</span
                >
                <span v-else>{{ row.evidence || '—' }}</span>
              </td>
            </tr>
            <tr v-if="!reporting.history.length">
              <td class="px-3 py-4 text-slate-500" colspan="6">No contribution records yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section
      v-if="reporting.leaderboards.length"
      class="mt-8 space-y-6"
      aria-label="Alliance leaderboards"
    >
      <article
        v-for="board in reporting.leaderboards"
        :key="board.categoryId"
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
      >
        <h2 class="text-xl font-semibold">{{ board.name }} leaderboard</h2>
        <p class="mt-1 text-sm text-slate-400">
          {{ board.calculationDescription
          }}<span v-if="board.calculationVersion">
            · Calculation v{{ board.calculationVersion }}</span
          >
        </p>
        <ol class="mt-4 space-y-2">
          <li
            v-for="(entry, index) in board.entries"
            :key="entry.membershipId"
            class="flex justify-between rounded-lg border border-slate-800 px-4 py-3"
          >
            <span>{{ index + 1 }}. {{ entry.name }}</span
            ><strong>{{ entry.value }} {{ board.unit }}</strong>
          </li>
          <li v-if="!board.entries.length" class="text-sm text-slate-500">
            No approved records for this period.
          </li>
        </ol>
      </article>
    </section>
  </main>
</template>
