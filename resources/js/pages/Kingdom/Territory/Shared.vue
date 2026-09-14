<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

import TerritoryCanvas from '@/features/territory-planner/components/TerritoryCanvas.vue';
import type { MapData, PlanAlliance, PlanObject } from '@/features/territory-planner/engine/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type SharedSnapshot = {
  plan: { id: string; name: string; scope: string; map_dataset_id: string; map_dataset_checksum: string };
  alliances: PlanAlliance[];
  objects: PlanObject[];
};
type SharedProjection = {
  share_id: string;
  revision_id: string;
  revision_number: number;
  snapshot_checksum: string;
  projection_checksum: string;
  expires_at: string;
  snapshot: SharedSnapshot;
  map: { id: string; checksum: string; source_label: string; confidence: string; observed_at: string; data: MapData };
};

const props = defineProps<{
  user: { name: string; email: string };
  activePlayer: { id: string; name: string; kingdomNumber: number | null };
  shareId: string;
}>();
const { t, formatDate } = useLocale();
const projection = ref<SharedProjection | null>(null);
const error = ref<string | null>(null);
const loading = ref(true);

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function resolveShare(): Promise<void> {
  const fragment = new URLSearchParams(window.location.hash.replace(/^#/, ''));
  const token = fragment.get('token') ?? '';
  history.replaceState(null, '', window.location.pathname);
  if (!/^[a-f0-9]{64}$/.test(token)) {
    error.value = t('territory.sharedLinkInvalid');
    loading.value = false;
    return;
  }
  try {
    const response = await fetch(`/territory/shared/${encodeURIComponent(props.shareId)}`, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      referrerPolicy: 'no-referrer',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ token }),
    });
    if (!response.ok) throw new Error(t('territory.sharedLinkUnavailable'));
    projection.value = (await response.json()) as SharedProjection;
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : t('territory.sharedLinkUnavailable');
  } finally {
    loading.value = false;
  }
}

onMounted(resolveShare);
</script>

<template>
  <AppLayout :user="user">
    <Head :title="t('territory.privateShareViewer')" />
    <section class="ks-surface p-4" aria-labelledby="territory-shared-heading">
      <p class="ks-kicker">{{ t('territory.privateSharing') }}</p>
      <h1 id="territory-shared-heading" class="ks-display mt-1 text-2xl font-semibold">
        {{ projection?.snapshot.plan.name ?? t('territory.privateShareViewer') }}
      </h1>
      <p v-if="projection" class="mt-2 text-sm text-[var(--ks-muted)]">
        {{ t('territory.revisionNumber', { revision: projection.revision_number }) }} ·
        {{ t('territory.shareExpires', { date: formatDate(projection.expires_at) }) }}
      </p>
      <p v-if="loading" class="mt-4" role="status">{{ t('territory.collaborationLoading') }}</p>
      <div v-else-if="error" class="mt-4 rounded border border-red-500/40 p-3 text-red-100" role="alert">
        {{ error }}
      </div>
      <div v-else-if="projection" class="mt-4">
        <div class="h-[min(70vh,720px)] overflow-hidden rounded border border-[var(--ks-border)]">
          <TerritoryCanvas
            :map="projection.map.data"
            :map-checksum="projection.map.checksum"
            :alliances="projection.snapshot.alliances"
            :objects="projection.snapshot.objects"
            :selected-keys="[]"
            tool="pan"
            placement-type="governor_city"
            :active-alliance-key="null"
            :label="t('territory.privateShareViewer')"
            read-only
          />
        </div>
        <p class="mt-2 text-xs text-[var(--ks-muted)]">
          {{ projection.map.source_label }} · {{ projection.map.id }} · {{ projection.projection_checksum }}
        </p>
      </div>
      <Link href="/territory" class="ks-command-link mt-4 inline-flex">{{ t('territory.backToPlans') }}</Link>
    </section>
  </AppLayout>
</template>
