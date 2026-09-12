<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import { useLocale } from '@/localization';
import type { ParticipantPage } from './participantPages';

const props = defineProps<{
  page: ParticipantPage<unknown>;
  total: number;
  kind: string;
  label: string;
  planId?: string | undefined;
  scope: string;
}>();
const { t } = useLocale();
const currentPage = usePage();
const busy = ref(false);
const failed = ref(false);
let retryCursor: string | null = null;
let cancel: (() => void) | undefined;
let generation = 0;
function navigate(cursor: string | null): void {
  if (busy.value) return;
  const request = ++generation;
  retryCursor = cursor;
  busy.value = true;
  failed.value = false;
  const fail = (): false => {
    if (request === generation) failed.value = true;
    return false;
  };
  const url = new URL(currentPage.url, window.location.origin);
  if (props.planId) url.searchParams.set('plan', props.planId);
  url.searchParams.delete(props.kind + '_cursor');
  if (cursor) url.searchParams.set(props.kind + '_cursor', cursor);
  router.get(
    url.pathname + url.search,
    {},
    {
      preserveState: true,
      preserveScroll: true,
      onCancelToken: (token) => {
        cancel = () => token.cancel();
      },
      onError: fail,
      onHttpException: fail,
      onNetworkError: fail,
      onFinish: () => {
        if (request === generation) {
          busy.value = false;
          cancel = undefined;
        }
      },
    },
  );
}
function reset(): void {
  generation++;
  cancel?.();
  cancel = undefined;
  busy.value = false;
  failed.value = false;
}
watch(() => props.scope, reset);
onBeforeUnmount(reset);
</script>

<template>
  <nav class="ks-surface mt-4 p-4" :aria-label="label" :data-transfer-catalogue="kind">
    <p class="text-sm text-[var(--ks-muted)]" aria-live="polite">
      {{ t('kingdomP7D.workflowHistoryTotal', { count: total }) }}
    </p>
    <div class="mt-3 flex flex-wrap gap-2">
      <button
        v-if="!page.isFirstPage"
        type="button"
        class="ks-command-button"
        data-variant="secondary"
        :disabled="busy"
        @click="navigate(null)"
      >
        {{ t('common.firstPage') }}
      </button>
      <button
        v-if="page.hasMore"
        type="button"
        class="ks-command-button"
        data-variant="secondary"
        :disabled="busy"
        :aria-busy="busy"
        @click="navigate(page.nextCursor)"
      >
        {{ t('common.nextPage') }}
      </button>
    </div>
    <div v-if="failed" class="mt-3" role="alert">
      <p>{{ t('kingdomP7D.workflowHistoryUnavailable') }}</p>
      <button
        type="button"
        class="ks-command-button mt-2"
        :disabled="busy"
        @click="navigate(retryCursor)"
      >
        {{ t('kingdomP7D.retryParticipantPage') }}
      </button>
    </div>
  </nav>
</template>
