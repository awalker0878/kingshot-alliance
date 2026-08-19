<script setup lang="ts">
import { displayLocal, displayUtc, humanize } from '@/lib/king-perks-display';
import type { Appointment, Plan } from '@/types/king-perks';

defineProps<{
  appointments: Appointment[];
  positionBlocks: Plan['positionBlocks'];
}>();

const emit = defineEmits<{
  edit: [appointment: Appointment];
  activate: [appointmentId: string];
  outcome: [appointmentId: string, status: 'completed' | 'no_show'];
  cancelledCooldown: [appointmentId: string];
}>();
</script>

<template>
  <div
    class="space-y-3 rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.70)] p-5"
  >
    <div>
      <h2 class="text-lg font-semibold text-[var(--ks-ivory)]">Appointment rotation</h2>
      <p class="text-sm text-[var(--ks-muted)]">
        The stored end time is derived from the selected appointment's occupancy duration.
      </p>
    </div>

    <p
      v-if="appointments.length === 0"
      class="rounded-xl border border-dashed border-[var(--ks-border)] p-6 text-sm text-[var(--ks-muted)]"
    >
      No appointments scheduled yet.
    </p>

    <article
      v-for="item in appointments"
      :key="item.id"
      class="rounded-xl border border-[var(--ks-border)] bg-[rgba(24,25,21,.70)] p-4"
    >
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <p class="font-semibold text-[var(--ks-ivory)]">
              {{ item.typeLabel }} · {{ item.playerName ?? item.playerId }}
            </p>
            <span
              class="rounded-full bg-[rgba(210,163,75,.05)] px-2 py-0.5 text-xs text-[var(--ks-muted)]"
            >
              {{ humanize(item.status) }}
            </span>
            <span
              v-if="!item.playerEligible"
              class="rounded-full bg-rose-400/10 px-2 py-0.5 text-xs text-rose-300"
            >
              reassignment required
            </span>
          </div>
          <p class="mt-1 text-sm text-[var(--ks-muted)]">
            {{ displayUtc(item.startsAt) }} → {{ displayUtc(item.endsAt) }}
          </p>
          <p class="text-xs text-[var(--ks-muted)]">
            {{ displayLocal(item.startsAt) }} → {{ displayLocal(item.endsAt) }}
          </p>
          <p class="mt-1 text-xs text-[var(--ks-muted)]">
            {{ item.durationMinutes }} min occupancy · {{ item.playerCooldownMinutes }} min Governor
            cooldown after appointment end
          </p>
          <p
            v-if="item.actualStartedAt || item.actualEndedAt"
            class="mt-1 text-xs text-emerald-300"
          >
            Actual: {{ item.actualStartedAt ? displayUtc(item.actualStartedAt) : 'not started' }} →
            {{ item.actualEndedAt ? displayUtc(item.actualEndedAt) : 'in progress' }}
          </p>
          <p v-if="item.notes" class="mt-1 text-xs text-[var(--ks-muted)]">{{ item.notes }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            v-if="item.status === 'scheduled' || item.status === 'confirmed'"
            type="button"
            class="rounded-lg border border-[var(--ks-border)] px-3 py-1.5 text-xs text-[var(--ks-ivory)]"
            @click="emit('edit', item)"
          >
            Edit
          </button>
          <button
            v-if="item.status === 'scheduled' || item.status === 'confirmed'"
            type="button"
            class="rounded-lg border border-emerald-400/30 px-3 py-1.5 text-xs text-emerald-200"
            @click="emit('activate', item.id)"
          >
            Active
          </button>
          <button
            v-if="item.status !== 'completed' && item.status !== 'cancelled'"
            type="button"
            class="rounded-lg border border-[var(--ks-border)] px-3 py-1.5 text-xs text-[var(--ks-ivory)]"
            @click="emit('outcome', item.id, 'completed')"
          >
            Complete
          </button>
          <button
            v-if="item.status !== 'completed' && item.status !== 'cancelled'"
            type="button"
            class="rounded-lg border border-[var(--ks-border)] px-3 py-1.5 text-xs text-[var(--ks-ivory)]"
            @click="emit('outcome', item.id, 'no_show')"
          >
            No-show
          </button>
          <button
            v-if="item.status !== 'completed' && item.status !== 'cancelled'"
            type="button"
            class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs text-rose-200"
            @click="emit('cancelledCooldown', item.id)"
          >
            Cancel + position cooldown
          </button>
        </div>
      </div>
    </article>

    <div v-if="positionBlocks.length" class="space-y-2 pt-2">
      <h3 class="text-sm font-semibold text-rose-200">Position cooldowns</h3>
      <div
        v-for="block in positionBlocks"
        :key="block.id"
        class="rounded-lg border border-rose-400/20 bg-rose-950/20 px-3 py-2 text-xs text-rose-100"
      >
        {{ humanize(block.type) }} · {{ displayUtc(block.startsAt) }} →
        {{ displayUtc(block.endsAt) }} · {{ humanize(block.reason) }}
      </div>
    </div>
  </div>
</template>
