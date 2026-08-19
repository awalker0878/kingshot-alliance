<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import RoyalCourtAppointmentForm from '@/components/royal-court/RoyalCourtAppointmentForm.vue';
import RoyalCourtAppointments from '@/components/royal-court/RoyalCourtAppointments.vue';
import RoyalCourtLiveOperations from '@/components/royal-court/RoyalCourtLiveOperations.vue';
import RoyalCourtRequests from '@/components/royal-court/RoyalCourtRequests.vue';
import RoyalCourtSkillForm from '@/components/royal-court/RoyalCourtSkillForm.vue';
import RoyalCourtSkills from '@/components/royal-court/RoyalCourtSkills.vue';
import RoyalCourtSmartFillForm from '@/components/royal-court/RoyalCourtSmartFillForm.vue';
import RoyalCourtStrategy from '@/components/royal-court/RoyalCourtStrategy.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { displayLocal, displayUtc } from '@/lib/king-perks-display';
import type {
  Appointment,
  AppointmentType,
  GovernorOption,
  LiveCourt,
  Plan,
  PushCategory,
  SkillType,
  StrategyDay,
} from '@/types/king-perks';

const props = defineProps<{
  event: {
    id: string;
    title: string | null;
    typeSlug: string;
    kingdomId: string;
    kingdomName: string;
  };
  occurrence: { id: string; startsAt: string; endsAt: string };
  plan: Plan | null;
  live: LiveCourt | null;
  strategyDays: StrategyDay[];
  players: GovernorOption[];
  appointmentTypes: AppointmentType[];
  pushCategories: PushCategory[];
  skillTypes: SkillType[];
}>();

const editingAppointment = ref<Appointment | null>(null);
const selectedStrategy = ref<StrategyDay | null>(null);

function createPlan(): void {
  router.post(`/events/${props.event.id}/occurrences/${props.occurrence.id}/king-perks`);
}

function publishPlan(): void {
  if (!props.plan) return;
  router.post(`/king-perk-plans/${props.plan.id}/publish`);
}

function applyStrategy(day: StrategyDay): void {
  selectedStrategy.value = { ...day };
}

async function editAppointment(appointment: Appointment): Promise<void> {
  editingAppointment.value = appointment;
  await nextTick();
  document
    .getElementById('appointment-form')
    ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function activate(appointmentId: string): void {
  router.post(`/king-perk-appointments/${appointmentId}/active`, {}, { preserveScroll: true });
}

function markOutcome(appointmentId: string, status: 'completed' | 'no_show'): void {
  router.patch(
    `/king-perk-appointments/${appointmentId}/outcome`,
    { status },
    { preserveScroll: true },
  );
}

function recordCancelledCooldown(appointmentId: string): void {
  router.post(
    `/king-perk-appointments/${appointmentId}/cancelled-cooldown`,
    {},
    { preserveScroll: true },
  );
}

function replaceLive(appointmentId: string, playerId: string): void {
  router.post(
    `/king-perk-appointments/${appointmentId}/replace`,
    { player_id: playerId },
    { preserveScroll: true },
  );
}

function declineRequest(requestId: string): void {
  router.post(`/king-perk-requests/${requestId}/decline`, {}, { preserveScroll: true });
}

function markSkill(skillId: string, state: 'scheduled' | 'activated'): void {
  router.post(`/king-skill-plans/${skillId}/${state}`, {}, { preserveScroll: true });
}
</script>

<template>
  <Head :title="`King Perks · ${event.kingdomName}`" />

  <AppLayout>
    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <RoomBanner
        eyebrow="King’s Court · Kingdom of Power"
        :title="event.kingdomName"
        subtitle="Coordinate King Perk requests, appointments, cooldowns and Kingdom-wide skills inside this Event’s real preparation window."
        image="/images/kingshot/kings-court.svg"
      >
        <template #actions>
          <a
            :href="`/events/${event.id}/king-perks/my?occurrence=${occurrence.id}`"
            class="ks-command-link"
          >
            My Appointments
          </a>
        </template>
      </RoomBanner>

      <section class="rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.70)] p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-xs font-semibold tracking-wider text-[var(--ks-muted)] uppercase">
              Preparation window
            </p>
            <p v-if="plan" class="mt-1 text-lg font-semibold text-[var(--ks-ivory)]">
              {{ displayUtc(plan.windowStartsAt) }} → {{ displayUtc(plan.windowEndsAt) }}
            </p>
            <p v-if="plan" class="mt-1 text-sm text-[var(--ks-muted)]">
              {{ displayLocal(plan.windowStartsAt) }} → {{ displayLocal(plan.windowEndsAt) }}
            </p>
            <p v-else class="mt-1 text-sm text-[var(--ks-muted)]">
              Open the Court schedule for this Event’s preparation window.
            </p>
          </div>
          <button
            v-if="!plan"
            type="button"
            class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-[var(--ks-ink)]"
            @click="createPlan"
          >
            Open Court Schedule
          </button>
          <button
            v-else-if="plan.status === 'draft'"
            type="button"
            class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-[var(--ks-ink)]"
            @click="publishPlan"
          >
            Proclaim Schedule
          </button>
          <span
            v-else
            class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-300"
          >
            {{ plan.status }}
          </span>
        </div>
      </section>

      <template v-if="plan">
        <RoyalCourtStrategy :days="strategyDays" @apply="applyStrategy" />

        <RoyalCourtLiveOperations
          v-if="live"
          :live="live"
          :players="players"
          @replace="replaceLive"
        />

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
          <RoyalCourtRequests :requests="plan.requests" @decline="declineRequest" />
          <RoyalCourtSmartFillForm
            :plan-id="plan.id"
            :categories="pushCategories"
            :strategy="selectedStrategy"
          />
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
          <RoyalCourtAppointments
            :appointments="plan.appointments"
            :position-blocks="plan.positionBlocks"
            @edit="editAppointment"
            @activate="activate"
            @outcome="markOutcome"
            @cancelled-cooldown="recordCancelledCooldown"
          />
          <RoyalCourtAppointmentForm
            :plan-id="plan.id"
            :appointment-types="appointmentTypes"
            :players="players"
            :editing="editingAppointment"
            @cleared="editingAppointment = null"
          />
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
          <RoyalCourtSkills :skills="plan.skills" @mark="markSkill" />
          <RoyalCourtSkillForm
            :plan-id="plan.id"
            :skill-types="skillTypes"
            :strategy="selectedStrategy"
          />
        </section>
      </template>
    </main>
  </AppLayout>
</template>
