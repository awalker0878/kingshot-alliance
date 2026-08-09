<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type TrackingSummary = {
  name: string;
  tag: string | null;
  state: string;
  kingdom: string;
  contextCurrent: boolean;
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
          {{ alliance.name }} · current Kingdom {{ alliance.kingdom ?? 'not configured' }}. This
          view contains neutral alliance identity and tracking state only; observations and
          diplomacy are not part of this slice.
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
            Names and tags are display data, not stable identity keys.
          </p>
        </div>
        <p class="text-sm text-slate-400">{{ tracking.length }} record(s)</p>
      </div>

      <div v-if="tracking.length" class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <thead class="text-xs tracking-wide text-slate-400 uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">Alliance</th>
              <th class="px-3 py-3 font-semibold">Tag</th>
              <th class="px-3 py-3 font-semibold">Kingdom context</th>
              <th class="px-3 py-3 font-semibold">Tracking state</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="(entry, index) in tracking" :key="`${entry.name}-${entry.kingdom}-${index}`">
              <td class="px-3 py-4 font-medium text-slate-100">{{ entry.name }}</td>
              <td class="px-3 py-4 text-slate-300">{{ entry.tag ?? '—' }}</td>
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
