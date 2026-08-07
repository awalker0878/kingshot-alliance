<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

type Category = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  unit: string;
  period: string;
  periodStart: string | null;
  periodEnd: string | null;
  goal: number | null;
  approvedTotal: number;
  evidenceRequired: boolean;
  selfReportAllowed: boolean;
  leaderboardEnabled: boolean;
  dataClass: string;
  calculationKey: string | null;
  calculationVersion: string | null;
  calculationDescription: string | null;
  active: boolean;
};

type RecordRow = {
  id: string;
  membershipId: string;
  memberName: string | null;
  categoryId: string;
  categoryName: string | null;
  unit: string | null;
  value: number;
  source: string;
  dataClass: string;
  status: string;
  evidence: string | null;
  recordedAt: string;
  reversalReason: string | null;
  correctionReason: string | null;
  calculationVersion: string | null;
};

const props = defineProps<{
  alliance: { id: string; name: string; timezone: string };
  periods: string[];
  dataClasses: string[];
  reporting: {
    metrics: {
      activeMembers: number;
      joinedLast30Days: number;
      leftLast30Days: number;
      attendanceLast30Days: number;
      noShowsLast30Days: number;
      attendanceRate: number | null;
      recruitmentTotal: number;
      recruitmentJoined: number;
      pendingContributionApprovals: number;
      openDataQualityFlags: number;
    };
    categories: Category[];
    members: Array<{ id: string; name: string; email: string }>;
    pendingRecords: RecordRow[];
    recentRecords: RecordRow[];
    dataQualityFlags: Array<{
      id: string;
      membershipId: string | null;
      categoryId: string | null;
      recordId: string | null;
      code: string;
      severity: string;
      message: string;
      detectedAt: string;
    }>;
    leaderboards: Array<{
      categoryId: string;
      name: string;
      unit: string;
      calculationDescription: string;
      calculationVersion: string | null;
      entries: Array<{ membershipId: string; name: string; value: number }>;
    }>;
    reportSchedules: Array<{
      id: string;
      name: string;
      recipientMembershipId: string;
      cadence: string;
      timezone: string;
      nextDueAt: string;
      reportVersion: string;
      enabled: boolean;
      lastQueuedAt: string | null;
    }>;
    recentReportRuns: Array<{
      id: string;
      format: string;
      status: string;
      reportVersion: string;
      rowCount: number | null;
      checksum: string | null;
      queuedAt: string | null;
      completedAt: string | null;
    }>;
  };
}>();

const categoryForm = useForm({
  name: '',
  description: '',
  unit: 'points',
  period: 'weekly',
  period_start: '',
  period_end: '',
  goal_value: null as number | null,
  evidence_required: false,
  allow_self_report: true,
  leaderboard_enabled: true,
  data_class: 'recorded_fact',
  calculation_key: '',
  calculation_version: '',
  calculation_description: '',
});
const recordForm = useForm({
  membership_id: '',
  category_id: '',
  value: null as number | null,
  evidence: '',
});
const scheduleForm = useForm({
  recipient_membership_id: '',
  name: 'Alliance contribution summary',
  cadence: 'weekly',
  timezone: props.alliance.timezone,
  next_due_at: '',
});

function createCategory(): void {
  categoryForm.post('/alliance/contributions/categories', {
    preserveScroll: true,
    onSuccess: () => categoryForm.reset(),
  });
}
function recordContribution(): void {
  recordForm.post('/alliance/contributions/records', {
    preserveScroll: true,
    onSuccess: () => recordForm.reset(),
  });
}
function approve(id: string): void {
  router.patch(`/alliance/contributions/records/${id}/approve`, {}, { preserveScroll: true });
}
function correct(row: RecordRow): void {
  const value = window.prompt(
    `Correct ${row.memberName ?? 'member'} ${row.categoryName ?? 'contribution'} value:`,
    String(row.value),
  );
  if (value === null) return;
  const reason = window.prompt('Why is this correction required?');
  if (!reason) return;
  router.post(
    `/alliance/contributions/records/${row.id}/correct`,
    { value: Number(value), reason, evidence: row.evidence ?? '' },
    { preserveScroll: true },
  );
}
function reverse(row: RecordRow): void {
  const reason = window.prompt('Why should this record be reversed?');
  if (!reason) return;
  router.patch(
    `/alliance/contributions/records/${row.id}/reverse`,
    { reason },
    { preserveScroll: true },
  );
}
function reconcileEvents(): void {
  router.post('/alliance/contributions/reconcile-events', {}, { preserveScroll: true });
}
function refreshQuality(): void {
  router.post('/alliance/contributions/data-quality/refresh', {}, { preserveScroll: true });
}
function resolveFlag(id: string): void {
  router.patch(`/alliance/contributions/data-quality/${id}/resolve`, {}, { preserveScroll: true });
}
function createSchedule(): void {
  scheduleForm.post('/alliance/contributions/report-schedules', {
    preserveScroll: true,
    onSuccess: () => scheduleForm.reset('recipient_membership_id', 'next_due_at'),
  });
}
function pct(value: number | null): string {
  return value === null ? '—' : `${Math.round(value * 100)}%`;
}
</script>

<template>
  <Head :title="`${alliance.name} contribution reporting`" />
  <main class="mx-auto min-h-screen max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link
          class="text-sm font-semibold text-cyan-300 hover:text-cyan-200"
          href="/alliance/contributions"
          >← Member view</Link
        >
        <h1 class="mt-4 text-3xl font-bold sm:text-4xl">Contribution reporting</h1>
        <p class="mt-2 max-w-3xl text-slate-400">
          Explainable records, attendance reconciliation, data quality, exports, and scheduled
          reporting for {{ alliance.name }}.
        </p>
      </div>
      <div class="flex gap-2">
        <a
          class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold hover:border-cyan-400"
          href="/alliance/contributions/export.csv"
          >CSV</a
        >
        <a
          class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold hover:border-cyan-400"
          href="/alliance/contributions/export.xls"
          >Spreadsheet</a
        >
      </div>
    </div>

    <section
      class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
      aria-label="Operational reporting metrics"
    >
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
        <p class="text-sm text-slate-400">Active members</p>
        <p class="mt-1 text-2xl font-bold">{{ reporting.metrics.activeMembers }}</p>
        <p class="text-xs text-slate-500">
          +{{ reporting.metrics.joinedLast30Days }} / -{{ reporting.metrics.leftLast30Days }} in 30d
        </p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
        <p class="text-sm text-slate-400">Attendance</p>
        <p class="mt-1 text-2xl font-bold">{{ pct(reporting.metrics.attendanceRate) }}</p>
        <p class="text-xs text-slate-500">
          {{ reporting.metrics.attendanceLast30Days }} attended ·
          {{ reporting.metrics.noShowsLast30Days }} no-show
        </p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
        <p class="text-sm text-slate-400">Recruitment</p>
        <p class="mt-1 text-2xl font-bold">
          {{ reporting.metrics.recruitmentJoined }}/{{ reporting.metrics.recruitmentTotal }}
        </p>
        <p class="text-xs text-slate-500">joined / candidates</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
        <p class="text-sm text-slate-400">Pending approvals</p>
        <p class="mt-1 text-2xl font-bold">{{ reporting.metrics.pendingContributionApprovals }}</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
        <p class="text-sm text-slate-400">Data issues</p>
        <p class="mt-1 text-2xl font-bold">{{ reporting.metrics.openDataQualityFlags }}</p>
      </div>
    </section>

    <section class="mt-8 grid gap-6 xl:grid-cols-2">
      <form
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
        @submit.prevent="createCategory"
      >
        <h2 class="text-xl font-semibold">New contribution category</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <label class="text-sm"
            >Name<input
              v-model="categoryForm.name"
              required
              maxlength="120"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Unit<input
              v-model="categoryForm.unit"
              required
              maxlength="40"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Period<select
              v-model="categoryForm.period"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option v-for="period in periods" :key="period" :value="period">{{ period }}</option>
            </select></label
          >
          <label class="text-sm"
            >Goal per member<input
              v-model.number="categoryForm.goal_value"
              min="0"
              step="0.01"
              type="number"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label v-if="['season', 'custom'].includes(categoryForm.period)" class="text-sm"
            >Period start<input
              v-model="categoryForm.period_start"
              required
              type="date"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label v-if="['season', 'custom'].includes(categoryForm.period)" class="text-sm"
            >Period end<input
              v-model="categoryForm.period_end"
              required
              type="date"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Data class<select
              v-model="categoryForm.data_class"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option v-for="value in dataClasses" :key="value" :value="value">
                {{ value.replaceAll('_', ' ') }}
              </option>
            </select></label
          >
          <label class="text-sm sm:col-span-2"
            >Description<textarea
              v-model="categoryForm.description"
              maxlength="4000"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            />
          </label>
          <template v-if="categoryForm.data_class === 'calculated_metric'">
            <label class="text-sm"
              >Calculation key<input
                v-model="categoryForm.calculation_key"
                required
                placeholder="event_attendance"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            /></label>
            <label class="text-sm"
              >Calculation version<input
                v-model="categoryForm.calculation_version"
                required
                placeholder="1"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            /></label>
            <label class="text-sm sm:col-span-2"
              >Calculation explanation<textarea
                v-model="categoryForm.calculation_description"
                required
                maxlength="4000"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              />
            </label>
          </template>
        </div>
        <div class="mt-4 flex flex-wrap gap-4 text-sm">
          <label
            ><input v-model="categoryForm.evidence_required" type="checkbox" /> Evidence
            required</label
          >
          <label
            ><input v-model="categoryForm.allow_self_report" type="checkbox" /> Allow
            self-report</label
          >
          <label
            ><input v-model="categoryForm.leaderboard_enabled" type="checkbox" /> Leaderboard
            enabled</label
          >
        </div>
        <button
          type="submit"
          class="mt-5 rounded-lg bg-cyan-500 px-4 py-2 font-semibold text-slate-950"
          :disabled="categoryForm.processing"
        >
          Create category
        </button>
      </form>

      <form
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
        @submit.prevent="recordContribution"
      >
        <h2 class="text-xl font-semibold">Manual contribution</h2>
        <div class="mt-4 grid gap-3">
          <label class="text-sm"
            >Member<select
              v-model="recordForm.membership_id"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="" disabled>Select member</option>
              <option v-for="member in reporting.members" :key="member.id" :value="member.id">
                {{ member.name }}
              </option>
            </select></label
          >
          <label class="text-sm"
            >Category<select
              v-model="recordForm.category_id"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="" disabled>Select category</option>
              <option
                v-for="category in reporting.categories.filter(
                  (item) => item.active && item.calculationKey !== 'event_attendance',
                )"
                :key="category.id"
                :value="category.id"
              >
                {{ category.name }}
              </option>
            </select></label
          >
          <label class="text-sm"
            >Value<input
              v-model.number="recordForm.value"
              required
              min="0"
              step="0.01"
              type="number"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Evidence or note<textarea
              v-model="recordForm.evidence"
              maxlength="4000"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            />
          </label>
        </div>
        <button
          type="submit"
          class="mt-5 rounded-lg bg-cyan-500 px-4 py-2 font-semibold text-slate-950"
          :disabled="recordForm.processing"
        >
          Record pending contribution
        </button>
      </form>
    </section>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-xl font-semibold">Attendance &amp; data quality</h2>
          <p class="mt-1 text-sm text-slate-400">
            Derived records use the category calculation version; refreshing flags never changes
            contribution totals.
          </p>
        </div>
        <div class="flex gap-2">
          <button
            type="button"
            class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold"
            @click="reconcileEvents"
          >
            Reconcile attendance</button
          ><button
            type="button"
            class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold"
            @click="refreshQuality"
          >
            Refresh data quality
          </button>
        </div>
      </div>
      <div class="mt-4 space-y-2">
        <div
          v-for="flag in reporting.dataQualityFlags"
          :key="flag.id"
          class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-800 px-4 py-3"
        >
          <div>
            <span
              class="text-xs font-semibold uppercase"
              :class="flag.severity === 'error' ? 'text-rose-300' : 'text-amber-300'"
              >{{ flag.severity }}</span
            >
            <p class="text-sm">{{ flag.message }}</p>
          </div>
          <button
            type="button"
            class="text-sm font-semibold text-cyan-300"
            @click="resolveFlag(flag.id)"
          >
            Resolve
          </button>
        </div>
        <p v-if="!reporting.dataQualityFlags.length" class="text-sm text-slate-500">
          No open data-quality flags.
        </p>
      </div>
    </section>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">Pending approvals</h2>
      <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="text-slate-400">
            <tr>
              <th class="px-3 py-2">Member</th>
              <th class="px-3 py-2">Category</th>
              <th class="px-3 py-2">Value</th>
              <th class="px-3 py-2">Source</th>
              <th class="px-3 py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in reporting.pendingRecords"
              :key="row.id"
              class="border-t border-slate-800"
            >
              <td class="px-3 py-3">{{ row.memberName }}</td>
              <td class="px-3 py-3">{{ row.categoryName }}</td>
              <td class="px-3 py-3">{{ row.value }} {{ row.unit }}</td>
              <td class="px-3 py-3">{{ row.source.replaceAll('_', ' ') }}</td>
              <td class="px-3 py-3">
                <div class="flex gap-3">
                  <button
                    type="button"
                    class="font-semibold text-emerald-300"
                    @click="approve(row.id)"
                  >
                    Approve</button
                  ><button type="button" class="font-semibold text-cyan-300" @click="correct(row)">
                    Correct</button
                  ><button type="button" class="font-semibold text-rose-300" @click="reverse(row)">
                    Reverse
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!reporting.pendingRecords.length">
              <td class="px-3 py-4 text-slate-500" colspan="5">No pending approvals.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="mt-8 grid gap-6 xl:grid-cols-2">
      <form
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
        @submit.prevent="createSchedule"
      >
        <h2 class="text-xl font-semibold">Scheduled report</h2>
        <p class="mt-1 text-sm text-slate-400">
          Schedules queue versioned report requests through the notification outbox.
        </p>
        <div class="mt-4 grid gap-3">
          <label class="text-sm"
            >Recipient<select
              v-model="scheduleForm.recipient_membership_id"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="" disabled>Select member</option>
              <option v-for="member in reporting.members" :key="member.id" :value="member.id">
                {{ member.name }}
              </option>
            </select></label
          >
          <label class="text-sm"
            >Name<input
              v-model="scheduleForm.name"
              required
              maxlength="120"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Cadence<select
              v-model="scheduleForm.cadence"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="daily">daily</option>
              <option value="weekly">weekly</option>
              <option value="monthly">monthly</option>
            </select></label
          >
          <label class="text-sm"
            >Time zone<input
              v-model="scheduleForm.timezone"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >First delivery<input
              v-model="scheduleForm.next_due_at"
              required
              type="datetime-local"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
        </div>
        <button
          type="submit"
          class="mt-5 rounded-lg bg-cyan-500 px-4 py-2 font-semibold text-slate-950"
        >
          Create schedule
        </button>
      </form>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
        <h2 class="text-xl font-semibold">Report history</h2>
        <div class="mt-4 space-y-3">
          <div
            v-for="run in reporting.recentReportRuns"
            :key="run.id"
            class="rounded-lg border border-slate-800 px-4 py-3"
          >
            <div class="flex justify-between gap-3">
              <strong>{{ run.format }}</strong
              ><span class="text-sm text-slate-400 capitalize">{{ run.status }}</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">
              Version {{ run.reportVersion }} · {{ run.rowCount ?? 'queued' }} rows
            </p>
            <p v-if="run.checksum" class="mt-1 font-mono text-[10px] break-all text-slate-600">
              sha256 {{ run.checksum }}
            </p>
          </div>
          <p v-if="!reporting.recentReportRuns.length" class="text-sm text-slate-500">
            No report runs yet.
          </p>
        </div>
      </div>
    </section>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">Configured categories</h2>
      <div class="mt-4 grid gap-3 lg:grid-cols-2">
        <article
          v-for="category in reporting.categories"
          :key="category.id"
          class="rounded-lg border border-slate-800 p-4"
        >
          <div class="flex justify-between gap-3">
            <strong>{{ category.name }}</strong
            ><span class="text-sm text-slate-400"
              >{{ category.approvedTotal }} {{ category.unit }}</span
            >
          </div>
          <p class="mt-1 text-sm text-slate-400">
            {{ category.dataClass.replaceAll('_', ' ') }} · {{ category.period }} · goal
            {{ category.goal ?? 'none' }}
          </p>
          <p v-if="category.calculationDescription" class="mt-2 text-sm text-slate-300">
            {{ category.calculationDescription }}
            <span class="text-slate-500">v{{ category.calculationVersion }}</span>
          </p>
          <p v-if="!category.leaderboardEnabled" class="mt-2 text-xs text-amber-300">
            Leaderboard opted out
          </p>
        </article>
      </div>
    </section>
  </main>
</template>
