<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{
  alliance: { id: string; name: string; timezone: string };
  userTimezone: string;
  canManage: boolean;
  event: {
    id: string;
    eventId: string;
    title: string;
    startsAt: string;
    endsAt: string;
    allianceTimezone: string;
    capacity: number | null;
    status: string;
    registrationOpensAt: string | null;
    registrationClosesAt: string | null;
    registration: { status: string; waitlistPosition: number | null } | null;
    instructions: string | null;
    registeredCount: number;
    waitlistedCount: number;
  };
  recommendedFormations: Array<{
    id: string;
    name: string;
    assignmentRole: string;
    heroes: string[];
    infantryPercent: number;
    cavalryPercent: number;
    archerPercent: number;
    notes: string | null;
    guidance: {
      name: string;
      source: string | null;
      rationale: string | null;
      effectiveFrom: string | null;
      effectiveUntil: string | null;
    } | null;
  }>;
  rallyGroups: Array<{
    id: string;
    name: string;
    maxJoiners: number | null;
    notes: string | null;
    assignments: Array<{
      id: string;
      membershipId: string;
      memberName: string;
      role: string;
      slotNumber: number | null;
      status: string;
      participationRecordedAt: string | null;
    }>;
  }>;
  savedFormations: Array<{
    id: string;
    name: string;
    heroes: string[];
    infantryPercent: number;
    cavalryPercent: number;
    archerPercent: number;
    notes: string | null;
    isDefault: boolean;
  }>;
}>();

const formationForm = useForm({
  name: '',
  heroes_text: '',
  infantry_percent: 10,
  cavalry_percent: 10,
  archer_percent: 80,
  notes: '',
  is_default: false,
});

function formatInZone(value: string, timeZone: string): string {
  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    timeZone,
    timeZoneName: 'short',
  }).format(new Date(value));
}

function canJoin(): boolean {
  if (props.event.registration && props.event.registration.status !== 'cancelled') return false;

  const now = Date.now();
  if (props.event.registrationOpensAt && now < new Date(props.event.registrationOpensAt).getTime()) {
    return false;
  }
  if (props.event.registrationClosesAt && now > new Date(props.event.registrationClosesAt).getTime()) {
    return false;
  }

  return new Date(props.event.startsAt).getTime() > now;
}

function canCancel(): boolean {
  return ['registered', 'waitlisted'].includes(props.event.registration?.status ?? '');
}

function register(): void {
  router.post(`/alliance/events/${props.event.id}/registration`);
}

function cancel(): void {
  router.delete(`/alliance/events/${props.event.id}/registration`);
}

function saveFormation(): void {
  formationForm
    .transform((data) => ({
      name: data.name,
      heroes: data.heroes_text
        .split(',')
        .map((hero) => hero.trim())
        .filter(Boolean),
      infantry_percent: Number(data.infantry_percent),
      cavalry_percent: Number(data.cavalry_percent),
      archer_percent: Number(data.archer_percent),
      notes: data.notes || null,
      is_default: data.is_default,
    }))
    .post('/alliance/formations', {
      preserveScroll: true,
      onSuccess: () => formationForm.reset(),
    });
}

function registrationLabel(): string {
  if (!props.event.registration) return 'Not registered';
  if (props.event.registration.status === 'waitlisted' && props.event.registration.waitlistPosition) {
    return `Waitlisted · position ${props.event.registration.waitlistPosition}`;
  }

  return props.event.registration.status.replace('_', ' ');
}
</script>

<template>
  <Head :title="event.title" />

  <main class="mx-auto min-h-screen max-w-5xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/alliance/events">
        ← Events
      </Link>
      <Link
        v-if="canManage"
        class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold hover:border-cyan-400"
        href="/alliance/events/manage"
      >
        Coordinator dashboard
      </Link>
    </div>

    <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-6 sm:p-8">
      <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-start">
        <div>
          <p class="text-sm font-semibold tracking-[0.18em] text-cyan-300 uppercase">Alliance event</p>
          <h1 class="mt-2 text-3xl font-bold sm:text-4xl">{{ event.title }}</h1>
          <p class="mt-3 text-sm font-semibold capitalize text-slate-300">{{ registrationLabel() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            v-if="canJoin()"
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950"
            type="button"
            @click="register"
          >
            Join event
          </button>
          <button
            v-if="canCancel()"
            class="rounded-lg border border-rose-700 px-4 py-2 font-semibold text-rose-200"
            type="button"
            @click="cancel"
          >
            Cancel registration
          </button>
        </div>
      </div>

      <dl class="mt-7 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-800 p-4">
          <dt class="text-sm text-slate-400">Your local time · {{ userTimezone }}</dt>
          <dd class="mt-1 font-semibold">{{ formatInZone(event.startsAt, userTimezone) }}</dd>
        </div>
        <div class="rounded-xl border border-slate-800 p-4">
          <dt class="text-sm text-slate-400">Alliance time · {{ event.allianceTimezone }}</dt>
          <dd class="mt-1 font-semibold">{{ formatInZone(event.startsAt, event.allianceTimezone) }}</dd>
        </div>
        <div class="rounded-xl border border-slate-800 p-4">
          <dt class="text-sm text-slate-400">Participation</dt>
          <dd class="mt-1 font-semibold">
            {{ event.registeredCount }} registered · {{ event.waitlistedCount }} waitlisted
          </dd>
        </div>
        <div class="rounded-xl border border-slate-800 p-4">
          <dt class="text-sm text-slate-400">Capacity</dt>
          <dd class="mt-1 font-semibold">{{ event.capacity ?? 'No limit' }}</dd>
        </div>
      </dl>

      <div v-if="event.instructions" class="mt-7 border-t border-slate-800 pt-6">
        <h2 class="text-lg font-semibold">Instructions</h2>
        <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-300">{{ event.instructions }}</p>
      </div>
    </section>

    <section class="mt-8" aria-labelledby="recommended-formations-heading">
      <h2 id="recommended-formations-heading" class="text-2xl font-semibold">Recommended formations</h2>
      <div v-if="recommendedFormations.length" class="mt-4 grid gap-4 lg:grid-cols-2">
        <article
          v-for="formation in recommendedFormations"
          :key="formation.id"
          class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5"
        >
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-semibold">{{ formation.name }}</h3>
            <span class="rounded-full bg-slate-800 px-2.5 py-1 text-xs font-semibold capitalize">
              {{ formation.assignmentRole }}
            </span>
          </div>
          <p class="mt-3 text-lg font-semibold">
            {{ formation.infantryPercent }}% infantry · {{ formation.cavalryPercent }}% cavalry ·
            {{ formation.archerPercent }}% archers
          </p>
          <p v-if="formation.heroes.length" class="mt-2 text-sm text-slate-300">
            Heroes: {{ formation.heroes.join(', ') }}
          </p>
          <p v-if="formation.notes" class="mt-2 text-sm text-slate-400">{{ formation.notes }}</p>

          <div v-if="formation.guidance" class="mt-4 rounded-xl bg-slate-950/60 p-3 text-sm">
            <p class="font-semibold">{{ formation.guidance.name }}</p>
            <p v-if="formation.guidance.rationale" class="mt-1 text-slate-400">
              {{ formation.guidance.rationale }}
            </p>
            <p v-if="formation.guidance.source" class="mt-2 text-xs text-slate-500">
              Source: {{ formation.guidance.source }}
            </p>
            <p v-if="formation.guidance.effectiveFrom" class="mt-1 text-xs text-slate-500">
              Effective {{ formation.guidance.effectiveFrom }}<span v-if="formation.guidance.effectiveUntil">
                through {{ formation.guidance.effectiveUntil }}</span
              >
            </p>
          </div>
        </article>
      </div>
      <p v-else class="mt-3 text-sm text-slate-500">No event-specific formation guidance has been published.</p>
    </section>

    <section class="mt-8" aria-labelledby="rally-groups-heading">
      <h2 id="rally-groups-heading" class="text-2xl font-semibold">Rally groups</h2>
      <div v-if="rallyGroups.length" class="mt-4 space-y-4">
        <article
          v-for="group in rallyGroups"
          :key="group.id"
          class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5"
        >
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-lg font-semibold">{{ group.name }}</h3>
            <span v-if="group.maxJoiners" class="text-xs text-slate-400">
              Max joiners: {{ group.maxJoiners }}
            </span>
          </div>
          <p v-if="group.notes" class="mt-2 text-sm text-slate-400">{{ group.notes }}</p>

          <ul v-if="group.assignments.length" class="mt-4 divide-y divide-slate-800">
            <li
              v-for="assignment in group.assignments"
              :key="assignment.id"
              class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"
            >
              <span class="font-semibold">{{ assignment.memberName }}</span>
              <span class="text-slate-300 capitalize">
                {{ assignment.role }}<span v-if="assignment.slotNumber"> #{{ assignment.slotNumber }}</span>
                · {{ assignment.status.replace('_', ' ') }}
              </span>
            </li>
          </ul>
          <p v-else class="mt-3 text-sm text-slate-500">No members assigned yet.</p>
        </article>
      </div>
      <p v-else class="mt-3 text-sm text-slate-500">No rally groups have been configured for this event.</p>
    </section>

    <section class="mt-8 grid gap-6 lg:grid-cols-2" aria-label="Saved formations">
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
        <h2 class="text-xl font-semibold">Your saved formations</h2>
        <div v-if="savedFormations.length" class="mt-4 space-y-3">
          <article v-for="formation in savedFormations" :key="formation.id" class="rounded-xl border border-slate-800 p-4">
            <div class="flex items-center justify-between gap-2">
              <h3 class="font-semibold">{{ formation.name }}</h3>
              <span v-if="formation.isDefault" class="text-xs font-semibold text-cyan-300">Default</span>
            </div>
            <p class="mt-2 text-sm text-slate-300">
              {{ formation.infantryPercent }} / {{ formation.cavalryPercent }} / {{ formation.archerPercent }}
            </p>
            <p v-if="formation.heroes.length" class="mt-1 text-sm text-slate-400">
              {{ formation.heroes.join(', ') }}
            </p>
          </article>
        </div>
        <p v-else class="mt-3 text-sm text-slate-500">You have not saved a formation yet.</p>
      </div>

      <form class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6" @submit.prevent="saveFormation">
        <h2 class="text-xl font-semibold">Save a formation</h2>
        <div class="mt-4 space-y-4">
          <div>
            <label class="text-sm font-medium" for="formation-name">Name</label>
            <input
              id="formation-name"
              v-model="formationForm.name"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              required
              maxlength="100"
            />
          </div>
          <div>
            <label class="text-sm font-medium" for="formation-heroes">Heroes</label>
            <input
              id="formation-heroes"
              v-model="formationForm.heroes_text"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              placeholder="Hero one, Hero two, Hero three"
            />
          </div>
          <fieldset>
            <legend class="text-sm font-medium">Troop ratio</legend>
            <div class="mt-2 grid grid-cols-3 gap-2">
              <label class="text-xs text-slate-400">
                Infantry %
                <input
                  v-model="formationForm.infantry_percent"
                  class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-white"
                  max="100"
                  min="0"
                  type="number"
                />
              </label>
              <label class="text-xs text-slate-400">
                Cavalry %
                <input
                  v-model="formationForm.cavalry_percent"
                  class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-white"
                  max="100"
                  min="0"
                  type="number"
                />
              </label>
              <label class="text-xs text-slate-400">
                Archers %
                <input
                  v-model="formationForm.archer_percent"
                  class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-white"
                  max="100"
                  min="0"
                  type="number"
                />
              </label>
            </div>
          </fieldset>
          <div>
            <label class="text-sm font-medium" for="formation-notes">Notes</label>
            <textarea
              id="formation-notes"
              v-model="formationForm.notes"
              class="mt-1 min-h-24 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              maxlength="2000"
            />
          </div>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="formationForm.is_default" type="checkbox" />
            Make this my default formation
          </label>
          <p v-if="Object.keys(formationForm.errors).length" class="text-sm text-rose-300" role="alert">
            Check the formation values. Troop percentages must total 100%.
          </p>
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            :disabled="formationForm.processing"
            type="submit"
          >
            Save formation
          </button>
        </div>
      </form>
    </section>
  </main>
</template>
