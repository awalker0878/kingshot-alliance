<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type ExtractedField = {
  id: string;
  rawText: string;
  value: string;
  confidence: number;
  boundingBox: unknown;
  warnings: string[];
} | null;

type ExtractionRow = {
  ordinal: number;
  rank: ExtractedField;
  playerName: ExtractedField;
  damage: ExtractedField;
};

type Extraction = {
  id: string;
  status: string;
  overallConfidence: number;
  fieldCount: number;
  failureCode: string | null;
  startedAt: string | null;
  completedAt: string | null;
  reportTimestamp: ExtractedField;
  rows: ExtractionRow[];
};

type ReviewRow = {
  ordinal: number;
  included: boolean;
  playerId: string | null;
  playerName: string;
  reportedRank: number | null;
  damagePoints: number | null;
  correctionReason: string | null;
  corrected: boolean;
};

type Review = {
  id: string;
  extractionAttemptId: string;
  revision: number;
  status: string;
  reportTimestampText: string | null;
  semanticFingerprintPrefix: string;
  semanticDuplicateReviewId: string | null;
  duplicateResolution: string | null;
  reviewedAt: string | null;
  rows: ReviewRow[];
};

type CommitAttempt = {
  id: string;
  status: string;
  destinationReportId: string | null;
  receipt: Record<string, unknown> | null;
  failureCode: string | null;
  startedAt: string | null;
  completedAt: string | null;
};

type Preview = {
  reviewId: string;
  rows: Array<{
    playerId: string;
    playerName: string;
    beforeScore: number;
    reportDamage: number;
    afterScore: number;
  }>;
};

type EvidenceItem = {
  id: string;
  originalName: string;
  mimeType: string;
  sizeBytes: number;
  width: number;
  height: number;
  sha256Prefix: string;
  status: string;
  kind: string;
  receivedAt: string | null;
  imageAvailable: boolean;
  visualDuplicate: { evidenceId: string; distance: number } | null;
  classifications: Array<{
    id: string;
    status: string;
    kind: string;
    confidence: number;
    reason: string | null;
    failureCode: string | null;
    ocrEngine: string | null;
    ocrVersion: string | null;
    startedAt: string | null;
    completedAt: string | null;
  }>;
  extractions: Extraction[];
  latestExtraction: Extraction | null;
  reviews: Review[];
  latestReview: Review | null;
  commits: CommitAttempt[];
  preview: Preview | null;
  canCommit: boolean;
  canRetry: boolean;
  canDelete: boolean;
  latestCommitStatus: string | null;
};

type DraftRow = {
  row_ordinal: number;
  included: boolean;
  player_id: string;
  player_name: string;
  reported_rank: number | null;
  damage_points: number | null;
  correction_reason: string;
};

type ReviewDraft = {
  report_timestamp_text: string;
  rows: DraftRow[];
};

const props = defineProps<{
  user: { name: string; email: string };
  userTimezone: string;
  workspace: {
    occurrenceId: string;
    allianceId: string;
    acceptedReportCount: number;
    players: Array<{ id: string; name: string }>;
    evidence: EvidenceItem[];
  };
}>();

const { t, formatDate, formatNumber } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
const uploadForm = useForm<{ evidence: File | null }>({ evidence: null });
const drafts = reactive<Record<string, ReviewDraft>>({});
const duplicateJustifications = reactive<Record<string, string>>({});
const activeEvidence = computed(() => props.workspace.evidence[0] ?? null);

function statusLabel(value: string): string {
  return value.replaceAll('_', ' ');
}

function statusTone(value: string): 'success' | 'warning' | 'danger' | 'info' {
  if (['approved', 'committed', 'completed', 'succeeded'].includes(value)) return 'success';
  if (['failed', 'rejected', 'unsupported', 'deleted', 'duplicate_blocked'].includes(value)) {
    return 'danger';
  }
  if (
    ['uploaded', 'classifying', 'classified', 'extracting', 'needs_review', 'committing'].includes(
      value,
    )
  ) {
    return 'warning';
  }
  return 'info';
}

function confidenceLabel(value: number): string {
  return `${Math.round(value * 100)}%`;
}

function fieldValue(field: ExtractedField): string {
  return field?.value ?? '';
}

function numericField(field: ExtractedField): number | null {
  if (!field || field.value.trim() === '') return null;
  const value = Number(field.value.replaceAll(',', ''));
  return Number.isFinite(value) ? value : null;
}

function reviewDraft(item: EvidenceItem): ReviewDraft {
  const existing = drafts[item.id];
  if (existing) return existing;

  const review = item.latestReview;
  if (review) {
    drafts[item.id] = {
      report_timestamp_text: review.reportTimestampText ?? '',
      rows: review.rows.map((row) => ({
        row_ordinal: row.ordinal,
        included: row.included,
        player_id: row.playerId ?? '',
        player_name: row.playerName,
        reported_rank: row.reportedRank,
        damage_points: row.damagePoints,
        correction_reason: row.correctionReason ?? '',
      })),
    };
    return drafts[item.id]!;
  }

  const extraction = item.latestExtraction;
  drafts[item.id] = {
    report_timestamp_text: fieldValue(extraction?.reportTimestamp ?? null),
    rows:
      extraction?.rows.map((row) => ({
        row_ordinal: row.ordinal,
        included: true,
        player_id: '',
        player_name: fieldValue(row.playerName),
        reported_rank: numericField(row.rank),
        damage_points: numericField(row.damage),
        correction_reason: '',
      })) ?? [],
  };
  return drafts[item.id]!;
}

function extractedRow(item: EvidenceItem, ordinal: number): ExtractionRow | undefined {
  return item.latestExtraction?.rows.find((row) => row.ordinal === ordinal);
}

function upload(): void {
  if (!uploadForm.evidence) return;
  uploadForm.post(`/events/${props.workspace.occurrenceId}/screenshot-intake`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => uploadForm.reset(),
  });
}

function saveReview(item: EvidenceItem): void {
  if (!item.latestExtraction) return;
  const draft = reviewDraft(item);
  router.put(
    `/events/${props.workspace.occurrenceId}/screenshot-intake/${item.id}/review`,
    {
      extraction_attempt_id: item.latestExtraction.id,
      report_timestamp_text: draft.report_timestamp_text || null,
      rows: draft.rows.map((row) => ({
        ...row,
        player_id: row.player_id || null,
        correction_reason: row.correction_reason || null,
      })),
    },
    { preserveScroll: true },
  );
}

function retry(item: EvidenceItem): void {
  router.post(
    `/events/${props.workspace.occurrenceId}/screenshot-intake/${item.id}/retry`,
    {},
    { preserveScroll: true },
  );
}

function resolveDuplicate(review: Review): void {
  router.post(
    `/events/${props.workspace.occurrenceId}/screenshot-intake/reviews/${review.id}/resolve-duplicate`,
    { justification: duplicateJustifications[review.id] ?? '' },
    { preserveScroll: true },
  );
}

function commit(item: EvidenceItem): void {
  const review = item.latestReview;
  if (!review || !item.canCommit) return;
  requestConfirmation({
    id: `evidence-commit-${review.id}`,
    title: t('evidence.commitTitle'),
    description: t('evidence.commitDescription'),
    confirmLabel: t('evidence.commit'),
    cancelLabel: t('common.cancel'),
    busyLabel: t('evidence.committing'),
    perform: (finish) =>
      router.post(
        `/events/${props.workspace.occurrenceId}/screenshot-intake/reviews/${review.id}/commit`,
        {},
        { preserveScroll: true, onFinish: finish },
      ),
  });
}

function destroy(item: EvidenceItem): void {
  requestConfirmation({
    id: `evidence-delete-${item.id}`,
    title: t('evidence.deleteTitle'),
    description: t('evidence.deleteDescription'),
    confirmLabel: t('evidence.delete'),
    cancelLabel: t('common.cancel'),
    busyLabel: t('evidence.deleting'),
    perform: (finish) =>
      router.delete(`/events/${props.workspace.occurrenceId}/screenshot-intake/${item.id}`, {
        preserveScroll: true,
        onFinish: finish,
      }),
  });
}

function refresh(): void {
  router.reload({ only: ['workspace'] });
}
</script>

<template>
  <Head :title="t('evidence.title')" />

  <AppLayout :user="user">
    <header class="ks-surface-gold p-5 sm:p-7">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="max-w-3xl">
          <p class="ks-kicker">{{ t('evidence.eyebrow') }}</p>
          <h1 class="ks-display mt-1 text-3xl font-semibold sm:text-4xl">
            {{ t('evidence.title') }}
          </h1>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('evidence.subtitle') }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link :href="`/events/${workspace.occurrenceId}`" class="ks-command-link">
            ← {{ t('evidence.back') }}
          </Link>
          <AppButton type="button" variant="secondary" @click="refresh">
            {{ t('evidence.refresh') }}
          </AppButton>
        </div>
      </div>
      <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:max-w-xl">
        <div class="rounded border border-[var(--ks-border)] bg-black/15 p-3">
          <dt class="text-xs text-[var(--ks-muted)]">{{ t('evidence.acceptedReports') }}</dt>
          <dd class="mt-1 text-xl font-semibold">
            {{ formatNumber(workspace.acceptedReportCount) }}
          </dd>
        </div>
        <div class="rounded border border-[var(--ks-border)] bg-black/15 p-3">
          <dt class="text-xs text-[var(--ks-muted)]">{{ t('evidence.existingTitle') }}</dt>
          <dd class="mt-1 text-xl font-semibold">{{ formatNumber(workspace.evidence.length) }}</dd>
        </div>
      </dl>
    </header>

    <section class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="evidence-upload-heading">
      <p class="ks-kicker">{{ t('evidence.uploadTitle') }}</p>
      <h2 id="evidence-upload-heading" class="ks-display mt-1 text-2xl font-semibold">
        {{ t('evidence.openIntake') }}
      </h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-muted)]">
        {{ t('evidence.uploadHelp') }}
      </p>
      <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="upload">
        <label class="block min-w-0 flex-1 text-sm">
          <span>{{ t('evidence.chooseFile') }}</span>
          <input
            class="ks-input mt-1.5"
            type="file"
            name="evidence"
            accept="image/jpeg,image/png,image/webp"
            required
            @change="uploadForm.evidence = ($event.target as HTMLInputElement).files?.[0] ?? null"
          />
        </label>
        <AppButton type="submit" :disabled="uploadForm.processing || !uploadForm.evidence">
          {{ uploadForm.processing ? t('evidence.uploading') : t('evidence.upload') }}
        </AppButton>
      </form>
    </section>

    <section class="mt-5" aria-labelledby="evidence-list-heading">
      <div class="flex items-end justify-between gap-3">
        <div>
          <p class="ks-kicker">{{ t('evidence.history') }}</p>
          <h2 id="evidence-list-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('evidence.existingTitle') }}
          </h2>
        </div>
      </div>

      <div
        v-if="!workspace.evidence.length"
        class="ks-surface mt-3 p-6 text-sm text-[var(--ks-muted)]"
      >
        {{ t('evidence.empty') }}
      </div>

      <div v-else class="mt-3 space-y-5">
        <article
          v-for="item in workspace.evidence"
          :key="item.id"
          class="ks-surface overflow-hidden"
        >
          <header class="border-b border-[var(--ks-border)] p-4 sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="truncate text-lg font-semibold">{{ item.originalName }}</h3>
                  <span class="ks-status" :data-tone="statusTone(item.status)">
                    {{ statusLabel(item.status) }}
                  </span>
                </div>
                <p class="mt-1 text-xs text-[var(--ks-muted)]">
                  {{ item.receivedAt ? formatDate(item.receivedAt) : '—' }} · {{ item.width }}×{{
                    item.height
                  }}
                  · {{ formatNumber(item.sizeBytes) }} B · {{ item.sha256Prefix }}…
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <AppButton
                  v-if="item.canRetry"
                  type="button"
                  variant="secondary"
                  @click="retry(item)"
                >
                  {{ t('evidence.retry') }}
                </AppButton>
                <AppButton
                  v-if="item.canDelete"
                  type="button"
                  variant="danger"
                  @click="destroy(item)"
                >
                  {{ t('evidence.delete') }}
                </AppButton>
              </div>
            </div>
            <div
              v-if="item.visualDuplicate"
              class="mt-3 rounded border border-[var(--ks-warning)] p-3 text-sm"
              role="status"
            >
              <strong>{{ t('evidence.visualDuplicate') }}</strong>
              <p class="mt-1 text-[var(--ks-muted)]">{{ t('evidence.visualDuplicateHelp') }}</p>
              <p class="mt-1 text-xs">
                {{ t('evidence.distance') }}: {{ item.visualDuplicate.distance }} ·
                {{ item.visualDuplicate.evidenceId }}
              </p>
            </div>
          </header>

          <div class="grid min-w-0 gap-0 xl:grid-cols-[minmax(18rem,.8fr)_minmax(0,1.2fr)]">
            <div class="border-b border-[var(--ks-border)] p-4 sm:p-5 xl:border-e xl:border-b-0">
              <h4 class="font-semibold">{{ t('evidence.imagePreview') }}</h4>
              <img
                v-if="item.imageAvailable"
                :src="`/events/${workspace.occurrenceId}/screenshot-intake/${item.id}/image`"
                :alt="`${t('evidence.imagePreview')}: ${item.originalName}`"
                class="mt-3 max-h-[42rem] w-full rounded border border-[var(--ks-border)] bg-black/20 object-contain"
              />
              <p
                v-else
                class="mt-3 rounded border border-[var(--ks-border)] p-4 text-sm text-[var(--ks-muted)]"
              >
                {{ t('evidence.imageUnavailable') }}
              </p>

              <details class="mt-4 rounded border border-[var(--ks-border)] p-3">
                <summary class="cursor-pointer font-semibold">{{ t('evidence.history') }}</summary>
                <div class="mt-3 space-y-4 text-sm">
                  <div>
                    <h5 class="font-semibold">{{ t('evidence.classificationHistory') }}</h5>
                    <ol class="mt-2 space-y-2">
                      <li
                        v-for="attempt in item.classifications"
                        :key="attempt.id"
                        class="rounded bg-black/10 p-2"
                      >
                        <span class="ks-status" :data-tone="statusTone(attempt.status)">{{
                          attempt.status
                        }}</span>
                        <span class="ms-2"
                          >{{ attempt.kind }} · {{ confidenceLabel(attempt.confidence) }}</span
                        >
                        <p v-if="attempt.failureCode" class="mt-1 text-xs text-[var(--ks-muted)]">
                          {{ t('evidence.failureCode') }}: {{ attempt.failureCode }}
                        </p>
                      </li>
                    </ol>
                  </div>
                  <div>
                    <h5 class="font-semibold">{{ t('evidence.extractionHistory') }}</h5>
                    <ol class="mt-2 space-y-2">
                      <li
                        v-for="attempt in item.extractions"
                        :key="attempt.id"
                        class="rounded bg-black/10 p-2"
                      >
                        <span class="ks-status" :data-tone="statusTone(attempt.status)">{{
                          attempt.status
                        }}</span>
                        <span class="ms-2">{{ confidenceLabel(attempt.overallConfidence) }}</span>
                        <p v-if="attempt.failureCode" class="mt-1 text-xs text-[var(--ks-muted)]">
                          {{ t('evidence.failureCode') }}: {{ attempt.failureCode }}
                        </p>
                      </li>
                    </ol>
                  </div>
                </div>
              </details>
            </div>

            <div class="min-w-0 p-4 sm:p-5">
              <div v-if="item.latestExtraction && item.latestExtraction.status === 'completed'">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p class="ks-kicker">{{ t('evidence.reviewTitle') }}</p>
                    <h4 class="mt-1 text-xl font-semibold">{{ t('evidence.extractedFields') }}</h4>
                  </div>
                  <span
                    class="ks-status"
                    :data-tone="
                      item.latestExtraction.overallConfidence < 0.8 ? 'warning' : 'success'
                    "
                  >
                    {{ t('evidence.confidence') }}
                    {{ confidenceLabel(item.latestExtraction.overallConfidence) }}
                  </span>
                </div>
                <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
                  {{ t('evidence.reviewHelp') }}
                </p>

                <label class="mt-4 block text-sm">
                  <span>{{ t('evidence.reportTimestamp') }}</span>
                  <input
                    v-model="reviewDraft(item).report_timestamp_text"
                    class="ks-input mt-1.5"
                    maxlength="64"
                    :disabled="item.status === 'committed' || item.status === 'deleted'"
                  />
                </label>

                <div
                  v-if="!reviewDraft(item).rows.length"
                  class="mt-4 rounded border border-[var(--ks-border)] p-4 text-sm"
                >
                  {{ t('evidence.noRows') }}
                </div>

                <div v-else class="mt-4 space-y-4">
                  <fieldset
                    v-for="row in reviewDraft(item).rows"
                    :key="row.row_ordinal"
                    class="rounded border border-[var(--ks-border)] p-3 sm:p-4"
                  >
                    <legend class="px-1 text-sm font-semibold">
                      {{ t('evidence.rank') }} {{ row.reported_rank ?? row.row_ordinal }}
                    </legend>
                    <label class="flex items-center gap-2 text-sm">
                      <input v-model="row.included" type="checkbox" />
                      <span>{{ t('evidence.include') }}</span>
                    </label>
                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                      <label class="block text-sm">
                        <span>{{ t('evidence.governor') }}</span>
                        <input v-model="row.player_name" class="ks-input mt-1.5" maxlength="128" />
                        <small class="mt-1 block text-[var(--ks-muted)]">
                          {{ t('evidence.rawText') }}:
                          {{ extractedRow(item, row.row_ordinal)?.playerName?.rawText ?? '—' }} ·
                          {{ t('evidence.confidence') }}
                          {{
                            confidenceLabel(
                              extractedRow(item, row.row_ordinal)?.playerName?.confidence ?? 0,
                            )
                          }}
                        </small>
                      </label>
                      <label class="block text-sm">
                        <span>{{ t('evidence.playerResolution') }}</span>
                        <select
                          v-model="row.player_id"
                          class="ks-input mt-1.5"
                          :required="row.included"
                        >
                          <option value="">{{ t('evidence.unresolved') }}</option>
                          <option
                            v-for="player in workspace.players"
                            :key="player.id"
                            :value="player.id"
                          >
                            {{ player.name }}
                          </option>
                        </select>
                      </label>
                      <label class="block text-sm">
                        <span>{{ t('evidence.rank') }}</span>
                        <input
                          v-model.number="row.reported_rank"
                          class="ks-input mt-1.5"
                          type="number"
                          min="1"
                          max="999"
                        />
                        <small class="mt-1 block text-[var(--ks-muted)]">
                          {{ t('evidence.confidence') }}
                          {{
                            confidenceLabel(
                              extractedRow(item, row.row_ordinal)?.rank?.confidence ?? 0,
                            )
                          }}
                        </small>
                      </label>
                      <label class="block text-sm">
                        <span>{{ t('evidence.damage') }}</span>
                        <input
                          v-model.number="row.damage_points"
                          class="ks-input mt-1.5"
                          type="number"
                          min="0"
                          :required="row.included"
                        />
                        <small class="mt-1 block text-[var(--ks-muted)]">
                          {{ t('evidence.rawText') }}:
                          {{ extractedRow(item, row.row_ordinal)?.damage?.rawText ?? '—' }} ·
                          {{ t('evidence.confidence') }}
                          {{
                            confidenceLabel(
                              extractedRow(item, row.row_ordinal)?.damage?.confidence ?? 0,
                            )
                          }}
                        </small>
                      </label>
                    </div>
                    <label class="mt-3 block text-sm">
                      <span>{{ t('evidence.correctionReason') }}</span>
                      <input
                        v-model="row.correction_reason"
                        class="ks-input mt-1.5"
                        maxlength="500"
                      />
                    </label>
                  </fieldset>
                </div>

                <div
                  v-if="
                    !['committed', 'deleted'].includes(item.status) && reviewDraft(item).rows.length
                  "
                  class="mt-4"
                >
                  <AppButton type="button" @click="saveReview(item)">{{
                    t('evidence.saveReview')
                  }}</AppButton>
                </div>
              </div>

              <div v-else class="rounded border border-[var(--ks-border)] p-4 text-sm">
                <strong>{{ t('evidence.processing') }}</strong>
                <p class="mt-1 text-[var(--ks-muted)]">{{ t('evidence.processingHelp') }}</p>
              </div>

              <div
                v-if="item.latestReview?.status === 'duplicate_blocked'"
                class="mt-5 rounded border border-[var(--ks-danger)] p-4"
                role="alert"
              >
                <h4 class="font-semibold">{{ t('evidence.semanticDuplicate') }}</h4>
                <p class="mt-2 text-sm text-[var(--ks-muted)]">
                  {{ t('evidence.semanticDuplicateHelp') }}
                </p>
                <label class="mt-3 block text-sm">
                  <span>{{ t('evidence.distinctJustification') }}</span>
                  <textarea
                    v-model="duplicateJustifications[item.latestReview.id]"
                    class="ks-input mt-1.5 min-h-24"
                    minlength="10"
                    maxlength="1000"
                  />
                </label>
                <AppButton
                  class="mt-3"
                  type="button"
                  variant="secondary"
                  @click="resolveDuplicate(item.latestReview)"
                >
                  {{ t('evidence.resolveDuplicate') }}
                </AppButton>
              </div>

              <section
                v-if="item.preview && item.latestReview?.status === 'approved'"
                class="mt-5"
                aria-label="Commit preview"
              >
                <p class="ks-kicker">{{ t('evidence.previewTitle') }}</p>
                <h4 class="mt-1 text-xl font-semibold">{{ t('evidence.previewTitle') }}</h4>
                <p class="mt-2 text-sm text-[var(--ks-muted)]">{{ t('evidence.previewHelp') }}</p>
                <div class="mt-3 overflow-x-auto">
                  <table class="w-full min-w-[34rem] text-sm">
                    <thead>
                      <tr class="text-start text-xs text-[var(--ks-muted)]">
                        <th class="p-2 text-start">{{ t('evidence.governor') }}</th>
                        <th class="p-2 text-end">{{ t('evidence.before') }}</th>
                        <th class="p-2 text-end">{{ t('evidence.report') }}</th>
                        <th class="p-2 text-end">{{ t('evidence.after') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="row in item.preview.rows"
                        :key="row.playerId"
                        class="border-t border-[var(--ks-border)]"
                      >
                        <td class="p-2 font-medium">{{ row.playerName }}</td>
                        <td class="p-2 text-end tabular-nums">
                          {{ formatNumber(row.beforeScore) }}
                        </td>
                        <td class="p-2 text-end tabular-nums">
                          +{{ formatNumber(row.reportDamage) }}
                        </td>
                        <td class="p-2 text-end font-semibold tabular-nums">
                          {{ formatNumber(row.afterScore) }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <AppButton v-if="item.canCommit" class="mt-4" type="button" @click="commit(item)">
                  {{ t('evidence.commit') }}
                </AppButton>
                <span
                  v-else-if="item.latestCommitStatus === 'succeeded'"
                  class="ks-status mt-4"
                  data-tone="success"
                >
                  {{ t('evidence.committed') }}
                </span>
              </section>

              <details
                v-if="item.reviews.length || item.commits.length"
                class="mt-5 rounded border border-[var(--ks-border)] p-3"
              >
                <summary class="cursor-pointer font-semibold">
                  {{ t('evidence.reviewHistory') }}
                </summary>
                <div class="mt-3 space-y-4 text-sm">
                  <ol class="space-y-2">
                    <li
                      v-for="review in item.reviews"
                      :key="review.id"
                      class="rounded bg-black/10 p-2"
                    >
                      {{ t('evidence.revision') }} {{ review.revision }} ·
                      <span class="ks-status" :data-tone="statusTone(review.status)">{{
                        review.status
                      }}</span>
                      · {{ review.semanticFingerprintPrefix }}…
                    </li>
                  </ol>
                  <div v-if="item.commits.length">
                    <h5 class="font-semibold">{{ t('evidence.commitHistory') }}</h5>
                    <ol class="mt-2 space-y-2">
                      <li
                        v-for="attempt in item.commits"
                        :key="attempt.id"
                        class="rounded bg-black/10 p-2"
                      >
                        <span class="ks-status" :data-tone="statusTone(attempt.status)">{{
                          attempt.status
                        }}</span>
                        <span v-if="attempt.destinationReportId" class="ms-2">
                          {{ t('evidence.destinationReport') }}: {{ attempt.destinationReportId }}
                        </span>
                        <p v-if="attempt.failureCode" class="mt-1 text-xs text-[var(--ks-muted)]">
                          {{ t('evidence.failureCode') }}: {{ attempt.failureCode }}
                        </p>
                      </li>
                    </ol>
                  </div>
                </div>
              </details>
            </div>
          </div>
        </article>
      </div>
    </section>

    <ConfirmActionDialog v-bind="dialog" @confirm="confirmAction" @cancel="cancelConfirmation" />
  </AppLayout>
</template>
