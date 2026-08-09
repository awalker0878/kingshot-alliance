<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

type Candidate = {
  entry_id: string;
  name: string;
  game_player_id: string | null;
  state: string;
};

type PreviewRow = {
  row: number;
  data: {
    game_player_id: string | null;
    name: string;
    power: string;
    progression_level: string | null;
    alliance_tag: string | null;
    game_role: string | null;
    state: string;
    joined_at: string | null;
    captured_at: string;
  };
  outcome: 'create' | 'update' | 'ambiguous' | 'rejected';
  target_entry_id: string | null;
  candidates: Candidate[];
  errors: string[];
};

type ImportRecord = {
  id: string;
  status: string;
  filename: string;
  checksum: string;
  rowCount: number;
  createCount: number;
  updateCount: number;
  ambiguousCount: number;
  rejectedCount: number;
  rows: PreviewRow[];
  resolutions: Record<string, string>;
  committedSummary: {
    rows_committed: number;
    roster_entries_created: number;
    roster_entries_updated: number;
    snapshots_created: number;
  } | null;
  committedAt: string | null;
};

const props = defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  schema: { version: string; headers: string[]; maxBytes: number; maxRows: number };
  importRecord: ImportRecord | null;
}>();

const uploadForm = useForm<{ file: File | null }>({ file: null });
const resolutionDrafts = reactive<Record<string, string>>({
  ...(props.importRecord?.resolutions ?? {}),
});
const commitForm = useForm<{ resolutions: Record<string, string> }>({ resolutions: {} });

const unresolvedAmbiguous = computed(() => {
  if (!props.importRecord) {
    return 0;
  }

  return props.importRecord.rows.filter(
    (row) => row.outcome === 'ambiguous' && !resolutionDrafts[String(row.row)],
  ).length;
});

function selectFile(event: Event): void {
  const input = event.target as HTMLInputElement;
  uploadForm.file = input.files?.[0] ?? null;
}

function preview(): void {
  uploadForm.post('/alliance/roster/import/preview', {
    forceFormData: true,
    preserveScroll: true,
  });
}

function commitImport(): void {
  if (!props.importRecord) {
    return;
  }

  commitForm.resolutions = { ...resolutionDrafts };
  commitForm.post(`/alliance/roster/import/${props.importRecord.id}/commit`, {
    preserveScroll: true,
  });
}

function formatBytes(bytes: number): string {
  return `${Math.round(bytes / 1024)} KiB`;
}

function formatInteger(value: string): string {
  return value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
</script>

<template>
  <Head :title="`Roster CSV · ${alliance.name}`" />

  <main class="mx-auto min-h-screen max-w-7xl px-6 py-12 text-slate-100 lg:px-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link
          class="text-sm font-semibold text-cyan-300 hover:text-cyan-200"
          href="/alliance/roster/manage"
        >
          ← Manage roster
        </Link>
        <p class="mt-5 text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Kingdom {{ alliance.kingdom ?? 'not set' }}
        </p>
        <h1 class="mt-2 text-3xl font-bold">Controlled CSV migration</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-400">
          Preview every row before persistence. Stable game IDs are the only automatic identity
          match. A display-name match is always treated as ambiguous and requires your explicit
          decision.
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <a
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200"
          href="/alliance/roster/export.csv?scope=member"
        >
          Export current roster
        </a>
        <a
          class="rounded-lg border border-cyan-800 px-4 py-2 text-sm font-semibold text-cyan-300"
          href="/alliance/roster/export.csv?scope=management"
        >
          Export with manager fields
        </a>
      </div>
    </div>

    <section class="mt-8 grid gap-6 lg:grid-cols-2">
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
        <h2 class="text-xl font-semibold">Upload for dry-run preview</h2>
        <p class="mt-2 text-sm text-slate-400">
          Schema {{ schema.version }} · maximum {{ schema.maxRows }} data rows · maximum
          {{ formatBytes(schema.maxBytes) }}. The file must be UTF-8 CSV and is parsed as text only.
        </p>
        <form class="mt-5" @submit.prevent="preview">
          <label class="text-sm font-medium" for="roster-csv">CSV file</label>
          <input
            id="roster-csv"
            accept=".csv,text/csv"
            :aria-describedby="uploadForm.errors.file ? 'roster-csv-error' : undefined"
            :aria-invalid="uploadForm.errors.file ? 'true' : undefined"
            class="mt-2 block w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"
            required
            type="file"
            @change="selectFile"
          />
          <button
            class="mt-4 rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            :disabled="uploadForm.processing || uploadForm.file === null"
            type="submit"
          >
            Validate and preview
          </button>
          <p
            v-if="uploadForm.errors.file"
            id="roster-csv-error"
            class="mt-3 text-sm text-rose-300"
            role="alert"
          >
            {{ uploadForm.errors.file }}
          </p>
        </form>
      </div>

      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
        <h2 class="text-xl font-semibold">Required columns</h2>
        <code class="mt-3 block overflow-x-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-300">
          {{ schema.headers.join(',') }}
        </code>
        <ul class="mt-4 space-y-2 text-sm text-slate-400">
          <li><strong class="text-slate-200">name, power, state</strong> are required.</li>
          <li>state is <code>active</code>, <code>tracked</code>, or <code>left</code>.</li>
          <li>joined_at uses <code>YYYY-MM-DD</code>.</li>
          <li>
            captured_at is optional; when present it must be an ISO-8601 timestamp with timezone.
          </li>
          <li>Blank captured_at values use the deterministic time stored in this preview.</li>
          <li>A repeated stable game ID inside one file is rejected.</li>
        </ul>
      </div>
    </section>

    <template v-if="importRecord">
      <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h2 class="text-xl font-semibold">Preview: {{ importRecord.filename }}</h2>
            <p class="mt-1 text-xs text-slate-500">SHA-256 {{ importRecord.checksum }}</p>
          </div>
          <p class="rounded-full border border-slate-700 px-3 py-1 text-sm capitalize">
            {{ importRecord.status }}
          </p>
        </div>

        <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <div class="rounded-xl bg-slate-950/60 p-4">
            <dt class="text-xs text-slate-500">Rows</dt>
            <dd class="mt-1 text-2xl font-bold">{{ importRecord.rowCount }}</dd>
          </div>
          <div class="rounded-xl bg-slate-950/60 p-4">
            <dt class="text-xs text-slate-500">Creates</dt>
            <dd class="mt-1 text-2xl font-bold">{{ importRecord.createCount }}</dd>
          </div>
          <div class="rounded-xl bg-slate-950/60 p-4">
            <dt class="text-xs text-slate-500">Updates</dt>
            <dd class="mt-1 text-2xl font-bold">{{ importRecord.updateCount }}</dd>
          </div>
          <div class="rounded-xl bg-slate-950/60 p-4">
            <dt class="text-xs text-slate-500">Ambiguous</dt>
            <dd class="mt-1 text-2xl font-bold">{{ importRecord.ambiguousCount }}</dd>
          </div>
          <div class="rounded-xl bg-slate-950/60 p-4">
            <dt class="text-xs text-slate-500">Rejected</dt>
            <dd class="mt-1 text-2xl font-bold">{{ importRecord.rejectedCount }}</dd>
          </div>
        </dl>

        <div
          v-if="importRecord.status === 'committed' && importRecord.committedSummary"
          class="mt-5 rounded-xl border border-emerald-900/70 bg-emerald-950/30 p-4 text-sm text-emerald-200"
          role="status"
          aria-live="polite"
        >
          Committed {{ importRecord.committedSummary.rows_committed }} rows:
          {{ importRecord.committedSummary.roster_entries_created }} roster creates,
          {{ importRecord.committedSummary.roster_entries_updated }} roster updates, and
          {{ importRecord.committedSummary.snapshots_created }} new append-only snapshots.
        </div>

        <div class="mt-6 overflow-x-auto rounded-xl border border-slate-800">
          <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-950/70 text-slate-400">
              <tr>
                <th class="px-4 py-3">CSV row</th>
                <th class="px-4 py-3">Player</th>
                <th class="px-4 py-3">Power</th>
                <th class="px-4 py-3">State</th>
                <th class="px-4 py-3">Preview outcome</th>
                <th class="px-4 py-3">Resolution / errors</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              <tr v-for="row in importRecord.rows" :key="row.row">
                <td class="px-4 py-4 text-slate-400">{{ row.row }}</td>
                <td class="px-4 py-4">
                  <span class="font-semibold">{{ row.data.name }}</span>
                  <span class="block text-xs text-slate-500">
                    Game ID: {{ row.data.game_player_id ?? 'not supplied' }}
                  </span>
                </td>
                <td class="px-4 py-4">{{ formatInteger(row.data.power) }}</td>
                <td class="px-4 py-4 text-slate-400 capitalize">{{ row.data.state }}</td>
                <td class="px-4 py-4 capitalize">{{ row.outcome }}</td>
                <td class="min-w-72 px-4 py-4">
                  <ul
                    v-if="row.errors.length"
                    class="space-y-1 text-rose-300"
                    role="alert"
                  >
                    <li v-for="error in row.errors" :key="error">{{ error }}</li>
                  </ul>
                  <template v-else-if="row.outcome === 'ambiguous'">
                    <label class="sr-only" :for="`resolution-${row.row}`">
                      Resolution for CSV row {{ row.row }}, {{ row.data.name }}
                    </label>
                    <select
                      :id="`resolution-${row.row}`"
                      v-model="resolutionDrafts[String(row.row)]"
                      class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                      :disabled="importRecord.status === 'committed'"
                    >
                      <option value="">Choose a resolution</option>
                      <option value="create">Create a new game-player identity</option>
                      <option
                        v-for="candidate in row.candidates"
                        :key="candidate.entry_id"
                        :value="candidate.entry_id"
                      >
                        Update {{ candidate.name }} · {{ candidate.game_player_id ?? 'no game ID' }} ·
                        {{ candidate.state }}
                      </option>
                    </select>
                  </template>
                  <span v-else-if="row.outcome === 'update'" class="text-slate-400">
                    Stable game ID matches roster entry {{ row.target_entry_id }}.
                  </span>
                  <span v-else class="text-slate-400">New roster identity.</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="importRecord.status !== 'committed'" class="mt-5">
          <p
            v-if="importRecord.rejectedCount"
            class="text-sm text-rose-300"
            role="alert"
          >
            This batch cannot be committed while any row is rejected. Correct the CSV and upload it
            again.
          </p>
          <p v-else-if="unresolvedAmbiguous" class="text-sm text-amber-300" role="status">
            Resolve {{ unresolvedAmbiguous }} ambiguous row(s) before confirmation.
          </p>
          <button
            class="mt-3 rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            :disabled="
              commitForm.processing || importRecord.rejectedCount > 0 || unresolvedAmbiguous > 0
            "
            type="button"
            @click="commitImport"
          >
            Confirm atomic import
          </button>
          <p
            v-if="Object.keys(commitForm.errors).length"
            class="mt-3 text-sm text-rose-300"
            role="alert"
          >
            The import could not be committed. Review the row resolutions or create a fresh preview.
          </p>
        </div>
      </section>
    </template>
  </main>
</template>
