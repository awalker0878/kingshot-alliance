<script setup lang="ts">
import { computed } from 'vue';

import { displayLocal, displayUtc, humanize, speedups } from '@/lib/king-perks-display';
import type { PerkRequest } from '@/types/king-perks';

const props = defineProps<{ requests: PerkRequest[] }>();

const emit = defineEmits<{
  decline: [requestId: string];
}>();

const submittedCount = computed(
  () => props.requests.filter((item) => item.status === 'submitted').length,
);
</script>

<template>
  <div class="space-y-3 rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.70)] p-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-[var(--ks-ivory)]">Governor Requests</h2>
        <p class="text-sm text-[var(--ks-muted)]">Ranked only within each activity category.</p>
      </div>
      <span class="text-xs text-[var(--ks-muted)]"> {{ submittedCount }} awaiting scheduling </span>
    </div>

    <p
      v-if="requests.length === 0"
      class="rounded-xl border border-dashed border-[var(--ks-border)] p-5 text-sm text-[var(--ks-muted)]"
    >
      No requests submitted yet.
    </p>

    <article
      v-for="item in requests"
      :key="item.id"
      class="rounded-xl border border-[var(--ks-border)] bg-[rgba(24,25,21,.70)] p-4"
    >
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <p class="font-semibold text-[var(--ks-ivory)]">
              {{ item.playerName ?? item.playerId }}
            </p>
            <span
              class="rounded-full bg-[rgba(210,163,75,.05)] px-2 py-0.5 text-xs text-[var(--ks-muted)]"
            >
              {{ item.categoryLabel }}
            </span>
            <span class="text-xs text-[var(--ks-muted)]">{{ item.status }}</span>
          </div>
          <p class="mt-1 text-sm text-[var(--ks-muted)]">
            {{ displayUtc(item.availabilityStartsAt) }} →
            {{ displayUtc(item.availabilityEndsAt) }}
          </p>
          <p class="text-xs text-[var(--ks-muted)]">
            {{ displayLocal(item.availabilityStartsAt) }} →
            {{ displayLocal(item.availabilityEndsAt) }}
          </p>
          <p class="mt-2 text-xs text-[var(--ks-muted)]">
            Speedups {{ speedups(item.plannedSpeedupMinutes) }} · preferred
            {{ humanize(item.preferredAppointmentType) }}
          </p>
          <p v-if="item.notes" class="mt-1 text-xs text-[var(--ks-muted)]">{{ item.notes }}</p>
        </div>
        <button
          v-if="item.status === 'submitted'"
          type="button"
          class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs text-rose-200"
          @click="emit('decline', item.id)"
        >
          Decline
        </button>
      </div>
    </article>
  </div>
</template>
