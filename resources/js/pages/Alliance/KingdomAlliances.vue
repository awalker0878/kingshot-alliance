<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type LatestObservation = {
  observedName: string;
  observedTag: string | null;
  power: string | null;
  memberCount: number | null;
  capturedAt: string;
  source: string;
};

type TrackingSummary = {
  name: string;
  tag: string | null;
  state: string;
  kingdom: string;
  contextCurrent: boolean;
  historyUrl: string;
  freshness: 'current' | 'stale' | 'missing';
  latestObservation: LatestObservation | null;
  diplomacyState: string;
  diplomacyNeedsReview: boolean;
};

defineProps<{
  alliance: {
    id: string;
    name: string;
    kingdom: string | null;
  };
  canManage: boolean;
  tracking: TrackingSummary[];
}>();

function formatCapturedAt(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function stateLabel(value: string): string {
  if (value === 'nap') return 'NAP';
  return value.charAt(0).toUpperCase() + value.slice(1).replaceAll('_', ' ');
}
</script>

<template>
  <Head title="Kingdom alliances" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Kingdom intelligence
        </p>
        <h1 class="mt-2 text-3xl font-bold">Tracked game-side alliances</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-400">
          {{ alliance.name }} · current Kingdom {{ alliance.kingdom ?? 'not configured' }}. Latest
          accepted observations are factual history; they never infer or automatically change
          diplomacy state.
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <Link
          v-if="canManage"
          class="rounded-lg bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950"
          href="/alliance/kingdom-alliances/manage"
        >
          Manage tracking
        </Link>
        <Link
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200"
          href="/dashboard"
        >
          Dashboard
        </Link>
      </div>
    </header>

    <section class="mt-10 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 class="text-xl font-semibold">Alliance tracking</h2>
          <p class="mt-1 text-sm text-slate-400">
            Current means the latest accepted observation was captured within 30 days. Missing is
            different from zero. Diplomacy labels are explicit manager-maintained state.
          </p>
        </div>
        <p class="text-sm text-slate-400">{{ tracking.length }} record(s)</p>
      </div>

      <div v-if="tracking.length" class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <thead class="text-xs tracking-wide text-slate-400 uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">Alliance</th>
              <th class="px-3 py-3 font-semibold">Latest facts</th>
              <th class="px-3 py-3 font-semibold">Freshness</th>
              <th class="px-3 py-3 font-semibold">Diplomacy</th>
              <th class="px-3 py-3 font-semibold">Kingdom context</th>
              <th class="px-3 py-3 font-semibold">Tracking</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="(entry, index) in tracking" :key="`${entry.name}-${entry.kingdom}-${index}`">
              <td class="px-3 py-4">
                <Link class="font-medium text-cyan-300 hover:underline" :href="entry.historyUrl">
                  {{ entry.name }}
                </Link>
                <p class="mt-1 text-xs text-slate-400">{{ entry.tag ?? 'No tag recorded' }}</p>
              </td>
              <td class="px-3 py-4 text-slate-300">
                <template v-if="entry.latestObservation">
                  <p>
                    Power {{ entry.latestObservation.power ?? 'missing' }} · Members
                    {{ entry.latestObservation.memberCount ?? 'missing' }}
                  </p>
                  <p class="mt-1 text-xs text-slate-500">
                    {{ formatCapturedAt(entry.latestObservation.capturedAt) }}
                  </p>
                </template>
                <span v-else> No accepted observation </span>
              </td>
              <td class="px-3 py-4 text-slate-300">
                <span class="rounded-full border border-slate-700 px-2 py-1 text-xs font-semibold">
                  {{ entry.freshness }}
                </span>
              </td>
              <td class="px-3 py-4 text-slate-300">
                <span class="font-semibold">{{ stateLabel(entry.diplomacyState) }}</span>
                <span
                  v-if="entry.diplomacyNeedsReview"
                  class="ml-2 rounded-full bg-amber-950 px-2 py-1 text-xs font-semibold text-amber-300"
                >
                  Review due
                </span>
              </td>
              <td class="px-3 py-4 text-slate-300">
                Kingdom {{ entry.kingdom }}
                <span
                  v-if="!entry.contextCurrent"
                  class="ml-2 rounded-full bg-amber-950 px-2 py-1 text-xs font-semibold text-amber-300"
                >
                  Historical context
                </span>
              </td>
              <td class="px-3 py-4 text-slate-300">{{ entry.state }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-else
        class="mt-6 rounded-xl border border-dashed border-slate-700 p-5 text-sm text-slate-400"
      >
        No game-side alliances are tracked yet.
      </p>
    </section>
  </main>
</template>
