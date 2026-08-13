<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import { useLocale } from '../../../localization';

type Category = {
  id: string;
  name: string;
  description: string | null;
  unit: string;
  period: string;
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
type Row = {
  id: string;
  memberName: string | null;
  categoryName: string | null;
  unit: string | null;
  value: number;
  source: string;
  status: string;
  evidence: string | null;
  recordedAt: string;
};
type Flag = { id: string; severity: string; message: string; detectedAt: string };
type Board = {
  categoryId: string;
  name: string;
  unit: string;
  calculationDescription: string;
  entries: Array<{ membershipId: string; name: string; value: number }>;
};
type Schedule = {
  id: string;
  name: string;
  recipientMembershipId: string;
  cadence: string;
  timezone: string;
  nextDueAt: string;
  enabled: boolean;
};
type Run = {
  id: string;
  format: string;
  status: string;
  reportVersion: string;
  rowCount: number | null;
  checksum: string | null;
};
const props = defineProps<{
  user: { name: string; email: string };
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
    pendingRecords: Row[];
    recentRecords: Row[];
    dataQualityFlags: Flag[];
    leaderboards: Board[];
    reportSchedules: Schedule[];
    recentReportRuns: Run[];
  };
}>();
const { t, formatDate, formatNumber } = useLocale();
const recordable = computed(() =>
  props.reporting.categories.filter((c) => c.active && c.calculationKey !== 'event_attendance'),
);
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
function createCategory() {
  categoryForm.post('/alliance/contributions/categories', {
    preserveScroll: true,
    onSuccess: () => categoryForm.reset(),
  });
}
function recordContribution() {
  recordForm.post('/alliance/contributions/records', {
    preserveScroll: true,
    onSuccess: () => recordForm.reset(),
  });
}
function approve(id: string) {
  router.patch(`/alliance/contributions/records/${id}/approve`, {}, { preserveScroll: true });
}
function correct(r: Row) {
  const v = window.prompt(
    t('contributions.correctValuePrompt', {
      member: r.memberName ?? t('contributions.member'),
      category: r.categoryName ?? t('contributions.category'),
    }),
    String(r.value),
  );
  if (v === null) return;
  const reason = window.prompt(t('contributions.correctionReasonPrompt'));
  if (reason)
    router.post(
      `/alliance/contributions/records/${r.id}/correct`,
      { value: Number(v), reason, evidence: r.evidence ?? '' },
      { preserveScroll: true },
    );
}
function reverse(r: Row) {
  const reason = window.prompt(t('contributions.reverseReasonPrompt'));
  if (reason)
    router.patch(
      `/alliance/contributions/records/${r.id}/reverse`,
      { reason },
      { preserveScroll: true },
    );
}
function reconcile() {
  router.post('/alliance/contributions/reconcile-events', {}, { preserveScroll: true });
}
function refresh() {
  router.post('/alliance/contributions/data-quality/refresh', {}, { preserveScroll: true });
}
function resolve(id: string) {
  router.patch(`/alliance/contributions/data-quality/${id}/resolve`, {}, { preserveScroll: true });
}
function schedule() {
  scheduleForm.post('/alliance/contributions/report-schedules', {
    preserveScroll: true,
    onSuccess: () => scheduleForm.reset('recipient_membership_id', 'next_due_at'),
  });
}
function pct(v: number | null) {
  return v === null ? '—' : `${Math.round(v * 100)}%`;
}
function text(v: string) {
  return v.replaceAll('_', ' ');
}
function date(v: string) {
  return formatDate(v, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}
function amount(v: number, u: string | null) {
  return `${formatNumber(v, { maximumFractionDigits: 2 })}${u ? ` ${u}` : ''}`;
}
function member(id: string) {
  return props.reporting.members.find((m) => m.id === id)?.name ?? id;
}
</script>

<template>
  <Head :title="`${t('contributions.managerTitle')} · ${alliance.name}`" />
  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link
          class="text-sm font-semibold text-[var(--ks-blue-strong)]"
          href="/alliance/contributions"
          >← {{ t('contributions.memberView') }}</Link
        >
        <p class="mt-4 text-xs font-bold tracking-[.2em] text-[var(--ks-gold)] uppercase">
          {{ t('contributions.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold">{{ t('contributions.managerTitle') }}</h1>
        <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
          {{ t('contributions.managerSubtitle', { alliance: alliance.name }) }}
        </p>
      </div>
      <div class="flex gap-2">
        <a class="btn" href="/alliance/contributions/export.csv">{{
          t('contributions.exportCsv')
        }}</a
        ><a
          class="btn border-[var(--ks-gold)]/50 text-[var(--ks-gold-strong)]"
          href="/alliance/contributions/export.xls"
          >{{ t('contributions.exportSpreadsheet') }}</a
        >
      </div>
    </header>

    <section
      class="ks-surface-gold mt-6 overflow-hidden"
      :aria-label="t('contributions.operationalMetrics')"
    >
      <div
        class="grid grid-cols-2 divide-x divide-y divide-[var(--ks-border)] xl:grid-cols-5 xl:divide-y-0"
      >
        <article class="metric">
          <small>{{ t('contributions.activeMembers') }}</small
          ><b>{{ formatNumber(reporting.metrics.activeMembers) }}</b
          ><small>{{
            t('contributions.memberMovement30', {
              joined: reporting.metrics.joinedLast30Days,
              left: reporting.metrics.leftLast30Days,
            })
          }}</small>
        </article>
        <article class="metric">
          <small>{{ t('contributions.attendance') }}</small
          ><b>{{ pct(reporting.metrics.attendanceRate) }}</b
          ><small>{{
            t('contributions.attendanceBreakdown', {
              attended: reporting.metrics.attendanceLast30Days,
              noShows: reporting.metrics.noShowsLast30Days,
            })
          }}</small>
        </article>
        <article class="metric">
          <small>{{ t('contributions.recruitment') }}</small
          ><b>{{ reporting.metrics.recruitmentJoined }}/{{ reporting.metrics.recruitmentTotal }}</b>
        </article>
        <article class="metric">
          <small>{{ t('contributions.pendingApprovals') }}</small
          ><b class="text-amber-200">{{ reporting.metrics.pendingContributionApprovals }}</b>
        </article>
        <article class="metric col-span-2 xl:col-span-1">
          <small>{{ t('contributions.dataIssues') }}</small
          ><b class="text-red-200">{{ reporting.metrics.openDataQualityFlags }}</b>
        </article>
      </div>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-2">
      <form class="ks-surface p-5" @submit.prevent="createCategory">
        <h2 class="ks-display text-xl font-semibold">{{ t('contributions.newCategory') }}</h2>
        <div class="formgrid">
          <label
            >{{ t('contributions.name')
            }}<input v-model="categoryForm.name" required maxlength="120" class="field" /></label
          ><label
            >{{ t('contributions.unit')
            }}<input v-model="categoryForm.unit" required maxlength="40" class="field" /></label
          ><label
            >{{ t('contributions.period')
            }}<select v-model="categoryForm.period" class="field">
              <option v-for="p in periods" :key="p" :value="p">{{ text(p) }}</option>
            </select></label
          ><label
            >{{ t('contributions.goalPerMember')
            }}<input
              v-model.number="categoryForm.goal_value"
              min="0"
              step=".01"
              type="number"
              class="field" /></label
          ><label v-if="['season', 'custom'].includes(categoryForm.period)"
            >{{ t('contributions.periodStart')
            }}<input
              v-model="categoryForm.period_start"
              required
              type="date"
              class="field" /></label
          ><label v-if="['season', 'custom'].includes(categoryForm.period)"
            >{{ t('contributions.periodEnd')
            }}<input v-model="categoryForm.period_end" required type="date" class="field" /></label
          ><label
            >{{ t('contributions.dataClass')
            }}<select v-model="categoryForm.data_class" class="field">
              <option v-for="d in dataClasses" :key="d" :value="d">{{ text(d) }}</option>
            </select></label
          ><label class="sm:col-span-2"
            >{{ t('contributions.description')
            }}<textarea v-model="categoryForm.description" maxlength="4000" class="field" /></label
          ><template v-if="categoryForm.data_class === 'calculated_metric'"
            ><label
              >{{ t('contributions.calculationKey')
              }}<input v-model="categoryForm.calculation_key" required class="field" /></label
            ><label
              >{{ t('contributions.calculationVersionField')
              }}<input v-model="categoryForm.calculation_version" required class="field" /></label
            ><label class="sm:col-span-2"
              >{{ t('contributions.calculationExplanation')
              }}<textarea
                v-model="categoryForm.calculation_description"
                required
                maxlength="4000"
                class="field"
              /></label
          ></template>
        </div>
        <div class="mt-4 flex flex-wrap gap-4 text-xs">
          <label
            ><input v-model="categoryForm.evidence_required" type="checkbox" />
            {{ t('contributions.evidenceRequired') }}</label
          ><label
            ><input v-model="categoryForm.allow_self_report" type="checkbox" />
            {{ t('contributions.allowSelfReport') }}</label
          ><label
            ><input v-model="categoryForm.leaderboard_enabled" type="checkbox" />
            {{ t('contributions.leaderboardEnabled') }}</label
          >
        </div>
        <button type="submit" class="primary" :disabled="categoryForm.processing">
          {{ t('contributions.createCategory') }}
        </button>
      </form>
      <form class="ks-surface p-5 xl:self-start" @submit.prevent="recordContribution">
        <h2 class="ks-display text-xl font-semibold">
          {{ t('contributions.manualContribution') }}
        </h2>
        <div class="mt-4 space-y-3">
          <label
            >{{ t('contributions.member')
            }}<select v-model="recordForm.membership_id" required class="field">
              <option value="" disabled>{{ t('contributions.selectMember') }}</option>
              <option v-for="m in reporting.members" :key="m.id" :value="m.id">
                {{ m.name }} · {{ m.email }}
              </option>
            </select></label
          ><label
            >{{ t('contributions.category')
            }}<select v-model="recordForm.category_id" required class="field">
              <option value="" disabled>{{ t('contributions.selectCategory') }}</option>
              <option v-for="c in recordable" :key="c.id" :value="c.id">
                {{ c.name }} ({{ c.unit }})
              </option>
            </select></label
          ><label
            >{{ t('contributions.value')
            }}<input
              v-model.number="recordForm.value"
              required
              min="0"
              step=".01"
              type="number"
              class="field" /></label
          ><label
            >{{ t('contributions.evidenceNote')
            }}<textarea v-model="recordForm.evidence" maxlength="4000" class="field" /></label
          ><button type="submit" class="primary" :disabled="recordForm.processing">
            {{ t('contributions.recordPending') }}
          </button>
        </div>
      </form>
    </section>

    <section class="ks-surface mt-5 p-5">
      <div class="flex flex-wrap justify-between gap-3">
        <div>
          <h2 class="ks-display text-xl font-semibold">
            {{ t('contributions.attendanceQuality') }}
          </h2>
          <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">
            {{ t('contributions.attendanceQualityHelp') }}
          </p>
        </div>
        <div class="flex gap-2">
          <button type="button" class="btn" @click="reconcile">
            {{ t('contributions.reconcileAttendance') }}</button
          ><button type="button" class="btn" @click="refresh">
            {{ t('contributions.refreshQuality') }}
          </button>
        </div>
      </div>
      <div v-if="reporting.dataQualityFlags.length" class="mt-4 space-y-2">
        <div
          v-for="f in reporting.dataQualityFlags"
          :key="f.id"
          class="flex justify-between gap-3 rounded-md border border-[var(--ks-border)] p-3"
        >
          <div>
            <strong :class="f.severity === 'error' ? 'text-red-300' : 'text-amber-300'">{{
              f.severity
            }}</strong>
            <p>{{ f.message }}</p>
            <small>{{ date(f.detectedAt) }}</small>
          </div>
          <button type="button" class="text-[var(--ks-gold-strong)]" @click="resolve(f.id)">
            {{ t('contributions.resolve') }}
          </button>
        </div>
      </div>
      <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">
        {{ t('contributions.noFlags') }}
      </p>
    </section>

    <section class="ks-surface mt-5 overflow-hidden">
      <h2 class="section-title">{{ t('contributions.approvalQueue') }}</h2>
      <div class="overflow-x-auto">
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('contributions.member') }}</th>
              <th>{{ t('contributions.category') }}</th>
              <th>{{ t('contributions.value') }}</th>
              <th>{{ t('contributions.source') }}</th>
              <th>{{ t('contributions.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in reporting.pendingRecords" :key="r.id">
              <td>{{ r.memberName }}</td>
              <td>{{ r.categoryName }}</td>
              <td>{{ amount(r.value, r.unit) }}</td>
              <td>{{ text(r.source) }}</td>
              <td>
                <button type="button" class="me-3 text-green-300" @click="approve(r.id)">
                  {{ t('contributions.approve') }}</button
                ><button
                  type="button"
                  class="me-3 text-[var(--ks-blue-strong)]"
                  @click="correct(r)"
                >
                  {{ t('contributions.correct') }}</button
                ><button type="button" class="text-red-300" @click="reverse(r)">
                  {{ t('contributions.reverse') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="!reporting.pendingRecords.length" class="p-5 text-sm text-[var(--ks-text-muted)]">
        {{ t('contributions.noPending') }}
      </p>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-3">
      <form class="ks-surface p-5" @submit.prevent="schedule">
        <h2 class="ks-display text-xl font-semibold">{{ t('contributions.scheduledReport') }}</h2>
        <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">
          {{ t('contributions.scheduleHelp') }}
        </p>
        <div class="mt-3 space-y-2">
          <label
            >{{ t('contributions.recipient')
            }}<select v-model="scheduleForm.recipient_membership_id" required class="field">
              <option value="" disabled>{{ t('contributions.selectMember') }}</option>
              <option v-for="m in reporting.members" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select></label
          ><label
            >{{ t('contributions.name')
            }}<input v-model="scheduleForm.name" required class="field" /></label
          ><label
            >{{ t('contributions.cadence')
            }}<select v-model="scheduleForm.cadence" class="field">
              <option value="daily">daily</option>
              <option value="weekly">weekly</option>
              <option value="monthly">monthly</option>
            </select></label
          ><label
            >{{ t('contributions.timezone')
            }}<input v-model="scheduleForm.timezone" required class="field" /></label
          ><label
            >{{ t('contributions.firstDelivery')
            }}<input
              v-model="scheduleForm.next_due_at"
              required
              type="datetime-local"
              class="field" /></label
          ><button type="submit" class="primary">{{ t('contributions.createSchedule') }}</button>
        </div>
      </form>
      <div class="ks-surface p-5">
        <h2 class="ks-display text-xl font-semibold">{{ t('contributions.reportSchedules') }}</h2>
        <article v-for="s in reporting.reportSchedules" :key="s.id" class="card">
          <strong>{{ s.name }}</strong>
          <p>{{ member(s.recipientMembershipId) }} · {{ text(s.cadence) }} · {{ s.timezone }}</p>
          <small>{{ t('contributions.nextDue') }}: {{ date(s.nextDueAt) }}</small>
        </article>
        <p v-if="!reporting.reportSchedules.length">{{ t('contributions.noSchedules') }}</p>
      </div>
      <div class="ks-surface p-5">
        <h2 class="ks-display text-xl font-semibold">{{ t('contributions.reportHistory') }}</h2>
        <article v-for="run in reporting.recentReportRuns" :key="run.id" class="card">
          <div class="flex justify-between">
            <strong>{{ run.format }}</strong
            ><span>{{ text(run.status) }}</span>
          </div>
          <small>{{
            t('contributions.reportVersionRows', {
              version: run.reportVersion,
              rows: run.rowCount ?? t('contributions.queued'),
            })
          }}</small>
          <p v-if="run.checksum" class="font-mono text-[10px] break-all">
            sha256 {{ run.checksum }}
          </p>
        </article>
        <p v-if="!reporting.recentReportRuns.length">{{ t('contributions.noReportRuns') }}</p>
      </div>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-2">
      <div class="ks-surface p-5">
        <h2 class="ks-display text-xl font-semibold">
          {{ t('contributions.configuredCategories') }}
        </h2>
        <article v-for="c in reporting.categories" :key="c.id" class="card">
          <div class="flex justify-between">
            <strong>{{ c.name }}</strong
            ><strong>{{ amount(c.approvedTotal, c.unit) }}</strong>
          </div>
          <small
            >{{ text(c.dataClass) }} · {{ text(c.period) }} · {{ t('contributions.goal') }}
            {{ c.goal ?? t('contributions.noGoal') }}</small
          >
          <p v-if="c.calculationDescription">{{ c.calculationDescription }}</p>
          <small v-if="!c.leaderboardEnabled" class="text-amber-300">{{
            t('contributions.leaderboardOptOut')
          }}</small>
        </article>
      </div>
      <div class="ks-surface p-5">
        <h2 class="ks-display text-xl font-semibold">
          {{ t('contributions.categoryLeaderboards') }}
        </h2>
        <article v-for="b in reporting.leaderboards" :key="b.categoryId" class="card">
          <strong>{{ b.name }}</strong
          ><small>{{ b.calculationDescription }}</small>
          <ol>
            <li
              v-for="(e, i) in b.entries.slice(0, 10)"
              :key="e.membershipId"
              class="flex justify-between"
            >
              <span>{{ i + 1 }}. {{ e.name }}</span
              ><strong>{{ amount(e.value, b.unit) }}</strong>
            </li>
          </ol>
        </article>
        <p v-if="!reporting.leaderboards.length">{{ t('contributions.noLeaderboards') }}</p>
      </div>
    </section>

    <section class="ks-surface mt-5 overflow-hidden">
      <h2 class="section-title">{{ t('contributions.recentRecords') }}</h2>
      <div class="overflow-x-auto">
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('contributions.member') }}</th>
              <th>{{ t('contributions.category') }}</th>
              <th>{{ t('contributions.value') }}</th>
              <th>{{ t('contributions.status') }}</th>
              <th>{{ t('contributions.source') }}</th>
              <th>{{ t('contributions.recorded') }}</th>
              <th>{{ t('contributions.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in reporting.recentRecords" :key="r.id">
              <td>{{ r.memberName }}</td>
              <td>{{ r.categoryName }}</td>
              <td>{{ amount(r.value, r.unit) }}</td>
              <td>{{ text(r.status) }}</td>
              <td>{{ text(r.source) }}</td>
              <td>{{ date(r.recordedAt) }}</td>
              <td>
                <button type="button" class="me-3 text-[var(--ks-blue-strong)]" @click="correct(r)">
                  {{ t('contributions.correct') }}</button
                ><button
                  v-if="r.status !== 'reversed'"
                  type="button"
                  class="text-red-300"
                  @click="reverse(r)"
                >
                  {{ t('contributions.reverse') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AppLayout>
</template>
<style scoped>
label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--ks-text-secondary);
}
.field {
  margin-top: 0.25rem;
  width: 100%;
  border: 1px solid var(--ks-border);
  border-radius: 0.375rem;
  background: var(--ks-bg);
  padding: 0.5rem 0.75rem;
}
.formgrid {
  margin-top: 1rem;
  display: grid;
  gap: 0.75rem;
}
@media (min-width: 640px) {
  .formgrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
.primary {
  margin-top: 0.75rem;
  border-radius: 0.375rem;
  background: var(--ks-blue);
  padding: 0.5rem 1rem;
  font-weight: 600;
  color: white;
}
.btn {
  border: 1px solid var(--ks-border);
  border-radius: 0.375rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.75rem;
}
.metric {
  padding: 1rem;
}
.metric b {
  display: block;
  margin-top: 0.25rem;
  font-size: 1.875rem;
}
.metric small,
.card small {
  color: var(--ks-text-muted);
}
.section-title {
  border-bottom: 1px solid var(--ks-border);
  padding: 1rem;
  font-size: 1.25rem;
  font-weight: 600;
}
.table {
  min-width: 52rem;
  width: 100%;
  font-size: 0.875rem;
}
.table th,
.table td {
  padding: 0.75rem 1rem;
  text-align: start;
}
.table thead {
  background: rgb(0 0 0/0.25);
  color: var(--ks-text-muted);
}
.table tbody tr {
  border-top: 1px solid var(--ks-border);
}
.card {
  margin-top: 0.75rem;
  border: 1px solid var(--ks-border);
  border-radius: 0.375rem;
  padding: 0.75rem;
}
</style>
