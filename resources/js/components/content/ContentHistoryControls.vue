<script setup lang="ts">
import AppButton from '@/components/ui/AppButton.vue';
import { useLocale } from '@/localization';

defineProps<{
  count: number;
  total: number;
  hasMore: boolean;
  isFirstPage: boolean;
  busy: boolean;
}>();
defineEmits<{ first: []; next: [] }>();
const { t } = useLocale();
</script>
<template>
  <nav
    class="mt-3 flex flex-wrap items-center justify-between gap-2"
    :aria-label="t('common.pagination')"
  >
    <p class="text-xs text-[var(--ks-muted)]" aria-live="polite">
      {{ t('contentExperience.pageRecords', { count, total }) }}
    </p>
    <div class="flex gap-2">
      <AppButton
        v-if="!isFirstPage"
        type="button"
        variant="ghost"
        :disabled="busy"
        @click="$emit('first')"
        >{{ t('common.firstPage') }}</AppButton
      >
      <AppButton
        v-if="hasMore"
        type="button"
        variant="ghost"
        :disabled="busy"
        @click="$emit('next')"
        >{{ t('common.nextPage') }}</AppButton
      >
    </div>
  </nav>
</template>
