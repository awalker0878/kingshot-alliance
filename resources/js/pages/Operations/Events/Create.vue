<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import EventSigil from '@/components/game/EventSigil.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Scope = 'player' | 'alliance' | 'kingdom';
type EventType = {
  id: string;
  slug: string;
  nameKey: string;
  descriptionKey: string | null;
  scope: Scope;
  registrationMode: string;
  capabilities: string[];
  allowCustomTitle: boolean;
  defaultDurationMinutes: number | null;
  defaultRecurrence: { frequency?: string; interval?: number } | null;
  metadataSchema: Record<string, unknown> | null;
};

type TemplateRow = {
  id: string;
  name: string;
  eventTypeId: string;
  title: string | null;
  timezone: string | null;
  durationMinutes: number | null;
  registrationMode: string | null;
  visibility: string;
  recurrence: Record<string, unknown> | null;
  metadata: Record<string, unknown> | null;
  reminderRules: Array<{ minutesBefore: number; channel: string; requiredCapability: string | null }>;
};

const props = defineProps<{
  user: { name: string; email: string };
  userTimezone: string;
  eventTypes: EventType[];
  templates: TemplateRow[];
  availableAlliances: Array<{ id: string; name: string; timezone: string }>;
  availableKingdoms: Array<{ id: string; name: string; timezone: string }>;
  allowedVisibilities: string[];
  allowedRegistrationModes: string[];
  allowedReminderChannels: string[];
  allowedReminderCapabilities: string[];
}>();

const { t, formatNumber } = useLocale();
const selectedEventTypeId = ref(props.eventTypes[0]?.id ?? '');
const mode = ref<'event' | 'template' | 'from-template'>('event');

const eventForm = useForm({
  event_type_id: selectedEventTypeId.value,
  title: '',
  description: '',
  starts_at: '',
  timezone: props.userTimezone,
  duration_minutes: null as number | null,
  registration_mode: '',
  visibility: 'members',
  alliance_id: '',
  kingdom_id: '',
  recurrence_frequency: '',
  recurrence_interval: 1,
  metadata_json: '',
  reminders: [] as Array<{ minutes_before: number; channel: string; required_capability: string | null }>,
});

const templateForm = useForm({
  name: '',
  event_type_id: selectedEventTypeId.value,
  title: '',
  timezone: props.userTimezone,
  duration_minutes: null as number | null,
  registration_mode: '',
  visibility: 'members',
  recurrence_frequency: '',
  recurrence_interval: 1,
  metadata_json: '',
  reminders: [] as Array<{ minutes_before: number; channel: string; required_capability: string | null }>,
});

const fromTemplateForm = useForm({
  template_id: props.templates[0]?.id ?? '',
  starts_at: '',
  alliance_id: '',
  kingdom_id: '',
});

const reminderMinutes = ref(60);
const reminderChannel = ref(props.allowedReminderChannels[0] ?? 'database');
const reminderCapability = ref('');
const templateReminderMinutes = ref(60);
const templateReminderChannel = ref(props.allowedReminderChannels[0] ?? 'database');
const templateReminderCapability = ref('');

const selectedType = computed(() => props.eventTypes.find((type) => type.id === selectedEventTypeId.value) ?? null);
const selectedTemplate = computed(() => props.templates.find((template) => template.id === fromTemplateForm.template_id) ?? null);

function selectType(type: EventType): void {
  selectedEventTypeId.value = type.id;
  eventForm.event_type_id = type.id;
  templateForm.event_type_id = type.id;
  if (!eventForm.registration_mode) eventForm.registration_mode = type.registrationMode;
  if (!templateForm.registration_mode) templateForm.registration_mode = type.registrationMode;
  if (!eventForm.duration_minutes) eventForm.duration_minutes = type.defaultDurationMinutes;
  if (!templateForm.duration_minutes) templateForm.duration_minutes = type.defaultDurationMinutes;
}

function addReminder(target: 'event' | 'template'): void {
  if (target === 'event') {
    eventForm.reminders.push({ minutes_before: reminderMinutes.value, channel: reminderChannel.value, required_capability: reminderCapability.value || null });
  } else {
    templateForm.reminders.push({ minutes_before: templateReminderMinutes.value, channel: templateReminderChannel.value, required_capability: templateReminderCapability.value || null });
  }
}

function removeReminder(target: 'event' | 'template', index: number): void {
  if (target === 'event') eventForm.reminders.splice(index, 1);
  else templateForm.reminders.splice(index, 1);
}

function submitEvent(): void {
  eventForm.post('/events');
}

function submitTemplate(): void {
  templateForm.post('/event-templates', { preserveScroll: true, onSuccess: () => templateForm.reset('name', 'title', 'metadata_json') });
}

function submitFromTemplate(): void {
  if (!fromTemplateForm.template_id) return;
  fromTemplateForm.post(`/event-templates/${fromTemplateForm.template_id}/events`);
}

function typeName(type: EventType): string { return t(type.nameKey); }
function typeDescription(type: EventType): string { return type.descriptionKey ? t(type.descriptionKey) : ''; }
function humanize(value: string): string { return value.replaceAll('_', ' '); }
</script>

<template>
  <Head :title="t('events.management.createTitle')" />

  <AppLayout :user="user">
    <RoomBanner
      :eyebrow="t('events.calendar.eyebrow')"
      :title="t('events.management.createTitle')"
      :subtitle="t('events.management.createDescription')"
      image="/images/kingshot/v4/event-command.svg"
      compact
    >
      <template #actions>
        <Link href="/events" class="ks-command-link">← {{ t('events.calendar.title') }}</Link>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-3">
      <StatSeal :label="t('events.management.eventTypes')" :value="formatNumber(eventTypes.length)" icon="⚔" />
      <StatSeal :label="t('events.management.templates')" :value="formatNumber(templates.length)" icon="▤" tone="teal" />
      <StatSeal :label="t('events.management.timezone')" :value="userTimezone" icon="◷" tone="stone" />
    </section>

    <div class="ks-toolbar mt-5">
      <div class="flex flex-wrap gap-2" role="tablist" :aria-label="t('events.management.createTitle')">
        <button type="button" class="ks-chip" :data-active="mode === 'event'" :aria-selected="mode === 'event'" role="tab" @click="mode = 'event'">
          {{ t('events.management.createEvent') }}
        </button>
        <button type="button" class="ks-chip" :data-active="mode === 'template'" :aria-selected="mode === 'template'" role="tab" @click="mode = 'template'">
          {{ t('events.management.createTemplate') }}
        </button>
        <button type="button" class="ks-chip" :data-active="mode === 'from-template'" :aria-selected="mode === 'from-template'" role="tab" @click="mode = 'from-template'">
          {{ t('events.management.createFromTemplate') }}
        </button>
      </div>
    </div>

    <template v-if="mode !== 'from-template'">
      <section class="mt-5" aria-labelledby="event-type-heading">
        <div class="px-1">
          <p class="ks-kicker">{{ t('events.management.eventTypes') }}</p>
          <h2 id="event-type-heading" class="ks-display mt-1 text-2xl font-semibold">{{ t('events.management.chooseEventType') }}</h2>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-2 2xl:grid-cols-4">
          <button
            v-for="type in eventTypes"
            :key="type.id"
            type="button"
            class="ks-surface group p-4 text-start transition hover:border-[var(--ks-border-strong)]"
            :class="selectedEventTypeId === type.id ? 'ring-1 ring-[var(--ks-teal-bright)]' : ''"
            @click="selectType(type)"
          >
            <div class="flex items-start gap-3">
              <EventSigil :name="typeName(type)" />
              <div class="min-w-0">
                <h3 class="font-[var(--ks-font-display)] text-lg font-semibold text-[var(--ks-gold-bright)]">{{ typeName(type) }}</h3>
                <p class="mt-1 text-xs text-[var(--ks-muted)]">{{ t(`events.scope.${type.scope}`) }} · {{ humanize(type.registrationMode) }}</p>
              </div>
            </div>
            <p v-if="typeDescription(type)" class="mt-3 line-clamp-3 text-sm leading-6 text-[var(--ks-text-secondary)]">{{ typeDescription(type) }}</p>
            <div class="mt-3 flex flex-wrap gap-1.5"><span v-for="capability in type.capabilities.slice(0, 4)" :key="capability" class="ks-chip">{{ humanize(capability) }}</span></div>
          </button>
        </div>
      </section>
    </template>

    <section v-if="mode === 'event'" class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="new-event-heading">
      <p class="ks-kicker">{{ selectedType ? typeName(selectedType) : t('events.management.createEvent') }}</p>
      <h2 id="new-event-heading" class="ks-display mt-1 text-2xl font-semibold">{{ t('events.management.createEvent') }}</h2>

      <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitEvent">
        <div v-if="selectedType?.allowCustomTitle" class="md:col-span-2">
          <label class="text-xs font-semibold" for="event-title">{{ t('events.management.title') }}</label>
          <input id="event-title" v-model="eventForm.title" class="ks-input mt-1.5" maxlength="160" />
        </div>
        <div class="md:col-span-2">
          <label class="text-xs font-semibold" for="event-description">{{ t('events.management.description') }}</label>
          <textarea id="event-description" v-model="eventForm.description" class="ks-input mt-1.5 min-h-24" maxlength="5000" />
        </div>
        <div>
          <label class="text-xs font-semibold" for="event-start">{{ t('events.management.startsAt') }}</label>
          <input id="event-start" v-model="eventForm.starts_at" class="ks-input mt-1.5" type="datetime-local" required />
        </div>
        <div>
          <label class="text-xs font-semibold" for="event-timezone">{{ t('events.management.timezone') }}</label>
          <input id="event-timezone" v-model="eventForm.timezone" class="ks-input mt-1.5" required />
        </div>
        <div>
          <label class="text-xs font-semibold" for="event-duration">{{ t('events.management.durationMinutes') }}</label>
          <input id="event-duration" v-model.number="eventForm.duration_minutes" class="ks-input mt-1.5" type="number" min="1" />
        </div>
        <div>
          <label class="text-xs font-semibold" for="event-registration">{{ t('events.management.registrationMode') }}</label>
          <select id="event-registration" v-model="eventForm.registration_mode" class="ks-input mt-1.5">
            <option v-for="registration in allowedRegistrationModes" :key="registration" :value="registration">{{ humanize(registration) }}</option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold" for="event-visibility">{{ t('events.management.visibility') }}</label>
          <select id="event-visibility" v-model="eventForm.visibility" class="ks-input mt-1.5">
            <option v-for="visibility in allowedVisibilities" :key="visibility" :value="visibility">{{ humanize(visibility) }}</option>
          </select>
        </div>
        <div v-if="selectedType?.scope === 'alliance'">
          <label class="text-xs font-semibold" for="event-alliance">{{ t('events.scope.alliance') }}</label>
          <select id="event-alliance" v-model="eventForm.alliance_id" class="ks-input mt-1.5" required>
            <option value="" disabled>{{ t('events.management.selectAlliance') }}</option>
            <option v-for="alliance in availableAlliances" :key="alliance.id" :value="alliance.id">{{ alliance.name }}</option>
          </select>
        </div>
        <div v-if="selectedType?.scope === 'kingdom'">
          <label class="text-xs font-semibold" for="event-kingdom">{{ t('events.scope.kingdom') }}</label>
          <select id="event-kingdom" v-model="eventForm.kingdom_id" class="ks-input mt-1.5" required>
            <option value="" disabled>{{ t('events.management.selectKingdom') }}</option>
            <option v-for="kingdom in availableKingdoms" :key="kingdom.id" :value="kingdom.id">{{ kingdom.name }}</option>
          </select>
        </div>

        <fieldset class="md:col-span-2 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <legend class="px-2 font-[var(--ks-font-display)] font-semibold text-[var(--ks-gold-bright)]">{{ t('events.management.recurrence') }}</legend>
          <div class="grid gap-3 sm:grid-cols-2">
            <select v-model="eventForm.recurrence_frequency" class="ks-input">
              <option value="">{{ t('events.management.noRecurrence') }}</option>
              <option value="daily">{{ t('events.management.daily') }}</option>
              <option value="weekly">{{ t('events.management.weekly') }}</option>
              <option value="monthly">{{ t('events.management.monthly') }}</option>
            </select>
            <input v-model.number="eventForm.recurrence_interval" class="ks-input" type="number" min="1" :aria-label="t('events.management.recurrenceInterval')" />
          </div>
        </fieldset>

        <fieldset class="md:col-span-2 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <legend class="px-2 font-[var(--ks-font-display)] font-semibold text-[var(--ks-gold-bright)]">{{ t('events.management.reminders') }}</legend>
          <div class="grid gap-2 sm:grid-cols-[8rem_1fr_1fr_auto]">
            <input v-model.number="reminderMinutes" class="ks-input" type="number" min="0" :aria-label="t('events.management.minutesBefore')" />
            <select v-model="reminderChannel" class="ks-input"><option v-for="channel in allowedReminderChannels" :key="channel" :value="channel">{{ humanize(channel) }}</option></select>
            <select v-model="reminderCapability" class="ks-input"><option value="">{{ t('events.management.noRequiredCapability') }}</option><option v-for="capability in allowedReminderCapabilities" :key="capability" :value="capability">{{ humanize(capability) }}</option></select>
            <AppButton type="button" variant="ghost" @click="addReminder('event')">{{ t('events.management.addReminder') }}</AppButton>
          </div>
          <div v-if="eventForm.reminders.length" class="mt-3 flex flex-wrap gap-2">
            <button v-for="(reminder, index) in eventForm.reminders" :key="`${reminder.minutes_before}-${index}`" type="button" class="ks-chip" @click="removeReminder('event', index)">
              {{ reminder.minutes_before }}m · {{ humanize(reminder.channel) }} ×
            </button>
          </div>
        </fieldset>

        <div class="md:col-span-2">
          <label class="text-xs font-semibold" for="event-metadata">{{ t('events.management.metadataJson') }}</label>
          <textarea id="event-metadata" v-model="eventForm.metadata_json" class="ks-input mt-1.5 min-h-24 font-mono text-xs" />
        </div>

        <p v-if="Object.keys(eventForm.errors).length" class="md:col-span-2 text-sm text-red-300" role="alert">{{ t('events.management.correctErrors') }}</p>
        <AppButton class="md:col-span-2 md:w-fit" type="submit" :disabled="eventForm.processing">{{ t('events.management.createEvent') }}</AppButton>
      </form>
    </section>

    <section v-if="mode === 'template'" class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="new-template-heading">
      <p class="ks-kicker">{{ t('events.management.templates') }}</p>
      <h2 id="new-template-heading" class="ks-display mt-1 text-2xl font-semibold">{{ t('events.management.createTemplate') }}</h2>
      <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitTemplate">
        <div class="md:col-span-2"><label class="text-xs font-semibold" for="template-name">{{ t('events.management.templateName') }}</label><input id="template-name" v-model="templateForm.name" class="ks-input mt-1.5" required maxlength="160" /></div>
        <div><label class="text-xs font-semibold" for="template-title">{{ t('events.management.title') }}</label><input id="template-title" v-model="templateForm.title" class="ks-input mt-1.5" maxlength="160" /></div>
        <div><label class="text-xs font-semibold" for="template-timezone">{{ t('events.management.timezone') }}</label><input id="template-timezone" v-model="templateForm.timezone" class="ks-input mt-1.5" /></div>
        <div><label class="text-xs font-semibold" for="template-duration">{{ t('events.management.durationMinutes') }}</label><input id="template-duration" v-model.number="templateForm.duration_minutes" class="ks-input mt-1.5" type="number" min="1" /></div>
        <div><label class="text-xs font-semibold" for="template-registration">{{ t('events.management.registrationMode') }}</label><select id="template-registration" v-model="templateForm.registration_mode" class="ks-input mt-1.5"><option v-for="registration in allowedRegistrationModes" :key="registration" :value="registration">{{ humanize(registration) }}</option></select></div>
        <div><label class="text-xs font-semibold" for="template-visibility">{{ t('events.management.visibility') }}</label><select id="template-visibility" v-model="templateForm.visibility" class="ks-input mt-1.5"><option v-for="visibility in allowedVisibilities" :key="visibility" :value="visibility">{{ humanize(visibility) }}</option></select></div>
        <div><label class="text-xs font-semibold" for="template-recurrence">{{ t('events.management.recurrence') }}</label><select id="template-recurrence" v-model="templateForm.recurrence_frequency" class="ks-input mt-1.5"><option value="">{{ t('events.management.noRecurrence') }}</option><option value="daily">{{ t('events.management.daily') }}</option><option value="weekly">{{ t('events.management.weekly') }}</option><option value="monthly">{{ t('events.management.monthly') }}</option></select></div>
        <div><label class="text-xs font-semibold" for="template-interval">{{ t('events.management.recurrenceInterval') }}</label><input id="template-interval" v-model.number="templateForm.recurrence_interval" class="ks-input mt-1.5" type="number" min="1" /></div>
        <div class="md:col-span-2 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"><p class="font-[var(--ks-font-display)] font-semibold text-[var(--ks-gold-bright)]">{{ t('events.management.reminders') }}</p><div class="mt-3 grid gap-2 sm:grid-cols-[8rem_1fr_1fr_auto]"><input v-model.number="templateReminderMinutes" class="ks-input" type="number" min="0" /><select v-model="templateReminderChannel" class="ks-input"><option v-for="channel in allowedReminderChannels" :key="channel" :value="channel">{{ humanize(channel) }}</option></select><select v-model="templateReminderCapability" class="ks-input"><option value="">{{ t('events.management.noRequiredCapability') }}</option><option v-for="capability in allowedReminderCapabilities" :key="capability" :value="capability">{{ humanize(capability) }}</option></select><AppButton type="button" variant="ghost" @click="addReminder('template')">{{ t('events.management.addReminder') }}</AppButton></div><div v-if="templateForm.reminders.length" class="mt-3 flex flex-wrap gap-2"><button v-for="(reminder, index) in templateForm.reminders" :key="`${reminder.minutes_before}-${index}`" type="button" class="ks-chip" @click="removeReminder('template', index)">{{ reminder.minutes_before }}m · {{ humanize(reminder.channel) }} ×</button></div></div>
        <div class="md:col-span-2"><label class="text-xs font-semibold" for="template-metadata">{{ t('events.management.metadataJson') }}</label><textarea id="template-metadata" v-model="templateForm.metadata_json" class="ks-input mt-1.5 min-h-24 font-mono text-xs" /></div>
        <AppButton class="md:col-span-2 md:w-fit" type="submit" :disabled="templateForm.processing">{{ t('events.management.createTemplate') }}</AppButton>
      </form>
    </section>

    <section v-if="mode === 'from-template'" class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="from-template-heading">
      <p class="ks-kicker">{{ t('events.management.templates') }}</p>
      <h2 id="from-template-heading" class="ks-display mt-1 text-2xl font-semibold">{{ t('events.management.createFromTemplate') }}</h2>
      <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitFromTemplate">
        <div class="md:col-span-2"><label class="text-xs font-semibold" for="from-template">{{ t('events.management.template') }}</label><select id="from-template" v-model="fromTemplateForm.template_id" class="ks-input mt-1.5" required><option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }}</option></select></div>
        <div><label class="text-xs font-semibold" for="from-template-start">{{ t('events.management.startsAt') }}</label><input id="from-template-start" v-model="fromTemplateForm.starts_at" class="ks-input mt-1.5" type="datetime-local" required /></div>
        <div v-if="selectedTemplate"><p class="ks-kicker">{{ t('events.management.templatePreview') }}</p><p class="mt-2 text-sm text-[var(--ks-text-secondary)]">{{ selectedTemplate.title || selectedTemplate.name }} · {{ selectedTemplate.timezone || userTimezone }}</p></div>
        <div><label class="text-xs font-semibold" for="from-template-alliance">{{ t('events.scope.alliance') }}</label><select id="from-template-alliance" v-model="fromTemplateForm.alliance_id" class="ks-input mt-1.5"><option value="">—</option><option v-for="alliance in availableAlliances" :key="alliance.id" :value="alliance.id">{{ alliance.name }}</option></select></div>
        <div><label class="text-xs font-semibold" for="from-template-kingdom">{{ t('events.scope.kingdom') }}</label><select id="from-template-kingdom" v-model="fromTemplateForm.kingdom_id" class="ks-input mt-1.5"><option value="">—</option><option v-for="kingdom in availableKingdoms" :key="kingdom.id" :value="kingdom.id">{{ kingdom.name }}</option></select></div>
        <AppButton class="md:col-span-2 md:w-fit" type="submit" :disabled="fromTemplateForm.processing || !fromTemplateForm.template_id">{{ t('events.management.createFromTemplate') }}</AppButton>
      </form>
    </section>
  </AppLayout>
</template>
