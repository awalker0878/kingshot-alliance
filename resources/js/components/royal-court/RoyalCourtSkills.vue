<script setup lang="ts">
import { displayUtc, humanize } from '@/lib/king-perks-display';
import type { Skill } from '@/types/king-perks';

defineProps<{ skills: Skill[] }>();

const emit = defineEmits<{
  mark: [skillId: string, state: 'scheduled' | 'activated'];
}>();
</script>

<template>
  <div
    class="space-y-3 rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.70)] p-5"
  >
    <div>
      <h2 class="text-lg font-semibold text-[var(--ks-ivory)]">King Skills</h2>
      <p class="text-sm text-[var(--ks-muted)]">
        Skill effects are tracked separately from personal appointment occupancy.
      </p>
    </div>

    <p
      v-if="skills.length === 0"
      class="rounded-xl border border-dashed border-[var(--ks-border)] p-5 text-sm text-[var(--ks-muted)]"
    >
      No King Skills planned yet.
    </p>

    <article
      v-for="item in skills"
      :key="item.id"
      class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--ks-border)] bg-[rgba(24,25,21,.70)] p-4"
    >
      <div>
        <p class="font-semibold text-[var(--ks-ivory)]">{{ item.label }}</p>
        <p class="mt-1 text-sm text-[var(--ks-muted)]">
          {{ displayUtc(item.plannedActivationAt) }} → {{ displayUtc(item.plannedEndsAt) }} ·
          {{ item.effectDurationMinutes }} min
        </p>
        <p class="text-xs text-[var(--ks-muted)]">
          Scheduling window opens {{ displayUtc(item.scheduleAvailableAt) }} ·
          {{ humanize(item.status) }}
        </p>
      </div>
      <div class="flex gap-2">
        <button
          v-if="item.status === 'planned'"
          type="button"
          class="rounded-lg border border-[var(--ks-border)] px-3 py-1.5 text-xs text-[var(--ks-ivory)]"
          @click="emit('mark', item.id, 'scheduled')"
        >
          Scheduled in game
        </button>
        <button
          v-if="item.status === 'planned' || item.status === 'scheduled_in_game'"
          type="button"
          class="rounded-lg border border-emerald-400/30 px-3 py-1.5 text-xs text-emerald-200"
          @click="emit('mark', item.id, 'activated')"
        >
          Activated
        </button>
      </div>
    </article>
  </div>
</template>
