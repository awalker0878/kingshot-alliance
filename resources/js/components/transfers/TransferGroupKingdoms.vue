<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { useLocale } from '@/localization';
import type { ParticipantPage } from './participantPages';

const props = defineProps<{ planId: string; groupId: string; scope: string; total: number }>();
const { t, formatNumber } = useLocale();
const page = ref<ParticipantPage<{ id: string; number: string }> | null>(null);
const busy = ref(false);
const failed = ref(false);
let active: AbortController | null = null;
let attempt: string | null = null;
let generation = 0;
function reset(): void {
  generation++;
  active?.abort();
  active = null;
  page.value = null;
  busy.value = false;
  failed.value = false;
}
watch(() => [props.scope, props.planId, props.groupId], reset);
onBeforeUnmount(reset);
async function load(cursor: string | null = null): Promise<void> {
  active?.abort();
  const controller = new AbortController();
  active = controller;
  const request = ++generation;
  attempt = cursor;
  busy.value = true;
  failed.value = false;
  const params = new URLSearchParams();
  if (cursor) params.set('cursor', cursor);
  try {
    const response = await fetch(
      `/alliance/transfers/manage/plans/${props.planId}/groups/${props.groupId}/kingdoms?${params}`,
      {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: controller.signal,
      },
    );
    if (!response.ok) throw new Error('Group membership unavailable.');
    const result: unknown = await response.json();
    if (
      typeof result !== 'object' ||
      result === null ||
      !('items' in result) ||
      !Array.isArray(result.items) ||
      result.items.length > 25 ||
      !result.items.every(
        (item: unknown) =>
          typeof item === 'object' &&
          item !== null &&
          'id' in item &&
          typeof item.id === 'string' &&
          'number' in item &&
          typeof item.number === 'string',
      ) ||
      !('nextCursor' in result) ||
      !(result.nextCursor === null || typeof result.nextCursor === 'string') ||
      !('hasMore' in result) ||
      result.hasMore !== (result.nextCursor !== null) ||
      !('pageSize' in result) ||
      result.pageSize !== 25 ||
      !('isFirstPage' in result) ||
      typeof result.isFirstPage !== 'boolean'
    ) {
      throw new Error('Invalid group membership page.');
    }
    if (request === generation)
      page.value = result as ParticipantPage<{ id: string; number: string }>;
  } catch {
    if (request === generation) failed.value = true;
  } finally {
    if (request === generation) busy.value = false;
  }
}
</script>

<template>
  <div class="mt-2 space-y-2" data-testid="transfer-group-kingdoms">
    <button type="button" class="ks-command-link" :disabled="busy" @click="load()">
      {{ t('kingdomP7D.kingdomNumbers') }} · {{ formatNumber(total) }}
    </button>
    <p v-if="page" class="text-sm break-words">
      {{ page.items.map((item) => item.number).join(', ') }}
    </p>
    <div v-if="page" class="flex flex-wrap gap-2">
      <button
        v-if="!page.isFirstPage"
        type="button"
        class="ks-command-link"
        :disabled="busy"
        @click="load()"
      >
        {{ t('common.firstPage') }}
      </button>
      <button
        v-if="page.hasMore"
        type="button"
        class="ks-command-link"
        :disabled="busy"
        @click="load(page.nextCursor)"
      >
        {{ t('common.nextPage') }}
      </button>
    </div>
    <div v-if="failed" role="alert">
      <p>{{ t('kingdomP7D.workflowHistoryUnavailable') }}</p>
      <button type="button" class="ks-command-link" :disabled="busy" @click="load(attempt)">
        {{ t('kingdomP7D.retryParticipantPage') }}
      </button>
    </div>
  </div>
</template>
