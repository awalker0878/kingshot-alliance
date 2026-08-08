<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

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
  snapshotQuality: {
    current: number;
    stale: number;
    missing: number;
    staleAfterDays: number;
  };
  recentRoster: {
    days: number;
    joins: number;
    departures: number;
  };
  linkage: {
    linked: number;
    total: number;
    percent: string | null;
  };
  sevenDayTrend: {
    days: number;
    change: string | null;
    comparablePlayers: number;
  };
  thirtyDayTrend: {
    days: number;
    change: string | null;
    comparablePlayers: number;
  };
  comparisons: PlayerComparison[];
};

defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  canManage: boolean;
  metrics: Metrics;
}>();

function formatDecimal(value: string | null): string {
  if (value === null) {
    return '—';
  }

  const negative = value.startsWith('-');
  const unsigned = negative ? value.slice(1) : value;
  const [whole, fraction] = unsigned.split('.');
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const formatted = fraction === undefined ? grouped : `${grouped}.${fraction}`;

  return negative ? `-${formatted}` : formatted;
}

function formatSigned(value: string | null): string {
  if (value === null) {
    return '—';
  }

  if (value.startsWith('-') || value === '0') {
    return formatDecimal(value);
  }

  return `+${formatDecimal(value)}`;
}

function formatDate(value: string): string {
  return new Date(value).toLocaleString();
}

function comparisonText(comparison: TrendComparison | null): string {
  if (comparison === null) {
    return 'Insufficient history';
  }

  return `${formatSigned(comparison.change)} from ${formatDate(comparison.baselineCapturedAt)}`;
}
</script>

<template>
  <Head :title="`Roster intelligence · ${alliance.name}`" />

  <main class="mx-auto min-h-screen max-w-7xl px-6 py-12 text-slate-100 lg:px-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/alliance/roster">
          ← Alliance roster
        </Link>
        <p class="mt-5 text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Kingdom {{ alliance.kingdom ?? 'not set' }}
        </p>
        <h1 class="mt-2 text-3xl font-bold">Roster intelligence</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-400">
          Operational summaries derived only from recorded roster and snapshot history. Missing data
          is never treated as zero, and trend windows are not interpolated.
        </p>
      </div>
      <Link
        v-if="canManage"
        class="rounded-lg border border-cyan-800 px-4 py-2 font-semibold text-cyan-300 hover:border-cyan-600"
        href="/alliance/roster/manage"
      >
        Manage roster
      </Link>
    </div>

    <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Roster summary">
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Active / tracked players</p>
        <p class="mt-2 text-3xl font-bold">{{ metrics.trackedPlayers }}</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Total recorded power</p>
        <p class="mt-2 text-3xl font-bold">{{ formatDecimal(metrics.totalPower) }}</p>
        <p class="mt-2 text-xs text-slate-500">
          {{ metrics.recordedPowerPlayers }} of {{ metrics.trackedPlayers }} tracked players recorded
        </p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Average recorded power</p>
        <p class="mt-2 text-3xl font-bold">{{ formatDecimal(metrics.averagePower) }}</p>
        <p class="mt-2 text-xs text-slate-500">Rounded to the nearest whole power.</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Median recorded power</p>
        <p class="mt-2 text-3xl font-bold">{{ formatDecimal(metrics.medianPower) }}</p>
        <p class="mt-2 text-xs text-slate-500">Calculated from players with a latest snapshot.</p>
      </div>
    </section>

    <section class="mt-6 grid gap-4 lg:grid-cols-3" aria-label="Roster data quality">
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <h2 class="text-lg font-semibold">Snapshot quality</h2>
        <dl class="mt-4 grid grid-cols-3 gap-3 text-center">
          <div>
            <dt class="text-xs text-slate-500">Current</dt>
            <dd class="mt-1 text-2xl font-bold">{{ metrics.snapshotQuality.current }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">Stale</dt>
            <dd class="mt-1 text-2xl font-bold">{{ metrics.snapshotQuality.stale }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">Missing</dt>
            <dd class="mt-1 text-2xl font-bold">{{ metrics.snapshotQuality.missing }}</dd>
          </div>
        </dl>
        <p class="mt-4 text-xs text-slate-500">
          Stale means the latest snapshot is older than {{ metrics.snapshotQuality.staleAfterDays }}
          days.
        </p>
      </div>

      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <h2 class="text-lg font-semibold">Recent roster movement</h2>
        <dl class="mt-4 grid grid-cols-2 gap-3 text-center">
          <div>
            <dt class="text-xs text-slate-500">Joined</dt>
            <dd class="mt-1 text-2xl font-bold">{{ metrics.recentRoster.joins }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">Departed</dt>
            <dd class="mt-1 text-2xl font-bold">{{ metrics.recentRoster.departures }}</dd>
          </div>
        </dl>
        <p class="mt-4 text-xs text-slate-500">Window: last {{ metrics.recentRoster.days }} days.</p>
      </div>

      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <h2 class="text-lg font-semibold">Membership linkage</h2>
        <p class="mt-3 text-3xl font-bold">
          {{ metrics.linkage.percent === null ? '—' : `${metrics.linkage.percent}%` }}
        </p>
        <p class="mt-2 text-sm text-slate-400">
          {{ metrics.linkage.linked }} of {{ metrics.linkage.total }} active/tracked roster profiles
          are linked to an application membership.
        </p>
      </div>
    </section>

    <section class="mt-6 grid gap-4 lg:grid-cols-2" aria-label="Power trends">
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Aggregate {{ metrics.sevenDayTrend.days }}-day power change</p>
        <p class="mt-2 text-3xl font-bold">{{ formatSigned(metrics.sevenDayTrend.change) }}</p>
        <p class="mt-2 text-sm text-slate-400">
          {{ metrics.sevenDayTrend.comparablePlayers }} comparable players.
        </p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">
          Aggregate {{ metrics.thirtyDayTrend.days }}-day power change
        </p>
        <p class="mt-2 text-3xl font-bold">{{ formatSigned(metrics.thirtyDayTrend.change) }}</p>
        <p class="mt-2 text-sm text-slate-400">
          {{ metrics.thirtyDayTrend.comparablePlayers }} comparable players.
        </p>
      </div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
      <h2 class="text-lg font-semibold">How trend windows work</h2>
      <p class="mt-2 text-sm text-slate-400">
        For an N-day comparison, the baseline is the closest recorded snapshot at or before the
        N-day target, but no older than 2N days. Snapshots newer than the target are not substituted,
        old history outside that range is excluded, and the system does not interpolate missing
        observations. The comparable-player count shows how much history actually supports each
        aggregate change.
      </p>
      <p class="mt-2 text-sm text-slate-400">
        Current totals use each active/tracked player's latest recorded snapshot. Stale snapshots are
        included in recorded-power aggregates but are identified separately; players with no snapshot
        are excluded rather than counted as zero.
      </p>
    </section>

    <section
      v-if="canManage"
      class="mt-8 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60"
      aria-labelledby="player-comparisons"
    >
      <div class="border-b border-slate-800 px-5 py-4">
        <h2 id="player-comparisons" class="text-xl font-semibold">Manager comparison detail</h2>
        <p class="mt-1 text-sm text-slate-400">
          Alphabetical diagnostic view only. Players are not ranked or scored by growth or decline.
        </p>
      </div>
      <div v-if="metrics.comparisons.length" class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-950/60 text-slate-400">
            <tr>
              <th class="px-5 py-3">Player</th>
              <th class="px-5 py-3">Snapshot state</th>
              <th class="px-5 py-3">Membership</th>
              <th class="px-5 py-3">Current power</th>
              <th class="px-5 py-3">7-day comparison</th>
              <th class="px-5 py-3">30-day comparison</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="comparison in metrics.comparisons" :key="comparison.entryId">
              <td class="px-5 py-4 font-semibold">
                <Link
                  class="text-cyan-300 hover:text-cyan-200"
                  :href="`/alliance/roster/${comparison.entryId}/history`"
                >
                  {{ comparison.name }}
                </Link>
              </td>
              <td class="px-5 py-4 capitalize text-slate-400">{{ comparison.snapshotState }}</td>
              <td class="px-5 py-4 text-slate-400">
                {{ comparison.membershipLinked ? 'Linked' : 'Unlinked' }}
              </td>
              <td class="px-5 py-4">
                <template v-if="comparison.current">
                  {{ formatDecimal(comparison.current.power) }}
                  <span class="block text-xs text-slate-500">
                    {{ formatDate(comparison.current.capturedAt) }}
                  </span>
                </template>
                <template v-else>—</template>
              </td>
              <td class="px-5 py-4 text-slate-400">{{ comparisonText(comparison.sevenDay) }}</td>
              <td class="px-5 py-4 text-slate-400">{{ comparisonText(comparison.thirtyDay) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="p-8 text-sm text-slate-400">No active or tracked roster profiles.</p>
    </section>

    <p class="mt-6 text-xs text-slate-500">
      Calculated at {{ formatDate(metrics.asOf) }}. Power growth is a roster observation metric and is
      not a Contribution score.
    </p>
  </main>
</template>
