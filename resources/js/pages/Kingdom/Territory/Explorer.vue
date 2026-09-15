<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { authorityContextKey, AUTHORITY_CONTEXT_STALE_EVENT } from '@/identity/authority-context';

import RoomBanner from '@/components/game/RoomBanner.vue';
import ActionNotice from '@/components/ui/ActionNotice.vue';
import AppButton from '@/components/ui/AppButton.vue';
import TerritoryCanvas from '@/features/territory-planner/components/TerritoryCanvas.vue';
import TerritoryMinimap from '@/features/territory-planner/components/TerritoryMinimap.vue';
import TerritoryObjectList from '@/features/territory-planner/components/TerritoryObjectList.vue';
import { buildTerritoryScene, sceneSearchPage } from '@/features/territory-planner/engine/scene';
import type { TerritorySceneEntity } from '@/features/territory-planner/engine/scene-types';
import type { MapData } from '@/features/territory-planner/engine/types';
import type { Viewport } from '@/features/territory-planner/engine/viewport';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type MapPayload = {
  id: string;
  checksum: string;
  source_label: string;
  source_uri: string | null;
  confidence: string;
  observed_at: string;
  data: MapData;
};
type WorkspaceView = {
  key: string;
  name: string;
  center_x: number;
  center_y: number;
  zoom: number;
  layers: Record<string, { visible: boolean; opacity: number }>;
  show_grid: boolean;
  show_labels: boolean;
};

const props = defineProps<{
  user: { name: string; email: string };
  activePlayer: {
    id: string;
    name: string;
    kingdomId: string;
    kingdomNumber: number | null;
  };
  territory: { map: MapPayload };
}>();
const { t, formatNumber } = useLocale();
const canvas = ref<InstanceType<typeof TerritoryCanvas> | null>(null);
const query = ref('');
const pageOffset = ref(0);
const showGrid = ref(false);
const showLabels = ref(true);
const editingViewKey = ref<string | null>(null);
const viewsLoaded = ref(false);
const mobilePanel = ref<'map' | 'tools' | 'objects'>('map');
const mapHost = ref<HTMLElement | null>(null);
const isFullscreen = ref(false);
const currentViewport = ref<Viewport | null>(null);
let viewportFrame: number | null = null;
let requestController = new AbortController();
let disposed = false;
const selectedReference = ref<string | null>(null);
const coordinateX = ref(
  Math.round(props.territory.map.data.bounds.x + props.territory.map.data.bounds.width / 2),
);
const coordinateY = ref(
  Math.round(props.territory.map.data.bounds.y + props.territory.map.data.bounds.height / 2),
);
const showTerrain = ref(true);
const showFacilities = ref(true);
const showResources = ref(true);
const showStructures = ref(true);
const showZones = ref(true);
const workspaceRevision = ref(0);
const savedViews = ref<WorkspaceView[]>([]);
const viewName = ref('');
const notice = ref<{ tone: 'success' | 'warning' | 'danger' | 'info'; message: string } | null>(
  null,
);
const busy = ref(false);

const scene = computed(() =>
  buildTerritoryScene({
    map: props.territory.map.data,
    mapChecksum: props.territory.map.checksum,
  }),
);
const visibleLayers = computed(() => [
  ...(showTerrain.value ? ['terrain'] : []),
  ...(showFacilities.value ? ['facilities'] : []),
  ...(showResources.value ? ['resources'] : []),
  ...(showStructures.value ? ['structures'] : []),
]);
const resultPage = computed(() =>
  sceneSearchPage(scene.value, query.value, {
    offset: pageOffset.value,
    limit: 100,
    layers: visibleLayers.value,
  }),
);
const results = computed(() => resultPage.value.items);
watch([query, visibleLayers], () => {
  pageOffset.value = 0;
});
const selected = computed<TerritorySceneEntity | null>(() => {
  if (!selectedReference.value) return null;
  return scene.value.entities.find((entity) => entity.key === selectedReference.value) ?? null;
});
const mapMinX = computed(() => props.territory.map.data.bounds.x);
const mapMinY = computed(() => props.territory.map.data.bounds.y);
const mapMaxX = computed(
  () => props.territory.map.data.bounds.x + props.territory.map.data.bounds.width - 1,
);
const mapMaxY = computed(
  () => props.territory.map.data.bounds.y + props.territory.map.data.bounds.height - 1,
);

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
async function jsonRequest(
  url: string,
  method = 'GET',
  body?: unknown,
): Promise<Record<string, unknown>> {
  const authority = `${props.activePlayer.id}:${props.territory.map.id}:${authorityContextKey()}`;
  const signal = requestController.signal;
  const init: NonNullable<Parameters<typeof fetch>[1]> = {
    signal,
    cache: 'no-store',
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
  if (
    disposed ||
    signal.aborted ||
    authority !== `${props.activePlayer.id}:${props.territory.map.id}:${authorityContextKey()}`
  ) {
    throw new Error(t('territory.requestFailed'));
  }
  if (!response.ok) {
    throw new Error(
      typeof payload.message === 'string' ? payload.message : t('territory.requestFailed'),
    );
  }
  return payload;
}
function inspect(entity: TerritorySceneEntity): void {
  selectedReference.value = entity.key;
  mobilePanel.value = 'map';
  void nextTick(() => canvas.value?.focusBounds(entity.bounds));
}
function inspectReference(key: string): void {
  const entity = scene.value.entities.find((candidate) => candidate.key === key);
  if (entity) inspect(entity);
}
function jump(): void {
  if (
    !Number.isInteger(coordinateX.value) ||
    !Number.isInteger(coordinateY.value) ||
    coordinateX.value < mapMinX.value ||
    coordinateX.value > mapMaxX.value ||
    coordinateY.value < mapMinY.value ||
    coordinateY.value > mapMaxY.value
  ) {
    notice.value = { tone: 'warning', message: t('territory.requestFailed') };
    return;
  }
  canvas.value?.jumpTo({ x: coordinateX.value, y: coordinateY.value });
}
function navigateFromMinimap(point: { x: number; y: number }): void {
  coordinateX.value = point.x;
  coordinateY.value = point.y;
  canvas.value?.jumpTo(point);
}
function syncViewport(): void {
  currentViewport.value = canvas.value?.viewport() ?? null;
  viewportFrame = window.requestAnimationFrame(syncViewport);
}
function layerSnapshot(): Record<string, { visible: boolean; opacity: number }> {
  return {
    terrain: { visible: showTerrain.value, opacity: 1 },
    zones: { visible: showZones.value, opacity: 1 },
    restrictions: { visible: showZones.value, opacity: 1 },
    structures: { visible: showStructures.value, opacity: 1 },
    facilities: { visible: showFacilities.value, opacity: 1 },
    resources: { visible: showResources.value, opacity: 1 },
    coverage: { visible: false, opacity: 1 },
    planned: { visible: false, opacity: 1 },
    observed: { visible: false, opacity: 1 },
    comparison: { visible: false, opacity: 1 },
    annotations: { visible: false, opacity: 1 },
  };
}
function applyLayers(layers: WorkspaceView['layers']): void {
  showTerrain.value = layers.terrain?.visible ?? true;
  showZones.value = (layers.zones?.visible ?? true) || (layers.restrictions?.visible ?? true);
  showStructures.value = layers.structures?.visible ?? true;
  showFacilities.value = layers.facilities?.visible ?? true;
  showResources.value = layers.resources?.visible ?? true;
}
async function loadViews(): Promise<void> {
  try {
    const query = new URLSearchParams({
      map_dataset_id: props.territory.map.id,
      map_dataset_checksum: props.territory.map.checksum,
    });
    const payload = await jsonRequest(`/territory/workspace-views?${query.toString()}`);
    workspaceRevision.value = Number(payload.revision ?? 0);
    savedViews.value = Array.isArray(payload.views) ? (payload.views as WorkspaceView[]) : [];
    viewsLoaded.value = true;
  } catch (error) {
    notice.value = {
      tone: 'warning',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  }
}
async function persistViews(views: WorkspaceView[]): Promise<void> {
  if (!viewsLoaded.value || busy.value) return;
  busy.value = true;
  try {
    const payload = await jsonRequest('/territory/workspace-views', 'PUT', {
      map_dataset_id: props.territory.map.id,
      map_dataset_checksum: props.territory.map.checksum,
      expected_revision: workspaceRevision.value,
      views,
    });
    workspaceRevision.value = Number(payload.revision);
    savedViews.value = payload.views as WorkspaceView[];
    editingViewKey.value = null;
    viewName.value = '';
    notice.value = {
      tone: 'success',
      message: t('territory.saved', { revision: workspaceRevision.value }),
    };
  } catch (error) {
    if (!disposed && !requestController.signal.aborted) {
      notice.value = {
        tone: 'danger',
        message: error instanceof Error ? error.message : t('territory.requestFailed'),
      };
    }
  } finally {
    busy.value = false;
  }
}
async function saveCurrentView(): Promise<void> {
  const name = viewName.value.trim();
  const camera = canvas.value?.viewport();
  if (!name || !camera) return;
  if (editingViewKey.value) {
    await persistViews(
      savedViews.value.map((view) =>
        view.key === editingViewKey.value ? { ...view, name } : view,
      ),
    );
    return;
  }
  if (savedViews.value.length >= 20) {
    notice.value = { tone: 'warning', message: t('territory.explorer.viewLimit') };
    return;
  }
  await persistViews([
    ...savedViews.value,
    {
      key: `view-${crypto.randomUUID()}`,
      name,
      center_x: camera.x,
      center_y: camera.y,
      zoom: camera.zoom,
      layers: layerSnapshot(),
      show_grid: showGrid.value,
      show_labels: showLabels.value,
    },
  ]);
}
function renameView(view: WorkspaceView): void {
  editingViewKey.value = view.key;
  viewName.value = view.name;
}
async function removeView(view: WorkspaceView): Promise<void> {
  await persistViews(savedViews.value.filter((item) => item.key !== view.key));
}
async function toggleFullscreen(): Promise<void> {
  try {
    if (document.fullscreenElement) await document.exitFullscreen();
    else await mapHost.value?.requestFullscreen();
  } catch {
    notice.value = { tone: 'warning', message: t('territory.requestFailed') };
  }
}
function fullscreenChanged(): void {
  isFullscreen.value = document.fullscreenElement === mapHost.value;
}
function clearPrivateViews(): void {
  requestController.abort();
  requestController = new AbortController();
  savedViews.value = [];
  viewsLoaded.value = false;
  workspaceRevision.value = 0;
  editingViewKey.value = null;
  viewName.value = '';
}
function openView(view: WorkspaceView): void {
  applyLayers(view.layers);
  showGrid.value = view.show_grid;
  showLabels.value = view.show_labels;
  canvas.value?.setCamera({ x: view.center_x, y: view.center_y, zoom: view.zoom });
}

watch(
  () => [props.activePlayer.id, props.territory.map.id, props.territory.map.checksum],
  () => {
    clearPrivateViews();
    void loadViews();
  },
);
onMounted(() => {
  void loadViews();
  viewportFrame = window.requestAnimationFrame(syncViewport);
  window.addEventListener(AUTHORITY_CONTEXT_STALE_EVENT, clearPrivateViews);
  document.addEventListener('fullscreenchange', fullscreenChanged);
});
onBeforeUnmount(() => {
  disposed = true;
  clearPrivateViews();
  requestController.abort();
  if (viewportFrame !== null) window.cancelAnimationFrame(viewportFrame);
  window.removeEventListener(AUTHORITY_CONTEXT_STALE_EVENT, clearPrivateViews);
  document.removeEventListener('fullscreenchange', fullscreenChanged);
});
</script>

<template>
  <Head :title="`${t('territory.mapProfile')} · ${territory.map.source_label}`" />
  <AppLayout :user="user">
    <RoomBanner
      :eyebrow="t('territory.eyebrow')"
      :title="territory.map.source_label"
      :subtitle="t('territory.mapEvidenceHelp')"
      image="/images/kingshot/v4/kingdom-transfer.svg"
    >
      <template #actions>
        <Link href="/territory" class="ks-command-link" data-variant="secondary">
          {{ t('territory.backToPlans') }}
        </Link>
      </template>
    </RoomBanner>

    <ActionNotice v-if="notice" class="mt-4" :tone="notice.tone" :message="notice.message" />

    <nav class="mt-4 flex gap-2 xl:hidden" :aria-label="t('territory.mapLayers')">
      <AppButton :aria-pressed="mobilePanel === 'map'" @click="mobilePanel = 'map'">{{
        t('territory.explorer.map')
      }}</AppButton>
      <AppButton :aria-pressed="mobilePanel === 'tools'" @click="mobilePanel = 'tools'">{{
        t('territory.mapLayers')
      }}</AppButton>
      <AppButton :aria-pressed="mobilePanel === 'objects'" @click="mobilePanel = 'objects'">{{
        t('territory.explorer.objects')
      }}</AppButton>
    </nav>
    <div class="mt-4 grid gap-4 xl:grid-cols-[14rem_minmax(0,1fr)_19rem]">
      <aside
        class="ks-surface p-4 xl:block"
        :class="{ hidden: mobilePanel !== 'tools' }"
        :aria-label="t('territory.mapLayers')"
      >
        <p class="ks-kicker">{{ t('territory.mapLayers') }}</p>
        <label class="mt-3 block text-sm font-semibold">
          {{ t('territory.filterObjects') }}
          <input v-model="query" class="ks-input mt-2 w-full" type="search" />
        </label>
        <div class="mt-4 space-y-2 text-sm">
          <label class="flex min-h-11 items-center gap-2"
            ><input v-model="showGrid" type="checkbox" />{{ t('territory.explorer.grid') }}</label
          >
          <label class="flex min-h-11 items-center gap-2"
            ><input v-model="showLabels" type="checkbox" />{{
              t('territory.explorer.labels')
            }}</label
          >
          <label class="flex min-h-11 items-center gap-2"
            ><input v-model="showTerrain" type="checkbox" />{{
              t('territory.explorer.terrain')
            }}</label
          >
          <label class="flex min-h-11 items-center gap-2"
            ><input v-model="showStructures" type="checkbox" />{{
              t('territory.showStructures')
            }}</label
          >
          <label class="flex min-h-11 items-center gap-2"
            ><input v-model="showFacilities" type="checkbox" />{{
              t('territory.explorer.facilities')
            }}</label
          >
          <label class="flex min-h-11 items-center gap-2"
            ><input v-model="showResources" type="checkbox" />{{
              t('territory.explorer.resources')
            }}</label
          >
          <label class="flex min-h-11 items-center gap-2"
            ><input v-model="showZones" type="checkbox" />{{ t('territory.showZones') }}</label
          >
        </div>

        <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
          <p class="ks-kicker">{{ t('territory.explorer.coordinates') }}</p>
          <div class="mt-2 grid grid-cols-2 gap-2">
            <input
              v-model.number="coordinateX"
              class="ks-input"
              type="number"
              :min="mapMinX"
              :max="mapMaxX"
              :aria-label="t('territory.explorer.xCoordinate')"
            />
            <input
              v-model.number="coordinateY"
              class="ks-input"
              type="number"
              :min="mapMinY"
              :max="mapMaxY"
              :aria-label="t('territory.explorer.yCoordinate')"
            />
          </div>
          <AppButton class="mt-2 w-full" @click="jump">{{
            t('territory.explorer.jump')
          }}</AppButton>
          <AppButton class="mt-2 w-full" @click="canvas?.fitMap()">{{
            t('territory.explorer.fitMap')
          }}</AppButton>
        </div>

        <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
          <p class="ks-kicker">{{ t('territory.explorer.savedViews') }}</p>
          <input
            v-model="viewName"
            maxlength="80"
            class="ks-input mt-2 w-full"
            :placeholder="t('territory.explorer.viewName')"
            :aria-label="t('territory.explorer.viewName')"
          />
          <AppButton
            class="mt-2 w-full"
            :busy="busy"
            :disabled="!viewName.trim() || !viewsLoaded || busy"
            @click="saveCurrentView"
            >{{
              editingViewKey ? t('territory.explorer.renameView') : t('territory.save')
            }}</AppButton
          >
          <div
            v-for="view in savedViews"
            :key="view.key"
            class="mt-2 border-t border-[var(--ks-border)] pt-2"
          >
            <button type="button" class="ks-command-link w-full text-left" @click="openView(view)">
              {{ view.name }}
            </button>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="min-h-11 text-xs underline"
                :disabled="busy"
                @click="renameView(view)"
              >
                {{ t('territory.explorer.renameView') }}
              </button>
              <button
                type="button"
                class="min-h-11 text-xs underline"
                :disabled="busy"
                @click="removeView(view)"
              >
                {{ t('territory.explorer.removeView') }}
              </button>
            </div>
          </div>
        </div>
      </aside>

      <main
        ref="mapHost"
        class="min-w-0 bg-[#101821] xl:block"
        :class="{ hidden: mobilePanel !== 'map' }"
      >
        <div class="flex justify-end p-2">
          <AppButton @click="toggleFullscreen">{{
            t(isFullscreen ? 'territory.explorer.exitFullscreen' : 'territory.explorer.fullscreen')
          }}</AppButton>
        </div>
        <TerritoryCanvas
          ref="canvas"
          :label="t('territory.canvasLabel')"
          :map="territory.map.data"
          :map-checksum="territory.map.checksum"
          :alliances="[]"
          :objects="[]"
          :selected-keys="[]"
          tool="select"
          placement-type="governor_city"
          :active-alliance-key="null"
          read-only
          :show-grid="showGrid"
          :show-labels="showLabels"
          :show-terrain="showTerrain"
          :show-structures="showStructures"
          :show-facilities="showFacilities"
          :show-resources="showResources"
          :show-zones="showZones"
          @inspect-reference="inspectReference"
        />
        <TerritoryMinimap
          :bounds="territory.map.data.bounds"
          :viewport="currentViewport"
          :label="t('territory.explorer.map')"
          @navigate="navigateFromMinimap"
        />
        <section class="ks-surface mt-4 p-4" aria-live="polite">
          <p class="text-sm text-[var(--ks-muted)]">
            {{
              t('territory.explorer.entityCount', { count: formatNumber(scene.entities.length) })
            }}
            · {{ territory.map.observed_at }} ·
            {{ t(`territory.confidence.${territory.map.confidence}`) }}
          </p>
          <p
            v-for="(availability, layer) in scene.layerAvailability"
            :key="layer"
            class="mt-1 text-xs text-[var(--ks-muted)]"
          >
            {{ layer }}: {{ availability.state }} ·
            {{ formatNumber(availability.available_count) }}/{{
              formatNumber(availability.expected_count)
            }}
            <span v-if="availability.unavailable_reason">
              · {{ availability.unavailable_reason }}</span
            >
          </p>
        </section>
      </main>

      <aside
        class="ks-surface p-4 xl:block"
        :class="{ hidden: mobilePanel !== 'objects' }"
        :aria-label="t('territory.explorer.inspector')"
      >
        <p class="ks-kicker">{{ t('territory.explorer.inspector') }}</p>
        <template v-if="selected">
          <h2 class="ks-display mt-2 text-xl font-semibold">{{ selected.label }}</h2>
          <p class="mt-2 text-sm text-[var(--ks-muted)]">
            {{ selected.layer }} · {{ selected.sourceKey }}
          </p>
          <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-3 gap-y-2 text-sm">
            <dt>X</dt>
            <dd>{{ selected.bounds.x }}</dd>
            <dt>Y</dt>
            <dd>{{ selected.bounds.y }}</dd>
            <dt>{{ t('territory.explorer.size') }}</dt>
            <dd>{{ selected.bounds.width }}×{{ selected.bounds.height }}</dd>
            <dt>{{ t('territory.explorer.confidence') }}</dt>
            <dd>{{ selected.confidence ?? territory.map.confidence }}</dd>
            <dt>{{ t('territory.mapSource') }}</dt>
            <dd>{{ selected.provenance.join(' · ') || selected.sourceKey }}</dd>
          </dl>
          <a
            v-if="territory.map.source_uri"
            class="mt-4 inline-block text-sm text-[var(--ks-gold-bright)] underline"
            :href="territory.map.source_uri"
            target="_blank"
            rel="noreferrer"
            >{{ t('territory.mapSource') }}</a
          >
        </template>
        <p v-else class="mt-2 text-sm text-[var(--ks-muted)]">
          {{ t('territory.explorer.selectObject') }}
        </p>

        <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
          <p class="ks-kicker">{{ t('territory.explorer.objects') }}</p>
          <p class="mt-2 text-xs" aria-live="polite">
            {{
              t('territory.explorer.results', {
                start: resultPage.total ? resultPage.offset + 1 : 0,
                end: resultPage.offset + results.length,
                total: resultPage.total,
              })
            }}
          </p>
          <nav class="mt-2 flex gap-2" :aria-label="t('territory.explorer.searchableObjects')">
            <AppButton
              :disabled="resultPage.previousOffset === null"
              @click="pageOffset = resultPage.previousOffset ?? 0"
              >{{ t('territory.explorer.previous') }}</AppButton
            >
            <AppButton
              :disabled="resultPage.nextOffset === null"
              @click="pageOffset = resultPage.nextOffset ?? 0"
              >{{ t('territory.explorer.next') }}</AppButton
            >
          </nav>
          <TerritoryObjectList
            class="mt-2"
            :entities="results"
            :selected-key="selectedReference"
            :label="t('territory.explorer.searchableObjects')"
            @inspect="inspect"
          />
        </div>
      </aside>
    </div>
  </AppLayout>
</template>
