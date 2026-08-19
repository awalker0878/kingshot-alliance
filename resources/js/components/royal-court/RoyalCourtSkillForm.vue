<script setup lang="ts">
import { watch } from 'vue';

import { useContextForm } from '@/composables/useContextForm';
import { utcInput } from '@/lib/king-perks-display';
import type { SkillType, StrategyDay } from '@/types/king-perks';

const props = defineProps<{
  planId: string;
  skillTypes: SkillType[];
  strategy: StrategyDay | null;
}>();

const form = useContextForm({
  skill_key: props.skillTypes[0]?.key ?? '',
  planned_activation_at: '',
  effect_duration_minutes: 60,
  notes: '',
});

watch(
  () => props.strategy,
  (strategy) => {
    if (!strategy?.skill) return;
    if (props.skillTypes.some((item) => item.key === strategy.skill)) {
      form.skill_key = strategy.skill;
    }
    form.planned_activation_at = utcInput(strategy.startsAt);
  },
);

function submit(): void {
  form.post(`/king-perk-plans/${props.planId}/skills`, {
    preserveScroll: true,
    onSuccess: () => {
      form.planned_activation_at = '';
      form.notes = '';
    },
  });
}
</script>

<template>
  <form
    class="space-y-4 rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.70)] p-5"
    @submit.prevent="submit"
  >
    <div>
      <h2 class="text-lg font-semibold text-[var(--ks-ivory)]">Plan King Skill</h2>
      <p class="text-xs text-[var(--ks-muted)]">
        Enter the effect duration verified in the game; the application does not guess it.
      </p>
    </div>

    <label class="block space-y-1 text-xs text-[var(--ks-muted)]">
      <span>Skill</span>
      <select
        v-model="form.skill_key"
        class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
      >
        <option v-for="item in skillTypes" :key="item.key" :value="item.key">
          {{ item.label }} · {{ item.recommendedFocus }}
        </option>
      </select>
    </label>

    <label class="block space-y-1 text-xs text-[var(--ks-muted)]">
      <span>Activation (UTC)</span>
      <input
        v-model="form.planned_activation_at"
        required
        type="datetime-local"
        class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
      />
    </label>

    <label class="block space-y-1 text-xs text-[var(--ks-muted)]">
      <span>Verified effect duration (minutes)</span>
      <input
        v-model.number="form.effect_duration_minutes"
        required
        min="1"
        max="10080"
        type="number"
        class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
      />
    </label>

    <label class="block space-y-1 text-xs text-[var(--ks-muted)]">
      <span>Notes</span>
      <textarea
        v-model="form.notes"
        rows="2"
        class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
      />
    </label>

    <button
      type="submit"
      class="w-full rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-[var(--ks-ink)] disabled:opacity-60"
      :disabled="form.processing"
    >
      Plan skill
    </button>
  </form>
</template>
