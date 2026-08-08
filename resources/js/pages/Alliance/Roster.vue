<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type Entry = {
  id: string;
  gamePlayerId: string | null;
  name: string;
  gameRole: string | null;
  state: string;
  joinedAt: string | null;
  leftAt: string | null;
  lastObservedAt: string | null;
  source: string;
  membership: { id: string; name: string; email: string } | null;
};

defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  canManage: boolean;
  entries: Entry[];
}>();
</script>

<template>
  <Head :title="`Roster · ${alliance.name}`" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 text-slate-100 lg:px-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/alliance">
          ← Back to alliance
        </Link>
        <p class="mt-5 text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Kingdom {{ alliance.kingdom ?? 'not set' }}
        </p>
        <h1 class="mt-2 text-3xl font-bold">Alliance roster</h1>
        <p class="mt-2 text-sm text-slate-400">
          Current manually maintained game-player roster. Historical power snapshots arrive in the
          next KINGDOMS-001 slice.
        </p>
      </div>
      <Link
        v-if="canManage"
        class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950"
        href="/alliance/roster/manage"
      >
        Manage roster
      </Link>
    </div>

    <section class="mt-8 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60">
      <div v-if="entries.length" class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-950/60 text-slate-400">
            <tr>
              <th class="px-5 py-3">Player</th>
              <th class="px-5 py-3">Game ID</th>
              <th class="px-5 py-3">Role</th>
              <th class="px-5 py-3">State</th>
              <th class="px-5 py-3">Linked member</th>
              <th class="px-5 py-3">Last observed</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="entry in entries" :key="entry.id">
              <td class="px-5 py-4 font-semibold">{{ entry.name }}</td>
              <td class="px-5 py-4 text-slate-400">{{ entry.gamePlayerId ?? '—' }}</td>
              <td class="px-5 py-4 text-slate-400">{{ entry.gameRole ?? '—' }}</td>
              <td class="px-5 py-4 capitalize">{{ entry.state }}</td>
              <td class="px-5 py-4 text-slate-400">{{ entry.membership?.name ?? 'Unlinked' }}</td>
              <td class="px-5 py-4 text-slate-400">
                {{ entry.lastObservedAt ? new Date(entry.lastObservedAt).toLocaleString() : '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="p-8 text-sm text-slate-400">No players are tracked yet.</p>
    </section>
  </main>
</template>
