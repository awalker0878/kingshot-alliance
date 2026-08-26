<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { useLocale } from '@/localization';

type Hero = { id: string; name: string; generation: number; rarity: string; troopClass: string };
type Schema = {
  kind: string;
  version: string;
  supportedFields: string[];
  requiredFields: string[];
  minimumClassificationConfidence: number;
  minimumFieldConfidence: number;
  fixtureCorpus: string;
  destinationAction: string;
};
type MachineField = {
  field_id?: string;
  fieldKey?: string;
  field_key?: string;
  rowOrdinal?: number;
  row_ordinal?: number;
  rawText?: string;
  raw_text?: string;
  normalizedValue?: string | null;
  candidate?: string | null;
  confidence: number;
  warnings: string[];
  canonical_id?: string | null;
  identity_confidence?: number | null;
};
type EvidenceReview = {
  id: string;
  revisionNumber: number;
  status: string;
  kind: string;
  schemaVersion: string;
  datasetId: string;
  datasetChecksum: string;
  capturedAt: string;
  payload: Record<string, unknown>;
  semanticDuplicateReviewId: string | null;
  duplicateResolution: string | null;
};
type EvidenceSummary = {
  id: string;
  expectedKind: string;
  detectedKind: string;
  lifecycleStatus: string;
  createdAt: string | null;
  imageAvailable: boolean;
  visualDuplicate: { evidenceId: string; distance: number | null } | null;
  classification: { id: string; status: string; kind: string; confidence: number; reason: string | null } | null;
  extraction: {
    id: string;
    status: string;
    schemaVersion: string;
    overallConfidence: number;
    fields: Array<{
      id: string;
      fieldKey: string;
      rowOrdinal: number;
      rawText: string;
      normalizedValue: string | null;
      confidence: number;
      warnings: string[];
    }>;
  } | null;
  normalization: {
    id: string;
    status: string;
    datasetId: string;
    datasetChecksum: string;
    payload: { fields?: MachineField[] };
    warnings: string[];
  } | null;
  review: EvidenceReview | null;
  commit: {
    id: string;
    status: string;
    destinationAction: string;
    destinationReceiptId: string | null;
    destinationReceipt: { observation_id?: string; receipt_id?: string; idempotent_replay?: boolean } | null;
    failureCode: string | null;
  } | null;
};
type Fact = {
  value: unknown;
  capturedAt: string;
  observationId: string;
  evidenceId: string;
  reviewId: string;
  datasetId: string;
  datasetChecksum: string;
};
type HeroState = {
  facts: Record<string, Fact>;
  gear: Record<string, Record<string, Fact>>;
  membership: Fact | null;
};
type CurrentState = {
  profile: Record<string, Fact>;
  heroes: Record<string, HeroState>;
  governorGear: Record<string, Record<string, Fact>>;
  charms: Record<string, Record<string, Fact>>;
  completeRosterCapture: Fact | null;
};
type ProgressionState = {
  history: Array<Record<string, unknown>>;
  current: CurrentState;
  last_updated_at: string | null;
};
type ReviewHeroRow = { hero_id: string; level: string; star: string; widget_level: string };
type ReviewGearRow = {
  slot_id: string;
  quality: string;
  level: string;
  star: string;
  mastery_level: string;
};
type ReviewCharmRow = { slot_id: string; charm_id: string; level: string };
type ReviewDraft = {
  kind: string;
  capturedAt: string;
  observed_name: string;
  power: string;
  progression_level: string;
  observed_alliance_tag: string;
  kingdom_number: string;
  heroes: ReviewHeroRow[];
  completeRosterCapture: boolean;
  hero_id: string;
  level: string;
  star: string;
  substar: string;
  widget_level: string;
  gear: ReviewGearRow[];
  charms: ReviewCharmRow[];
};

const props = defineProps<{
  rosterEntryId: string;
  heroes: Hero[];
  schemas: Schema[];
  evidence: EvidenceSummary[];
  progressionState: ProgressionState;
}>();

const { t, formatDate } = useLocale();
const heroById = computed(() => new Map(props.heroes.map((hero) => [hero.id, hero])));
const activeEvidenceId = ref<string | null>(null);
const duplicateResolution = ref('');
const preview = ref<{ before: unknown; after: unknown } | null>(null);
const previewLoading = ref(false);
const reviewDraft = ref<ReviewDraft>(emptyDraft(''));

const uploadForm = useForm<{ evidence_kind: string; evidence: File | null }>({
  evidence_kind: props.schemas[0]?.kind ?? '',
  evidence: null,
});

const classLabels = computed<Record<string, string>>(() => ({
  governor_profile: t('progression.governorProfileScreenshot'),
  governor_hero_roster: t('progression.heroRosterScreenshot'),
  governor_hero_detail: t('progression.heroDetailScreenshot'),
  governor_hero_gear: t('progression.heroGearScreenshot'),
  governor_gear: t('progression.governorGearScreenshot'),
  governor_charms: t('progression.governorCharmsScreenshot'),
}));

function localDateTimeValue(date = new Date()): string {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
  return local.toISOString().slice(0, 16);
}

function emptyDraft(kind: string): ReviewDraft {
  return {
    kind,
    capturedAt: localDateTimeValue(),
    observed_name: '',
    power: '',
    progression_level: '',
    observed_alliance_tag: '',
    kingdom_number: '',
    heroes: [],
    completeRosterCapture: false,
    hero_id: '',
    level: '',
    star: '',
    substar: '',
    widget_level: '',
    gear: [],
    charms: [],
  };
}

function onFile(event: Event): void {
  const input = event.target as HTMLInputElement;
  uploadForm.evidence = input.files?.[0] ?? null;
}

function upload(): void {
  if (!uploadForm.evidence_kind || !uploadForm.evidence) return;
  uploadForm.post(`/progression/governor/${props.rosterEntryId}/evidence`, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      uploadForm.evidence = null;
    },
  });
}

function schemaFor(kind: string): Schema | undefined {
  return props.schemas.find((schema) => schema.kind === kind);
}

function machineFields(item: EvidenceSummary): MachineField[] {
  const normalized = item.normalization?.payload?.fields;
  if (Array.isArray(normalized)) return normalized;
  return (
    item.extraction?.fields.map((field) => ({
      fieldKey: field.fieldKey,
      rowOrdinal: field.rowOrdinal,
      rawText: field.rawText,
      candidate: field.normalizedValue,
      confidence: field.confidence,
      warnings: field.warnings,
      canonical_id: null,
    })) ?? []
  );
}

function fieldKey(field: MachineField): string {
  return field.field_key ?? field.fieldKey ?? '';
}

function rowOrdinal(field: MachineField): number {
  return field.row_ordinal ?? field.rowOrdinal ?? 0;
}

function rawText(field: MachineField): string {
  return field.raw_text ?? field.rawText ?? '';
}

function candidate(field: MachineField): string {
  return field.canonical_id ?? field.candidate ?? field.normalizedValue ?? '';
}

function field(item: EvidenceSummary, key: string, ordinal = 0): MachineField | undefined {
  return machineFields(item).find((value) => fieldKey(value) === key && rowOrdinal(value) === ordinal);
}

function value(item: EvidenceSummary, key: string, ordinal = 0): string {
  const found = field(item, key, ordinal);
  return found ? candidate(found) : '';
}

function heroId(valueToResolve: string): string {
  const lowered = valueToResolve.trim().toLocaleLowerCase();
  return (
    props.heroes.find(
      (hero) => hero.id.toLocaleLowerCase() === lowered || hero.name.toLocaleLowerCase() === lowered,
    )?.id ?? ''
  );
}

function ordinals(item: EvidenceSummary, key: string): number[] {
  return Array.from(
    new Set(
      machineFields(item)
        .filter((machineField) => fieldKey(machineField) === key)
        .map((machineField) => rowOrdinal(machineField)),
    ),
  ).sort((a, b) => a - b);
}

function startReview(item: EvidenceSummary): void {
  const draft = emptyDraft(item.detectedKind);
  if (item.detectedKind === 'governor_profile') {
    draft.observed_name = value(item, 'observed_name');
    draft.power = value(item, 'power');
    draft.progression_level = value(item, 'progression_level');
    draft.observed_alliance_tag = value(item, 'observed_alliance_tag');
    draft.kingdom_number = value(item, 'kingdom_number');
  } else if (item.detectedKind === 'governor_hero_roster') {
    draft.heroes = ordinals(item, 'hero_name').map((ordinal) => ({
      hero_id: heroId(value(item, 'hero_name', ordinal)),
      level: value(item, 'level', ordinal),
      star: value(item, 'star', ordinal),
      widget_level: value(item, 'widget_level', ordinal),
    }));
  } else if (item.detectedKind === 'governor_hero_detail') {
    draft.hero_id = heroId(value(item, 'hero_name'));
    draft.level = value(item, 'level');
    draft.star = value(item, 'star');
    draft.substar = value(item, 'substar');
    draft.widget_level = value(item, 'widget_level');
  } else if (item.detectedKind === 'governor_hero_gear') {
    draft.hero_id = heroId(value(item, 'hero_name'));
    draft.gear = ordinals(item, 'gear_slot').map((ordinal) => ({
      slot_id: value(item, 'gear_slot', ordinal),
      quality: value(item, 'gear_quality', ordinal),
      level: value(item, 'gear_level', ordinal),
      star: '',
      mastery_level: value(item, 'mastery_level', ordinal),
    }));
  } else if (item.detectedKind === 'governor_gear') {
    draft.gear = ordinals(item, 'gear_slot').map((ordinal) => ({
      slot_id: value(item, 'gear_slot', ordinal),
      quality: value(item, 'gear_quality', ordinal),
      level: value(item, 'gear_level', ordinal),
      star: value(item, 'gear_star', ordinal),
      mastery_level: '',
    }));
  } else if (item.detectedKind === 'governor_charms') {
    draft.charms = ordinals(item, 'charm_slot').map((ordinal) => ({
      slot_id: value(item, 'charm_slot', ordinal),
      charm_id: value(item, 'charm_name', ordinal).toLocaleLowerCase().replace(/[^a-z0-9._-]+/g, '-'),
      level: value(item, 'charm_level', ordinal),
    }));
  }
  reviewDraft.value = draft;
  activeEvidenceId.value = item.id;
  preview.value = null;
}

function optionalNumber(valueToParse: string): number | undefined {
  if (valueToParse.trim() === '') return undefined;
  const parsed = Number.parseInt(valueToParse, 10);
  return Number.isFinite(parsed) ? parsed : undefined;
}

function reviewPayload(): Record<string, unknown> {
  const draft = reviewDraft.value;
  if (draft.kind === 'governor_profile') {
    const payload: Record<string, unknown> = {};
    if (draft.observed_name.trim()) payload.observed_name = draft.observed_name.trim();
    if (draft.power.trim()) payload.power = draft.power.trim();
    if (draft.progression_level.trim()) payload.progression_level = draft.progression_level.trim();
    if (draft.observed_alliance_tag.trim()) payload.observed_alliance_tag = draft.observed_alliance_tag.trim();
    const kingdomNumber = optionalNumber(draft.kingdom_number);
    if (kingdomNumber !== undefined) payload.kingdom_number = kingdomNumber;
    return payload;
  }
  if (draft.kind === 'governor_hero_roster') {
    return {
      heroes: draft.heroes
        .filter((hero) => hero.hero_id !== '')
        .map((hero) => {
          const row: Record<string, unknown> = { hero_id: hero.hero_id };
          const level = optionalNumber(hero.level);
          const star = optionalNumber(hero.star);
          const widget = optionalNumber(hero.widget_level);
          if (level !== undefined) row.level = level;
          if (star !== undefined) row.star = star;
          if (widget !== undefined) row.widget_level = widget;
          return row;
        }),
      complete_roster_capture: draft.completeRosterCapture,
    };
  }
  if (draft.kind === 'governor_hero_detail') {
    const payload: Record<string, unknown> = { hero_id: draft.hero_id };
    for (const [key, raw] of [
      ['level', draft.level],
      ['star', draft.star],
      ['substar', draft.substar],
      ['widget_level', draft.widget_level],
    ] as const) {
      const parsed = optionalNumber(raw);
      if (parsed !== undefined) payload[key] = parsed;
    }
    return payload;
  }
  if (draft.kind === 'governor_hero_gear') {
    return {
      hero_id: draft.hero_id,
      gear: draft.gear.map((gear) => {
        const row: Record<string, unknown> = { slot_id: gear.slot_id };
        if (gear.quality.trim()) row.quality = gear.quality.trim();
        const level = optionalNumber(gear.level);
        const mastery = optionalNumber(gear.mastery_level);
        if (level !== undefined) row.level = level;
        if (mastery !== undefined) row.mastery_level = mastery;
        return row;
      }),
    };
  }
  if (draft.kind === 'governor_gear') {
    return {
      gear: draft.gear.map((gear) => {
        const row: Record<string, unknown> = { slot_id: gear.slot_id };
        if (gear.quality.trim()) row.quality = gear.quality.trim();
        const level = optionalNumber(gear.level);
        const star = optionalNumber(gear.star);
        if (level !== undefined) row.level = level;
        if (star !== undefined) row.star = star;
        return row;
      }),
    };
  }
  return {
    charms: draft.charms.map((charm) => {
      const row: Record<string, unknown> = { slot_id: charm.slot_id };
      if (charm.charm_id.trim()) row.charm_id = charm.charm_id.trim();
      const level = optionalNumber(charm.level);
      if (level !== undefined) row.level = level;
      return row;
    }),
  };
}

function saveReview(item: EvidenceSummary): void {
  if (!item.normalization) return;
  router.post(
    `/progression/governor/${props.rosterEntryId}/evidence/${item.id}/review`,
    {
      normalization_attempt_id: item.normalization.id,
      captured_at: new Date(reviewDraft.value.capturedAt).toISOString(),
      payload: reviewPayload(),
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        activeEvidenceId.value = null;
      },
    },
  );
}

function addGearRow(): void {
  reviewDraft.value.gear.push({ slot_id: '', quality: '', level: '', star: '', mastery_level: '' });
}

function addCharmRow(): void {
  reviewDraft.value.charms.push({ slot_id: '', charm_id: '', level: '' });
}

function resolveDuplicate(review: EvidenceReview): void {
  if (duplicateResolution.value.trim().length < 8) return;
  router.post(
    `/progression/governor/${props.rosterEntryId}/evidence/reviews/${review.id}/resolve-duplicate`,
    { justification: duplicateResolution.value.trim() },
    { preserveScroll: true, onSuccess: () => (duplicateResolution.value = '') },
  );
}

function commit(review: EvidenceReview): void {
  router.post(
    `/progression/governor/${props.rosterEntryId}/evidence/reviews/${review.id}/commit`,
    {},
    { preserveScroll: true },
  );
}

function retry(item: EvidenceSummary): void {
  router.post(
    `/progression/governor/${props.rosterEntryId}/evidence/${item.id}/retry`,
    {},
    { preserveScroll: true },
  );
}

function removeEvidence(item: EvidenceSummary): void {
  if (!window.confirm(t('progression.deleteEvidenceConfirm'))) return;
  router.delete(`/progression/governor/${props.rosterEntryId}/evidence/${item.id}`, {
    preserveScroll: true,
  });
}

async function previewReview(review: EvidenceReview): Promise<void> {
  previewLoading.value = true;
  preview.value = null;
  try {
    const response = await fetch(
      `/progression/governor/${props.rosterEntryId}/evidence/reviews/${review.id}/preview`,
      { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
    );
    if (!response.ok) return;
    preview.value = (await response.json()) as { before: unknown; after: unknown };
  } finally {
    previewLoading.value = false;
  }
}

function confidenceLabel(confidence: number): string {
  return `${Math.round(confidence * 100)}%`;
}

function labelForClass(kind: string): string {
  return classLabels.value[kind] ?? kind;
}

function heroName(heroIdValue: string): string {
  return heroById.value.get(heroIdValue)?.name ?? heroIdValue;
}

function displayFact(fact: Fact | undefined): string {
  if (!fact) return '—';
  if (fact.value === 'observed_present') return t('progression.observedPresent');
  if (fact.value === 'observed_absent') return t('progression.observedAbsent');
  return String(fact.value);
}

function factDate(fact: Fact | undefined): string {
  return fact ? formatDate(fact.capturedAt, { dateStyle: 'medium' }) : '';
}
</script>

<template>
  <div class="space-y-6">
    <section class="ks-surface overflow-hidden" aria-labelledby="screenshot-intake-heading">
      <div class="border-b border-[var(--ks-border)] p-5 sm:p-6">
        <p class="ks-kicker">{{ t('progression.screenshotIntake') }}</p>
        <h2 id="screenshot-intake-heading" class="ks-display mt-1 text-2xl font-semibold">
          {{ t('progression.screenshotIntake') }}
        </h2>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('progression.screenshotIntakeHelp') }}
        </p>
      </div>

      <form class="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end sm:p-6" @submit.prevent="upload">
        <label class="text-xs text-[var(--ks-muted)]">
          <span>{{ t('progression.screenshotClass') }}</span>
          <select
            v-model="uploadForm.evidence_kind"
            required
            class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-3 text-sm"
          >
            <option v-for="schema in schemas" :key="schema.kind" :value="schema.kind">
              {{ labelForClass(schema.kind) }}
            </option>
          </select>
        </label>
        <label class="text-xs text-[var(--ks-muted)]">
          <span>{{ t('progression.screenshotFile') }}</span>
          <input
            required
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="mt-1 block min-h-11 w-full rounded border border-[var(--ks-border)] px-3 py-2 text-sm"
            @change="onFile"
          />
        </label>
        <button
          type="submit"
          class="min-h-11 rounded bg-[var(--ks-teal)] px-4 text-sm font-semibold disabled:opacity-50"
          :disabled="uploadForm.processing || !uploadForm.evidence"
        >
          {{ t('progression.uploadScreenshot') }}
        </button>
        <p v-if="Object.keys(uploadForm.errors).length" class="text-sm text-red-300 sm:col-span-3" role="alert">
          {{ Object.values(uploadForm.errors)[0] }}
        </p>
      </form>
    </section>

    <section class="ks-surface overflow-hidden" aria-labelledby="current-screenshot-state-heading">
      <div class="border-b border-[var(--ks-border)] p-5 sm:p-6">
        <p class="ks-kicker">{{ t('progression.currentObservedState') }}</p>
        <h2 id="current-screenshot-state-heading" class="ks-display mt-1 text-2xl font-semibold">
          {{ t('progression.currentObservedState') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('progression.currentObservedStateHelp') }}
        </p>
        <p v-if="progressionState.last_updated_at" class="mt-2 text-xs text-[var(--ks-muted)]">
          {{ t('progression.lastRosterUpdate') }}:
          {{ formatDate(progressionState.last_updated_at, { dateStyle: 'medium', timeStyle: 'short' }) }}
        </p>
      </div>
      <div
        v-if="!progressionState.last_updated_at"
        class="p-5 text-sm text-[var(--ks-muted)] sm:p-6"
      >
        {{ t('progression.noCurrentScreenshotFacts') }}
      </div>
      <div v-else class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-3">
        <article
          v-for="(hero, heroIdValue) in progressionState.current.heroes"
          :key="heroIdValue"
          class="rounded border border-[var(--ks-border)] p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <h3 class="font-semibold">{{ heroName(String(heroIdValue)) }}</h3>
            <span class="text-xs text-[var(--ks-muted)]">{{ displayFact(hero.membership ?? undefined) }}</span>
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
            <template v-for="(fact, key) in hero.facts" :key="key">
              <dt class="text-[var(--ks-muted)]">{{ key }}</dt>
              <dd class="text-end">
                {{ displayFact(fact) }}
                <span class="block text-[0.68rem] text-[var(--ks-muted)]">{{ factDate(fact) }}</span>
              </dd>
            </template>
          </dl>
          <details v-if="Object.keys(hero.gear).length" class="mt-3 text-sm">
            <summary class="cursor-pointer font-medium">{{ t('progression.gear') }}</summary>
            <div class="mt-2 space-y-2">
              <div v-for="(slot, slotId) in hero.gear" :key="slotId" class="rounded bg-black/15 p-2">
                <p class="text-xs font-semibold">{{ slotId }}</p>
                <p v-for="(fact, key) in slot" :key="key" class="mt-1 text-xs text-[var(--ks-muted)]">
                  {{ key }}: {{ displayFact(fact) }} · {{ factDate(fact) }}
                </p>
              </div>
            </div>
          </details>
        </article>
      </div>
    </section>

    <section class="ks-surface overflow-hidden" aria-labelledby="evidence-history-heading">
      <div class="border-b border-[var(--ks-border)] p-5 sm:p-6">
        <p class="ks-kicker">{{ t('progression.evidenceHistory') }}</p>
        <h2 id="evidence-history-heading" class="ks-display mt-1 text-2xl font-semibold">
          {{ t('progression.evidenceHistory') }}
        </h2>
      </div>
      <p v-if="evidence.length === 0" class="p-5 text-sm text-[var(--ks-muted)] sm:p-6">
        {{ t('progression.noScreenshotEvidence') }}
      </p>

      <div class="divide-y divide-[var(--ks-border)]">
        <article v-for="item in evidence" :key="item.id" class="space-y-4 p-5 sm:p-6">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold">{{ labelForClass(item.expectedKind) }}</h3>
                <span class="ks-chip">{{ item.lifecycleStatus }}</span>
              </div>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('progression.expectedClass') }}: {{ labelForClass(item.expectedKind) }} ·
                {{ t('progression.detectedClass') }}: {{ labelForClass(item.detectedKind) }}
              </p>
              <p v-if="item.normalization" class="mt-1 break-all text-xs text-[var(--ks-muted)]">
                {{ t('progression.normalizationDataset') }}: {{ item.normalization.datasetId }} ·
                {{ item.normalization.datasetChecksum.slice(0, 12) }}…
              </p>
            </div>
            <div class="flex flex-wrap gap-2">
              <a
                v-if="item.imageAvailable"
                :href="`/progression/governor/${rosterEntryId}/evidence/${item.id}/image`"
                target="_blank"
                rel="noopener"
                class="ks-command-link"
                data-variant="secondary"
              >
                {{ t('progression.viewScreenshot') }}
              </a>
              <button
                v-if="item.lifecycleStatus === 'failed'"
                type="button"
                class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm"
                @click="retry(item)"
              >
                {{ t('progression.retryProcessing') }}
              </button>
              <button
                v-if="!['classifying', 'extracting', 'committing', 'deleted'].includes(item.lifecycleStatus)"
                type="button"
                class="min-h-11 rounded border border-red-400/25 px-3 text-sm text-red-200"
                @click="removeEvidence(item)"
              >
                {{ t('progression.deleteEvidence') }}
              </button>
            </div>
          </div>

          <p v-if="item.lifecycleStatus === 'failed'" class="text-sm text-red-300" role="status">
            {{ t('progression.screenshotProcessingFailed') }}
          </p>
          <p
            v-else-if="['uploaded', 'classifying', 'classified', 'extracting'].includes(item.lifecycleStatus)"
            class="text-sm text-[var(--ks-muted)]"
            role="status"
          >
            {{ t('progression.processingScreenshot') }}
          </p>
          <p v-else-if="item.lifecycleStatus === 'committed'" class="text-sm text-emerald-200" role="status">
            {{ t('progression.screenshotCommitted') }}
          </p>
          <p v-else-if="item.lifecycleStatus === 'deleted'" class="text-sm text-[var(--ks-muted)]" role="status">
            {{ t('progression.screenshotDeleted') }}
          </p>

          <details v-if="machineFields(item).length" class="rounded border border-[var(--ks-border)] p-3">
            <summary class="cursor-pointer font-semibold">{{ t('progression.machineCandidates') }}</summary>
            <div class="mt-3 overflow-x-auto">
              <table class="w-full min-w-[42rem] text-left text-xs">
                <thead class="text-[var(--ks-muted)]">
                  <tr>
                    <th class="pb-2 pe-3">Field</th>
                    <th class="pb-2 pe-3">{{ t('progression.rawOcr') }}</th>
                    <th class="pb-2 pe-3">{{ t('progression.normalizedCandidate') }}</th>
                    <th class="pb-2">{{ t('progression.fieldConfidence') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="machineField in machineFields(item)" :key="`${fieldKey(machineField)}-${rowOrdinal(machineField)}`" class="border-t border-[var(--ks-border)] align-top">
                    <td class="py-2 pe-3 font-medium">{{ fieldKey(machineField) }} #{{ rowOrdinal(machineField) + 1 }}</td>
                    <td class="py-2 pe-3">{{ rawText(machineField) }}</td>
                    <td class="py-2 pe-3">{{ candidate(machineField) || '—' }}</td>
                    <td class="py-2">
                      <span>{{ confidenceLabel(machineField.confidence) }}</span>
                      <span
                        v-if="machineField.confidence < (schemaFor(item.detectedKind)?.minimumFieldConfidence ?? 0) || machineField.canonical_id === null && fieldKey(machineField) === 'hero_name'"
                        class="mt-1 block text-amber-200"
                      >
                        {{ t('progression.lowConfidence') }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </details>

          <div v-if="item.lifecycleStatus === 'needs_review' && item.normalization" class="rounded border border-[var(--ks-border)] p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h4 class="font-semibold">{{ t('progression.reviewCandidates') }}</h4>
                <p class="mt-1 text-xs text-[var(--ks-muted)]">{{ t('progression.reviewHelp') }}</p>
              </div>
              <button
                v-if="activeEvidenceId !== item.id"
                type="button"
                class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm"
                @click="startReview(item)"
              >
                {{ t('progression.reviewCandidates') }}
              </button>
            </div>

            <form v-if="activeEvidenceId === item.id" class="mt-4 space-y-4" @submit.prevent="saveReview(item)">
              <label class="block text-xs text-[var(--ks-muted)]">
                <span>{{ t('progression.capturedAt') }}</span>
                <input v-model="reviewDraft.capturedAt" required type="datetime-local" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm sm:max-w-sm" />
              </label>

              <div v-if="reviewDraft.kind === 'governor_profile'" class="grid gap-3 sm:grid-cols-2">
                <label class="text-xs text-[var(--ks-muted)]"><span>Name</span><input v-model="reviewDraft.observed_name" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm" /></label>
                <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.observedPower') }}</span><input v-model="reviewDraft.power" inputmode="numeric" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm" /></label>
                <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.progressionLevel') }}</span><input v-model="reviewDraft.progression_level" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm" /></label>
                <label class="text-xs text-[var(--ks-muted)]"><span>Alliance</span><input v-model="reviewDraft.observed_alliance_tag" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm" /></label>
                <label class="text-xs text-[var(--ks-muted)]"><span>Kingdom</span><input v-model="reviewDraft.kingdom_number" inputmode="numeric" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm" /></label>
              </div>

              <div v-else-if="reviewDraft.kind === 'governor_hero_roster'" class="space-y-3">
                <fieldset v-for="(hero, index) in reviewDraft.heroes" :key="index" class="grid gap-2 rounded border border-[var(--ks-border)] p-3 sm:grid-cols-4">
                  <legend class="px-1 text-xs text-[var(--ks-muted)]">{{ t('progression.heroObservationNumber', { number: index + 1 }) }}</legend>
                  <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.hero') }}</span><select v-model="hero.hero_id" required class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-2 text-sm"><option value="">{{ t('progression.selectHero') }}</option><option v-for="option in heroes" :key="option.id" :value="option.id">{{ option.name }}</option></select></label>
                  <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.level') }}</span><input v-model="hero.level" type="number" min="0" max="80" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label>
                  <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.star') }}</span><input v-model="hero.star" type="number" min="0" max="5" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label>
                  <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.widgetLevel') }}</span><input v-model="hero.widget_level" type="number" min="0" max="10" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label>
                </fieldset>
                <label class="flex min-h-11 items-center gap-2 rounded border border-[var(--ks-border)] px-3 text-sm"><input v-model="reviewDraft.completeRosterCapture" type="checkbox" /><span>{{ t('progression.completeRosterCapture') }}</span></label>
                <p v-if="reviewDraft.completeRosterCapture" class="text-xs text-amber-200">{{ t('progression.completeRosterWarning') }}</p>
              </div>

              <div v-else-if="reviewDraft.kind === 'governor_hero_detail'" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.hero') }}</span><select v-model="reviewDraft.hero_id" required class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-2 text-sm"><option value="">{{ t('progression.selectHero') }}</option><option v-for="option in heroes" :key="option.id" :value="option.id">{{ option.name }}</option></select></label>
                <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.level') }}</span><input v-model="reviewDraft.level" type="number" min="0" max="80" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label>
                <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.star') }}</span><input v-model="reviewDraft.star" type="number" min="0" max="5" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label>
                <label class="text-xs text-[var(--ks-muted)]"><span>Substar</span><input v-model="reviewDraft.substar" type="number" min="0" max="5" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label>
                <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.widgetLevel') }}</span><input v-model="reviewDraft.widget_level" type="number" min="0" max="10" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label>
              </div>

              <div v-else-if="reviewDraft.kind === 'governor_hero_gear' || reviewDraft.kind === 'governor_gear'" class="space-y-3">
                <label v-if="reviewDraft.kind === 'governor_hero_gear'" class="block text-xs text-[var(--ks-muted)]"><span>{{ t('progression.hero') }}</span><select v-model="reviewDraft.hero_id" required class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-2 text-sm sm:max-w-sm"><option value="">{{ t('progression.selectHero') }}</option><option v-for="option in heroes" :key="option.id" :value="option.id">{{ option.name }}</option></select></label>
                <fieldset v-for="(gear, index) in reviewDraft.gear" :key="index" class="grid gap-2 rounded border border-[var(--ks-border)] p-3 sm:grid-cols-4">
                  <legend class="px-1 text-xs text-[var(--ks-muted)]">{{ t('progression.gearSlot') }} {{ index + 1 }}</legend>
                  <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.gearSlot') }}</span><input v-model="gear.slot_id" required class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label>
                  <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.quality') }}</span><input v-model="gear.quality" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label>
                  <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.level') }}</span><input v-model="gear.level" type="number" min="0" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label>
                  <div class="grid grid-cols-[1fr_auto] gap-2">
                    <label class="text-xs text-[var(--ks-muted)]"><span>{{ reviewDraft.kind === 'governor_hero_gear' ? t('progression.masteryLevel') : t('progression.star') }}</span><input v-if="reviewDraft.kind === 'governor_hero_gear'" v-model="gear.mastery_level" type="number" min="0" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /><input v-else v-model="gear.star" type="number" min="0" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label>
                    <button type="button" class="mt-5 min-h-11 rounded border border-red-400/25 px-3 text-red-200" :aria-label="t('progression.removeRow')" @click="reviewDraft.gear.splice(index, 1)">×</button>
                  </div>
                </fieldset>
                <button type="button" class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm" @click="addGearRow">+ {{ t('progression.addGearSlot') }}</button>
              </div>

              <div v-else-if="reviewDraft.kind === 'governor_charms'" class="space-y-3">
                <fieldset v-for="(charm, index) in reviewDraft.charms" :key="index" class="grid gap-2 rounded border border-[var(--ks-border)] p-3 sm:grid-cols-3">
                  <legend class="px-1 text-xs text-[var(--ks-muted)]">{{ t('progression.charmSlot') }} {{ index + 1 }}</legend>
                  <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.charmSlot') }}</span><input v-model="charm.slot_id" required class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label>
                  <label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.charmIdentity') }}</span><input v-model="charm.charm_id" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label>
                  <div class="grid grid-cols-[1fr_auto] gap-2"><label class="text-xs text-[var(--ks-muted)]"><span>{{ t('progression.level') }}</span><input v-model="charm.level" type="number" min="0" max="22" class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm" /></label><button type="button" class="mt-5 min-h-11 rounded border border-red-400/25 px-3 text-red-200" :aria-label="t('progression.removeRow')" @click="reviewDraft.charms.splice(index, 1)">×</button></div>
                </fieldset>
                <button type="button" class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm" @click="addCharmRow">+ {{ t('progression.addCharmSlot') }}</button>
              </div>

              <button type="submit" class="min-h-11 rounded bg-[var(--ks-teal)] px-4 text-sm font-semibold">
                {{ t('progression.saveReview') }}
              </button>
            </form>
          </div>

          <div v-if="item.review?.status === 'duplicate_blocked'" class="rounded border border-amber-400/30 p-4">
            <p class="font-semibold text-amber-100">{{ t('progression.semanticDuplicate') }}</p>
            <label class="mt-3 block text-xs text-[var(--ks-muted)]"><span>{{ t('progression.duplicateResolution') }}</span><textarea v-model="duplicateResolution" rows="3" class="mt-1 w-full rounded border border-[var(--ks-border)] bg-black/20 p-3 text-sm" /></label>
            <button type="button" class="mt-3 min-h-11 rounded border border-amber-400/40 px-3 text-sm" @click="resolveDuplicate(item.review)">{{ t('progression.resolveDuplicate') }}</button>
          </div>

          <div v-if="item.review?.status === 'approved'" class="flex flex-wrap gap-2">
            <button type="button" class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm" :disabled="previewLoading" @click="previewReview(item.review)">{{ t('progression.destinationPreview') }}</button>
            <button v-if="item.lifecycleStatus !== 'committed'" type="button" class="min-h-11 rounded bg-[var(--ks-teal)] px-3 text-sm font-semibold" @click="commit(item.review)">{{ t('progression.commitObservation') }}</button>
          </div>

          <div v-if="preview" class="grid gap-3 lg:grid-cols-2" aria-live="polite">
            <div class="rounded border border-[var(--ks-border)] p-3"><p class="mb-2 text-xs font-semibold">{{ t('progression.beforePreview') }}</p><pre class="max-h-80 overflow-auto whitespace-pre-wrap text-[0.68rem] text-[var(--ks-muted)]">{{ JSON.stringify(preview.before, null, 2) }}</pre></div>
            <div class="rounded border border-[var(--ks-border)] p-3"><p class="mb-2 text-xs font-semibold">{{ t('progression.afterPreview') }}</p><pre class="max-h-80 overflow-auto whitespace-pre-wrap text-[0.68rem] text-[var(--ks-muted)]">{{ JSON.stringify(preview.after, null, 2) }}</pre></div>
          </div>

          <p v-if="item.commit?.destinationReceiptId" class="break-all text-xs text-[var(--ks-muted)]">
            {{ t('progression.destinationReceipt') }}: {{ item.commit.destinationReceiptId }}
            <span v-if="item.commit.destinationReceipt?.observation_id"> · {{ item.commit.destinationReceipt.observation_id }}</span>
          </p>
        </article>
      </div>
    </section>
  </div>
</template>
