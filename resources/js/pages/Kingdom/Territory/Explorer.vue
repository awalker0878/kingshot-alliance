<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import ActionNotice from '@/components/ui/ActionNotice.vue';
import AppButton from '@/components/ui/AppButton.vue';
import TerritoryCanvas from '@/features/territory-planner/components/TerritoryCanvas.vue';
import {
  buildTerritoryScene,
  sceneEntitiesForQuery,
} from '@/features/territory-planner/engine/scene';
import type { TerritorySceneEntity } from '@/features/territory-planner/engine/scene-types';
import type { MapData } from '@/features/territory-planner/engine/types';
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
const results = computed(() => sceneEntitiesForQuery(scene.value, query.value, 200));
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
  if (!response.ok)
    throw new Error(
      typeof payload.message === 'string' ? payload.message : t('territory.requestFailed'),
    );
  return payload;
}
function inspect(entity: TerritorySceneEntity): void {
  selectedReference.value = entity.key;
  canvas.value?.focusBounds(entity.bounds);
}
function inspectReference(key: string): void {
  const entity = scene.value.entities.find((candidate) => candidate.key === key);
  if (entity) inspect(entity);
}
function jump(): void {
  if (
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
  } catch (error) {
    notice.value = {
      tone: 'warning',
      message: error instanceof Error ? error.message : t('territory.requestFailed'),
    };
  }
}
async function saveCurrentView(): Promise<void> {
  const name = viewName.value.trim();
  const camera = canvas.value?.viewport();
  if (!name || !camera) return;
  busy.value = true;
  try {
    const key = `view-${crypto.randomUUID()}`;
    const views = [
      ...savedViews.value,
      {
        key,
        name,
        center_x: camera.x,
        center_y: camera.y,
        zoom: camera.zoom,
        layers: layerSnapshot(),
        show_grid: true,
        show_labels: true,
      },
    ].slice(-20);
    const payload = await jsonRequest('/territory/workspace-views', 'PUT', {
      map_dataset_id: props.territory.map.id,
      map_dataset_checksum: props.territory.map.checksum,
      expected_revision: workspaceRevision.value,
      views,
    });
    workspaceRevision.value = Number(payload.revision);
    savedViews.value = payload.views as WorkspaceView[];
    viewName.value = '';
    notice.value = {
      tone: 'success',
      message: t('territory.saved', { revision: workspaceRevision.value }),
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
function openView(view: WorkspaceView): void {
  applyLayers(view.layers);
  canvas.value?.setCamera({ x: view.center_x, y: view.center_y, zoom: view.zoom });
}

onMounted(() => void loadViews());
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

    <div class="mt-4 grid gap-4 xl:grid-cols-[14rem_minmax(0,1fr)_19rem]">
      <aside class="ks-surface p-4" :aria-label="t('territory.mapLayers')">
        <p class="ks-kicker">{{ t('territory.mapLayers') }}</p>
        <label class="mt-3 block text-sm font-semibold">
          {{ t('territory.filterObjects') }}
          <input v-model="query" class="ks-input mt-2 w-full" type="search" />
        </label>
        <div class="mt-4 space-y-2 text-sm">
          <label class="flex min-h-11 items-center gap-2"
            ><input v-model="showTerrain" type="checkbox" />Terrain</label
          >
          <label class="flex min-h-11 items-center gap-2"
            ><input v-model="showStructures" type="checkbox" />{{
              t('territory.showStructures')
            }}</label
          >
          <label class="flex min-h-11 items-center gap-2"
            ><input v-model="showFacilities" type="checkbox" />Facilities</label
          >
          <label class="flex min-h-11 items-center gap-2"
            ><input v-model="showResources" type="checkbox" />Resources</label
          >
          <label class="flex min-h-11 items-center gap-2"
            ><input v-model="showZones" type="checkbox" />{{ t('territory.showZones') }}</label
          >
        </div>

        <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
          <p class="ks-kicker">Coordinates</p>
          <div class="mt-2 grid grid-cols-2 gap-2">
            <input
              v-model.number="coordinateX"
              class="ks-input"
              type="number"
              :min="mapMinX"
              :max="mapMaxX"
              aria-label="X coordinate"
            />
            <input
              v-model.number="coordinateY"
              class="ks-input"
              type="number"
              :min="mapMinY"
              :max="mapMaxY"
              aria-label="Y coordinate"
            />
          </div>
          <AppButton class="mt-2 w-full" @click="jump">Jump</AppButton>
          <AppButton class="mt-2 w-full" @click="canvas?.fitMap()">Fit map</AppButton>
        </div>

        <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
          <p class="ks-kicker">Saved views</p>
          <input
            v-model="viewName"
            maxlength="80"
            class="ks-input mt-2 w-full"
            placeholder="View name"
          />
          <AppButton
            class="mt-2 w-full"
            :busy="busy"
            :disabled="!viewName.trim()"
            @click="saveCurrentView"
            >{{ t('territory.save') }}</AppButton
          >
          <button
            v-for="view in savedViews"
            :key="view.key"
            type="button"
            class="ks-command-link mt-2 w-full text-left"
            @click="openView(view)"
          >
            {{ view.name }}
          </button>
        </div>
      </aside>

      <main class="min-w-0">
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
          :show-terrain="showTerrain"
          :show-structures="showStructures"
          :show-facilities="showFacilities"
          :show-resources="showResources"
          :show-zones="showZones"
          @inspect-reference="inspectReference"
        />
        <section class="ks-surface mt-4 p-4" aria-live="polite">
          <p class="text-sm text-[var(--ks-muted)]">
            {{ formatNumber(scene.entities.length) }} scene entities ·
            {{ territory.map.observed_at }} ·
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

      <aside class="ks-surface p-4" aria-label="Object inspector">
        <p class="ks-kicker">Inspector</p>
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
            <dt>Size</dt>
            <dd>{{ selected.bounds.width }}×{{ selected.bounds.height }}</dd>
            <dt>Asset</dt>
            <dd class="break-all">{{ selected.assetKey ?? '—' }}</dd>
            <dt>Confidence</dt>
            <dd>{{ selected.confidence ?? territory.map.confidence }}</dd>
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
          Select a map object or choose one from search results.
        </p>

        <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
          <p class="ks-kicker">Objects</p>
          <div
            class="mt-2 max-h-[32rem] overflow-auto"
            role="list"
            aria-label="Searchable map objects"
          >
            <button
              v-for="entity in results"
              :key="entity.key"
              type="button"
              class="block min-h-11 w-full border-b border-[var(--ks-border)] px-2 py-2 text-left text-sm focus-visible:outline-2 focus-visible:outline-offset-[-2px]"
              :aria-pressed="selectedReference === entity.key"
              @click="inspect(entity)"
            >
              <span class="block font-semibold">{{ entity.label }}</span>
              <span class="text-xs text-[var(--ks-muted)]"
                >{{ entity.layer }} · X{{ entity.bounds.x }} Y{{ entity.bounds.y }}</span
              >
            </button>
          </div>
        </div>
      </aside>
    </div>
  </AppLayout>
</template>
