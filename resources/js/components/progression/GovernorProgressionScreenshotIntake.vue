<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import { useLocale } from '@/localization';

import type {
  GovernorProgressionEvidenceSummary,
  GovernorProgressionFact,
  GovernorProgressionHero,
  GovernorProgressionMachineField,
  GovernorProgressionReview,
  GovernorProgressionSchema,
  GovernorProgressionState,
} from './governorProgressionTypes';

type ReviewHeroRow = {
  hero_id: string;
  level: string;
  star: string;
  widget_level: string;
};

type ReviewGearRow = {
  slot_id: string;
  quality: string;
  level: string;
  star: string;
  mastery_level: string;
};

type ReviewCharmRow = {
  slot_id: string;
  charm_id: string;
  level: string;
};

type ReviewPayloadValue =
  | string
  | number
  | boolean
  | null
  | undefined
  | ReviewPayloadValue[]
  | { [key: string]: ReviewPayloadValue };

type ReviewPayload = Record<string, ReviewPayloadValue>;

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
  heroes: GovernorProgressionHero[];
  schemas: GovernorProgressionSchema[];
  evidence: GovernorProgressionEvidenceSummary[];
  progressionState: GovernorProgressionState;
  canManage: boolean;
}>();

const { t, formatDate } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
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

function labelForClass(kind: string): string {
  return classLabels.value[kind] ?? kind;
}

function schemaFor(kind: string): GovernorProgressionSchema | undefined {
  return props.schemas.find((schema) => schema.kind === kind);
}

function onFile(event: Event): void {
  const input = event.target as HTMLInputElement;
  uploadForm.evidence = input.files?.[0] ?? null;
}

function upload(): void {
  if (!props.canManage || !uploadForm.evidence_kind || !uploadForm.evidence) return;

  uploadForm.post(`/progression/governor/${props.rosterEntryId}/evidence`, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      uploadForm.evidence = null;
    },
  });
}

function machineFields(
  item: GovernorProgressionEvidenceSummary,
): GovernorProgressionMachineField[] {
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

function fieldKey(field: GovernorProgressionMachineField): string {
  return field.field_key ?? field.fieldKey ?? '';
}

function rowOrdinal(field: GovernorProgressionMachineField): number {
  return field.row_ordinal ?? field.rowOrdinal ?? 0;
}

function rawText(field: GovernorProgressionMachineField): string {
  return field.raw_text ?? field.rawText ?? '';
}

function candidate(field: GovernorProgressionMachineField): string {
  return field.canonical_id ?? field.candidate ?? field.normalizedValue ?? '';
}

function field(
  item: GovernorProgressionEvidenceSummary,
  key: string,
  ordinal = 0,
): GovernorProgressionMachineField | undefined {
  return machineFields(item).find(
    (value) => fieldKey(value) === key && rowOrdinal(value) === ordinal,
  );
}

function value(item: GovernorProgressionEvidenceSummary, key: string, ordinal = 0): string {
  const found = field(item, key, ordinal);
  return found ? candidate(found) : '';
}

function heroId(valueToResolve: string): string {
  const lowered = valueToResolve.trim().toLocaleLowerCase();
  return (
    props.heroes.find(
      (hero) =>
        hero.id.toLocaleLowerCase() === lowered || hero.name.toLocaleLowerCase() === lowered,
    )?.id ?? ''
  );
}

function ordinals(item: GovernorProgressionEvidenceSummary, key: string): number[] {
  return Array.from(
    new Set(
      machineFields(item)
        .filter((machineField) => fieldKey(machineField) === key)
        .map((machineField) => rowOrdinal(machineField)),
    ),
  ).sort((a, b) => a - b);
}

function startReview(item: GovernorProgressionEvidenceSummary): void {
  if (!props.canManage || !item.normalization) return;

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
      charm_id: value(item, 'charm_name', ordinal)
        .toLocaleLowerCase()
        .replace(/[^a-z0-9._-]+/g, '-'),
      level: value(item, 'charm_level', ordinal),
    }));
  }

  reviewDraft.value = draft;
  activeEvidenceId.value = item.id;
  duplicateResolution.value = '';
  preview.value = null;
}

function cancelReview(): void {
  activeEvidenceId.value = null;
  duplicateResolution.value = '';
  preview.value = null;
}

function optionalNumber(valueToParse: string): number | undefined {
  if (valueToParse.trim() === '') return undefined;
  const parsed = Number.parseInt(valueToParse, 10);
  return Number.isFinite(parsed) ? parsed : undefined;
}

function reviewPayload(): ReviewPayload {
  const draft = reviewDraft.value;

  if (draft.kind === 'governor_profile') {
    const payload: ReviewPayload = {};
    if (draft.observed_name.trim()) payload.observed_name = draft.observed_name.trim();
    if (draft.power.trim()) payload.power = draft.power.trim();
    if (draft.progression_level.trim()) {
      payload.progression_level = draft.progression_level.trim();
    }
    if (draft.observed_alliance_tag.trim()) {
      payload.observed_alliance_tag = draft.observed_alliance_tag.trim();
    }
    const kingdomNumber = optionalNumber(draft.kingdom_number);
    if (kingdomNumber !== undefined) payload.kingdom_number = kingdomNumber;
    return payload;
  }

  if (draft.kind === 'governor_hero_roster') {
    return {
      heroes: draft.heroes
        .filter((hero) => hero.hero_id !== '')
        .map((hero) => {
          const row: ReviewPayload = { hero_id: hero.hero_id };
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
    const payload: ReviewPayload = { hero_id: draft.hero_id };
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
        const row: ReviewPayload = { slot_id: gear.slot_id };
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
        const row: ReviewPayload = { slot_id: gear.slot_id };
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
      const row: ReviewPayload = { slot_id: charm.slot_id };
      if (charm.charm_id.trim()) row.charm_id = charm.charm_id.trim();
      const level = optionalNumber(charm.level);
      if (level !== undefined) row.level = level;
      return row;
    }),
  };
}

function saveReview(item: GovernorProgressionEvidenceSummary): void {
  if (!props.canManage || !item.normalization) return;

  router.post(
    `/progression/governor/${props.rosterEntryId}/evidence/${item.id}/review`,
    {
      normalization_attempt_id: item.normalization.id,
      captured_at: new Date(reviewDraft.value.capturedAt).toISOString(),
      payload: reviewPayload(),
    },
    {
      preserveScroll: true,
      onSuccess: cancelReview,
    },
  );
}

function resolveDuplicate(review: GovernorProgressionReview): void {
  if (!props.canManage || duplicateResolution.value.trim().length < 8) return;

  router.post(
    `/progression/governor/${props.rosterEntryId}/evidence/reviews/${review.id}/resolve-duplicate`,
    { justification: duplicateResolution.value.trim() },
    {
      preserveScroll: true,
      onSuccess: () => {
        duplicateResolution.value = '';
      },
    },
  );
}

function commit(review: GovernorProgressionReview): void {
  if (!props.canManage) return;

  router.post(
    `/progression/governor/${props.rosterEntryId}/evidence/reviews/${review.id}/commit`,
    {},
    { preserveScroll: true },
  );
}

function retry(item: GovernorProgressionEvidenceSummary): void {
  if (!props.canManage) return;

  router.post(
    `/progression/governor/${props.rosterEntryId}/evidence/${item.id}/retry`,
    {},
    { preserveScroll: true },
  );
}

function removeEvidence(item: GovernorProgressionEvidenceSummary): void {
  if (!props.canManage) return;

  requestConfirmation({
    id: `governor-progression-evidence-delete-${item.id}`,
    title: t('progression.deleteEvidence'),
    description: t('progression.deleteEvidenceConfirm'),
    confirmLabel: t('progression.deleteEvidence'),
    cancelLabel: t('common.cancel'),
    perform: (finish) =>
      router.delete(`/progression/governor/${props.rosterEntryId}/evidence/${item.id}`, {
        preserveScroll: true,
        onFinish: finish,
      }),
  });
}

async function previewReview(review: GovernorProgressionReview): Promise<void> {
  previewLoading.value = true;
  preview.value = null;
  try {
    const response = await fetch(
      `/progression/governor/${props.rosterEntryId}/evidence/reviews/${review.id}/preview`,
      {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      },
    );
    if (!response.ok) return;
    preview.value = (await response.json()) as { before: unknown; after: unknown };
  } finally {
    previewLoading.value = false;
  }
}

function addHeroRow(): void {
  reviewDraft.value.heroes.push({ hero_id: '', level: '', star: '', widget_level: '' });
}

function addGearRow(): void {
  reviewDraft.value.gear.push({
    slot_id: '',
    quality: '',
    level: '',
    star: '',
    mastery_level: '',
  });
}

function addCharmRow(): void {
  reviewDraft.value.charms.push({ slot_id: '', charm_id: '', level: '' });
}

function confidenceLabel(confidence: number): string {
  return `${Math.round(confidence * 100)}%`;
}

function isLowConfidence(
  item: GovernorProgressionEvidenceSummary,
  machineField: GovernorProgressionMachineField,
): boolean {
  const threshold = schemaFor(item.detectedKind)?.minimumFieldConfidence ?? 0.8;
  return machineField.confidence < threshold;
}

function heroName(heroIdValue: string): string {
  return heroById.value.get(heroIdValue)?.name ?? heroIdValue;
}

function factValue(fact: GovernorProgressionFact | undefined): string {
  if (!fact) return '—';
  if (typeof fact.value === 'boolean') return fact.value ? 'Yes' : 'No';
  return String(fact.value ?? '—');
}

function factDate(fact: GovernorProgressionFact | undefined): string {
  return fact ? formatDate(fact.capturedAt, { dateStyle: 'medium' }) : '';
}

function evidenceStatus(item: GovernorProgressionEvidenceSummary): string {
  if (item.commit?.status === 'completed') return t('progression.screenshotCommitted');
  if (item.lifecycleStatus === 'deleted') return t('progression.screenshotDeleted');
  if (item.lifecycleStatus === 'failed') return t('progression.screenshotProcessingFailed');
  if (['uploaded', 'classifying', 'classified', 'extracting'].includes(item.lifecycleStatus)) {
    return t('progression.processingScreenshot');
  }
  return item.lifecycleStatus.replaceAll('_', ' ');
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

      <form
        v-if="canManage"
        class="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end sm:p-6"
        @submit.prevent="upload"
      >
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
            accept="image/png,image/jpeg,image/webp"
            class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] px-3 py-2 text-sm"
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
        <p
          v-if="Object.keys(uploadForm.errors).length"
          class="text-sm text-red-300 sm:col-span-3"
          role="alert"
        >
          {{ Object.values(uploadForm.errors)[0] }}
        </p>
      </form>
    </section>

    <section class="ks-surface overflow-hidden" aria-labelledby="current-screenshot-state-heading">
      <div class="border-b border-[var(--ks-border)] p-5 sm:p-6">
        <p class="ks-kicker">{{ t('progression.observedFacts') }}</p>
        <h2 id="current-screenshot-state-heading" class="ks-display mt-1 text-2xl font-semibold">
          {{ t('progression.currentObservedState') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('progression.currentObservedStateHelp') }}
        </p>
        <p v-if="progressionState.last_updated_at" class="mt-2 text-xs text-[var(--ks-muted)]">
          {{ t('progression.lastRosterUpdate') }}:
          {{
            formatDate(progressionState.last_updated_at, {
              dateStyle: 'medium',
              timeStyle: 'short',
            })
          }}
        </p>
      </div>

      <div
        v-if="!progressionState.last_updated_at"
        class="p-5 text-sm text-[var(--ks-muted)] sm:p-6"
      >
        {{ t('progression.noCurrentScreenshotFacts') }}
      </div>

      <div v-else class="space-y-5 p-5 sm:p-6">
        <div
          v-if="Object.keys(progressionState.current.profile).length"
          class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
        >
          <article
            v-for="(fact, key) in progressionState.current.profile"
            :key="key"
            class="rounded border border-[var(--ks-border)] p-4"
          >
            <p class="text-xs font-semibold tracking-wide text-[var(--ks-muted)] uppercase">
              {{ String(key).replaceAll('_', ' ') }}
            </p>
            <p class="mt-1 text-lg font-semibold">{{ factValue(fact) }}</p>
            <p class="mt-1 text-xs text-[var(--ks-muted)]">
              {{ t('progression.factCaptured', { date: factDate(fact) }) }}
            </p>
          </article>
        </div>

        <div
          v-if="Object.keys(progressionState.current.heroes).length"
          class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
          <article
            v-for="(hero, heroIdValue) in progressionState.current.heroes"
            :key="heroIdValue"
            class="rounded border border-[var(--ks-border)] p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <h3 class="font-semibold">{{ heroName(String(heroIdValue)) }}</h3>
              <span v-if="hero.membership" class="ks-chip">
                {{
                  hero.membership.value === 'observed_absent'
                    ? t('progression.observedAbsent')
                    : t('progression.observedPresent')
                }}
              </span>
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
              <template v-for="(fact, key) in hero.facts" :key="key">
                <dt class="text-[var(--ks-muted)]">{{ String(key).replaceAll('_', ' ') }}</dt>
                <dd class="text-end">
                  {{ factValue(fact) }}
                  <span class="block text-[0.68rem] text-[var(--ks-muted)]">
                    {{ factDate(fact) }}
                  </span>
                </dd>
              </template>
            </dl>
            <div v-if="Object.keys(hero.gear).length" class="mt-4 space-y-2">
              <p class="text-xs font-semibold tracking-wide text-[var(--ks-muted)] uppercase">
                {{ t('progression.gear') }}
              </p>
              <div
                v-for="(gearFacts, slot) in hero.gear"
                :key="slot"
                class="rounded border border-[var(--ks-border)] p-2 text-xs"
              >
                <p class="font-semibold">{{ slot }}</p>
                <p class="mt-1 text-[var(--ks-muted)]">
                  <span v-for="(fact, key) in gearFacts" :key="key" class="me-2">
                    {{ key }}: {{ factValue(fact) }}
                  </span>
                </p>
              </div>
            </div>
          </article>
        </div>

        <div
          v-if="
            Object.keys(progressionState.current.governorGear).length ||
            Object.keys(progressionState.current.charms).length
          "
          class="grid gap-4 lg:grid-cols-2"
        >
          <article class="rounded border border-[var(--ks-border)] p-4">
            <h3 class="font-semibold">{{ t('progression.governorGearScreenshot') }}</h3>
            <div class="mt-3 space-y-2">
              <div
                v-for="(facts, slot) in progressionState.current.governorGear"
                :key="slot"
                class="rounded border border-[var(--ks-border)] p-3 text-xs"
              >
                <p class="font-semibold">{{ slot }}</p>
                <p class="mt-1 text-[var(--ks-muted)]">
                  <span v-for="(fact, key) in facts" :key="key" class="me-2">
                    {{ key }}: {{ factValue(fact) }}
                  </span>
                </p>
              </div>
            </div>
          </article>

          <article class="rounded border border-[var(--ks-border)] p-4">
            <h3 class="font-semibold">{{ t('progression.governorCharmsScreenshot') }}</h3>
            <div class="mt-3 space-y-2">
              <div
                v-for="(facts, slot) in progressionState.current.charms"
                :key="slot"
                class="rounded border border-[var(--ks-border)] p-3 text-xs"
              >
                <p class="font-semibold">{{ slot }}</p>
                <p class="mt-1 text-[var(--ks-muted)]">
                  <span v-for="(fact, key) in facts" :key="key" class="me-2">
                    {{ key }}: {{ factValue(fact) }}
                  </span>
                </p>
              </div>
            </div>
          </article>
        </div>

        <details
          v-if="progressionState.history.length"
          class="rounded border border-[var(--ks-border)]"
        >
          <summary class="min-h-11 cursor-pointer px-4 py-3 text-sm font-semibold">
            {{ t('progression.governorObservations') }} · {{ progressionState.history.length }}
          </summary>
          <ol class="border-t border-[var(--ks-border)]">
            <li
              v-for="observation in progressionState.history"
              :key="String(observation.id ?? observation.evidenceId ?? observation.capturedAt)"
              class="border-b border-[var(--ks-border)] px-4 py-3 text-xs last:border-b-0"
            >
              <span class="font-semibold">{{ observation.kind ?? 'observation' }}</span>
              <span v-if="observation.capturedAt" class="ms-2 text-[var(--ks-muted)]">
                {{
                  formatDate(String(observation.capturedAt), {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                  })
                }}
              </span>
              <span v-if="observation.datasetId" class="mt-1 block text-[var(--ks-muted)]">
                {{ observation.datasetId }}
              </span>
            </li>
          </ol>
        </details>
      </div>
    </section>

    <section
      v-if="canManage"
      class="ks-surface overflow-hidden"
      aria-labelledby="screenshot-evidence-heading"
    >
      <div class="border-b border-[var(--ks-border)] p-5 sm:p-6">
        <p class="ks-kicker">{{ t('progression.provenance') }}</p>
        <h2 id="screenshot-evidence-heading" class="ks-display mt-1 text-2xl font-semibold">
          {{ t('progression.evidenceHistory') }}
        </h2>
      </div>

      <p v-if="evidence.length === 0" class="p-5 text-sm text-[var(--ks-muted)] sm:p-6">
        {{ t('progression.noScreenshotEvidence') }}
      </p>

      <div v-else class="divide-y divide-[var(--ks-border)]">
        <article v-for="item in evidence" :key="item.id" class="p-5 sm:p-6">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold">{{ labelForClass(item.detectedKind) }}</h3>
                <span class="ks-chip">{{ evidenceStatus(item) }}</span>
              </div>
              <p class="mt-2 text-xs break-all text-[var(--ks-muted)]">
                {{ item.originalName }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('progression.expectedClass') }}: {{ labelForClass(item.expectedKind) }} ·
                {{ t('progression.detectedClass') }}: {{ labelForClass(item.detectedKind) }}
              </p>
              <p v-if="item.classification" class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('progression.fieldConfidence') }}:
                {{ confidenceLabel(item.classification.confidence) }}
                <span v-if="item.classification.reason"> · {{ item.classification.reason }}</span>
              </p>
              <p v-if="item.normalization" class="mt-1 text-xs break-all text-[var(--ks-muted)]">
                {{ t('progression.normalizationDataset') }}: {{ item.normalization.datasetId }} ·
                {{ item.normalization.datasetChecksum }}
              </p>
              <p v-if="item.visualDuplicate" class="mt-2 text-sm" role="status">
                Visual duplicate warning · distance {{ item.visualDuplicate.distance ?? 'unknown' }}
              </p>
              <p
                v-if="item.expectedKind !== item.detectedKind && item.detectedKind !== 'unknown'"
                class="mt-2 text-sm font-semibold"
                role="alert"
              >
                Expected/detected class mismatch — review the detected class before approval.
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
                v-if="item.lifecycleStatus === 'failed' || item.lifecycleStatus === 'unsupported'"
                type="button"
                class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm"
                @click="retry(item)"
              >
                {{ t('progression.retryProcessing') }}
              </button>
              <button
                v-if="item.lifecycleStatus !== 'deleted'"
                type="button"
                class="min-h-11 rounded border border-red-400/30 px-3 text-sm text-red-200"
                @click="removeEvidence(item)"
              >
                {{ t('progression.deleteEvidence') }}
              </button>
            </div>
          </div>

          <div v-if="machineFields(item).length" class="mt-5">
            <h4 class="text-sm font-semibold">{{ t('progression.machineCandidates') }}</h4>
            <div class="mt-2 grid gap-2 lg:grid-cols-2">
              <div
                v-for="machineField in machineFields(item)"
                :key="`${fieldKey(machineField)}-${rowOrdinal(machineField)}`"
                class="rounded border border-[var(--ks-border)] p-3 text-xs"
              >
                <div class="flex items-start justify-between gap-2">
                  <span class="font-semibold">{{ fieldKey(machineField) }}</span>
                  <span>{{ confidenceLabel(machineField.confidence) }}</span>
                </div>
                <p class="mt-2 text-[var(--ks-muted)]">
                  {{ t('progression.rawOcr') }}: {{ rawText(machineField) || '—' }}
                </p>
                <p class="mt-1">
                  {{ t('progression.normalizedCandidate') }}: {{ candidate(machineField) || '—' }}
                </p>
                <p
                  v-if="isLowConfidence(item, machineField)"
                  class="mt-2 font-semibold"
                  role="status"
                >
                  {{ t('progression.lowConfidence') }}
                </p>
                <ul
                  v-if="machineField.warnings.length"
                  class="mt-2 list-disc ps-4 text-[var(--ks-muted)]"
                >
                  <li v-for="warning in machineField.warnings" :key="warning">{{ warning }}</li>
                </ul>
              </div>
            </div>
          </div>

          <div
            v-if="item.normalization && !item.review && item.lifecycleStatus !== 'deleted'"
            class="mt-5"
          >
            <button
              v-if="activeEvidenceId !== item.id"
              type="button"
              class="min-h-11 rounded bg-[var(--ks-teal)] px-4 text-sm font-semibold"
              @click="startReview(item)"
            >
              {{ t('progression.reviewCandidates') }}
            </button>

            <form
              v-else
              class="mt-3 space-y-4 rounded border border-[var(--ks-border)] p-4"
              @submit.prevent="saveReview(item)"
            >
              <div>
                <h4 class="font-semibold">{{ t('progression.reviewCandidates') }}</h4>
                <p class="mt-1 text-xs text-[var(--ks-muted)]">
                  {{ t('progression.reviewHelp') }}
                </p>
              </div>

              <label class="block text-xs text-[var(--ks-muted)]">
                <span>{{ t('progression.capturedAt') }}</span>
                <input
                  v-model="reviewDraft.capturedAt"
                  required
                  type="datetime-local"
                  class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm sm:max-w-md"
                />
              </label>

              <div v-if="reviewDraft.kind === 'governor_profile'" class="grid gap-3 sm:grid-cols-2">
                <label class="text-xs text-[var(--ks-muted)]">
                  <span>Observed name</span>
                  <input
                    v-model="reviewDraft.observed_name"
                    class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm"
                  />
                </label>
                <label class="text-xs text-[var(--ks-muted)]">
                  <span>{{ t('progression.observedPower') }}</span>
                  <input
                    v-model="reviewDraft.power"
                    inputmode="numeric"
                    class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm"
                  />
                </label>
                <label class="text-xs text-[var(--ks-muted)]">
                  <span>{{ t('progression.progressionLevel') }}</span>
                  <input
                    v-model="reviewDraft.progression_level"
                    class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm"
                  />
                </label>
                <label class="text-xs text-[var(--ks-muted)]">
                  <span>Alliance tag</span>
                  <input
                    v-model="reviewDraft.observed_alliance_tag"
                    class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm"
                  />
                </label>
                <label class="text-xs text-[var(--ks-muted)]">
                  <span>Kingdom number</span>
                  <input
                    v-model="reviewDraft.kingdom_number"
                    inputmode="numeric"
                    class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm"
                  />
                </label>
              </div>

              <fieldset v-else-if="reviewDraft.kind === 'governor_hero_roster'" class="space-y-3">
                <legend class="font-semibold">{{ t('progression.heroObservations') }}</legend>
                <div
                  v-for="(hero, index) in reviewDraft.heroes"
                  :key="index"
                  class="grid gap-2 rounded border border-[var(--ks-border)] p-3 sm:grid-cols-4"
                >
                  <label class="text-xs text-[var(--ks-muted)]">
                    <span>{{ t('progression.hero') }}</span>
                    <select
                      v-model="hero.hero_id"
                      required
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-2 text-sm"
                    >
                      <option value="">{{ t('progression.selectHero') }}</option>
                      <option
                        v-for="heroOption in heroes"
                        :key="heroOption.id"
                        :value="heroOption.id"
                      >
                        {{ heroOption.name }}
                      </option>
                    </select>
                  </label>
                  <label class="text-xs text-[var(--ks-muted)]">
                    <span>{{ t('progression.level') }}</span>
                    <input
                      v-model="hero.level"
                      type="number"
                      min="0"
                      max="80"
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                    />
                  </label>
                  <label class="text-xs text-[var(--ks-muted)]">
                    <span>{{ t('progression.star') }}</span>
                    <input
                      v-model="hero.star"
                      type="number"
                      min="0"
                      max="5"
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                    />
                  </label>
                  <div class="grid grid-cols-[1fr_auto] gap-2">
                    <label class="text-xs text-[var(--ks-muted)]">
                      <span>{{ t('progression.widgetLevel') }}</span>
                      <input
                        v-model="hero.widget_level"
                        type="number"
                        min="0"
                        max="10"
                        class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                      />
                    </label>
                    <button
                      type="button"
                      class="mt-5 min-h-11 rounded border border-[var(--ks-border)] px-3"
                      :aria-label="t('progression.removeRow')"
                      @click="reviewDraft.heroes.splice(index, 1)"
                    >
                      ×
                    </button>
                  </div>
                </div>
                <button
                  type="button"
                  class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm"
                  @click="addHeroRow"
                >
                  + {{ t('progression.addHero') }}
                </button>
                <label
                  class="flex min-h-11 items-start gap-3 rounded border border-[var(--ks-border)] p-3 text-sm"
                >
                  <input v-model="reviewDraft.completeRosterCapture" type="checkbox" class="mt-1" />
                  <span>
                    {{ t('progression.completeRosterCapture') }}
                    <small class="mt-1 block text-[var(--ks-muted)]">
                      {{ t('progression.completeRosterWarning') }}
                    </small>
                  </span>
                </label>
              </fieldset>

              <div
                v-else-if="reviewDraft.kind === 'governor_hero_detail'"
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
              >
                <label class="text-xs text-[var(--ks-muted)]">
                  <span>{{ t('progression.hero') }}</span>
                  <select
                    v-model="reviewDraft.hero_id"
                    required
                    class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-2 text-sm"
                  >
                    <option value="">{{ t('progression.selectHero') }}</option>
                    <option
                      v-for="heroOption in heroes"
                      :key="heroOption.id"
                      :value="heroOption.id"
                    >
                      {{ heroOption.name }}
                    </option>
                  </select>
                </label>
                <label class="text-xs text-[var(--ks-muted)]">
                  <span>{{ t('progression.level') }}</span>
                  <input
                    v-model="reviewDraft.level"
                    type="number"
                    min="0"
                    max="80"
                    class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                  />
                </label>
                <label class="text-xs text-[var(--ks-muted)]">
                  <span>{{ t('progression.star') }}</span>
                  <input
                    v-model="reviewDraft.star"
                    type="number"
                    min="0"
                    max="5"
                    class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                  />
                </label>
                <label class="text-xs text-[var(--ks-muted)]">
                  <span>Substar</span>
                  <input
                    v-model="reviewDraft.substar"
                    type="number"
                    min="0"
                    max="5"
                    class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                  />
                </label>
                <label class="text-xs text-[var(--ks-muted)]">
                  <span>{{ t('progression.widgetLevel') }}</span>
                  <input
                    v-model="reviewDraft.widget_level"
                    type="number"
                    min="0"
                    max="10"
                    class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                  />
                </label>
              </div>

              <fieldset
                v-else-if="
                  reviewDraft.kind === 'governor_hero_gear' || reviewDraft.kind === 'governor_gear'
                "
                class="space-y-3"
              >
                <legend class="font-semibold">{{ t('progression.gear') }}</legend>
                <label
                  v-if="reviewDraft.kind === 'governor_hero_gear'"
                  class="block text-xs text-[var(--ks-muted)] sm:max-w-md"
                >
                  <span>{{ t('progression.hero') }}</span>
                  <select
                    v-model="reviewDraft.hero_id"
                    required
                    class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-2 text-sm"
                  >
                    <option value="">{{ t('progression.selectHero') }}</option>
                    <option
                      v-for="heroOption in heroes"
                      :key="heroOption.id"
                      :value="heroOption.id"
                    >
                      {{ heroOption.name }}
                    </option>
                  </select>
                </label>
                <div
                  v-for="(gear, index) in reviewDraft.gear"
                  :key="index"
                  class="grid gap-2 rounded border border-[var(--ks-border)] p-3 sm:grid-cols-5"
                >
                  <label class="text-xs text-[var(--ks-muted)]">
                    <span>{{ t('progression.gearSlot') }}</span>
                    <input
                      v-model="gear.slot_id"
                      required
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                    />
                  </label>
                  <label class="text-xs text-[var(--ks-muted)]">
                    <span>{{ t('progression.quality') }}</span>
                    <input
                      v-model="gear.quality"
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                    />
                  </label>
                  <label class="text-xs text-[var(--ks-muted)]">
                    <span>{{ t('progression.level') }}</span>
                    <input
                      v-model="gear.level"
                      type="number"
                      min="0"
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                    />
                  </label>
                  <label class="text-xs text-[var(--ks-muted)]">
                    <span>
                      {{
                        reviewDraft.kind === 'governor_hero_gear'
                          ? t('progression.masteryLevel')
                          : t('progression.star')
                      }}
                    </span>
                    <input
                      v-if="reviewDraft.kind === 'governor_hero_gear'"
                      v-model="gear.mastery_level"
                      type="number"
                      min="0"
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                    />
                    <input
                      v-else
                      v-model="gear.star"
                      type="number"
                      min="0"
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                    />
                  </label>
                  <button
                    type="button"
                    class="min-h-11 self-end rounded border border-[var(--ks-border)] px-3"
                    :aria-label="t('progression.removeRow')"
                    @click="reviewDraft.gear.splice(index, 1)"
                  >
                    ×
                  </button>
                </div>
                <button
                  type="button"
                  class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm"
                  @click="addGearRow"
                >
                  + {{ t('progression.addGearSlot') }}
                </button>
              </fieldset>

              <fieldset v-else-if="reviewDraft.kind === 'governor_charms'" class="space-y-3">
                <legend class="font-semibold">
                  {{ t('progression.governorCharmsScreenshot') }}
                </legend>
                <div
                  v-for="(charm, index) in reviewDraft.charms"
                  :key="index"
                  class="grid gap-2 rounded border border-[var(--ks-border)] p-3 sm:grid-cols-4"
                >
                  <label class="text-xs text-[var(--ks-muted)]">
                    <span>{{ t('progression.charmSlot') }}</span>
                    <input
                      v-model="charm.slot_id"
                      required
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                    />
                  </label>
                  <label class="text-xs text-[var(--ks-muted)]">
                    <span>{{ t('progression.charmIdentity') }}</span>
                    <input
                      v-model="charm.charm_id"
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                    />
                  </label>
                  <label class="text-xs text-[var(--ks-muted)]">
                    <span>{{ t('progression.level') }}</span>
                    <input
                      v-model="charm.level"
                      type="number"
                      min="0"
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                    />
                  </label>
                  <button
                    type="button"
                    class="min-h-11 self-end rounded border border-[var(--ks-border)] px-3"
                    :aria-label="t('progression.removeRow')"
                    @click="reviewDraft.charms.splice(index, 1)"
                  >
                    ×
                  </button>
                </div>
                <button
                  type="button"
                  class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm"
                  @click="addCharmRow"
                >
                  + {{ t('progression.addCharmSlot') }}
                </button>
              </fieldset>

              <div class="flex flex-wrap gap-2">
                <button
                  type="submit"
                  class="min-h-11 rounded bg-[var(--ks-teal)] px-4 text-sm font-semibold"
                >
                  {{ t('progression.saveReview') }}
                </button>
                <button
                  type="button"
                  class="min-h-11 rounded border border-[var(--ks-border)] px-4 text-sm"
                  @click="cancelReview"
                >
                  {{ t('common.cancel') }}
                </button>
              </div>
            </form>
          </div>

          <div v-if="item.review" class="mt-5 rounded border border-[var(--ks-border)] p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <h4 class="font-semibold">Approved review #{{ item.review.revisionNumber }}</h4>
                <p class="mt-1 text-xs break-all text-[var(--ks-muted)]">
                  {{ item.review.datasetId }} · {{ item.review.datasetChecksum }}
                </p>
                <p class="mt-1 text-xs text-[var(--ks-muted)]">
                  {{ t('progression.capturedAt') }}:
                  {{
                    formatDate(item.review.capturedAt, {
                      dateStyle: 'medium',
                      timeStyle: 'short',
                    })
                  }}
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm"
                  :disabled="previewLoading"
                  @click="previewReview(item.review)"
                >
                  {{ t('progression.destinationPreview') }}
                </button>
                <button
                  v-if="!item.commit || item.commit.status !== 'completed'"
                  type="button"
                  class="min-h-11 rounded bg-[var(--ks-teal)] px-3 text-sm font-semibold"
                  :disabled="
                    Boolean(item.review.semanticDuplicateReviewId) &&
                    !item.review.duplicateResolution
                  "
                  @click="commit(item.review)"
                >
                  {{ t('progression.commitObservation') }}
                </button>
              </div>
            </div>

            <div
              v-if="item.review.semanticDuplicateReviewId && !item.review.duplicateResolution"
              class="mt-4"
            >
              <p class="text-sm font-semibold" role="alert">
                {{ t('progression.semanticDuplicate') }}
              </p>
              <label class="mt-2 block text-xs text-[var(--ks-muted)]">
                <span>{{ t('progression.duplicateResolution') }}</span>
                <textarea
                  v-model="duplicateResolution"
                  rows="3"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-black/20 p-3 text-sm"
                />
              </label>
              <button
                type="button"
                class="mt-2 min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm disabled:opacity-50"
                :disabled="duplicateResolution.trim().length < 8"
                @click="resolveDuplicate(item.review)"
              >
                {{ t('progression.resolveDuplicate') }}
              </button>
            </div>

            <div v-if="preview" class="mt-4 grid gap-3 lg:grid-cols-2" aria-live="polite">
              <div class="rounded border border-[var(--ks-border)] p-3">
                <p class="text-xs font-semibold">{{ t('progression.beforePreview') }}</p>
                <pre class="mt-2 max-h-72 overflow-auto text-xs whitespace-pre-wrap">{{
                  JSON.stringify(preview.before, null, 2)
                }}</pre>
              </div>
              <div class="rounded border border-[var(--ks-border)] p-3">
                <p class="text-xs font-semibold">{{ t('progression.afterPreview') }}</p>
                <pre class="mt-2 max-h-72 overflow-auto text-xs whitespace-pre-wrap">{{
                  JSON.stringify(preview.after, null, 2)
                }}</pre>
              </div>
            </div>

            <div v-if="item.commit?.status === 'completed'" class="mt-4 text-sm" role="status">
              <p class="font-semibold">{{ t('progression.destinationReceipt') }}</p>
              <p class="mt-1 text-xs break-all text-[var(--ks-muted)]">
                {{ item.commit.destinationReceiptId }}
                <template v-if="item.commit.destinationReceipt?.observation_id">
                  · {{ item.commit.destinationReceipt.observation_id }}
                </template>
                <template v-if="item.commit.destinationReceipt?.idempotent_replay">
                  · recovered idempotent replay
                </template>
              </p>
            </div>
          </div>
        </article>
      </div>
    </section>
    <ConfirmActionDialog v-bind="dialog" @confirm="confirmAction" @cancel="cancelConfirmation" />
  </div>
</template>
