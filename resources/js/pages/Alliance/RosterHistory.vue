<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

type Snapshot = {
  id: string;
  observedName: string;
  power: string;
  progressionLevel: string | null;
  observedAllianceTag: string | null;
  capturedAt: string;
  source: string;
  actorName?: string;
};

const props = defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  entry: {
    id: string;
    gamePlayerId: string | null;
    name: string;
    gameRole: string | null;
    state: string;
    membership: { name: string } | null;
  };
  canManage: boolean;
  latest: Snapshot | null;
  snapshots: Snapshot[];
  staleAfterDays: number;
}>();

function localDateTimeValue(date = new Date()): string {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
  return local.toISOString().slice(0, 16);
}

function formatInteger(value: string): string {
  return value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function snapshotState(snapshot: Snapshot | null): string {
  if (snapshot === null) {
    return 'Missing';
  }

  const staleAt = Date.now() - props.staleAfterDays * 24 * 60 * 60 * 1000;
  return new Date(snapshot.capturedAt).getTime() < staleAt ? 'Stale' : 'Current';
}

const snapshotForm = useForm({
  observed_name: props.entry.name,
  power: '',
  progression_level: '',
  observed_alliance_tag: '',
  captured_at: localDateTimeValue(),
});

function recordSnapshot(): void {
  snapshotForm
    .transform((data) => ({
      ...data,
      captured_at: new Date(data.captured_at).toISOString(),
    }))
    .post(`/alliance/roster/${props.entry.id}/snapshots`, {
      preserveScroll: true,
      onSuccess: () => {
        snapshotForm.power = '';
        snapshotForm.progression_level = '';
        snapshotForm.observed_alliance_tag = '';
        snapshotForm.captured_at = localDateTimeValue();
      },
    });
}
</script>

<template>
  <Head :title="`Snapshot history · ${entry.name}`" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 text-slate-100 lg:px-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link
          class="text-sm font-semibold text-cyan-300 hover:text-cyan-200"
          href="/alliance/roster"
        >
          ← Alliance roster
        </Link>
        <p class="mt-5 text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Kingdom {{ alliance.kingdom ?? 'not set' }}
        </p>
        <h1 class="mt-2 text-3xl font-bold">{{ entry.name }}</h1>
        <p class="mt-2 text-sm text-slate-400">
          Game ID: {{ entry.gamePlayerId ?? 'unknown' }} · Role: {{ entry.gameRole ?? '—' }} ·
          State: {{ entry.state }} · Linked member: {{ entry.membership?.name ?? 'unlinked' }}
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

    <section class="mt-8 grid gap-4 md:grid-cols-4" aria-label="Latest player snapshot">
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Snapshot state</p>
        <p class="mt-2 text-2xl font-bold">{{ snapshotState(latest) }}</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Latest power</p>
        <p class="mt-2 text-2xl font-bold">{{ latest ? formatInteger(latest.power) : '—' }}</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Progression / level</p>
        <p class="mt-2 text-2xl font-bold">{{ latest?.progressionLevel ?? '—' }}</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Observed alliance / tag</p>
        <p class="mt-2 text-2xl font-bold">{{ latest?.observedAllianceTag ?? '—' }}</p>
      </div>
    </section>

    <p class="mt-3 text-xs text-slate-500">
      Current means the latest recorded snapshot is no more than {{ staleAfterDays }} days old.
      Missing means no snapshot has been recorded. Historical rows remain immutable observations.
    </p>

    <section
      v-if="canManage"
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/60 p-6"
      aria-labelledby="record-snapshot"
    >
      <h2 id="record-snapshot" class="text-xl font-semibold">Record snapshot</h2>
      <p class="mt-2 text-sm text-slate-400">
        Record what was observed at the capture time. Retrying the same accepted observation is
        idempotent; a later capture time creates a new historical row.
      </p>

      <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="recordSnapshot">
        <div>
          <label class="text-sm font-medium" for="snapshot-name">Observed player name</label>
          <input
            id="snapshot-name"
            v-model="snapshotForm.observed_name"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="160"
            required
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="snapshot-power">Power</label>
          <input
            id="snapshot-power"
            v-model="snapshotForm.power"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            inputmode="numeric"
            maxlength="19"
            pattern="[0-9]+"
            required
          />
          <p v-if="snapshotForm.errors.power" class="mt-1 text-sm text-rose-300">
            {{ snapshotForm.errors.power }}
          </p>
        </div>
        <div>
          <label class="text-sm font-medium" for="snapshot-level">Progression / level</label>
          <input
            id="snapshot-level"
            v-model="snapshotForm.progression_level"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="64"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="snapshot-tag">Observed alliance / tag</label>
          <input
            id="snapshot-tag"
            v-model="snapshotForm.observed_alliance_tag"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="32"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="snapshot-captured">Captured at</label>
          <input
            id="snapshot-captured"
            v-model="snapshotForm.captured_at"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            type="datetime-local"
            required
          />
          <p v-if="snapshotForm.errors.captured_at" class="mt-1 text-sm text-rose-300">
            {{ snapshotForm.errors.captured_at }}
          </p>
        </div>
        <div class="flex items-end">
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            :disabled="snapshotForm.processing"
            type="submit"
          >
            Record snapshot
          </button>
        </div>
      </form>
    </section>

    <section class="mt-8 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60">
      <div class="border-b border-slate-800 px-5 py-4">
        <h2 class="text-xl font-semibold">Snapshot history</h2>
        <p class="mt-1 text-sm text-slate-400">
          Newest capture first. Up to the latest 250 observations are shown.
        </p>
      </div>
      <div v-if="snapshots.length" class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-950/60 text-slate-400">
            <tr>
              <th class="px-5 py-3">Captured</th>
              <th class="px-5 py-3">Name</th>
              <th class="px-5 py-3">Power</th>
              <th class="px-5 py-3">Progression</th>
              <th class="px-5 py-3">Alliance / tag</th>
              <th class="px-5 py-3">Source</th>
              <th v-if="canManage" class="px-5 py-3">Recorded by</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="snapshot in snapshots" :key="snapshot.id">
              <td class="px-5 py-4 text-slate-400">
                {{ new Date(snapshot.capturedAt).toLocaleString() }}
              </td>
              <td class="px-5 py-4 font-semibold">{{ snapshot.observedName }}</td>
              <td class="px-5 py-4">{{ formatInteger(snapshot.power) }}</td>
              <td class="px-5 py-4 text-slate-400">{{ snapshot.progressionLevel ?? '—' }}</td>
              <td class="px-5 py-4 text-slate-400">{{ snapshot.observedAllianceTag ?? '—' }}</td>
              <td class="px-5 py-4 text-slate-400">{{ snapshot.source }}</td>
              <td v-if="canManage" class="px-5 py-4 text-slate-400">
                {{ snapshot.actorName ?? '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="p-8 text-sm text-slate-400">
        No snapshots have been recorded for this player.
      </p>
    </section>
  </main>
</template>
