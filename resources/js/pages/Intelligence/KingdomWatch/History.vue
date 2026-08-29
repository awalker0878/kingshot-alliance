<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

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

type TimelineItem = {
  id: string;
  kind: string;
  owner: string;
  observedAt: string;
  source: {
    type: string;
    reference: string | null;
    adapter: string | null;
    adapterVersion: string | null;
  };
  evidenceIds: string[];
  confidence: number | null;
  scope: {
    type: string;
    allianceId: string;
    trackingId: string;
    kingdomAllianceId: string | null;
  };
  summary: {
    name?: string;
    tag?: string | null;
    power?: string | null;
    memberCount?: number | null;
    from?: string;
    to?: string;
    text?: string;
    metric?: string | null;
    currentValue?: unknown;
    previousValue?: unknown;
    delta?: string | number | null;
  };
  canonicalUrl: string | null;
  derived: boolean;
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
  timeline: TimelineItem[];
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
function timelineHeading(item: TimelineItem): string {
  if (item.kind === 'alliance_observation') {
    return t('kingdomP7B.timelineObservation', { name: item.summary.name ?? props.tracking.name });
  }
  if (item.kind === 'diplomacy_transition') {
    return t('kingdomP7B.timelineDiplomacy', {
      from: item.summary.from ?? t('kingdomP7B.unknown'),
      to: item.summary.to ?? t('kingdomP7B.unknown'),
    });
  }
  return t('kingdomP7B.timelineChange', {
    metric: (item.summary.metric ?? t('kingdomP7B.unknown')).replaceAll('_', ' '),
  });
}
function timelineDetail(item: TimelineItem): string {
  if (item.kind === 'alliance_observation') {
    return t('kingdomP7B.timelineObservationFacts', {
      power: item.summary.power ?? t('kingdomP7B.missing'),
      members:
        item.summary.memberCount === null || item.summary.memberCount === undefined
          ? t('kingdomP7B.missing')
          : formatNumber(item.summary.memberCount),
    });
  }
  if (item.kind === 'diplomacy_transition') {
    return t('kingdomP7B.timelineOfficerRecorded');
  }
  return t('kingdomP7B.timelineDerivedFacts', {
    previous: String(item.summary.previousValue ?? t('kingdomP7B.missing')),
    current: String(item.summary.currentValue ?? t('kingdomP7B.missing')),
    delta: String(item.summary.delta ?? t('kingdomP7B.missing')),
  });
}
</script>

<template>
  <Head
    :title="`${t('kingdomP7B.historyTitle', { alliance: tracking.name })} · ${alliance.name}`"
  />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
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
        class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-ivory)]"
        href="/alliance/kingdom-alliances"
      >
        {{ t('kingdomP7A.overviewTitle') }}
      </Link>
    </header>

    <section class="mt-8 grid gap-4 md:grid-cols-3">
      <div class="ks-surface p-5">
        <p class="text-xs font-semibold tracking-wide text-[var(--ks-text-secondary)] uppercase">
          {{ t('kingdomP7B.freshness') }}
        </p>
        <p class="mt-2 text-xl font-semibold">{{ t(`kingdomP7B.${freshness}`) }}</p>
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

    <section class="ks-surface mt-8 p-6" aria-labelledby="intelligence-timeline-heading">
      <p class="text-sm font-semibold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
        {{ t('kingdomP7B.timelineEyebrow') }}
      </p>
      <h2 id="intelligence-timeline-heading" class="mt-2 text-xl font-semibold">
        {{ t('kingdomP7B.timelineTitle') }}
      </h2>
      <p class="mt-1 max-w-4xl text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('kingdomP7B.timelineHelp') }}
      </p>
      <ol v-if="timeline.length" class="mt-6 space-y-4">
        <li
          v-for="item in timeline"
          :key="item.id"
          class="relative border-s border-[var(--ks-border-strong)] ps-5"
        >
          <span
            class="absolute -start-1.5 top-1 h-3 w-3 rounded-full border border-[var(--ks-gold-dark)] bg-[var(--ks-teal)]"
            aria-hidden="true"
          />
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="font-semibold">{{ timelineHeading(item) }}</h3>
              <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">
                {{ timelineDetail(item) }}
              </p>
            </div>
            <span class="ks-status" :data-tone="item.derived ? 'info' : 'success'">
              {{
                item.derived ? t('kingdomP7B.timelineDerived') : t('kingdomP7B.timelineOwnerFact')
              }}
            </span>
          </div>
          <dl
            class="mt-3 grid gap-2 text-xs text-[var(--ks-text-muted)] sm:grid-cols-2 xl:grid-cols-4"
          >
            <div>
              <dt>{{ t('kingdomP7B.timelineObservedAt') }}</dt>
              <dd class="mt-1 text-[var(--ks-text-secondary)]">
                {{ formatDate(item.observedAt) }}
              </dd>
            </div>
            <div>
              <dt>{{ t('kingdomP7B.timelineOwner') }}</dt>
              <dd class="mt-1 text-[var(--ks-text-secondary)]">{{ item.owner }}</dd>
            </div>
            <div>
              <dt>{{ t('kingdomP7B.timelineSource') }}</dt>
              <dd class="mt-1 break-words text-[var(--ks-text-secondary)]">
                {{ item.source.type
                }}{{ item.source.reference ? ` · ${item.source.reference}` : '' }}
              </dd>
            </div>
            <div>
              <dt>{{ t('kingdomP7B.timelineEvidenceConfidence') }}</dt>
              <dd class="mt-1 text-[var(--ks-text-secondary)]">
                {{ t('kingdomP7B.timelineEvidenceCount', { count: item.evidenceIds.length }) }} ·
                {{
                  item.confidence === null
                    ? t('kingdomP7B.timelineConfidenceMissing')
                    : item.confidence
                }}
              </dd>
            </div>
          </dl>
          <Link
            v-if="item.canonicalUrl"
            :href="item.canonicalUrl"
            class="mt-3 inline-block text-xs underline"
          >
            {{ t('kingdomP7B.timelineOpenOwner') }}
          </Link>
        </li>
      </ol>
      <p v-else class="ks-fantasy-empty mt-5">{{ t('kingdomP7B.timelineEmpty') }}</p>
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
          class="rounded-lg border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
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
        {{ t('kingdomP7B.readOnlyHistorical') }}
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
            :placeholder="t('kingdomP7B.notSet')"
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
            :placeholder="t('kingdomP7B.notSet')"
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
            :placeholder="t('kingdomP7B.privateContext')"
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
            class="rounded-lg bg-[var(--ks-blue)] px-4 py-2 font-semibold text-[var(--ks-ivory)] disabled:opacity-60"
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
        <table class="min-w-full divide-y divide-[var(--ks-border)] text-left text-sm">
          <thead class="text-xs tracking-wide text-[var(--ks-text-secondary)] uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7B.capturedAt') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7B.observedName') }}</th>
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
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="observation in history" :key="observation.id ?? observation.capturedAt">
              <td class="px-3 py-4 text-[var(--ks-muted)]">
                {{ formatDate(observation.capturedAt) }}
                <span
                  v-if="observation.invalidatedAt"
                  class="ml-2 rounded-full bg-rose-950 px-2 py-1 text-xs font-semibold text-rose-300"
                >
                  {{ t('kingdomP7B.invalidated') }}
                </span>
              </td>
              <td class="px-3 py-4">
                <p class="font-medium text-[var(--ks-ivory)]">{{ observation.observedName }}</p>
                <p class="mt-1 text-xs text-[var(--ks-text-secondary)]">
                  {{ observation.observedTag ?? t('kingdomP7A.noTag') }}
                </p>
              </td>
              <td class="px-3 py-4 text-[var(--ks-muted)]">
                {{ observation.power ?? t('kingdomP7B.missing') }}
              </td>
              <td class="px-3 py-4 text-[var(--ks-muted)]">
                {{ observation.memberCount ?? t('kingdomP7B.missing') }}
              </td>
              <td v-if="canManage" class="px-3 py-4 text-[var(--ks-muted)]">
                <p>
                  {{ observation.actorName ?? t('kingdomP7B.unavailableActor') }} ·
                  {{ observation.source }}
                </p>
                <p
                  v-if="observation.correctsObservationId"
                  class="mt-1 text-xs text-[var(--ks-text-muted)]"
                >
                  {{ t('kingdomP7B.correctionOf', { id: observation.correctsObservationId }) }}
                </p>
                <p
                  v-if="observation.invalidatedAt"
                  class="mt-1 text-xs text-[var(--ks-text-muted)]"
                >
                  {{
                    t('kingdomP7B.invalidatedBy', {
                      actor: observation.invalidatedByName ?? t('kingdomP7B.unavailableActor'),
                      date: formatDate(observation.invalidatedAt),
                    })
                  }}
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
                    class="rounded-lg border border-[var(--ks-border)] px-3 py-2 text-xs font-semibold"
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
        class="mt-6 rounded-xl border border-dashed border-[var(--ks-border)] p-5 text-sm text-[var(--ks-text-secondary)]"
      >
        {{ t('kingdomP7B.noObservations') }}
      </p>
    </section>

    <section
      v-if="canManage && invalidateTargetId"
      class="mt-8 rounded-2xl border border-rose-900 bg-[rgba(24,25,21,.78)] p-6"
    >
      <h2 class="text-xl font-semibold">{{ t('kingdomP7B.invalidate') }}</h2>
      <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">
        {{ t('kingdomP7B.invalidationHelp') }}
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
            class="rounded-lg bg-rose-300 px-4 py-2 font-semibold text-[var(--ks-ink)] disabled:opacity-60"
            :disabled="invalidateForm.processing || !canMutate"
            type="submit"
          >
            {{ t('kingdomP7B.confirmInvalidation') }}
          </button>
          <button
            class="rounded-lg border border-[var(--ks-border)] px-4 py-2 font-semibold"
            type="button"
            @click="cancelInvalidation"
          >
            {{ t('common.cancel') }}
          </button>
        </div>
      </form>
    </section>
  </AppLayout>
</template>
