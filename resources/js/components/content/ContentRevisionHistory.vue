<script setup lang="ts">
import { ref, watch } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import ContentHistoryControls from './ContentHistoryControls.vue';
import { useContentHistory } from '@/features/alliance-content/useContentHistory';
import type { ContentRevisionRow } from '@/features/alliance-content/types';
import { useLocale } from '@/localization';

const props = defineProps<{ contentId: string; count: number; refresh: number }>();
defineEmits<{ restore: [revisionId: string] }>();
const { t, formatDate } = useLocale();
const opened = ref(false);
const { items, total, nextCursor, currentCursor, busy, failed, loaded, load } =
  useContentHistory<ContentRevisionRow>(
    () => `/alliance/content/manage/${encodeURIComponent(props.contentId)}/revisions`,
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
  <details class="p-4 sm:p-5" @toggle="toggle">
    <summary class="cursor-pointer text-sm font-semibold">
      {{ t('contentExperience.revisions') }} · {{ loaded ? total : count }}
    </summary>
    <p v-if="busy" role="status" class="mt-3 text-sm">{{ t('common.loading') }}</p>
    <div v-if="failed" class="mt-3" role="alert">
      <p>{{ t('contentExperience.collectionFailed') }}</p>
      <AppButton type="button" variant="ghost" :disabled="busy" @click="load()">{{
        t('common.firstPage')
      }}</AppButton>
    </div>
    <div class="mt-3 space-y-2">
      <div
        v-for="revision in items"
        :key="revision.id"
        class="flex flex-wrap items-center justify-between gap-3 rounded border border-[var(--ks-border)] p-3"
      >
        <span class="text-sm"
          >#{{ revision.revisionNumber }} · {{ revision.title }} ·
          {{
            revision.createdAt
              ? formatDate(revision.createdAt, { dateStyle: 'medium', timeStyle: 'short' })
              : '—'
          }}</span
        >
        <button type="button" class="ks-chip" @click="$emit('restore', revision.id)">
          {{ t('contentExperience.restoreRevision', { number: revision.revisionNumber }) }}
        </button>
      </div>
    </div>
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
