<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, onMounted, onUnmounted, ref, watch } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import ActionNotice from '@/components/ui/ActionNotice.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import MarchAnalysisPanel from '@/features/territory-planner/components/MarchAnalysisPanel.vue';
import TerritoryCanvas from '@/features/territory-planner/components/TerritoryCanvas.vue';
import {
  createEditorSession,
  TerritoryRequestError,
  type SaveReceipt,
  type SaveRequest,
} from '@/features/territory-planner/engine/editor-session';
import {
  assignCanonicalGovernorIdentity,
  assignExternalGovernorIdentity,
  materializeHiveProposal,
  objectIsLocked,
  requireAtomicEditableSelection,
  rotateObjectsAtomic,
  selectionPivot,
  translateObjectsAtomic,
} from '@/features/territory-planner/engine/commands';
import { analyzeLayout, validatePlacement } from '@/features/territory-planner/engine/geometry';
import {
  editableAllianceKeys,
  privateShareFragment,
  reviewableAllianceKeys,
  uniquePlayerOptions,
  type CollaborationGrant,
  type CollaborationOverview as CollaborationOverviewBase,
} from '@/features/territory-planner/engine/collaboration';
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
import type { ExportMetadata, ExportScope } from '@/features/territory-planner/engine/export';
import { buildLayoutDocument } from '@/features/territory-planner/engine/interchange';
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
  layout_checksum: string;
  governor_options: Record<string, Array<{ id: string; name: string }>>;
  collaboration_player_options: Array<{ id: string; name: string }>;
};
type Tool = 'select' | 'pan' | 'place';
type DialogAction = { kind: 'archive' } | { kind: 'restore'; revision: Revision } | null;
type RevisionSnapshot = {
  alliances: PlanAlliance[];
  groups: PlanGroup[];
  objects: PlanObject[];
  plan: { planning_preferences?: PlanningPreferences };
};
type EditorLayout = {
  alliances: PlanAlliance[];
  groups: PlanGroup[];
  objects: PlanObject[];
  preferences: PlanningPreferences;
};
type ServerSnapshot = {
  schema_version: number;
  plan: { planning_preferences?: PlanningPreferences };
  alliances: PlanAlliance[];
  groups: PlanGroup[];
  objects: PlanObject[];
};
type ServerMutationReceipt = {
  plan_id: string;
  revision: number;
  status: string;
  published_revision_id: string | null;
  snapshot: ServerSnapshot | null;
  layout_checksum: string | null;
  mutation_id: string | null;
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
type RecoveryDraft = {
  id: string;
  base_revision: number;
  current_revision: number;
  stale: boolean;
  map_dataset_id: string;
  map_dataset_checksum: string;
  document_checksum: string;
  document: EditorLayout;
  updated_at: string | null;
  expires_at: string | null;
};
type CollaborationComment = {
  id: string;
  player_id: string;
  object_key: string;
  body: string;
  head_revision: number;
  created_at: string | null;
};
type CollaborationReview = {
  id: string;
  reviewer_player_id: string;
  head_revision: number;
  decision: 'approved' | 'changes_requested';
  note: string | null;
  stale: boolean;
  created_at: string | null;
};
type CollaborationShare = {
  id: string;
  territory_plan_revision_id: string;
  recipient_player_id: string;
  alliance_keys: string[];
  expires_at: string;
  revoked_at: string | null;
  created_at: string | null;
};
type CollaborationOverview = CollaborationOverviewBase & {
  comments: CollaborationComment[];
  comments_after: string | null;
  reviews: CollaborationReview[];
  reviews_before: string | null;
  grants: CollaborationGrant[];
  grants_after: string | null;
  shares: CollaborationShare[];
  shares_before: string | null;
};

function cloneJson<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T;
}

const props = defineProps<{
  user: { name: string; email: string };
  activePlayer: { id: string; name: string; kingdomNumber: number | null };
  territory: TerritoryProp;
}>();
const { t, formatNumber, formatDate, locale } = useLocale();
/** The import preview only appears after a parse, so its panel is split out of this page chunk. */
const TerritoryImportPreview = defineAsyncComponent(
  () => import('@/features/territory-planner/components/TerritoryImportPreview.vue'),
);
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
const exportScope = ref<ExportScope>('map');
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
const recoveryDraft = ref<RecoveryDraft | null>(null);
const recoveryBusy = ref(false);
const collaboration = ref<CollaborationOverview | null>(null);
const collaborationBusy = ref(false);
const collaborationCommentBody = ref('');
const collaborationReviewDecision = ref<'approved' | 'changes_requested'>('approved');
const collaborationReviewNote = ref('');
const collaborationGrantPlayerId = ref('');
const collaborationGrantAllianceKey = ref(alliances.value[0]?.key ?? '');
const collaborationGrantPermission = ref<'review' | 'edit'>('review');
const collaborationGrantExpiresAt = ref('');
const collaborationShareRevisionId = ref(props.territory.revisions[0]?.id ?? '');
const collaborationShareRecipientPlayerId = ref('');
const collaborationShareAllianceKeys = ref<string[]>([]);
const collaborationShareExpiresAt = ref('');
const createdPrivateShare = ref<{ id: string; fragment: string; expires_at: string } | null>(null);
let persistenceSession: ReturnType<typeof createEditorSession<EditorLayout>> | null = null;
let autosaveTimer: ReturnType<typeof setTimeout> | null = null;
let recoveryTimer: ReturnType<typeof setTimeout> | null = null;

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
const governorCities = computed(() =>
  visibleObjects.value.filter((object) => object.type === 'governor_city'),
);
const canManage = computed(() => props.territory.plan.can_manage && status.value !== 'archived');
const delegatedEditableAllianceKeys = computed(() => editableAllianceKeys(collaboration.value));
const reviewableLayerKeys = computed(() =>
  reviewableAllianceKeys(
    collaboration.value,
    alliances.value.map((alliance) => alliance.key),
  ),
);
const canEdit = computed(
  () =>
    status.value !== 'archived' &&
    (canManage.value || delegatedEditableAllianceKeys.value.size > 0),
);
const collaborationPlayers = computed(() =>
  uniquePlayerOptions({ kingdom: props.territory.collaboration_player_options ?? [] }),
);
const selectedObject = computed(() =>
  selectedKeys.value.length === 1
    ? (objects.value.find((object) => object.key === selectedKeys.value[0]) ?? null)
    : null,
);
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

function currentLayout(): EditorLayout {
  return {
    alliances: cloneJson(alliances.value),
    groups: cloneJson(groups.value),
    objects: cloneJson(objects.value),
    preferences: cloneJson(preferences.value),
  };
}
function installLayout(layout: EditorLayout): void {
  alliances.value = cloneJson(layout.alliances);
  groups.value = cloneJson(layout.groups);
  objects.value = cloneJson(layout.objects);
  preferences.value = cloneJson(layout.preferences);
  selectedKeys.value = selectedKeys.value.filter((key) =>
    objects.value.some((object) => object.key === key),
  );
}
function editorReceipt(
  raw: ServerMutationReceipt,
  fallbackMutationId = 'replacement',
): SaveReceipt<EditorLayout> {
  if (!raw.snapshot || !raw.layout_checksum)
    throw new TerritoryRequestError('The server returned an incomplete Territory receipt.', 502);
  return {
    mutation_id: raw.mutation_id ?? fallbackMutationId,
    revision: raw.revision,
    status: raw.status,
    layout_checksum: raw.layout_checksum,
    layout: {
      alliances: raw.snapshot.alliances,
      groups: raw.snapshot.groups,
      objects: raw.snapshot.objects,
      preferences: raw.snapshot.plan.planning_preferences ?? {},
    },
  };
}
function mutationId(): string {
  return crypto.randomUUID();
}

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
function canEditAlliance(allianceKey: string | null): boolean {
  if (!allianceKey || status.value === 'archived') return false;
  const alliance = alliances.value.find((candidate) => candidate.key === allianceKey);
  if (alliance?.locked) return false;
  return canManage.value || delegatedEditableAllianceKeys.value.has(allianceKey);
}
function editableByScope(object: PlanObject): boolean {
  return canEditAlliance(object.alliance_key);
}
function editable(object: PlanObject): boolean {
  return editableByScope(object) && !objectIsLocked(object);
}
function atomicSelection(keys: string[]): PlanObject[] | null {
  const result = requireAtomicEditableSelection(objects.value, keys, editable);
  if (result.ok) return result.selected;
  if (result.reason === 'locked_selection') {
    notice.value = {
      tone: 'warning',
      message: `Selection includes ${result.blockedKeys.length} locked or read-only object(s); no objects were changed.`,
    };
  }
  return null;
}
function governorOptionsFor(object: PlanObject): Array<{ id: string; name: string }> {
  return props.territory.governor_options[object.alliance_key] ?? [];
}
function assignGovernor(object: PlanObject, playerId: string): void {
  if (!editable(object)) return;
  remember();
  const replacement = assignCanonicalGovernorIdentity(object, playerId);
  objects.value = objects.value.map((candidate) =>
    candidate.key === object.key ? replacement : candidate,
  );
}
function assignExternalGovernor(object: PlanObject, name: string): void {
  if (!editable(object)) return;
  remember();
  const replacement = assignExternalGovernorIdentity(object, name);
  objects.value = objects.value.map((candidate) =>
    candidate.key === object.key ? replacement : candidate,
  );
}
function setSelectedBear(allianceKey: string, objectKey: string): void {
  const alliance = alliances.value.find((candidate) => candidate.key === allianceKey);
  if (!canManage.value || alliance?.locked) return;
  remember();
  const selected = { ...(preferences.value.selected_bear_trap_by_alliance ?? {}) };
  if (objectKey) selected[allianceKey] = objectKey;
  else delete selected[allianceKey];
  preferences.value = { ...preferences.value, selected_bear_trap_by_alliance: selected };
}

function place(point: { x: number; y: number }): void {
  const allianceKey = activeAllianceKey.value;
  if (!allianceKey || !canEditAlliance(allianceKey)) return;
  remember();
  const objectKey = key('object');
  objects.value.push({
    key: objectKey,
    alliance_key: allianceKey,
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
  if ((!payload.dx && !payload.dy) || !atomicSelection(payload.keys)) return;
  const result = translateObjectsAtomic(
    objects.value,
    payload.keys,
    payload.dx,
    payload.dy,
    editable,
  );
  if (!result.ok) return;
  remember();
  objects.value = result.objects;
}
function removeSelected(): void {
  const selected = atomicSelection(selectedKeys.value);
  if (!selected?.length) return;
  const keys = new Set(selected.map((object) => object.key));
  remember();
  objects.value = objects.value.filter((object) => !keys.has(object.key));
  selectedKeys.value = [];
}
function duplicateSelected(): void {
  const source = atomicSelection(selectedKeys.value);
  if (!source?.length) return;
  remember();
  const clones = source.map((object, index) => ({
    ...cloneJson(object),
    key: key(`copy${index}`),
    x: object.x + 3,
    y: object.y + 3,
    group_key: null,
    player_id: null,
    external_player_name: object.external_player_name
      ? `${object.external_player_name} copy`
      : null,
  }));
  objects.value.push(...clones);
  selectedKeys.value = clones.map((object) => object.key);
}
function groupSelected(): void {
  const items = atomicSelection(selectedKeys.value);
  if (!items || items.length < 2) return;
  remember();
  const groupKey = key('group');
  groups.value.push({ key: groupKey, label: t('territory.group') });
  const keys = new Set(items.map((item) => item.key));
  objects.value = objects.value.map((object) =>
    keys.has(object.key) ? { ...object, group_key: groupKey } : object,
  );
}
function ungroupSelected(): void {
  const selected = atomicSelection(selectedKeys.value);
  if (!selected?.length) return;
  const affected = new Set(
    selected.map((object) => object.group_key).filter((value): value is string => value !== null),
  );
  if (!affected.size) return;
  const groupMembers = objects.value.filter((object) => affected.has(object.group_key ?? ''));
  if (!atomicSelection(groupMembers.map((object) => object.key))) return;
  remember();
  objects.value = objects.value.map((object) =>
    affected.has(object.group_key ?? '') ? { ...object, group_key: null } : object,
  );
  groups.value = groups.value.filter((group) => !affected.has(group.key));
}
function rotateSelected(direction: 1 | -1 = 1): void {
  const selected = atomicSelection(selectedKeys.value);
  if (!selected?.length) return;
  const groupedKeys = new Set(selected.map((object) => object.group_key).filter(Boolean));
  const rotationKeys = groupedKeys.size
    ? objects.value
        .filter((object) => object.group_key !== null && groupedKeys.has(object.group_key))
        .map((object) => object.key)
    : selected.map((object) => object.key);
  const rotationSelection = atomicSelection(rotationKeys);
  if (!rotationSelection?.length) return;
  const pivot = selectionPivot(props.territory.map.data, rotationSelection);
  const result = rotateObjectsAtomic(
    props.territory.map.data,
    objects.value,
    rotationKeys,
    direction,
    pivot,
    editable,
  );
  if (!result.ok) return;
  remember();
  objects.value = result.objects;
}
function nudge(dx: number, dy: number): void {
  move({ keys: selectedKeys.value, dx, dy });
}
function selectObject(object: PlanObject): void {
  selectedKeys.value = [object.key];
}
function beginExactEdit(): void {
  if (canManage.value) remember();
}

function stampCities(): void {
  const allianceKey = activeAllianceKey.value;
  if (!allianceKey || !canEditAlliance(allianceKey)) return;
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
        alliance_key: allianceKey,
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
  signal?: AbortSignal,
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
  if (signal !== undefined) init.signal = signal;
  if (body !== undefined) init.body = JSON.stringify(body);
  const response = await fetch(url, init);
  const payload = (await response.json().catch(() => ({}))) as Record<string, unknown>;
  if (!response.ok) {
    const errors = payload.errors as Record<string, string[] | string> | undefined;
    const first = errors ? Object.values(errors).flat()[0] : null;
    throw new TerritoryRequestError(
      typeof first === 'string'
        ? first
        : typeof payload.message === 'string'
          ? payload.message
          : t('territory.requestFailed'),
      response.status,
    );
  }
  return payload;
}

async function loadRecoveryDraft(): Promise<void> {
  if (!canEdit.value) return;
  const payload = await jsonRequest(`/territory/${props.territory.plan.id}/recovery`, 'GET');
  recoveryDraft.value = (payload.recovery as RecoveryDraft | null) ?? null;
}

async function persistRecoveryDraft(): Promise<void> {
  if (!canEdit.value || !persistenceSession?.dirty()) return;
  recoveryBusy.value = true;
  try {
    const payload = await jsonRequest(`/territory/${props.territory.plan.id}/recovery`, 'PUT', {
      base_revision: revision.value,
      map_dataset_id: props.territory.map.id,
      map_dataset_checksum: props.territory.map.checksum,
      document: currentLayout(),
    });
    recoveryDraft.value = (payload.recovery as RecoveryDraft | null) ?? null;
  } catch (error) {
    notice.value = {
      tone: 'warning',
      message: error instanceof Error ? error.message : t('territory.recoverySaveFailed'),
    };
  } finally {
    recoveryBusy.value = false;
  }
}

async function discardRecoveryDraft(silent = false): Promise<void> {
  if (!canEdit.value) return;
  try {
    await jsonRequest(`/territory/${props.territory.plan.id}/recovery`, 'DELETE');
    recoveryDraft.value = null;
    if (!silent) notice.value = { tone: 'info', message: t('territory.recoveryDiscarded') };
  } catch (error) {
    if (!silent) {
      notice.value = {
        tone: 'danger',
        message: error instanceof Error ? error.message : t('territory.requestFailed'),
      };
    }
  }
}

function recoverDraft(): void {
  const draft = recoveryDraft.value;
  if (!draft) return;
  if (
    draft.map_dataset_id !== props.territory.map.id ||
    draft.map_dataset_checksum !== props.territory.map.checksum
  ) {
    notice.value = { tone: 'danger', message: t('territory.recoveryMapMismatch') };
    return;
  }
  remember();
  installLayout(cloneJson(draft.document));
  history.value = [];
  future.value = [];
  recoveryDraft.value = null;
  notice.value = {
    tone: draft.stale ? 'warning' : 'info',
    message: draft.stale
      ? t('territory.recoveryRestoredStale', {
          base: draft.base_revision,
          current: draft.current_revision,
        })
      : t('territory.recoveryRestored'),
  };
}

function defaultExpiry(hours: number): string {
  const value = new Date(Date.now() + hours * 60 * 60 * 1000);
  const local = new Date(value.getTime() - value.getTimezoneOffset() * 60_000);
  return local.toISOString().slice(0, 16);
}
function expiryIso(value: string): string {
  const parsed = new Date(value);
  if (!Number.isFinite(parsed.getTime()))
    throw new TerritoryRequestError(t('territory.invalidExpiry'), 422);
  return parsed.toISOString();
}
async function loadCollaboration(): Promise<void> {
  collaborationBusy.value = true;
  try {
    collaboration.value = (await jsonRequest(
      `/territory/${props.territory.plan.id}/collaboration`,
      'GET',
    )) as unknown as CollaborationOverview;
    if (!collaborationGrantPlayerId.value)
      collaborationGrantPlayerId.value = collaborationPlayers.value[0]?.id ?? '';
    if (!collaborationShareRecipientPlayerId.value)
      collaborationShareRecipientPlayerId.value = collaborationPlayers.value[0]?.id ?? '';
    if (!collaborationGrantAllianceKey.value)
      collaborationGrantAllianceKey.value = alliances.value[0]?.key ?? '';
    if (!collaborationShareAllianceKeys.value.length && canManage.value)
      collaborationShareAllianceKeys.value = alliances.value.map((alliance) => alliance.key);
  } finally {
    collaborationBusy.value = false;
  }
}
async function postObjectComment(): Promise<void> {
  const object = selectedObject.value;
  const body = collaborationCommentBody.value.trim();
  if (!object || !body || !reviewableLayerKeys.value.has(object.alliance_key)) return;
  collaborationBusy.value = true;
  try {
    await jsonRequest(`/territory/${props.territory.plan.id}/comments`, 'POST', {
      expected_revision: collaboration.value?.current_revision ?? revision.value,
      object_key: object.key,
      body,
    });
    collaborationCommentBody.value = '';
    await loadCollaboration();
    notice.value = { tone: 'success', message: t('territory.commentAdded') };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    collaborationBusy.value = false;
  }
}
async function submitPlanReview(): Promise<void> {
  const current = collaboration.value;
  if (!current?.can_review) return;
  collaborationBusy.value = true;
  try {
    await jsonRequest(`/territory/${props.territory.plan.id}/reviews`, 'POST', {
      expected_revision: current.current_revision,
      snapshot_checksum: current.current_snapshot_checksum,
      decision: collaborationReviewDecision.value,
      note: collaborationReviewNote.value.trim() || null,
    });
    collaborationReviewNote.value = '';
    await loadCollaboration();
    notice.value = { tone: 'success', message: t('territory.reviewRecorded') };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    collaborationBusy.value = false;
  }
}
async function grantCollaborationAccess(): Promise<void> {
  if (!canManage.value || !collaborationGrantPlayerId.value || !collaborationGrantAllianceKey.value)
    return;
  collaborationBusy.value = true;
  try {
    await jsonRequest(`/territory/${props.territory.plan.id}/access`, 'POST', {
      player_id: collaborationGrantPlayerId.value,
      alliance_key: collaborationGrantAllianceKey.value,
      permission: collaborationGrantPermission.value,
      expires_at: expiryIso(collaborationGrantExpiresAt.value),
    });
    await loadCollaboration();
    notice.value = { tone: 'success', message: t('territory.accessGranted') };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    collaborationBusy.value = false;
  }
}
async function revokeCollaborationAccess(grantId: string): Promise<void> {
  if (!canManage.value) return;
  collaborationBusy.value = true;
  try {
    await jsonRequest(`/territory/${props.territory.plan.id}/access/${grantId}`, 'DELETE');
    await loadCollaboration();
    notice.value = { tone: 'info', message: t('territory.accessRevoked') };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    collaborationBusy.value = false;
  }
}
async function createPrivateShare(): Promise<void> {
  if (
    !canManage.value ||
    !collaborationShareRevisionId.value ||
    !collaborationShareRecipientPlayerId.value ||
    !collaborationShareAllianceKeys.value.length
  )
    return;
  collaborationBusy.value = true;
  try {
    const payload = await jsonRequest(`/territory/${props.territory.plan.id}/shares`, 'POST', {
      revision_id: collaborationShareRevisionId.value,
      recipient_player_id: collaborationShareRecipientPlayerId.value,
      alliance_keys: collaborationShareAllianceKeys.value,
      expires_at: expiryIso(collaborationShareExpiresAt.value),
    });
    const id = String(payload.id ?? '');
    const token = String(payload.token ?? '');
    const expiresAt = String(payload.expires_at ?? '');
    if (!id || !token) throw new TerritoryRequestError(t('territory.requestFailed'), 502);
    createdPrivateShare.value = {
      id,
      fragment: privateShareFragment(id, token),
      expires_at: expiresAt,
    };
    await loadCollaboration();
    notice.value = { tone: 'success', message: t('territory.shareCreated') };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    collaborationBusy.value = false;
  }
}
async function revokePrivateShare(shareId: string): Promise<void> {
  if (!canManage.value) return;
  collaborationBusy.value = true;
  try {
    await jsonRequest(`/territory/${props.territory.plan.id}/shares/${shareId}`, 'DELETE');
    if (createdPrivateShare.value?.id === shareId) createdPrivateShare.value = null;
    await loadCollaboration();
    notice.value = { tone: 'info', message: t('territory.shareRevoked') };
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    collaborationBusy.value = false;
  }
}
async function loadMoreCollaboration(
  kind: 'comments' | 'reviews' | 'grants' | 'shares',
): Promise<void> {
  const current = collaboration.value;
  if (!current) return;
  const cursor =
    kind === 'comments'
      ? current.comments_after
      : kind === 'reviews'
        ? current.reviews_before
        : kind === 'grants'
          ? current.grants_after
          : current.shares_before;
  if (!cursor) return;
  const parameter =
    kind === 'comments'
      ? 'after'
      : kind === 'reviews'
        ? 'review_before'
        : kind === 'grants'
          ? 'grant_after'
          : 'share_before';
  collaborationBusy.value = true;
  try {
    const page = (await jsonRequest(
      `/territory/${props.territory.plan.id}/collaboration?${parameter}=${encodeURIComponent(cursor)}`,
      'GET',
    )) as unknown as CollaborationOverview;
    if (kind === 'comments') {
      current.comments.push(...page.comments);
      current.comments_after = page.comments_after;
    }
    if (kind === 'reviews') {
      current.reviews.push(...page.reviews);
      current.reviews_before = page.reviews_before;
    }
    if (kind === 'grants') {
      current.grants.push(...page.grants);
      current.grants_after = page.grants_after;
    }
    if (kind === 'shares') {
      current.shares.push(...page.shares);
      current.shares_before = page.shares_before;
    }
  } finally {
    collaborationBusy.value = false;
  }
}

function initializePersistence(): void {
  persistenceSession = createEditorSession<EditorLayout>({
    revision: revision.value,
    layoutChecksum: props.territory.layout_checksum,
    read: currentLayout,
    install: installLayout,
    authority: () => `${props.activePlayer.id}:${props.territory.plan.id}`,
    mutationId,
    accepted: (receipt) => {
      revision.value = receipt.revision;
      status.value = receipt.status;
    },
    save: async (request: SaveRequest<EditorLayout>, signal: AbortSignal) => {
      const payload = await jsonRequest(
        `/territory/${props.territory.plan.id}`,
        'PUT',
        {
          expected_revision: request.expected_revision,
          mutation_id: request.mutation_id,
          alliances: request.layout.alliances,
          groups: request.layout.groups,
          objects: request.layout.objects,
          planning_preferences: request.layout.preferences,
        },
        signal,
      );
      return editorReceipt(payload.receipt as ServerMutationReceipt, request.mutation_id);
    },
  });
}

async function flushPersistence(successNotice = true): Promise<void> {
  if (!persistenceSession)
    throw new TerritoryRequestError('Territory persistence is not ready.', 503);
  if (validation.value.violations.length)
    throw new TerritoryRequestError(t('territory.fixViolations'), 422);
  await persistenceSession.flush();
  await discardRecoveryDraft(true);
  if (successNotice)
    notice.value = { tone: 'success', message: t('territory.saved', { revision: revision.value }) };
}

async function save(): Promise<void> {
  if (!canEdit.value) return;
  busy.value = true;
  notice.value = null;
  try {
    await flushPersistence(true);
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
  if (!canManage.value) return;
  if (validation.value.violations.length) {
    notice.value = { tone: 'danger', message: t('territory.fixViolations') };
    return;
  }
  busy.value = true;
  try {
    if (!persistenceSession)
      throw new TerritoryRequestError('Territory persistence is not ready.', 503);
    const publication = await persistenceSession.publication();
    const payload = await jsonRequest(
      `/territory/${props.territory.plan.id}/publish`,
      'POST',
      publication,
    );
    const receipt = payload.receipt as ServerMutationReceipt;
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
  if (!canManage.value) return;
  busy.value = true;
  try {
    await jsonRequest(`/territory/${props.territory.plan.id}`, 'DELETE', {
      expected_revision: revision.value,
    });
    dialogAction.value = null;
    persistenceSession?.dispose();
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
  if (!canManage.value) return;
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
    const receipt = editorReceipt(payload.receipt as ServerMutationReceipt);
    if (!persistenceSession)
      throw new TerritoryRequestError('Territory persistence is not ready.', 503);
    persistenceSession.replace(receipt);
    history.value = [];
    future.value = [];
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
  const allianceKey = activeAllianceKey.value;
  if (!allianceKey || !canEditAlliance(allianceKey)) return;
  busy.value = true;
  try {
    const payload = await jsonRequest('/territory/hive-preview', 'POST', {
      map_dataset_id: props.territory.map.id,
      map_dataset_checksum: props.territory.map.checksum,
      existing_objects: objects.value.map(({ key, type, alliance_key, x, y, rotation }) => ({
        key,
        type,
        alliance_key,
        x,
        y,
        rotation,
      })),
      style: hiveStyle.value,
      alliance_key: activeAllianceKey.value,
      center_x: hiveCenterX.value,
      center_y: hiveCenterY.value,
      city_count: hiveCityCount.value,
      spacing: 1,
      planning_preferences: preferences.value,
    });
    if (payload.status !== 'feasible') {
      const diagnostics = payload.diagnostics as Array<{ message?: string }> | undefined;
      throw new TerritoryRequestError(
        diagnostics?.[0]?.message ?? t('territory.hivePreviewBlocked'),
        422,
      );
    }
    const generated = payload.objects as PlanObject[];
    hivePreview.value = materializeHiveProposal(generated, objects.value.length);
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

function exportDocument() {
  return buildLayoutDocument(
    props.territory.plan,
    revision.value,
    props.territory.map.id,
    props.territory.map.checksum,
    {
      alliances: alliances.value,
      groups: groups.value,
      objects: objects.value,
      preferences: preferences.value,
    },
  );
}
function exportMetadata(): ExportMetadata {
  return {
    title: props.territory.plan.name,
    mapProfile: props.territory.map.source_label,
    observedAt: props.territory.map.observed_at,
    confidence: props.territory.map.confidence,
    exportedAt: new Date().toISOString(),
    mapChecksum: props.territory.map.checksum,
    planRevision: revision.value,
    workingDraft: persistenceSession?.dirty() ?? false,
    locale: locale.value,
  };
}
async function runExport(kind: 'json' | 'svg' | 'png'): Promise<void> {
  if (busy.value) return;
  busy.value = true;
  try {
    // Capture one working revision before the optional export module is loaded.
    const documentJson = JSON.stringify(exportDocument(), null, 2);
    if (new Blob([documentJson]).size > 5_000_000) throw new Error(t('territory.requestFailed'));
    const snapshot = JSON.parse(documentJson) as ReturnType<typeof exportDocument>;
    const metadata = exportMetadata();
    const options = canvas.value?.exportOptions();
    const {
      buildSvg,
      downloadText,
      downloadPngFromSvg,
      exportScopeBounds,
      estimateExportSize,
      EXPORT_FONT_FAMILY,
    } = await import('@/features/territory-planner/engine/export');
    if (kind === 'json') downloadText(`${metadata.title}.json`, documentJson, 'application/json');
    else {
      const viewport = canvas.value?.viewport();
      const bounds = exportScopeBounds({
        scope: exportScope.value,
        map: props.territory.map.data,
        objects: snapshot.objects,
        selectedKeys: selectedKeys.value,
        activeAllianceKey: activeAllianceKey.value,
        ...(viewport ? { viewport } : {}),
      });
      estimateExportSize(bounds);
      const artwork = await import('@/features/territory-planner/engine/artwork-runtime');
      metadata.fontFamily = EXPORT_FONT_FAMILY;
      const version = artwork.artworkVersion();
      if (version) metadata.artworkVersion = version;
      if (options) await artwork.attachExportArtwork(options);
      const svg = buildSvg(
        props.territory.map.data,
        snapshot.alliances,
        snapshot.objects,
        metadata,
        { ...options, bounds },
      );
      if (kind === 'svg') downloadText(`${metadata.title}.svg`, svg, 'image/svg+xml');
      else await downloadPngFromSvg(`${metadata.title}.png`, svg);
    }
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  } finally {
    busy.value = false;
  }
}
function exportJson(): Promise<void> {
  return runExport('json');
}
function exportSvg(): Promise<void> {
  return runExport('svg');
}
function exportPng(): Promise<void> {
  return runExport('png');
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
async function sha256(value: string): Promise<string> {
  const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(value));
  return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
}
async function applyImport(): Promise<void> {
  if (!importPreview.value || !importPreview.value.can_commit || !importDocument.value) return;
  busy.value = true;
  try {
    const documentChecksum = await sha256(importDocument.value);
    const payload = await jsonRequest(`/territory/${props.territory.plan.id}/import`, 'POST', {
      expected_revision: revision.value,
      document: importDocument.value,
      document_checksum: documentChecksum,
    });
    const receipt = editorReceipt(payload.receipt as ServerMutationReceipt);
    if (!persistenceSession)
      throw new TerritoryRequestError('Territory persistence is not ready.', 503);
    persistenceSession.replace(receipt);
    importPreview.value = null;
    importDocument.value = '';
    history.value = [];
    future.value = [];
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
    if (!canEdit.value || !persistenceSession) return;
    if (recoveryTimer) clearTimeout(recoveryTimer);
    recoveryTimer = setTimeout(() => {
      void persistRecoveryDraft();
    }, 900);
    if (autosaveTimer) clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(() => {
      if (!persistenceSession?.dirty() || validation.value.violations.length) return;
      void flushPersistence(false).catch((error) => {
        notice.value = {
          tone:
            error instanceof TerritoryRequestError && error.status === 409 ? 'warning' : 'danger',
          message: error instanceof Error ? error.message : t('territory.requestFailed'),
        };
      });
    }, 2000);
  },
  { deep: true },
);
onMounted(() => {
  initializePersistence();
  collaborationGrantExpiresAt.value = defaultExpiry(24 * 7);
  collaborationShareExpiresAt.value = defaultExpiry(24);
  void loadCollaboration().catch((error) => {
    notice.value = {
      tone: 'warning',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  });
  void loadRecoveryDraft().catch((error) => {
    notice.value = {
      tone: 'warning',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  });
  window.addEventListener('keydown', onKey);
});
onUnmounted(() => {
  if (autosaveTimer) clearTimeout(autosaveTimer);
  if (recoveryTimer) clearTimeout(recoveryTimer);
  persistenceSession?.dispose();
  window.removeEventListener('keydown', onKey);
});
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

    <section
      v-if="recoveryDraft"
      class="ks-surface mt-4 border border-amber-500/40 p-4"
      role="status"
      aria-live="polite"
    >
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="ks-kicker">{{ t('territory.recoveryAvailable') }}</p>
          <p class="mt-1 text-sm text-[var(--ks-muted)]">
            {{
              recoveryDraft.stale
                ? t('territory.recoveryAvailableStale', {
                    base: recoveryDraft.base_revision,
                    current: recoveryDraft.current_revision,
                  })
                : t('territory.recoveryAvailableCurrent', { revision: recoveryDraft.base_revision })
            }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <AppButton :busy="recoveryBusy" @click="recoverDraft">{{
            t('territory.recoverDraft')
          }}</AppButton>
          <AppButton
            data-variant="secondary"
            :busy="recoveryBusy"
            @click="discardRecoveryDraft(false)"
            >{{ t('territory.discardDraft') }}</AppButton
          >
        </div>
      </div>
    </section>

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
            :disabled="!canEditAlliance(activeAllianceKey)"
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
              ><input v-model="alliance.visible" type="checkbox" :disabled="!canManage" />{{
                alliance.display_name
              }}</label
            >
            <div class="mt-2 flex gap-2">
              <input
                v-model="alliance.presentation_color"
                type="color"
                :disabled="!canManage"
                :aria-label="t('territory.layerColor', { alliance: alliance.display_name })"
              /><label class="flex items-center gap-1 text-xs"
                ><input v-model="alliance.locked" type="checkbox" :disabled="!canManage" />{{
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
            :disabled="!canEditAlliance(activeAllianceKey)"
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
            :disabled="!canEditAlliance(activeAllianceKey)"
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
          :map-checksum="territory.map.checksum"
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
          :can-edit="canManage"
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
              :disabled="!canManage"
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
              :disabled="!canManage"
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
            :disabled="!canManage || !cloneName.trim()"
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
          :disabled="!canManage || validation.violations.length > 0"
          @click="publish"
          >{{ t('territory.publish') }}</AppButton
        ><button class="ks-command-link" @click="exportJson">{{ t('territory.exportJson') }}</button
        ><button class="ks-command-link" @click="exportPng">{{ t('territory.exportPng') }}</button
        ><button class="ks-command-link" @click="exportSvg">{{ t('territory.exportSvg') }}</button
        ><label class="flex items-center gap-2 text-xs text-[var(--ks-text-muted)]"
          >{{ t('territory.exportScope')
          }}<select v-model="exportScope" class="ks-input" data-testid="territory-export-scope">
            <option value="map">{{ t('territory.exportScopeMap') }}</option>
            <option value="viewport">{{ t('territory.exportScopeViewport') }}</option>
            <option value="selection">{{ t('territory.exportScopeSelection') }}</option>
            <option value="alliance">{{ t('territory.exportScopeAlliance') }}</option>
          </select></label
        ><label class="ks-command-link cursor-pointer"
          >{{ t('territory.importJson')
          }}<input
            type="file"
            accept="application/json,.json"
            class="sr-only"
            @change="importFile" /></label
        ><button
          v-if="canManage"
          class="ks-command-link"
          data-variant="danger"
          @click="dialogAction = { kind: 'archive' }"
        >
          {{ t('territory.archive') }}
        </button>
      </div>
      <TerritoryImportPreview v-if="importPreview" :preview="importPreview" @commit="applyImport" />
    </section>

    <section class="ks-surface mt-4 p-4" aria-labelledby="territory-collaboration-heading">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="ks-kicker">{{ t('territory.collaboration') }}</p>
          <h2 id="territory-collaboration-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ t('territory.collaborationTitle') }}
          </h2>
        </div>
        <AppButton :busy="collaborationBusy" data-variant="secondary" @click="loadCollaboration">
          {{ t('territory.refreshCollaboration') }}
        </AppButton>
      </div>
      <p class="mt-2 text-sm text-[var(--ks-muted)]">{{ t('territory.collaborationHelp') }}</p>

      <div v-if="collaboration" class="mt-4 grid gap-4 xl:grid-cols-2">
        <fieldset class="rounded border border-[var(--ks-border)] p-3">
          <legend class="px-1 text-sm font-semibold">{{ t('territory.objectComments') }}</legend>
          <p class="text-xs text-[var(--ks-muted)]">
            {{
              selectedObject
                ? selectedObject.label || selectedObject.key
                : t('territory.selectOneObjectForComment')
            }}
          </p>
          <textarea
            v-model="collaborationCommentBody"
            class="ks-input mt-2 min-h-24 w-full"
            maxlength="4000"
            :disabled="!selectedObject || !reviewableLayerKeys.has(selectedObject.alliance_key)"
            :placeholder="t('territory.commentPlaceholder')"
          />
          <AppButton
            class="mt-2"
            :busy="collaborationBusy"
            :disabled="
              !selectedObject ||
              !collaborationCommentBody.trim() ||
              !reviewableLayerKeys.has(selectedObject.alliance_key)
            "
            @click="postObjectComment"
            >{{ t('territory.addComment') }}</AppButton
          >
          <ul class="mt-3 space-y-2 text-xs">
            <li
              v-for="comment in collaboration.comments"
              :key="comment.id"
              class="rounded border border-[var(--ks-border)] p-2"
            >
              <strong>{{ comment.object_key }}</strong> ·
              {{ t('territory.revisionNumber', { revision: comment.head_revision }) }}
              <p class="mt-1 whitespace-pre-wrap">{{ comment.body }}</p>
              <p v-if="comment.created_at" class="mt-1 text-[var(--ks-muted)]">
                {{ formatDate(comment.created_at) }}
              </p>
            </li>
          </ul>
          <button
            v-if="collaboration.comments_after"
            class="ks-command-link mt-2"
            @click="loadMoreCollaboration('comments')"
          >
            {{ t('territory.loadMore') }}
          </button>
        </fieldset>

        <fieldset class="rounded border border-[var(--ks-border)] p-3">
          <legend class="px-1 text-sm font-semibold">{{ t('territory.planReview') }}</legend>
          <select
            v-model="collaborationReviewDecision"
            class="ks-input mt-2 w-full"
            :disabled="!collaboration.can_review"
          >
            <option value="approved">{{ t('territory.reviewApproved') }}</option>
            <option value="changes_requested">{{ t('territory.reviewChangesRequested') }}</option>
          </select>
          <textarea
            v-model="collaborationReviewNote"
            class="ks-input mt-2 min-h-20 w-full"
            maxlength="4000"
            :disabled="!collaboration.can_review"
            :placeholder="t('territory.reviewNotePlaceholder')"
          />
          <AppButton
            class="mt-2"
            :busy="collaborationBusy"
            :disabled="!collaboration.can_review"
            @click="submitPlanReview"
          >
            {{ t('territory.recordReview') }}
          </AppButton>
          <ul class="mt-3 space-y-2 text-xs">
            <li
              v-for="review in collaboration.reviews"
              :key="review.id"
              class="rounded border border-[var(--ks-border)] p-2"
            >
              <strong>{{
                review.decision === 'approved'
                  ? t('territory.reviewApproved')
                  : t('territory.reviewChangesRequested')
              }}</strong>
              · {{ t('territory.revisionNumber', { revision: review.head_revision }) }}
              <span v-if="review.stale" class="ml-1 text-amber-200">{{
                t('territory.staleReview')
              }}</span>
              <p v-if="review.note" class="mt-1 whitespace-pre-wrap">{{ review.note }}</p>
            </li>
          </ul>
          <button
            v-if="collaboration.reviews_before"
            class="ks-command-link mt-2"
            @click="loadMoreCollaboration('reviews')"
          >
            {{ t('territory.loadMore') }}
          </button>
        </fieldset>

        <fieldset v-if="canManage" class="rounded border border-[var(--ks-border)] p-3">
          <legend class="px-1 text-sm font-semibold">{{ t('territory.delegatedAccess') }}</legend>
          <label class="mt-2 block text-xs"
            >{{ t('territory.player') }}
            <select v-model="collaborationGrantPlayerId" class="ks-input mt-1 w-full">
              <option value="">{{ t('territory.choosePlayer') }}</option>
              <option v-for="player in collaborationPlayers" :key="player.id" :value="player.id">
                {{ player.name }}
              </option>
            </select>
          </label>
          <label class="mt-2 block text-xs"
            >{{ t('territory.layer') }}
            <select v-model="collaborationGrantAllianceKey" class="ks-input mt-1 w-full">
              <option v-for="alliance in alliances" :key="alliance.key" :value="alliance.key">
                {{ alliance.display_name }}
              </option>
            </select>
          </label>
          <div class="mt-2 grid gap-2 sm:grid-cols-2">
            <label class="text-xs"
              >{{ t('territory.permission') }}
              <select v-model="collaborationGrantPermission" class="ks-input mt-1 w-full">
                <option value="review">{{ t('territory.permissionReview') }}</option>
                <option value="edit">{{ t('territory.permissionEdit') }}</option>
              </select>
            </label>
            <label class="text-xs"
              >{{ t('territory.expiresAt') }}
              <input
                v-model="collaborationGrantExpiresAt"
                type="datetime-local"
                class="ks-input mt-1 w-full"
              />
            </label>
          </div>
          <AppButton
            class="mt-2"
            :busy="collaborationBusy"
            :disabled="!collaborationGrantPlayerId || !collaborationGrantAllianceKey"
            @click="grantCollaborationAccess"
          >
            {{ t('territory.grantAccess') }}
          </AppButton>
          <ul class="mt-3 space-y-2 text-xs">
            <li
              v-for="grant in collaboration.grants"
              :key="grant.id"
              class="flex flex-wrap items-center justify-between gap-2 rounded border border-[var(--ks-border)] p-2"
            >
              <span
                >{{ grant.alliance_key }} · {{ grant.permission }} ·
                {{ formatDate(grant.expires_at) }}</span
              >
              <button
                v-if="!grant.revoked_at"
                class="ks-command-link"
                data-variant="danger"
                @click="revokeCollaborationAccess(grant.id)"
              >
                {{ t('territory.revoke') }}
              </button>
            </li>
          </ul>
          <button
            v-if="collaboration.grants_after"
            class="ks-command-link mt-2"
            @click="loadMoreCollaboration('grants')"
          >
            {{ t('territory.loadMore') }}
          </button>
        </fieldset>

        <fieldset v-if="canManage" class="rounded border border-[var(--ks-border)] p-3">
          <legend class="px-1 text-sm font-semibold">{{ t('territory.privateSharing') }}</legend>
          <label class="mt-2 block text-xs"
            >{{ t('territory.publishedRevision') }}
            <select v-model="collaborationShareRevisionId" class="ks-input mt-1 w-full">
              <option value="">{{ t('territory.choosePublishedRevision') }}</option>
              <option v-for="item in territory.revisions" :key="item.id" :value="item.id">
                #{{ item.revision_number }}
              </option>
            </select>
          </label>
          <label class="mt-2 block text-xs"
            >{{ t('territory.recipient') }}
            <select v-model="collaborationShareRecipientPlayerId" class="ks-input mt-1 w-full">
              <option value="">{{ t('territory.choosePlayer') }}</option>
              <option v-for="player in collaborationPlayers" :key="player.id" :value="player.id">
                {{ player.name }}
              </option>
            </select>
          </label>
          <fieldset class="mt-2">
            <legend class="text-xs">{{ t('territory.sharedLayers') }}</legend>
            <label
              v-for="alliance in alliances"
              :key="alliance.key"
              class="mt-1 flex items-center gap-2 text-xs"
            >
              <input
                v-model="collaborationShareAllianceKeys"
                type="checkbox"
                :value="alliance.key"
              />{{ alliance.display_name }}
            </label>
          </fieldset>
          <label class="mt-2 block text-xs"
            >{{ t('territory.expiresAt') }}
            <input
              v-model="collaborationShareExpiresAt"
              type="datetime-local"
              class="ks-input mt-1 w-full"
            />
          </label>
          <AppButton
            class="mt-2"
            :busy="collaborationBusy"
            :disabled="
              !collaborationShareRevisionId ||
              !collaborationShareRecipientPlayerId ||
              !collaborationShareAllianceKeys.length
            "
            @click="createPrivateShare"
          >
            {{ t('territory.createPrivateShare') }}
          </AppButton>
          <div
            v-if="createdPrivateShare"
            class="mt-3 rounded border border-emerald-500/40 p-2 text-xs"
            aria-live="polite"
          >
            <p class="font-semibold">{{ t('territory.shareTokenShownOnce') }}</p>
            <code class="mt-1 block break-all">{{ createdPrivateShare.fragment }}</code>
          </div>
          <ul class="mt-3 space-y-2 text-xs">
            <li
              v-for="share in collaboration.shares"
              :key="share.id"
              class="flex flex-wrap items-center justify-between gap-2 rounded border border-[var(--ks-border)] p-2"
            >
              <span
                >{{ t('territory.revisionIdShort', { id: share.territory_plan_revision_id }) }} ·
                {{ formatDate(share.expires_at) }}</span
              >
              <button
                v-if="!share.revoked_at"
                class="ks-command-link"
                data-variant="danger"
                @click="revokePrivateShare(share.id)"
              >
                {{ t('territory.revoke') }}
              </button>
            </li>
          </ul>
          <button
            v-if="collaboration.shares_before"
            class="ks-command-link mt-2"
            @click="loadMoreCollaboration('shares')"
          >
            {{ t('territory.loadMore') }}
          </button>
        </fieldset>
      </div>
      <p v-else class="mt-4 text-sm text-[var(--ks-muted)]">
        {{ t('territory.collaborationLoading') }}
      </p>
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
            v-if="canManage"
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
