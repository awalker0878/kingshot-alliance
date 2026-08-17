<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Context = {
  scope: 'player' | 'alliance' | 'kingdom';
  targetId: string;
  label: string;
  allianceId?: string;
  kingdomId?: string;
  kingdomNumber?: number;
};

type Defaults = {
  schedule_source: string;
  recurrence_policy: 'disabled' | 'fixed_interval' | 'configurable';
  recurrence_allowed: boolean;
  default_duration_minutes: number | null;
  default_capacity: number | null;
  default_recurrence_frequency: 'none' | 'daily' | 'weekly';
  default_recurrence_interval: number;
  minimum_repeat_interval_minutes: number | null;
  default_registration_opens_minutes_before: number | null;
  default_registration_closes_minutes_before: number | null;
  default_instructions_key: string | null;
  default_settings: Record<string, unknown>;
  capabilities: Record<string, unknown>;
};

type EventTypeOption = {
  id: string;
  scopeConfigurationId: string;
  slug: string;
  nameKey: string;
  descriptionKey: string | null;
  category: string;
  iconKey: string | null;
  defaults: Defaults;
};

type TemplateOption = {
  id: string;
  name: string;
  nameKey: string;
  scope: 'player' | 'alliance' | 'kingdom';
  targetId: string;
  targetLabel: string;
  timezone: string;
  recurrenceFrequency: 'none' | 'daily' | 'weekly';
  recurrenceInterval: number;
};

const props = defineProps<{
  user: { name: string; email: string };
  contexts: Context[];
  typesByScope: Record<'player' | 'alliance' | 'kingdom', EventTypeOption[]>;
  templates: TemplateOption[];
}>();

const { t } = useLocale();

const form = useForm({
  scope: props.contexts[0]?.scope ?? 'alliance',
  target_id: props.contexts[0]?.targetId ?? '',
  event_type_id: '',
  first_local_start: '',
  title: '',
  instructions: '',
  duration_minutes: null as number | null,
  capacity: null as number | null,
  registration_opens_minutes_before: null as number | null,
  registration_closes_minutes_before: null as number | null,
  recurrence_frequency: 'none' as 'none' | 'daily' | 'weekly',
  recurrence_interval: 1,
  recurrence_until_local: '',
  publish: true,
});

const selectedContext = computed(() =>
  props.contexts.find(
    (context) => context.scope === form.scope && context.targetId === form.target_id,
  ),
);
function contextLabel(context: Context): string {
  if (context.scope === 'kingdom') {
    return `${t('events.scope.kingdom')} ${context.label}`;
  }

  return context.label;
}
const typeOptions = computed(() => props.typesByScope[form.scope] ?? []);
const selectedType = computed(() =>
  typeOptions.value.find((type) => type.id === form.event_type_id),
);
const defaults = computed(() => selectedType.value?.defaults ?? null);
const contextTemplates = computed(() =>
  props.templates.filter(
    (template) => template.scope === form.scope && template.targetId === form.target_id,
  ),
);
const templateForm = useForm({
  template_id: '',
  first_local_start: '',
  recurrence_until_local: '',
  title: '',
});
const selectedTemplate = computed(() =>
  contextTemplates.value.find((template) => template.id === templateForm.template_id),
);

function applyDefaults(type: EventTypeOption | undefined): void {
  if (!type) return;
  const value = type.defaults;
  form.duration_minutes = value.default_duration_minutes;
  form.capacity = value.default_capacity;
  form.registration_opens_minutes_before = value.default_registration_opens_minutes_before;
  form.registration_closes_minutes_before = value.default_registration_closes_minutes_before;
  form.recurrence_frequency = value.default_recurrence_frequency;
  form.recurrence_interval = value.default_recurrence_interval;
  form.recurrence_until_local = '';
  form.instructions = value.default_instructions_key ? t(value.default_instructions_key) : '';
}

function chooseContext(value: string): void {
  const context = props.contexts.find(
    (candidate) => `${candidate.scope}:${candidate.targetId}` === value,
  );
  if (!context) return;
  form.scope = context.scope;
  form.target_id = context.targetId;
}

watch(
  () => [form.scope, form.target_id] as const,
  () => {
    const first = typeOptions.value[0];
    form.event_type_id = first?.id ?? '';
    applyDefaults(first);
    templateForm.template_id = contextTemplates.value[0]?.id ?? '';
    templateForm.clearErrors();
  },
  { immediate: true },
);

watch(
  () => form.event_type_id,
  (id) => applyDefaults(typeOptions.value.find((type) => type.id === id)),
);

function submit(): void {
  form.post('/events');
}

function scheduleTemplate(): void {
  if (!selectedTemplate.value) return;
  templateForm.post(`/event-templates/${selectedTemplate.value.id}/events`);
}
</script>

<template>
  <Head :title="t('events.create.title')" />
  <AppLayout :user="props.user">
    <div class="mx-auto max-w-6xl">
      <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
            {{ t('events.create.eyebrow') }}
          </p>
          <h1 class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
            {{ t('events.create.title') }}
          </h1>
          <p class="mt-3 max-w-3xl text-sm leading-6 text-[var(--ks-text-muted)]">
            {{ t('events.create.description') }}
          </p>
        </div>
        <Link
          href="/events"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold"
          >{{ t('events.create.back') }}</Link
        >
      </header>

      <div
        v-if="props.contexts.length === 0"
        class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-8 text-center"
      >
        <p class="font-semibold">{{ t('events.create.noContexts') }}</p>
      </div>

      <div v-else class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <form
          class="space-y-5 rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-5 sm:p-6"
          @submit.prevent="submit"
        >
          <section class="space-y-5">
            <label v-if="props.contexts.length > 1" class="block text-sm font-semibold">
              {{ t('events.create.context') }}
              <select
                :value="`${form.scope}:${form.target_id}`"
                class="mt-2 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2"
                @change="chooseContext(($event.target as HTMLSelectElement).value)"
              >
                <option
                  v-for="context in props.contexts"
                  :key="`${context.scope}:${context.targetId}`"
                  :value="`${context.scope}:${context.targetId}`"
                >
                  {{ contextLabel(context) }} · {{ t(`events.scope.${context.scope}`) }}
                </option>
              </select>
            </label>

            <div
              v-else-if="selectedContext"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-4"
            >
              <p class="text-xs font-bold tracking-[0.14em] text-[var(--ks-text-muted)] uppercase">
                {{ t(`events.scope.${selectedContext.scope}`) }}
              </p>
              <p class="mt-1 font-semibold">{{ contextLabel(selectedContext) }}</p>
            </div>

            <label class="block text-sm font-semibold">
              {{ t('events.create.eventType') }}
              <select
                v-model="form.event_type_id"
                class="mt-2 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2"
              >
                <option v-for="type in typeOptions" :key="type.id" :value="type.id">
                  {{ t(type.nameKey) }}
                </option>
              </select>
            </label>

            <div
              v-if="selectedType?.descriptionKey"
              class="text-sm leading-6 text-[var(--ks-text-muted)]"
            >
              {{ t(selectedType.descriptionKey) }}
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <label class="text-sm font-semibold"
                >{{ t('events.create.start')
                }}<input
                  v-model="form.first_local_start"
                  required
                  type="datetime-local"
                  class="mt-2 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2"
              /></label>
              <label class="text-sm font-semibold"
                >{{ t('events.create.duration')
                }}<input
                  v-model.number="form.duration_minutes"
                  required
                  type="number"
                  min="1"
                  max="10080"
                  class="mt-2 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2"
              /></label>
              <label class="text-sm font-semibold"
                >{{ t('events.create.capacity')
                }}<input
                  v-model.number="form.capacity"
                  type="number"
                  min="1"
                  class="mt-2 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2"
              /></label>
              <label class="text-sm font-semibold"
                >{{ t('events.create.titleOverride')
                }}<input
                  v-model="form.title"
                  type="text"
                  maxlength="160"
                  class="mt-2 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2"
              /></label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <label class="text-sm font-semibold"
                >{{ t('events.create.registrationOpens')
                }}<input
                  v-model.number="form.registration_opens_minutes_before"
                  type="number"
                  min="0"
                  class="mt-2 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2"
              /></label>
              <label class="text-sm font-semibold"
                >{{ t('events.create.registrationCloses')
                }}<input
                  v-model.number="form.registration_closes_minutes_before"
                  type="number"
                  min="0"
                  class="mt-2 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2"
              /></label>
            </div>

            <div
              v-if="defaults"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-4"
            >
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p
                    class="text-xs font-bold tracking-[0.14em] text-[var(--ks-text-muted)] uppercase"
                  >
                    {{ t('events.create.schedulePolicy') }}
                  </p>
                  <p class="mt-1 text-sm font-semibold">
                    {{ t(`events.scheduleSources.${defaults.schedule_source}`) }} ·
                    {{ t(`events.recurrencePolicies.${defaults.recurrence_policy}`) }}
                  </p>
                </div>
                <span
                  v-if="!defaults.recurrence_allowed"
                  class="rounded-full border border-[var(--ks-border)] px-2 py-1 text-xs text-[var(--ks-text-muted)]"
                  >{{ t('events.create.recurrenceDisabled') }}</span
                >
              </div>

              <div v-if="defaults.recurrence_allowed" class="mt-4 grid gap-3 sm:grid-cols-3">
                <label class="text-xs font-semibold"
                  >{{ t('events.create.recurrence') }}
                  <select
                    v-model="form.recurrence_frequency"
                    :disabled="defaults.recurrence_policy === 'fixed_interval'"
                    class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 disabled:opacity-60"
                  >
                    <option value="none">{{ t('events.recurrenceFrequencies.none') }}</option>
                    <option value="daily">{{ t('events.recurrenceFrequencies.daily') }}</option>
                    <option value="weekly">{{ t('events.recurrenceFrequencies.weekly') }}</option>
                  </select>
                </label>
                <label class="text-xs font-semibold"
                  >{{ t('events.create.interval')
                  }}<input
                    v-model.number="form.recurrence_interval"
                    :disabled="defaults.recurrence_policy === 'fixed_interval'"
                    type="number"
                    min="1"
                    class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 disabled:opacity-60"
                /></label>
                <label class="text-xs font-semibold"
                  >{{ t('events.create.recurrenceUntil')
                  }}<input
                    v-model="form.recurrence_until_local"
                    type="datetime-local"
                    class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2"
                /></label>
              </div>
            </div>

            <label class="block text-sm font-semibold"
              >{{ t('events.create.instructions')
              }}<textarea
                v-model="form.instructions"
                rows="7"
                maxlength="10000"
                class="mt-2 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2 leading-6"
              />
            </label>

            <div
              v-if="Object.keys(defaults?.default_settings ?? {}).length"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-4"
            >
              <p class="text-xs font-bold tracking-[0.14em] text-[var(--ks-text-muted)] uppercase">
                {{ t('events.create.gameDefaults') }}
              </p>
              <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                <div
                  v-for="(value, key) in defaults?.default_settings"
                  :key="key"
                  class="flex justify-between gap-3 border-b border-[var(--ks-border)] py-1.5 text-xs"
                >
                  <dt class="text-[var(--ks-text-muted)]">
                    {{ String(key).replaceAll('_', ' ') }}
                  </dt>
                  <dd class="text-right font-semibold">
                    {{ Array.isArray(value) ? value.join(', ') : String(value) }}
                  </dd>
                </div>
              </dl>
            </div>

            <div
              v-if="Object.keys(form.errors).length"
              class="rounded border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200"
            >
              {{ Object.values(form.errors)[0] }}
            </div>

            <button
              type="submit"
              :disabled="form.processing || !form.event_type_id || !form.first_local_start"
              class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-2.5 font-bold text-[var(--ks-ink)] disabled:opacity-50"
            >
              {{ t('events.create.submit') }}
            </button>
          </section>
        </form>

        <aside
          v-if="selectedType"
          class="h-fit rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-5 lg:sticky lg:top-24"
        >
          <p class="text-xs font-bold tracking-[0.14em] text-[var(--ks-text-muted)] uppercase">
            {{ t('events.create.modules') }}
          </p>
          <h2 class="mt-2 text-lg font-semibold">{{ t(selectedType.nameKey) }}</h2>
          <div class="mt-4 flex flex-wrap gap-2">
            <span
              v-for="capability in Object.keys(selectedType.defaults.capabilities)"
              :key="capability"
              class="rounded-full border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-2.5 py-1 text-xs"
              >{{ t(`events.capabilities.${capability}`) }}</span
            >
          </div>

          <div class="mt-6 border-t border-[var(--ks-border)] pt-5">
            <h2 class="font-semibold">{{ t('events.create.templates') }}</h2>
            <p class="mt-1 text-xs leading-5 text-[var(--ks-text-muted)]">
              {{ t('events.create.templateHelp') }}
            </p>

            <form
              v-if="contextTemplates.length"
              class="mt-4 space-y-3"
              @submit.prevent="scheduleTemplate"
            >
              <label class="block text-xs font-semibold">
                {{ t('events.create.template') }}
                <select
                  v-model="templateForm.template_id"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2"
                >
                  <option
                    v-for="template in contextTemplates"
                    :key="template.id"
                    :value="template.id"
                  >
                    {{ template.name }} · {{ t(template.nameKey) }}
                  </option>
                </select>
              </label>
              <label class="block text-xs font-semibold">
                {{ t('events.create.templateStart') }}
                <input
                  v-model="templateForm.first_local_start"
                  required
                  type="datetime-local"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2"
                />
              </label>
              <label class="block text-xs font-semibold">
                {{ t('events.create.templateTitle') }}
                <input
                  v-model="templateForm.title"
                  type="text"
                  maxlength="160"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2"
                />
              </label>
              <label
                v-if="selectedTemplate?.recurrenceFrequency !== 'none'"
                class="block text-xs font-semibold"
              >
                {{ t('events.create.recurrenceUntil') }}
                <input
                  v-model="templateForm.recurrence_until_local"
                  type="datetime-local"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2"
                />
              </label>
              <p
                v-if="Object.keys(templateForm.errors).length"
                class="rounded border border-red-500/30 bg-red-500/10 p-3 text-xs text-red-200"
              >
                {{ Object.values(templateForm.errors)[0] }}
              </p>
              <button
                type="submit"
                :disabled="
                  templateForm.processing ||
                  !templateForm.template_id ||
                  !templateForm.first_local_start
                "
                class="w-full rounded bg-[var(--ks-blue-soft)] px-4 py-2 text-sm font-semibold text-[var(--ks-blue-strong)] disabled:opacity-50"
              >
                {{ t('events.create.scheduleTemplate') }}
              </button>
            </form>
            <p v-else class="mt-3 text-xs text-[var(--ks-text-muted)]">
              {{ t('events.create.noTemplates') }}
            </p>
          </div>
        </aside>
      </div>
    </div>
  </AppLayout>
</template>
