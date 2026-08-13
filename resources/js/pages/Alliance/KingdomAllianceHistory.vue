<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

type Observation = {
  id?: string;
  observedName: string;
  observedTag: string | null;
  power: string | null;
  memberCount: number | null;
  capturedAt: string;
  source: string;
  actorName?: string | null;
  correctsObservationId?: string | null;
  invalidatedAt?: string | null;
  invalidatedByName?: string | null;
  invalidationReason?: string | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: {
    id: string;
    name: string;
    kingdom: string | null;
  };
  canManage: boolean;
  tracking: {
    id?: string;
    name: string;
    tag: string | null;
    state: string;
    kingdom: string;
    contextCurrent: boolean;
  };
  freshness: 'current' | 'stale' | 'missing';
  freshDays: number;
  latest: Observation | null;
  history: Observation[];
}>();

function localDateTimeNow(): string {
  const now = new Date();
  return new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
}

const { t, formatDate: localeDate, formatNumber } = useLocale();

const recordForm = useForm({
  observed_name: props.tracking.name,
  observed_tag: props.tracking.tag ?? '',
  power: '',
  member_count: '',
  captured_at: localDateTimeNow(),
  corrects_observation_id: '',
  correction_reason: '',
});

const invalidateTargetId = ref<string | null>(null);
const invalidateForm = useForm({ reason: '' });

const recordDomainError = computed(
  () => (recordForm.errors as Record<string, string | undefined>).observation,
);

const canMutate = computed(
  () => props.canManage && props.tracking.state === 'active' && props.tracking.contextCurrent,
);

function formatDate(value: string): string {
  return localeDate(value, { dateStyle: 'medium', timeStyle: 'short' });
}

function recordObservation(): void {
  if (!props.tracking.id) return;

  recordForm
    .transform((data) => ({
      ...data,
      captured_at: new Date(data.captured_at).toISOString(),
    }))
    .post(`/alliance/kingdom-alliances/${props.tracking.id}/observations`, {
      preserveScroll: true,
      onSuccess: () => {
        recordForm.power = '';
        recordForm.member_count = '';
        recordForm.captured_at = localDateTimeNow();
        recordForm.corrects_observation_id = '';
        recordForm.correction_reason = '';
      },
    });
}

function beginCorrection(observation: Observation): void {
  if (!observation.id) return;

  recordForm.clearErrors();
  recordForm.observed_name = observation.observedName;
  recordForm.observed_tag = observation.observedTag ?? '';
  recordForm.power = observation.power ?? '';
  recordForm.member_count = observation.memberCount === null ? '' : String(observation.memberCount);
  recordForm.captured_at = localDateTimeNow();
  recordForm.corrects_observation_id = observation.id;
  recordForm.correction_reason = '';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelCorrection(): void {
  recordForm.corrects_observation_id = '';
  recordForm.correction_reason = '';
}

function beginInvalidation(observation: Observation): void {
  if (!observation.id) return;
  invalidateTargetId.value = observation.id;
  invalidateForm.reset();
  invalidateForm.clearErrors();
}

function cancelInvalidation(): void {
  invalidateTargetId.value = null;
  invalidateForm.reset();
  invalidateForm.clearErrors();
}

function invalidateObservation(): void {
  if (!props.tracking.id || !invalidateTargetId.value) return;

  invalidateForm.post(
    `/alliance/kingdom-alliances/${props.tracking.id}/observations/${invalidateTargetId.value}/invalidate`,
    {
      preserveScroll: true,
      onSuccess: () => cancelInvalidation(),
    },
  );
}
</script>

<template>
  <Head
    :title="`${t('kingdomP7B.historyTitle', { alliance: tracking.name })} · ${alliance.name}`"
  />

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('kingdomP7B.eyebrow') }}
        </p>
        <h1 class="mt-2 text-3xl font-bold">
          {{ t('kingdomP7B.historyTitle', { alliance: tracking.name }) }}
        </h1>
        <p class="mt-2 max-w-3xl text-sm text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7B.historySubtitle', { kingdom: tracking.kingdom }) }}
        </p>
      </div>
      <Link
        class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200"
        href="/alliance/kingdom-alliances"
      >
        Back to tracked alliances
      </Link>
    </header>

    <section class="mt-8 grid gap-4 md:grid-cols-3">
      <div class="ks-surface p-5">
        <p class="text-xs font-semibold tracking-wide text-[var(--ks-text-secondary)] uppercase">
          {{ t('kingdomP7B.freshness') }}
        </p>
        <p class="mt-2 text-xl font-semibold">{{ freshness }}</p>
        <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
          {{ t('kingdomP7B.currentWithinDays', { days: freshDays }) }}
        </p>
      </div>
      <div class="ks-surface p-5">
        <p class="text-xs font-semibold tracking-wide text-[var(--ks-text-secondary)] uppercase">
          {{ t('kingdomP7B.latestPower') }}
        </p>
        <p class="mt-2 text-xl font-semibold">{{ latest?.power ?? t('kingdomP7B.missing') }}</p>
        <p class="mt-1 text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7B.missingNotZero') }}</p>
      </div>
      <div class="ks-surface p-5">
        <p class="text-xs font-semibold tracking-wide text-[var(--ks-text-secondary)] uppercase">
          {{ t('kingdomP7B.latestMembers') }}
        </p>
        <p class="mt-2 text-xl font-semibold">
          {{
            latest?.memberCount === null || latest?.memberCount === undefined
              ? t('kingdomP7B.missing')
              : formatNumber(latest.memberCount)
          }}
        </p>
        <p v-if="latest" class="mt-1 text-xs text-[var(--ks-text-muted)]">
          {{ t('kingdomP7B.captured', { date: formatDate(latest.capturedAt) }) }}
        </p>
      </div>
    </section>

    <section v-if="canManage" class="ks-surface mt-8 p-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-xl font-semibold">
            {{
              recordForm.corrects_observation_id
                ? t('kingdomP7B.recordCorrection')
                : t('kingdomP7B.recordObservation')
            }}
          </h2>
          <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">
            {{ t('kingdomP7B.observationHelp') }}
          </p>
        </div>
        <button
          v-if="recordForm.corrects_observation_id"
          class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold"
          type="button"
          @click="cancelCorrection"
        >
          {{ t('kingdomP7B.cancelCorrection') }}
        </button>
      </div>

      <p
        v-if="!tracking.contextCurrent"
        class="mt-4 rounded-xl border border-amber-900 bg-amber-950/40 p-4 text-sm text-amber-200"
      >
        This tracking record belongs to historical Kingdom context. Observation mutations are
        blocked; history remains readable.
      </p>

      <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="recordObservation">
        <div>
          <label class="block text-sm font-medium" for="observation-name">{{
            t('kingdomP7B.observedName')
          }}</label>
          <input
            id="observation-name"
            v-model="recordForm.observed_name"
            class="ks-input mt-2 w-full"
            maxlength="160"
            required
            type="text"
          />
          <p v-if="recordForm.errors.observed_name" class="mt-1 text-sm text-rose-300">
            {{ recordForm.errors.observed_name }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="observation-tag">{{
            t('kingdomP7B.observedTag')
          }}</label>
          <input
            id="observation-tag"
            v-model="recordForm.observed_tag"
            class="ks-input mt-2 w-full"
            maxlength="32"
            type="text"
          />
        </div>

        <div>
          <label class="block text-sm font-medium" for="observation-power">{{
            t('kingdomP7B.power')
          }}</label>
          <input
            id="observation-power"
            v-model="recordForm.power"
            class="ks-input mt-2 w-full"
            inputmode="numeric"
            pattern="[0-9]*"
            placeholder="Leave blank if unknown"
            type="text"
          />
          <p v-if="recordForm.errors.power" class="mt-1 text-sm text-rose-300">
            {{ recordForm.errors.power }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="observation-members">{{
            t('kingdomP7B.members')
          }}</label>
          <input
            id="observation-members"
            v-model="recordForm.member_count"
            class="ks-input mt-2 w-full"
            min="0"
            placeholder="Leave blank if unknown"
            type="number"
          />
          <p v-if="recordForm.errors.member_count" class="mt-1 text-sm text-rose-300">
            {{ recordForm.errors.member_count }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="observation-captured">{{
            t('kingdomP7B.capturedAt')
          }}</label>
          <input
            id="observation-captured"
            v-model="recordForm.captured_at"
            class="ks-input mt-2 w-full"
            required
            type="datetime-local"
          />
          <p v-if="recordForm.errors.captured_at" class="mt-1 text-sm text-rose-300">
            {{ recordForm.errors.captured_at }}
          </p>
        </div>

        <div v-if="recordForm.corrects_observation_id">
          <label class="block text-sm font-medium" for="correction-reason">
            {{ t('kingdomP7B.correctionReason') }}
          </label>
          <textarea
            id="correction-reason"
            v-model="recordForm.correction_reason"
            class="ks-input mt-2 min-h-24 w-full"
            maxlength="5000"
            placeholder="Manager-private context"
          />
          <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
            {{ t('kingdomP7B.correctionPrivate') }}
          </p>
        </div>

        <div class="md:col-span-2">
          <p v-if="recordDomainError" class="mb-3 text-sm text-rose-300">
            {{ recordDomainError }}
          </p>
          <button
            class="rounded-lg bg-[var(--ks-blue)] px-4 py-2 font-semibold text-white disabled:opacity-60"
            :disabled="recordForm.processing || !canMutate"
            type="submit"
          >
            {{
              recordForm.corrects_observation_id
                ? t('kingdomP7B.recordCorrection')
                : t('kingdomP7B.recordObservation')
            }}
          </button>
        </div>
      </form>
    </section>

    <section class="ks-surface mt-8 p-6">
      <div>
        <h2 class="text-xl font-semibold">{{ t('kingdomP7B.historicalObservations') }}</h2>
        <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7B.historyHelp') }}
        </p>
      </div>

      <div v-if="history.length" class="mt-6 grid gap-3 lg:hidden">
        <article
          v-for="observation in history"
          :key="observation.id ?? observation.capturedAt"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/10 p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="font-semibold">{{ observation.observedName }}</p>
              <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                {{ observation.observedTag ?? '—' }}
              </p>
            </div>
            <span
              v-if="observation.invalidatedAt"
              class="rounded-full bg-rose-950 px-2 py-1 text-xs font-semibold text-rose-300"
              >{{ t('kingdomP7B.invalidated') }}</span
            >
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7B.capturedAt') }}</dt>
              <dd>{{ formatDate(observation.capturedAt) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7B.power') }}</dt>
              <dd>{{ observation.power ?? t('kingdomP7B.missing') }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7B.members') }}</dt>
              <dd>{{ observation.memberCount ?? t('kingdomP7B.missing') }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7B.source') }}</dt>
              <dd>{{ observation.source }}</dd>
            </div>
          </dl>
          <div v-if="canManage && !observation.invalidatedAt && canMutate" class="mt-3 flex gap-2">
            <button
              class="rounded-lg border border-[var(--ks-border)] px-3 py-2 text-xs font-semibold"
              type="button"
              @click="beginCorrection(observation)"
            >
              {{ t('kingdomP7B.correct') }}</button
            ><button
              class="rounded-lg border border-rose-900 px-3 py-2 text-xs font-semibold text-rose-300"
              type="button"
              @click="beginInvalidation(observation)"
            >
              {{ t('kingdomP7B.invalidate') }}
            </button>
          </div>
        </article>
      </div>

      <div v-if="history.length" class="mt-6 hidden overflow-x-auto lg:block">
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <thead class="text-xs tracking-wide text-[var(--ks-text-secondary)] uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7B.capturedAt') }}</th>
              <th class="px-3 py-3 font-semibold">Observed identity</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7B.power') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7B.members') }}</th>
              <th v-if="canManage" class="px-3 py-3 font-semibold">
                {{ t('kingdomP7B.provenance') }}
              </th>
              <th v-if="canManage" class="px-3 py-3 font-semibold">
                {{ t('kingdomP7B.actions') }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="observation in history" :key="observation.id ?? observation.capturedAt">
              <td class="px-3 py-4 text-slate-300">
                {{ formatDate(observation.capturedAt) }}
                <span
                  v-if="observation.invalidatedAt"
                  class="ml-2 rounded-full bg-rose-950 px-2 py-1 text-xs font-semibold text-rose-300"
                >
                  {{ t('kingdomP7B.invalidated') }}
                </span>
              </td>
              <td class="px-3 py-4">
                <p class="font-medium text-slate-100">{{ observation.observedName }}</p>
                <p class="mt-1 text-xs text-[var(--ks-text-secondary)]">
                  {{ observation.observedTag ?? 'No tag' }}
                </p>
              </td>
              <td class="px-3 py-4 text-slate-300">
                {{ observation.power ?? t('kingdomP7B.missing') }}
              </td>
              <td class="px-3 py-4 text-slate-300">
                {{ observation.memberCount ?? t('kingdomP7B.missing') }}
              </td>
              <td v-if="canManage" class="px-3 py-4 text-slate-300">
                <p>
                  {{ observation.actorName ?? t('kingdomP7B.unavailableActor') }} ·
                  {{ observation.source }}
                </p>
                <p
                  v-if="observation.correctsObservationId"
                  class="mt-1 text-xs text-[var(--ks-text-muted)]"
                >
                  Correction of {{ observation.correctsObservationId }}
                </p>
                <p
                  v-if="observation.invalidatedAt"
                  class="mt-1 text-xs text-[var(--ks-text-muted)]"
                >
                  {{ t('kingdomP7B.invalidated') }} by
                  {{ observation.invalidatedByName ?? 'unavailable actor' }} on
                  {{ formatDate(observation.invalidatedAt) }}
                </p>
                <p
                  v-if="observation.invalidationReason"
                  class="mt-1 text-xs text-[var(--ks-text-muted)]"
                >
                  {{ observation.invalidationReason }}
                </p>
              </td>
              <td v-if="canManage" class="px-3 py-4">
                <div v-if="!observation.invalidatedAt && canMutate" class="flex flex-wrap gap-2">
                  <button
                    class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold"
                    type="button"
                    @click="beginCorrection(observation)"
                  >
                    {{ t('kingdomP7B.correct') }}
                  </button>
                  <button
                    class="rounded-lg border border-rose-900 px-3 py-2 text-xs font-semibold text-rose-300"
                    type="button"
                    @click="beginInvalidation(observation)"
                  >
                    {{ t('kingdomP7B.invalidate') }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-else
        class="mt-6 rounded-xl border border-dashed border-slate-700 p-5 text-sm text-[var(--ks-text-secondary)]"
      >
        {{ t('kingdomP7B.noObservations') }}
      </p>
    </section>

    <section
      v-if="canManage && invalidateTargetId"
      class="mt-8 rounded-2xl border border-rose-900 bg-slate-900/70 p-6"
    >
      <h2 class="text-xl font-semibold">{{ t('kingdomP7B.invalidate') }}</h2>
      <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">
        The original row remains historical and is excluded from latest/freshness projections.
      </p>
      <form class="mt-5" @submit.prevent="invalidateObservation">
        <label class="block text-sm font-medium" for="invalidation-reason">{{
          t('kingdomP7B.invalidateReason')
        }}</label>
        <textarea
          id="invalidation-reason"
          v-model="invalidateForm.reason"
          class="ks-input mt-2 min-h-24 w-full"
          maxlength="5000"
          required
        />
        <p v-if="invalidateForm.errors.reason" class="mt-1 text-sm text-rose-300">
          {{ invalidateForm.errors.reason }}
        </p>
        <div class="mt-4 flex flex-wrap gap-3">
          <button
            class="rounded-lg bg-rose-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            :disabled="invalidateForm.processing || !canMutate"
            type="submit"
          >
            {{ t('kingdomP7B.confirmInvalidation') }}
          </button>
          <button
            class="rounded-lg border border-slate-700 px-4 py-2 font-semibold"
            type="button"
            @click="cancelInvalidation"
          >
            Cancel
          </button>
        </div>
      </form>
    </section>
  </AppLayout>
</template>
