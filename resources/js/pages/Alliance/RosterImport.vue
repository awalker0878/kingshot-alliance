<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

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
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  schema: { version: string; headers: string[]; maxBytes: number; maxRows: number };
  importRecord: ImportRecord | null;
}>();

const { locale, t, formatNumber } = useLocale();
const uploadForm = useForm<{ file: File | null }>({ file: null });
const resolutionDrafts = reactive<Record<string, string>>({
  ...(props.importRecord?.resolutions ?? {}),
});
const commitForm = useForm<{ resolutions: Record<string, string> }>({ resolutions: {} });

const unresolvedAmbiguous = computed(() => {
  if (!props.importRecord) return 0;

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
  if (!props.importRecord) return;

  commitForm.resolutions = { ...resolutionDrafts };
  commitForm.post(`/alliance/roster/import/${props.importRecord.id}/commit`, {
    preserveScroll: true,
  });
}

function formatBytes(bytes: number): string {
  return new Intl.NumberFormat(locale.value, { maximumFractionDigits: 0 }).format(bytes / 1024) + ' KiB';
}

function formatPower(value: string): string {
  try {
    return new Intl.NumberFormat(locale.value).format(BigInt(value));
  } catch {
    return value;
  }
}

function resolutionLabel(row: PreviewRow): string {
  return `${t('rosterImport.resolutionErrors')}: ${row.row}, ${row.data.name}`;
}

function outcomeTone(outcome: PreviewRow['outcome']): string {
  if (outcome === 'create') return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (outcome === 'update') return 'border-blue-400/25 bg-blue-500/10 text-blue-200';
  if (outcome === 'ambiguous') return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
  return 'border-red-400/25 bg-red-500/10 text-red-200';
}

function statusTone(status: string): string {
  if (status === 'committed') return 'border-green-400/25 bg-green-500/10 text-green-200';
  return 'border-[var(--ks-border)] bg-black/15 text-[var(--ks-text-secondary)]';
}
</script>

<template>
  <Head :title="`${t('rosterImport.title')} · ${alliance.name}`" />

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div class="max-w-3xl">
        <Link
          class="inline-flex min-h-10 items-center text-sm font-semibold text-[var(--ks-blue-strong)] hover:text-white"
          href="/alliance/roster/manage"
        >
          ← {{ t('roster.manage') }}
        </Link>
        <p class="mt-4 text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('roster.eyebrow', { kingdom: alliance.kingdom ?? t('roster.kingdomNotSet') }) }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">{{ t('rosterImport.title') }}</h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('rosterImport.subtitle') }}
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a
          class="inline-flex min-h-10 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-xs font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-border-strong)] hover:text-white"
          href="/alliance/roster/export.csv?scope=member"
        >
          {{ t('rosterImport.exportCurrent') }}
        </a>
        <a
          class="inline-flex min-h-10 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-gold)]/45 bg-[var(--ks-gold-soft)] px-3 py-2 text-xs font-semibold text-[var(--ks-gold-strong)] transition hover:border-[var(--ks-gold)] hover:text-white"
          href="/alliance/roster/export.csv?scope=management"
        >
          {{ t('rosterImport.exportManager') }}
        </a>
      </div>
    </header>

    <section class="mt-6 grid gap-5 xl:grid-cols-3">
      <article class="ks-surface-gold p-5 sm:p-6 xl:col-span-2">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
              {{ t('rosterImport.uploadPreview') }}
            </p>
            <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
              {{
                t('rosterImport.schemaHelp', {
                  version: schema.version,
                  rows: formatNumber(schema.maxRows),
                  bytes: formatBytes(schema.maxBytes),
                })
              }}
            </p>
          </div>
        </div>

        <form class="mt-5" @submit.prevent="preview">
          <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="roster-csv">
            {{ t('rosterImport.csvFile') }}
          </label>
          <input
            id="roster-csv"
            accept=".csv,text/csv"
            :aria-describedby="uploadForm.errors.file ? 'roster-csv-error' : undefined"
            :aria-invalid="uploadForm.errors.file ? 'true' : undefined"
            class="mt-2 block w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm file:me-3 file:rounded-md file:border-0 file:bg-[var(--ks-blue-soft)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[var(--ks-blue-strong)]"
            required
            type="file"
            @change="selectFile"
          />
          <button
            class="mt-4 min-h-10 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--ks-blue-strong)] disabled:opacity-60"
            :disabled="uploadForm.processing || uploadForm.file === null"
            type="submit"
          >
            {{ t('rosterImport.validatePreview') }}
          </button>
          <p
            v-if="uploadForm.errors.file"
            id="roster-csv-error"
            class="mt-3 text-sm text-red-300"
            role="alert"
          >
            {{ uploadForm.errors.file }}
          </p>
        </form>
      </article>

      <aside class="ks-surface p-5 sm:p-6">
        <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
          {{ t('rosterImport.requiredColumns') }}
        </p>
        <code class="mt-3 block overflow-x-auto rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/25 p-3 text-xs text-[var(--ks-text-secondary)]">
          {{ schema.headers.join(',') }}
        </code>
        <p class="mt-4 text-xs leading-5 text-[var(--ks-text-muted)]">
          {{ t('rosterImport.requirementsHelp') }}
        </p>
      </aside>
    </section>

    <template v-if="importRecord">
      <section class="ks-surface-gold mt-6 overflow-hidden">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-[var(--ks-border)] p-4 sm:p-5">
          <div class="min-w-0">
            <h2 class="ks-display truncate text-xl font-semibold">
              {{ t('rosterImport.preview', { filename: importRecord.filename }) }}
            </h2>
            <p class="mt-1 truncate text-xs text-[var(--ks-text-muted)]">SHA-256 {{ importRecord.checksum }}</p>
          </div>
          <span
            :class="statusTone(importRecord.status)"
            class="rounded-full border px-3 py-1 text-xs font-semibold capitalize"
          >
            {{ importRecord.status }}
          </span>
        </div>

        <dl class="grid grid-cols-2 divide-x divide-y divide-[var(--ks-border)] sm:grid-cols-5 sm:divide-y-0">
          <div class="p-4">
            <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase">{{ t('rosterImport.rows') }}</dt>
            <dd class="ks-display mt-2 text-2xl font-semibold">{{ formatNumber(importRecord.rowCount) }}</dd>
          </div>
          <div class="p-4">
            <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-green-300 uppercase">{{ t('rosterImport.creates') }}</dt>
            <dd class="ks-display mt-2 text-2xl font-semibold">{{ formatNumber(importRecord.createCount) }}</dd>
          </div>
          <div class="p-4">
            <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-blue-300 uppercase">{{ t('rosterImport.updates') }}</dt>
            <dd class="ks-display mt-2 text-2xl font-semibold">{{ formatNumber(importRecord.updateCount) }}</dd>
          </div>
          <div class="p-4">
            <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-amber-300 uppercase">{{ t('rosterImport.ambiguous') }}</dt>
            <dd class="ks-display mt-2 text-2xl font-semibold">{{ formatNumber(importRecord.ambiguousCount) }}</dd>
          </div>
          <div class="col-span-2 p-4 sm:col-span-1">
            <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-red-300 uppercase">{{ t('rosterImport.rejected') }}</dt>
            <dd class="ks-display mt-2 text-2xl font-semibold">{{ formatNumber(importRecord.rejectedCount) }}</dd>
          </div>
        </dl>
      </section>

      <div
        v-if="importRecord.status === 'committed' && importRecord.committedSummary"
        class="mt-4 rounded-[var(--ks-radius-md)] border border-green-400/25 bg-green-500/10 p-4 text-sm text-green-200"
        role="status"
        aria-live="polite"
      >
        {{
          t('rosterImport.committedSummary', {
            rows: importRecord.committedSummary.rows_committed,
            creates: importRecord.committedSummary.roster_entries_created,
            updates: importRecord.committedSummary.roster_entries_updated,
            snapshots: importRecord.committedSummary.snapshots_created,
          })
        }}
      </div>

      <section class="ks-surface mt-4 overflow-hidden">
        <div v-if="importRecord.rows.length" class="lg:hidden">
          <article
            v-for="row in importRecord.rows"
            :key="row.row"
            class="border-b border-[var(--ks-border)] p-4 last:border-b-0"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs text-[var(--ks-text-muted)]">{{ t('rosterImport.csvRow') }} {{ row.row }}</p>
                <p class="mt-1 truncate font-semibold">{{ row.data.name }}</p>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  {{ row.data.game_player_id ?? t('rosterImport.gameIdNotSupplied') }}
                </p>
              </div>
              <div class="text-end">
                <strong>{{ formatPower(row.data.power) }}</strong>
                <span
                  :class="outcomeTone(row.outcome)"
                  class="mt-2 block rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                >
                  {{ row.outcome }}
                </span>
              </div>
            </div>

            <div class="mt-4">
              <ul v-if="row.errors.length" class="space-y-1 text-sm text-red-300" role="alert">
                <li v-for="error in row.errors" :key="error">{{ error }}</li>
              </ul>
              <select
                v-else-if="row.outcome === 'ambiguous'"
                v-model="resolutionDrafts[String(row.row)]"
                :aria-label="resolutionLabel(row)"
                class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                :disabled="importRecord.status === 'committed'"
              >
                <option value="">{{ t('rosterImport.chooseResolution') }}</option>
                <option value="create">{{ t('rosterImport.createNewIdentity') }}</option>
                <option
                  v-for="candidate in row.candidates"
                  :key="candidate.entry_id"
                  :value="candidate.entry_id"
                >
                  {{
                    t('rosterImport.updateCandidate', {
                      name: candidate.name,
                      gameId: candidate.game_player_id ?? '—',
                      state: candidate.state,
                    })
                  }}
                </option>
              </select>
              <p v-else-if="row.outcome === 'update'" class="text-sm text-[var(--ks-text-secondary)]">
                {{ t('rosterImport.stableMatch', { entry: row.target_entry_id ?? '—' }) }}
              </p>
              <p v-else class="text-sm text-[var(--ks-text-secondary)]">{{ t('rosterImport.newIdentity') }}</p>
            </div>
          </article>
        </div>

        <div v-if="importRecord.rows.length" class="hidden overflow-x-auto lg:block">
          <table class="w-full min-w-[64rem] text-sm">
            <thead class="bg-black/25 text-[0.68rem] font-bold tracking-[0.08em] text-[var(--ks-text-muted)] uppercase">
              <tr>
                <th class="px-4 py-3 text-start">{{ t('rosterImport.csvRow') }}</th>
                <th class="px-4 py-3 text-start">{{ t('roster.player') }}</th>
                <th class="px-4 py-3 text-start">{{ t('roster.power') }}</th>
                <th class="px-4 py-3 text-start">{{ t('roster.state') }}</th>
                <th class="px-4 py-3 text-start">{{ t('rosterImport.previewOutcome') }}</th>
                <th class="px-4 py-3 text-start">{{ t('rosterImport.resolutionErrors') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ks-border)]">
              <tr v-for="row in importRecord.rows" :key="row.row" class="align-top transition hover:bg-white/[0.025]">
                <td class="px-4 py-4 text-[var(--ks-text-muted)]">{{ row.row }}</td>
                <td class="px-4 py-4">
                  <span class="font-semibold">{{ row.data.name }}</span>
                  <span class="mt-1 block text-xs text-[var(--ks-text-muted)]">
                    {{ row.data.game_player_id ?? t('rosterImport.gameIdNotSupplied') }}
                  </span>
                </td>
                <td class="px-4 py-4 font-semibold">{{ formatPower(row.data.power) }}</td>
                <td class="px-4 py-4 text-[var(--ks-text-secondary)]">{{ row.data.state }}</td>
                <td class="px-4 py-4">
                  <span
                    :class="outcomeTone(row.outcome)"
                    class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                  >
                    {{ row.outcome }}
                  </span>
                </td>
                <td class="min-w-72 px-4 py-4">
                  <ul v-if="row.errors.length" class="space-y-1 text-red-300" role="alert">
                    <li v-for="error in row.errors" :key="error">{{ error }}</li>
                  </ul>
                  <select
                    v-else-if="row.outcome === 'ambiguous'"
                    v-model="resolutionDrafts[String(row.row)]"
                    :aria-label="resolutionLabel(row)"
                    class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
                    :disabled="importRecord.status === 'committed'"
                  >
                    <option value="">{{ t('rosterImport.chooseResolution') }}</option>
                    <option value="create">{{ t('rosterImport.createNewIdentity') }}</option>
                    <option
                      v-for="candidate in row.candidates"
                      :key="candidate.entry_id"
                      :value="candidate.entry_id"
                    >
                      {{
                        t('rosterImport.updateCandidate', {
                          name: candidate.name,
                          gameId: candidate.game_player_id ?? '—',
                          state: candidate.state,
                        })
                      }}
                    </option>
                  </select>
                  <span v-else-if="row.outcome === 'update'" class="text-[var(--ks-text-secondary)]">
                    {{ t('rosterImport.stableMatch', { entry: row.target_entry_id ?? '—' }) }}
                  </span>
                  <span v-else class="text-[var(--ks-text-secondary)]">{{ t('rosterImport.newIdentity') }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="importRecord.status !== 'committed'" class="ks-surface mt-4 p-5">
        <p v-if="importRecord.rejectedCount" class="text-sm text-red-300" role="alert">
          {{ t('rosterImport.rejectedBlock') }}
        </p>
        <p v-else-if="unresolvedAmbiguous" class="text-sm text-amber-300" role="status">
          {{ t('rosterImport.unresolvedRows', { count: unresolvedAmbiguous }) }}
        </p>
        <button
          class="mt-3 min-h-10 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--ks-blue-strong)] disabled:opacity-60"
          :disabled="commitForm.processing || importRecord.rejectedCount > 0 || unresolvedAmbiguous > 0"
          type="button"
          @click="commitImport"
        >
          {{ t('rosterImport.confirmAtomic') }}
        </button>
        <p v-if="Object.keys(commitForm.errors).length" class="mt-3 text-sm text-red-300" role="alert">
          {{ t('rosterImport.commitError') }}
        </p>
      </section>
    </template>
  </AppLayout>
</template>
