<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

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
  membership: { name: string } | null;
};

type Filters = {
  q: string;
  state: string;
  linkage: string;
  role: string;
  observation: string;
};

const props = defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  canManage: boolean;
  entries: Entry[];
  filters: Filters;
  roleOptions: string[];
  staleAfterDays: number;
}>();

const filters = reactive<Filters>({ ...props.filters });

function applyFilters(): void {
  router.get(
    '/alliance/roster',
    Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')),
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  );
}

function clearFilters(): void {
  filters.q = '';
  filters.state = '';
  filters.linkage = '';
  filters.role = '';
  filters.observation = '';
  applyFilters();
}
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

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
      <form class="grid gap-4 lg:grid-cols-5" aria-label="Roster filters" @submit.prevent="applyFilters">
        <div class="lg:col-span-2">
          <label class="text-sm font-medium" for="roster-search">Search player or game ID</label>
          <input
            id="roster-search"
            v-model="filters.q"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="160"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="roster-state-filter">State</label>
          <select
            id="roster-state-filter"
            v-model="filters.state"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="">All states</option>
            <option value="active">Active</option>
            <option value="tracked">Tracked</option>
            <option value="left">Left</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="roster-linkage-filter">Membership link</label>
          <select
            id="roster-linkage-filter"
            v-model="filters.linkage"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="">All</option>
            <option value="linked">Linked</option>
            <option value="unlinked">Unlinked</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="roster-role-filter">Game role / rank</label>
          <select
            id="roster-role-filter"
            v-model="filters.role"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="">All roles</option>
            <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="roster-observation-filter">Observation</label>
          <select
            id="roster-observation-filter"
            v-model="filters.observation"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="">Any freshness</option>
            <option value="current">Current</option>
            <option value="stale">Stale</option>
            <option value="missing">Missing</option>
          </select>
        </div>
        <div class="flex items-end gap-3 lg:col-span-4">
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950"
            type="submit"
          >
            Apply filters
          </button>
          <button
            class="rounded-lg border border-slate-700 px-4 py-2 font-semibold text-slate-300"
            type="button"
            @click="clearFilters"
          >
            Clear
          </button>
        </div>
      </form>
      <p class="mt-4 text-xs text-slate-500">
        In this slice, “stale” means the manually maintained roster record has not been observed for
        {{ staleAfterDays }} days. Snapshot freshness is introduced separately.
      </p>
    </section>

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
      <p v-else class="p-8 text-sm text-slate-400">No roster entries match these filters.</p>
    </section>
  </main>
</template>
