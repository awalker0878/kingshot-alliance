<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { useLocale } from '@/localization';

withDefaults(
  defineProps<{
    summary: string;
    isFirstPage: boolean;
    firstPageHref: string;
    hasMore: boolean;
    busy?: boolean;
  }>(),
  { busy: false },
);

defineEmits<{ next: [] }>();

const { t } = useLocale();
</script>

<template>
  <nav
    class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--ks-border)] p-5"
    :aria-label="t('common.pagination')"
  >
    <p class="text-xs text-[var(--ks-muted)]" aria-live="polite">{{ summary }}</p>
    <div class="flex flex-wrap gap-2">
      <Link
        v-if="!isFirstPage"
        :href="firstPageHref"
        class="ks-command-link"
        data-variant="secondary"
      >
        {{ t('common.firstPage') }}
      </Link>
      <button
        v-if="hasMore"
        type="button"
        class="ks-command-button"
        data-variant="secondary"
        :disabled="busy"
        :aria-busy="busy ? 'true' : undefined"
        @click="$emit('next')"
      >
        {{ t('common.nextPage') }}
      </button>
    </div>
  </nav>
</template>
