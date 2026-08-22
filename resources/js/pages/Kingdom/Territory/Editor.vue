<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import ActionNotice from '@/components/ui/ActionNotice.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import MarchAnalysisPanel from '@/features/territory-planner/components/MarchAnalysisPanel.vue';
import TerritoryCanvas from '@/features/territory-planner/components/TerritoryCanvas.vue';
import {
  buildSvg,
  downloadPngFromSvg,
  downloadText,
} from '@/features/territory-planner/engine/export';
import { analyzeLayout, validatePlacement } from '@/features/territory-planner/engine/geometry';
import type {
  AllianceAnalysis,
  MapData,
  PlanAlliance,
  PlanGroup,
  PlanObject,
  PlanningPreferences,
  TerritoryObjectType,
  ValidationIssue,
} from '@/features/territory-planner/engine/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type PlanSummary = {
  id: string;
  scope: 'alliance' | 'kingdom';
  kingdom_id: string;
  owner_alliance_id: string | null;
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
  map_dataset_id: string;
  map_dataset_checksum: string;
  snapshot_checksum: string;
  published_at: string | null;
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
  governor_options: Record<string, Array<{ id: string; name: string }>>;
};
type Tool = 'select' | 'pan' | 'place';
type DialogAction = { kind: 'archive' } | { kind: 'restore'; revision: Revision } | null;
type RevisionSnapshot = {
  alliances: PlanAlliance[];
  groups: PlanGroup[];
  objects: PlanObject[];
  plan: { planning_preferences?: PlanningPreferences };
};
type ImportPreview = {
  can_commit: boolean;
  map: { id: string; checksum: string; source_label: string; confidence: string };
  alliances: PlanAlliance[];
  groups: PlanGroup[];
  objects: PlanObject[];
  validation: {
    violations: ValidationIssue[];
    warnings: ValidationIssue[];
    suggestions: ValidationIssue[];
  };
};

function cloneJson<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T;
}

const props = defineProps<{
  user: { name: string; email: string };
  activePlayer: { id: string; name: string; kingdomNumber: number | null };
  territory: TerritoryProp;
}>();
const { t, formatNumber, formatDate } = useLocale();
const alliances = ref<PlanAlliance[]>(cloneJson(props.territory.alliances));
const groups = ref<PlanGroup[]>(cloneJson(props.territory.groups));
const objects = ref<PlanObject[]>(cloneJson(props.territory.objects));
const preferences = ref<PlanningPreferences>(
  cloneJson(props.territory.plan.planning_preferences ?? {}),
);
const revision = ref(props.territory.plan.revision);
const status = ref(props.territory.plan.status);
const selectedKeys = ref<string[]>([]);
const activeAllianceKey = ref(alliances.value[0]?.key ?? null);
const tool = ref<Tool>('select');
const placementType = ref<TerritoryObjectType>('governor_city');
const notice = ref<{ tone: 'success' | 'warning' | 'danger' | 'info'; message: string } | null>(
  null,
);
const busy = ref(false);
const history = ref<string[]>([]);
const future = ref<string[]>([]);
const canvas = ref<InstanceType<typeof TerritoryCanvas> | null>(null);
const dialogAction = ref<DialogAction>(null);
const importPreview = ref<ImportPreview | null>(null);
const importDocument = ref('');
const hivePreview = ref<PlanObject[]>([]);
const hiveStyle = ref<'swirl' | 'banner_pad'>('swirl');
const hiveCenterX = ref(
  Math.round(props.territory.map.data.bounds.x + props.territory.map.data.bounds.width / 2),
);
const hiveCenterY = ref(
  Math.round(props.territory.map.data.bounds.y + props.territory.map.data.bounds.height / 2),
);
const hiveCityCount = ref(50);
const stampColumns = ref(5);
const stampRows = ref(5);
const compareRevisionId = ref('');
const comparison = ref<{
  revision: number;
  current: Record<string, AllianceAnalysis>;
  previous: Record<string, AllianceAnalysis>;
} | null>(null);
const cloneName = ref(`${props.territory.plan.name} copy`);
const showCoverage = ref(true);
const showStructures = ref(true);
const showZones = ref(true);
const objectFilter = ref('');
const draftStorageKey = `territory-draft:${props.territory.plan.id}:${props.territory.plan.revision}`;

const mapMinX = computed(() => props.territory.map.data.bounds.x);
const mapMinY = computed(() => props.territory.map.data.bounds.y);
const mapMaxX = computed(
  () => props.territory.map.data.bounds.x + props.territory.map.data.bounds.width - 1,
);
const mapMaxY = computed(
  () => props.territory.map.data.bounds.y + props.territory.map.data.bounds.height - 1,
);
const mapDistanceLimit = computed(() =>
  Math.ceil(
    Math.hypot(props.territory.map.data.bounds.width, props.territory.map.data.bounds.height),
  ),
);
const validation = computed(() =>
  validatePlacement(props.territory.map.data, objects.value, preferences.value),
);
const analysis = computed(() =>
  analyzeLayout(props.territory.map.data, objects.value, preferences.value),
);
const selectedObjects = computed(() =>
  objects.value.filter((object) => selectedKeys.value.includes(object.key)),
);
const governorCities = computed(() =>
  visibleObjects.value.filter((object) => object.type === 'governor_city'),
);
const activeAlliance = computed(
  () => alliances.value.find((alliance) => alliance.key === activeAllianceKey.value) ?? null,
);
const canEdit = computed(() => props.territory.plan.can_manage && status.value !== 'archived');
const visibleObjects = computed(() => {
  const query = objectFilter.value.trim().toLocaleLowerCase();
  return objects.value.filter((object) => {
    const layer = alliances.value.find((alliance) => alliance.key === object.alliance_key);
    if (!layer?.visible) return false;
    if (!query) return true;
    return [object.label, object.external_player_name, object.type, layer.display_name].some(
      (value) => value?.toLocaleLowerCase().includes(query),
    );
  });
});
const objectIssues = computed(() => {
  const map = new Map<string, string[]>();
  [
    ...validation.value.violations,
    ...validation.value.warnings,
    ...validation.value.suggestions,
  ].forEach((issue) => {
    if (!issue.object_key) return;
    map.set(issue.object_key, [...(map.get(issue.object_key) ?? []), issue.message]);
  });
  return map;
});
const hivePreviewValidation = computed(() =>
  validatePlacement(
    props.territory.map.data,
    [...objects.value, ...hivePreview.value],
    preferences.value,
  ),
);

function snapshot(): string {
  return JSON.stringify({
    alliances: alliances.value,
    groups: groups.value,
    objects: objects.value,
    preferences: preferences.value,
  });
}
function remember(): void {
  history.value.push(snapshot());
  if (history.value.length > 100) history.value.shift();
  future.value = [];
}
function restoreSnapshot(value: string): void {
  const state = JSON.parse(value) as {
    alliances: PlanAlliance[];
    groups: PlanGroup[];
    objects: PlanObject[];
    preferences: PlanningPreferences;
  };
  alliances.value = state.alliances;
  groups.value = state.groups;
  objects.value = state.objects;
  preferences.value = state.preferences;
  selectedKeys.value = selectedKeys.value.filter((key) =>
    objects.value.some((object) => object.key === key),
  );
}
function undo(): void {
  const value = history.value.pop();
  if (!value) return;
  future.value.push(snapshot());
  restoreSnapshot(value);
}
function redo(): void {
  const value = future.value.pop();
  if (!value) return;
  history.value.push(snapshot());
  restoreSnapshot(value);
}
function key(prefix: string): string {
  return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
}
function allianceFor(object: PlanObject): PlanAlliance | undefined {
  return alliances.value.find((alliance) => alliance.key === object.alliance_key);
}
function editable(object: PlanObject): boolean {
  return canEdit.value && !allianceFor(object)?.locked;
}
function governorOptionsFor(object: PlanObject): Array<{ id: string; name: string }> {
  return props.territory.governor_options[object.alliance_key] ?? [];
}
function assignGovernor(object: PlanObject, playerId: string): void {
  if (!editable(object)) return;
  remember();
  object.player_id = playerId || null;
  if (playerId) object.external_player_name = null;
}
function assignExternalGovernor(object: PlanObject, name: string): void {
  if (!editable(object)) return;
  object.player_id = null;
  object.external_player_name = name.trim() || null;
}
function setSelectedBear(allianceKey: string, objectKey: string): void {
  const alliance = alliances.value.find((candidate) => candidate.key === allianceKey);
  if (!canEdit.value || alliance?.locked) return;
  remember();
  const selected = { ...(preferences.value.selected_bear_trap_by_alliance ?? {}) };
  if (objectKey) selected[allianceKey] = objectKey;
  else delete selected[allianceKey];
  preferences.value = { ...preferences.value, selected_bear_trap_by_alliance: selected };
}

function place(point: { x: number; y: number }): void {
  if (!canEdit.value || !activeAllianceKey.value || activeAlliance.value?.locked) return;
  remember();
  const objectKey = key('object');
  objects.value.push({
    key: objectKey,
    alliance_key: activeAllianceKey.value,
    group_key: null,
    type: placementType.value,
    player_id: null,
    external_player_name: null,
    label: null,
    x: point.x,
    y: point.y,
    rotation: 0,
    sort_order: objects.value.length,
    metadata: {},
  });
  selectedKeys.value = [objectKey];
}
function move(payload: { keys: string[]; dx: number; dy: number }): void {
  const movable = payload.keys.filter((item) => {
    const object = objects.value.find((candidate) => candidate.key === item);
    return object ? editable(object) : false;
  });
  if (!movable.length || (!payload.dx && !payload.dy)) return;
  remember();
  objects.value = objects.value.map((object) =>
    movable.includes(object.key)
      ? { ...object, x: object.x + payload.dx, y: object.y + payload.dy }
      : object,
  );
}
function removeSelected(): void {
  const keys = selectedObjects.value.filter(editable).map((object) => object.key);
  if (!keys.length) return;
  remember();
  objects.value = objects.value.filter((object) => !keys.includes(object.key));
  selectedKeys.value = [];
}
function duplicateSelected(): void {
  const source = selectedObjects.value.filter(editable);
  if (!source.length) return;
  remember();
  const clones = source.map((object, index) => ({
    ...cloneJson(object),
    key: key(`copy${index}`),
    x: object.x + 3,
    y: object.y + 3,
    group_key: null,
  }));
  objects.value.push(...clones);
  selectedKeys.value = clones.map((object) => object.key);
}
function groupSelected(): void {
  const items = selectedObjects.value.filter(editable);
  if (items.length < 2) return;
  remember();
  const groupKey = key('group');
  groups.value.push({ key: groupKey, label: t('territory.group') });
  objects.value = objects.value.map((object) =>
    items.some((item) => item.key === object.key) ? { ...object, group_key: groupKey } : object,
  );
}
function ungroupSelected(): void {
  const affected = new Set(
    selectedObjects.value
      .filter(editable)
      .map((object) => object.group_key)
      .filter((value): value is string => value !== null),
  );
  if (!affected.size) return;
  remember();
  objects.value = objects.value.map((object) =>
    affected.has(object.group_key ?? '') ? { ...object, group_key: null } : object,
  );
  groups.value = groups.value.filter((group) => !affected.has(group.key));
}
function rotateSelected(direction = 1): void {
  const keys = selectedObjects.value.filter(editable).map((object) => object.key);
  if (!keys.length) return;
  remember();
  objects.value = objects.value.map((object) =>
    keys.includes(object.key)
      ? { ...object, rotation: (object.rotation + direction * 90 + 360) % 360 }
      : object,
  );
}
function nudge(dx: number, dy: number): void {
  move({ keys: selectedKeys.value, dx, dy });
}
function selectObject(object: PlanObject): void {
  selectedKeys.value = [object.key];
}
function beginExactEdit(): void {
  if (canEdit.value) remember();
}

function stampCities(): void {
  if (!canEdit.value || !activeAllianceKey.value || activeAlliance.value?.locked) return;
  const count = stampColumns.value * stampRows.value;
  if (count < 1 || count > 100) {
    notice.value = { tone: 'danger', message: t('territory.stampLimit') };
    return;
  }
  remember();
  const groupKey = key('tc-block');
  groups.value.push({ key: groupKey, label: t('territory.tcBlock') });
  const placed: PlanObject[] = [];
  for (let row = 0; row < stampRows.value; row += 1) {
    for (let column = 0; column < stampColumns.value; column += 1) {
      placed.push({
        key: key('city'),
        alliance_key: activeAllianceKey.value,
        group_key: groupKey,
        type: 'governor_city',
        player_id: null,
        external_player_name: null,
        label: null,
        x: hiveCenterX.value + column * 3,
        y: hiveCenterY.value + row * 3,
        rotation: 0,
        sort_order: objects.value.length + placed.length,
        metadata: {},
      });
    }
  }
  objects.value.push(...placed);
  selectedKeys.value = placed.map((object) => object.key);
}

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
async function jsonRequest(
  url: string,
  method: string,
  body?: unknown,
): Promise<Record<string, unknown>> {
  const init: NonNullable<Parameters<typeof fetch>[1]> = {
    method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
  };
  if (body !== undefined) init.body = JSON.stringify(body);
  const response = await fetch(url, init);
  const payload = (await response.json().catch(() => ({}))) as Record<string, unknown>;
  if (!response.ok) {
    const errors = payload.errors as Record<string, string[] | string> | undefined;
    const first = errors ? Object.values(errors).flat()[0] : null;
    throw new Error(
      typeof first === 'string'
        ? first
        : typeof payload.message === 'string'
          ? payload.message
          : t('territory.requestFailed'),
    );
  }
  return payload;
}

async function save(): Promise<void> {
  if (!canEdit.value || validation.value.violations.length) {
    notice.value = { tone: 'danger', message: t('territory.fixViolations') };
    return;
  }
  busy.value = true;
  notice.value = null;
  try {
    const payload = await jsonRequest(`/territory/${props.territory.plan.id}`, 'PUT', {
      expected_revision: revision.value,
      alliances: alliances.value,
      groups: groups.value,
      objects: objects.value,
      planning_preferences: preferences.value,
    });
    const receipt = payload.receipt as { revision: number; status: string };
    revision.value = receipt.revision;
    status.value = receipt.status;
    history.value = [];
    future.value = [];
    localStorage.removeItem(draftStorageKey);
    notice.value = { tone: 'success', message: t('territory.saved', { revision: revision.value }) };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    busy.value = false;
  }
}
async function publish(): Promise<void> {
  if (validation.value.violations.length) {
    notice.value = { tone: 'danger', message: t('territory.fixViolations') };
    return;
  }
  busy.value = true;
  try {
    const payload = await jsonRequest(`/territory/${props.territory.plan.id}/publish`, 'POST', {
      expected_revision: revision.value,
    });
    const receipt = payload.receipt as { status: string };
    status.value = receipt.status;
    notice.value = { tone: 'success', message: t('territory.published') };
    router.reload({ only: ['territory'] });
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    busy.value = false;
  }
}
async function archive(): Promise<void> {
  busy.value = true;
  try {
    await jsonRequest(`/territory/${props.territory.plan.id}`, 'DELETE', {
      expected_revision: revision.value,
    });
    dialogAction.value = null;
    localStorage.removeItem(draftStorageKey);
    router.visit('/territory');
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    busy.value = false;
  }
}
async function clonePlan(): Promise<void> {
  const name = cloneName.value.trim();
  if (!name) return;
  busy.value = true;
  try {
    const payload = await jsonRequest(`/territory/${props.territory.plan.id}/clone`, 'POST', {
      name,
    });
    const receipt = payload.receipt as { plan_id: string };
    router.visit(`/territory/${receipt.plan_id}`);
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    busy.value = false;
  }
}
async function restoreRevision(item: Revision): Promise<void> {
  busy.value = true;
  try {
    const payload = await jsonRequest(
      `/territory/${props.territory.plan.id}/revisions/${item.id}/restore`,
      'POST',
      { expected_revision: revision.value },
    );
    const receipt = payload.receipt as { revision: number };
    revision.value = receipt.revision;
    dialogAction.value = null;
    notice.value = {
      tone: 'success',
      message: t('territory.restored', { revision: item.revision_number }),
    };
    router.reload({ only: ['territory'] });
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    busy.value = false;
  }
}
async function compareRevision(): Promise<void> {
  if (!compareRevisionId.value) {
    comparison.value = null;
    return;
  }
  busy.value = true;
  try {
    const payload = await jsonRequest(
      `/territory/${props.territory.plan.id}/revisions/${compareRevisionId.value}`,
      'GET',
    );
    const record = payload.revision as { revision_number: number; snapshot: RevisionSnapshot };
    const previousPreferences = record.snapshot.plan.planning_preferences ?? {};
    comparison.value = {
      revision: record.revision_number,
      current: analysis.value,
      previous: analyzeLayout(
        props.territory.map.data,
        record.snapshot.objects,
        previousPreferences,
      ),
    };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    busy.value = false;
  }
}

async function generateHivePreview(): Promise<void> {
  if (!activeAllianceKey.value) return;
  busy.value = true;
  try {
    const payload = await jsonRequest('/territory/hive-preview', 'POST', {
      style: hiveStyle.value,
      alliance_key: activeAllianceKey.value,
      center_x: hiveCenterX.value,
      center_y: hiveCenterY.value,
      city_count: hiveCityCount.value,
    });
    const generated = payload.objects as Array<
      Partial<PlanObject> & Pick<PlanObject, 'type' | 'x' | 'y' | 'alliance_key'>
    >;
    const groupKey = key('hive');
    hivePreview.value = generated.map((object, index) => ({
      key: key(`hive-preview-${index}`),
      alliance_key: object.alliance_key,
      group_key: groupKey,
      type: object.type,
      player_id: null,
      external_player_name: null,
      label: object.label ?? null,
      x: object.x,
      y: object.y,
      rotation: 0,
      sort_order: objects.value.length + index,
      metadata: {},
    }));
    if (hivePreviewValidation.value.violations.length)
      notice.value = { tone: 'warning', message: t('territory.hivePreviewBlocked') };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    busy.value = false;
  }
}
function applyHivePreview(): void {
  if (!hivePreview.value.length || hivePreviewValidation.value.violations.length) return;
  remember();
  const groupKey = hivePreview.value[0]?.group_key ?? key('hive');
  groups.value.push({
    key: groupKey,
    label: hiveStyle.value === 'swirl' ? t('territory.swirlHive') : t('territory.bannerPadHive'),
  });
  objects.value.push(...hivePreview.value);
  selectedKeys.value = hivePreview.value.map((object) => object.key);
  hivePreview.value = [];
}

function exportDocument(): {
  schema_version: 1;
  plan: Record<string, unknown>;
  alliances: PlanAlliance[];
  groups: PlanGroup[];
  objects: PlanObject[];
} {
  return {
    schema_version: 1,
    plan: {
      ...props.territory.plan,
      revision: revision.value,
      map_dataset_id: props.territory.map.id,
      map_dataset_checksum: props.territory.map.checksum,
      planning_preferences: preferences.value,
    },
    alliances: alliances.value,
    groups: groups.value,
    objects: objects.value,
  };
}
function exportMetadata() {
  return {
    title: props.territory.plan.name,
    mapProfile: props.territory.map.source_label,
    observedAt: props.territory.map.observed_at,
    confidence: props.territory.map.confidence,
    exportedAt: new Date().toISOString(),
  };
}
function exportJson(): void {
  downloadText(
    `${props.territory.plan.name.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}.json`,
    JSON.stringify(exportDocument(), null, 2),
    'application/json',
  );
}
function exportSvg(): void {
  downloadText(
    `${props.territory.plan.name}.svg`,
    buildSvg(props.territory.map.data, alliances.value, objects.value, exportMetadata()),
    'image/svg+xml',
  );
}
async function exportPng(): Promise<void> {
  await downloadPngFromSvg(
    `${props.territory.plan.name}.png`,
    buildSvg(props.territory.map.data, alliances.value, objects.value, exportMetadata()),
  );
}
async function importFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;
  busy.value = true;
  try {
    importDocument.value = await file.text();
    const payload = await jsonRequest('/territory/import-preview', 'POST', {
      document: importDocument.value,
    });
    importPreview.value = payload.preview as ImportPreview;
    notice.value = { tone: 'info', message: t('territory.importReady') };
  } catch (error) {
    importDocument.value = '';
    importPreview.value = null;
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    busy.value = false;
    input.value = '';
  }
}
async function applyImport(): Promise<void> {
  if (!importPreview.value || !importPreview.value.can_commit || !importDocument.value) return;
  busy.value = true;
  try {
    const payload = await jsonRequest(`/territory/${props.territory.plan.id}/import`, 'POST', {
      expected_revision: revision.value,
      document: importDocument.value,
    });
    const receipt = payload.receipt as { revision: number; status: string };
    revision.value = receipt.revision;
    status.value = receipt.status;
    importPreview.value = null;
    importDocument.value = '';
    history.value = [];
    future.value = [];
    localStorage.removeItem(draftStorageKey);
    notice.value = {
      tone: 'success',
      message: t('territory.imported', { revision: revision.value }),
    };
    router.reload({ only: ['territory'] });
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    busy.value = false;
  }
}

function onKey(event: KeyboardEvent): void {
  if (
    event.target instanceof HTMLInputElement ||
    event.target instanceof HTMLTextAreaElement ||
    event.target instanceof HTMLSelectElement
  )
    return;
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
    event.preventDefault();
    if (event.shiftKey) {
      redo();
    } else {
      undo();
    }
    return;
  }
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'y') {
    event.preventDefault();
    redo();
    return;
  }
  if (event.key === 'Delete' || event.key === 'Backspace') {
    event.preventDefault();
    removeSelected();
    return;
  }
  if (event.key.toLowerCase() === 'r') {
    event.preventDefault();
    rotateSelected(event.shiftKey ? -1 : 1);
    return;
  }
  if (event.key === 'ArrowLeft') {
    event.preventDefault();
    nudge(-1, 0);
  }
  if (event.key === 'ArrowRight') {
    event.preventDefault();
    nudge(1, 0);
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault();
    nudge(0, 1);
  }
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    nudge(0, -1);
  }
}

watch(
  [alliances, groups, objects, preferences],
  () => {
    if (canEdit.value) localStorage.setItem(draftStorageKey, snapshot());
  },
  { deep: true },
);
onMounted(() => {
  window.addEventListener('keydown', onKey);
  const stored = localStorage.getItem(draftStorageKey);
  if (stored && stored !== snapshot()) {
    restoreSnapshot(stored);
    notice.value = { tone: 'info', message: t('territory.localDraftRestored') };
  }
});
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
  <Head :title="`${territory.plan.name} · ${t('territory.editorTitle')}`" />
  <AppLayout :user="user">
    <RoomBanner
      :eyebrow="t('territory.eyebrow')"
      :title="territory.plan.name"
      :subtitle="t('territory.editorSubtitle', { governor: activePlayer.name })"
      image="/images/kingshot/v4/kingdom-transfer.svg"
    >
      <template #actions>
        <Link
          v-if="territory.plan.scope === 'kingdom'"
          :href="`/territory/${territory.plan.id}/alliances`"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('territory.layers') }}
        </Link>
        <Link href="/territory" class="ks-command-link" data-variant="secondary">
          {{ t('territory.backToPlans') }}
        </Link>
      </template>
    </RoomBanner>

    <header class="mt-4">
      <p class="ks-kicker">{{ t('territory.eyebrow') }}</p>
      <h1 id="territory-editor-heading" class="ks-display mt-1 text-3xl font-semibold">
        {{ t('territory.editorTitle') }}
      </h1>
    </header>

    <ActionNotice v-if="notice" class="mt-4" :tone="notice.tone" :message="notice.message" />

    <section class="ks-surface mt-4 p-4" :aria-label="t('territory.planStatus')">
      <div class="flex flex-wrap items-center gap-3 text-sm">
        <span
          class="ks-status"
          :data-tone="
            status === 'published' ? 'success' : status === 'archived' ? 'danger' : 'info'
          "
          >{{ t(`territory.status.${status}`) }}</span
        >
        <span class="ks-chip">{{ t('territory.revision', { revision }) }}</span>
        <span class="ks-chip"
          >{{ territory.map.source_label }} · {{ territory.map.observed_at }}</span
        >
        <span class="ks-chip">{{ t(`territory.confidence.${territory.map.confidence}`) }}</span>
        <a
          v-if="territory.map.source_uri"
          :href="territory.map.source_uri"
          target="_blank"
          rel="noreferrer"
          class="text-[var(--ks-gold-bright)] underline"
          >{{ t('territory.mapSource') }}</a
        >
      </div>
      <p class="mt-2 text-xs leading-5 text-[var(--ks-muted)]">
        {{ t('territory.mapEvidenceHelp') }}
      </p>
    </section>

    <div class="mt-4 grid gap-4 2xl:grid-cols-[18rem_minmax(0,1fr)_22rem]">
      <aside class="ks-surface p-4" :aria-label="t('territory.buildTools')">
        <p class="ks-kicker">{{ t('territory.build') }}</p>
        <div class="mt-3 grid grid-cols-3 gap-2 2xl:grid-cols-1">
          <button
            class="ks-command-link"
            :aria-pressed="tool === 'select'"
            @click="tool = 'select'"
          >
            {{ t('territory.select') }}
          </button>
          <button class="ks-command-link" :aria-pressed="tool === 'pan'" @click="tool = 'pan'">
            {{ t('territory.pan') }}
          </button>
          <button
            class="ks-command-link"
            :aria-pressed="tool === 'place'"
            :disabled="!canEdit || Boolean(activeAlliance?.locked)"
            @click="tool = 'place'"
          >
            {{ t('territory.place') }}
          </button>
        </div>
        <label class="mt-4 block text-sm font-semibold"
          >{{ t('territory.activeAlliance')
          }}<select v-model="activeAllianceKey" class="ks-input mt-2 w-full">
            <option v-for="alliance in alliances" :key="alliance.key" :value="alliance.key">
              {{ alliance.display_name }}
            </option>
          </select></label
        >
        <label class="mt-4 block text-sm font-semibold"
          >{{ t('territory.objectType')
          }}<select v-model="placementType" class="ks-input mt-2 w-full">
            <option value="headquarters">{{ t('territory.types.headquarters') }}</option>
            <option value="banner">{{ t('territory.types.banner') }}</option>
            <option value="governor_city">{{ t('territory.types.governor_city') }}</option>
            <option value="bear_trap">{{ t('territory.types.bear_trap') }}</option>
          </select></label
        >

        <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
          <p class="ks-kicker">{{ t('territory.layers') }}</p>
          <div
            v-for="alliance in alliances"
            :key="alliance.key"
            class="mt-3 rounded border border-[var(--ks-border)] p-2"
          >
            <label class="flex items-center gap-2 text-xs"
              ><input v-model="alliance.visible" type="checkbox" />{{
                alliance.display_name
              }}</label
            >
            <div class="mt-2 flex gap-2">
              <input
                v-model="alliance.presentation_color"
                type="color"
                :aria-label="t('territory.layerColor', { alliance: alliance.display_name })"
              /><label class="flex items-center gap-1 text-xs"
                ><input v-model="alliance.locked" type="checkbox" :disabled="!canEdit" />{{
                  t('territory.lockLayer')
                }}</label
              >
            </div>
          </div>
          <Link
            v-if="territory.plan.scope === 'kingdom'"
            :href="`/territory/${territory.plan.id}/alliances`"
            class="ks-command-link mt-3 inline-flex"
            data-variant="secondary"
          >
            {{ t('territory.layers') }}
          </Link>
        </div>

        <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
          <p class="ks-kicker">{{ t('territory.mapLayers') }}</p>
          <label class="mt-2 flex items-center gap-2 text-xs"
            ><input v-model="showCoverage" type="checkbox" />{{
              t('territory.showCoverage')
            }}</label
          >
          <label class="mt-2 flex items-center gap-2 text-xs"
            ><input v-model="showStructures" type="checkbox" />{{
              t('territory.showStructures')
            }}</label
          >
          <label class="mt-2 flex items-center gap-2 text-xs"
            ><input v-model="showZones" type="checkbox" />{{ t('territory.showZones') }}</label
          >
        </div>

        <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
          <h2 class="ks-display text-lg font-semibold">{{ t('territory.hiveBuilder') }}</h2>
          <select v-model="hiveStyle" class="ks-input mt-2 w-full">
            <option value="swirl">{{ t('territory.swirlHive') }}</option>
            <option value="banner_pad">{{ t('territory.bannerPadHive') }}</option>
          </select>
          <div class="mt-2 grid grid-cols-2 gap-2">
            <label class="text-xs"
              >X<input
                v-model.number="hiveCenterX"
                type="number"
                :min="mapMinX"
                :max="mapMaxX"
                class="ks-input mt-1 w-full" /></label
            ><label class="text-xs"
              >Y<input
                v-model.number="hiveCenterY"
                type="number"
                :min="mapMinY"
                :max="mapMaxY"
                class="ks-input mt-1 w-full"
            /></label>
          </div>
          <label class="mt-2 block text-xs"
            >{{ t('territory.cityCount')
            }}<input
              v-model.number="hiveCityCount"
              type="number"
              min="1"
              max="100"
              class="ks-input mt-1 w-full"
          /></label>
          <AppButton
            class="mt-2 w-full"
            :busy="busy"
            :disabled="!canEdit || Boolean(activeAlliance?.locked)"
            @click="generateHivePreview"
            >{{ t('territory.previewHive') }}</AppButton
          >
          <div
            v-if="hivePreview.length"
            class="mt-2 rounded border border-[var(--ks-border)] p-2 text-xs"
          >
            <p>{{ t('territory.previewObjects', { count: hivePreview.length }) }}</p>
            <p v-if="hivePreviewValidation.violations.length" class="mt-1 text-red-200">
              {{
                t('territory.previewViolations', { count: hivePreviewValidation.violations.length })
              }}
            </p>
            <AppButton
              class="mt-2"
              :disabled="hivePreviewValidation.violations.length > 0"
              @click="applyHivePreview"
              >{{ t('territory.placeHive') }}</AppButton
            >
          </div>
        </div>

        <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
          <p class="ks-kicker">{{ t('territory.tcBlock') }}</p>
          <div class="mt-2 grid grid-cols-2 gap-2">
            <label class="text-xs"
              >{{ t('territory.columns')
              }}<input
                v-model.number="stampColumns"
                type="number"
                min="1"
                max="10"
                class="ks-input mt-1 w-full" /></label
            ><label class="text-xs"
              >{{ t('territory.rows')
              }}<input
                v-model.number="stampRows"
                type="number"
                min="1"
                max="10"
                class="ks-input mt-1 w-full"
            /></label>
          </div>
          <AppButton
            class="mt-2 w-full"
            :disabled="!canEdit || Boolean(activeAlliance?.locked)"
            @click="stampCities"
            >{{ t('territory.stampCities') }}</AppButton
          >
        </div>

        <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
          <p class="ks-kicker">{{ t('territory.editing') }}</p>
          <div class="mt-3 grid grid-cols-2 gap-2">
            <button class="ks-command-link" :disabled="!history.length" @click="undo">
              {{ t('territory.undo') }}</button
            ><button class="ks-command-link" :disabled="!future.length" @click="redo">
              {{ t('territory.redo') }}</button
            ><button
              class="ks-command-link"
              :disabled="!selectedKeys.length"
              @click="duplicateSelected"
            >
              {{ t('territory.duplicate') }}</button
            ><button
              class="ks-command-link"
              :disabled="selectedKeys.length < 2"
              @click="groupSelected"
            >
              {{ t('territory.group') }}</button
            ><button
              class="ks-command-link"
              :disabled="!selectedKeys.length"
              @click="ungroupSelected"
            >
              {{ t('territory.ungroup') }}</button
            ><button
              class="ks-command-link"
              :disabled="!selectedKeys.length"
              @click="rotateSelected()"
            >
              {{ t('territory.rotate') }}</button
            ><button
              class="ks-command-link col-span-2"
              :disabled="!selectedKeys.length"
              @click="removeSelected"
            >
              {{ t('territory.deleteSelected') }}
            </button>
          </div>
        </div>
      </aside>

      <main class="min-w-0">
        <TerritoryCanvas
          ref="canvas"
          v-model:selected-keys="selectedKeys"
          :label="t('territory.canvasLabel')"
          :map="territory.map.data"
          :alliances="alliances"
          :objects="objects"
          :tool="tool"
          :placement-type="placementType"
          :active-alliance-key="activeAllianceKey"
          :read-only="!canEdit"
          :show-coverage="showCoverage"
          :show-structures="showStructures"
          :show-zones="showZones"
          @move="move"
          @place="place"
        />
        <section class="ks-surface mt-4 p-4" aria-labelledby="validation-heading">
          <div class="flex items-center justify-between gap-3">
            <h2 id="validation-heading" class="ks-display text-xl font-semibold">
              {{ t('territory.validation') }}
            </h2>
            <span
              class="ks-status"
              :data-tone="
                validation.violations.length
                  ? 'danger'
                  : validation.warnings.length
                    ? 'warning'
                    : 'success'
              "
              >{{
                validation.violations.length
                  ? t('territory.invalid')
                  : validation.warnings.length
                    ? t('territory.validWithWarnings')
                    : t('territory.valid')
              }}</span
            >
          </div>
          <ul
            v-if="validation.violations.length || validation.warnings.length"
            class="mt-3 space-y-2 text-sm"
          >
            <li
              v-for="item in validation.violations"
              :key="`v-${item.code}-${item.object_key}`"
              class="text-red-200"
            >
              {{ item.message }}
            </li>
            <li
              v-for="item in validation.warnings"
              :key="`w-${item.code}-${item.object_key}`"
              class="text-amber-200"
            >
              {{ item.message }}
            </li>
          </ul>
          <p
            v-if="
              !validation.violations.length &&
              !validation.warnings.length &&
              !validation.suggestions.length
            "
            class="mt-2 text-sm text-[var(--ks-muted)]"
          >
            {{ t('territory.noValidationIssues') }}
          </p>
        </section>

        <section
          v-if="validation.suggestions.length"
          class="ks-surface mt-4 p-4"
          aria-labelledby="planning-suggestions-heading"
        >
          <h2 id="planning-suggestions-heading" class="ks-display text-xl font-semibold">
            {{ t('territory.suggestions') }}
          </h2>
          <ul class="mt-3 space-y-2 text-sm text-sky-100">
            <li v-for="item in validation.suggestions" :key="`s-${item.code}-${item.object_key}`">
              {{ item.message }}
            </li>
          </ul>
        </section>

        <section class="ks-surface mt-4 p-4" aria-labelledby="governor-assignment-heading">
          <h2 id="governor-assignment-heading" class="ks-display text-xl font-semibold">
            {{ t('territory.governorAssignment') }}
          </h2>
          <div v-if="governorCities.length" class="mt-3 grid gap-3 md:grid-cols-2">
            <label
              v-for="object in governorCities"
              :key="object.key"
              class="rounded border border-[var(--ks-border)] p-3 text-sm"
            >
              <span class="font-semibold">
                {{
                  object.label || object.external_player_name || t('territory.types.governor_city')
                }}
              </span>
              <select
                v-if="governorOptionsFor(object).length"
                :value="object.player_id ?? ''"
                class="ks-input mt-2 w-full"
                :disabled="!editable(object)"
                :aria-label="t('territory.governorAssignment')"
                @change="assignGovernor(object, ($event.target as HTMLSelectElement).value)"
              >
                <option value="">{{ t('territory.unassignedGovernor') }}</option>
                <option
                  v-for="governor in governorOptionsFor(object)"
                  :key="governor.id"
                  :value="governor.id"
                >
                  {{ governor.name }}
                </option>
              </select>
              <input
                v-else
                :value="object.external_player_name ?? ''"
                type="text"
                maxlength="160"
                class="ks-input mt-2 w-full"
                :disabled="!editable(object)"
                :placeholder="t('territory.externalGovernorName')"
                :aria-label="t('territory.externalGovernorName')"
                @focus="beginExactEdit"
                @change="assignExternalGovernor(object, ($event.target as HTMLInputElement).value)"
              />
            </label>
          </div>
        </section>

        <details class="ks-surface mt-4 p-4" open>
          <summary class="ks-display cursor-pointer text-xl font-semibold">
            {{ t('territory.placedObjects', { count: visibleObjects.length }) }}
          </summary>
          <label class="mt-3 block text-sm"
            >{{ t('territory.filterObjects')
            }}<input v-model="objectFilter" class="ks-input mt-1 w-full"
          /></label>
          <div class="mt-3 max-h-[32rem] overflow-auto">
            <table class="w-full min-w-[46rem] text-sm">
              <thead>
                <tr class="text-start text-xs text-[var(--ks-muted)]">
                  <th class="p-2 text-start">{{ t('territory.object') }}</th>
                  <th class="p-2 text-start">{{ t('territory.alliance') }}</th>
                  <th class="p-2 text-start">X</th>
                  <th class="p-2 text-start">Y</th>
                  <th class="p-2 text-start">{{ t('territory.state') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="object in visibleObjects"
                  :key="object.key"
                  class="border-t border-[var(--ks-border)]"
                  :class="selectedKeys.includes(object.key) ? 'bg-white/[.04]' : ''"
                >
                  <td class="p-2">
                    <button
                      class="text-start font-semibold text-[var(--ks-gold-bright)]"
                      @click="selectObject(object)"
                    >
                      {{
                        object.label ||
                        object.external_player_name ||
                        t(`territory.types.${object.type}`)
                      }}
                    </button>
                  </td>
                  <td class="p-2">{{ allianceFor(object)?.display_name }}</td>
                  <td class="p-2">
                    <input
                      v-model.number="object.x"
                      type="number"
                      :min="mapMinX"
                      :max="mapMaxX"
                      class="ks-input w-24 px-2 py-1"
                      :disabled="!editable(object)"
                      :aria-label="`${object.label ?? object.type} X`"
                      @focus="beginExactEdit"
                    />
                  </td>
                  <td class="p-2">
                    <input
                      v-model.number="object.y"
                      type="number"
                      :min="mapMinY"
                      :max="mapMaxY"
                      class="ks-input w-24 px-2 py-1"
                      :disabled="!editable(object)"
                      :aria-label="`${object.label ?? object.type} Y`"
                      @focus="beginExactEdit"
                    />
                  </td>
                  <td class="p-2">
                    <span v-if="objectIssues.get(object.key)?.length" class="text-amber-100">{{
                      objectIssues.get(object.key)?.join(' ')
                    }}</span
                    ><span v-else class="text-emerald-200">{{ t('territory.valid') }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </details>
      </main>

      <aside class="space-y-4">
        <section class="ks-surface p-4" aria-labelledby="analysis-heading">
          <p class="ks-kicker">{{ t('territory.analyze') }}</p>
          <h2 id="analysis-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ t('territory.layoutAnalysis') }}
          </h2>
          <div
            v-for="alliance in alliances"
            :key="alliance.key"
            class="mt-4 border-t border-[var(--ks-border)] pt-3"
          >
            <strong>{{ alliance.display_name }}</strong
            ><template v-if="analysis[alliance.key]"
              ><dl class="mt-2 grid grid-cols-2 gap-2 text-xs">
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.coverage') }}</dt>
                  <dd>{{ analysis[alliance.key]?.coverage_percent ?? '—' }}%</dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.uncovered') }}</dt>
                  <dd>
                    {{ formatNumber(analysis[alliance.key]?.uncovered_governor_cities ?? 0) }}
                  </dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.components') }}</dt>
                  <dd>{{ formatNumber(analysis[alliance.key]?.territory_components ?? 0) }}</dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.avgDistance') }}</dt>
                  <dd>{{ analysis[alliance.key]?.bear_distance_tiles.average ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.medianDistance') }}</dt>
                  <dd>{{ analysis[alliance.key]?.bear_distance_tiles.median ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.maxDistance') }}</dt>
                  <dd>{{ analysis[alliance.key]?.bear_distance_tiles.max ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.bannerEfficiency') }}</dt>
                  <dd>{{ analysis[alliance.key]?.banner_efficiency ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.violations') }}</dt>
                  <dd>{{ formatNumber(analysis[alliance.key]?.violation_count ?? 0) }}</dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.warnings') }}</dt>
                  <dd>{{ formatNumber(analysis[alliance.key]?.warning_count ?? 0) }}</dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.suggestions') }}</dt>
                  <dd>{{ formatNumber(analysis[alliance.key]?.suggestion_count ?? 0) }}</dd>
                </div>
              </dl></template
            >
          </div>
        </section>
        <MarchAnalysisPanel
          :alliances="alliances"
          :objects="objects"
          :analysis="analysis"
          :preferences="preferences"
          :can-edit="canEdit"
          @select-trap="setSelectedBear($event.allianceKey, $event.trapKey)"
        />
        <section class="ks-surface p-4">
          <p class="ks-kicker">{{ t('territory.preferences') }}</p>
          <label class="mt-3 block text-sm"
            >{{ t('territory.preferredBearRadius')
            }}<input
              v-model.number="preferences.preferred_bear_radius_tiles"
              type="number"
              min="1"
              :max="mapDistanceLimit"
              class="ks-input mt-1 w-full"
              @focus="beginExactEdit" /></label
          ><label class="mt-3 block text-sm"
            >{{ t('territory.marchSecondsPerTile')
            }}<input
              v-model.number="preferences.march_seconds_per_tile"
              type="number"
              min="0.01"
              max="60"
              step="0.01"
              class="ks-input mt-1 w-full"
              @focus="beginExactEdit"
          /></label>
          <p class="mt-2 text-xs text-[var(--ks-muted)]">
            {{ t('territory.marchAssumptionHelp') }}
          </p>
        </section>
        <section class="ks-surface p-4">
          <p class="ks-kicker">{{ t('territory.compare') }}</p>
          <select
            v-model="compareRevisionId"
            class="ks-input mt-2 w-full"
            @change="compareRevision"
          >
            <option value="">{{ t('territory.chooseRevision') }}</option>
            <option v-for="item in territory.revisions" :key="item.id" :value="item.id">
              #{{ item.revision_number }} ·
              {{ item.published_at ? formatDate(item.published_at) : '—' }}
            </option>
          </select>
          <div v-if="comparison" class="mt-3 text-xs">
            <p>{{ t('territory.compareAgainst', { revision: comparison.revision }) }}</p>
            <div
              v-for="alliance in alliances"
              :key="alliance.key"
              class="mt-2 border-t border-[var(--ks-border)] pt-2"
            >
              <strong>{{ alliance.display_name }}</strong>
              <p>
                {{ t('territory.coverage') }}:
                {{ comparison.previous[alliance.key]?.coverage_percent ?? '—' }} →
                {{ comparison.current[alliance.key]?.coverage_percent ?? '—' }}
              </p>
              <p>
                {{ t('territory.uncovered') }}:
                {{ comparison.previous[alliance.key]?.uncovered_governor_cities ?? 0 }} →
                {{ comparison.current[alliance.key]?.uncovered_governor_cities ?? 0 }}
              </p>
              <p>
                {{ t('territory.types.banner') }}:
                {{ comparison.previous[alliance.key]?.counts.banner ?? 0 }} →
                {{ comparison.current[alliance.key]?.counts.banner ?? 0 }}
              </p>
              <p>
                {{ t('territory.avgDistance') }}:
                {{ comparison.previous[alliance.key]?.bear_distance_tiles.average ?? '—' }} →
                {{ comparison.current[alliance.key]?.bear_distance_tiles.average ?? '—' }}
              </p>
              <p>
                {{ t('territory.medianDistance') }}:
                {{ comparison.previous[alliance.key]?.bear_distance_tiles.median ?? '—' }} →
                {{ comparison.current[alliance.key]?.bear_distance_tiles.median ?? '—' }}
              </p>
              <p>
                {{ t('territory.maxDistance') }}:
                {{ comparison.previous[alliance.key]?.bear_distance_tiles.max ?? '—' }} →
                {{ comparison.current[alliance.key]?.bear_distance_tiles.max ?? '—' }}
              </p>
              <p>
                {{ t('territory.violations') }}:
                {{ comparison.previous[alliance.key]?.violation_count ?? 0 }} →
                {{ comparison.current[alliance.key]?.violation_count ?? 0 }}
              </p>
              <p>
                {{ t('territory.warnings') }}:
                {{ comparison.previous[alliance.key]?.warning_count ?? 0 }} →
                {{ comparison.current[alliance.key]?.warning_count ?? 0 }}
              </p>
              <p>
                {{ t('territory.suggestions') }}:
                {{ comparison.previous[alliance.key]?.suggestion_count ?? 0 }} →
                {{ comparison.current[alliance.key]?.suggestion_count ?? 0 }}
              </p>
            </div>
          </div>
        </section>
        <section class="ks-surface p-4">
          <p class="ks-kicker">{{ t('territory.clone') }}</p>
          <input v-model="cloneName" maxlength="160" class="ks-input mt-2 w-full" /><AppButton
            class="mt-2"
            :busy="busy"
            :disabled="!cloneName.trim()"
            @click="clonePlan"
            >{{ t('territory.clonePlan') }}</AppButton
          >
        </section>
      </aside>
    </div>

    <section class="ks-surface mt-4 p-4">
      <div class="flex flex-wrap gap-2">
        <AppButton
          :busy="busy"
          :disabled="!canEdit || validation.violations.length > 0"
          @click="save"
          >{{ t('territory.save') }}</AppButton
        ><AppButton
          :busy="busy"
          :disabled="!canEdit || validation.violations.length > 0"
          @click="publish"
          >{{ t('territory.publish') }}</AppButton
        ><button class="ks-command-link" @click="exportJson">{{ t('territory.exportJson') }}</button
        ><button class="ks-command-link" @click="exportPng">{{ t('territory.exportPng') }}</button
        ><button class="ks-command-link" @click="exportSvg">{{ t('territory.exportSvg') }}</button
        ><label class="ks-command-link cursor-pointer"
          >{{ t('territory.importJson')
          }}<input
            type="file"
            accept="application/json,.json"
            class="sr-only"
            @change="importFile" /></label
        ><button
          v-if="canEdit"
          class="ks-command-link"
          data-variant="danger"
          @click="dialogAction = { kind: 'archive' }"
        >
          {{ t('territory.archive') }}
        </button>
      </div>
      <div v-if="importPreview" class="mt-4 rounded border border-[var(--ks-border)] p-3">
        <p class="text-sm font-semibold">{{ t('territory.importPreview') }}</p>
        <dl class="mt-2 grid gap-2 text-xs sm:grid-cols-4">
          <div>
            <dt class="text-[var(--ks-muted)]">{{ t('territory.layers') }}</dt>
            <dd>{{ formatNumber(importPreview.alliances.length) }}</dd>
          </div>
          <div>
            <dt class="text-[var(--ks-muted)]">{{ t('territory.object') }}</dt>
            <dd>{{ formatNumber(importPreview.objects.length) }}</dd>
          </div>
          <div>
            <dt class="text-[var(--ks-muted)]">{{ t('territory.violations') }}</dt>
            <dd>{{ formatNumber(importPreview.validation.violations.length) }}</dd>
          </div>
          <div>
            <dt class="text-[var(--ks-muted)]">{{ t('territory.warnings') }}</dt>
            <dd>{{ formatNumber(importPreview.validation.warnings.length) }}</dd>
          </div>
        </dl>
        <p class="mt-2 text-xs text-[var(--ks-muted)]">
          {{ importPreview.map.source_label }} · {{ importPreview.map.id }}
        </p>
        <ul
          v-if="importPreview.validation.violations.length"
          class="mt-2 space-y-1 text-xs text-red-200"
        >
          <li
            v-for="item in importPreview.validation.violations"
            :key="`import-v-${item.code}-${item.object_key}`"
          >
            {{ item.message }}
          </li>
        </ul>
        <ul
          v-if="importPreview.validation.warnings.length"
          class="mt-2 space-y-1 text-xs text-amber-200"
        >
          <li
            v-for="item in importPreview.validation.warnings"
            :key="`import-w-${item.code}-${item.object_key}`"
          >
            {{ item.message }}
          </li>
        </ul>
        <AppButton class="mt-3" :disabled="!importPreview.can_commit" @click="applyImport">
          {{ t('territory.applyImport') }}
        </AppButton>
      </div>
    </section>

    <section v-if="territory.revisions.length" class="ks-surface mt-4 p-4">
      <p class="ks-kicker">{{ t('territory.revisions') }}</p>
      <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="item in territory.revisions"
          :key="item.id"
          class="rounded border border-[var(--ks-border)] p-3"
        >
          <strong>#{{ item.revision_number }}</strong>
          <p class="mt-1 text-xs text-[var(--ks-muted)]">
            {{ item.published_at ? formatDate(item.published_at) : '—' }}
          </p>
          <button
            v-if="canEdit"
            class="ks-command-link mt-2"
            @click="dialogAction = { kind: 'restore', revision: item }"
          >
            {{ t('territory.restore') }}
          </button>
        </div>
      </div>
    </section>

    <ConfirmActionDialog
      id="territory-plan-action-dialog"
      :open="dialogAction !== null"
      :title="dialogAction?.kind === 'restore' ? t('territory.restore') : t('territory.archive')"
      :description="
        dialogAction?.kind === 'restore'
          ? t('territory.restoreConfirm')
          : t('territory.archiveConfirm')
      "
      :confirm-label="
        dialogAction?.kind === 'restore' ? t('territory.restore') : t('territory.archive')
      "
      :cancel-label="t('territory.cancel')"
      :busy="busy"
      :danger="dialogAction?.kind === 'archive'"
      @cancel="dialogAction = null"
      @confirm="
        dialogAction?.kind === 'restore' ? restoreRevision(dialogAction.revision) : archive()
      "
    />
  </AppLayout>
</template>
