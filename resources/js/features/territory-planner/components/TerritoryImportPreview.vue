<script setup lang="ts">
import AppButton from '@/components/ui/AppButton.vue';
import { useLocale } from '@/localization';
import type { PlanAlliance, PlanObject, ValidationIssue } from '../engine/types';

/**
 * Import preview panel. It is loaded on demand because it only appears after a planner
 * parses an interchange document, and it renders counts plus validation findings verbatim
 * so nothing is presented as committed before the planner confirms it.
 */
defineProps<{
  preview: {
    can_commit: boolean;
    map: { id: string; source_label: string };
    alliances: PlanAlliance[];
    objects: PlanObject[];
    validation: { violations: ValidationIssue[]; warnings: ValidationIssue[] };
  };
}>();
defineEmits<{ commit: [] }>();

const { t, formatNumber } = useLocale();
</script>

<template>
  <div class="mt-4 rounded border border-[var(--ks-border)] p-3">
    <p class="text-sm font-semibold">{{ t('territory.importPreview') }}</p>
    <dl class="mt-2 grid gap-2 text-xs sm:grid-cols-4">
      <div>
        <dt class="text-[var(--ks-muted)]">{{ t('territory.layers') }}</dt>
        <dd>{{ formatNumber(preview.alliances.length) }}</dd>
      </div>
      <div>
        <dt class="text-[var(--ks-muted)]">{{ t('territory.object') }}</dt>
        <dd>{{ formatNumber(preview.objects.length) }}</dd>
      </div>
      <div>
        <dt class="text-[var(--ks-muted)]">{{ t('territory.violations') }}</dt>
        <dd>{{ formatNumber(preview.validation.violations.length) }}</dd>
      </div>
      <div>
        <dt class="text-[var(--ks-muted)]">{{ t('territory.warnings') }}</dt>
        <dd>{{ formatNumber(preview.validation.warnings.length) }}</dd>
      </div>
    </dl>
    <p class="mt-2 text-xs text-[var(--ks-muted)]">
      {{ preview.map.source_label }} · {{ preview.map.id }}
    </p>
    <ul v-if="preview.validation.violations.length" class="mt-2 space-y-1 text-xs text-red-200">
      <li
        v-for="item in preview.validation.violations"
        :key="`import-v-${item.code}-${item.object_key}`"
      >
        {{ item.message }}
      </li>
    </ul>
    <ul v-if="preview.validation.warnings.length" class="mt-2 space-y-1 text-xs text-amber-200">
      <li
        v-for="item in preview.validation.warnings"
        :key="`import-w-${item.code}-${item.object_key}`"
      >
        {{ item.message }}
      </li>
    </ul>
    <AppButton class="mt-3" :disabled="!preview.can_commit" @click="$emit('commit')">
      {{ t('territory.applyImport') }}
    </AppButton>
  </div>
</template>
