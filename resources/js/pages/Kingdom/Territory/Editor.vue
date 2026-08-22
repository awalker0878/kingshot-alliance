<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import ActionNotice from '@/components/ui/ActionNotice.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { buildSvg, downloadText } from '@/features/territory-planner/engine/export';
import { analyzeLayout, validatePlacement } from '@/features/territory-planner/engine/geometry';
import type { MapData, PlanAlliance, PlanGroup, PlanObject, PlanningPreferences, TerritoryObjectType } from '@/features/territory-planner/engine/types';
import TerritoryCanvas from '@/features/territory-planner/components/TerritoryCanvas.vue';
import { useLocale } from '@/localization';

type PlanSummary = { id: string; scope: 'alliance' | 'kingdom'; kingdom_id: string; owner_alliance_id: string | null; name: string; status: string; revision: number; map_dataset_id: string; map_dataset_checksum: string; planning_preferences: PlanningPreferences; can_manage: boolean };
type Revision = { id: string; revision_number: number; map_dataset_id: string; map_dataset_checksum: string; snapshot_checksum: string; published_at: string | null };
type TerritoryProp = { plan: PlanSummary; alliances: PlanAlliance[]; groups: PlanGroup[]; objects: PlanObject[]; validation: unknown; analysis: unknown; map: { id: string; checksum: string; source_label: string; source_uri: string | null; confidence: string; observed_at: string; data: MapData }; revisions: Revision[] };
type Tool = 'select' | 'pan' | 'place';

const props = defineProps<{ user: { name: string; email: string }; activePlayer: { id: string; name: string; kingdomNumber: number | null }; territory: TerritoryProp }>();
const { t, formatNumber, formatDate } = useLocale();
const alliances = ref<PlanAlliance[]>(structuredClone(props.territory.alliances));
const groups = ref<PlanGroup[]>(structuredClone(props.territory.groups));
const objects = ref<PlanObject[]>(structuredClone(props.territory.objects));
const preferences = ref<PlanningPreferences>(structuredClone(props.territory.plan.planning_preferences ?? {}));
const revision = ref(props.territory.plan.revision);
const status = ref(props.territory.plan.status);
const selectedKeys = ref<string[]>([]);
const activeAllianceKey = ref(alliances.value[0]?.key ?? null);
const tool = ref<Tool>('select');
const placementType = ref<TerritoryObjectType>('governor_city');
const notice = ref<{ tone: 'success' | 'warning' | 'danger' | 'info'; message: string } | null>(null);
const busy = ref(false);
const history = ref<string[]>([]);
const future = ref<string[]>([]);
const canvas = ref<InstanceType<typeof TerritoryCanvas> | null>(null);
const archiveOpen = ref(false);
const importPreview = ref<Record<string, unknown> | null>(null);
const importDocument = ref<string | null>(null);
const compareRevisionId = ref<string | null>(null);
const comparison = ref<Record<string, unknown> | null>(null);

const validation = computed(() => validatePlacement(props.territory.map.data, objects.value, preferences.value));
const analysis = computed(() => analyzeLayout(props.territory.map.data, objects.value, preferences.value));
const selectedObjects = computed(() => objects.value.filter((object) => selectedKeys.value.includes(object.key)));
const activeAlliance = computed(() => alliances.value.find((alliance) => alliance.key === activeAllianceKey.value) ?? null);
const canEdit = computed(() => props.territory.plan.can_manage && status.value !== 'archived');

function snapshot(): string {
  return JSON.stringify({ alliances: alliances.value, groups: groups.value, objects: objects.value, preferences: preferences.value });
}
function remember(): void {
  history.value.push(snapshot());
  if (history.value.length > 100) history.value.shift();
  future.value = [];
}
function restoreSnapshot(value: string): void {
  const state = JSON.parse(value) as { alliances: PlanAlliance[]; groups: PlanGroup[]; objects: PlanObject[]; preferences: PlanningPreferences };
  alliances.value = state.alliances; groups.value = state.groups; objects.value = state.objects; preferences.value = state.preferences;
  selectedKeys.value = selectedKeys.value.filter((key) => objects.value.some((object) => object.key === key));
}
function undo(): void { const value = history.value.pop(); if (!value) return; future.value.push(snapshot()); restoreSnapshot(value); }
function redo(): void { const value = future.value.pop(); if (!value) return; history.value.push(snapshot()); restoreSnapshot(value); }
function key(prefix: string): string { return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`; }

function place(point: { x: number; y: number }): void {
  if (!canEdit.value || !activeAllianceKey.value) return;
  remember();
  const objectKey = key('object');
  objects.value.push({ key: objectKey, alliance_key: activeAllianceKey.value, group_key: null, type: placementType.value, player_id: null, external_player_name: null, label: null, x: point.x, y: point.y, rotation: 0, sort_order: objects.value.length, metadata: {} });
  selectedKeys.value = [objectKey];
}
function move(payload: { keys: string[]; dx: number; dy: number }): void {
  if (!canEdit.value || (!payload.dx && !payload.dy)) return;
  remember();
  objects.value = objects.value.map((object) => payload.keys.includes(object.key) ? { ...object, x: object.x + payload.dx, y: object.y + payload.dy } : object);
}
function removeSelected(): void { if (!canEdit.value || !selectedKeys.value.length) return; remember(); objects.value = objects.value.filter((object) => !selectedKeys.value.includes(object.key)); selectedKeys.value = []; }
function duplicateSelected(): void { if (!canEdit.value || !selectedKeys.value.length) return; remember(); const clones = selectedObjects.value.map((object, index) => ({ ...structuredClone(object), key: key(`copy${index}`), x: object.x + 3, y: object.y + 3, group_key: null })); objects.value.push(...clones); selectedKeys.value = clones.map((object) => object.key); }
function groupSelected(): void { if (!canEdit.value || selectedKeys.value.length < 2) return; remember(); const groupKey = key('group'); groups.value.push({ key: groupKey, label: t('territory.group') }); objects.value = objects.value.map((object) => selectedKeys.value.includes(object.key) ? { ...object, group_key: groupKey } : object); }
function ungroupSelected(): void { if (!canEdit.value) return; remember(); const affected = new Set(selectedObjects.value.map((object) => object.group_key).filter((value): value is string => value !== null)); objects.value = objects.value.map((object) => affected.has(object.group_key ?? '') ? { ...object, group_key: null } : object); groups.value = groups.value.filter((group) => !affected.has(group.key)); }
function rotateSelected(direction = 1): void { if (!canEdit.value || !selectedKeys.value.length) return; remember(); objects.value = objects.value.map((object) => selectedKeys.value.includes(object.key) ? { ...object, rotation: (object.rotation + direction * 90 + 360) % 360 } : object); }
function nudge(dx: number, dy: number): void { if (!canEdit.value || !selectedKeys.value.length) return; move({ keys: selectedKeys.value, dx, dy }); }
function addExternalAlliance(): void { if (!canEdit.value) return; remember(); const allianceKey = key('alliance'); alliances.value.push({ key: allianceKey, alliance_id: null, external_name: t('territory.externalAlliance'), external_tag: null, display_name: t('territory.externalAlliance'), presentation_color: '#c4874e', sort_order: alliances.value.length, visible: true, locked: false }); activeAllianceKey.value = allianceKey; }

function csrfToken(): string { return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''; }
async function jsonRequest(url: string, method: string, body: unknown): Promise<Record<string, unknown>> {
  const response = await fetch(url, { method, credentials: 'same-origin', redirect: 'follow', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) });
  const payload = (await response.json().catch(() => ({}))) as Record<string, unknown>;
  if (!response.ok) throw new Error(typeof payload.message === 'string' ? payload.message : t('territory.requestFailed'));
  return payload;
}

async function save(): Promise<void> {
  if (!canEdit.value || validation.value.violations.length) { notice.value = { tone: 'danger', message: t('territory.fixViolations') }; return; }
  busy.value = true; notice.value = null;
  try {
    const payload = await jsonRequest(`/territory/${props.territory.plan.id}`, 'PUT', { expected_revision: revision.value, alliances: alliances.value, groups: groups.value, objects: objects.value, planning_preferences: preferences.value });
    const receipt = payload.receipt as { revision: number; status: string };
    revision.value = receipt.revision; status.value = receipt.status; history.value = []; future.value = [];
    notice.value = { tone: 'success', message: t('territory.saved', { revision: revision.value }) };
  } catch (error) { notice.value = { tone: 'danger', message: error instanceof Error ? error.message : t('territory.requestFailed') }; }
  finally { busy.value = false; }
}
async function publish(): Promise<void> {
  busy.value = true;
  try { const payload = await jsonRequest(`/territory/${props.territory.plan.id}/publish`, 'POST', { expected_revision: revision.value }); const receipt = payload.receipt as { status: string }; status.value = receipt.status; notice.value = { tone: 'success', message: t('territory.published') }; router.reload({ only: ['territory'] }); }
  catch (error) { notice.value = { tone: 'danger', message: error instanceof Error ? error.message : t('territory.requestFailed') }; }
  finally { busy.value = false; }
}
async function archive(): Promise<void> {
  busy.value = true;
  try { await jsonRequest(`/territory/${props.territory.plan.id}`, 'DELETE', { expected_revision: revision.value }); archiveOpen.value = false; router.visit('/territory'); }
  catch (error) { notice.value = { tone: 'danger', message: error instanceof Error ? error.message : t('territory.requestFailed') }; }
  finally { busy.value = false; }
}
async function generateHive(style: 'swirl' | 'banner_pad'): Promise<void> {
  if (!activeAllianceKey.value) return;
  busy.value = true;
  try {
    const payload = await jsonRequest('/territory/hive-preview', 'POST', { style, alliance_key: activeAllianceKey.value, center_x: 600, center_y: 600, city_count: 50 });
    const generated = payload.objects as PlanObject[];
    remember();
    const groupKey = generated[0]?.group_key ?? key('hive'); groups.value.push({ key: groupKey, label: style === 'swirl' ? t('territory.swirlHive') : t('territory.bannerPadHive') });
    objects.value.push(...generated.map((object, index) => ({ ...object, key: key(`hive${index}`), group_key: groupKey, player_id: null, external_player_name: null, rotation: 0, sort_order: objects.value.length + index, metadata: {} })));
    selectedKeys.value = objects.value.filter((object) => object.group_key === groupKey).map((object) => object.key);
  } catch (error) { notice.value = { tone: 'danger', message: error instanceof Error ? error.message : t('territory.requestFailed') }; }
  finally { busy.value = false; }
}

function exportJson(): void {
  const document = { schema_version: 1, plan: { ...props.territory.plan, revision: revision.value, map_dataset_id: props.territory.map.id, map_dataset_checksum: props.territory.map.checksum, planning_preferences: preferences.value }, alliances: alliances.value, groups: groups.value, objects: objects.value };
  downloadText(`${props.territory.plan.name.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}.json`, JSON.stringify(document, null, 2), 'application/json');
}
function exportSvg(): void { downloadText(`${props.territory.plan.name}.svg`, buildSvg(props.territory.map.data, alliances.value, objects.value, props.territory.plan.name), 'image/svg+xml'); }
function exportPng(): void { const data = canvas.value?.exportPng(); if (!data) return; const anchor = document.createElement('a'); anchor.href = data; anchor.download = `${props.territory.plan.name}.png`; anchor.click(); }
async function importFile(event: Event): Promise<void> { const input = event.target as HTMLInputElement; const file = input.files?.[0]; if (!file) return; importDocument.value = await file.text(); busy.value = true; try { const payload = await jsonRequest('/territory/import-preview', 'POST', { document: importDocument.value }); importPreview.value = payload.preview as Record<string, unknown>; notice.value = { tone: 'info', message: t('territory.importReady') }; } catch (error) { notice.value = { tone: 'danger', message: error instanceof Error ? error.message : t('territory.requestFailed') }; } finally { busy.value = false; input.value = ''; } }
function applyImport(): void { if (!importPreview.value || importPreview.value.can_commit !== true) return; remember(); alliances.value = structuredClone(importPreview.value.alliances as PlanAlliance[]); groups.value = structuredClone(importPreview.value.groups as PlanGroup[]); objects.value = structuredClone(importPreview.value.objects as PlanObject[]); preferences.value = structuredClone(importPreview.value.planning_preferences as PlanningPreferences); importPreview.value = null; notice.value = { tone: 'warning', message: t('territory.importAppliedUnsaved') }; }

function onKey(event: KeyboardEvent): void {
  if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement || event.target instanceof HTMLSelectElement) return;
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') { event.preventDefault(); event.shiftKey ? redo() : undo(); return; }
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'y') { event.preventDefault(); redo(); return; }
  if (event.key === 'Delete' || event.key === 'Backspace') { event.preventDefault(); removeSelected(); return; }
  if (event.key.toLowerCase() === 'r') { event.preventDefault(); rotateSelected(event.shiftKey ? -1 : 1); return; }
  if (event.key === 'ArrowLeft') { event.preventDefault(); nudge(-1, 0); }
  if (event.key === 'ArrowRight') { event.preventDefault(); nudge(1, 0); }
  if (event.key === 'ArrowUp') { event.preventDefault(); nudge(0, 1); }
  if (event.key === 'ArrowDown') { event.preventDefault(); nudge(0, -1); }
}

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
  <Head :title="`${territory.plan.name} · ${t('territory.editorTitle')}`" />
  <AppLayout :user="user">
    <RoomBanner :eyebrow="t('territory.eyebrow')" :title="territory.plan.name" :subtitle="t('territory.editorSubtitle', { governor: activePlayer.name })" image="/images/kingshot/v4/kingdom-transfer.svg">
      <template #actions><Link href="/territory" class="ks-command-link" data-variant="secondary">{{ t('territory.backToPlans') }}</Link></template>
    </RoomBanner>

    <ActionNotice v-if="notice" class="mt-4" :tone="notice.tone" :message="notice.message" />

    <section class="ks-surface mt-4 p-4" aria-label="Planner status">
      <div class="flex flex-wrap items-center gap-3 text-sm">
        <span class="ks-status" :data-tone="status === 'published' ? 'success' : 'info'">{{ t(`territory.status.${status}`) }}</span>
        <span class="ks-chip">{{ t('territory.revision', { revision }) }}</span>
        <span class="ks-chip">{{ territory.map.source_label }} · {{ territory.map.observed_at }}</span>
        <span class="ks-chip">{{ t(`territory.confidence.${territory.map.confidence}`) }}</span>
        <a v-if="territory.map.source_uri" :href="territory.map.source_uri" target="_blank" rel="noreferrer" class="text-[var(--ks-gold-bright)] underline">{{ t('territory.mapSource') }}</a>
      </div>
      <p class="mt-2 text-xs leading-5 text-[var(--ks-muted)]">{{ t('territory.mapEvidenceHelp') }}</p>
    </section>

    <div class="mt-4 grid gap-4 2xl:grid-cols-[17rem_minmax(0,1fr)_22rem]">
      <aside class="ks-surface p-4" aria-label="Build tools">
        <p class="ks-kicker">{{ t('territory.build') }}</p>
        <div class="mt-3 grid grid-cols-3 gap-2 2xl:grid-cols-1">
          <button class="ks-command-link" :aria-pressed="tool === 'select'" @click="tool = 'select'">{{ t('territory.select') }}</button>
          <button class="ks-command-link" :aria-pressed="tool === 'pan'" @click="tool = 'pan'">{{ t('territory.pan') }}</button>
          <button class="ks-command-link" :aria-pressed="tool === 'place'" :disabled="!canEdit" @click="tool = 'place'">{{ t('territory.place') }}</button>
        </div>
        <label class="mt-4 block text-sm font-semibold">{{ t('territory.activeAlliance') }}<select v-model="activeAllianceKey" class="ks-input mt-2 w-full"><option v-for="alliance in alliances" :key="alliance.key" :value="alliance.key">{{ alliance.display_name }}</option></select></label>
        <label class="mt-4 block text-sm font-semibold">{{ t('territory.objectType') }}<select v-model="placementType" class="ks-input mt-2 w-full"><option value="headquarters">{{ t('territory.types.headquarters') }}</option><option value="banner">{{ t('territory.types.banner') }}</option><option value="governor_city">{{ t('territory.types.governor_city') }}</option><option value="bear_trap">{{ t('territory.types.bear_trap') }}</option></select></label>
        <div class="mt-4 grid gap-2">
          <AppButton :disabled="!canEdit" @click="generateHive('swirl')">{{ t('territory.swirlHive') }}</AppButton>
          <AppButton :disabled="!canEdit" @click="generateHive('banner_pad')">{{ t('territory.bannerPadHive') }}</AppButton>
          <AppButton v-if="territory.plan.scope === 'kingdom'" :disabled="!canEdit" @click="addExternalAlliance">{{ t('territory.addAlliance') }}</AppButton>
        </div>
        <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
          <p class="ks-kicker">{{ t('territory.editing') }}</p>
          <div class="mt-3 grid grid-cols-2 gap-2">
            <button class="ks-command-link" :disabled="!history.length" @click="undo">{{ t('territory.undo') }}</button><button class="ks-command-link" :disabled="!future.length" @click="redo">{{ t('territory.redo') }}</button>
            <button class="ks-command-link" :disabled="!selectedKeys.length" @click="duplicateSelected">{{ t('territory.duplicate') }}</button><button class="ks-command-link" :disabled="selectedKeys.length < 2" @click="groupSelected">{{ t('territory.group') }}</button>
            <button class="ks-command-link" :disabled="!selectedKeys.length" @click="ungroupSelected">{{ t('territory.ungroup') }}</button><button class="ks-command-link" :disabled="!selectedKeys.length" @click="rotateSelected()">{{ t('territory.rotate') }}</button>
            <button class="ks-command-link col-span-2" :disabled="!selectedKeys.length" @click="removeSelected">{{ t('territory.deleteSelected') }}</button>
          </div>
        </div>
      </aside>

      <main class="min-w-0">
        <TerritoryCanvas ref="canvas" v-model:selected-keys="selectedKeys" :map="territory.map.data" :alliances="alliances" :objects="objects" :tool="tool" :placement-type="placementType" :active-alliance-key="activeAllianceKey" :read-only="!canEdit" @move="move" @place="place" />
        <section class="ks-surface mt-4 p-4" aria-labelledby="validation-heading">
          <div class="flex items-center justify-between gap-3"><h2 id="validation-heading" class="ks-display text-xl font-semibold">{{ t('territory.validation') }}</h2><span class="ks-status" :data-tone="validation.violations.length ? 'danger' : validation.warnings.length ? 'warning' : 'success'">{{ validation.violations.length ? t('territory.invalid') : validation.warnings.length ? t('territory.validWithWarnings') : t('territory.valid') }}</span></div>
          <ul v-if="validation.violations.length || validation.warnings.length" class="mt-3 space-y-2 text-sm">
            <li v-for="item in validation.violations" :key="`v-${item.code}-${item.object_key}`" class="text-red-200">{{ item.message }}</li>
            <li v-for="item in validation.warnings" :key="`w-${item.code}-${item.object_key}`" class="text-amber-200">{{ item.message }}</li>
          </ul>
          <p v-else class="mt-2 text-sm text-[var(--ks-muted)]">{{ t('territory.noValidationIssues') }}</p>
        </section>
      </main>

      <aside class="space-y-4">
        <section class="ks-surface p-4" aria-labelledby="analysis-heading"><p class="ks-kicker">{{ t('territory.analyze') }}</p><h2 id="analysis-heading" class="ks-display mt-1 text-xl font-semibold">{{ t('territory.layoutAnalysis') }}</h2><div v-for="alliance in alliances" :key="alliance.key" class="mt-4 border-t border-[var(--ks-border)] pt-3"><strong>{{ alliance.display_name }}</strong><template v-if="analysis[alliance.key]"><dl class="mt-2 grid grid-cols-2 gap-2 text-xs"><div><dt class="text-[var(--ks-muted)]">{{ t('territory.coverage') }}</dt><dd>{{ analysis[alliance.key]?.coverage_percent ?? '—' }}%</dd></div><div><dt class="text-[var(--ks-muted)]">{{ t('territory.uncovered') }}</dt><dd>{{ formatNumber(analysis[alliance.key]?.uncovered_governor_cities ?? 0) }}</dd></div><div><dt class="text-[var(--ks-muted)]">{{ t('territory.components') }}</dt><dd>{{ formatNumber(analysis[alliance.key]?.territory_components ?? 0) }}</dd></div><div><dt class="text-[var(--ks-muted)]">{{ t('territory.avgDistance') }}</dt><dd>{{ analysis[alliance.key]?.bear_distance_tiles.average ?? '—' }}</dd></div><div><dt class="text-[var(--ks-muted)]">{{ t('territory.maxDistance') }}</dt><dd>{{ analysis[alliance.key]?.bear_distance_tiles.max ?? '—' }}</dd></div><div><dt class="text-[var(--ks-muted)]">{{ t('territory.bannerEfficiency') }}</dt><dd>{{ analysis[alliance.key]?.banner_efficiency ?? '—' }}</dd></div></dl></template></div></section>
        <section class="ks-surface p-4"><p class="ks-kicker">{{ t('territory.preferences') }}</p><label class="mt-3 block text-sm">{{ t('territory.preferredBearRadius') }}<input v-model.number="preferences.preferred_bear_radius_tiles" type="number" min="1" max="1200" class="ks-input mt-1 w-full" /></label><label class="mt-3 block text-sm">{{ t('territory.marchSecondsPerTile') }}<input v-model.number="preferences.march_seconds_per_tile" type="number" min="0.01" max="60" step="0.01" class="ks-input mt-1 w-full" /></label><p class="mt-2 text-xs text-[var(--ks-muted)]">{{ t('territory.marchAssumptionHelp') }}</p></section>
        <section class="ks-surface p-4"><p class="ks-kicker">{{ t('territory.selection') }}</p><p class="mt-2 text-sm">{{ t('territory.selectedCount', { count: selectedKeys.length }) }}</p><div v-for="object in selectedObjects.slice(0, 20)" :key="object.key" class="mt-3 grid grid-cols-[1fr_5rem_5rem] gap-2"><span class="truncate text-xs">{{ object.label || t(`territory.types.${object.type}`) }}</span><input v-model.number="object.x" type="number" class="ks-input px-2 py-1 text-xs" :aria-label="`${object.label ?? object.type} X`" /><input v-model.number="object.y" type="number" class="ks-input px-2 py-1 text-xs" :aria-label="`${object.label ?? object.type} Y`" /></div></section>
      </aside>
    </div>

    <section class="ks-surface mt-4 p-4"><div class="flex flex-wrap gap-2"><AppButton :busy="busy" :disabled="!canEdit || validation.violations.length > 0" @click="save">{{ t('territory.save') }}</AppButton><AppButton :busy="busy" :disabled="!canEdit || validation.violations.length > 0" @click="publish">{{ t('territory.publish') }}</AppButton><button class="ks-command-link" @click="exportJson">{{ t('territory.exportJson') }}</button><button class="ks-command-link" @click="exportPng">{{ t('territory.exportPng') }}</button><button class="ks-command-link" @click="exportSvg">{{ t('territory.exportSvg') }}</button><label class="ks-command-link cursor-pointer">{{ t('territory.importJson') }}<input type="file" accept="application/json,.json" class="sr-only" @change="importFile" /></label><button v-if="canEdit" class="ks-command-link" data-variant="danger" @click="archiveOpen = true">{{ t('territory.archive') }}</button></div><div v-if="importPreview" class="mt-4 rounded border border-[var(--ks-border)] p-3"><p class="text-sm">{{ t('territory.importPreview') }}</p><AppButton class="mt-2" :disabled="importPreview.can_commit !== true" @click="applyImport">{{ t('territory.applyImport') }}</AppButton></div></section>

    <section v-if="territory.revisions.length" class="ks-surface mt-4 p-4"><p class="ks-kicker">{{ t('territory.revisions') }}</p><div class="mt-3 flex flex-wrap gap-2"><span v-for="item in territory.revisions" :key="item.id" class="ks-chip">#{{ item.revision_number }} · {{ item.published_at ? formatDate(item.published_at) : '—' }}</span></div></section>

    <ConfirmActionDialog v-model:open="archiveOpen" :title="t('territory.archive')" :description="t('territory.archiveConfirm')" :confirm-label="t('territory.archive')" :busy="busy" destructive @confirm="archive" />
  </AppLayout>
</template>
