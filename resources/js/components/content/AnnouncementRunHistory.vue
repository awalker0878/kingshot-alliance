<script setup lang="ts">
import { ref, watch } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import ContentHistoryControls from './ContentHistoryControls.vue';
import { useContentHistory } from '@/features/alliance-content/useContentHistory';
import type { BroadcastRun } from '@/features/alliance-content/types';
import { useLocale } from '@/localization';

const props = defineProps<{
  contentId: string;
  count: number;
  refresh: number;
  retryBusyId: string | null;
}>();
const emit = defineEmits<{ retry: [run: BroadcastRun] }>();
const { t, formatDate } = useLocale();
const opened = ref(false);
const { items, total, nextCursor, currentCursor, busy, failed, loaded, load } =
  useContentHistory<BroadcastRun>(
    () => `/alliance/content/manage/${encodeURIComponent(props.contentId)}/runs`,
  );
function toggle(event: Event): void {
  opened.value = (event.target as HTMLDetailsElement).open;
  if (opened.value && !loaded.value) void load();
}
watch(
  () => props.refresh,
  () => {
    if (opened.value) void load(currentCursor.value);
  },
);
</script>
<template>
  <details class="mt-4" @toggle="toggle">
    <summary class="cursor-pointer text-sm font-semibold">
      {{ t('contentExperience.deliveryHistory') }} · {{ loaded ? total : count }}
    </summary>
    <p v-if="busy" class="mt-3 text-sm" role="status">{{ t('common.loading') }}</p>
    <div v-if="failed" class="mt-3" role="alert">
      <p>{{ t('contentExperience.collectionFailed') }}</p>
      <AppButton type="button" variant="ghost" :disabled="busy" @click="load()">{{
        t('common.firstPage')
      }}</AppButton>
    </div>
    <div class="mt-3 space-y-2">
      <article
        v-for="run in items"
        :key="run.id"
        :data-broadcast-run-id="run.id"
        class="rounded border border-[var(--ks-border)] bg-black/10 p-3"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <strong class="text-sm">{{
              formatDate(run.scheduledFor, { dateStyle: 'medium', timeStyle: 'short' })
            }}</strong>
            <p class="mt-1 text-xs" :data-broadcast-status="run.status">
              {{ t(`contentExperience.broadcastState.${run.status}`) }}
            </p>
            <p class="mt-1 text-xs text-[var(--ks-muted)]">
              {{
                t('contentExperience.broadcastProgress', {
                  examined: run.recipientCount + run.skippedCount,
                  skipped: run.skippedCount,
                  suppressed: run.suppressedCount,
                  replayed: run.replayedCount,
                })
              }}
            </p>
            <p class="mt-1 text-xs text-[var(--ks-muted)]">
              {{
                t('contentExperience.deliveryRunSummary', {
                  recipients: run.recipientCount,
                  sent: run.deliveryCounts.sent ?? 0,
                  queued: (run.deliveryCounts.queued ?? 0) + (run.deliveryCounts.pending ?? 0),
                  failed: run.deliveryCounts.failed ?? 0,
                  read: run.readCount,
                })
              }}
            </p>
          </div>
          <p
            v-if="run.retryCandidateCount > run.failedDeliveryIds.length"
            class="text-xs text-[var(--ks-muted)]"
            role="status"
          >
            {{
              t('contentExperience.retryCandidateSummary', {
                selected: run.failedDeliveryIds.length,
                total: run.retryCandidateCount,
              })
            }}
          </p>
          <AppButton
            v-if="run.failedDeliveryIds.length"
            type="button"
            variant="ghost"
            :busy="retryBusyId === run.id"
            :busy-label="t('contentExperience.retryingFailures')"
            @click="emit('retry', run)"
          >
            {{ t('contentExperience.retryFailed', { count: run.failedDeliveryIds.length }) }}
          </AppButton>
        </div>
      </article>
    </div>
    <p v-if="loaded && !items.length && !busy && !failed" class="mt-3 text-xs">
      {{ t('contentExperience.noDeliveryHistory') }}
    </p>
    <ContentHistoryControls
      v-if="loaded && !failed"
      :count="items.length"
      :total="total"
      :has-more="nextCursor !== null"
      :is-first-page="currentCursor === null"
      :busy="busy"
      @first="load()"
      @next="load(nextCursor)"
    />
  </details>
</template>
