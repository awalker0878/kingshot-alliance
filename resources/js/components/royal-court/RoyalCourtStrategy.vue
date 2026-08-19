<script setup lang="ts">
import { displayUtc, humanize } from '@/lib/king-perks-display';
import type { StrategyDay } from '@/types/king-perks';

defineProps<{ days: StrategyDay[] }>();

const emit = defineEmits<{
  apply: [day: StrategyDay];
}>();
</script>

<template>
  <section
    class="space-y-4 rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.70)] p-5"
  >
    <div>
      <h2 class="text-lg font-semibold text-[var(--ks-ivory)]">Court Auto-Assign</h2>
      <p class="text-sm text-[var(--ks-muted)]">
        These arrangements help officers fill the Court. Alliance leadership remains in control.
      </p>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="day in days"
        :key="day.day"
        class="rounded-xl border border-[var(--ks-border)] bg-[rgba(24,25,21,.70)] p-4"
      >
        <div class="flex items-center justify-between gap-3">
          <p class="text-xs font-semibold tracking-wider text-amber-300 uppercase">
            Preparation day {{ day.day }}
          </p>
          <button
            type="button"
            class="text-xs font-semibold text-[var(--ks-gold)] hover:text-[var(--ks-gold-bright)]"
            @click="emit('apply', day)"
          >
            Use in planner
          </button>
        </div>
        <p class="mt-2 font-semibold text-[var(--ks-ivory)]">{{ humanize(day.focus) }}</p>
        <p class="mt-1 text-xs text-[var(--ks-muted)]">
          {{ displayUtc(day.startsAt) }} → {{ displayUtc(day.endsAt) }}
        </p>
        <p v-if="day.skill" class="mt-2 text-sm text-[var(--ks-muted)]">
          King Skill: {{ humanize(day.skill) }}
        </p>
        <p v-if="day.appointmentTypes.length" class="text-sm text-[var(--ks-muted)]">
          Appointments: {{ day.appointmentTypes.map(humanize).join(' → ') }}
        </p>
        <p class="mt-2 text-xs leading-5 text-[var(--ks-muted)]">{{ day.strategyNote }}</p>
      </article>
    </div>
  </section>
</template>
