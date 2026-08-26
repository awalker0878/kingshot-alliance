<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import { useLocale } from '@/localization';

type EvidenceKind =
  | 'transfer_governor_status'
  | 'transfer_score_passes'
  | 'transfer_invitation'
  | 'transfer_target_kingdom_rules'
  | 'transfer_official_group';

type Schema = {
  kind: EvidenceKind;
  version: string;
  supportedFields: string[];
  requiredFields: string[];
  minimumClassificationConfidence: number;
  minimumFieldConfidence: number;
  fixtureCorpus: string;
  destinationAction: string;
};

type ExtractedField = {
  key: string;
  ordinal: number;
  raw: string;
  value: string | number | null;
  confidence: number;
  warnings: string[];
};

type EvidenceItem = {
  id: string;
  expectedKind: EvidenceKind;
  kind: EvidenceKind | 'unknown';
  status: string;
  createdAt: string | null;
  hasImage: boolean;
  hasVisualDuplicate: boolean;
  classification: {
    kind: EvidenceKind | 'unknown';
    confidence: number;
    reason: string | null;
    status: string;
  } | null;
  extraction: {
    id: string;
    schemaVersion: string;
    confidence: number;
    status: string;
    fields: ExtractedField[];
  } | null;
  review: {
    id: string;
    revision: number;
    status: 'approved' | 'duplicate_blocked';
    observedAt: string;
    validUntil: string | null;
    governorPower: number | null;
    transferScore: number | null;
    passesAvailable: number | null;
    passesRequired: number | null;
    invitationStatus: string | null;
    targetPowerCap: number | null;
    kingdomClassification: string | null;
    officialGroupIdentifier: string | null;
    officialGroupKingdomNumbers: number[];
    semanticDuplicateReviewId: string | null;
    duplicateResolution: string | null;
  } | null;
  commit: {
    id: string;
    status: 'pending' | 'succeeded' | 'failed';
    destinationAction: string;
    destinationReceiptId: string | null;
    failureCode: string | null;
  } | null;
};

type Preview = {
  current_outcome: string;
  current_primary_action: string | null;
  after_outcome: string;
  after_primary_action: string | null;
  reviewed_fact_keys: string[];
  transfer_score_before: string | number | boolean | null;
  transfer_score_after: string | number | boolean | null;
};

type CurrentRequirement = {
  key: string;
  state: string;
  explanation: string;
  actual: string | number | boolean | null;
  required: string | number | boolean | null;
  sourceType: string | null;
  sourceReference: string | null;
  observedAt: string | null;
  validUntil: string | null;
};

type CurrentObservation = {
  id: string;
  kind: string;
  value: string | number | boolean | null;
  sourceType: string;
  sourceReference: string;
  observedAt: string;
  validUntil: string | null;
  details: string | null;
};

type ReviewDraft = {
  observedAt: string;
  validUntil: string;
  governorPower: string;
  transferScore: string;
  passesAvailable: string;
  passesRequired: string;
  invitationStatus: string;
  targetKingdomNumber: string;
  targetPowerCap: string;
  kingdomClassification: string;
  officialGroupIdentifier: string;
  officialGroupKingdoms: string;
};

type ReviewPayloadValue = string | number | null | number[];

const props = defineProps<{
  planId: string;
  participantId: string;
  participantName: string;
  mutable: boolean;
  targetKingdom: string | null;
  currentEligibility: {
    outcome: string;
    primaryAction: string | null;
    evaluatedAt: string;
    requirements: CurrentRequirement[];
  } | null;
  currentObservations: CurrentObservation[];
  currentOfficialGroup: {
    label: string;
    sourceType: string;
    sourceReference: string;
    observedAt: string;
  } | null;
  currentTargetCondition: {
    powerCap: number | null;
    classification: string;
    sourceType: string;
    sourceReference: string;
    observedAt: string;
  } | null;
  currentTransferScore: {
    state: string;
    value: string | number | boolean | null;
    sourceType: string | null;
    sourceReference: string | null;
    observedAt: string | null;
    validUntil: string | null;
    details: string | null;
  };
}>();

const { t, formatDate, formatNumber } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
const loaded = ref(false);
const loading = ref(false);
const loadError = ref(false);
const evidence = ref<EvidenceItem[]>([]);
const schemas = ref<Schema[]>([]);
const uploadKind = ref<EvidenceKind>('transfer_governor_status');
const uploadFile = ref<File | null>(null);
const notice = ref('');
const previews = reactive<Record<string, Preview | null>>({});
const previewLoading = reactive<Record<string, boolean>>({});
const duplicateJustifications = reactive<Record<string, string>>({});
const drafts = reactive<Record<string, ReviewDraft>>({});

const basePath = computed(
  () => `/alliance/transfers/${props.planId}/participants/${props.participantId}/evidence`,
);

function kindLabel(kind: string): string {
  return t(`kingdomP7D.evidenceKind_${kind}`);
}

function statusLabel(status: string): string {
  return t(`kingdomP7D.evidenceStatus_${status}`);
}

function reviewStatusLabel(status: string): string {
  return t(`kingdomP7D.evidenceReviewStatus_${status}`);
}

function commitStatusLabel(status: string): string {
  return t(`kingdomP7D.evidenceCommitStatus_${status}`);
}

function schemaFor(kind: string): Schema | undefined {
  return schemas.value.find((schema) => schema.kind === kind);
}

function percent(value: number): string {
  return `${Math.round(value * 100)}%`;
}

function timestamp(value: string | null): string {
  return value ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

function displayValue(value: unknown): string {
  if (typeof value === 'number') return formatNumber(value);
  if (typeof value === 'boolean') return value ? t('common.yes') : t('common.no');
  return value === null || value === undefined || value === '' ? '—' : String(value);
}

function evidenceField(item: EvidenceItem, key: string): ExtractedField | undefined {
  return item.extraction?.fields.find((field) => field.key === key);
}

function evidenceFields(item: EvidenceItem, key: string): ExtractedField[] {
  return item.extraction?.fields.filter((field) => field.key === key) ?? [];
}

function normalizedText(item: EvidenceItem, key: string): string {
  const value = evidenceField(item, key)?.value;
  return value === null || value === undefined ? '' : String(value);
}

function ensureDraft(item: EvidenceItem): void {
  if (drafts[item.id]) return;

  drafts[item.id] = {
    observedAt: item.review?.observedAt ? localDateTime(item.review.observedAt) : '',
    validUntil: item.review?.validUntil ? localDateTime(item.review.validUntil) : '',
    governorPower: item.review?.governorPower?.toString() ?? normalizedText(item, 'governor_power'),
    transferScore: item.review?.transferScore?.toString() ?? normalizedText(item, 'transfer_score'),
    passesAvailable:
      item.review?.passesAvailable?.toString() ?? normalizedText(item, 'transfer_passes_available'),
    passesRequired:
      item.review?.passesRequired?.toString() ?? normalizedText(item, 'transfer_passes_required'),
    invitationStatus: item.review?.invitationStatus ?? normalizedText(item, 'invitation_status'),
    targetKingdomNumber: normalizedText(item, 'target_kingdom_number') || props.targetKingdom || '',
    targetPowerCap: item.review?.targetPowerCap?.toString() ?? normalizedText(item, 'power_cap'),
    kingdomClassification:
      item.review?.kingdomClassification ?? normalizedText(item, 'kingdom_classification'),
    officialGroupIdentifier:
      item.review?.officialGroupIdentifier ?? normalizedText(item, 'official_group_identifier'),
    officialGroupKingdoms:
      item.review?.officialGroupKingdomNumbers.join(', ') ??
      evidenceFields(item, 'kingdom_number')
        .map((field) => String(field.value ?? ''))
        .filter(Boolean)
        .join(', '),
  };
}

function localDateTime(value: string): string {
  const date = new Date(value);
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
  return date.toISOString().slice(0, 16);
}

async function loadEvidence(force = false): Promise<void> {
  if (loading.value || (loaded.value && !force)) return;
  loading.value = true;
  loadError.value = false;
  try {
    const response = await fetch(basePath.value, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    if (!response.ok) throw new Error(`Evidence request failed: ${response.status}`);
    const payload = (await response.json()) as { evidence: EvidenceItem[]; schemas: Schema[] };
    evidence.value = payload.evidence;
    schemas.value = payload.schemas;
    for (const item of evidence.value) ensureDraft(item);
    loaded.value = true;
  } catch {
    loadError.value = true;
  } finally {
    loading.value = false;
  }
}

function chooseFile(event: Event): void {
  const input = event.target as HTMLInputElement;
  uploadFile.value = input.files?.[0] ?? null;
}

function upload(): void {
  if (!props.mutable || !uploadFile.value) return;
  notice.value = '';
  router.post(
    basePath.value,
    { evidence_kind: uploadKind.value, evidence: uploadFile.value },
    {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        uploadFile.value = null;
        void loadEvidence(true);
      },
    },
  );
}

function requiresValidity(kind: EvidenceKind): boolean {
  return ['transfer_governor_status', 'transfer_score_passes', 'transfer_invitation'].includes(
    kind,
  );
}

function numeric(value: string): number | null {
  if (value.trim() === '') return null;
  const parsed = Number(value);
  return Number.isSafeInteger(parsed) && parsed >= 0 ? parsed : null;
}

function parseKingdoms(value: string): number[] {
  return [
    ...new Set(
      value
        .split(/[\s,;]+/)
        .map(Number)
        .filter((number) => Number.isInteger(number) && number > 0),
    ),
  ].sort((a, b) => a - b);
}

function review(item: EvidenceItem): void {
  const draft = drafts[item.id];
  if (!draft || !item.extraction || !draft.observedAt) return;
  if (requiresValidity(item.expectedKind) && !draft.validUntil) {
    notice.value = t('kingdomP7D.freshnessRequiredHelp');
    return;
  }

  const payload: Record<string, ReviewPayloadValue> = {
    extraction_attempt_id: item.extraction.id,
    observed_at: new Date(draft.observedAt).toISOString(),
    valid_until: draft.validUntil ? new Date(draft.validUntil).toISOString() : null,
  };

  if (item.expectedKind === 'transfer_governor_status') {
    payload.governor_power = numeric(draft.governorPower);
  } else if (item.expectedKind === 'transfer_score_passes') {
    payload.transfer_score = numeric(draft.transferScore);
    payload.transfer_passes_available = numeric(draft.passesAvailable);
    payload.transfer_passes_required = numeric(draft.passesRequired);
  } else if (item.expectedKind === 'transfer_invitation') {
    payload.invitation_status = draft.invitationStatus || null;
    payload.target_kingdom_number = numeric(draft.targetKingdomNumber);
  } else if (item.expectedKind === 'transfer_target_kingdom_rules') {
    payload.target_kingdom_number = numeric(draft.targetKingdomNumber);
    payload.target_power_cap = numeric(draft.targetPowerCap);
    payload.kingdom_classification = draft.kingdomClassification || null;
  } else if (item.expectedKind === 'transfer_official_group') {
    payload.official_group_identifier = draft.officialGroupIdentifier || null;
    payload.kingdom_numbers = parseKingdoms(draft.officialGroupKingdoms);
  }

  notice.value = '';
  router.post(`${basePath.value}/${item.id}/review`, payload, {
    preserveScroll: true,
    onSuccess: () => void loadEvidence(true),
  });
}

async function preview(item: EvidenceItem): Promise<void> {
  if (!item.review || previewLoading[item.id]) return;
  previewLoading[item.id] = true;
  notice.value = '';
  try {
    const response = await fetch(`${basePath.value}/reviews/${item.review.id}/preview`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    if (!response.ok) throw new Error(`Preview request failed: ${response.status}`);
    previews[item.id] = (await response.json()) as Preview;
  } catch {
    notice.value = t('kingdomP7D.evidenceLoadFailed');
  } finally {
    previewLoading[item.id] = false;
  }
}

function resolveDuplicate(item: EvidenceItem): void {
  if (!item.review) return;
  const justification = duplicateJustifications[item.review.id]?.trim() ?? '';
  if (justification.length < 8) return;
  router.post(
    `${basePath.value}/reviews/${item.review.id}/resolve-duplicate`,
    { justification },
    { preserveScroll: true, onSuccess: () => void loadEvidence(true) },
  );
}

function commit(item: EvidenceItem): void {
  if (!item.review || !previews[item.id]) {
    notice.value = t('kingdomP7D.previewRequired');
    return;
  }
  router.post(
    `${basePath.value}/reviews/${item.review.id}/commit`,
    {},
    {
      preserveScroll: true,
      onSuccess: () => void loadEvidence(true),
    },
  );
}

function retry(item: EvidenceItem): void {
  router.post(
    `${basePath.value}/${item.id}/retry`,
    {},
    {
      preserveScroll: true,
      onSuccess: () => void loadEvidence(true),
    },
  );
}

function remove(item: EvidenceItem): void {
  requestConfirmation({
    id: `delete-transfer-evidence-${item.id}`,
    title: t('kingdomP7D.deleteEvidence'),
    description: t('kingdomP7D.deleteEvidenceConfirm'),
    confirmLabel: t('kingdomP7D.deleteEvidence'),
    cancelLabel: t('common.cancel'),
    danger: true,
    perform: (finish) =>
      router.delete(`${basePath.value}/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => void loadEvidence(true),
        onFinish: finish,
      }),
  });
}

function hasLowConfidence(item: EvidenceItem): boolean {
  const schema = schemaFor(item.expectedKind);
  if (!schema || !item.extraction) return false;
  return item.extraction.fields.some((field) => field.confidence < schema.minimumFieldConfidence);
}

function classMismatch(item: EvidenceItem): boolean {
  return Boolean(item.classification && item.classification.kind !== item.expectedKind);
}

function fieldCorrected(item: EvidenceItem, field: ExtractedField): boolean {
  const draft = drafts[item.id];
  if (!draft) return false;
  const normalized = String(field.value ?? '');
  const current =
    field.key === 'governor_power'
      ? draft.governorPower
      : field.key === 'transfer_score'
        ? draft.transferScore
        : field.key === 'transfer_passes_available'
          ? draft.passesAvailable
          : field.key === 'transfer_passes_required'
            ? draft.passesRequired
            : field.key === 'invitation_status'
              ? draft.invitationStatus
              : field.key === 'target_kingdom_number'
                ? draft.targetKingdomNumber
                : field.key === 'power_cap'
                  ? draft.targetPowerCap
                  : field.key === 'kingdom_classification'
                    ? draft.kingdomClassification
                    : field.key === 'official_group_identifier'
                      ? draft.officialGroupIdentifier
                      : normalized;
  return current.trim() !== normalized.trim();
}
</script>

<template>
  <section
    class="border-t border-[var(--ks-border)] p-5 sm:p-6"
    :aria-label="t('kingdomP7D.transferEvidenceTitle')"
  >
    <details @toggle="loadEvidence()">
      <summary class="cursor-pointer list-none">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="ks-kicker">{{ t('kingdomP7D.addInGameEvidence') }}</p>
            <h3 class="mt-1 text-lg font-semibold">{{ t('kingdomP7D.transferEvidenceTitle') }}</h3>
          </div>
          <span
            class="rounded-full border border-[var(--ks-border)] px-3 py-1 text-xs font-semibold"
          >
            {{ loaded ? evidence.length : '…' }}
          </span>
        </div>
        <p class="mt-2 max-w-4xl text-sm text-[var(--ks-muted)]">
          {{ t('kingdomP7D.transferEvidenceHelp') }}
        </p>
      </summary>

      <div class="mt-5 grid gap-5">
        <form
          v-if="mutable"
          class="rounded-xl border border-[var(--ks-border)] bg-black/10 p-4"
          @submit.prevent="upload"
        >
          <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
            <label class="text-sm font-semibold">
              {{ t('kingdomP7D.screenshotClass') }}
              <select
                v-model="uploadKind"
                class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
              >
                <option v-for="schema in schemas" :key="schema.kind" :value="schema.kind">
                  {{ kindLabel(schema.kind) }}
                </option>
              </select>
            </label>
            <label class="text-sm font-semibold">
              {{ t('kingdomP7D.uploadScreenshot') }}
              <input
                accept="image/jpeg,image/png,image/webp"
                class="mt-1 block w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 text-sm"
                required
                type="file"
                @change="chooseFile"
              />
            </label>
            <button
              :disabled="!uploadFile"
              class="rounded-lg bg-[var(--ks-gold)] px-4 py-2 text-sm font-bold text-[var(--ks-ink)] disabled:opacity-40"
              type="submit"
            >
              {{ t('kingdomP7D.uploadEvidence') }}
            </button>
          </div>
          <div v-if="schemaFor(uploadKind)" class="mt-3 text-xs text-[var(--ks-muted)]">
            {{ t('kingdomP7D.schemaVersion') }}: {{ schemaFor(uploadKind)!.version }} ·
            {{ t('kingdomP7D.fixtureCorpus') }}: {{ schemaFor(uploadKind)!.fixtureCorpus }} ·
            {{ t('kingdomP7D.destinationAction') }}: {{ schemaFor(uploadKind)!.destinationAction }}
          </div>
        </form>

        <div
          v-if="loading"
          role="status"
          class="rounded-xl border border-[var(--ks-border)] p-4 text-sm"
        >
          {{ t('kingdomP7D.evidenceLoading') }}
        </div>
        <div
          v-else-if="loadError"
          role="alert"
          class="rounded-xl border border-red-400/30 bg-red-500/10 p-4 text-sm text-red-100"
        >
          {{ t('kingdomP7D.evidenceLoadFailed') }}
        </div>
        <div
          v-else-if="loaded && evidence.length === 0"
          class="rounded-xl border border-[var(--ks-border)] p-4 text-sm text-[var(--ks-muted)]"
        >
          {{ t('kingdomP7D.noTransferEvidence') }}
        </div>

        <article
          v-for="item in evidence"
          :key="item.id"
          class="overflow-hidden rounded-xl border border-[var(--ks-border)] bg-black/10"
        >
          <header class="flex flex-wrap items-start justify-between gap-3 p-4">
            <div>
              <h4 class="font-semibold">{{ kindLabel(item.expectedKind) }}</h4>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('kingdomP7D.uploadTime') }} {{ timestamp(item.createdAt) }} ·
                {{ t('kingdomP7D.processingStatus') }}: {{ statusLabel(item.status) }}
              </p>
            </div>
            <a
              v-if="item.hasImage"
              :href="`${basePath}/${item.id}/image`"
              class="text-sm font-semibold text-[var(--ks-gold-bright)] underline-offset-4 hover:underline"
              target="_blank"
              >{{ t('kingdomP7D.evidenceImage') }}</a
            >
          </header>

          <div
            v-if="item.hasVisualDuplicate"
            class="border-y border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-100"
          >
            <strong>{{ t('kingdomP7D.visualDuplicate') }}</strong>
            <p class="mt-1">{{ t('kingdomP7D.visualDuplicateHelp') }}</p>
          </div>

          <div class="grid gap-4 border-t border-[var(--ks-border)] p-4 lg:grid-cols-2">
            <section>
              <h5 class="ks-kicker">{{ t('kingdomP7D.detectedClass') }}</h5>
              <dl class="mt-2 grid gap-2 text-sm">
                <div class="flex justify-between gap-4">
                  <dt>{{ t('kingdomP7D.expectedClass') }}</dt>
                  <dd class="font-semibold">{{ kindLabel(item.expectedKind) }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                  <dt>{{ t('kingdomP7D.detectedClass') }}</dt>
                  <dd class="font-semibold">
                    {{ item.classification ? kindLabel(item.classification.kind) : '—' }}
                  </dd>
                </div>
                <div class="flex justify-between gap-4">
                  <dt>{{ t('kingdomP7D.classificationConfidence') }}</dt>
                  <dd>{{ item.classification ? percent(item.classification.confidence) : '—' }}</dd>
                </div>
              </dl>
              <p v-if="item.classification?.reason" class="mt-2 text-xs text-[var(--ks-muted)]">
                {{ t('kingdomP7D.classificationReason') }}: {{ item.classification.reason }}
              </p>
              <p
                v-if="classMismatch(item)"
                role="alert"
                class="mt-3 rounded-lg border border-red-400/30 bg-red-500/10 p-3 text-sm text-red-100"
              >
                {{ t('kingdomP7D.classMismatch') }}
              </p>
            </section>
            <section v-if="schemaFor(item.expectedKind)">
              <h5 class="ks-kicker">{{ t('kingdomP7D.schemaVersion') }}</h5>
              <p class="mt-2 text-sm font-semibold">{{ schemaFor(item.expectedKind)!.version }}</p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('kingdomP7D.fixtureCorpus') }}:
                {{ schemaFor(item.expectedKind)!.fixtureCorpus }} ·
                {{ t('kingdomP7D.fieldConfidence') }} ≥
                {{ percent(schemaFor(item.expectedKind)!.minimumFieldConfidence) }}
              </p>
            </section>
          </div>

          <section v-if="item.extraction" class="border-t border-[var(--ks-border)] p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h5 class="font-semibold">{{ t('kingdomP7D.reviewedFacts') }}</h5>
              <span class="text-xs text-[var(--ks-muted)]"
                >{{ t('kingdomP7D.schemaVersion') }} {{ item.extraction.schemaVersion }}</span
              >
            </div>
            <div class="mt-3 grid gap-3 md:hidden">
              <article
                v-for="field in item.extraction.fields"
                :key="`mobile-${field.key}-${field.ordinal}`"
                class="rounded-lg border border-[var(--ks-border)] p-3 text-sm"
              >
                <p class="break-all font-semibold">{{ field.key }}</p>
                <dl class="mt-3 grid gap-3">
                  <div>
                    <dt class="ks-kicker">{{ t('kingdomP7D.rawObservation') }}</dt>
                    <dd class="mt-1 break-words">{{ field.raw }}</dd>
                  </div>
                  <div>
                    <dt class="ks-kicker">{{ t('kingdomP7D.normalizedValue') }}</dt>
                    <dd class="mt-1">
                      {{ displayValue(field.value) }}
                      <span
                        v-if="fieldCorrected(item, field)"
                        class="ml-1 text-xs font-semibold text-amber-200"
                        >· {{ t('kingdomP7D.evidenceCorrected') }}</span
                      >
                    </dd>
                  </div>
                  <div>
                    <dt class="ks-kicker">{{ t('kingdomP7D.fieldConfidence') }}</dt>
                    <dd class="mt-1">{{ percent(field.confidence) }}</dd>
                  </div>
                </dl>
                <p
                  v-if="
                    schemaFor(item.expectedKind) &&
                    field.confidence < schemaFor(item.expectedKind)!.minimumFieldConfidence
                  "
                  class="mt-3 text-xs text-amber-200"
                >
                  {{ t('kingdomP7D.belowSchemaConfidence') }}
                </p>
                <p
                  v-for="warning in field.warnings"
                  :key="warning"
                  class="mt-2 text-xs text-amber-200"
                >
                  {{ warning }}
                </p>
              </article>
            </div>
            <div class="mt-3 hidden overflow-x-auto md:block">
              <table class="w-full text-left text-sm">
                <thead class="text-xs text-[var(--ks-muted)]">
                  <tr>
                    <th class="pr-3 pb-2">{{ t('kingdomP7D.evidenceField') }}</th>
                    <th class="pr-3 pb-2">{{ t('kingdomP7D.rawObservation') }}</th>
                    <th class="pr-3 pb-2">{{ t('kingdomP7D.normalizedValue') }}</th>
                    <th class="pb-2">{{ t('kingdomP7D.fieldConfidence') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="field in item.extraction.fields"
                    :key="`${field.key}-${field.ordinal}`"
                    class="border-t border-[var(--ks-border)] align-top"
                  >
                    <td class="py-2 pr-3 font-semibold">{{ field.key }}</td>
                    <td class="py-2 pr-3 break-words">{{ field.raw }}</td>
                    <td class="py-2 pr-3">
                      {{ displayValue(field.value) }}
                      <span
                        v-if="fieldCorrected(item, field)"
                        class="ml-1 text-xs font-semibold text-amber-200"
                        >· {{ t('kingdomP7D.evidenceCorrected') }}</span
                      >
                    </td>
                    <td class="py-2">
                      {{ percent(field.confidence) }}
                      <p
                        v-if="
                          schemaFor(item.expectedKind) &&
                          field.confidence < schemaFor(item.expectedKind)!.minimumFieldConfidence
                        "
                        class="mt-1 max-w-xs text-xs text-amber-200"
                      >
                        {{ t('kingdomP7D.belowSchemaConfidence') }}
                      </p>
                      <p
                        v-for="warning in field.warnings"
                        :key="warning"
                        class="mt-1 text-xs text-amber-200"
                      >
                        {{ warning }}
                      </p>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-if="hasLowConfidence(item)" class="mt-3 text-sm text-amber-100">
              {{ t('kingdomP7D.belowSchemaConfidence') }}
            </p>
          </section>

          <section
            v-if="item.extraction && item.status !== 'unsupported' && item.status !== 'deleted'"
            class="border-t border-[var(--ks-border)] p-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h5 class="font-semibold">{{ t('kingdomP7D.reviewEvidence') }}</h5>
                <p class="mt-1 text-sm text-[var(--ks-muted)]">
                  {{ t('kingdomP7D.reviewBeforeCommit') }}
                </p>
              </div>
              <span
                v-if="item.review"
                class="rounded-full border border-[var(--ks-border)] px-3 py-1 text-xs font-semibold"
              >
                {{ reviewStatusLabel(item.review.status) }} · {{ t('kingdomP7D.evidenceRevision') }}
                {{ item.review.revision }}
              </span>
            </div>

            <form
              v-if="mutable && drafts[item.id]"
              class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
              @submit.prevent="review(item)"
            >
              <label class="text-sm font-semibold">
                {{ t('kingdomP7D.observationTime') }}
                <input
                  v-model="drafts[item.id]!.observedAt"
                  class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                  required
                  type="datetime-local"
                />
              </label>
              <label v-if="requiresValidity(item.expectedKind)" class="text-sm font-semibold">
                {{ t('kingdomP7D.freshnessBoundary') }}
                <input
                  v-model="drafts[item.id]!.validUntil"
                  class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                  required
                  type="datetime-local"
                />
              </label>

              <label
                v-if="item.expectedKind === 'transfer_governor_status'"
                class="text-sm font-semibold"
              >
                {{ t('kingdomP7D.observation_governor_power') }}
                <input
                  v-model="drafts[item.id]!.governorPower"
                  class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                  min="0"
                  required
                  type="number"
                />
              </label>

              <template v-else-if="item.expectedKind === 'transfer_score_passes'">
                <label class="text-sm font-semibold"
                  >{{ t('kingdomP7D.transferScore')
                  }}<input
                    v-model="drafts[item.id]!.transferScore"
                    class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                    min="0"
                    required
                    type="number"
                /></label>
                <label class="text-sm font-semibold"
                  >{{ t('kingdomP7D.passesAvailable')
                  }}<input
                    v-model="drafts[item.id]!.passesAvailable"
                    class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                    min="0"
                    required
                    type="number"
                /></label>
                <label class="text-sm font-semibold"
                  >{{ t('kingdomP7D.passesRequired')
                  }}<input
                    v-model="drafts[item.id]!.passesRequired"
                    class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                    min="0"
                    required
                    type="number"
                /></label>
              </template>

              <template v-else-if="item.expectedKind === 'transfer_invitation'">
                <label class="text-sm font-semibold"
                  >{{ t('kingdomP7D.invitationStatus') }}
                  <select
                    v-model="drafts[item.id]!.invitationStatus"
                    class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                    required
                  >
                    <option value="none">{{ t('kingdomP7D.invitation_none') }}</option>
                    <option value="ordinary_received">
                      {{ t('kingdomP7D.invitation_ordinary_received') }}
                    </option>
                    <option value="special_pending">
                      {{ t('kingdomP7D.invitation_special_pending') }}
                    </option>
                    <option value="special_approved">
                      {{ t('kingdomP7D.invitation_special_approved') }}
                    </option>
                  </select>
                </label>
                <label class="text-sm font-semibold"
                  >{{ t('kingdomP7D.targetKingdomNumber')
                  }}<input
                    v-model="drafts[item.id]!.targetKingdomNumber"
                    class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                    min="1"
                    type="number"
                /></label>
              </template>

              <template v-else-if="item.expectedKind === 'transfer_target_kingdom_rules'">
                <label class="text-sm font-semibold"
                  >{{ t('kingdomP7D.targetKingdomNumber')
                  }}<input
                    v-model="drafts[item.id]!.targetKingdomNumber"
                    class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                    min="1"
                    required
                    type="number"
                /></label>
                <label class="text-sm font-semibold"
                  >{{ t('kingdomP7D.powerCap')
                  }}<input
                    v-model="drafts[item.id]!.targetPowerCap"
                    class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                    min="0"
                    required
                    type="number"
                /></label>
                <label class="text-sm font-semibold"
                  >{{ t('kingdomP7D.kingdomClassification') }}
                  <select
                    v-model="drafts[item.id]!.kingdomClassification"
                    class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                  >
                    <option value="">{{ t('kingdomP7D.classificationNotProved') }}</option>
                    <option value="ordinary">{{ t('kingdomP7D.classification_ordinary') }}</option>
                    <option value="leading">{{ t('kingdomP7D.classification_leading') }}</option>
                  </select>
                </label>
              </template>

              <template v-else-if="item.expectedKind === 'transfer_official_group'">
                <label class="text-sm font-semibold"
                  >{{ t('kingdomP7D.officialGroupIdentifier')
                  }}<input
                    v-model="drafts[item.id]!.officialGroupIdentifier"
                    class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                    maxlength="96"
                    required
                /></label>
                <label class="text-sm font-semibold sm:col-span-2"
                  >{{ t('kingdomP7D.officialGroupKingdoms')
                  }}<input
                    v-model="drafts[item.id]!.officialGroupKingdoms"
                    class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                    required
                /></label>
              </template>

              <p
                v-if="requiresValidity(item.expectedKind)"
                class="text-xs text-[var(--ks-muted)] sm:col-span-2 lg:col-span-3"
              >
                {{ t('kingdomP7D.freshnessRequiredHelp') }}
              </p>
              <p class="text-xs text-[var(--ks-muted)] sm:col-span-2 lg:col-span-3">
                {{ t('kingdomP7D.correctionsHelp') }}
              </p>
              <div>
                <button
                  class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold"
                  type="submit"
                >
                  {{ t('kingdomP7D.reviewEvidence') }}
                </button>
              </div>
            </form>
          </section>

          <section
            v-if="item.review"
            class="grid gap-4 border-t border-[var(--ks-border)] p-4 lg:grid-cols-2"
          >
            <div>
              <h5 class="font-semibold">{{ t('kingdomP7D.currentTransferFacts') }}</h5>
              <p class="mt-2 text-sm">
                <strong>{{ t('kingdomP7D.currentEligibility') }}:</strong>
                {{
                  currentEligibility
                    ? t(`kingdomP7D.eligibility_${currentEligibility.outcome}`)
                    : t('kingdomP7D.needsVerification')
                }}
              </p>
              <p
                v-if="currentEligibility?.primaryAction"
                class="mt-1 text-sm text-[var(--ks-muted)]"
              >
                {{ currentEligibility.primaryAction }}
              </p>
              <dl class="mt-3 grid gap-2 text-sm">
                <div class="rounded-lg border border-[var(--ks-border)] p-3">
                  <dt class="ks-kicker">{{ t('kingdomP7D.transferScore') }}</dt>
                  <dd class="mt-1 font-semibold">{{ displayValue(currentTransferScore.value) }}</dd>
                  <dd class="mt-1 text-xs text-[var(--ks-muted)]">
                    {{ timestamp(currentTransferScore.observedAt)
                    }}<span v-if="currentTransferScore.validUntil">
                      · {{ t('kingdomP7D.validUntil') }}
                      {{ timestamp(currentTransferScore.validUntil) }}</span
                    >
                  </dd>
                </div>
                <div class="rounded-lg border border-[var(--ks-border)] p-3">
                  <dt class="ks-kicker">{{ t('kingdomP7D.officialTransferGroup') }}</dt>
                  <dd class="mt-1 font-semibold">
                    {{ currentOfficialGroup?.label ?? t('kingdomP7D.needsVerification') }}
                  </dd>
                  <dd v-if="currentOfficialGroup" class="mt-1 text-xs text-[var(--ks-muted)]">
                    {{ timestamp(currentOfficialGroup.observedAt) }} ·
                    {{ currentOfficialGroup.sourceReference }}
                  </dd>
                </div>
                <div class="rounded-lg border border-[var(--ks-border)] p-3">
                  <dt class="ks-kicker">{{ t('kingdomP7D.powerCap') }}</dt>
                  <dd class="mt-1 font-semibold">
                    {{ displayValue(currentTargetCondition?.powerCap) }}
                  </dd>
                  <dd v-if="currentTargetCondition" class="mt-1 text-xs text-[var(--ks-muted)]">
                    {{ timestamp(currentTargetCondition.observedAt) }} ·
                    {{ currentTargetCondition.sourceReference }}
                  </dd>
                </div>
              </dl>
              <details v-if="currentEligibility?.requirements.length" class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold">
                  {{ t('kingdomP7D.eligibilityRequirements') }}
                </summary>
                <ul class="mt-2 grid gap-2 text-sm">
                  <li
                    v-for="requirement in currentEligibility.requirements"
                    :key="requirement.key"
                    class="rounded-lg border border-[var(--ks-border)] p-3"
                  >
                    <div class="flex justify-between gap-3">
                      <strong>{{ t(`kingdomP7D.requirementKey_${requirement.key}`) }}</strong
                      ><span>{{ t(`kingdomP7D.requirement_${requirement.state}`) }}</span>
                    </div>
                    <p class="mt-1 text-[var(--ks-muted)]">{{ requirement.explanation }}</p>
                    <p v-if="requirement.observedAt" class="mt-1 text-xs text-[var(--ks-muted)]">
                      {{ timestamp(requirement.observedAt)
                      }}<span v-if="requirement.validUntil">
                        · {{ t('kingdomP7D.validUntil') }}
                        {{ timestamp(requirement.validUntil) }}</span
                      >
                    </p>
                  </li>
                </ul>
              </details>
            </div>

            <div>
              <h5 class="font-semibold">{{ t('kingdomP7D.reviewedFacts') }}</h5>
              <p class="mt-2 text-sm text-[var(--ks-muted)]">
                {{ t('kingdomP7D.observationTime') }} {{ timestamp(item.review.observedAt)
                }}<span v-if="item.review.validUntil">
                  · {{ t('kingdomP7D.validUntil') }} {{ timestamp(item.review.validUntil) }}</span
                >
              </p>
              <ul class="mt-3 grid gap-2 text-sm">
                <li
                  v-if="item.review.governorPower !== null"
                  class="rounded-lg border border-[var(--ks-border)] p-3"
                >
                  {{ t('kingdomP7D.observation_governor_power') }}:
                  <strong>{{ formatNumber(item.review.governorPower) }}</strong>
                </li>
                <li
                  v-if="item.review.transferScore !== null"
                  class="rounded-lg border border-[var(--ks-border)] p-3"
                >
                  {{ t('kingdomP7D.transferScore') }}:
                  <strong>{{ formatNumber(item.review.transferScore) }}</strong>
                </li>
                <li
                  v-if="item.review.passesAvailable !== null"
                  class="rounded-lg border border-[var(--ks-border)] p-3"
                >
                  {{ t('kingdomP7D.passesAvailable') }}:
                  <strong>{{ formatNumber(item.review.passesAvailable) }}</strong>
                </li>
                <li
                  v-if="item.review.passesRequired !== null"
                  class="rounded-lg border border-[var(--ks-border)] p-3"
                >
                  {{ t('kingdomP7D.passesRequired') }}:
                  <strong>{{ formatNumber(item.review.passesRequired) }}</strong>
                </li>
                <li
                  v-if="item.review.invitationStatus"
                  class="rounded-lg border border-[var(--ks-border)] p-3"
                >
                  {{ t('kingdomP7D.invitationStatus') }}:
                  <strong>{{ t(`kingdomP7D.invitation_${item.review.invitationStatus}`) }}</strong>
                </li>
                <li
                  v-if="item.review.targetPowerCap !== null"
                  class="rounded-lg border border-[var(--ks-border)] p-3"
                >
                  {{ t('kingdomP7D.powerCap') }}:
                  <strong>{{ formatNumber(item.review.targetPowerCap) }}</strong>
                </li>
                <li
                  v-if="item.review.officialGroupIdentifier"
                  class="rounded-lg border border-[var(--ks-border)] p-3"
                >
                  {{ t('kingdomP7D.officialGroupIdentifier') }}:
                  <strong>{{ item.review.officialGroupIdentifier }}</strong> ·
                  {{ item.review.officialGroupKingdomNumbers.join(', ') }}
                </li>
              </ul>
            </div>
          </section>

          <section
            v-if="item.review?.status === 'duplicate_blocked'"
            class="border-t border-amber-400/20 bg-amber-500/10 p-4 text-sm"
          >
            <strong class="text-amber-100">{{ t('kingdomP7D.semanticDuplicate') }}</strong>
            <p class="mt-1 text-amber-50/90">{{ t('kingdomP7D.semanticDuplicateHelp') }}</p>
            <form
              v-if="mutable"
              class="mt-3 flex flex-col gap-2 sm:flex-row"
              @submit.prevent="resolveDuplicate(item)"
            >
              <input
                v-model="duplicateJustifications[item.review.id]"
                :placeholder="t('kingdomP7D.duplicateJustification')"
                class="min-w-0 flex-1 rounded-lg border border-amber-400/30 bg-[var(--ks-bg)] px-3 py-2"
                minlength="8"
                required
              />
              <button
                class="rounded-lg border border-amber-300/40 px-4 py-2 font-semibold text-amber-50"
                type="submit"
              >
                {{ t('kingdomP7D.resolveDuplicate') }}
              </button>
            </form>
          </section>

          <section
            v-if="item.review?.status === 'approved'"
            class="border-t border-[var(--ks-border)] p-4"
          >
            <button
              :disabled="previewLoading[item.id]"
              class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold disabled:opacity-40"
              type="button"
              @click="preview(item)"
            >
              {{ t('kingdomP7D.previewImpact') }}
            </button>
            <div v-if="previews[item.id]" class="mt-4 grid gap-3 md:grid-cols-2">
              <div class="rounded-xl border border-[var(--ks-border)] p-4">
                <p class="ks-kicker">{{ t('kingdomP7D.beforeImport') }}</p>
                <p class="mt-1 font-semibold">
                  {{ t(`kingdomP7D.eligibility_${previews[item.id]!.current_outcome}`) }}
                </p>
                <p class="mt-2 text-sm text-[var(--ks-muted)]">
                  {{
                    previews[item.id]!.current_primary_action ?? t('kingdomP7D.noRemainingActions')
                  }}
                </p>
              </div>
              <div class="rounded-xl border border-[var(--ks-gold)]/40 bg-[var(--ks-gold)]/5 p-4">
                <p class="ks-kicker">{{ t('kingdomP7D.afterImport') }}</p>
                <p class="mt-1 font-semibold">
                  {{ t(`kingdomP7D.eligibility_${previews[item.id]!.after_outcome}`) }}
                </p>
                <p class="mt-2 text-sm text-[var(--ks-muted)]">
                  {{
                    previews[item.id]!.after_primary_action ?? t('kingdomP7D.noRemainingActions')
                  }}
                </p>
              </div>
              <p class="text-xs text-[var(--ks-muted)] md:col-span-2">
                {{ t('kingdomP7D.reviewedFactKeys') }}:
                {{ previews[item.id]!.reviewed_fact_keys.join(', ') }}
              </p>
            </div>
            <div
              v-if="mutable && previews[item.id] && item.commit?.status !== 'succeeded'"
              class="mt-4"
            >
              <button
                class="rounded-lg bg-[var(--ks-gold)] px-4 py-2 text-sm font-bold text-[var(--ks-ink)]"
                type="button"
                @click="commit(item)"
              >
                {{ t('kingdomP7D.commitEvidence') }}
              </button>
            </div>
          </section>

          <footer
            class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--ks-border)] p-4 text-sm"
          >
            <div>
              <span v-if="item.commit"
                ><strong>{{ commitStatusLabel(item.commit.status) }}</strong
                ><span v-if="item.commit.destinationReceiptId">
                  · {{ t('kingdomP7D.destinationReceipt') }}
                  {{ item.commit.destinationReceiptId }}</span
                ></span
              >
              <span v-else-if="item.status === 'unsupported'" class="text-amber-100">{{
                t('kingdomP7D.unsupportedEvidence')
              }}</span>
            </div>
            <div v-if="mutable" class="flex flex-wrap gap-2">
              <button
                v-if="item.status === 'failed'"
                class="rounded-lg border border-[var(--ks-border)] px-3 py-2 font-semibold"
                type="button"
                @click="retry(item)"
              >
                {{ t('kingdomP7D.retryProcessing') }}
              </button>
              <button
                v-if="!['classifying', 'extracting', 'committing', 'deleted'].includes(item.status)"
                class="rounded-lg border border-red-400/30 px-3 py-2 text-red-200"
                type="button"
                @click="remove(item)"
              >
                {{ t('kingdomP7D.deleteEvidence') }}
              </button>
            </div>
          </footer>
        </article>

        <p
          v-if="notice"
          aria-live="polite"
          class="rounded-lg border border-amber-400/30 bg-amber-500/10 p-3 text-sm text-amber-100"
        >
          {{ notice }}
        </p>
      </div>
    </details>
    <ConfirmActionDialog v-bind="dialog" @confirm="confirmAction" @cancel="cancelConfirmation" />
  </section>
</template>
