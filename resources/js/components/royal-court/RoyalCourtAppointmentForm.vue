<script setup lang="ts">
import { computed, watch } from 'vue';

import { useContextForm } from '@/composables/useContextForm';
import { utcInput } from '@/lib/king-perks-display';
import type { Appointment, AppointmentType, GovernorOption } from '@/types/king-perks';

const props = defineProps<{
  planId: string;
  appointmentTypes: AppointmentType[];
  players: GovernorOption[];
  editing: Appointment | null;
}>();

const emit = defineEmits<{
  cleared: [];
}>();

const form = useContextForm({
  appointment_id: '',
  appointment_type: props.appointmentTypes[0]?.key ?? '',
  player_id: props.players[0]?.id ?? '',
  starts_at: '',
  notes: '',
});

const selectedAppointment = computed(() =>
  props.appointmentTypes.find((item) => item.key === form.appointment_type),
);

watch(
  () => props.editing,
  (editing) => {
    if (!editing) return;

    form.appointment_id = editing.id;
    form.appointment_type = editing.type;
    form.player_id = editing.playerId;
    form.starts_at = utcInput(editing.startsAt);
    form.notes = editing.notes ?? '';
  },
);

function clear(): void {
  form.reset();
  form.clearErrors();
  emit('cleared');
}

function submit(): void {
  form.post(`/king-perk-plans/${props.planId}/appointments`, {
    preserveScroll: true,
    onSuccess: clear,
  });
}
</script>

<template>
  <form
    id="appointment-form"
    class="space-y-4 rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.70)] p-5"
    @submit.prevent="submit"
  >
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-[var(--ks-ivory)]">
          {{ form.appointment_id ? 'Reassign appointment' : 'Assign appointment' }}
        </h2>
        <p class="text-xs text-[var(--ks-muted)]">End time is calculated automatically.</p>
      </div>
      <button
        v-if="form.appointment_id"
        type="button"
        class="text-xs text-[var(--ks-muted)]"
        @click="clear"
      >
        Clear
      </button>
    </div>

    <label class="block space-y-1 text-xs text-[var(--ks-muted)]">
      <span>Position</span>
      <select
        v-model="form.appointment_type"
        class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
      >
        <option v-for="item in appointmentTypes" :key="item.key" :value="item.key">
          {{ item.label }}
        </option>
      </select>
    </label>

    <div
      v-if="selectedAppointment"
      class="rounded-lg bg-[rgba(24,25,21,.86)] p-3 text-xs text-[var(--ks-muted)]"
    >
      Occupies {{ selectedAppointment.durationMinutes }} min · Governor cooldown
      {{ selectedAppointment.playerCooldownMinutes }} min · cancelled-position block
      {{ selectedAppointment.cancelledPositionCooldownMinutes }} min
    </div>

    <label class="block space-y-1 text-xs text-[var(--ks-muted)]">
      <span>Governor</span>
      <select
        v-model="form.player_id"
        class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-stone)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
      >
        <option v-for="player in players" :key="player.id" :value="player.id">
          {{ player.name }}
        </option>
      </select>
    </label>

    <label class="block space-y-1 text-xs text-[var(--ks-muted)]">
      <span>Starts at (UTC)</span>
      <input
        v-model="form.starts_at"
        required
        type="datetime-local"
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
      {{ form.appointment_id ? 'Save reassignment' : 'Assign' }}
    </button>
  </form>
</template>
