<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Plan = { id: string; name: string; kingdom_id: string; can_manage?: boolean };
type Revision = {
  id: string;
  revision_number: number;
  map_dataset_id: string;
  map_dataset_checksum: string;
  published_at: string;
};
type AllianceOption = { id: string; key: string; name: string };
type SpatialObject = {
  key: string;
  type: string;
  x: number;
  y: number;
  player_id?: string | null;
  external_player_name?: string | null;
  plan_local_identity?: string | null;
  observed_label?: string | null;
  identity_state?: string;
  confidence?: number | null;
};
type GovernorRow = {
  planned: SpatialObject;
  observed: SpatialObject | null;
  status: string;
  distance_tiles: number | null;
  delta_x: number | null;
  delta_y: number | null;
  planned_covered: boolean | null;
  observed_covered: boolean | null;
  coverage_delta: string;
};
type StructureRow = {
  planned: SpatialObject;
  observed: SpatialObject | null;
  status: string;
  distance_tiles: number | null;
  delta_x: number | null;
  delta_y: number | null;
};
type Observation = {
  id: string;
  captured_at: string;
  coverage_kind: string;
  completeness: string;
  map_dataset_id: string;
  map_dataset_checksum: string;
  source_evidence_id?: string | null;
};
type Summary = {
  governors_total: number;
  in_position: number;
  out_of_position: number;
  missing: number;
  not_observed: number;
  unexpected: number;
  structures_changed: number;
  lost_coverage: number;
};
type Reconciliation = {
  state: string;
  plan: Plan;
  revision?: Revision;
  revisions?: Revision[];
  alliance?: AllianceOption;
  alliance_options?: AllianceOption[];
  observation?: Observation;
  observations?: Observation[];
  freshness?: { age_seconds: number; state: string };
  compatibility?: string;
  summary?: Summary;
  governors?: GovernorRow[];
  structures?: StructureRow[];
  unexpected?: Array<SpatialObject & { status: string; observed_covered?: boolean | null }>;
  planned_objects?: SpatialObject[];
  observed_objects?: SpatialObject[];
};
type Candidate = {
  field: string;
  ordinal: number;
  raw: string;
  normalized: string;
  confidence: number;
  bounds?: Record<string, number> | null;
  warnings?: string[];
};
type EvidenceItem = {
  id: string;
  status: string;
  detected_kind: string;
  created_at: string | null;
  visual_duplicate_evidence_id?: string | null;
  visual_duplicate_distance?: number | null;
  candidates: Candidate[];
  review: null | {
    id: string;
    revision: number;
    status: string;
    captured_at: string;
    coverage_kind: string;
    completeness: string;
    coverage_bounds?: Record<string, number> | null;
    payload: { objects?: SpatialObject[] };
    duplicate_review_id?: string | null;
    duplicate_resolution?: string | null;
  };
  commit: null | {
    status: string;
    receipt_id?: string | null;
    receipt?: { observation_id?: string } | null;
    failure_code?: string | null;
  };
};
type ReviewDraft = {
  captured_at: string;
  coverage_kind: string;
  completeness: string;
  bounds: { x: number; y: number; width: number; height: number };
  objects: Array<SpatialObject & { identity_choice: string }>;
};

const props = defineProps<{
  user: { name: string; email: string };
  activePlayer: { id: string; name: string; kingdomNumber: number | null };
  reconciliation: Reconciliation;
  canManageEvidence: boolean;
  evidence: EvidenceItem[];
}>();

const { t, formatDate, formatNumber } = useLocale();
const selectedRevision = ref(
  props.reconciliation.revision?.id ?? props.reconciliation.revisions?.[0]?.id ?? '',
);
const selectedAlliance = ref(props.reconciliation.alliance?.id ?? '');
const selectedObservation = ref(props.reconciliation.observation?.id ?? '');
const filter = ref('all');
const duplicateJustification = reactive<Record<string, string>>({});
const invalidationReason = ref('');

const upload = useForm<{
  kingdom_id: string;
  map_dataset_id: string;
  map_dataset_checksum: string;
  evidence: File | null;
}>({
  kingdom_id: props.reconciliation.plan?.kingdom_id ?? '',
  map_dataset_id: props.reconciliation.revision?.map_dataset_id ?? '',
  map_dataset_checksum: props.reconciliation.revision?.map_dataset_checksum ?? '',
  evidence: null,
});

function navigate(): void {
  router.get(
    `/territory/${props.reconciliation.plan.id}/reconciliation`,
    {
      revision: selectedRevision.value || undefined,
      alliance: selectedAlliance.value || undefined,
      observation: selectedObservation.value || undefined,
    },
    { preserveScroll: true, preserveState: false },
  );
}

function statusLabel(status: string): string {
  const labels: Record<string, string> = {
    in_position: 'territory.statusInPosition',
    out_of_position: 'territory.statusOutOfPosition',
    missing: 'territory.statusMissing',
    not_observed: 'territory.statusNotObserved',
    unexpected: 'territory.statusUnexpected',
    ambiguous: 'territory.statusAmbiguous',
    identity_ambiguous: 'territory.statusIdentityAmbiguous',
    identity_unresolved: 'territory.statusIdentityUnresolved',
    unchanged: 'territory.statusUnchanged',
    moved: 'territory.statusMoved',
  };
  return t(labels[status] ?? 'territory.statusUnexpected');
}

function coordinate(object: SpatialObject | null): string {
  return object ? `X:${formatNumber(object.x)} Y:${formatNumber(object.y)}` : '—';
}

function governorName(object: SpatialObject): string {
  return (
    object.observed_label ||
    object.external_player_name ||
    object.plan_local_identity ||
    object.player_id ||
    object.key
  );
}

const observationIsStale = computed(() => props.reconciliation.freshness?.state === 'stale');

const visibleGovernors = computed(() =>
  (props.reconciliation.governors ?? []).filter((row) => {
    if (filter.value === 'all') return true;
    if (filter.value === 'out') return row.status === 'out_of_position';
    if (filter.value === 'missing') return row.status === 'missing';
    if (filter.value === 'not_observed') return row.status === 'not_observed';
    if (filter.value === 'unexpected') {
      return ['identity_ambiguous', 'identity_unresolved'].includes(row.status);
    }
    if (filter.value === 'coverage') {
      return row.coverage_delta === 'lost_coverage' || row.coverage_delta === 'gained_coverage';
    }
    if (filter.value === 'uncertain') {
      return (
        observationIsStale.value ||
        ['identity_ambiguous', 'identity_unresolved', 'not_observed'].includes(row.status)
      );
    }
    return false;
  }),
);

const visibleStructures = computed(() =>
  (props.reconciliation.structures ?? []).filter((row) => {
    if (filter.value === 'all') return true;
    if (filter.value === 'structures') return row.status !== 'unchanged';
    if (filter.value === 'missing') return row.status === 'missing';
    if (filter.value === 'not_observed') return row.status === 'not_observed';
    if (filter.value === 'uncertain') {
      return observationIsStale.value || ['ambiguous', 'not_observed'].includes(row.status);
    }
    return false;
  }),
);

const visibleUnexpected = computed(() =>
  (props.reconciliation.unexpected ?? []).filter((object) => {
    if (filter.value === 'all' || filter.value === 'unexpected') return true;
    if (filter.value === 'uncertain') {
      return (
        observationIsStale.value ||
        ['identity_ambiguous', 'identity_unresolved'].includes(object.status)
      );
    }
    return false;
  }),
);

const plannedGovernorOptions = computed(() =>
  (props.reconciliation.planned_objects ?? [])
    .filter((object) => object.type === 'governor_city')
    .map((object) => ({
      value: object.player_id
        ? `player:${object.player_id}`
        : `local:${object.external_player_name ?? object.key}`,
      label: governorName(object),
    })),
);

function draftFromEvidence(item: EvidenceItem): ReviewDraft {
  const reviewed = item.review?.payload?.objects;
  const objects = (
    reviewed && reviewed.length
      ? reviewed
      : item.candidates.map((candidate) => {
          let parsed: { x?: number; y?: number; label?: string | null } = {};
          try {
            parsed = JSON.parse(candidate.normalized) as {
              x?: number;
              y?: number;
              label?: string | null;
            };
          } catch {
            parsed = {};
          }
          const type = candidate.field.replace('_coordinate', '');
          return {
            key: `${type}-${candidate.ordinal + 1}`,
            type,
            x: Number(parsed.x ?? 0),
            y: Number(parsed.y ?? 0),
            observed_label: parsed.label ?? null,
            identity_state: 'unresolved',
            player_id: null,
            plan_local_identity: null,
            confidence: candidate.confidence,
          };
        })
  ).map((object) => ({
    ...object,
    identity_choice: object.player_id
      ? `player:${object.player_id}`
      : object.plan_local_identity
        ? `local:${object.plan_local_identity}`
        : object.identity_state === 'ambiguous'
          ? 'ambiguous'
          : 'unresolved',
  }));
  const bounds = item.review?.coverage_bounds;
  return {
    captured_at: (item.review?.captured_at ?? item.created_at ?? new Date().toISOString()).slice(
      0,
      16,
    ),
    coverage_kind: item.review?.coverage_kind ?? 'partial_region',
    completeness: item.review?.completeness ?? 'partial',
    bounds: {
      x: Number(bounds?.x ?? 0),
      y: Number(bounds?.y ?? 0),
      width: Number(bounds?.width ?? 1),
      height: Number(bounds?.height ?? 1),
    },
    objects,
  };
}

const reviewDrafts = reactive<Record<string, ReviewDraft>>(
  Object.fromEntries(props.evidence.map((item) => [item.id, draftFromEvidence(item)])),
);

function reviewDraft(item: EvidenceItem): ReviewDraft {
  return reviewDrafts[item.id] ?? draftFromEvidence(item);
}

function applyIdentity(object: SpatialObject & { identity_choice: string }): void {
  object.player_id = null;
  object.plan_local_identity = null;
  if (object.identity_choice.startsWith('player:')) {
    object.identity_state = 'resolved_player';
    object.player_id = object.identity_choice.slice(7);
  } else if (object.identity_choice.startsWith('local:')) {
    object.identity_state = 'resolved_plan_local';
    object.plan_local_identity = object.identity_choice.slice(6);
  } else {
    object.identity_state = object.identity_choice === 'ambiguous' ? 'ambiguous' : 'unresolved';
  }
}

function reviewObjectPayload(object: SpatialObject & { identity_choice: string }): SpatialObject {
  return {
    key: object.key,
    type: object.type,
    x: object.x,
    y: object.y,
    player_id: object.player_id ?? null,
    external_player_name: object.external_player_name ?? null,
    plan_local_identity: object.plan_local_identity ?? null,
    observed_label: object.observed_label ?? null,
    identity_state: object.identity_state ?? 'unresolved',
    confidence: object.confidence ?? null,
  };
}

function submitUpload(): void {
  upload.kingdom_id = props.reconciliation.plan.kingdom_id;
  upload.map_dataset_id = props.reconciliation.revision?.map_dataset_id ?? '';
  upload.map_dataset_checksum = props.reconciliation.revision?.map_dataset_checksum ?? '';
  upload.post('/territory-observations/evidence', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => upload.reset('evidence'),
  });
}

function submitReview(item: EvidenceItem): void {
  const draft = reviewDrafts[item.id];
  if (!draft) return;
  if (['complete_hive', 'complete_visible_region'].includes(draft.coverage_kind)) {
    draft.completeness = 'complete';
  }
  draft.objects.forEach(applyIdentity);
  router.post(
    `/territory-observations/evidence/${item.id}/review`,
    {
      captured_at: new Date(draft.captured_at).toISOString(),
      coverage_kind: draft.coverage_kind,
      completeness: draft.completeness,
      coverage_bounds: draft.coverage_kind === 'complete_visible_region' ? draft.bounds : null,
      objects: draft.objects.map(reviewObjectPayload),
    },
    { preserveScroll: true },
  );
}

function resolveDuplicate(item: EvidenceItem): void {
  if (!item.review) return;
  router.post(
    `/territory-observations/reviews/${item.review.id}/resolve-duplicate`,
    { justification: duplicateJustification[item.id] ?? '' },
    { preserveScroll: true },
  );
}

function commit(item: EvidenceItem): void {
  if (!item.review) return;
  router.post(
    `/territory-observations/reviews/${item.review.id}/commit`,
    {},
    { preserveScroll: true },
  );
}

function retry(item: EvidenceItem): void {
  router.post(`/territory-observations/evidence/${item.id}/retry`, {}, { preserveScroll: true });
}

function deleteEvidence(item: EvidenceItem): void {
  router.delete(`/territory-observations/evidence/${item.id}`, { preserveScroll: true });
}

function invalidateObservation(): void {
  const observation = props.reconciliation.observation;
  if (!observation || invalidationReason.value.trim().length < 8) return;
  router.post(
    `/territory-observations/${observation.id}/invalidate`,
    { reason: invalidationReason.value },
    { preserveScroll: true },
  );
}

function freshnessLabel(): string {
  const state = props.reconciliation.freshness?.state;
  return state === 'fresh'
    ? t('territory.freshnessFresh')
    : state === 'aging'
      ? t('territory.freshnessAging')
      : t('territory.freshnessStale');
}
</script>

<template>
  <Head :title="`${reconciliation.plan.name} · ${t('territory.reconciliationTitle')}`" />
  <AppLayout :user="user">
    <section class="ks-surface overflow-hidden p-5 lg:p-7">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="max-w-3xl">
          <p class="ks-kicker">{{ t('territory.eyebrow') }}</p>
          <h1 class="ks-display mt-2 text-3xl font-semibold">
            {{ t('territory.reconciliationTitle') }}
          </h1>
          <p class="mt-2 text-sm text-[var(--ks-muted)]">
            {{ t('territory.reconciliationSubtitle') }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            :href="`/territory/${reconciliation.plan.id}`"
            class="ks-command-link"
            data-variant="secondary"
          >
            {{ t('territory.compareBackToEditor') }}
          </Link>
          <Link href="/territory" class="ks-command-link" data-variant="secondary">
            {{ t('territory.backToPlans') }}
          </Link>
        </div>
      </div>

      <div class="mt-5 grid gap-3 md:grid-cols-3">
        <label class="text-sm font-semibold">
          {{ t('territory.publishedRevisionLabel') }}
          <select v-model="selectedRevision" class="ks-input mt-2 w-full" @change="navigate">
            <option
              v-for="revision in reconciliation.revisions ?? []"
              :key="revision.id"
              :value="revision.id"
            >
              #{{ revision.revision_number }} · {{ formatDate(revision.published_at) }}
            </option>
          </select>
        </label>
        <label
          v-if="(reconciliation.alliance_options?.length ?? 0) > 1"
          class="text-sm font-semibold"
        >
          {{ t('territory.chooseAllianceObservation') }}
          <select
            v-model="selectedAlliance"
            class="ks-input mt-2 w-full"
            @change="
              selectedObservation = '';
              navigate();
            "
          >
            <option value="">—</option>
            <option
              v-for="alliance in reconciliation.alliance_options"
              :key="alliance.id"
              :value="alliance.id"
            >
              {{ alliance.name }}
            </option>
          </select>
        </label>
        <label v-if="reconciliation.alliance" class="text-sm font-semibold">
          {{ t('territory.observedSnapshotLabel') }}
          <select v-model="selectedObservation" class="ks-input mt-2 w-full" @change="navigate">
            <option value="">{{ t('territory.latestObservation') }}</option>
            <option
              v-for="observation in reconciliation.observations ?? []"
              :key="observation.id"
              :value="observation.id"
            >
              {{ formatDate(observation.captured_at) }} · {{ observation.coverage_kind }}
            </option>
          </select>
        </label>
      </div>
    </section>

    <section
      v-if="reconciliation.state === 'no_published_revision'"
      class="ks-surface mt-5 p-6"
      role="status"
    >
      {{ t('territory.noPublishedRevision') }}
    </section>
    <section
      v-else-if="reconciliation.state === 'alliance_required'"
      class="ks-surface mt-5 p-6"
      role="status"
    >
      {{ t('territory.allianceRequired') }}
    </section>
    <section
      v-else-if="reconciliation.state === 'dataset_incompatible'"
      class="ks-surface mt-5 p-6"
      role="alert"
    >
      {{ t('territory.datasetIncompatible') }}
    </section>

    <template
      v-if="
        reconciliation.state === 'ready' && reconciliation.summary && reconciliation.observation
      "
    >
      <section
        class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
        :aria-label="t('territory.reconciliationSummaryAria')"
      >
        <article class="ks-surface p-4">
          <p class="text-xs text-[var(--ks-muted)]">{{ t('territory.inPosition') }}</p>
          <p class="mt-2 text-3xl font-semibold">
            {{ formatNumber(reconciliation.summary.in_position) }}
          </p>
        </article>
        <article class="ks-surface p-4">
          <p class="text-xs text-[var(--ks-muted)]">{{ t('territory.outOfPosition') }}</p>
          <p class="mt-2 text-3xl font-semibold">
            {{ formatNumber(reconciliation.summary.out_of_position) }}
          </p>
        </article>
        <article class="ks-surface p-4">
          <p class="text-xs text-[var(--ks-muted)]">
            {{ t('territory.missing') }} / {{ t('territory.notObserved') }}
          </p>
          <p class="mt-2 text-3xl font-semibold">
            {{ formatNumber(reconciliation.summary.missing) }} /
            {{ formatNumber(reconciliation.summary.not_observed) }}
          </p>
        </article>
        <article class="ks-surface p-4">
          <p class="text-xs text-[var(--ks-muted)]">{{ t('territory.lostCoverage') }}</p>
          <p class="mt-2 text-3xl font-semibold">
            {{ formatNumber(reconciliation.summary.lost_coverage) }}
          </p>
        </article>
      </section>

      <section class="ks-surface mt-5 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="ks-kicker">{{ t('territory.observedSnapshotLabel') }}</p>
            <h2 class="mt-1 text-lg font-semibold">{{ freshnessLabel() }}</h2>
            <p class="text-sm text-[var(--ks-muted)]">
              {{
                t('territory.observedAt', {
                  date: formatDate(reconciliation.observation.captured_at),
                })
              }}
              · {{ reconciliation.observation.coverage_kind }} ·
              {{ reconciliation.observation.completeness }}
            </p>
          </div>
          <span class="ks-badge">{{ reconciliation.compatibility }}</span>
        </div>
        <p class="mt-3 rounded-lg border p-3 text-sm" role="status">
          {{ t('territory.exactPlanRemains') }}
        </p>
        <p
          v-if="reconciliation.freshness?.state === 'stale'"
          class="mt-3 rounded-lg border p-3 text-sm"
          role="alert"
        >
          {{ t('territory.freshnessStale') }}
        </p>
      </section>

      <section class="ks-surface mt-5 p-5" :aria-label="t('territory.observedMapOverlay')">
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-lg font-semibold">{{ t('territory.observedMapOverlay') }}</h2>
          <p class="text-xs text-[var(--ks-muted)]">
            ○ {{ t('territory.plannedMarker') }} · ● {{ t('territory.observedMarker') }}
          </p>
        </div>
        <div class="mt-4 overflow-hidden rounded-xl border bg-[var(--ks-panel)]">
          <svg
            viewBox="0 0 1200 1200"
            class="aspect-square max-h-[560px] w-full"
            role="img"
            :aria-label="t('territory.observedMapOverlay')"
          >
            <g
              v-for="row in [
                ...(reconciliation.governors ?? []),
                ...(reconciliation.structures ?? []),
              ]"
              :key="row.planned.key"
            >
              <line
                v-if="row.observed"
                :x1="row.planned.x"
                :y1="1200 - row.planned.y"
                :x2="row.observed.x"
                :y2="1200 - row.observed.y"
                stroke="currentColor"
                stroke-width="1"
                opacity="0.3"
              />
              <circle
                :cx="row.planned.x"
                :cy="1200 - row.planned.y"
                r="7"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              />
              <circle
                v-if="row.observed"
                :cx="row.observed.x"
                :cy="1200 - row.observed.y"
                r="4"
                fill="currentColor"
              />
            </g>
          </svg>
        </div>
      </section>

      <section class="ks-surface mt-5 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-lg font-semibold">{{ t('territory.discrepancyList') }}</h2>
          <select
            v-model="filter"
            class="ks-input min-w-52"
            :aria-label="t('territory.filterRowsAria')"
          >
            <option value="all">{{ t('territory.filterAll') }}</option>
            <option value="out">{{ t('territory.filterOutOfPosition') }}</option>
            <option value="missing">{{ t('territory.filterMissing') }}</option>
            <option value="not_observed">{{ t('territory.filterNotObserved') }}</option>
            <option value="unexpected">{{ t('territory.filterUnexpected') }}</option>
            <option value="coverage">{{ t('territory.filterCoverage') }}</option>
            <option value="structures">{{ t('territory.filterStructures') }}</option>
            <option value="uncertain">{{ t('territory.filterUncertain') }}</option>
          </select>
        </div>
        <div class="mt-4 overflow-x-auto">
          <table class="w-full min-w-[760px] text-left text-sm">
            <thead>
              <tr class="border-b text-xs text-[var(--ks-muted)]">
                <th class="p-2">{{ t('territory.governor') }}</th>
                <th class="p-2">{{ t('territory.plannedCoordinate') }}</th>
                <th class="p-2">{{ t('territory.observedCoordinate') }}</th>
                <th class="p-2">{{ t('territory.delta') }}</th>
                <th class="p-2">{{ t('territory.coverageDelta') }}</th>
                <th class="p-2">{{ t('territory.state') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in visibleGovernors"
                :key="row.planned.key"
                class="border-b last:border-0"
              >
                <td class="p-2 font-semibold">{{ governorName(row.planned) }}</td>
                <td class="p-2 font-mono">{{ coordinate(row.planned) }}</td>
                <td class="p-2 font-mono">{{ coordinate(row.observed) }}</td>
                <td class="p-2">
                  {{
                    row.distance_tiles === null ? '—' : `${formatNumber(row.distance_tiles)} tiles`
                  }}
                </td>
                <td class="p-2">{{ row.coverage_delta }}</td>
                <td class="p-2">{{ statusLabel(row.status) }}</td>
              </tr>
              <tr
                v-for="row in visibleStructures"
                :key="`structure-${row.planned.key}`"
                class="border-b last:border-0"
              >
                <td class="p-2 font-semibold">{{ t(`territory.types.${row.planned.type}`) }}</td>
                <td class="p-2 font-mono">{{ coordinate(row.planned) }}</td>
                <td class="p-2 font-mono">{{ coordinate(row.observed) }}</td>
                <td class="p-2">
                  {{
                    row.distance_tiles === null ? '—' : `${formatNumber(row.distance_tiles)} tiles`
                  }}
                </td>
                <td class="p-2">—</td>
                <td class="p-2">{{ statusLabel(row.status) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="visibleUnexpected.length > 0" class="mt-5 grid gap-2 md:grid-cols-2">
          <article
            v-for="object in visibleUnexpected"
            :key="object.key"
            class="rounded-lg border p-3"
          >
            <p class="font-semibold">{{ object.observed_label || object.key }}</p>
            <p class="text-sm text-[var(--ks-muted)]">
              {{ coordinate(object) }} · {{ statusLabel(object.status) }}
            </p>
          </article>
        </div>
      </section>
    </template>

    <section
      v-if="reconciliation.state === 'no_observation'"
      class="ks-surface mt-5 p-6"
      role="status"
    >
      {{ t('territory.noObservation') }}
    </section>

    <section
      v-if="canManageEvidence && reconciliation.alliance && reconciliation.revision"
      class="ks-surface mt-5 p-5"
    >
      <p class="ks-kicker">{{ t('territory.observationProvenance') }}</p>
      <h2 class="mt-1 text-xl font-semibold">{{ t('territory.addObservedEvidence') }}</h2>
      <p class="mt-2 max-w-3xl text-sm text-[var(--ks-muted)]">{{ t('territory.evidenceHelp') }}</p>
      <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="submitUpload">
        <label class="min-w-64 flex-1 text-sm font-semibold">
          {{ t('territory.screenshotFile') }}
          <input
            type="file"
            accept="image/png,image/jpeg,image/webp"
            class="ks-input mt-2 w-full"
            required
            @change="upload.evidence = ($event.target as HTMLInputElement).files?.[0] ?? null"
          />
        </label>
        <AppButton type="submit" :busy="upload.processing" :disabled="!upload.evidence">
          {{ t('territory.uploadAndClassify') }}
        </AppButton>
      </form>
    </section>

    <section
      v-if="canManageEvidence && evidence.length"
      class="mt-5 space-y-4"
      :aria-label="t('territory.observationProvenance')"
    >
      <article v-for="item in evidence" :key="item.id" class="ks-surface p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="ks-kicker">{{ t('territory.evidenceStatus') }}</p>
            <h3 class="mt-1 text-lg font-semibold">{{ item.status }} · {{ item.detected_kind }}</h3>
            <p v-if="item.created_at" class="text-xs text-[var(--ks-muted)]">
              {{ formatDate(item.created_at) }}
            </p>
          </div>
          <div class="flex gap-2">
            <a
              :href="`/territory-observations/evidence/${item.id}/image`"
              class="ks-command-link"
              target="_blank"
              rel="noopener"
            >
              {{ t('territory.viewScreenshot') }}
            </a>
            <button
              v-if="!['committed', 'deleted'].includes(item.status)"
              type="button"
              class="ks-command-link"
              @click="retry(item)"
            >
              {{ t('territory.retryProcessing') }}
            </button>
            <button
              type="button"
              class="ks-command-link"
              data-variant="danger"
              @click="deleteEvidence(item)"
            >
              {{ t('territory.deleteEvidence') }}
            </button>
          </div>
        </div>

        <div v-if="item.candidates.length" class="mt-4">
          <p class="text-sm font-semibold">{{ t('territory.extractedCandidates') }}</p>
          <ul class="mt-2 grid gap-2 md:grid-cols-2">
            <li
              v-for="candidate in item.candidates"
              :key="`${candidate.field}-${candidate.ordinal}`"
              class="rounded-lg border p-3 text-sm"
            >
              <span class="font-semibold">{{ candidate.field }}</span>
              <span class="ml-2 text-[var(--ks-muted)]">
                {{ formatNumber(candidate.confidence, { style: 'percent' }) }}
              </span>
              <p class="mt-1 font-mono text-xs">{{ candidate.raw }}</p>
            </li>
          </ul>
        </div>

        <form
          v-if="!['committed', 'deleted'].includes(item.status)"
          class="mt-5 space-y-4"
          @submit.prevent="submitReview(item)"
        >
          <h4 class="font-semibold">{{ t('territory.reviewObservation') }}</h4>
          <div class="grid gap-3 md:grid-cols-3">
            <label class="text-sm font-semibold">
              {{ t('territory.capturedAt') }}
              <input
                v-model="reviewDraft(item).captured_at"
                type="datetime-local"
                class="ks-input mt-2 w-full"
                required
              />
            </label>
            <label class="text-sm font-semibold">
              {{ t('territory.coverageKind') }}
              <select v-model="reviewDraft(item).coverage_kind" class="ks-input mt-2 w-full">
                <option value="complete_hive">{{ t('territory.coverageCompleteHive') }}</option>
                <option value="complete_visible_region">
                  {{ t('territory.coverageCompleteVisibleRegion') }}
                </option>
                <option value="partial_region">{{ t('territory.coveragePartialRegion') }}</option>
                <option value="single_object">{{ t('territory.coverageSingleObject') }}</option>
                <option value="unknown_coverage">{{ t('territory.coverageUnknown') }}</option>
              </select>
            </label>
            <label class="text-sm font-semibold">
              {{ t('territory.completeness') }}
              <select v-model="reviewDraft(item).completeness" class="ks-input mt-2 w-full">
                <option value="complete">{{ t('territory.completenessComplete') }}</option>
                <option value="partial">{{ t('territory.completenessPartial') }}</option>
                <option value="unknown">{{ t('territory.completenessUnknown') }}</option>
              </select>
            </label>
          </div>
          <div
            v-if="reviewDraft(item).coverage_kind === 'complete_visible_region'"
            class="grid gap-3 sm:grid-cols-4"
          >
            <label
              v-for="field in ['x', 'y', 'width', 'height']"
              :key="field"
              class="text-sm font-semibold"
            >
              {{
                t(
                  `territory.${field === 'x' ? 'coordinateX' : field === 'y' ? 'coordinateY' : field}`,
                )
              }}
              <input
                v-model.number="reviewDraft(item).bounds[field as keyof ReviewDraft['bounds']]"
                type="number"
                class="ks-input mt-2 w-full"
              />
            </label>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-left text-sm">
              <thead>
                <tr class="border-b text-xs text-[var(--ks-muted)]">
                  <th class="p-2">{{ t('territory.object') }}</th>
                  <th class="p-2">X</th>
                  <th class="p-2">Y</th>
                  <th class="p-2">{{ t('territory.identity') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="object in reviewDraft(item).objects" :key="object.key" class="border-b">
                  <td class="p-2">
                    <span class="font-semibold">{{ t(`territory.types.${object.type}`) }}</span>
                    <input
                      v-model="object.observed_label"
                      class="ks-input mt-1 w-full"
                      :placeholder="object.key"
                    />
                  </td>
                  <td class="p-2">
                    <input v-model.number="object.x" type="number" class="ks-input w-24" />
                  </td>
                  <td class="p-2">
                    <input v-model.number="object.y" type="number" class="ks-input w-24" />
                  </td>
                  <td class="p-2">
                    <select
                      v-if="object.type === 'governor_city'"
                      v-model="object.identity_choice"
                      class="ks-input w-full"
                      @change="applyIdentity(object)"
                    >
                      <option value="unresolved">{{ t('territory.unresolvedIdentity') }}</option>
                      <option value="ambiguous">{{ t('territory.ambiguousIdentity') }}</option>
                      <option
                        v-for="option in plannedGovernorOptions"
                        :key="option.value"
                        :value="option.value"
                      >
                        {{ option.label }}
                      </option>
                    </select>
                    <span v-else class="text-[var(--ks-muted)]">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <AppButton type="submit">{{ t('territory.reviewAndApprove') }}</AppButton>
        </form>

        <div v-if="item.review?.status === 'duplicate_blocked'" class="mt-4 rounded-lg border p-4">
          <p class="font-semibold">{{ t('territory.semanticDuplicate') }}</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <input
              v-model="duplicateJustification[item.id]"
              class="ks-input min-w-72 flex-1"
              :placeholder="t('territory.duplicateJustification')"
            />
            <AppButton type="button" @click="resolveDuplicate(item)">
              {{ t('territory.resolveDuplicate') }}
            </AppButton>
          </div>
        </div>
        <div
          v-if="item.review?.status === 'approved' && item.commit?.status !== 'succeeded'"
          class="mt-4"
        >
          <AppButton type="button" @click="commit(item)">
            {{ t('territory.commitObservation') }}
          </AppButton>
        </div>
        <div v-if="item.commit?.status === 'succeeded'" class="mt-4 rounded-lg border p-4">
          <p class="font-semibold">{{ t('territory.committedObservation') }}</p>
          <p class="mt-1 text-xs text-[var(--ks-muted)]">
            {{ t('territory.evidenceReceipt') }}: {{ item.commit.receipt_id }}
          </p>
        </div>
      </article>
    </section>
    <section
      v-else-if="canManageEvidence"
      class="ks-surface mt-5 p-5 text-sm text-[var(--ks-muted)]"
    >
      {{ t('territory.noEvidence') }}
    </section>

    <section v-if="canManageEvidence && reconciliation.observation" class="ks-surface mt-5 p-5">
      <h2 class="text-lg font-semibold">{{ t('territory.invalidateObservation') }}</h2>
      <div class="mt-3 flex flex-wrap gap-2">
        <input
          v-model="invalidationReason"
          class="ks-input min-w-72 flex-1"
          :placeholder="t('territory.invalidationReason')"
        />
        <AppButton
          type="button"
          :disabled="invalidationReason.trim().length < 8"
          @click="invalidateObservation"
        >
          {{ t('territory.invalidateObservation') }}
        </AppButton>
      </div>
    </section>
  </AppLayout>
</template>
