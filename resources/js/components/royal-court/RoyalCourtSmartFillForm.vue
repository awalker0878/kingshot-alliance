<script setup lang="ts">
import { watch } from 'vue';

import { useContextForm } from '@/composables/useContextForm';
import { utcInput } from '@/lib/king-perks-display';
import type { PushCategory, StrategyDay } from '@/types/king-perks';

const props = defineProps<{
  planId: string;
  categories: PushCategory[];
  strategy: StrategyDay | null;
}>();

const form = useContextForm({
  push_category: props.categories[0]?.key ?? '',
  from: '',
  until: '',
  limit: 200,
});

watch(
  () => props.strategy,
  (strategy) => {
    if (!strategy) return;
    if (strategy.focus && props.categories.some((item) => item.key === strategy.focus)) {
      form.push_category = strategy.focus;
    }
    form.from = utcInput(strategy.startsAt);
    form.until = utcInput(strategy.endsAt);
  },
);

function submit(): void {
  form.post(`/king-perk-plans/${props.planId}/auto-schedule`, {
    preserveScroll: true,
  });
}
</script>

<template>
  <form
    class="space-y-4 rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.70)] p-5"
    @submit.prevent="submit"
  >
    <div>
      <h2 class="text-lg font-semibold text-[var(--ks-ivory)]">Smart fill</h2>
      <p class="text-xs text-[var(--ks-muted)]">
        Fills legal duration-aware windows from submitted availability. Training uses Noble Advisor
        first, then Chief Minister overflow.
      </p>
    </div>
    <label class="block space-y-1 text-xs text-[var(--ks-muted)]">
      <span>Focus</span>
      <select
        v-model="form.push_category"
        class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
      >
        <option v-for="category in categories" :key="category.key" :value="category.key">
          {{ category.label }}
        </option>
      </select>
    </label>
    <label class="block space-y-1 text-xs text-[var(--ks-muted)]">
      <span>From (UTC)</span>
      <input
        v-model="form.from"
        required
        type="datetime-local"
        class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
      />
    </label>
    <label class="block space-y-1 text-xs text-[var(--ks-muted)]">
      <span>Until (UTC)</span>
      <input
        v-model="form.until"
        required
        type="datetime-local"
        class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
      />
    </label>
    <label class="block space-y-1 text-xs text-[var(--ks-muted)]">
      <span>Maximum assignments</span>
      <input
        v-model.number="form.limit"
        min="1"
        max="500"
        type="number"
        class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
      />
    </label>
    <button
      type="submit"
      class="w-full rounded-lg bg-[var(--ks-gold)] px-4 py-2 text-sm font-semibold text-[var(--ks-ink)] disabled:opacity-60"
      :disabled="form.processing"
    >
      Auto-fill window
    </button>
  </form>
</template>
