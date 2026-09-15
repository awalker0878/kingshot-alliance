<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import ActionNotice from '@/components/ui/ActionNotice.vue';
import AppButton from '@/components/ui/AppButton.vue';
import {
  alignObjectsAtomic,
  distributeObjectsAtomic,
  materializeHiveProposal,
  objectIsLocked,
  setObjectCoordinatesAtomic,
  type TerritoryAlignment,
  type TerritoryCommandResult,
  type TerritoryDistribution,
} from '@/features/territory-planner/engine/commands';
import type {
  MapData,
  PlanAlliance,
  PlanGroup,
  PlanObject,
  PlanningPreferences,
} from '@/features/territory-planner/engine/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type PlanSummary = {
  id: string;
  name: string;
  status: string;
  revision: number;
  map_dataset_id: string;
  map_dataset_checksum: string;
  planning_preferences: PlanningPreferences;
  can_manage: boolean;
};
type Revision = {
  id: string;
  revision_number: number;
  published_at: string | null;
};
type Annotation = {
  key: string;
  kind: 'label' | 'line' | 'arrow' | 'rectangle';
  alliance_key: string | null;
  text: string | null;
  x: number;
  y: number;
  target_x: number | null;
  target_y: number | null;
  sort_order: number;
};
type HiveTemplate = {
  id: string;
  name: string;
  style: 'swirl' | 'banner_pad';
  city_count: number;
  spacing: number;
  planning_preferences: PlanningPreferences;
  map_dataset_id: string;
  map_dataset_checksum: string;
};
type Rendition = {
  id: string;
  territory_plan_revision_id: string;
  scope: string;
  media_type: string;
  content_checksum: string;
  content_bytes: number;
  metadata: Record<string, unknown>;
  created_at: string | null;
};
type TerritoryProp = {
  plan: PlanSummary;
  alliances: PlanAlliance[];
  groups: PlanGroup[];
  objects: PlanObject[];
  map: {
    id: string;
    checksum: string;
    source_label: string;
    source_uri: string | null;
    confidence: string;
    observed_at: string;
    data: MapData;
  };
  revisions: Revision[];
  layout_checksum: string;
  annotations: Annotation[];
  hive_templates: HiveTemplate[];
  renditions: Rendition[];
};
type CoordinatePreviewRow = {
  key: string;
  x: number;
  y: number;
};
type MutationReceipt = {
  revision: number;
  status: string;
  layout_checksum: string | null;
  snapshot: {
    alliances?: PlanAlliance[];
    groups?: PlanGroup[];
    objects?: PlanObject[];
    annotations?: Annotation[];
    plan?: { planning_preferences?: PlanningPreferences };
  } | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  activePlayer: { id: string; name: string; kingdomNumber: number | null };
  territory: TerritoryProp;
}>();

const { locale } = useLocale();
const cloneJson = <T,>(value: T): T => JSON.parse(JSON.stringify(value)) as T;
const alliances = ref<PlanAlliance[]>(cloneJson(props.territory.alliances));
const groups = ref<PlanGroup[]>(cloneJson(props.territory.groups));
const objects = ref<PlanObject[]>(cloneJson(props.territory.objects));
const preferences = ref<PlanningPreferences>(
  cloneJson(props.territory.plan.planning_preferences ?? {}),
);
const annotations = ref<Annotation[]>(cloneJson(props.territory.annotations ?? []));
const templates = ref<HiveTemplate[]>(cloneJson(props.territory.hive_templates ?? []));
const renditions = ref<Rendition[]>(cloneJson(props.territory.renditions ?? []));
const selectedKeys = ref<string[]>([]);
const revision = ref(props.territory.plan.revision);
const status = ref(props.territory.plan.status);
const layoutChecksum = ref(props.territory.layout_checksum);
const baselineLayout = ref(layoutJson());
const notice = ref<{ tone: 'success' | 'warning' | 'danger' | 'info'; message: string } | null>(
  null,
);
const busy = ref(false);
const csvText = ref('');
const coordinatePreview = ref<CoordinatePreviewRow[]>([]);
const templateName = ref('');
const templateStyle = ref<'swirl' | 'banner_pad'>('swirl');
const templateCityCount = ref(25);
const templateSpacing = ref(1);
const templateId = ref('');
const templateAllianceKey = ref(alliances.value[0]?.key ?? '');
const templateCenterX = ref(
  Math.round(props.territory.map.data.bounds.x + props.territory.map.data.bounds.width / 2),
);
const templateCenterY = ref(
  Math.round(props.territory.map.data.bounds.y + props.territory.map.data.bounds.height / 2),
);

const dirty = computed(() => layoutJson() !== baselineLayout.value);
const selectedCount = computed(() => selectedKeys.value.length);
const latestPublishedRevision = computed(
  () =>
    [...props.territory.revisions].sort((a, b) => b.revision_number - a.revision_number)[0] ?? null,
);

function layoutJson(): string {
  return JSON.stringify({
    alliances: alliances.value,
    groups: groups.value,
    objects: objects.value,
    preferences: preferences.value,
  });
}

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function jsonRequest(
  url: string,
  method: string,
  body?: unknown,
): Promise<Record<string, unknown>> {
  const response = await fetch(url, {
    method,
    cache: 'no-store',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });
  const payload = (await response.json().catch(() => ({}))) as Record<string, unknown>;
  if (!response.ok) {
    const errors = payload.errors as Record<string, string[] | string> | undefined;
    const first = errors ? Object.values(errors).flat()[0] : null;
    throw new Error(
      typeof first === 'string'
        ? first
        : typeof payload.message === 'string'
          ? payload.message
          : 'The Territory request failed.',
    );
  }
  return payload;
}

function editable(object: PlanObject): boolean {
  const alliance = alliances.value.find((candidate) => candidate.key === object.alliance_key);
  return props.territory.plan.can_manage && !objectIsLocked(object, alliance?.locked ?? false);
}

function applyCommand(result: TerritoryCommandResult): void {
  if (!result.ok) {
    notice.value = {
      tone: 'warning',
      message:
        result.reason === 'locked_selection'
          ? `The operation was refused because ${result.blockedKeys.length} selected object(s) are locked or read-only.`
          : 'Select at least one object first.',
    };
    return;
  }
  objects.value = result.objects;
  notice.value = {
    tone: 'info',
    message: `Updated ${result.affectedKeys.length} object(s) atomically.`,
  };
}

function align(alignment: TerritoryAlignment): void {
  applyCommand(
    alignObjectsAtomic(
      props.territory.map.data,
      objects.value,
      selectedKeys.value,
      alignment,
      editable,
    ),
  );
}

function distribute(direction: TerritoryDistribution): void {
  applyCommand(
    distributeObjectsAtomic(
      props.territory.map.data,
      objects.value,
      selectedKeys.value,
      direction,
      editable,
    ),
  );
}

function selectAllEditable(): void {
  selectedKeys.value = objects.value.filter(editable).map((object) => object.key);
}

async function saveLayout(): Promise<void> {
  if (!props.territory.plan.can_manage || !dirty.value) return;
  busy.value = true;
  try {
    const payload = await jsonRequest(`/territory/${props.territory.plan.id}`, 'PUT', {
      expected_revision: revision.value,
      mutation_id: crypto.randomUUID(),
      alliances: alliances.value,
      groups: groups.value,
      objects: objects.value,
      planning_preferences: preferences.value,
    });
    const receipt = payload.receipt as MutationReceipt;
    revision.value = receipt.revision;
    status.value = receipt.status;
    if (receipt.layout_checksum) layoutChecksum.value = receipt.layout_checksum;
    if (receipt.snapshot?.alliances) alliances.value = cloneJson(receipt.snapshot.alliances);
    if (receipt.snapshot?.groups) groups.value = cloneJson(receipt.snapshot.groups);
    if (receipt.snapshot?.objects) objects.value = cloneJson(receipt.snapshot.objects);
    if (receipt.snapshot?.plan?.planning_preferences)
      preferences.value = cloneJson(receipt.snapshot.plan.planning_preferences);
    baselineLayout.value = layoutJson();
    notice.value = { tone: 'success', message: `Layout saved as revision ${revision.value}.` };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : 'Save failed.',
    };
  } finally {
    busy.value = false;
  }
}

async function previewCsv(): Promise<void> {
  if (!csvText.value.trim()) return;
  busy.value = true;
  try {
    const payload = await jsonRequest(
      `/territory/${props.territory.plan.id}/coordinates/preview`,
      'POST',
      { csv: csvText.value },
    );
    const rows = Array.isArray(payload.rows) ? payload.rows : [];
    coordinatePreview.value = rows
      .filter(
        (row): row is Record<string, unknown> =>
          typeof row === 'object' && row !== null && !Array.isArray(row),
      )
      .map((row) => ({ key: String(row.key ?? ''), x: Number(row.x), y: Number(row.y) }));
    notice.value = {
      tone: 'info',
      message: `Validated ${coordinatePreview.value.length} CSV coordinate row(s).`,
    };
  } catch (error) {
    coordinatePreview.value = [];
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : 'CSV preview failed.',
    };
  } finally {
    busy.value = false;
  }
}

function applyCsvCoordinates(): void {
  if (!coordinatePreview.value.length) return;
  try {
    applyCommand(setObjectCoordinatesAtomic(objects.value, coordinatePreview.value, editable));
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : 'CSV coordinates could not be applied.',
    };
  }
}

function addAnnotation(): void {
  const x = Math.round(
    props.territory.map.data.bounds.x + props.territory.map.data.bounds.width / 2,
  );
  const y = Math.round(
    props.territory.map.data.bounds.y + props.territory.map.data.bounds.height / 2,
  );
  annotations.value.push({
    key: `annotation-${crypto.randomUUID()}`,
    kind: 'label',
    alliance_key: null,
    text: 'New label',
    x,
    y,
    target_x: null,
    target_y: null,
    sort_order: annotations.value.length,
  });
}

function normalizeAnnotationTarget(annotation: Annotation): void {
  if (annotation.kind === 'label') {
    annotation.target_x = null;
    annotation.target_y = null;
    return;
  }
  annotation.target_x ??= annotation.x + 5;
  annotation.target_y ??= annotation.y + 5;
}

async function saveAnnotations(): Promise<void> {
  if (!props.territory.plan.can_manage) return;
  busy.value = true;
  try {
    annotations.value.forEach(normalizeAnnotationTarget);
    annotations.value.forEach((annotation, index) => {
      annotation.sort_order = index;
    });
    const payload = await jsonRequest(`/territory/${props.territory.plan.id}/annotations`, 'PUT', {
      expected_revision: revision.value,
      annotations: annotations.value,
    });
    revision.value = Number(payload.revision);
    status.value = 'draft';
    if (typeof payload.layout_checksum === 'string') layoutChecksum.value = payload.layout_checksum;
    const snapshot = payload.snapshot as { annotations?: Annotation[] } | undefined;
    if (snapshot?.annotations) annotations.value = cloneJson(snapshot.annotations);
    notice.value = { tone: 'success', message: `Annotations saved as revision ${revision.value}.` };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : 'Annotation save failed.',
    };
  } finally {
    busy.value = false;
  }
}

async function refreshTemplates(): Promise<void> {
  const payload = await jsonRequest(`/territory/${props.territory.plan.id}/hive-templates`, 'GET');
  templates.value = Array.isArray(payload.templates) ? (payload.templates as HiveTemplate[]) : [];
  if (!templateId.value && templates.value[0]) templateId.value = templates.value[0].id;
}

async function saveHiveTemplate(): Promise<void> {
  if (!templateName.value.trim()) return;
  busy.value = true;
  try {
    const payload = await jsonRequest(
      `/territory/${props.territory.plan.id}/hive-templates`,
      'POST',
      {
        name: templateName.value.trim(),
        style: templateStyle.value,
        city_count: templateCityCount.value,
        spacing: templateSpacing.value,
        planning_preferences: preferences.value,
      },
    );
    const template = payload.template as HiveTemplate;
    templateId.value = template.id;
    await refreshTemplates();
    notice.value = { tone: 'success', message: `Hive template “${template.name}” saved.` };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : 'Template save failed.',
    };
  } finally {
    busy.value = false;
  }
}

async function instantiateHiveTemplate(): Promise<void> {
  if (!templateId.value || !templateAllianceKey.value) return;
  busy.value = true;
  try {
    const payload = await jsonRequest(
      `/territory/${props.territory.plan.id}/hive-templates/${templateId.value}/instantiate`,
      'POST',
      {
        existing_objects: objects.value,
        alliance_key: templateAllianceKey.value,
        center_x: templateCenterX.value,
        center_y: templateCenterY.value,
      },
    );
    if (payload.status !== 'feasible' || !Array.isArray(payload.objects)) {
      const diagnostics = Array.isArray(payload.diagnostics)
        ? payload.diagnostics
            .map((item) =>
              typeof item === 'object' && item !== null
                ? String((item as Record<string, unknown>).message ?? '')
                : '',
            )
            .filter(Boolean)
            .join(' ')
        : '';
      throw new Error(diagnostics || 'The Hive template is not feasible at this location.');
    }
    const additions = materializeHiveProposal(
      payload.objects as PlanObject[],
      objects.value.length,
    );
    const groupKeys = new Set(groups.value.map((group) => group.key));
    for (const groupKey of new Set(
      additions
        .map((object) => object.group_key)
        .filter((value): value is string => Boolean(value)),
    )) {
      if (!groupKeys.has(groupKey)) {
        groups.value.push({ key: groupKey, label: 'Hive template' });
        groupKeys.add(groupKey);
      }
    }
    objects.value.push(...additions);
    selectedKeys.value = additions.map((object) => object.key);
    notice.value = {
      tone: 'success',
      message: `Materialized ${additions.length} Hive template object(s). Save the layout to persist them.`,
    };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : 'Template instantiation failed.',
    };
  } finally {
    busy.value = false;
  }
}

async function refreshRenditions(): Promise<void> {
  const payload = await jsonRequest(`/territory/${props.territory.plan.id}/renditions`, 'GET');
  renditions.value = Array.isArray(payload.renditions) ? (payload.renditions as Rendition[]) : [];
}

function textBase64(value: string): string {
  const bytes = new TextEncoder().encode(value);
  let binary = '';
  for (let offset = 0; offset < bytes.length; offset += 0x8000) {
    binary += String.fromCharCode(
      ...bytes.subarray(offset, Math.min(bytes.length, offset + 0x8000)),
    );
  }
  return btoa(binary);
}

async function persistArtworkRendition(): Promise<void> {
  const published = latestPublishedRevision.value;
  if (!published || status.value !== 'published') {
    notice.value = {
      tone: 'warning',
      message: 'Publish the plan before persisting a visual rendition.',
    };
    return;
  }
  if (dirty.value) {
    notice.value = {
      tone: 'warning',
      message: 'Save and publish layout changes before persisting a rendition.',
    };
    return;
  }
  busy.value = true;
  try {
    const exportModule = await import('@/features/territory-planner/engine/export');
    const artworkRuntime = await import('@/features/territory-planner/engine/artwork-runtime');
    const artworkModule = await import('@/features/territory-planner/engine/artwork');
    const options: { image?: Parameters<typeof exportModule.buildSvg>[4]['image'] } = {};
    const attached = await artworkRuntime.attachExportArtwork(options);
    if (!attached) {
      throw new Error(
        'Authorized Kingshot artwork bytes are not delivered; an artwork-bearing rendition cannot be persisted.',
      );
    }
    const manifest = await artworkModule.loadArtworkManifest();
    const exportedAt = new Date().toISOString();
    const svg = exportModule.buildSvg(
      props.territory.map.data,
      alliances.value,
      objects.value,
      {
        title: props.territory.plan.name,
        mapProfile: props.territory.map.source_label,
        observedAt: props.territory.map.observed_at,
        confidence: props.territory.map.confidence,
        exportedAt,
        mapChecksum: props.territory.map.checksum,
        planRevision: published.revision_number,
        artworkVersion: manifest.registry_version,
        locale: locale.value,
        fontFamily: exportModule.EXPORT_FONT_FAMILY,
      },
      options,
    );
    const payload = await jsonRequest(`/territory/${props.territory.plan.id}/renditions`, 'POST', {
      revision_id: published.id,
      scope: 'world',
      media_type: 'image/svg+xml',
      content_base64: textBase64(svg),
      metadata: {
        locale: locale.value,
        font_family: exportModule.EXPORT_FONT_FAMILY,
        artwork_manifest_version: manifest.registry_version,
        map_dataset_id: props.territory.map.id,
        map_dataset_checksum: props.territory.map.checksum,
        exported_at: exportedAt,
      },
    });
    await refreshRenditions();
    const rendition = payload.rendition as Rendition;
    notice.value = {
      tone: 'success',
      message: `Persisted artwork rendition ${rendition.content_checksum.slice(0, 12)}….`,
    };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : 'Rendition persistence failed.',
    };
  } finally {
    busy.value = false;
  }
}

function bytesFromBase64(value: string): Uint8Array {
  const binary = atob(value);
  return Uint8Array.from(binary, (character) => character.charCodeAt(0));
}

async function reopenRendition(rendition: Rendition): Promise<void> {
  busy.value = true;
  try {
    const payload = await jsonRequest(
      `/territory/${props.territory.plan.id}/renditions/${rendition.id}`,
      'GET',
    );
    const record = payload.rendition as Rendition & { content_base64: string };
    const blob = new Blob([bytesFromBase64(record.content_base64)], { type: record.media_type });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `territory-rendition-${record.id}.${record.media_type === 'image/svg+xml' ? 'svg' : record.media_type === 'image/png' ? 'png' : 'pdf'}`;
    document.body.append(anchor);
    anchor.click();
    anchor.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : 'Rendition reopen failed.',
    };
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <Head :title="`${territory.plan.name} · Layout Tools`" />
  <AppLayout :user="user">
    <header class="ks-surface p-5">
      <p class="ks-kicker">Territory planning</p>
      <div class="mt-1 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="ks-display text-3xl font-semibold">Layout Tools</h1>
          <p class="mt-2 text-sm text-[var(--ks-muted)]">
            {{ territory.plan.name }} · revision {{ revision }} · {{ status }}
          </p>
        </div>
        <nav class="flex flex-wrap gap-2" aria-label="Territory plan navigation">
          <Link :href="`/territory/${territory.plan.id}`" class="ks-command-link">Editor</Link>
          <Link
            :href="`/territory/${territory.plan.id}/reconciliation`"
            class="ks-command-link"
            data-variant="secondary"
            >Plan vs observed</Link
          >
          <Link href="/territory" class="ks-command-link" data-variant="secondary">Plans</Link>
        </nav>
      </div>
    </header>

    <ActionNotice v-if="notice" class="mt-4" :tone="notice.tone" :message="notice.message" />

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
      <section class="ks-surface p-4" aria-labelledby="transform-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="ks-kicker">Atomic editing</p>
            <h2 id="transform-heading" class="ks-display text-xl font-semibold">
              Align, distribute and bulk coordinates
            </h2>
          </div>
          <span class="ks-chip">{{ selectedCount }} selected</span>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
          <AppButton data-variant="secondary" @click="selectAllEditable">Select editable</AppButton>
          <AppButton data-variant="secondary" @click="selectedKeys = []">Clear</AppButton>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
          <button
            v-for="item in [
              ['left', 'Align left'],
              ['center_x', 'Center X'],
              ['right', 'Align right'],
              ['top', 'Align top'],
              ['center_y', 'Center Y'],
              ['bottom', 'Align bottom'],
            ] as const"
            :key="item[0]"
            class="ks-command-link"
            :disabled="!selectedCount"
            @click="align(item[0])"
          >
            {{ item[1] }}
          </button>
          <button
            class="ks-command-link"
            :disabled="selectedCount < 3"
            @click="distribute('horizontal')"
          >
            Distribute X
          </button>
          <button
            class="ks-command-link"
            :disabled="selectedCount < 3"
            @click="distribute('vertical')"
          >
            Distribute Y
          </button>
        </div>
        <div class="mt-4 max-h-[30rem] overflow-auto rounded border border-[var(--ks-border)]">
          <table class="w-full min-w-[42rem] text-sm">
            <thead class="sticky top-0 bg-[var(--ks-surface)]">
              <tr class="text-start text-xs text-[var(--ks-muted)]">
                <th class="p-2">Select</th>
                <th class="p-2 text-start">Object</th>
                <th class="p-2 text-start">Layer</th>
                <th class="p-2">X</th>
                <th class="p-2">Y</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="object in objects"
                :key="object.key"
                class="border-t border-[var(--ks-border)]"
              >
                <td class="p-2 text-center">
                  <input
                    v-model="selectedKeys"
                    type="checkbox"
                    :value="object.key"
                    :disabled="!editable(object)"
                    :aria-label="`Select ${object.label ?? object.external_player_name ?? object.key}`"
                  />
                </td>
                <td class="p-2">
                  {{ object.label || object.external_player_name || object.type }}
                </td>
                <td class="p-2">{{ object.alliance_key }}</td>
                <td class="p-2 text-center">{{ object.x }}</td>
                <td class="p-2 text-center">{{ object.y }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2">
          <AppButton
            :busy="busy"
            :disabled="!dirty || !territory.plan.can_manage"
            @click="saveLayout"
            >Save layout</AppButton
          >
          <span v-if="dirty" class="text-xs text-amber-200">Unsaved layout changes</span>
        </div>
      </section>

      <section class="ks-surface p-4" aria-labelledby="csv-heading">
        <p class="ks-kicker">Interchange</p>
        <h2 id="csv-heading" class="ks-display text-xl font-semibold">CSV coordinate table</h2>
        <p class="mt-2 text-sm text-[var(--ks-muted)]">
          Preview is strict and bounded; applying coordinates is one atomic command and respects
          locks.
        </p>
        <a
          :href="`/territory/${territory.plan.id}/coordinates.csv`"
          class="ks-command-link mt-3 inline-flex"
          >Export CSV</a
        >
        <label class="mt-4 block text-sm font-semibold"
          >CSV preview
          <textarea
            v-model="csvText"
            class="ks-input mt-2 min-h-48 w-full font-mono text-xs"
            placeholder="key,type,variant_key,x,y,rotation,..."
          />
        </label>
        <div class="mt-3 flex flex-wrap gap-2">
          <AppButton :busy="busy" :disabled="!csvText.trim()" @click="previewCsv"
            >Validate CSV</AppButton
          >
          <AppButton
            data-variant="secondary"
            :disabled="!coordinatePreview.length"
            @click="applyCsvCoordinates"
            >Apply coordinates</AppButton
          >
        </div>
        <p v-if="coordinatePreview.length" class="mt-2 text-xs text-[var(--ks-muted)]">
          {{ coordinatePreview.length }} validated row(s) ready to apply.
        </p>
      </section>

      <section class="ks-surface p-4" aria-labelledby="annotations-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="ks-kicker">Annotations</p>
            <h2 id="annotations-heading" class="ks-display text-xl font-semibold">
              Plan annotations
            </h2>
          </div>
          <AppButton
            data-variant="secondary"
            :disabled="annotations.length >= 500"
            @click="addAnnotation"
            >Add annotation</AppButton
          >
        </div>
        <div class="mt-3 space-y-3">
          <fieldset
            v-for="(annotation, index) in annotations"
            :key="annotation.key"
            class="rounded border border-[var(--ks-border)] p-3"
          >
            <legend class="px-1 text-xs font-semibold">Annotation {{ index + 1 }}</legend>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
              <label class="text-xs"
                >Kind<select
                  v-model="annotation.kind"
                  class="ks-input mt-1 w-full"
                  @change="normalizeAnnotationTarget(annotation)"
                >
                  <option value="label">Label</option>
                  <option value="line">Line</option>
                  <option value="arrow">Arrow</option>
                  <option value="rectangle">Rectangle</option>
                </select></label
              >
              <label class="text-xs"
                >Alliance<select v-model="annotation.alliance_key" class="ks-input mt-1 w-full">
                  <option :value="null">All layers</option>
                  <option v-for="alliance in alliances" :key="alliance.key" :value="alliance.key">
                    {{ alliance.display_name }}
                  </option>
                </select></label
              >
              <label class="text-xs"
                >X<input v-model.number="annotation.x" type="number" class="ks-input mt-1 w-full"
              /></label>
              <label class="text-xs"
                >Y<input v-model.number="annotation.y" type="number" class="ks-input mt-1 w-full"
              /></label>
              <label v-if="annotation.kind !== 'label'" class="text-xs"
                >Target X<input
                  v-model.number="annotation.target_x"
                  type="number"
                  class="ks-input mt-1 w-full"
              /></label>
              <label v-if="annotation.kind !== 'label'" class="text-xs"
                >Target Y<input
                  v-model.number="annotation.target_y"
                  type="number"
                  class="ks-input mt-1 w-full"
              /></label>
              <label class="text-xs sm:col-span-2"
                >Text<input v-model="annotation.text" maxlength="500" class="ks-input mt-1 w-full"
              /></label>
            </div>
            <button
              class="ks-command-link mt-2"
              data-variant="danger"
              @click="annotations.splice(index, 1)"
            >
              Remove
            </button>
          </fieldset>
        </div>
        <AppButton
          class="mt-3"
          :busy="busy"
          :disabled="!territory.plan.can_manage"
          @click="saveAnnotations"
          >Save annotations</AppButton
        >
      </section>

      <section class="ks-surface p-4" aria-labelledby="templates-heading">
        <p class="ks-kicker">Hive Builder</p>
        <h2 id="templates-heading" class="ks-display text-xl font-semibold">
          Reusable Hive templates
        </h2>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
          <label class="text-xs"
            >Template name<input
              v-model="templateName"
              maxlength="160"
              class="ks-input mt-1 w-full"
          /></label>
          <label class="text-xs"
            >Style<select v-model="templateStyle" class="ks-input mt-1 w-full">
              <option value="swirl">Swirl</option>
              <option value="banner_pad">Banner pad</option>
            </select></label
          >
          <label class="text-xs"
            >City count<input
              v-model.number="templateCityCount"
              type="number"
              min="1"
              max="100"
              class="ks-input mt-1 w-full"
          /></label>
          <label class="text-xs"
            >Spacing<input
              v-model.number="templateSpacing"
              type="number"
              min="0"
              max="8"
              class="ks-input mt-1 w-full"
          /></label>
        </div>
        <AppButton
          class="mt-2"
          :busy="busy"
          :disabled="!territory.plan.can_manage || !templateName.trim()"
          @click="saveHiveTemplate"
          >Save template</AppButton
        >
        <div class="mt-4 border-t border-[var(--ks-border)] pt-3">
          <label class="text-xs"
            >Saved template<select v-model="templateId" class="ks-input mt-1 w-full">
              <option value="">Choose template</option>
              <option v-for="template in templates" :key="template.id" :value="template.id">
                {{ template.name }} · {{ template.style }} · {{ template.city_count }} cities
              </option>
            </select></label
          >
          <div class="mt-2 grid gap-2 sm:grid-cols-3">
            <label class="text-xs"
              >Alliance<select v-model="templateAllianceKey" class="ks-input mt-1 w-full">
                <option v-for="alliance in alliances" :key="alliance.key" :value="alliance.key">
                  {{ alliance.display_name }}
                </option>
              </select></label
            >
            <label class="text-xs"
              >Center X<input
                v-model.number="templateCenterX"
                type="number"
                class="ks-input mt-1 w-full"
            /></label>
            <label class="text-xs"
              >Center Y<input
                v-model.number="templateCenterY"
                type="number"
                class="ks-input mt-1 w-full"
            /></label>
          </div>
          <AppButton
            class="mt-2"
            :busy="busy"
            :disabled="!templateId || !templateAllianceKey"
            @click="instantiateHiveTemplate"
            >Instantiate template</AppButton
          >
        </div>
      </section>

      <section class="ks-surface p-4 xl:col-span-2" aria-labelledby="renditions-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="ks-kicker">Visual evidence</p>
            <h2 id="renditions-heading" class="ks-display text-xl font-semibold">
              Persisted renditions
            </h2>
          </div>
          <AppButton
            :busy="busy"
            :disabled="!territory.plan.can_manage"
            @click="persistArtworkRendition"
            >Persist artwork SVG</AppButton
          >
        </div>
        <p class="mt-2 text-sm text-[var(--ks-muted)]">
          Persistence is revision-pinned and fails closed until real authorized artwork bytes can be
          embedded.
        </p>
        <div class="mt-3 overflow-auto">
          <table class="w-full min-w-[48rem] text-sm">
            <thead>
              <tr class="text-start text-xs text-[var(--ks-muted)]">
                <th class="p-2 text-start">Created</th>
                <th class="p-2 text-start">Revision</th>
                <th class="p-2 text-start">Scope</th>
                <th class="p-2 text-start">Type</th>
                <th class="p-2 text-start">Checksum</th>
                <th class="p-2">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="rendition in renditions"
                :key="rendition.id"
                class="border-t border-[var(--ks-border)]"
              >
                <td class="p-2">{{ rendition.created_at ?? '—' }}</td>
                <td class="p-2">{{ rendition.territory_plan_revision_id }}</td>
                <td class="p-2">{{ rendition.scope }}</td>
                <td class="p-2">{{ rendition.media_type }}</td>
                <td class="p-2 font-mono text-xs">
                  {{ rendition.content_checksum.slice(0, 16) }}…
                </td>
                <td class="p-2 text-center">
                  <button class="ks-command-link" @click="reopenRendition(rendition)">
                    Reopen
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
