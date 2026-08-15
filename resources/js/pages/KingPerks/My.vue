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

type Appointment = {
  id: string;
  type: string;
  typeLabel: string;
  startsAt: string;
  endsAt: string;
  durationMinutes: number;
  status: string;
  confirmedAt: string | null;
  actualStartedAt: string | null;
  actualEndedAt: string | null;
  notes: string | null;
};

type PerkRequest = {
  id: string;
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

type Plan = {
  id: string;
  status: string;
  windowStartsAt: string;
  windowEndsAt: string;
  appointments: Appointment[];
  requests: PerkRequest[];
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
  player: { id: string; name: string };
  plan: Plan | null;
  appointmentTypes: AppointmentType[];
  pushCategories: PushCategory[];
}>();

const form = reactive({
  push_category: props.pushCategories[0]?.key ?? '',
  preferred_appointment_type: '',
  availability_starts_at: '',
  availability_ends_at: '',
  planned_speedup_hours: null as number | null,
  planned_resource_amount: null as number | null,
  notes: '',
});

const compatibleAppointments = computed(() => {
  const category = props.pushCategories.find((item) => item.key === form.push_category);
  if (!category) return [];

  return props.appointmentTypes.filter((item) =>
    category.preferredAppointmentTypes.includes(item.key),
  );
});

function displayUtc(value: string): string {
  return `${new Intl.DateTimeFormat('en-CA', {
    timeZone: 'UTC',
    year: 'numeric',
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
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))} ${zone}`;
}

function durationLabel(minutes: number | null): string {
  if (minutes === null) return 'Not provided';
  const hours = minutes / 60;
  return Number.isInteger(hours) ? `${hours}h` : `${hours.toFixed(1)}h`;
}

function confirm(id: string): void {
  router.post(`/king-perk-appointments/${id}/confirm`, {}, { preserveScroll: true });
}

function decline(id: string): void {
  router.post(`/king-perk-appointments/${id}/decline`, {}, { preserveScroll: true });
}

function submitRequest(): void {
  if (!props.plan) return;

  router.post(
    `/king-perk-plans/${props.plan.id}/requests`,
    {
      push_category: form.push_category,
      preferred_appointment_type: form.preferred_appointment_type || null,
      availability_starts_at: form.availability_starts_at,
      availability_ends_at: form.availability_ends_at,
      planned_speedup_minutes:
        form.planned_speedup_hours === null ? null : Math.round(form.planned_speedup_hours * 60),
      planned_resource_amount: form.planned_resource_amount,
      notes: form.notes || null,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        form.preferred_appointment_type = '';
        form.availability_starts_at = '';
        form.availability_ends_at = '';
        form.planned_speedup_hours = null;
        form.planned_resource_amount = null;
        form.notes = '';
      },
    },
  );
}

function withdraw(id: string): void {
  router.delete(`/king-perk-requests/${id}`, { preserveScroll: true });
}
</script>

<template>
  <Head :title="`My King Perks · ${event.kingdomName}`" />

  <AppLayout :user="user" :player-alliance-name="null" :has-player-alliance="false">
    <main class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <header class="space-y-2">
        <p class="text-xs font-semibold tracking-[0.2em] text-amber-300 uppercase">
          Kingdom of Power · My King Perks
        </p>
        <h1 class="text-3xl font-semibold text-white">{{ player.name }}</h1>
        <p class="max-w-3xl text-sm text-slate-300">
          Confirm your assigned appointment or tell Kingdom leadership when you can use a
          preparation bonus. Times are shown in server UTC and your browser's local timezone.
        </p>
      </header>

      <section
        v-if="!plan"
        class="rounded-2xl border border-white/10 bg-slate-950/50 p-6 text-sm text-slate-300"
      >
        Kingdom leadership has not published a King Perks plan for this preparation phase yet.
      </section>

      <template v-else>
        <section class="rounded-2xl border border-white/10 bg-slate-950/50 p-5">
          <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase">
            Preparation window
          </p>
          <p class="mt-2 text-lg font-semibold text-white">
            {{ displayUtc(plan.windowStartsAt) }} → {{ displayUtc(plan.windowEndsAt) }}
          </p>
          <p class="mt-1 text-sm text-slate-400">
            {{ displayLocal(plan.windowStartsAt) }} → {{ displayLocal(plan.windowEndsAt) }}
          </p>
        </section>

        <section class="space-y-3 rounded-2xl border border-white/10 bg-slate-950/50 p-5">
          <div>
            <h2 class="text-lg font-semibold text-white">My appointments</h2>
            <p class="text-sm text-slate-400">
              Your appointment must cover the complete game bonus window.
            </p>
          </div>

          <p
            v-if="plan.appointments.length === 0"
            class="rounded-xl border border-dashed border-white/10 p-5 text-sm text-slate-400"
          >
            You do not have an appointment yet.
          </p>

          <article
            v-for="item in plan.appointments"
            :key="item.id"
            class="rounded-xl border border-white/10 bg-slate-900/60 p-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="font-semibold text-white">{{ item.typeLabel }}</h3>
                  <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs text-slate-300">{{
                    item.status.replaceAll('_', ' ')
                  }}</span>
                </div>
                <p class="mt-2 text-sm text-white">
                  {{ displayUtc(item.startsAt) }} → {{ displayUtc(item.endsAt) }}
                </p>
                <p class="text-xs text-slate-400">
                  {{ displayLocal(item.startsAt) }} → {{ displayLocal(item.endsAt) }}
                </p>
                <p class="mt-2 text-xs text-slate-400">
                  Occupancy: {{ item.durationMinutes }} minutes
                </p>
                <p v-if="item.notes" class="mt-2 text-sm text-slate-300">{{ item.notes }}</p>
              </div>
              <div
                v-if="item.status === 'scheduled' || item.status === 'confirmed'"
                class="flex flex-wrap gap-2"
              >
                <button
                  v-if="item.status === 'scheduled'"
                  type="button"
                  class="rounded-lg bg-emerald-400 px-3 py-2 text-xs font-semibold text-slate-950"
                  @click="confirm(item.id)"
                >
                  Confirm
                </button>
                <button
                  type="button"
                  class="rounded-lg border border-rose-400/30 px-3 py-2 text-xs font-semibold text-rose-200"
                  @click="decline(item.id)"
                >
                  Can't attend
                </button>
              </div>
            </div>
          </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
          <div class="space-y-3 rounded-2xl border border-white/10 bg-slate-950/50 p-5">
            <div>
              <h2 class="text-lg font-semibold text-white">My requests</h2>
              <p class="text-sm text-slate-400">
                Requests remain category-specific; training usage is not compared to construction or
                research usage.
              </p>
            </div>
            <p
              v-if="plan.requests.length === 0"
              class="rounded-xl border border-dashed border-white/10 p-5 text-sm text-slate-400"
            >
              No appointment requests submitted.
            </p>
            <article
              v-for="item in plan.requests"
              :key="item.id"
              class="rounded-xl border border-white/10 bg-slate-900/60 p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="font-semibold text-white">{{ item.categoryLabel }}</p>
                  <p class="mt-1 text-sm text-slate-300">
                    {{ displayUtc(item.availabilityStartsAt) }} →
                    {{ displayUtc(item.availabilityEndsAt) }}
                  </p>
                  <p class="text-xs text-slate-400">
                    {{ displayLocal(item.availabilityStartsAt) }} →
                    {{ displayLocal(item.availabilityEndsAt) }}
                  </p>
                  <p class="mt-2 text-xs text-slate-400">
                    Planned speedups: {{ durationLabel(item.plannedSpeedupMinutes) }} · Status:
                    {{ item.status }}
                  </p>
                </div>
                <button
                  v-if="item.status === 'submitted'"
                  type="button"
                  class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-slate-200"
                  @click="withdraw(item.id)"
                >
                  Withdraw
                </button>
              </div>
            </article>
          </div>

          <form
            class="space-y-4 rounded-2xl border border-white/10 bg-slate-950/50 p-5"
            @submit.prevent="submitRequest"
          >
            <div>
              <h2 class="text-lg font-semibold text-white">Request an appointment</h2>
              <p class="text-xs text-slate-400">
                Availability must contain the full appointment window.
              </p>
            </div>

            <label class="block space-y-1 text-xs text-slate-300">
              <span>Preparation focus</span>
              <select
                v-model="form.push_category"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
                @change="form.preferred_appointment_type = ''"
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

            <label class="block space-y-1 text-xs text-slate-300">
              <span>Preferred appointment (optional)</span>
              <select
                v-model="form.preferred_appointment_type"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
              >
                <option value="">Any applicable appointment</option>
                <option v-for="type in compatibleAppointments" :key="type.key" :value="type.key">
                  {{ type.label }}
                </option>
              </select>
            </label>

            <label class="block space-y-1 text-xs text-slate-300">
              <span>Available from (UTC)</span>
              <input
                v-model="form.availability_starts_at"
                required
                type="datetime-local"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
              />
            </label>
            <label class="block space-y-1 text-xs text-slate-300">
              <span>Available until (UTC)</span>
              <input
                v-model="form.availability_ends_at"
                required
                type="datetime-local"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
              />
            </label>
            <label class="block space-y-1 text-xs text-slate-300">
              <span>Planned speedups (hours, optional)</span>
              <input
                v-model.number="form.planned_speedup_hours"
                min="0"
                step="0.5"
                type="number"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
              />
            </label>
            <label class="block space-y-1 text-xs text-slate-300">
              <span>Planned resource amount (optional)</span>
              <input
                v-model.number="form.planned_resource_amount"
                min="0"
                type="number"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
              />
            </label>
            <label class="block space-y-1 text-xs text-slate-300">
              <span>Notes</span>
              <textarea
                v-model="form.notes"
                rows="3"
                class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white"
              />
            </label>
            <button
              type="submit"
              class="w-full rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950"
            >
              Submit request
            </button>
          </form>
        </section>
      </template>
    </main>
  </AppLayout>
</template>
