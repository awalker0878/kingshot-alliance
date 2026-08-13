<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import { useLocale } from '../../../localization';
import AppLayout from '../../../layouts/AppLayout.vue';

const props = defineProps<{
  user: { name: string; email?: string };
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

const { t, formatDate } = useLocale();

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

function formatAllianceDate(value: string): string {
  return formatDate(value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    timeZone: props.alliance.timezone,
    timeZoneName: 'short',
  });
}

function recurrenceLabel(value: string): string {
  return t(`eventCoordinator.recurrence${value.charAt(0).toUpperCase()}${value.slice(1)}`);
}

function roleLabel(value: string): string {
  return t(`eventCoordinator.role${value.charAt(0).toUpperCase()}${value.slice(1)}`);
}

function registrationStatusLabel(value: string): string {
  const key = value === 'no_show' ? 'noShow' : value;
  return t(`allianceOperations.status.${key}`);
}

function assignmentStatusLabel(value: string): string {
  if (value === 'assigned') return t('eventCoordinator.statusAssigned');
  if (value === 'participated') return t('eventCoordinator.statusParticipated');
  if (value === 'no_show') return t('allianceOperations.status.noShow');
  return value;
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

function registrationsForOccurrence(occurrenceId: string) {
  return props.registrations.filter((item) => item.occurrenceId === occurrenceId);
}

function groupsForOccurrence(occurrenceId: string) {
  return props.groups.filter((item) => item.occurrenceId === occurrenceId);
}

function assignmentsForGroup(groupId: string) {
  return props.assignments.filter((item) => item.groupId === groupId);
}
</script>

<template>
  <Head :title="`${alliance.name} · ${t('eventCoordinator.title')}`" />

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <div class="mx-auto max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8 lg:py-10">
      <section class="ks-surface-gold overflow-hidden rounded-3xl p-6 sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-3xl">
            <Link
              class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--ks-gold)] transition hover:text-[var(--ks-gold-strong)]"
              href="/alliance/events"
            >
              <span aria-hidden="true">←</span>
              {{ t('eventCoordinator.backToEvents') }}
            </Link>
            <p class="mt-6 text-xs font-bold tracking-[0.24em] text-[var(--ks-gold)] uppercase">
              {{ t('eventCoordinator.eyebrow') }}
            </p>
            <h1 class="ks-display mt-2 text-3xl font-semibold text-[var(--ks-text)] sm:text-4xl">
              {{ t('eventCoordinator.title') }}
            </h1>
            <p
              class="mt-3 max-w-2xl text-sm leading-6 text-[var(--ks-text-secondary)] sm:text-base"
            >
              {{ t('eventCoordinator.description') }}
            </p>
          </div>
          <div
            class="rounded-2xl border border-[var(--ks-border)] bg-black/20 px-4 py-3 text-sm text-[var(--ks-text-secondary)]"
          >
            <span class="font-semibold text-[var(--ks-text)]">{{ alliance.name }}</span>
            <span class="mx-2 text-[var(--ks-text-muted)]">·</span>
            {{ alliance.timezone }}
          </div>
        </div>
      </section>

      <nav
        class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
        :aria-label="t('eventCoordinator.title')"
      >
        <a
          class="ks-surface rounded-2xl px-4 py-3 text-sm font-semibold hover:border-[var(--ks-gold-soft)]"
          href="#scheduling"
        >
          {{ t('eventCoordinator.scheduling') }}
        </a>
        <a
          class="ks-surface rounded-2xl px-4 py-3 text-sm font-semibold hover:border-[var(--ks-gold-soft)]"
          href="#guidance"
        >
          {{ t('eventCoordinator.remindersGuidance') }}
        </a>
        <a
          class="ks-surface rounded-2xl px-4 py-3 text-sm font-semibold hover:border-[var(--ks-gold-soft)]"
          href="#rally-setup"
        >
          {{ t('eventCoordinator.rallySetup') }}
        </a>
        <a
          class="ks-surface rounded-2xl px-4 py-3 text-sm font-semibold hover:border-[var(--ks-gold-soft)]"
          href="#readiness"
        >
          {{ t('eventCoordinator.readiness') }}
        </a>
      </nav>

      <section
        id="scheduling"
        class="scroll-mt-6 space-y-4"
        :aria-labelledby="'scheduling-heading'"
      >
        <div>
          <p class="text-xs font-bold tracking-[0.22em] text-[var(--ks-gold)] uppercase">01</p>
          <h2 id="scheduling-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('eventCoordinator.scheduling') }}
          </h2>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
          <form class="ks-surface rounded-3xl p-5 sm:p-6" @submit.prevent="submitEvent">
            <h3 class="text-lg font-semibold">{{ t('eventCoordinator.createEvent') }}</h3>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
              <label class="sm:col-span-2">
                <span class="text-sm font-medium">{{ t('eventCoordinator.eventTitle') }}</span>
                <input
                  v-model="eventForm.title"
                  required
                  maxlength="160"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
              <label>
                <span class="text-sm font-medium"
                  >{{ t('eventCoordinator.firstStart') }} · {{ alliance.timezone }}</span
                >
                <input
                  v-model="eventForm.first_local_start"
                  required
                  type="datetime-local"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
              <label>
                <span class="text-sm font-medium">{{ t('eventCoordinator.durationMinutes') }}</span>
                <input
                  v-model="eventForm.duration_minutes"
                  required
                  min="1"
                  max="1440"
                  type="number"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
              <label>
                <span class="text-sm font-medium">{{
                  t('allianceOperations.events.capacity')
                }}</span>
                <input
                  v-model="eventForm.capacity"
                  min="1"
                  type="number"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
              <label>
                <span class="text-sm font-medium">{{
                  t('eventCoordinator.registrationOpens')
                }}</span>
                <input
                  v-model="eventForm.registration_opens_minutes_before"
                  min="0"
                  type="number"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
              <label>
                <span class="text-sm font-medium">{{
                  t('eventCoordinator.registrationCloses')
                }}</span>
                <input
                  v-model="eventForm.registration_closes_minutes_before"
                  min="0"
                  type="number"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
              <label>
                <span class="text-sm font-medium">{{ t('eventCoordinator.recurrence') }}</span>
                <select
                  v-model="eventForm.recurrence_frequency"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                >
                  <option v-for="option in recurrenceOptions" :key="option" :value="option">
                    {{ recurrenceLabel(option) }}
                  </option>
                </select>
              </label>
              <label>
                <span class="text-sm font-medium">{{ t('eventCoordinator.interval') }}</span>
                <input
                  v-model="eventForm.recurrence_interval"
                  min="1"
                  max="52"
                  type="number"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
              <label class="sm:col-span-2">
                <span class="text-sm font-medium">{{ t('eventCoordinator.recurrenceEnd') }}</span>
                <input
                  v-model="eventForm.recurrence_until_local"
                  type="datetime-local"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
              <label class="sm:col-span-2">
                <span class="text-sm font-medium">{{ t('eventDetail.instructions') }}</span>
                <textarea
                  v-model="eventForm.instructions"
                  maxlength="10000"
                  class="mt-1.5 min-h-28 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
            </div>
            <p
              v-if="Object.keys(eventForm.errors).length"
              class="mt-4 text-sm text-[var(--ks-red)]"
              role="alert"
            >
              {{ t('eventCoordinator.eventValidation') }}
            </p>
            <button
              :disabled="eventForm.processing"
              class="mt-5 rounded-xl bg-[var(--ks-gold)] px-4 py-2.5 font-semibold text-slate-950 disabled:opacity-60"
              type="submit"
            >
              {{ t('eventCoordinator.createEventAction') }}
            </button>
          </form>

          <div class="space-y-6">
            <form class="ks-surface rounded-3xl p-5 sm:p-6" @submit.prevent="submitTemplate">
              <h3 class="text-lg font-semibold">{{ t('eventCoordinator.createTemplate') }}</h3>
              <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="sm:col-span-2">
                  <span class="text-sm font-medium">{{ t('eventDetail.formationName') }}</span>
                  <input
                    v-model="templateForm.name"
                    required
                    maxlength="120"
                    class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                  />
                </label>
                <label
                  ><span class="text-sm font-medium">{{
                    t('eventCoordinator.durationMinutes')
                  }}</span
                  ><input
                    v-model="templateForm.duration_minutes"
                    required
                    min="1"
                    max="1440"
                    type="number"
                    class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                /></label>
                <label
                  ><span class="text-sm font-medium">{{
                    t('allianceOperations.events.capacity')
                  }}</span
                  ><input
                    v-model="templateForm.capacity"
                    min="1"
                    type="number"
                    class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                /></label>
                <label
                  ><span class="text-sm font-medium">{{ t('eventCoordinator.recurrence') }}</span
                  ><select
                    v-model="templateForm.recurrence_frequency"
                    class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                  >
                    <option v-for="option in recurrenceOptions" :key="option" :value="option">
                      {{ recurrenceLabel(option) }}
                    </option>
                  </select></label
                >
                <label
                  ><span class="text-sm font-medium">{{ t('eventCoordinator.interval') }}</span
                  ><input
                    v-model="templateForm.recurrence_interval"
                    min="1"
                    max="52"
                    type="number"
                    class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                /></label>
                <label
                  ><span class="text-sm font-medium">{{
                    t('eventCoordinator.registrationOpens')
                  }}</span
                  ><input
                    v-model="templateForm.registration_opens_minutes_before"
                    min="0"
                    type="number"
                    class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                /></label>
                <label
                  ><span class="text-sm font-medium">{{
                    t('eventCoordinator.registrationCloses')
                  }}</span
                  ><input
                    v-model="templateForm.registration_closes_minutes_before"
                    min="0"
                    type="number"
                    class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                /></label>
                <label class="sm:col-span-2"
                  ><span class="text-sm font-medium">{{ t('eventDetail.instructions') }}</span
                  ><textarea
                    v-model="templateForm.instructions"
                    maxlength="10000"
                    class="mt-1.5 min-h-20 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                  />
                </label>
              </div>
              <button
                class="mt-5 rounded-xl border border-[var(--ks-gold-soft)] px-4 py-2.5 font-semibold text-[var(--ks-gold)]"
                type="submit"
              >
                {{ t('eventCoordinator.saveTemplate') }}
              </button>
            </form>

            <form class="ks-surface rounded-3xl p-5 sm:p-6" @submit.prevent="submitTemplateEvent">
              <h3 class="text-lg font-semibold">
                {{ t('eventCoordinator.scheduleFromTemplate') }}
              </h3>
              <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="sm:col-span-2"
                  ><span class="text-sm font-medium">{{
                    t('eventCoordinator.createTemplate')
                  }}</span
                  ><select
                    v-model="templateEventForm.template_id"
                    required
                    class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                  >
                    <option value="">{{ t('eventCoordinator.chooseTemplate') }}</option>
                    <option v-for="template in templates" :key="template.id" :value="template.id">
                      {{ template.name }}
                    </option>
                  </select></label
                >
                <label
                  ><span class="text-sm font-medium">{{ t('eventCoordinator.firstStart') }}</span
                  ><input
                    v-model="templateEventForm.first_local_start"
                    required
                    type="datetime-local"
                    class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                /></label>
                <label
                  ><span class="text-sm font-medium">{{ t('eventCoordinator.recurrenceEnd') }}</span
                  ><input
                    v-model="templateEventForm.recurrence_until_local"
                    type="datetime-local"
                    class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                /></label>
                <label class="sm:col-span-2"
                  ><span class="text-sm font-medium">{{ t('eventCoordinator.optionalTitle') }}</span
                  ><input
                    v-model="templateEventForm.title"
                    maxlength="160"
                    class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                /></label>
              </div>
              <button
                class="mt-5 rounded-xl border border-[var(--ks-blue)] px-4 py-2.5 font-semibold text-[var(--ks-blue)]"
                type="submit"
              >
                {{ t('eventCoordinator.scheduleTemplate') }}
              </button>
            </form>
          </div>
        </div>
      </section>

      <section id="guidance" class="scroll-mt-6 space-y-4" :aria-labelledby="'guidance-heading'">
        <div>
          <p class="text-xs font-bold tracking-[0.22em] text-[var(--ks-gold)] uppercase">02</p>
          <h2 id="guidance-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('eventCoordinator.remindersGuidance') }}
          </h2>
        </div>
        <div class="grid gap-6 xl:grid-cols-[0.7fr_1.3fr]">
          <form class="ks-surface rounded-3xl p-5 sm:p-6" @submit.prevent="submitReminder">
            <h3 class="text-lg font-semibold">{{ t('eventCoordinator.reminderRule') }}</h3>
            <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
              {{ t('eventCoordinator.reminderHelp') }}
            </p>
            <div class="mt-5 space-y-4">
              <label
                ><span class="text-sm font-medium">{{ t('navigation.events') }}</span
                ><select
                  v-model="reminderForm.event_id"
                  required
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                >
                  <option value="">{{ t('eventCoordinator.chooseEvent') }}</option>
                  <option v-for="event in events" :key="event.id" :value="event.id">
                    {{ event.title }}
                  </option>
                </select></label
              >
              <label
                ><span class="text-sm font-medium">{{ t('eventCoordinator.minutesBefore') }}</span
                ><input
                  v-model="reminderForm.minutes_before_start"
                  required
                  min="1"
                  max="10080"
                  type="number"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
              /></label>
            </div>
            <button
              class="mt-5 rounded-xl border border-[var(--ks-blue)] px-4 py-2.5 font-semibold text-[var(--ks-blue)]"
              type="submit"
            >
              {{ t('eventCoordinator.addReminder') }}
            </button>
          </form>

          <form class="ks-surface rounded-3xl p-5 sm:p-6" @submit.prevent="submitGuidance">
            <h3 class="text-lg font-semibold">{{ t('eventCoordinator.rallyGuidance') }}</h3>
            <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
              {{ t('eventCoordinator.guidanceHelp') }}
            </p>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
              <label class="sm:col-span-3"
                ><span class="text-sm font-medium">{{ t('eventDetail.formationName') }}</span
                ><input
                  v-model="guidanceForm.name"
                  required
                  maxlength="120"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
              /></label>
              <label
                ><span class="text-sm font-medium">{{ t('eventDetail.infantry') }} %</span
                ><input
                  v-model="guidanceForm.infantry_percent"
                  required
                  min="0"
                  max="100"
                  type="number"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
              /></label>
              <label
                ><span class="text-sm font-medium">{{ t('eventDetail.cavalry') }} %</span
                ><input
                  v-model="guidanceForm.cavalry_percent"
                  required
                  min="0"
                  max="100"
                  type="number"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
              /></label>
              <label
                ><span class="text-sm font-medium">{{ t('eventDetail.archers') }} %</span
                ><input
                  v-model="guidanceForm.archer_percent"
                  required
                  min="0"
                  max="100"
                  type="number"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
              /></label>
              <label class="sm:col-span-3"
                ><span class="text-sm font-medium">{{
                  t('eventCoordinator.heroRecommendations')
                }}</span
                ><input
                  v-model="guidanceForm.hero_recommendations_text"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                  :placeholder="t('eventDetail.heroesHint')"
              /></label>
              <label
                ><span class="text-sm font-medium">{{ t('eventCoordinator.effectiveFrom') }}</span
                ><input
                  v-model="guidanceForm.effective_from"
                  required
                  type="date"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
              /></label>
              <label
                ><span class="text-sm font-medium">{{ t('eventCoordinator.effectiveUntil') }}</span
                ><input
                  v-model="guidanceForm.effective_until"
                  type="date"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
              /></label>
              <label
                ><span class="text-sm font-medium">{{ t('eventDetail.source') }}</span
                ><input
                  v-model="guidanceForm.source"
                  maxlength="255"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
              /></label>
              <label class="sm:col-span-3"
                ><span class="text-sm font-medium">{{
                  t('eventCoordinator.leadRequirements')
                }}</span
                ><textarea
                  v-model="guidanceForm.lead_requirements"
                  class="mt-1.5 min-h-20 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
              <label class="sm:col-span-3"
                ><span class="text-sm font-medium">{{ t('eventCoordinator.joinerGuidance') }}</span
                ><textarea
                  v-model="guidanceForm.joiner_guidance"
                  class="mt-1.5 min-h-20 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
              <label class="sm:col-span-3"
                ><span class="text-sm font-medium">{{ t('eventDetail.notes') }}</span
                ><textarea
                  v-model="guidanceForm.notes"
                  class="mt-1.5 min-h-20 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
              <label class="sm:col-span-3"
                ><span class="text-sm font-medium">{{ t('eventDetail.guidance') }}</span
                ><textarea
                  v-model="guidanceForm.rationale"
                  class="mt-1.5 min-h-20 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
            </div>
            <p
              v-if="Object.keys(guidanceForm.errors).length"
              class="mt-4 text-sm text-[var(--ks-red)]"
              role="alert"
            >
              {{ t('eventCoordinator.guidanceValidation') }}
            </p>
            <button
              class="mt-5 rounded-xl bg-[var(--ks-gold)] px-4 py-2.5 font-semibold text-slate-950"
              type="submit"
            >
              {{ t('eventCoordinator.publishGuidance') }}
            </button>
          </form>
        </div>
      </section>

      <section id="rally-setup" class="scroll-mt-6 space-y-4" :aria-labelledby="'rally-heading'">
        <div>
          <p class="text-xs font-bold tracking-[0.22em] text-[var(--ks-gold)] uppercase">03</p>
          <h2 id="rally-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('eventCoordinator.rallySetup') }}
          </h2>
        </div>
        <div class="grid gap-6 xl:grid-cols-3">
          <form class="ks-surface rounded-3xl p-5" @submit.prevent="submitFormation">
            <h3 class="text-lg font-semibold">{{ t('eventCoordinator.eventFormation') }}</h3>
            <div class="mt-5 space-y-4">
              <label
                ><span class="text-sm font-medium">{{ t('eventCoordinator.occurrence') }}</span
                ><select
                  v-model="formationForm.occurrence_id"
                  required
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                >
                  <option value="">{{ t('eventCoordinator.chooseOccurrence') }}</option>
                  <option
                    v-for="occurrence in occurrences"
                    :key="occurrence.id"
                    :value="occurrence.id"
                  >
                    {{ occurrence.title }} · {{ formatAllianceDate(occurrence.startsAt) }}
                  </option>
                </select></label
              >
              <label
                ><span class="text-sm font-medium">{{ t('eventDetail.formationName') }}</span
                ><input
                  v-model="formationForm.name"
                  required
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
              /></label>
              <label
                ><span class="text-sm font-medium">{{ t('eventDetail.assignmentRole') }}</span
                ><select
                  v-model="formationForm.assignment_role"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                >
                  <option v-for="role in roleOptions" :key="role" :value="role">
                    {{ roleLabel(role) }}
                  </option>
                </select></label
              >
              <label
                ><span class="text-sm font-medium">{{ t('eventDetail.guidance') }}</span
                ><select
                  v-model="formationForm.guidance_rule_id"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                >
                  <option value="">{{ t('eventCoordinator.noGuidance') }}</option>
                  <option v-for="rule in guidance" :key="rule.id" :value="rule.id">
                    {{ rule.name }}
                  </option>
                </select></label
              >
              <label
                ><span class="text-sm font-medium">{{ t('eventDetail.heroes') }}</span
                ><input
                  v-model="formationForm.heroes_text"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                  :placeholder="t('eventDetail.heroesHint')"
              /></label>
              <div class="grid grid-cols-3 gap-2">
                <label
                  ><span class="text-xs font-medium">{{ t('eventDetail.infantry') }} %</span
                  ><input
                    v-model="formationForm.infantry_percent"
                    type="number"
                    min="0"
                    max="100"
                    class="mt-1 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-2 py-2.5"
                /></label>
                <label
                  ><span class="text-xs font-medium">{{ t('eventDetail.cavalry') }} %</span
                  ><input
                    v-model="formationForm.cavalry_percent"
                    type="number"
                    min="0"
                    max="100"
                    class="mt-1 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-2 py-2.5"
                /></label>
                <label
                  ><span class="text-xs font-medium">{{ t('eventDetail.archers') }} %</span
                  ><input
                    v-model="formationForm.archer_percent"
                    type="number"
                    min="0"
                    max="100"
                    class="mt-1 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-2 py-2.5"
                /></label>
              </div>
              <label
                ><span class="text-sm font-medium">{{ t('eventDetail.notes') }}</span
                ><textarea
                  v-model="formationForm.notes"
                  class="mt-1.5 min-h-20 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
            </div>
            <button
              class="mt-5 rounded-xl border border-[var(--ks-blue)] px-4 py-2.5 font-semibold text-[var(--ks-blue)]"
              type="submit"
            >
              {{ t('eventCoordinator.addFormation') }}
            </button>
          </form>

          <form class="ks-surface rounded-3xl p-5" @submit.prevent="submitGroup">
            <h3 class="text-lg font-semibold">{{ t('eventCoordinator.rallyGroup') }}</h3>
            <div class="mt-5 space-y-4">
              <label
                ><span class="text-sm font-medium">{{ t('eventCoordinator.occurrence') }}</span
                ><select
                  v-model="groupForm.occurrence_id"
                  required
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                >
                  <option value="">{{ t('eventCoordinator.chooseOccurrence') }}</option>
                  <option
                    v-for="occurrence in occurrences"
                    :key="occurrence.id"
                    :value="occurrence.id"
                  >
                    {{ occurrence.title }} · {{ formatAllianceDate(occurrence.startsAt) }}
                  </option>
                </select></label
              >
              <label
                ><span class="text-sm font-medium">{{ t('eventDetail.formationName') }}</span
                ><input
                  v-model="groupForm.name"
                  required
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
              /></label>
              <label
                ><span class="text-sm font-medium">{{ t('eventCoordinator.maxJoiners') }}</span
                ><input
                  v-model="groupForm.max_joiners"
                  min="1"
                  type="number"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
              /></label>
              <label
                ><span class="text-sm font-medium">{{
                  t('eventDetail.recommendedFormations')
                }}</span
                ><select
                  v-model="groupForm.recommended_formation_id"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                >
                  <option value="">{{ t('eventCoordinator.none') }}</option>
                  <option
                    v-for="formation in recommendationsForOccurrence(groupForm.occurrence_id)"
                    :key="formation.id"
                    :value="formation.id"
                  >
                    {{ formation.name }}
                  </option>
                </select></label
              >
              <label
                ><span class="text-sm font-medium">{{ t('eventDetail.notes') }}</span
                ><textarea
                  v-model="groupForm.notes"
                  class="mt-1.5 min-h-20 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                />
              </label>
            </div>
            <button
              class="mt-5 rounded-xl border border-[var(--ks-blue)] px-4 py-2.5 font-semibold text-[var(--ks-blue)]"
              type="submit"
            >
              {{ t('eventCoordinator.createGroup') }}
            </button>
          </form>

          <form class="ks-surface rounded-3xl p-5" @submit.prevent="submitAssignment">
            <h3 class="text-lg font-semibold">{{ t('eventCoordinator.assignMember') }}</h3>
            <div class="mt-5 space-y-4">
              <label
                ><span class="text-sm font-medium">{{ t('eventCoordinator.rallyGroup') }}</span
                ><select
                  v-model="assignmentForm.group_id"
                  required
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                >
                  <option value="">{{ t('eventCoordinator.chooseGroup') }}</option>
                  <option v-for="group in groups" :key="group.id" :value="group.id">
                    {{ group.name }}
                  </option>
                </select></label
              >
              <label
                ><span class="text-sm font-medium">{{ t('eventCoordinator.member') }}</span
                ><select
                  v-model="assignmentForm.membership_id"
                  required
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                >
                  <option value="">{{ t('eventCoordinator.chooseMember') }}</option>
                  <option v-for="member in members" :key="member.id" :value="member.id">
                    {{ member.name }}
                  </option>
                </select></label
              >
              <label
                ><span class="text-sm font-medium">{{ t('eventDetail.assignmentRole') }}</span
                ><select
                  v-model="assignmentForm.role"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
                >
                  <option v-for="role in roleOptions" :key="role" :value="role">
                    {{ roleLabel(role) }}
                  </option>
                </select></label
              >
              <label
                ><span class="text-sm font-medium">{{ t('eventCoordinator.slotNumber') }}</span
                ><input
                  v-model="assignmentForm.slot_number"
                  min="1"
                  type="number"
                  class="mt-1.5 w-full rounded-xl border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-3 py-2.5"
              /></label>
            </div>
            <button
              class="mt-5 rounded-xl bg-[var(--ks-gold)] px-4 py-2.5 font-semibold text-slate-950"
              type="submit"
            >
              {{ t('eventCoordinator.saveAssignment') }}
            </button>
          </form>
        </div>
      </section>

      <section id="readiness" class="scroll-mt-6 space-y-4" :aria-labelledby="'readiness-heading'">
        <div>
          <p class="text-xs font-bold tracking-[0.22em] text-[var(--ks-gold)] uppercase">04</p>
          <h2 id="readiness-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('eventCoordinator.readiness') }}
          </h2>
        </div>
        <div class="space-y-5">
          <article
            v-for="occurrence in occurrences"
            :key="occurrence.id"
            class="ks-surface rounded-3xl p-5 sm:p-6"
          >
            <div
              class="flex flex-col gap-2 border-b border-[var(--ks-border)] pb-4 sm:flex-row sm:items-end sm:justify-between"
            >
              <div>
                <h3 class="text-lg font-semibold">{{ occurrence.title }}</h3>
                <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">
                  {{ formatAllianceDate(occurrence.startsAt) }}
                </p>
              </div>
              <Link
                class="text-sm font-semibold text-[var(--ks-blue)] hover:underline"
                :href="`/alliance/events/${occurrence.id}`"
                >{{ t('allianceOperations.events.details') }}</Link
              >
            </div>
            <div class="mt-5 grid gap-6 xl:grid-cols-2">
              <div>
                <h4
                  class="text-sm font-bold tracking-[0.14em] text-[var(--ks-text-secondary)] uppercase"
                >
                  {{ t('eventCoordinator.registrations') }}
                </h4>
                <div
                  v-if="registrationsForOccurrence(occurrence.id).length"
                  class="mt-3 divide-y divide-[var(--ks-border)]"
                >
                  <div
                    v-for="registration in registrationsForOccurrence(occurrence.id)"
                    :key="registration.id"
                    class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                  >
                    <div>
                      <p class="font-medium">{{ registration.memberName }}</p>
                      <p class="mt-0.5 text-xs text-[var(--ks-text-muted)]">
                        {{ registrationStatusLabel(registration.status) }}
                      </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                      <button
                        class="rounded-lg border border-[var(--ks-green)] px-3 py-1.5 text-sm font-semibold text-[var(--ks-green)]"
                        type="button"
                        @click="attendance(occurrence.id, registration.id, 'attended')"
                      >
                        {{ t('allianceOperations.status.attended') }}
                      </button>
                      <button
                        class="rounded-lg border border-[var(--ks-red)] px-3 py-1.5 text-sm font-semibold text-[var(--ks-red)]"
                        type="button"
                        @click="attendance(occurrence.id, registration.id, 'no_show')"
                      >
                        {{ t('allianceOperations.status.noShow') }}
                      </button>
                    </div>
                  </div>
                </div>
                <p v-else class="mt-3 text-sm text-[var(--ks-text-muted)]">
                  {{ t('eventCoordinator.noRegistrations') }}
                </p>
              </div>

              <div>
                <h4
                  class="text-sm font-bold tracking-[0.14em] text-[var(--ks-text-secondary)] uppercase"
                >
                  {{ t('eventCoordinator.rallyAssignments') }}
                </h4>
                <div
                  v-if="
                    groupsForOccurrence(occurrence.id).some(
                      (group) => assignmentsForGroup(group.id).length,
                    )
                  "
                  class="mt-3 space-y-4"
                >
                  <div v-for="group in groupsForOccurrence(occurrence.id)" :key="group.id">
                    <div
                      v-if="assignmentsForGroup(group.id).length"
                      class="rounded-2xl border border-[var(--ks-border)] bg-black/10 p-3"
                    >
                      <p class="text-sm font-semibold text-[var(--ks-gold)]">{{ group.name }}</p>
                      <div class="mt-2 divide-y divide-[var(--ks-border)]">
                        <div
                          v-for="assignment in assignmentsForGroup(group.id)"
                          :key="assignment.id"
                          class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                          <div>
                            <p class="font-medium">
                              {{ assignment.memberName }} · {{ roleLabel(assignment.role) }}
                            </p>
                            <p class="mt-0.5 text-xs text-[var(--ks-text-muted)]">
                              {{ assignmentStatusLabel(assignment.status) }}
                            </p>
                          </div>
                          <div class="flex flex-wrap gap-2">
                            <button
                              class="rounded-lg border border-[var(--ks-green)] px-3 py-1.5 text-sm font-semibold text-[var(--ks-green)]"
                              type="button"
                              @click="participation(assignment.id, 'participated')"
                            >
                              {{ t('eventCoordinator.participated') }}
                            </button>
                            <button
                              class="rounded-lg border border-[var(--ks-red)] px-3 py-1.5 text-sm font-semibold text-[var(--ks-red)]"
                              type="button"
                              @click="participation(assignment.id, 'no_show')"
                            >
                              {{ t('allianceOperations.status.noShow') }}
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <p v-else class="mt-3 text-sm text-[var(--ks-text-muted)]">
                  {{ t('eventCoordinator.noAssignments') }}
                </p>
              </div>
            </div>
          </article>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
