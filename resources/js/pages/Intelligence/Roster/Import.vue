<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import { useContextForm } from '@/composables/useContextForm';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

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
const uploadForm = useContextForm<{ file: File | null }>({ file: null });
const resolutionDrafts = reactive<Record<string, string>>({
  ...(props.importRecord?.resolutions ?? {}),
});
const commitForm = useContextForm<{ resolutions: Record<string, string> }>({ resolutions: {} });

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
  commitForm.post(`/alliance/roster/imports/${props.importRecord.id}/commit`, {
    preserveScroll: true,
  });
}

function rowLabel(row: PreviewRow): string {
  return row.data.name || row.data.game_player_id || `#${row.row}`;
}
</script>

<template>
  <Head :title="t('rosterImport.title')" />
  <AppLayout>
    <section class="ks-surface p-5">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="ks-kicker">{{ t('rosterImport.eyebrow') }}</p>
          <h1 class="ks-display mt-1 text-3xl font-semibold">{{ t('rosterImport.title') }}</h1>
          <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{ t('rosterImport.subtitle') }}
          </p>
        </div>
        <Link href="/alliance/roster" class="ks-command-link" data-variant="secondary">
          {{ t('rosterImport.back') }}
        </Link>
      </div>

      <dl class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <dt class="ks-kicker">{{ t('rosterImport.schemaVersion') }}</dt>
          <dd class="mt-2 font-semibold">{{ schema.version }}</dd>
        </div>
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <dt class="ks-kicker">{{ t('rosterImport.maxRows') }}</dt>
          <dd class="mt-2 font-semibold">{{ formatNumber(schema.maxRows) }}</dd>
        </div>
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <dt class="ks-kicker">{{ t('rosterImport.maxBytes') }}</dt>
          <dd class="mt-2 font-semibold">{{ formatNumber(schema.maxBytes) }}</dd>
        </div>
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <dt class="ks-kicker">{{ t('common.language') }}</dt>
          <dd class="mt-2 font-semibold">{{ locale }}</dd>
        </div>
      </dl>
    </section>

    <section class="ks-surface mt-5 p-5" aria-labelledby="roster-import-upload-heading">
      <h2 id="roster-import-upload-heading" class="ks-display text-xl font-semibold">
        {{ t('rosterImport.uploadTitle') }}
      </h2>
      <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
        {{ t('rosterImport.requiredHeaders', { headers: schema.headers.join(', ') }) }}
      </p>
      <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="preview">
        <label class="min-w-[18rem] flex-1 text-sm font-semibold">
          {{ t('rosterImport.file') }}
          <input type="file" accept=".csv,text/csv" class="ks-input mt-2" @change="selectFile" />
        </label>
        <button
          type="submit"
          class="ks-command-button"
          :disabled="uploadForm.processing || !uploadForm.file"
        >
          {{ t('rosterImport.preview') }}
        </button>
      </form>
      <p v-if="uploadForm.errors.file" class="mt-3 text-sm text-red-300" role="alert">
        {{ uploadForm.errors.file }}
      </p>
    </section>

    <template v-if="importRecord">
      <section class="ks-surface mt-5 p-5" aria-labelledby="roster-import-summary-heading">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <p class="ks-kicker">{{ t('rosterImport.previewEyebrow') }}</p>
            <h2 id="roster-import-summary-heading" class="ks-display mt-1 text-xl font-semibold">
              {{ importRecord.filename }}
            </h2>
          </div>
          <span class="ks-status" data-tone="info">{{ importRecord.status }}</span>
        </div>

        <dl class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          <div class="rounded border border-[var(--ks-border)] bg-black/15 p-3">
            <dt class="ks-kicker">{{ t('rosterImport.rows') }}</dt>
            <dd class="mt-1 font-semibold">{{ formatNumber(importRecord.rowCount) }}</dd>
          </div>
          <div class="rounded border border-[var(--ks-border)] bg-black/15 p-3">
            <dt class="ks-kicker">{{ t('rosterImport.creates') }}</dt>
            <dd class="mt-1 font-semibold">{{ formatNumber(importRecord.createCount) }}</dd>
          </div>
          <div class="rounded border border-[var(--ks-border)] bg-black/15 p-3">
            <dt class="ks-kicker">{{ t('rosterImport.updates') }}</dt>
            <dd class="mt-1 font-semibold">{{ formatNumber(importRecord.updateCount) }}</dd>
          </div>
          <div class="rounded border border-[var(--ks-border)] bg-black/15 p-3">
            <dt class="ks-kicker">{{ t('rosterImport.ambiguous') }}</dt>
            <dd class="mt-1 font-semibold">{{ formatNumber(importRecord.ambiguousCount) }}</dd>
          </div>
          <div class="rounded border border-[var(--ks-border)] bg-black/15 p-3">
            <dt class="ks-kicker">{{ t('rosterImport.rejected') }}</dt>
            <dd class="mt-1 font-semibold">{{ formatNumber(importRecord.rejectedCount) }}</dd>
          </div>
        </dl>
      </section>

      <section class="ks-surface mt-5 overflow-hidden" aria-labelledby="roster-import-rows-heading">
        <div class="border-b border-[var(--ks-border)] p-5">
          <h2 id="roster-import-rows-heading" class="ks-display text-xl font-semibold">
            {{ t('rosterImport.rowsTitle') }}
          </h2>
          <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
            {{ t('rosterImport.rowsHelp') }}
          </p>
        </div>

        <div class="divide-y divide-[var(--ks-border)]">
          <article v-for="row in importRecord.rows" :key="row.row" class="p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="font-semibold">{{ rowLabel(row) }}</p>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  #{{ row.row }} · {{ row.data.game_player_id ?? '—' }}
                </p>
              </div>
              <span
                class="ks-status"
                :data-tone="
                  row.outcome === 'rejected'
                    ? 'danger'
                    : row.outcome === 'ambiguous'
                      ? 'warning'
                      : 'success'
                "
              >
                {{ row.outcome }}
              </span>
            </div>

            <ul v-if="row.errors.length" class="mt-3 space-y-1 text-sm text-red-300">
              <li v-for="error in row.errors" :key="error">{{ error }}</li>
            </ul>

            <div v-if="row.outcome === 'ambiguous'" class="mt-4">
              <label class="text-sm font-semibold">
                {{ t('rosterImport.resolve') }}
                <select v-model="resolutionDrafts[String(row.row)]" class="ks-input mt-2">
                  <option value="">{{ t('rosterImport.chooseCandidate') }}</option>
                  <option
                    v-for="candidate in row.candidates"
                    :key="candidate.entry_id"
                    :value="candidate.entry_id"
                  >
                    {{ candidate.name }} · {{ candidate.game_player_id ?? '—' }}
                  </option>
                </select>
              </label>
            </div>
          </article>
        </div>
      </section>

      <section class="ks-surface mt-5 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <p class="text-sm text-[var(--ks-text-secondary)]">
            {{ t('rosterImport.unresolvedCount', { count: unresolvedAmbiguous }) }}
          </p>
          <button
            type="button"
            class="ks-command-button"
            :disabled="
              commitForm.processing ||
              unresolvedAmbiguous > 0 ||
              importRecord.status === 'committed'
            "
            @click="commitImport"
          >
            {{ t('rosterImport.commit') }}
          </button>
        </div>
      </section>
    </template>
  </AppLayout>
</template>
