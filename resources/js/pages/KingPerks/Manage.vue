<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';

type AppointmentType = {
  key: string;
  label: string;
  durationMinutes: number;
  playerCooldownMinutes: number;
  cancelledPositionCooldownMinutes: number;
  recommendedFocus: string;
};

type SkillType = {
  key: string;
  label: string;
  recommendedFocus: string;
  advanceSchedulingMinutes: number;
};

type Appointment = {
  id: string;
  type: string;
  typeLabel: string;
  playerId: string;
  playerName: string;
  startsAt: string;
  endsAt: string;
  status: string;
  confirmedAt: string | null;
  notes: string | null;
};

type Skill = {
  id: string;
  key: string;
  label: string;
  plannedActivationAt: string;
  plannedEndsAt: string;
  effectDurationMinutes: number;
  scheduleAvailableAt: string;
  status: string;
  notes: string | null;
};

type Plan = {
  id: string;
  status: string;
  windowStartsAt: string;
  windowEndsAt: string;
  publishedAt: string | null;
  appointments: Appointment[];
  positionBlocks: Array<{
    id: string;
    type: string;
    startsAt: string;
    endsAt: string;
    reason: string;
  }>;
  skills: Skill[];
};

const props = defineProps<{
  user: { name: string; email: string };
  event: {
    id: string;
    title: string | null;
    typeSlug: string;
    kingdomId: string;
    kingdomName: string;
  };
  occurrence: { id: string; startsAt: string; endsAt: string };
  plan: Plan | null;
  players: Array<{ id: string; name: string }>;
  appointmentTypes: AppointmentType[];
  skillTypes: SkillType[];
}>();

const appointment = reactive({
  appointment_type: props.appointmentTypes[0]?.key ?? '',
  player_id: props.players[0]?.id ?? '',
  starts_at: '',
  notes: '',
});

const skill = reactive({
  skill_key: props.skillTypes[0]?.key ?? '',
  planned_activation_at: '',
  effect_duration_minutes: 60,
  notes: '',
});

const selectedAppointment = computed(() =>
  props.appointmentTypes.find((item) => item.key === appointment.appointment_type),
);

function displayUtc(value: string): string {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'UTC',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(new Date(value));
}

function createPlan(): void {
  router.post(`/events/${props.event.id}/occurrences/${props.occurrence.id}/king-perks`);
}

function publishPlan(): void {
  if (!props.plan) return;
  router.post(`/king-perk-plans/${props.plan.id}/publish`);
}

function assignAppointment(): void {
  if (!props.plan) return;
  router.post(`/king-perk-plans/${props.plan.id}/appointments`, { ...appointment }, {
    preserveScroll: true,
    onSuccess: () => {
      appointment.player_id = props.players[0]?.id ?? '';
      appointment.starts_at = '';
      appointment.notes = '';
    },
  });
}

function markOutcome(id: string, status: 'completed' | 'no_show'): void {
  router.patch(`/king-perk-appointments/${id}/outcome`, { status }, { preserveScroll: true });
}

function recordCancelledCooldown(id: string): void {
  router.post(`/king-perk-appointments/${id}/cancelled-cooldown`, {}, { preserveScroll: true });
}

function planSkill(): void {
  if (!props.plan) return;
  router.post(`/king-perk-plans/${props.plan.id}/skills`, { ...skill }, {
    preserveScroll: true,
    onSuccess: () => {
      skill.planned_activation_at = '';
      skill.notes = '';
    },
  });
}

function markSkill(id: string, state: 'scheduled' | 'activated'): void {
  router.post(`/king-skill-plans/${id}/${state}`, {}, { preserveScroll: true });
}
</script>

<template>
  <Head :title="`King Perks · ${event.kingdomName}`" />

  <AppLayout :user="user" :player-alliance-name="null" :has-player-alliance="false">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <header class="space-y-2">
        <p class="text-xs font-semibold tracking-[0.2em] text-amber-300 uppercase">
          Kingdom of Power · King Perks
        </p>
        <h1 class="text-3xl font-semibold text-white">{{ event.kingdomName }}</h1>
        <p class="max-w-3xl text-sm text-slate-300">
          Schedule King appointments and Kingdom-wide King Skills against the preparation window.
          Appointment end times and cooldown blocks are derived from the King Perks timing catalogue,
          not from arbitrary UI slots.
        </p>
      </header>

      <section class="rounded-2xl border border-white/10 bg-slate-950/50 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase">Preparation window</p>
            <p v-if="plan" class="mt-1 text-lg font-semibold text-white">
              {{ displayUtc(plan.windowStartsAt) }} UTC → {{ displayUtc(plan.windowEndsAt) }} UTC
            </p>
            <p v-else class="mt-1 text-sm text-slate-300">
              Create the plan to snapshot the preparation phase for this occurrence.
            </p>
          </div>
          <button
            v-if="!plan"
            type="button"
            class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950"
            @click="createPlan"
          >
            Create King Perks plan
          </button>
          <button
            v-else-if="plan.status === 'draft'"
            type="button"
            class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950"
            @click="publishPlan"
          >
            Publish schedule
          </button>
          <span v-else class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-300">
            {{ plan.status }}
          </span>
        </div>
      </section>

      <template v-if="plan">
        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
          <div class="space-y-3 rounded-2xl border border-white/10 bg-slate-950/50 p-5">
            <div>
              <h2 class="text-lg font-semibold text-white">Appointment rotation</h2>
              <p class="text-sm text-slate-400">Now / next windows are persisted in UTC.</p>
            </div>

            <div v-if="plan.appointments.length === 0" class="rounded-xl border border-dashed border-white/10 p-6 text-sm text-slate-400">
              No appointments scheduled yet.
            </div>

            <article
              v-for="item in plan.appointments"
              :key="item.id"
              class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-900/60 p-4"
            >
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-semibold text-white">{{ item.typeLabel }} · {{ item.playerName }}</p>
                  <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs text-slate-300">{{ item.status }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-300">
                  {{ displayUtc(item.startsAt) }}–{{ displayUtc(item.endsAt) }} UTC
                </p>
                <p v-if="item.notes" class="mt-1 text-xs text-slate-400">{{ item.notes }}</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-slate-200" @click="markOutcome(item.id, 'completed')">
                  Complete
                </button>
                <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-slate-200" @click="markOutcome(item.id, 'no_show')">
                  No-show
                </button>
                <button type="button" class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs text-rose-200" @click="recordCancelledCooldown(item.id)">
                  Cancel + block position
                </button>
              </div>
            </article>

            <div v-if="plan.positionBlocks.length > 0" class="space-y-2 pt-2">
              <h3 class="text-sm font-semibold text-rose-200">Position cooldowns</h3>
              <div v-for="block in plan.positionBlocks" :key="block.id" class="rounded-lg border border-rose-400/20 bg-rose-950/20 px-3 py-2 text-xs text-rose-100">
                {{ block.type.replaceAll('_', ' ') }} · {{ displayUtc(block.startsAt) }}–{{ displayUtc(block.endsAt) }} UTC · {{ block.reason.replaceAll('_', ' ') }}
              </div>
            </div>
          </div>

          <form class="space-y-4 rounded-2xl border border-white/10 bg-slate-950/50 p-5" @submit.prevent="assignAppointment">
            <div>
              <h2 class="text-lg font-semibold text-white">Assign appointment</h2>
              <p class="text-xs text-slate-400">The end time is calculated automatically.</p>
            </div>
            <label class="block space-y-1 text-xs text-slate-300">
              <span>Position</span>
              <select v-model="appointment.appointment_type" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white">
                <option v-for="item in appointmentTypes" :key="item.key" :value="item.key">{{ item.label }}</option>
              </select>
            </label>
            <div v-if="selectedAppointment" class="rounded-lg bg-slate-900/80 p-3 text-xs text-slate-300">
              Occupies {{ selectedAppointment.durationMinutes }} min · Player cooldown {{ selectedAppointment.playerCooldownMinutes }} min · Cancelled-position block {{ selectedAppointment.cancelledPositionCooldownMinutes }} min
            </div>
            <label class="block space-y-1 text-xs text-slate-300">
              <span>Player</span>
              <select v-model="appointment.player_id" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white">
                <option v-for="player in players" :key="player.id" :value="player.id">{{ player.name }}</option>
              </select>
            </label>
            <label class="block space-y-1 text-xs text-slate-300">
              <span>Starts at (server UTC)</span>
              <input v-model="appointment.starts_at" required type="datetime-local" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white" />
            </label>
            <label class="block space-y-1 text-xs text-slate-300">
              <span>Notes</span>
              <textarea v-model="appointment.notes" rows="2" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white" />
            </label>
            <button type="submit" class="w-full rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950">Assign</button>
          </form>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
          <div class="space-y-3 rounded-2xl border border-white/10 bg-slate-950/50 p-5">
            <div>
              <h2 class="text-lg font-semibold text-white">King Skills</h2>
              <p class="text-sm text-slate-400">Track the in-game scheduling and active effect window separately from appointments.</p>
            </div>
            <article v-for="item in plan.skills" :key="item.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-900/60 p-4">
              <div>
                <p class="font-semibold text-white">{{ item.label }}</p>
                <p class="mt-1 text-sm text-slate-300">{{ displayUtc(item.plannedActivationAt) }}–{{ displayUtc(item.plannedEndsAt) }} UTC · {{ item.effectDurationMinutes }} min</p>
                <p class="text-xs text-slate-400">In-game scheduling opens {{ displayUtc(item.scheduleAvailableAt) }} UTC · {{ item.status }}</p>
              </div>
              <div class="flex gap-2">
                <button v-if="item.status === 'planned'" type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-slate-200" @click="markSkill(item.id, 'scheduled')">Scheduled in game</button>
                <button v-if="item.status !== 'activated'" type="button" class="rounded-lg border border-emerald-400/30 px-3 py-1.5 text-xs text-emerald-200" @click="markSkill(item.id, 'activated')">Activated</button>
              </div>
            </article>
          </div>

          <form class="space-y-4 rounded-2xl border border-white/10 bg-slate-950/50 p-5" @submit.prevent="planSkill">
            <div>
              <h2 class="text-lg font-semibold text-white">Plan King Skill</h2>
              <p class="text-xs text-slate-400">Enter the effect duration shown by the game; unverified durations are never guessed.</p>
            </div>
            <label class="block space-y-1 text-xs text-slate-300">
              <span>Skill</span>
              <select v-model="skill.skill_key" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white">
                <option v-for="item in skillTypes" :key="item.key" :value="item.key">{{ item.label }} · {{ item.recommendedFocus }}</option>
              </select>
            </label>
            <label class="block space-y-1 text-xs text-slate-300">
              <span>Activation (server UTC)</span>
              <input v-model="skill.planned_activation_at" required type="datetime-local" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white" />
            </label>
            <label class="block space-y-1 text-xs text-slate-300">
              <span>Effect duration (minutes)</span>
              <input v-model.number="skill.effect_duration_minutes" required min="1" max="10080" type="number" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white" />
            </label>
            <label class="block space-y-1 text-xs text-slate-300">
              <span>Notes</span>
              <textarea v-model="skill.notes" rows="2" class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white" />
            </label>
            <button type="submit" class="w-full rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950">Plan skill</button>
          </form>
        </section>
      </template>
    </div>
  </AppLayout>
</template>
