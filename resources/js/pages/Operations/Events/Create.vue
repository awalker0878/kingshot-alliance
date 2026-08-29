<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import EventSigil from '@/components/game/EventSigil.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Scope = 'player' | 'alliance' | 'kingdom';
type EventProfile = {
  verification_state: 'candidate' | 'verified' | 'conflicting' | 'unsupported';
  profile_state: 'disabled' | 'enabled';
  profile_enabled: boolean;
  workflow_dimensions: string[];
};
type CreationContext = {
  scope: Scope;
  targetId: string;
  label: string;
};
type EventTypeRow = {
  id: string;
  scopeConfigurationId: string;
  slug: string;
  nameKey: string;
  descriptionKey: string | null;
  category: string;
  iconKey: string | null;
  defaults: { profile: EventProfile };
};
type TemplateRow = {
  id: string;
  name: string;
  nameKey: string;
  scope: Scope;
  targetId: string;
  targetLabel: string;
  timezone: string;
  recurrenceFrequency: string;
  recurrenceInterval: number;
};

const props = defineProps<{
  user: { name: string; email: string };
  contexts: CreationContext[];
  typesByScope: Record<Scope, EventTypeRow[]>;
  templates: TemplateRow[];
}>();

const { t, formatNumber } = useLocale();
const mode = ref<'event' | 'template' | 'from-template'>('event');
const selectedContext = ref(props.contexts[0] ?? null);
const availableTypes = computed(() =>
  selectedContext.value ? (props.typesByScope[selectedContext.value.scope] ?? []) : [],
);
const selectedTypeId = ref(availableTypes.value[0]?.id ?? '');
const selectedType = computed(
  () => availableTypes.value.find((type) => type.id === selectedTypeId.value) ?? null,
);

const eventForm = useForm({
  scope: selectedContext.value?.scope ?? ('player' as Scope),
  target_id: selectedContext.value?.targetId ?? '',
  event_type_id: selectedTypeId.value,
  first_local_start: '',
  title: '',
  instructions: '',
  duration_minutes: 60 as number | null,
  capacity: null as number | null,
  registration_opens_minutes_before: null as number | null,
  registration_closes_minutes_before: null as number | null,
  recurrence_frequency: 'none',
  recurrence_interval: 1,
  recurrence_until_local: '',
  publish: true,
});

const templateForm = useForm({
  scope: selectedContext.value?.scope ?? ('player' as Scope),
  target_id: selectedContext.value?.targetId ?? '',
  event_type_id: selectedTypeId.value,
  name: '',
  instructions: '',
  duration_minutes: 60 as number | null,
  capacity: null as number | null,
  registration_opens_minutes_before: null as number | null,
  registration_closes_minutes_before: null as number | null,
  recurrence_frequency: 'none',
  recurrence_interval: 1,
});

const fromTemplateForm = useForm({
  first_local_start: '',
  recurrence_until_local: '',
  title: '',
});
const selectedTemplateId = ref(props.templates[0]?.id ?? '');

watch(selectedContext, (context) => {
  if (!context) return;
  eventForm.scope = context.scope;
  eventForm.target_id = context.targetId;
  templateForm.scope = context.scope;
  templateForm.target_id = context.targetId;
  selectedTypeId.value = (props.typesByScope[context.scope] ?? [])[0]?.id ?? '';
});

watch(selectedTypeId, (id) => {
  eventForm.event_type_id = id;
  templateForm.event_type_id = id;
});

function humanize(value: string): string {
  return value.replaceAll('_', ' ');
}

function submitEvent(): void {
  eventForm.post('/events');
}

function submitTemplate(): void {
  templateForm.post('/event-templates', { preserveScroll: true });
}

function submitFromTemplate(): void {
  if (!selectedTemplateId.value) return;
  fromTemplateForm.post(`/event-templates/${selectedTemplateId.value}/events`);
}
</script>

<template>
  <Head :title="t('events.create.title')" />

  <AppLayout :user="user">
    <RoomBanner
      :eyebrow="t('events.calendar.eyebrow')"
      :title="t('events.create.title')"
      :subtitle="t('events.create.description')"
      image="/images/kingshot/v4/event-command.svg"
      compact
    >
      <template #actions>
        <Link href="/events" class="ks-command-link">← {{ t('events.calendar.title') }}</Link>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-3">
      <StatSeal
        :label="t('events.create.eventType')"
        :value="formatNumber(availableTypes.length)"
        icon="⚔"
      />
      <StatSeal
        :label="t('events.create.templates')"
        :value="formatNumber(templates.length)"
        icon="▤"
        tone="teal"
      />
      <StatSeal
        :label="t('events.create.context')"
        :value="selectedContext?.label ?? '—'"
        icon="◷"
        tone="stone"
      />
    </section>

    <div class="ks-toolbar mt-5 flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap gap-2" role="tablist" :aria-label="t('events.create.title')">
        <button
          type="button"
          class="ks-chip"
          :data-active="mode === 'event'"
          @click="mode = 'event'"
        >
          {{ t('events.create.submit') }}
        </button>
        <button
          type="button"
          class="ks-chip"
          :data-active="mode === 'template'"
          @click="mode = 'template'"
        >
          {{ t('events.manage.templateTitle') }}
        </button>
        <button
          type="button"
          class="ks-chip"
          :data-active="mode === 'from-template'"
          @click="mode = 'from-template'"
        >
          {{ t('events.create.scheduleTemplate') }}
        </button>
      </div>

      <label v-if="mode !== 'from-template'" class="min-w-64 text-xs font-semibold">
        {{ t('events.create.context') }}
        <select v-model="selectedContext" class="ks-input mt-1.5">
          <option
            v-for="context in contexts"
            :key="`${context.scope}:${context.targetId}`"
            :value="context"
          >
            {{ t(`events.scope.${context.scope}`) }} · {{ context.label }}
          </option>
        </select>
      </label>
    </div>

    <template v-if="mode !== 'from-template'">
      <section class="mt-5" aria-labelledby="event-type-heading">
        <p class="ks-kicker">{{ t('events.create.eventType') }}</p>
        <h2 id="event-type-heading" class="ks-display mt-1 text-2xl font-semibold">
          {{ t('events.create.eventType') }}
        </h2>
        <div class="mt-4 grid gap-3 md:grid-cols-2 2xl:grid-cols-4">
          <button
            v-for="type in availableTypes"
            :key="type.id"
            type="button"
            class="ks-surface p-4 text-start transition hover:border-[var(--ks-border-strong)]"
            :class="selectedTypeId === type.id ? 'ring-1 ring-[var(--ks-teal-bright)]' : ''"
            @click="selectedTypeId = type.id"
          >
            <div class="flex items-start gap-3">
              <EventSigil :name="t(type.nameKey)" />
              <div class="min-w-0">
                <h3
                  class="text-lg font-[var(--ks-font-display)] font-semibold text-[var(--ks-gold-bright)]"
                >
                  {{ t(type.nameKey) }}
                </h3>
                <p class="mt-1 text-xs text-[var(--ks-muted)]">
                  {{ humanize(type.defaults.profile.verification_state) }} ·
                  {{ humanize(type.defaults.profile.profile_state) }}
                </p>
              </div>
            </div>
            <p
              v-if="type.descriptionKey"
              class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]"
            >
              {{ t(type.descriptionKey) }}
            </p>
            <div
              v-if="type.defaults.profile.workflow_dimensions.length"
              class="mt-3 flex flex-wrap gap-1.5"
            >
              <span
                v-for="dimension in type.defaults.profile.workflow_dimensions"
                :key="dimension"
                class="ks-chip"
              >
                {{ humanize(dimension) }}
              </span>
            </div>
            <p v-else class="mt-3 text-xs text-[var(--ks-muted)]">
              {{ humanize(type.defaults.profile.profile_state) }}
            </p>
          </button>
        </div>
      </section>
    </template>

    <section v-if="mode === 'event'" class="ks-surface mt-5 p-5 sm:p-6">
      <p class="ks-kicker">
        {{ selectedType ? t(selectedType.nameKey) : t('events.create.submit') }}
      </p>
      <h2 class="ks-display mt-1 text-2xl font-semibold">{{ t('events.create.submit') }}</h2>
      <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitEvent">
        <label class="text-sm md:col-span-2">
          {{ t('events.create.titleOverride') }}
          <input v-model="eventForm.title" class="ks-input mt-1.5" maxlength="160" />
        </label>
        <label class="text-sm md:col-span-2">
          {{ t('events.show.instructions') }}
          <textarea
            v-model="eventForm.instructions"
            class="ks-input mt-1.5 min-h-24"
            maxlength="10000"
          />
        </label>
        <label class="text-sm">
          {{ t('events.create.start') }}
          <input
            v-model="eventForm.first_local_start"
            class="ks-input mt-1.5"
            type="datetime-local"
            required
          />
        </label>
        <label class="text-sm">
          {{ t('events.create.duration') }}
          <input
            v-model.number="eventForm.duration_minutes"
            class="ks-input mt-1.5"
            type="number"
            min="1"
            required
          />
        </label>
        <label class="text-sm">
          {{ t('events.show.capacity') }}
          <input
            v-model.number="eventForm.capacity"
            class="ks-input mt-1.5"
            type="number"
            min="1"
          />
        </label>
        <label class="text-sm">
          {{ t('events.create.recurrence') }}
          <select v-model="eventForm.recurrence_frequency" class="ks-input mt-1.5">
            <option value="none">{{ t('events.recurrenceFrequencies.none') }}</option>
            <option value="daily">{{ t('events.recurrenceFrequencies.daily') }}</option>
            <option value="weekly">{{ t('events.recurrenceFrequencies.weekly') }}</option>
          </select>
        </label>
        <label class="text-sm">
          {{ t('events.create.interval') }}
          <input
            v-model.number="eventForm.recurrence_interval"
            class="ks-input mt-1.5"
            type="number"
            min="1"
            max="52"
          />
        </label>
        <label class="text-sm">
          {{ t('events.create.recurrenceUntil') }}
          <input
            v-model="eventForm.recurrence_until_local"
            class="ks-input mt-1.5"
            type="datetime-local"
          />
        </label>
        <label class="flex items-center gap-2 text-sm md:col-span-2">
          <input v-model="eventForm.publish" type="checkbox" />
          {{ t('events.eventStatuses.published') }}
        </label>
        <p
          v-if="Object.keys(eventForm.errors).length"
          class="text-sm text-red-200 md:col-span-2"
          role="alert"
        >
          {{ Object.values(eventForm.errors)[0] }}
        </p>
        <AppButton type="submit" :disabled="eventForm.processing || !selectedTypeId">
          {{ t('events.create.submit') }}
        </AppButton>
      </form>
    </section>

    <section v-else-if="mode === 'template'" class="ks-surface mt-5 p-5 sm:p-6">
      <h2 class="ks-display text-2xl font-semibold">{{ t('events.manage.templateTitle') }}</h2>
      <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitTemplate">
        <label class="text-sm md:col-span-2">
          {{ t('events.manage.templateName') }}
          <input v-model="templateForm.name" class="ks-input mt-1.5" maxlength="120" required />
        </label>
        <label class="text-sm md:col-span-2">
          {{ t('events.show.instructions') }}
          <textarea
            v-model="templateForm.instructions"
            class="ks-input mt-1.5 min-h-24"
            maxlength="10000"
          />
        </label>
        <label class="text-sm">
          {{ t('events.create.duration') }}
          <input
            v-model.number="templateForm.duration_minutes"
            class="ks-input mt-1.5"
            type="number"
            min="1"
            required
          />
        </label>
        <label class="text-sm">
          {{ t('events.show.capacity') }}
          <input
            v-model.number="templateForm.capacity"
            class="ks-input mt-1.5"
            type="number"
            min="1"
          />
        </label>
        <p
          v-if="Object.keys(templateForm.errors).length"
          class="text-sm text-red-200 md:col-span-2"
          role="alert"
        >
          {{ Object.values(templateForm.errors)[0] }}
        </p>
        <AppButton type="submit" :disabled="templateForm.processing || !selectedTypeId">
          {{ t('events.manage.templateSave') }}
        </AppButton>
      </form>
    </section>

    <section v-else class="ks-surface mt-5 p-5 sm:p-6">
      <h2 class="ks-display text-2xl font-semibold">{{ t('events.create.scheduleTemplate') }}</h2>
      <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitFromTemplate">
        <label class="text-sm md:col-span-2">
          {{ t('events.create.templates') }}
          <select v-model="selectedTemplateId" class="ks-input mt-1.5" required>
            <option v-for="template in templates" :key="template.id" :value="template.id">
              {{ template.name }} · {{ template.targetLabel }}
            </option>
          </select>
        </label>
        <label class="text-sm">
          {{ t('events.create.templateStart') }}
          <input
            v-model="fromTemplateForm.first_local_start"
            class="ks-input mt-1.5"
            type="datetime-local"
            required
          />
        </label>
        <label class="text-sm">
          {{ t('events.create.recurrenceUntil') }}
          <input
            v-model="fromTemplateForm.recurrence_until_local"
            class="ks-input mt-1.5"
            type="datetime-local"
          />
        </label>
        <label class="text-sm md:col-span-2">
          {{ t('events.create.templateTitle') }}
          <input v-model="fromTemplateForm.title" class="ks-input mt-1.5" maxlength="160" />
        </label>
        <p
          v-if="Object.keys(fromTemplateForm.errors).length"
          class="text-sm text-red-200 md:col-span-2"
          role="alert"
        >
          {{ Object.values(fromTemplateForm.errors)[0] }}
        </p>
        <AppButton type="submit" :disabled="fromTemplateForm.processing || !selectedTemplateId">
          {{ t('events.create.scheduleTemplate') }}
        </AppButton>
      </form>
    </section>
  </AppLayout>
</template>
