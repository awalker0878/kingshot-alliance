<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type Observation = {
  observedName: string;
  observedTag: string | null;
  power: string | null;
  memberCount: number | null;
  capturedAt: string;
};

type CurrentRow = {
  shareTargetId: string;
  sourceAlliance: { id: string; name: string };
  gameAlliance: { name: string; tag: string | null };
  freshness: 'current' | 'stale' | 'missing';
  latestObservation: Observation | null;
};

type HistoryItem = Observation & {
  freshness: 'current' | 'stale';
};

type History = {
  shareTargetId: string;
  sourceAlliance: { id: string; name: string };
  gameAlliance: { name: string; tag: string | null };
  items: HistoryItem[];
  nextCursor: string | null;
};

defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  canManage: boolean;
  current: CurrentRow[];
  selectedHistory: History | null;
}>();

function formatCapturedAt(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function historyUrl(target: string, cursor?: string | null): string {
  const parameters = new URLSearchParams({ target });
  if (cursor) parameters.set('cursor', cursor);
  return `/alliance/kingdom-sharing?${parameters.toString()}`;
}
</script>

<template>
  <Head title="Shared Kingdom intelligence" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Shared Kingdom intelligence
        </p>
        <h1 class="mt-2 text-3xl font-bold">Shared game-alliance facts</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-400">
          {{ alliance.name }} · current Kingdom {{ alliance.kingdom ?? 'not configured' }}. These are
          factual observations explicitly shared by another platform Alliance. They never change
          tracking, diplomacy, transfers, or roster state automatically.
        </p>
      </div>
      <nav aria-label="Shared intelligence actions" class="flex flex-wrap gap-3">
        <Link
          v-if="canManage"
          class="rounded-lg bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950"
          href="/alliance/kingdom-sharing/manage"
        >
          Manage sharing
        </Link>
        <Link
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200"
          href="/dashboard"
        >
          Dashboard
        </Link>
      </nav>
    </header>

    <section
      aria-labelledby="shared-current-heading"
      class="mt-10 rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
    >
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 id="shared-current-heading" class="text-xl font-semibold">Current shared facts</h2>
          <p class="mt-1 text-sm text-slate-400">
            Only explicitly granted targets are shown. Missing means no accepted observation exists;
            it is not zero.
          </p>
        </div>
        <p class="text-sm text-slate-400">{{ current.length }} shared target(s)</p>
      </div>

      <div v-if="current.length" class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <caption class="sr-only">
            Current game-alliance facts shared with this Alliance
          </caption>
          <thead class="text-xs tracking-wide text-slate-400 uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">Source</th>
              <th class="px-3 py-3 font-semibold">Game alliance</th>
              <th class="px-3 py-3 font-semibold">Latest facts</th>
              <th class="px-3 py-3 font-semibold">Freshness</th>
              <th class="px-3 py-3 font-semibold">History</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="entry in current" :key="entry.shareTargetId">
              <td class="px-3 py-4 font-medium text-slate-200">{{ entry.sourceAlliance.name }}</td>
              <td class="px-3 py-4">
                <p class="font-medium text-slate-200">{{ entry.gameAlliance.name }}</p>
                <p class="mt-1 text-xs text-slate-400">
                  {{ entry.gameAlliance.tag ?? 'No tag recorded' }}
                </p>
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
                <span v-else>No accepted observation</span>
              </td>
              <td class="px-3 py-4 text-slate-300">
                <span class="rounded-full border border-slate-700 px-2 py-1 text-xs font-semibold">
                  {{ entry.freshness }}
                </span>
              </td>
              <td class="px-3 py-4">
                <Link
                  class="font-semibold text-cyan-300 hover:underline"
                  :href="historyUrl(entry.shareTargetId)"
                >
                  View history
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-else
        class="mt-6 rounded-xl border border-dashed border-slate-700 p-5 text-sm text-slate-400"
      >
        No active shared targets are available. Removed, revoked, or invalidated sharing is not
        displayed.
      </p>
    </section>

    <section
      v-if="selectedHistory"
      aria-labelledby="shared-history-heading"
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
    >
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 id="shared-history-heading" class="text-xl font-semibold">Bounded shared history</h2>
          <p class="mt-1 text-sm text-slate-400">
            {{ selectedHistory.gameAlliance.name }} · shared by
            {{ selectedHistory.sourceAlliance.name }}. Pages contain at most 50 accepted observations
            and one traversal stops at 250.
          </p>
        </div>
        <Link
          class="text-sm font-semibold text-cyan-300 hover:underline"
          href="/alliance/kingdom-sharing"
        >
          Close history
        </Link>
      </div>

      <div v-if="selectedHistory.items.length" class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <caption class="sr-only">
            Accepted observations for the selected shared game alliance
          </caption>
          <thead class="text-xs tracking-wide text-slate-400 uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">Captured</th>
              <th class="px-3 py-3 font-semibold">Observed identity</th>
              <th class="px-3 py-3 font-semibold">Power</th>
              <th class="px-3 py-3 font-semibold">Members</th>
              <th class="px-3 py-3 font-semibold">Freshness</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="item in selectedHistory.items" :key="`${item.capturedAt}-${item.observedName}`">
              <td class="px-3 py-4 text-slate-300">{{ formatCapturedAt(item.capturedAt) }}</td>
              <td class="px-3 py-4">
                <p class="font-medium text-slate-200">{{ item.observedName }}</p>
                <p class="mt-1 text-xs text-slate-400">
                  {{ item.observedTag ?? 'No tag recorded' }}
                </p>
              </td>
              <td class="px-3 py-4 text-slate-300">{{ item.power ?? 'missing' }}</td>
              <td class="px-3 py-4 text-slate-300">{{ item.memberCount ?? 'missing' }}</td>
              <td class="px-3 py-4 text-slate-300">{{ item.freshness }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-else class="mt-6 text-sm text-slate-400">
        No accepted observations are available for this target.
      </p>

      <div class="mt-6 flex items-center justify-between gap-4">
        <p class="text-xs text-slate-500">
          History continuation is opaque and re-authorized on every page.
        </p>
        <Link
          v-if="selectedHistory.nextCursor"
          class="rounded-lg border border-cyan-800 px-4 py-2 text-sm font-semibold text-cyan-300"
          :href="historyUrl(selectedHistory.shareTargetId, selectedHistory.nextCursor)"
        >
          Older observations
        </Link>
      </div>
    </section>
  </main>
</template>
