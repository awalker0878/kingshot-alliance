<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';

type AppointmentType = {
  key: string;
  label: string;
  durationMinutes: number;
  playerCooldownMinutes: number;
  playerCooldownAnchor: string;
  cancelledPositionCooldownMinutes: number;
  recommendedFocus: string;
};

type PushCategory = {
  key: string;
  label: string;
  preferredAppointmentTypes: string[];
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
  playerName: string | null;
  playerEligible: boolean;
  startsAt: string;
  endsAt: string;
  durationMinutes: number;
  playerCooldownMinutes: number;
  playerCooldownAnchor: string;
  status: string;
  confirmedAt: string | null;
  actualStartedAt: string | null;
  actualEndedAt: string | null;
  notes: string | null;
};

type PerkRequest = {
  id: string;
  playerId: string;
  playerName: string | null;
  category: string;
  categoryLabel: string;
  preferredAppointmentType: string | null;
  availabilityStartsAt: string;
  availabilityEndsAt: string;
  plannedSpeedupMinutes: number | null;
  plannedResourceAmount: number | null;
  status: string;
  scheduledAppointmentId: string | null;
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
  requests: PerkRequest[];
};

type LiveLane = {
  type: string;
  label: string;
  now: Appointment | null;
  next: Appointment | null;
  following: Appointment | null;
};

type StrategyDay = {
  day: number;
  startsAt: string;
  endsAt: string;
  focus: string | null;
  skill: string | null;
  appointmentTypes: string[];
  strategyNote: string;
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
  live: { generatedAt: string; lanes: LiveLane[] } | null;
  strategyDays: StrategyDay[];
  players: Array<{ id: string; name: string }>;
  appointmentTypes: AppointmentType[];
  pushCategories: PushCategory[];
  skillTypes: SkillType[];
}>();

const appointment = reactive({
  appointment_id: '',
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

const auto = reactive({
  push_category: props.pushCategories[0]?.key ?? '',
  from: '',
  until: '',
  limit: 200,
});

const replacementPlayer = reactive<Record<string, string>>({});

const selectedAppointment = computed(() =>
  props.appointmentTypes.find((item) => item.key === appointment.appointment_type),
);

const submittedRequests = computed(
  () => props.plan?.requests.filter((item) => item.status === 'submitted') ?? [],
);

function displayUtc(value: string): string {
  return `${new Intl.DateTimeFormat('en-CA', {
    timeZone: 'UTC',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(new Date(value))} UTC`;
}

function displayLocal(value: string): string {
  const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
  return `${new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))} ${zone}`;
}

function utcInput(value: string): string {
  return new Date(value).toISOString().slice(0, 16);
}

function humanize(value: string | null): string {
  return value ? value.replaceAll('_', ' ') : '—';
}

function speedups(minutes: number | null): string {
  if (minutes === null) return 'not declared';
  const hours = minutes / 60;
  return Number.isInteger(hours) ? `${hours}h` : `${hours.toFixed(1)}h`;
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
  router.post(
    `/king-perk-plans/${props.plan.id}/appointments`,
    {
      appointment_id: appointment.appointment_id || null,
      appointment_type: appointment.appointment_type,
      player_id: appointment.player_id,
      starts_at: appointment.starts_at,
      notes: appointment.notes || null,
    },
    {
      preserveScroll: true,
      onSuccess: clearAppointmentForm,
    },
  );
}

function clearAppointmentForm(): void {
  appointment.appointment_id = '';
  appointment.appointment_type = props.appointmentTypes[0]?.key ?? '';
  appointment.player_id = props.players[0]?.id ?? '';
  appointment.starts_at = '';
  appointment.notes = '';
}

function editAppointment(item: Appointment): void {
  appointment.appointment_id = item.id;
  appointment.appointment_type = item.type;
  appointment.player_id = item.playerId;
  appointment.starts_at = utcInput(item.startsAt);
  appointment.notes = item.notes ?? '';
  document
    .getElementById('appointment-form')
    ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function activate(id: string): void {
  router.post(`/king-perk-appointments/${id}/active`, {}, { preserveScroll: true });
}

function markOutcome(id: string, status: 'completed' | 'no_show'): void {
  router.patch(`/king-perk-appointments/${id}/outcome`, { status }, { preserveScroll: true });
}

function recordCancelledCooldown(id: string): void {
  router.post(`/king-perk-appointments/${id}/cancelled-cooldown`, {}, { preserveScroll: true });
}

function replaceLive(item: Appointment): void {
  const playerId = replacementPlayer[item.id];
  if (!playerId) return;
  router.post(
    `/king-perk-appointments/${item.id}/replace`,
    { player_id: playerId },
    { preserveScroll: true, onSuccess: () => delete replacementPlayer[item.id] },
  );
}

function declineRequest(id: string): void {
  router.post(`/king-perk-requests/${id}/decline`, {}, { preserveScroll: true });
}

function autoSchedule(): void {
  if (!props.plan) return;
  router.post(
    `/king-perk-plans/${props.plan.id}/auto-schedule`,
    { ...auto },
    { preserveScroll: true },
  );
}

function applyStrategy(day: StrategyDay): void {
  if (day.focus && props.pushCategories.some((item) => item.key === day.focus)) {
    auto.push_category = day.focus;
    auto.from = utcInput(day.startsAt);
    auto.until = utcInput(day.endsAt);
  }
  if (day.skill) {
    skill.skill_key = day.skill;
    skill.planned_activation_at = utcInput(day.startsAt);
  }
}

function planSkill(): void {
  if (!props.plan) return;
  router.post(
    `/king-perk-plans/${props.plan.id}/skills`,
    { ...skill },
    {
      preserveScroll: true,
      onSuccess: () => {
        skill.planned_activation_at = '';
        skill.notes = '';
      },
    },
  );
}

function markSkill(id: string, state: 'scheduled' | 'activated'): void {
  router.post(`/king-skill-plans/${id}/${state}`, {}, { preserveScroll: true });
}
</script>

<template>
  <Head :title="`King Perks · ${event.kingdomName}`" />

  <AppLayout :user="user" :player-alliance-name="null" :has-player-alliance="false">
    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <header class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-xs font-semibold tracking-[0.2em] text-amber-300 uppercase">
              Kingdom of Power · King Perks
            </p>
            <h1 class="mt-2 text-3xl font-semibold text-white">{{ event.kingdomName }}</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-300">
              Plan personal King appointments and Kingdom-wide King Skills against the authoritative
              preparation window. Appointment occupancy and cooldown rules are enforced by the
              backend catalogue, not by arbitrary calendar slots.
            </p>
          </div>
          <a
            :href="`/events/${event.id}/king-perks/my?occurrence=${occurrence.id}`"
            class="rounded-lg border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/5"
          >
            My appointments
          </a>
        </div>
      </header>

      <section class="rounded-2xl border border-white/10 bg-slate-950/50 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase">
              Preparation window
            </p>
            <p v-if="plan" class="mt-1 text-lg font-semibold text-white">
              {{ displayUtc(plan.windowStartsAt) }} → {{ displayUtc(plan.windowEndsAt) }}
            </p>
            <p v-if="plan" class="mt-1 text-sm text-slate-400">
              {{ displayLocal(plan.windowStartsAt) }} → {{ displayLocal(plan.windowEndsAt) }}
            </p>
            <p v-else class="mt-1 text-sm text-slate-300">
              Create the plan to snapshot this occurrence's preparation window.
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
          <span
            v-else
            class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-300"
            >{{ plan.status }}</span
          >
        </div>
      </section>

      <template v-if="plan">
        <section class="space-y-4 rounded-2xl border border-white/10 bg-slate-950/50 p-5">
          <div>
            <h2 class="text-lg font-semibold text-white">Preparation strategy</h2>
            <p class="text-sm text-slate-400">
              Presets are planning recommendations, not hard game rules. Leadership remains in
              control.
            </p>
          </div>
          <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="day in strategyDays"
              :key="day.day"
              class="rounded-xl border border-white/10 bg-slate-900/60 p-4"
            >
              <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-semibold tracking-wider text-amber-300 uppercase">
                  Preparation day {{ day.day }}
                </p>
                <button
                  type="button"
                  class="text-xs font-semibold text-sky-300 hover:text-sky-200"
                  @click="applyStrategy(day)"
                >
                  Use in planner
                </button>
              </div>
              <p class="mt-2 font-semibold text-white">{{ humanize(day.focus) }}</p>
              <p class="mt-1 text-xs text-slate-400">
                {{ displayUtc(day.startsAt) }} → {{ displayUtc(day.endsAt) }}
              </p>
              <p v-if="day.skill" class="mt-2 text-sm text-slate-300">
                King Skill: {{ humanize(day.skill) }}
              </p>
              <p v-if="day.appointmentTypes.length" class="text-sm text-slate-300">
                Appointments: {{ day.appointmentTypes.map(humanize).join(' → ') }}
              </p>
              <p class="mt-2 text-xs leading-5 text-slate-400">{{ day.strategyNote }}</p>
            </article>
          </div>
        </section>

        <section
          v-if="live"
          class="space-y-4 rounded-2xl border border-white/10 bg-slate-950/50 p-5"
        >
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-white">Live operations</h2>
              <p class="text-sm text-slate-400">
                Now / next / following for each appointment position.
              </p>
            </div>
            <p class="text-xs text-slate-500">Snapshot {{ displayUtc(live.generatedAt) }}</p>
          </div>
          <div class="grid gap-3 xl:grid-cols-2">
            <article
              v-for="lane in live.lanes"
              :key="lane.type"
              class="rounded-xl border border-white/10 bg-slate-900/60 p-4"
            >
              <h3 class="font-semibold text-white">{{ lane.label }}</h3>
              <div class="mt-3 grid gap-2 sm:grid-cols-3">
                <div
                  v-for="entry in [
                    { key: 'NOW', item: lane.now },
                    { key: 'NEXT', item: lane.next },
                    { key: 'FOLLOWING', item: lane.following },
                  ]"
                  :key="entry.key"
                  class="rounded-lg bg-slate-950/70 p-3"
                >
                  <p class="text-[11px] font-semibold tracking-wider text-slate-500">
                    {{ entry.key }}
                  </p>
                  <template v-if="entry.item">
                    <p class="mt-1 text-sm font-semibold text-white">
                      {{ entry.item.playerName ?? 'Unknown Player' }}
                    </p>
                    <p class="text-xs text-slate-400">{{ displayUtc(entry.item.startsAt) }}</p>
                    <p
                      class="text-xs"
                      :class="entry.item.playerEligible ? 'text-emerald-300' : 'text-rose-300'"
                    >
                      {{ entry.item.playerEligible ? entry.item.status : 'Player left Kingdom' }}
                    </p>
                  </template>
                  <p v-else class="mt-1 text-xs text-slate-500">Open</p>
                </div>
              </div>
              <div
                v-if="lane.now && lane.now.status !== 'completed'"
                class="mt-3 flex flex-wrap items-end gap-2"
              >
                <label class="min-w-48 flex-1 space-y-1 text-xs text-slate-300">
                  <span>Rapid replacement</span>
                  <select
                    v-model="replacementPlayer[lane.now.id]"
                    class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white"
                  >
                    <option value="">Select Player</option>
                    <option v-for="player in players" :key="player.id" :value="player.id">
                      {{ player.name }}
                    </option>
                  </select>
                </label>
                <button
                  type="button"
                  class="rounded-lg border border-rose-400/30 px-3 py-2 text-xs font-semibold text-rose-200"
                  @click="replaceLive(lane.now)"
                >
                  No-show + replace
                </button>
              </div>
            </article>
          </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
          <div class="space-y-3 rounded-2xl border border-white/10 bg-slate-950/50 p-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
              <div>
                <h2 class="text-lg font-semibold text-white">Player requests</h2>
                <p class="text-sm text-slate-400">Ranked only within each activity category.</p>
              </div>
              <span class="text-xs text-slate-500"
                >{{ submittedRequests.length }} awaiting scheduling</span
              >
            </div>
            <p
              v-if="plan.requests.length === 0"
              class="rounded-xl border border-dashed border-white/10 p-5 text-sm text-slate-400"
            >
              No requests submitted yet.
            </p>
            <article
              v-for="item in plan.requests"
              :key="item.id"
              class="rounded-xl border border-white/10 bg-slate-900/60 p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="font-semibold text-white">{{ item.playerName ?? item.playerId }}</p>
                    <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs text-slate-300">{{
                      item.categoryLabel
                    }}</span>
                    <span class="text-xs text-slate-500">{{ item.status }}</span>
                  </div>
                  <p class="mt-1 text-sm text-slate-300">
                    {{ displayUtc(item.availabilityStartsAt) }} →
                    {{ displayUtc(item.availabilityEndsAt) }}
                  </p>
                  <p class="text-xs text-slate-500">
                    {{ displayLocal(item.availabilityStartsAt) }} →
                    {{ displayLocal(item.availabilityEndsAt) }}
                  </p>
                  <p class="mt-2 text-xs text-slate-400">
                    Speedups {{ speedups(item.plannedSpeedupMinutes) }} · preferred
                    {{ humanize(item.preferredAppointmentType) }}
                  </p>
                  <p v-if="item.notes" class="mt-1 text-xs text-slate-400">{{ item.notes }}</p>
                </div>
                <button
                  v-if="item.status === 'submitted'"
                  type="button"
                  class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs text-rose-200"
                  @click="declineRequest(item.id)"
                >
                  Decline
                </button>
              </div>
            </article>
          </div>

          <form
            class="space-y-4 rounded-2xl border border-white/10 bg-slate-950/50 p-5"
            @submit.prevent="autoSchedule"
          >
            <div>
              <h2 class="text-lg font-semibold text-white">Smart fill</h2>
              <p class="text-xs text-slate-400">
                Fills legal duration-aware windows from submitted availability. Training uses Noble
                Advisor first, then Chief Minister overflow.
              </p>
            </div>
            <label class="block space-y-1 text-xs text-slate-300">
              <span>Focus</span>
              <select
                v-model="auto.push_category"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
              >
                <option
                  v-for="category in pushCategories"
                  :key="category.key"
                  :value="category.key"
                >
                  {{ category.label }}
                </option>
              </select>
            </label>
            <label class="block space-y-1 text-xs text-slate-300"
              ><span>From (UTC)</span
              ><input
                v-model="auto.from"
                required
                type="datetime-local"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
            /></label>
            <label class="block space-y-1 text-xs text-slate-300"
              ><span>Until (UTC)</span
              ><input
                v-model="auto.until"
                required
                type="datetime-local"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
            /></label>
            <label class="block space-y-1 text-xs text-slate-300"
              ><span>Maximum assignments</span
              ><input
                v-model.number="auto.limit"
                min="1"
                max="500"
                type="number"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
            /></label>
            <button
              type="submit"
              class="w-full rounded-lg bg-sky-400 px-4 py-2 text-sm font-semibold text-slate-950"
            >
              Auto-fill window
            </button>
          </form>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
          <div class="space-y-3 rounded-2xl border border-white/10 bg-slate-950/50 p-5">
            <div>
              <h2 class="text-lg font-semibold text-white">Appointment rotation</h2>
              <p class="text-sm text-slate-400">
                The stored end time is derived from the selected appointment's occupancy duration.
              </p>
            </div>
            <p
              v-if="plan.appointments.length === 0"
              class="rounded-xl border border-dashed border-white/10 p-6 text-sm text-slate-400"
            >
              No appointments scheduled yet.
            </p>
            <article
              v-for="item in plan.appointments"
              :key="item.id"
              class="rounded-xl border border-white/10 bg-slate-900/60 p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="font-semibold text-white">
                      {{ item.typeLabel }} · {{ item.playerName ?? item.playerId }}
                    </p>
                    <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs text-slate-300">{{
                      humanize(item.status)
                    }}</span>
                    <span
                      v-if="!item.playerEligible"
                      class="rounded-full bg-rose-400/10 px-2 py-0.5 text-xs text-rose-300"
                      >reassignment required</span
                    >
                  </div>
                  <p class="mt-1 text-sm text-slate-300">
                    {{ displayUtc(item.startsAt) }} → {{ displayUtc(item.endsAt) }}
                  </p>
                  <p class="text-xs text-slate-500">
                    {{ displayLocal(item.startsAt) }} → {{ displayLocal(item.endsAt) }}
                  </p>
                  <p class="mt-1 text-xs text-slate-400">
                    {{ item.durationMinutes }} min occupancy · {{ item.playerCooldownMinutes }} min
                    Player cooldown after appointment end
                  </p>
                  <p
                    v-if="item.actualStartedAt || item.actualEndedAt"
                    class="mt-1 text-xs text-emerald-300"
                  >
                    Actual:
                    {{ item.actualStartedAt ? displayUtc(item.actualStartedAt) : 'not started' }} →
                    {{ item.actualEndedAt ? displayUtc(item.actualEndedAt) : 'in progress' }}
                  </p>
                  <p v-if="item.notes" class="mt-1 text-xs text-slate-400">{{ item.notes }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-if="item.status === 'scheduled' || item.status === 'confirmed'"
                    type="button"
                    class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-slate-200"
                    @click="editAppointment(item)"
                  >
                    Edit
                  </button>
                  <button
                    v-if="item.status === 'scheduled' || item.status === 'confirmed'"
                    type="button"
                    class="rounded-lg border border-emerald-400/30 px-3 py-1.5 text-xs text-emerald-200"
                    @click="activate(item.id)"
                  >
                    Active
                  </button>
                  <button
                    v-if="item.status !== 'completed' && item.status !== 'cancelled'"
                    type="button"
                    class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-slate-200"
                    @click="markOutcome(item.id, 'completed')"
                  >
                    Complete
                  </button>
                  <button
                    v-if="item.status !== 'completed' && item.status !== 'cancelled'"
                    type="button"
                    class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-slate-200"
                    @click="markOutcome(item.id, 'no_show')"
                  >
                    No-show
                  </button>
                  <button
                    v-if="item.status !== 'completed' && item.status !== 'cancelled'"
                    type="button"
                    class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs text-rose-200"
                    @click="recordCancelledCooldown(item.id)"
                  >
                    Cancel + position cooldown
                  </button>
                </div>
              </div>
            </article>

            <div v-if="plan.positionBlocks.length" class="space-y-2 pt-2">
              <h3 class="text-sm font-semibold text-rose-200">Position cooldowns</h3>
              <div
                v-for="block in plan.positionBlocks"
                :key="block.id"
                class="rounded-lg border border-rose-400/20 bg-rose-950/20 px-3 py-2 text-xs text-rose-100"
              >
                {{ humanize(block.type) }} · {{ displayUtc(block.startsAt) }} →
                {{ displayUtc(block.endsAt) }} · {{ humanize(block.reason) }}
              </div>
            </div>
          </div>

          <form
            id="appointment-form"
            class="space-y-4 rounded-2xl border border-white/10 bg-slate-950/50 p-5"
            @submit.prevent="assignAppointment"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <h2 class="text-lg font-semibold text-white">
                  {{ appointment.appointment_id ? 'Reassign appointment' : 'Assign appointment' }}
                </h2>
                <p class="text-xs text-slate-400">End time is calculated automatically.</p>
              </div>
              <button
                v-if="appointment.appointment_id"
                type="button"
                class="text-xs text-slate-400"
                @click="clearAppointmentForm"
              >
                Clear
              </button>
            </div>
            <label class="block space-y-1 text-xs text-slate-300"
              ><span>Position</span
              ><select
                v-model="appointment.appointment_type"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
              >
                <option v-for="item in appointmentTypes" :key="item.key" :value="item.key">
                  {{ item.label }}
                </option>
              </select></label
            >
            <div
              v-if="selectedAppointment"
              class="rounded-lg bg-slate-900/80 p-3 text-xs text-slate-300"
            >
              Occupies {{ selectedAppointment.durationMinutes }} min · Player cooldown
              {{ selectedAppointment.playerCooldownMinutes }} min · cancelled-position block
              {{ selectedAppointment.cancelledPositionCooldownMinutes }} min
            </div>
            <label class="block space-y-1 text-xs text-slate-300"
              ><span>Player</span
              ><select
                v-model="appointment.player_id"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
              >
                <option v-for="player in players" :key="player.id" :value="player.id">
                  {{ player.name }}
                </option>
              </select></label
            >
            <label class="block space-y-1 text-xs text-slate-300"
              ><span>Starts at (UTC)</span
              ><input
                v-model="appointment.starts_at"
                required
                type="datetime-local"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
            /></label>
            <label class="block space-y-1 text-xs text-slate-300"
              ><span>Notes</span
              ><textarea
                v-model="appointment.notes"
                rows="2"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
              />
            </label>
            <button
              type="submit"
              class="w-full rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950"
            >
              {{ appointment.appointment_id ? 'Save reassignment' : 'Assign' }}
            </button>
          </form>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
          <div class="space-y-3 rounded-2xl border border-white/10 bg-slate-950/50 p-5">
            <div>
              <h2 class="text-lg font-semibold text-white">King Skills</h2>
              <p class="text-sm text-slate-400">
                Skill effects are tracked separately from personal appointment occupancy.
              </p>
            </div>
            <p
              v-if="plan.skills.length === 0"
              class="rounded-xl border border-dashed border-white/10 p-5 text-sm text-slate-400"
            >
              No King Skills planned yet.
            </p>
            <article
              v-for="item in plan.skills"
              :key="item.id"
              class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-900/60 p-4"
            >
              <div>
                <p class="font-semibold text-white">{{ item.label }}</p>
                <p class="mt-1 text-sm text-slate-300">
                  {{ displayUtc(item.plannedActivationAt) }} →
                  {{ displayUtc(item.plannedEndsAt) }} · {{ item.effectDurationMinutes }} min
                </p>
                <p class="text-xs text-slate-400">
                  Scheduling window opens {{ displayUtc(item.scheduleAvailableAt) }} ·
                  {{ humanize(item.status) }}
                </p>
              </div>
              <div class="flex gap-2">
                <button
                  v-if="item.status === 'planned'"
                  type="button"
                  class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-slate-200"
                  @click="markSkill(item.id, 'scheduled')"
                >
                  Scheduled in game
                </button>
                <button
                  v-if="item.status === 'planned' || item.status === 'scheduled_in_game'"
                  type="button"
                  class="rounded-lg border border-emerald-400/30 px-3 py-1.5 text-xs text-emerald-200"
                  @click="markSkill(item.id, 'activated')"
                >
                  Activated
                </button>
              </div>
            </article>
          </div>

          <form
            class="space-y-4 rounded-2xl border border-white/10 bg-slate-950/50 p-5"
            @submit.prevent="planSkill"
          >
            <div>
              <h2 class="text-lg font-semibold text-white">Plan King Skill</h2>
              <p class="text-xs text-slate-400">
                Enter the effect duration verified in the game; the application does not guess it.
              </p>
            </div>
            <label class="block space-y-1 text-xs text-slate-300"
              ><span>Skill</span
              ><select
                v-model="skill.skill_key"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
              >
                <option v-for="item in skillTypes" :key="item.key" :value="item.key">
                  {{ item.label }} · {{ item.recommendedFocus }}
                </option>
              </select></label
            >
            <label class="block space-y-1 text-xs text-slate-300"
              ><span>Activation (UTC)</span
              ><input
                v-model="skill.planned_activation_at"
                required
                type="datetime-local"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
            /></label>
            <label class="block space-y-1 text-xs text-slate-300"
              ><span>Verified effect duration (minutes)</span
              ><input
                v-model.number="skill.effect_duration_minutes"
                required
                min="1"
                max="10080"
                type="number"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
            /></label>
            <label class="block space-y-1 text-xs text-slate-300"
              ><span>Notes</span
              ><textarea
                v-model="skill.notes"
                rows="2"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
              />
            </label>
            <button
              type="submit"
              class="w-full rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950"
            >
              Plan skill
            </button>
          </form>
        </section>
      </template>
    </main>
  </AppLayout>
</template>
