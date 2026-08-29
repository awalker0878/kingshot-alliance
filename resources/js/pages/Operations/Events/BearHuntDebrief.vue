<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Delta = {
  current: number | null;
  previous: number | null;
  delta: number | null;
  percentChange: number | null;
  state: 'available' | 'previous_zero' | 'unavailable';
};
type AttendanceComparison = {
  current: string | null;
  previous: string | null;
  state: 'available' | 'unavailable';
};
type RallySummary = {
  available: boolean;
  participated: number | null;
  led: number | null;
  joined: number | null;
};
type Governor = {
  playerId: string;
  playerName: string | null;
  damage: number;
  rank: number | null;
  acceptedReportCount: number;
  recordedAt: string | null;
  attendanceStatus: string | null;
  rallies: RallySummary;
};
type HistoryRun = {
  occurrenceId: string;
  startsAt: string;
  endsAt: string;
  status: string;
  totalDamage: number | null;
  governorCount: number;
  personalDamage: number | null;
  personalRank: number | null;
  attendance: {
    available: boolean;
    total: number;
    present: number;
    absent: number;
    excused: number;
    unknown: number;
    ratePercent: number | null;
    personalStatus: string | null;
  };
  rallies: {
    available: boolean;
    participated: number;
    led: number;
    joined: number;
    personalParticipated: number | null;
    personalLed: number | null;
    personalJoined: number | null;
  };
};
type TrendPoint = {
  occurrenceId: string;
  startsAt: string;
  damage?: number | null;
  rank?: number | null;
  attendanceStatus?: string | null;
  rallies?: number | null;
  ralliesAvailable?: boolean;
  totalDamage?: number | null;
  governorCount?: number;
  attendanceRatePercent?: number | null;
  attendanceAvailable?: boolean;
  recordedRallies?: number | null;
};

type Debrief = {
  run: {
    occurrenceId: string;
    eventId: string;
    allianceId: string;
    title: string | null;
    startsAt: string;
    endsAt: string;
    status: string;
  };
  summary: {
    resultsAvailable: boolean;
    totalDamage: number | null;
    governorCount: number;
    acceptedReportCount: number;
    attendance: {
      available: boolean;
      total: number;
      ratePercent: number | null;
      byStatus: Record<string, number>;
    };
    rallies: {
      available: boolean;
      recordedAssignments: number;
      participated: number | null;
      led: number | null;
      joined: number | null;
    };
    unmatchedGovernorCount: number;
  };
  governors: Governor[];
  personal: {
    playerId: string;
    playerName: string;
    result: Governor | null;
    attendanceStatus: string | null;
    rallies: RallySummary;
  };
  unmatchedGovernors: Array<{
    evidenceId: string;
    receivedAt: string | null;
    reviewHref: string;
    rows: Array<{
      ordinal: number;
      observedName: string | null;
      reportedRank: number | null;
      damage: number | null;
      confidence: number | null;
    }>;
  }>;
  canReviewEvidence: boolean;
  previousRun: HistoryRun | null;
  comparison: {
    allianceDamage: Delta;
    governorCount: Delta;
    attendancePresent: Delta;
    attendanceRate: Delta;
    recordedRallies: Delta;
    personalDamage: Delta;
    personalRank: { current: number | null; previous: number | null; movement: number | null };
    personalAttendance: AttendanceComparison;
    personalRallies: Delta;
  } | null;
  signals: {
    allianceDamage: 'unknown' | 'increased' | 'decreased' | 'unchanged';
    personalDamage: 'unknown' | 'increased' | 'decreased' | 'unchanged';
    personalRallies: 'unknown' | 'increased' | 'decreased' | 'unchanged';
    newPersonalBest: boolean;
    acceptedResult: boolean;
    evidenceStatus: 'accepted' | 'recorded_without_accepted_evidence' | 'unavailable';
    reviewPending: boolean;
  };
  personalTrend: TrendPoint[];
  allianceTrend: TrendPoint[];
  runs: HistoryRun[];
};

const props = defineProps<{
  user: { name: string; email: string };
  userTimezone: string;
  debrief: Debrief;
}>();

const { t, formatDate, formatNumber } = useLocale();
const title = computed(() => props.debrief.run.title || t('debrief.title'));
const personalMax = computed(() =>
  Math.max(1, ...props.debrief.personalTrend.map((point) => point.damage ?? 0)),
);
const allianceMax = computed(() =>
  Math.max(1, ...props.debrief.allianceTrend.map((point) => point.totalDamage ?? 0)),
);

function runDate(value: string): string {
  return formatDate(value, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}
function shortDate(value: string): string {
  return formatDate(value, { month: 'short', day: 'numeric' });
}
function damage(value: number | null | undefined): string {
  if (value === null || value === undefined) return t('debrief.notRecorded');
  return formatNumber(value, { notation: 'compact', maximumFractionDigits: 2 });
}
function count(value: number | null | undefined, available = true): string {
  return available && value !== null && value !== undefined
    ? formatNumber(value)
    : t('debrief.notRecorded');
}
function attendance(value: string | null | undefined): string {
  return value ? t(`events.attendanceStatuses.${value}`) : t('debrief.notRecorded');
}
function attendanceRate(value: number | null | undefined, available = true): string {
  return available && value !== null && value !== undefined
    ? `${formatNumber(value, { maximumFractionDigits: 1 })}%`
    : t('debrief.notRecorded');
}
function rank(value: number | null | undefined): string {
  return value === null || value === undefined
    ? t('debrief.notRecorded')
    : `#${formatNumber(value)}`;
}
function deltaText(
  value: Delta | null | undefined,
  unit: 'damage' | 'count' | 'rate' = 'count',
): string {
  if (!value || value.state === 'unavailable' || value.delta === null) {
    return t('debrief.notComparable');
  }
  if (value.delta === 0) return t('debrief.noChange');
  const direction = value.delta > 0 ? t('debrief.increased') : t('debrief.decreased');
  const absolute = Math.abs(value.delta);
  const amount =
    unit === 'damage'
      ? damage(absolute)
      : unit === 'rate'
        ? `${formatNumber(absolute, { maximumFractionDigits: 2 })}%`
        : formatNumber(absolute);
  if (value.percentChange !== null && unit !== 'rate') {
    return t('debrief.changeWithPercent', {
      direction,
      amount,
      percent: formatNumber(Math.abs(value.percentChange), { maximumFractionDigits: 1 }),
    });
  }
  return t('debrief.change', { direction, amount });
}
function rankMovement(): string {
  const movement = props.debrief.comparison?.personalRank.movement;
  if (movement === null || movement === undefined) return t('debrief.notComparable');
  if (movement === 0) return t('debrief.noChange');
  return movement > 0
    ? t('debrief.rankUp', { count: formatNumber(movement) })
    : t('debrief.rankDown', { count: formatNumber(Math.abs(movement)) });
}
function attendanceComparisonText(): string {
  const comparison = props.debrief.comparison?.personalAttendance;
  if (!comparison || comparison.state === 'unavailable') return t('debrief.notComparable');
  return `${t('debrief.previousHunt')}: ${attendance(comparison.previous)}`;
}
function barWidth(value: number | null | undefined, max: number): string {
  if (value === null || value === undefined) return '0%';
  return `${Math.max(3, Math.round((value / max) * 100))}%`;
}
function direction(value: Debrief['signals']['personalDamage']): string {
  return t(`debrief.signals.directions.${value}`);
}
</script>

<template>
  <Head :title="`${t('debrief.title')} · ${title}`" />

  <AppLayout :user="user">
    <RoomBanner
      :eyebrow="t('debrief.eyebrow')"
      :title="t('debrief.title')"
      :subtitle="runDate(debrief.run.startsAt)"
      image="/images/kingshot/v4/event-command.svg"
      compact
    >
      <template #actions>
        <Link :href="`/events/${debrief.run.occurrenceId}`" class="ks-command-link">
          ← {{ t('events.show.back') }}
        </Link>
        <Link
          v-if="debrief.canReviewEvidence"
          :href="`/events/${debrief.run.occurrenceId}/screenshot-intake`"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('evidence.openIntake') }}
        </Link>
      </template>
      <template #aside>
        <span class="ks-status" data-tone="info">{{ debrief.run.status }}</span>
      </template>
    </RoomBanner>

    <p class="mt-4 max-w-4xl text-sm leading-6 text-[var(--ks-muted)]">
      {{ t('debrief.subtitle') }}
    </p>

    <section class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" :aria-label="t('debrief.title')">
      <StatSeal
        :label="t('debrief.totalDamage')"
        :value="damage(debrief.summary.totalDamage)"
        icon="⚔"
      />
      <StatSeal
        :label="t('debrief.governors')"
        :value="
          debrief.summary.resultsAvailable
            ? formatNumber(debrief.summary.governorCount)
            : t('debrief.notRecorded')
        "
        icon="♟"
        tone="stone"
      />
      <StatSeal
        :label="t('debrief.attendance')"
        :value="
          debrief.summary.attendance.available
            ? formatNumber(debrief.summary.attendance.byStatus.present ?? 0)
            : t('debrief.notRecorded')
        "
        icon="✓"
        tone="teal"
      />
      <StatSeal
        :label="t('debrief.recordedRallies')"
        :value="count(debrief.summary.rallies.participated, debrief.summary.rallies.available)"
        icon="⚑"
      />
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.35fr)_minmax(19rem,.65fr)]">
      <div class="min-w-0 space-y-5">
        <section class="ks-surface-gold p-5 sm:p-6" aria-labelledby="your-hunt-heading">
          <p class="ks-kicker">{{ debrief.personal.playerName }}</p>
          <h2 id="your-hunt-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('debrief.yourHunt') }}
          </h2>
          <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4">
              <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
                {{ t('debrief.damage') }}
              </p>
              <p class="mt-1 text-xl font-semibold">
                {{ damage(debrief.personal.result?.damage) }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ deltaText(debrief.comparison?.personalDamage, 'damage') }}
              </p>
            </div>
            <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4">
              <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
                {{ t('debrief.rank') }}
              </p>
              <p class="mt-1 text-xl font-semibold">
                {{ rank(debrief.personal.result?.rank) }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">{{ rankMovement() }}</p>
            </div>
            <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4">
              <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
                {{ t('debrief.attendance') }}
              </p>
              <p class="mt-1 text-xl font-semibold">
                {{ attendance(debrief.personal.attendanceStatus) }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ attendanceComparisonText() }}
              </p>
            </div>
            <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4">
              <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
                {{ t('debrief.recordedRallies') }}
              </p>
              <p class="mt-1 text-xl font-semibold">
                {{
                  count(debrief.personal.rallies.participated, debrief.personal.rallies.available)
                }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ deltaText(debrief.comparison?.personalRallies) }}
              </p>
            </div>
          </div>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4">
              <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
                {{ t('debrief.signals.evidence') }}
              </p>
              <p class="mt-1 font-semibold">
                {{ t(`debrief.signals.evidenceStates.${debrief.signals.evidenceStatus}`) }}
              </p>
              <p v-if="debrief.signals.reviewPending" class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('debrief.signals.reviewPending') }}
              </p>
            </div>
            <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4">
              <p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
                {{ t('debrief.signals.factualSignals') }}
              </p>
              <p v-if="debrief.signals.newPersonalBest" class="mt-1 font-semibold">
                {{ t('debrief.signals.newPersonalBest') }}
              </p>
              <p class="mt-1 text-sm text-[var(--ks-muted)]">
                {{
                  t('debrief.signals.damageDirection', {
                    direction: direction(debrief.signals.personalDamage),
                  })
                }}
                ·
                {{
                  t('debrief.signals.rallyDirection', {
                    direction: direction(debrief.signals.personalRallies),
                  })
                }}
              </p>
            </div>
          </div>
        </section>

        <section class="ks-surface p-5 sm:p-6" aria-labelledby="leaderboard-heading">
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('debrief.alliancePerformance') }}</p>
              <h2 id="leaderboard-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{ t('debrief.leaderboard') }}
              </h2>
            </div>
            <span class="text-sm text-[var(--ks-muted)]">
              {{
                t('debrief.reportCount', {
                  count: formatNumber(debrief.summary.acceptedReportCount),
                })
              }}
            </span>
          </div>

          <div v-if="debrief.governors.length" class="mt-4 space-y-3 sm:hidden">
            <article
              v-for="governor in debrief.governors"
              :key="governor.playerId"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/10 p-4"
            >
              <div class="flex min-w-0 items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="text-xs text-[var(--ks-muted)]">{{ rank(governor.rank) }}</p>
                  <h3 class="font-semibold break-words">
                    {{ governor.playerName || t('debrief.unknownGovernor') }}
                  </h3>
                </div>
                <strong class="shrink-0 text-right">{{ damage(governor.damage) }}</strong>
              </div>
              <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt class="text-xs text-[var(--ks-muted)]">{{ t('debrief.attendance') }}</dt>
                  <dd class="mt-1">{{ attendance(governor.attendanceStatus) }}</dd>
                </div>
                <div class="text-right">
                  <dt class="text-xs text-[var(--ks-muted)]">
                    {{ t('debrief.recordedRallies') }}
                  </dt>
                  <dd class="mt-1">
                    {{ count(governor.rallies.participated, governor.rallies.available) }}
                  </dd>
                </div>
              </dl>
            </article>
          </div>

          <div v-if="debrief.governors.length" class="mt-4 hidden overflow-x-auto sm:block">
            <table class="w-full min-w-[42rem] text-left text-sm">
              <thead class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
                <tr>
                  <th class="px-2 py-2">{{ t('debrief.rank') }}</th>
                  <th class="px-2 py-2">{{ t('debrief.governor') }}</th>
                  <th class="px-2 py-2 text-right">{{ t('debrief.damage') }}</th>
                  <th class="px-2 py-2">{{ t('debrief.attendance') }}</th>
                  <th class="px-2 py-2 text-right">{{ t('debrief.recordedRallies') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="governor in debrief.governors"
                  :key="governor.playerId"
                  class="border-t border-[var(--ks-border)]"
                >
                  <td class="px-2 py-3 font-semibold">{{ rank(governor.rank) }}</td>
                  <td class="max-w-60 px-2 py-3 break-words">
                    {{ governor.playerName || t('debrief.unknownGovernor') }}
                  </td>
                  <td class="px-2 py-3 text-right font-semibold">
                    {{ damage(governor.damage) }}
                  </td>
                  <td class="px-2 py-3">{{ attendance(governor.attendanceStatus) }}</td>
                  <td class="px-2 py-3 text-right">
                    {{ count(governor.rallies.participated, governor.rallies.available) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="mt-4 text-sm text-[var(--ks-muted)]">{{ t('debrief.noResults') }}</p>
        </section>

        <section
          v-if="debrief.canReviewEvidence && debrief.unmatchedGovernors.length"
          class="ks-surface p-5 sm:p-6"
          aria-labelledby="needs-review-heading"
        >
          <p class="ks-kicker">{{ t('debrief.needsReview') }}</p>
          <h2 id="needs-review-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{
              t('debrief.unmatchedGovernors', {
                count: formatNumber(debrief.summary.unmatchedGovernorCount),
              })
            }}
          </h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('debrief.reviewHelp') }}
          </p>
          <div class="mt-4 space-y-3">
            <article
              v-for="item in debrief.unmatchedGovernors"
              :key="item.evidenceId"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4"
            >
              <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-[var(--ks-muted)]">
                  {{ item.receivedAt ? runDate(item.receivedAt) : t('debrief.notRecorded') }}
                </p>
                <Link :href="item.reviewHref" class="ks-command-link" data-variant="secondary">
                  {{ t('debrief.reviewImport') }}
                </Link>
              </div>
              <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                <li
                  v-for="row in item.rows"
                  :key="row.ordinal"
                  class="rounded border border-[var(--ks-border)] bg-black/10 p-3 text-sm"
                >
                  <strong>{{ row.observedName || t('debrief.unknownGovernor') }}</strong>
                  <span class="mt-1 block text-[var(--ks-muted)]">
                    {{ damage(row.damage) }} · {{ rank(row.reportedRank) }}
                  </span>
                </li>
              </ul>
            </article>
          </div>
        </section>

        <section class="ks-surface p-5 sm:p-6" aria-labelledby="trends-heading">
          <p class="ks-kicker">{{ t('debrief.trends') }}</p>
          <h2 id="trends-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('debrief.runTrends') }}
          </h2>
          <div class="mt-5 grid gap-6 lg:grid-cols-2">
            <div>
              <h3 class="font-semibold">{{ t('debrief.yourDamageTrend') }}</h3>
              <ol class="mt-3 space-y-3" :aria-label="t('debrief.yourDamageTrend')">
                <li
                  v-for="point in debrief.personalTrend"
                  :key="point.occurrenceId"
                  class="grid grid-cols-[5rem_1fr_auto] items-center gap-x-2 gap-y-1 text-sm"
                >
                  <span class="text-[var(--ks-muted)]">{{ shortDate(point.startsAt) }}</span>
                  <span class="h-2 rounded bg-white/10" aria-hidden="true">
                    <span
                      class="block h-full rounded bg-current opacity-70"
                      :style="{ width: barWidth(point.damage, personalMax) }"
                    />
                  </span>
                  <span class="min-w-16 text-right">{{ damage(point.damage) }}</span>
                  <span class="col-span-3 text-xs leading-5 text-[var(--ks-muted)]">
                    {{ t('debrief.rank') }}: {{ rank(point.rank) }} · {{ t('debrief.attendance') }}:
                    {{ attendance(point.attendanceStatus) }} · {{ t('debrief.recordedRallies') }}:
                    {{ count(point.rallies, point.ralliesAvailable ?? false) }}
                  </span>
                </li>
              </ol>
            </div>
            <div>
              <h3 class="font-semibold">{{ t('debrief.allianceDamageTrend') }}</h3>
              <ol class="mt-3 space-y-3" :aria-label="t('debrief.allianceDamageTrend')">
                <li
                  v-for="point in debrief.allianceTrend"
                  :key="point.occurrenceId"
                  class="grid grid-cols-[5rem_1fr_auto] items-center gap-x-2 gap-y-1 text-sm"
                >
                  <span class="text-[var(--ks-muted)]">{{ shortDate(point.startsAt) }}</span>
                  <span class="h-2 rounded bg-white/10" aria-hidden="true">
                    <span
                      class="block h-full rounded bg-current opacity-70"
                      :style="{ width: barWidth(point.totalDamage, allianceMax) }"
                    />
                  </span>
                  <span class="min-w-16 text-right">{{ damage(point.totalDamage) }}</span>
                  <span class="col-span-3 text-xs leading-5 text-[var(--ks-muted)]">
                    {{
                      t('debrief.governorCount', {
                        count: formatNumber(point.governorCount ?? 0),
                      })
                    }}
                    · {{ t('debrief.attendance') }}:
                    {{
                      attendanceRate(
                        point.attendanceRatePercent,
                        point.attendanceAvailable ?? false,
                      )
                    }}
                    · {{ t('debrief.recordedRallies') }}:
                    {{ count(point.recordedRallies, point.ralliesAvailable ?? false) }}
                  </span>
                </li>
              </ol>
            </div>
          </div>
        </section>
      </div>

      <aside class="space-y-5">
        <section class="ks-surface p-5" aria-labelledby="previous-heading">
          <p class="ks-kicker">{{ t('debrief.previousHunt') }}</p>
          <h2 id="previous-heading" class="ks-display mt-1 text-xl font-semibold">
            {{
              debrief.previousRun
                ? shortDate(debrief.previousRun.startsAt)
                : t('debrief.noPrevious')
            }}
          </h2>
          <div v-if="debrief.previousRun && debrief.comparison" class="mt-4 space-y-3 text-sm">
            <div class="rounded border border-[var(--ks-border)] p-3">
              <p class="text-[var(--ks-muted)]">{{ t('debrief.totalDamage') }}</p>
              <strong class="mt-1 block">{{ damage(debrief.summary.totalDamage) }}</strong>
              <span class="mt-1 block text-xs">
                {{ deltaText(debrief.comparison.allianceDamage, 'damage') }}
              </span>
            </div>
            <div class="rounded border border-[var(--ks-border)] p-3">
              <p class="text-[var(--ks-muted)]">{{ t('debrief.governors') }}</p>
              <strong class="mt-1 block">
                {{
                  debrief.summary.resultsAvailable
                    ? formatNumber(debrief.summary.governorCount)
                    : t('debrief.notRecorded')
                }}
              </strong>
              <span class="mt-1 block text-xs">
                {{ deltaText(debrief.comparison.governorCount) }}
              </span>
            </div>
            <div class="rounded border border-[var(--ks-border)] p-3">
              <p class="text-[var(--ks-muted)]">{{ t('debrief.attendance') }}</p>
              <strong class="mt-1 block">
                <template v-if="debrief.summary.attendance.available">
                  {{ formatNumber(debrief.summary.attendance.byStatus.present ?? 0) }} ·
                  {{
                    debrief.summary.attendance.ratePercent === null
                      ? t('debrief.notRecorded')
                      : `${formatNumber(debrief.summary.attendance.ratePercent, {
                          maximumFractionDigits: 1,
                        })}%`
                  }}
                </template>
                <template v-else>{{ t('debrief.notRecorded') }}</template>
              </strong>
              <span class="mt-1 block text-xs">
                {{ deltaText(debrief.comparison.attendancePresent) }} ·
                {{ deltaText(debrief.comparison.attendanceRate, 'rate') }}
              </span>
            </div>
            <div class="rounded border border-[var(--ks-border)] p-3">
              <p class="text-[var(--ks-muted)]">{{ t('debrief.recordedRallies') }}</p>
              <strong class="mt-1 block">
                {{ count(debrief.summary.rallies.participated, debrief.summary.rallies.available) }}
              </strong>
              <span class="mt-1 block text-xs">
                {{ deltaText(debrief.comparison.recordedRallies) }}
              </span>
            </div>
          </div>
          <p v-else class="mt-3 text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('debrief.noPreviousHelp') }}
          </p>
        </section>

        <section class="ks-surface p-5" aria-labelledby="history-heading">
          <p class="ks-kicker">{{ t('debrief.history') }}</p>
          <h2 id="history-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ t('debrief.runHistory') }}
          </h2>
          <ol class="mt-4 space-y-2">
            <li v-for="run in debrief.runs" :key="run.occurrenceId">
              <Link
                :href="`/events/${run.occurrenceId}/debrief`"
                class="block rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-3 transition hover:border-[var(--ks-border-strong)]"
                :aria-current="run.occurrenceId === debrief.run.occurrenceId ? 'page' : undefined"
              >
                <span class="flex items-center justify-between gap-2">
                  <strong>{{ shortDate(run.startsAt) }}</strong>
                  <span class="text-sm">{{ damage(run.totalDamage) }}</span>
                </span>
                <span class="mt-1 block text-xs text-[var(--ks-muted)]">
                  {{ t('debrief.governorCount', { count: formatNumber(run.governorCount) }) }}
                </span>
              </Link>
            </li>
          </ol>
        </section>
      </aside>
    </div>
  </AppLayout>
</template>
