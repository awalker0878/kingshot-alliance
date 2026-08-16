<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

type ScopeRow = {
  id: string;
  scope: 'player' | 'alliance' | 'kingdom';
  active: boolean;
  defaultDurationMinutes: number | null;
  defaultCapacity: number | null;
  scheduleSource: string;
  recurrencePolicy: string;
  defaultRecurrenceFrequency: string;
  defaultRecurrenceInterval: number;
  minimumRepeatIntervalMinutes: number | null;
  defaultRegistrationOpensMinutesBefore: number | null;
  defaultRegistrationClosesMinutesBefore: number | null;
  defaultInstructionsKey: string | null;
  defaultSettings: Record<string, unknown>;
  capabilities: string[];
};

type EventTypeRow = {
  id: string;
  slug: string;
  nameKey: string;
  descriptionKey: string | null;
  category: string;
  iconKey: string | null;
  active: boolean;
  system: boolean;
  scopes: ScopeRow[];
};

const props = defineProps<{
  user: { name: string; email: string };
  eventTypes: EventTypeRow[];
  capabilityOptions: string[];
  scheduleSourceOptions: string[];
  recurrencePolicyOptions: string[];
  recurrenceFrequencyOptions: string[];
  status: string | null;
}>();

const { t } = useLocale();

function formFor(scope: ScopeRow) {
  return useForm({
    is_active: scope.active,
    default_duration_minutes: scope.defaultDurationMinutes,
    default_capacity: scope.defaultCapacity,
    schedule_source: scope.scheduleSource,
    recurrence_policy: scope.recurrencePolicy,
    default_recurrence_frequency: scope.defaultRecurrenceFrequency,
    default_recurrence_interval: scope.defaultRecurrenceInterval,
    minimum_repeat_interval_minutes: scope.minimumRepeatIntervalMinutes,
    default_registration_opens_minutes_before: scope.defaultRegistrationOpensMinutesBefore,
    default_registration_closes_minutes_before: scope.defaultRegistrationClosesMinutesBefore,
    default_instructions_key: scope.defaultInstructionsKey,
    default_settings_json: JSON.stringify(scope.defaultSettings ?? {}, null, 2),
    capabilities: [...scope.capabilities],
  });
}

const forms = new Map<string, ReturnType<typeof formFor>>();
for (const type of props.eventTypes) {
  for (const scope of type.scopes) {
    forms.set(scope.id, formFor(scope));
  }
}

function form(scope: ScopeRow) {
  const current = forms.get(scope.id);
  if (!current) throw new Error(`Missing form for event type scope ${scope.id}`);
  return current;
}

function save(type: EventTypeRow, scope: ScopeRow): void {
  form(scope).patch(`/platform/event-types/${type.id}/scopes/${scope.scope}`, {
    preserveScroll: true,
  });
}

function scopeLabel(scope: string): string {
  return t(`events.scope.${scope}`);
}
</script>

<template>
  <Head :title="t('events.catalogue.title')" />
  <AppLayout :user="props.user">
    <header class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('events.catalogue.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
          {{ t('events.catalogue.title') }}
        </h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('events.catalogue.description') }}
        </p>
      </div>
      <Link
        href="/platform"
        class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold"
      >
        {{ t('events.catalogue.back') }}
      </Link>
    </header>

    <p
      v-if="props.status"
      role="status"
      class="mb-6 rounded-[var(--ks-radius-md)] border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200"
    >
      {{ t('events.catalogue.saved') }}
    </p>

    <div class="space-y-5">
      <article
        v-for="type in props.eventTypes"
        :key="type.id"
        class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-5"
      >
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-xl font-semibold">{{ t(type.nameKey) }}</h2>
              <span
                class="rounded-full border border-[var(--ks-border)] px-2 py-0.5 text-xs text-[var(--ks-text-muted)]"
                >{{ type.category }}</span
              >
            </div>
            <p
              v-if="type.descriptionKey"
              class="mt-2 max-w-3xl text-sm text-[var(--ks-text-muted)]"
            >
              {{ t(type.descriptionKey) }}
            </p>
          </div>
          <code class="text-xs text-[var(--ks-text-muted)]">{{ type.slug }}</code>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
          <section
            v-for="scope in type.scopes"
            :key="scope.id"
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-4"
          >
            <div class="mb-4 flex items-center justify-between">
              <h3 class="font-semibold">{{ scopeLabel(scope.scope) }}</h3>
              <label class="flex items-center gap-2 text-sm">
                <input v-model="form(scope).is_active" type="checkbox" />
                {{ t('events.catalogue.active') }}
              </label>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <label class="text-xs font-semibold text-[var(--ks-text-muted)]">
                {{ t('events.catalogue.duration') }}
                <input
                  v-model.number="form(scope).default_duration_minutes"
                  type="number"
                  min="1"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 text-sm"
                />
              </label>
              <label class="text-xs font-semibold text-[var(--ks-text-muted)]">
                {{ t('events.catalogue.capacity') }}
                <input
                  v-model.number="form(scope).default_capacity"
                  type="number"
                  min="1"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 text-sm"
                />
              </label>
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
              <label class="text-xs font-semibold text-[var(--ks-text-muted)]">
                {{ t('events.catalogue.scheduleSource') }}
                <select
                  v-model="form(scope).schedule_source"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 text-sm"
                >
                  <option v-for="value in props.scheduleSourceOptions" :key="value" :value="value">
                    {{ value.replaceAll('_', ' ') }}
                  </option>
                </select>
              </label>
              <label class="text-xs font-semibold text-[var(--ks-text-muted)]">
                {{ t('events.catalogue.recurrencePolicy') }}
                <select
                  v-model="form(scope).recurrence_policy"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 text-sm"
                >
                  <option
                    v-for="value in props.recurrencePolicyOptions"
                    :key="value"
                    :value="value"
                  >
                    {{ value.replaceAll('_', ' ') }}
                  </option>
                </select>
              </label>
              <label class="text-xs font-semibold text-[var(--ks-text-muted)]">
                {{ t('events.catalogue.recurrenceFrequency') }}
                <select
                  v-model="form(scope).default_recurrence_frequency"
                  :disabled="form(scope).recurrence_policy === 'disabled'"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 text-sm disabled:opacity-50"
                >
                  <option
                    v-for="value in props.recurrenceFrequencyOptions"
                    :key="value"
                    :value="value"
                  >
                    {{ value }}
                  </option>
                </select>
              </label>
              <label class="text-xs font-semibold text-[var(--ks-text-muted)]">
                {{ t('events.catalogue.recurrenceInterval') }}
                <input
                  v-model.number="form(scope).default_recurrence_interval"
                  :disabled="form(scope).recurrence_policy === 'disabled'"
                  type="number"
                  min="1"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 text-sm disabled:opacity-50"
                />
              </label>
              <label class="text-xs font-semibold text-[var(--ks-text-muted)]">
                {{ t('events.catalogue.minimumRepeat') }}
                <input
                  v-model.number="form(scope).minimum_repeat_interval_minutes"
                  :disabled="form(scope).recurrence_policy === 'disabled'"
                  type="number"
                  min="1"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 text-sm disabled:opacity-50"
                />
              </label>
              <label class="text-xs font-semibold text-[var(--ks-text-muted)]">
                {{ t('events.catalogue.registrationOpens') }}
                <input
                  v-model.number="form(scope).default_registration_opens_minutes_before"
                  type="number"
                  min="0"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 text-sm"
                />
              </label>
              <label class="text-xs font-semibold text-[var(--ks-text-muted)]">
                {{ t('events.catalogue.registrationCloses') }}
                <input
                  v-model.number="form(scope).default_registration_closes_minutes_before"
                  type="number"
                  min="0"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 text-sm"
                />
              </label>
              <label class="text-xs font-semibold text-[var(--ks-text-muted)] sm:col-span-2">
                {{ t('events.catalogue.instructionsKey') }}
                <input
                  v-model="form(scope).default_instructions_key"
                  type="text"
                  class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 text-sm"
                />
              </label>
            </div>

            <label class="mt-3 block text-xs font-semibold text-[var(--ks-text-muted)]">
              {{ t('events.catalogue.defaultSettings') }}
              <textarea
                v-model="form(scope).default_settings_json"
                rows="9"
                spellcheck="false"
                class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 font-mono text-xs"
              />
            </label>

            <fieldset class="mt-4">
              <legend class="mb-2 text-xs font-semibold text-[var(--ks-text-muted)]">
                {{ t('events.catalogue.capabilities') }}
              </legend>
              <div class="grid grid-cols-2 gap-2">
                <label
                  v-for="capability in props.capabilityOptions"
                  :key="capability"
                  class="flex items-center gap-2 text-xs"
                >
                  <input v-model="form(scope).capabilities" type="checkbox" :value="capability" />
                  <span>{{ capability.replaceAll('_', ' ') }}</span>
                </label>
              </div>
            </fieldset>

            <button
              type="button"
              class="mt-4 rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2 text-sm font-bold text-slate-950 disabled:opacity-50"
              :disabled="form(scope).processing"
              @click="save(type, scope)"
            >
              {{ t('events.catalogue.save') }}
            </button>
          </section>
        </div>
      </article>
    </div>
  </AppLayout>
</template>
