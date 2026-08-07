<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
  alliance: { id: string; name: string; timezone: string };
  recurrenceOptions: string[];
  roleOptions: string[];
  templates: Array<{
    id: string;
    name: string;
    durationMinutes: number;
    capacity: number | null;
    registrationOpensMinutesBefore: number | null;
    registrationClosesMinutesBefore: number;
    recurrenceFrequency: string;
    recurrenceInterval: number;
    instructions: string | null;
  }>;
  occurrences: Array<{
    id: string;
    eventId: string;
    title: string;
    startsAt: string;
    endsAt: string;
    status: string;
    capacity: number | null;
  }>;
  members: Array<{ id: string; name: string }>;
  guidance: Array<{
    id: string;
    name: string;
    infantryPercent: number;
    cavalryPercent: number;
    archerPercent: number;
    heroRecommendations: string[];
    leadRequirements: string | null;
    joinerGuidance: string | null;
    source: string | null;
    rationale: string | null;
    effectiveFrom: string | null;
    effectiveUntil: string | null;
  }>;
  recommendations: Array<{
    id: string;
    occurrenceId: string;
    name: string;
    assignmentRole: string;
  }>;
  groups: Array<{ id: string; occurrenceId: string; name: string; maxJoiners: number | null }>;
  registrations: Array<{
    id: string;
    occurrenceId: string;
    membershipId: string;
    memberName: string;
    status: string;
  }>;
  assignments: Array<{
    id: string;
    groupId: string;
    membershipId: string;
    memberName: string;
    role: string;
    slotNumber: number | null;
    status: string;
  }>;
}>();

const events = computed(() => {
  const byId = new Map<string, { id: string; title: string }>();
  for (const occurrence of props.occurrences) {
    byId.set(occurrence.eventId, { id: occurrence.eventId, title: occurrence.title });
  }
  return [...byId.values()];
});

const eventForm = useForm({
  title: '',
  first_local_start: '',
  duration_minutes: 30,
  capacity: null as number | null,
  registration_opens_minutes_before: 180 as number | null,
  registration_closes_minutes_before: 0,
  recurrence_frequency: 'none',
  recurrence_interval: 1,
  recurrence_until_local: '',
  instructions: '',
});

const templateForm = useForm({
  name: '',
  duration_minutes: 30,
  capacity: null as number | null,
  registration_opens_minutes_before: 180 as number | null,
  registration_closes_minutes_before: 0,
  recurrence_frequency: 'none',
  recurrence_interval: 1,
  instructions: '',
});

const templateEventForm = useForm({
  template_id: '',
  title: '',
  first_local_start: '',
  recurrence_until_local: '',
});
const reminderForm = useForm({ event_id: '', minutes_before_start: 60 });
const guidanceForm = useForm({
  name: '',
  infantry_percent: 10,
  cavalry_percent: 10,
  archer_percent: 80,
  hero_recommendations_text: '',
  lead_requirements: '',
  joiner_guidance: '',
  notes: '',
  effective_from: '',
  effective_until: '',
  source: '',
  rationale: '',
});
const formationForm = useForm({
  occurrence_id: '',
  name: '',
  assignment_role: 'joiner',
  guidance_rule_id: '',
  heroes_text: '',
  infantry_percent: 10,
  cavalry_percent: 10,
  archer_percent: 80,
  notes: '',
  sort_order: 0,
});
const groupForm = useForm({
  occurrence_id: '',
  name: '',
  max_joiners: null as number | null,
  recommended_formation_id: '',
  notes: '',
  sort_order: 0,
});
const assignmentForm = useForm({
  group_id: '',
  membership_id: '',
  role: 'joiner',
  slot_number: null as number | null,
});

function formatDate(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    timeZone: props.alliance.timezone,
    timeZoneName: 'short',
  }).format(new Date(value));
}

function submitEvent(): void {
  eventForm
    .transform((data) => ({
      ...data,
      capacity: data.capacity || null,
      registration_opens_minutes_before: data.registration_opens_minutes_before ?? null,
      recurrence_until_local: data.recurrence_until_local || null,
      instructions: data.instructions || null,
    }))
    .post('/alliance/events', { preserveScroll: true, onSuccess: () => eventForm.reset() });
}

function submitTemplate(): void {
  templateForm
    .transform((data) => ({
      ...data,
      capacity: data.capacity || null,
      registration_opens_minutes_before: data.registration_opens_minutes_before ?? null,
      instructions: data.instructions || null,
    }))
    .post('/alliance/event-templates', {
      preserveScroll: true,
      onSuccess: () => templateForm.reset(),
    });
}

function submitTemplateEvent(): void {
  templateEventForm
    .transform((data) => ({
      ...data,
      title: data.title || null,
      recurrence_until_local: data.recurrence_until_local || null,
    }))
    .post('/alliance/event-templates/events', {
      preserveScroll: true,
      onSuccess: () => templateEventForm.reset(),
    });
}

function submitReminder(): void {
  if (!reminderForm.event_id) return;
  reminderForm.post(`/alliance/events/${reminderForm.event_id}/reminders`, {
    preserveScroll: true,
  });
}

function submitGuidance(): void {
  guidanceForm
    .transform((data) => ({
      name: data.name,
      infantry_percent: Number(data.infantry_percent),
      cavalry_percent: Number(data.cavalry_percent),
      archer_percent: Number(data.archer_percent),
      hero_recommendations: data.hero_recommendations_text
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean),
      lead_requirements: data.lead_requirements || null,
      joiner_guidance: data.joiner_guidance || null,
      notes: data.notes || null,
      effective_from: data.effective_from,
      effective_until: data.effective_until || null,
      source: data.source || null,
      rationale: data.rationale || null,
    }))
    .post('/alliance/rally-guidance', {
      preserveScroll: true,
      onSuccess: () => guidanceForm.reset(),
    });
}

function submitFormation(): void {
  if (!formationForm.occurrence_id) return;
  formationForm
    .transform((data) => ({
      name: data.name,
      assignment_role: data.assignment_role,
      guidance_rule_id: data.guidance_rule_id || null,
      heroes: data.heroes_text
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean),
      infantry_percent: Number(data.infantry_percent),
      cavalry_percent: Number(data.cavalry_percent),
      archer_percent: Number(data.archer_percent),
      notes: data.notes || null,
      sort_order: Number(data.sort_order),
    }))
    .post(`/alliance/events/${formationForm.occurrence_id}/formations`, { preserveScroll: true });
}

function submitGroup(): void {
  if (!groupForm.occurrence_id) return;
  groupForm
    .transform((data) => ({
      name: data.name,
      max_joiners: data.max_joiners || null,
      recommended_formation_id: data.recommended_formation_id || null,
      notes: data.notes || null,
      sort_order: Number(data.sort_order),
    }))
    .post(`/alliance/events/${groupForm.occurrence_id}/rally-groups`, { preserveScroll: true });
}

function submitAssignment(): void {
  if (!assignmentForm.group_id) return;
  assignmentForm
    .transform((data) => ({
      membership_id: data.membership_id,
      role: data.role,
      slot_number: data.slot_number || null,
    }))
    .put(`/alliance/rally-groups/${assignmentForm.group_id}/assignments`, { preserveScroll: true });
}

function attendance(
  occurrenceId: string,
  registrationId: string,
  status: 'attended' | 'no_show',
): void {
  router.patch(
    `/alliance/events/${occurrenceId}/registrations/${registrationId}/attendance`,
    { status },
    { preserveScroll: true },
  );
}

function participation(assignmentId: string, status: 'participated' | 'no_show'): void {
  router.patch(
    `/alliance/rally-assignments/${assignmentId}/participation`,
    { status },
    { preserveScroll: true },
  );
}

function recommendationsForOccurrence(occurrenceId: string) {
  return props.recommendations.filter((item) => item.occurrenceId === occurrenceId);
}
</script>

<template>
  <Head :title="`${alliance.name} event coordinator`" />

  <main class="mx-auto min-h-screen max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <Link
          class="text-sm font-semibold text-cyan-300 hover:text-cyan-200"
          href="/alliance/events"
          >← Events</Link
        >
        <h1 class="mt-4 text-3xl font-bold sm:text-4xl">Event coordinator</h1>
        <p class="mt-2 text-sm text-slate-400">
          Schedule in {{ alliance.timezone }}, configure guidance, and track readiness.
        </p>
      </div>
    </div>

    <section class="mt-8 grid gap-6 xl:grid-cols-2" aria-label="Scheduling">
      <form
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
        @submit.prevent="submitEvent"
      >
        <h2 class="text-xl font-semibold">Create event</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <label class="text-sm sm:col-span-2"
            >Title<input
              v-model="eventForm.title"
              required
              maxlength="160"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >First start · {{ alliance.timezone
            }}<input
              v-model="eventForm.first_local_start"
              required
              type="datetime-local"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Duration minutes<input
              v-model="eventForm.duration_minutes"
              required
              min="1"
              max="1440"
              type="number"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Capacity<input
              v-model="eventForm.capacity"
              min="1"
              type="number"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Registration opens minutes before<input
              v-model="eventForm.registration_opens_minutes_before"
              min="0"
              type="number"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Registration closes minutes before<input
              v-model="eventForm.registration_closes_minutes_before"
              min="0"
              type="number"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Recurrence<select
              v-model="eventForm.recurrence_frequency"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option
                v-for="option in recurrenceOptions"
                :key="option"
                :value="option"
                class="capitalize"
              >
                {{ option }}
              </option>
            </select></label
          >
          <label class="text-sm"
            >Interval<input
              v-model="eventForm.recurrence_interval"
              min="1"
              max="52"
              type="number"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm sm:col-span-2"
            >Recurrence end · optional<input
              v-model="eventForm.recurrence_until_local"
              type="datetime-local"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm sm:col-span-2"
            >Instructions<textarea
              v-model="eventForm.instructions"
              maxlength="10000"
              class="mt-1 min-h-28 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            />
          </label>
        </div>
        <p
          v-if="Object.keys(eventForm.errors).length"
          class="mt-3 text-sm text-rose-300"
          role="alert"
        >
          Check the event fields and registration window.
        </p>
        <button
          :disabled="eventForm.processing"
          class="mt-4 rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
          type="submit"
        >
          Create event
        </button>
      </form>

      <div class="space-y-6">
        <form
          class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
          @submit.prevent="submitTemplate"
        >
          <h2 class="text-xl font-semibold">Create event template</h2>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <label class="text-sm sm:col-span-2"
              >Name<input
                v-model="templateForm.name"
                required
                maxlength="120"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            /></label>
            <label class="text-sm"
              >Duration minutes<input
                v-model="templateForm.duration_minutes"
                required
                min="1"
                max="1440"
                type="number"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            /></label>
            <label class="text-sm"
              >Capacity<input
                v-model="templateForm.capacity"
                min="1"
                type="number"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            /></label>
            <label class="text-sm"
              >Recurrence<select
                v-model="templateForm.recurrence_frequency"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              >
                <option v-for="option in recurrenceOptions" :key="option" :value="option">
                  {{ option }}
                </option>
              </select></label
            >
            <label class="text-sm"
              >Interval<input
                v-model="templateForm.recurrence_interval"
                min="1"
                max="52"
                type="number"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            /></label>
            <label class="text-sm"
              >Opens minutes before<input
                v-model="templateForm.registration_opens_minutes_before"
                min="0"
                type="number"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            /></label>
            <label class="text-sm"
              >Closes minutes before<input
                v-model="templateForm.registration_closes_minutes_before"
                min="0"
                type="number"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            /></label>
            <label class="text-sm sm:col-span-2"
              >Instructions<textarea
                v-model="templateForm.instructions"
                maxlength="10000"
                class="mt-1 min-h-20 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              />
            </label>
          </div>
          <button
            class="mt-4 rounded-lg border border-cyan-700 px-4 py-2 font-semibold text-cyan-100"
            type="submit"
          >
            Save template
          </button>
        </form>

        <form
          class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
          @submit.prevent="submitTemplateEvent"
        >
          <h2 class="text-xl font-semibold">Schedule from template</h2>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <label class="text-sm sm:col-span-2"
              >Template<select
                v-model="templateEventForm.template_id"
                required
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              >
                <option value="">Choose template</option>
                <option v-for="template in templates" :key="template.id" :value="template.id">
                  {{ template.name }}
                </option>
              </select></label
            >
            <label class="text-sm"
              >First start<input
                v-model="templateEventForm.first_local_start"
                required
                type="datetime-local"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            /></label>
            <label class="text-sm"
              >Recurrence end<input
                v-model="templateEventForm.recurrence_until_local"
                type="datetime-local"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            /></label>
            <label class="text-sm sm:col-span-2"
              >Optional title override<input
                v-model="templateEventForm.title"
                maxlength="160"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            /></label>
          </div>
          <button
            class="mt-4 rounded-lg border border-cyan-700 px-4 py-2 font-semibold text-cyan-100"
            type="submit"
          >
            Schedule template
          </button>
        </form>
      </div>
    </section>

    <section class="mt-8 grid gap-6 xl:grid-cols-2" aria-label="Reminders and guidance">
      <form
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
        @submit.prevent="submitReminder"
      >
        <h2 class="text-xl font-semibold">Reminder rule</h2>
        <p class="mt-2 text-sm text-slate-400">
          Phase 3 delivers in-app reminders through the retry-safe outbox.
        </p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <label class="text-sm"
            >Event<select
              v-model="reminderForm.event_id"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="">Choose event</option>
              <option v-for="event in events" :key="event.id" :value="event.id">
                {{ event.title }}
              </option>
            </select></label
          >
          <label class="text-sm"
            >Minutes before start<input
              v-model="reminderForm.minutes_before_start"
              required
              min="1"
              max="10080"
              type="number"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
        </div>
        <button
          class="mt-4 rounded-lg border border-slate-700 px-4 py-2 font-semibold"
          type="submit"
        >
          Add reminder
        </button>
      </form>

      <form
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
        @submit.prevent="submitGuidance"
      >
        <h2 class="text-xl font-semibold">Rally guidance</h2>
        <p class="mt-2 text-sm text-slate-400">
          Recommendations are versioned by effective date and can cite a source/rationale.
        </p>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
          <label class="text-sm sm:col-span-3"
            >Name<input
              v-model="guidanceForm.name"
              required
              maxlength="120"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Infantry %<input
              v-model="guidanceForm.infantry_percent"
              required
              min="0"
              max="100"
              type="number"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Cavalry %<input
              v-model="guidanceForm.cavalry_percent"
              required
              min="0"
              max="100"
              type="number"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Archers %<input
              v-model="guidanceForm.archer_percent"
              required
              min="0"
              max="100"
              type="number"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm sm:col-span-3"
            >Hero recommendations<input
              v-model="guidanceForm.hero_recommendations_text"
              placeholder="Hero one, Hero two"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Effective from<input
              v-model="guidanceForm.effective_from"
              required
              type="date"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Effective until<input
              v-model="guidanceForm.effective_until"
              type="date"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm"
            >Source<input
              v-model="guidanceForm.source"
              maxlength="255"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="text-sm sm:col-span-3"
            >Lead requirements<textarea
              v-model="guidanceForm.lead_requirements"
              class="mt-1 min-h-20 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            />
          </label>
          <label class="text-sm sm:col-span-3"
            >Joiner guidance<textarea
              v-model="guidanceForm.joiner_guidance"
              class="mt-1 min-h-20 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            />
          </label>
          <label class="text-sm sm:col-span-3"
            >Rationale<textarea
              v-model="guidanceForm.rationale"
              class="mt-1 min-h-20 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            />
          </label>
        </div>
        <p
          v-if="Object.keys(guidanceForm.errors).length"
          class="mt-3 text-sm text-rose-300"
          role="alert"
        >
          Check the effective dates and ensure the troop ratio totals 100%.
        </p>
        <button
          class="mt-4 rounded-lg border border-slate-700 px-4 py-2 font-semibold"
          type="submit"
        >
          Publish guidance
        </button>
      </form>
    </section>

    <section class="mt-8 grid gap-6 xl:grid-cols-3" aria-label="Rally setup">
      <form
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5"
        @submit.prevent="submitFormation"
      >
        <h2 class="text-lg font-semibold">Event formation</h2>
        <div class="mt-4 space-y-3">
          <label class="block text-sm"
            >Occurrence<select
              v-model="formationForm.occurrence_id"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="">Choose occurrence</option>
              <option v-for="occurrence in occurrences" :key="occurrence.id" :value="occurrence.id">
                {{ occurrence.title }} · {{ formatDate(occurrence.startsAt) }}
              </option>
            </select></label
          >
          <label class="block text-sm"
            >Name<input
              v-model="formationForm.name"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="block text-sm"
            >Role<select
              v-model="formationForm.assignment_role"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
            </select></label
          >
          <label class="block text-sm"
            >Guidance<select
              v-model="formationForm.guidance_rule_id"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="">No linked guidance</option>
              <option v-for="rule in guidance" :key="rule.id" :value="rule.id">
                {{ rule.name }}
              </option>
            </select></label
          >
          <label class="block text-sm"
            >Heroes<input
              v-model="formationForm.heroes_text"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <div class="grid grid-cols-3 gap-2">
            <label class="text-xs"
              >Inf %<input
                v-model="formationForm.infantry_percent"
                type="number"
                min="0"
                max="100"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2" /></label
            ><label class="text-xs"
              >Cav %<input
                v-model="formationForm.cavalry_percent"
                type="number"
                min="0"
                max="100"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2" /></label
            ><label class="text-xs"
              >Arc %<input
                v-model="formationForm.archer_percent"
                type="number"
                min="0"
                max="100"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2"
            /></label>
          </div>
        </div>
        <button
          class="mt-4 rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold"
          type="submit"
        >
          Add formation
        </button>
      </form>

      <form
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5"
        @submit.prevent="submitGroup"
      >
        <h2 class="text-lg font-semibold">Rally group</h2>
        <div class="mt-4 space-y-3">
          <label class="block text-sm"
            >Occurrence<select
              v-model="groupForm.occurrence_id"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="">Choose occurrence</option>
              <option v-for="occurrence in occurrences" :key="occurrence.id" :value="occurrence.id">
                {{ occurrence.title }} · {{ formatDate(occurrence.startsAt) }}
              </option>
            </select></label
          >
          <label class="block text-sm"
            >Name<input
              v-model="groupForm.name"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="block text-sm"
            >Max joiners<input
              v-model="groupForm.max_joiners"
              min="1"
              type="number"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
          <label class="block text-sm"
            >Recommended formation<select
              v-model="groupForm.recommended_formation_id"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="">None</option>
              <option
                v-for="formation in recommendationsForOccurrence(groupForm.occurrence_id)"
                :key="formation.id"
                :value="formation.id"
              >
                {{ formation.name }}
              </option>
            </select></label
          >
          <label class="block text-sm"
            >Notes<textarea
              v-model="groupForm.notes"
              class="mt-1 min-h-20 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            />
          </label>
        </div>
        <button
          class="mt-4 rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold"
          type="submit"
        >
          Create group
        </button>
      </form>

      <form
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5"
        @submit.prevent="submitAssignment"
      >
        <h2 class="text-lg font-semibold">Assign rally member</h2>
        <div class="mt-4 space-y-3">
          <label class="block text-sm"
            >Group<select
              v-model="assignmentForm.group_id"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="">Choose group</option>
              <option v-for="group in groups" :key="group.id" :value="group.id">
                {{ group.name }}
              </option>
            </select></label
          >
          <label class="block text-sm"
            >Member<select
              v-model="assignmentForm.membership_id"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="">Choose member</option>
              <option v-for="member in members" :key="member.id" :value="member.id">
                {{ member.name }}
              </option>
            </select></label
          >
          <label class="block text-sm"
            >Role<select
              v-model="assignmentForm.role"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
            </select></label
          >
          <label class="block text-sm"
            >Slot number<input
              v-model="assignmentForm.slot_number"
              min="1"
              type="number"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          /></label>
        </div>
        <button
          class="mt-4 rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold"
          type="submit"
        >
          Save assignment
        </button>
      </form>
    </section>

    <section
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
      aria-labelledby="readiness-heading"
    >
      <h2 id="readiness-heading" class="text-2xl font-semibold">Readiness and attendance</h2>
      <div class="mt-5 space-y-6">
        <article
          v-for="occurrence in occurrences"
          :key="occurrence.id"
          class="rounded-xl border border-slate-800 p-4"
        >
          <h3 class="font-semibold">{{ occurrence.title }}</h3>
          <p class="mt-1 text-sm text-slate-400">{{ formatDate(occurrence.startsAt) }}</p>
          <div class="mt-4 grid gap-5 lg:grid-cols-2">
            <div>
              <h4 class="text-sm font-semibold text-slate-300">Registrations</h4>
              <ul class="mt-2 divide-y divide-slate-800">
                <li
                  v-for="registration in registrations.filter(
                    (item) => item.occurrenceId === occurrence.id,
                  )"
                  :key="registration.id"
                  class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
                >
                  <span
                    >{{ registration.memberName }} ·
                    <span class="capitalize">{{
                      registration.status.replace('_', ' ')
                    }}</span></span
                  >
                  <span class="flex gap-2"
                    ><button
                      class="text-emerald-300 hover:text-emerald-200"
                      type="button"
                      @click="attendance(occurrence.id, registration.id, 'attended')"
                    >
                      Attended</button
                    ><button
                      class="text-rose-300 hover:text-rose-200"
                      type="button"
                      @click="attendance(occurrence.id, registration.id, 'no_show')"
                    >
                      No-show
                    </button></span
                  >
                </li>
              </ul>
            </div>
            <div>
              <h4 class="text-sm font-semibold text-slate-300">Rally assignments</h4>
              <ul class="mt-2 divide-y divide-slate-800">
                <template
                  v-for="group in groups.filter((item) => item.occurrenceId === occurrence.id)"
                  :key="group.id"
                >
                  <li
                    v-for="assignment in assignments.filter((item) => item.groupId === group.id)"
                    :key="assignment.id"
                    class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
                  >
                    <span
                      >{{ group.name }} · {{ assignment.memberName }} · {{ assignment.role }}</span
                    >
                    <span class="flex gap-2"
                      ><button
                        class="text-emerald-300 hover:text-emerald-200"
                        type="button"
                        @click="participation(assignment.id, 'participated')"
                      >
                        Participated</button
                      ><button
                        class="text-rose-300 hover:text-rose-200"
                        type="button"
                        @click="participation(assignment.id, 'no_show')"
                      >
                        No-show
                      </button></span
                    >
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </article>
      </div>
    </section>
  </main>
</template>
