<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type AdapterDefinition = {
  key: string;
  version: string;
  label: string;
  targetKinds: string[];
  acquisitionEnabled: boolean;
  pollIntervalSeconds: number | null;
};
type LatestBatch = {
  id: string;
  state: string;
  startedAt: string;
  completedAt: string | null;
  recordsReceived: number;
  recordsStaged: number;
  recordsQuarantined: number;
  recordsRejected: number;
  failureCode: string | null;
  nextSourceCursor: string | null;
};
type SubscriptionRow = {
  id: string;
  adapterKey: string;
  adapterVersion: string;
  adapterLabel: string;
  state: string;
  kingdom: string;
  contextCurrent: boolean;
  sourceCursor: string | null;
  nextRunAt: string | null;
  lastClaimedAt: string | null;
  lastSucceededAt: string | null;
  lastFailedAt: string | null;
  consecutiveFailures: number;
  circuitOpenUntil: string | null;
  lastFailureCode: string | null;
  blockedAt: string | null;
  blockedReason: string | null;
  pendingCandidates: number;
  quarantinedCandidates: number;
  rejectedCandidates: number;
  latestBatch: LatestBatch | null;
};
type CandidateRow = {
  id: string;
  subscriptionId: string;
  adapterKey: string;
  targetKind: string;
  stableGameId: string | null;
  sourceRecordId: string | null;
  capturedAt: string;
  state: string;
  quarantineCode: string | null;
  rejectionCode: string | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  adapters: AdapterDefinition[];
  subscriptions: SubscriptionRow[];
  candidates: CandidateRow[];
}>();

const { t, formatDate, formatNumber } = useLocale();
const createForm = useForm({ adapter_key: props.adapters[0]?.key ?? '' });
const activeSubscriptions = computed(
  () => props.subscriptions.filter((item) => item.state === 'active').length,
);
const quarantinedCount = computed(() =>
  props.subscriptions.reduce((total, item) => total + item.quarantinedCandidates, 0),
);

function createSubscription(): void {
  createForm.post('/alliance/kingdom-ingestion/subscriptions', {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  });
}

function transition(subscription: SubscriptionRow, state: 'active' | 'paused' | 'disabled'): void {
  if (
    state === 'disabled' &&
    !window.confirm(t('kingdomP7A.disableConfirm', { adapter: subscription.adapterLabel }))
  )
    return;
  router.patch(
    `/alliance/kingdom-ingestion/subscriptions/${subscription.id}/state`,
    { state },
    { preserveScroll: true },
  );
}

function replayCandidate(candidate: CandidateRow): void {
  if (!window.confirm(t('kingdomP7A.replayConfirm'))) return;
  router.post(
    `/alliance/kingdom-ingestion/subscriptions/${candidate.subscriptionId}/candidates/${candidate.id}/replay`,
    {},
    { preserveScroll: true },
  );
}

function rejectCandidate(candidate: CandidateRow): void {
  if (!window.confirm(t('kingdomP7A.rejectConfirm'))) return;
  router.post(
    `/alliance/kingdom-ingestion/subscriptions/${candidate.subscriptionId}/candidates/${candidate.id}/reject`,
    {},
    { preserveScroll: true },
  );
}

function date(value: string | null): string {
  return value ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

function label(value: string): string {
  const known: Record<string, string> = {
    active: t('kingdomP7A.active'),
    paused: t('kingdomP7A.paused'),
    disabled: t('kingdomP7A.disabled'),
    pending: t('kingdomP7A.pending'),
    quarantined: t('kingdomP7A.quarantined'),
    rejected: t('kingdomP7A.rejected'),
  };
  return (
    known[value] ?? value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
  );
}

function tone(value: string): string {
  if (['active', 'completed', 'succeeded', 'promoted'].includes(value))
    return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (['paused', 'pending', 'quarantined'].includes(value))
    return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
  if (['disabled', 'failed', 'rejected', 'blocked'].includes(value))
    return 'border-red-400/25 bg-red-500/10 text-red-200';
  return 'border-[var(--ks-border)] bg-[var(--ks-teal-soft)] text-[var(--ks-gold-bright)]';
}
</script>

<template>
  <Head :title="`${t('kingdomP7A.ingestionTitle')} · ${alliance.name}`" />
  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-5">
      <div class="max-w-3xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('kingdomP7A.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('kingdomP7A.ingestionTitle') }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7A.ingestionSubtitle', { alliance: alliance.name }) }}
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <Link
          href="/alliance/kingdom-alliances"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          >{{ t('kingdomP7A.overviewTitle') }}</Link
        ><Link
          href="/alliance/settings/kingdom"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          >{{ t('kingdomP7A.settings') }}</Link
        >
      </div>
    </header>

    <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ingestion summary">
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7A.currentKingdom') }}
        </p>
        <p class="ks-display mt-2 text-2xl font-bold text-[var(--ks-gold)]">
          {{ alliance.kingdom ? `#${alliance.kingdom}` : t('kingdomP7A.notConfigured') }}
        </p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7A.approvedAdapters') }}
        </p>
        <p class="mt-2 text-2xl font-bold">{{ formatNumber(adapters.length) }}</p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7A.subscriptionsCount') }}
        </p>
        <p class="mt-2 text-2xl font-bold">{{ formatNumber(subscriptions.length) }}</p>
        <p class="mt-1 text-xs text-green-200">
          {{ formatNumber(activeSubscriptions) }} {{ t('kingdomP7A.active') }}
        </p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7A.quarantinedCount') }}
        </p>
        <p
          class="mt-2 text-2xl font-bold"
          :class="quarantinedCount ? 'text-amber-200' : 'text-green-200'"
        >
          {{ formatNumber(quarantinedCount) }}
        </p>
      </article>
    </section>

    <section class="ks-surface mt-6 p-5" aria-labelledby="source-heading">
      <h2 id="source-heading" class="ks-display text-xl font-semibold">
        {{ t('kingdomP7A.approvedSource') }}
      </h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('kingdomP7A.approvedSourceHelp') }}
      </p>
      <form
        class="mt-5 grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end"
        @submit.prevent="createSubscription"
      >
        <div>
          <label class="text-sm font-semibold" for="ingestion-adapter">{{
            t('kingdomP7A.sourceAdapter')
          }}</label
          ><select
            id="ingestion-adapter"
            v-model="createForm.adapter_key"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/20 px-3 py-2.5"
            :disabled="adapters.length === 0"
          >
            <option v-if="adapters.length === 0" value="">{{ t('kingdomP7A.noAdapters') }}</option>
            <option v-for="adapter in adapters" :key="adapter.key" :value="adapter.key">
              {{ adapter.label }} · {{ adapter.version }} ·
              {{
                adapter.acquisitionEnabled
                  ? t('kingdomP7A.scheduled')
                  : t('kingdomP7A.manualPipelineOnly')
              }}
            </option>
          </select>
          <p v-if="createForm.errors.adapter_key" class="mt-1 text-sm text-red-300" role="alert">
            {{ createForm.errors.adapter_key }}
          </p>
          <p v-else class="mt-2 text-xs text-[var(--ks-text-muted)]">
            {{ t('kingdomP7A.adapterHelp') }}
          </p>
        </div>
        <button
          class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2.5 text-sm font-semibold text-[var(--ks-ivory)] disabled:opacity-50"
          :disabled="createForm.processing || adapters.length === 0 || alliance.kingdom === null"
          type="submit"
        >
          {{ t('kingdomP7A.enableAdapter') }}
        </button>
      </form>
    </section>

    <section class="ks-surface mt-6 p-5" aria-labelledby="subscriptions-heading">
      <h2 id="subscriptions-heading" class="ks-display text-xl font-semibold">
        {{ t('kingdomP7A.subscriptions') }}
      </h2>
      <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('kingdomP7A.subscriptionsHelp') }}
      </p>
      <div v-if="subscriptions.length" class="mt-5 space-y-3 lg:hidden">
        <article
          v-for="subscription in subscriptions"
          :key="subscription.id"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="font-semibold">{{ subscription.adapterLabel }}</p>
              <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                {{ subscription.adapterKey }} · {{ subscription.adapterVersion }}
              </p>
            </div>
            <span
              :class="tone(subscription.state)"
              class="rounded-full border px-2 py-1 text-xs font-semibold"
              >{{ label(subscription.state) }}</span
            >
          </div>
          <p class="mt-3 text-sm">
            #{{ subscription.kingdom
            }}<span v-if="!subscription.contextCurrent" class="ms-2 text-xs text-amber-200">{{
              t('kingdomP7A.historical')
            }}</span>
          </p>
          <p class="mt-2 text-xs text-[var(--ks-text-secondary)]">
            {{
              t('kingdomP7A.candidateSummary', {
                pending: formatNumber(subscription.pendingCandidates),
                quarantined: formatNumber(subscription.quarantinedCandidates),
                rejected: formatNumber(subscription.rejectedCandidates),
              })
            }}
          </p>
          <p class="mt-2 text-xs text-[var(--ks-text-muted)]">
            {{
              subscription.nextRunAt
                ? `${t('kingdomP7A.nextRun')}: ${date(subscription.nextRunAt)}`
                : t('kingdomP7A.notScheduled')
            }}
          </p>
          <p v-if="subscription.latestBatch" class="mt-2 text-xs text-[var(--ks-text-secondary)]">
            {{ label(subscription.latestBatch.state) }} ·
            {{
              t('kingdomP7A.latestBatchSummary', {
                staged: formatNumber(subscription.latestBatch.recordsStaged),
                quarantined: formatNumber(subscription.latestBatch.recordsQuarantined),
              })
            }}
          </p>
          <p v-if="subscription.lastFailureCode" class="mt-2 text-xs text-red-200">
            {{ label(subscription.lastFailureCode) }} ·
            {{ formatNumber(subscription.consecutiveFailures) }}
          </p>
          <div class="mt-4 flex flex-wrap gap-2">
            <button
              v-if="subscription.state === 'active'"
              class="rounded border border-amber-400/25 px-3 py-1.5 text-xs font-semibold text-amber-200"
              type="button"
              @click="transition(subscription, 'paused')"
            >
              {{ t('kingdomP7A.pause') }}</button
            ><button
              v-else
              class="rounded border border-[var(--ks-border)] px-3 py-1.5 text-xs font-semibold text-[var(--ks-gold-bright)] disabled:opacity-50"
              :disabled="!subscription.contextCurrent"
              type="button"
              @click="transition(subscription, 'active')"
            >
              {{ t('kingdomP7A.enable') }}</button
            ><button
              v-if="subscription.state !== 'disabled'"
              class="rounded border border-red-400/25 px-3 py-1.5 text-xs font-semibold text-red-200"
              type="button"
              @click="transition(subscription, 'disabled')"
            >
              {{ t('kingdomP7A.disable') }}
            </button>
          </div>
        </article>
      </div>

      <div v-if="subscriptions.length" class="mt-5 hidden overflow-x-auto lg:block">
        <table class="min-w-full text-left text-sm">
          <caption class="sr-only">
            {{
              t('kingdomP7A.subscriptions')
            }}
          </caption>
          <thead class="text-xs text-[var(--ks-text-muted)] uppercase">
            <tr>
              <th class="px-3 py-3">{{ t('kingdomP7A.adapter') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7A.kingdom') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7A.state') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7A.scheduling') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7A.candidates') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7A.latestBatch') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7A.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="subscription in subscriptions" :key="subscription.id">
              <td class="px-3 py-4 align-top">
                <p class="font-semibold">{{ subscription.adapterLabel }}</p>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  {{ subscription.adapterKey }} · {{ subscription.adapterVersion }}
                </p>
              </td>
              <td class="px-3 py-4 align-top">
                #{{ subscription.kingdom
                }}<span
                  v-if="!subscription.contextCurrent"
                  class="mt-1 block text-xs text-amber-200"
                  >{{ t('kingdomP7A.historical') }}</span
                >
              </td>
              <td class="px-3 py-4 align-top">
                <span
                  :class="tone(subscription.state)"
                  class="rounded-full border px-2 py-1 text-xs font-semibold"
                  >{{ label(subscription.state) }}</span
                >
                <p v-if="subscription.lastFailureCode" class="mt-2 text-xs text-red-200">
                  {{ label(subscription.lastFailureCode) }} ·
                  {{ formatNumber(subscription.consecutiveFailures) }}
                </p>
              </td>
              <td class="px-3 py-4 align-top text-[var(--ks-text-secondary)]">
                <p>
                  {{
                    subscription.nextRunAt
                      ? `${t('kingdomP7A.nextRun')}: ${date(subscription.nextRunAt)}`
                      : t('kingdomP7A.notScheduled')
                  }}
                </p>
                <p v-if="subscription.circuitOpenUntil" class="mt-1 text-xs text-amber-200">
                  {{ t('kingdomP7A.circuitUntil', { date: date(subscription.circuitOpenUntil) }) }}
                </p>
                <p
                  v-else-if="subscription.lastClaimedAt"
                  class="mt-1 text-xs text-[var(--ks-text-muted)]"
                >
                  {{ t('kingdomP7A.lastClaimed', { date: date(subscription.lastClaimedAt) }) }}
                </p>
              </td>
              <td class="px-3 py-4 align-top text-[var(--ks-text-secondary)]">
                {{
                  t('kingdomP7A.candidateSummary', {
                    pending: formatNumber(subscription.pendingCandidates),
                    quarantined: formatNumber(subscription.quarantinedCandidates),
                    rejected: formatNumber(subscription.rejectedCandidates),
                  })
                }}
              </td>
              <td class="px-3 py-4 align-top text-[var(--ks-text-secondary)]">
                <template v-if="subscription.latestBatch"
                  ><p>{{ label(subscription.latestBatch.state) }}</p>
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                    {{
                      t('kingdomP7A.latestBatchSummary', {
                        staged: formatNumber(subscription.latestBatch.recordsStaged),
                        quarantined: formatNumber(subscription.latestBatch.recordsQuarantined),
                      })
                    }}
                  </p>
                  <p v-if="subscription.latestBatch.failureCode" class="mt-1 text-xs text-red-200">
                    {{ label(subscription.latestBatch.failureCode) }}
                  </p></template
                ><span v-else>{{ t('kingdomP7A.noBatches') }}</span>
              </td>
              <td class="px-3 py-4 align-top">
                <div class="flex flex-wrap gap-2">
                  <button
                    v-if="subscription.state === 'active'"
                    class="rounded border border-amber-400/25 px-3 py-1.5 text-xs font-semibold text-amber-200"
                    type="button"
                    @click="transition(subscription, 'paused')"
                  >
                    {{ t('kingdomP7A.pause') }}</button
                  ><button
                    v-else
                    class="rounded border border-[var(--ks-border)] px-3 py-1.5 text-xs font-semibold text-[var(--ks-gold-bright)] disabled:opacity-50"
                    :disabled="!subscription.contextCurrent"
                    type="button"
                    @click="transition(subscription, 'active')"
                  >
                    {{ t('kingdomP7A.enable') }}</button
                  ><button
                    v-if="subscription.state !== 'disabled'"
                    class="rounded border border-red-400/25 px-3 py-1.5 text-xs font-semibold text-red-200"
                    type="button"
                    @click="transition(subscription, 'disabled')"
                  >
                    {{ t('kingdomP7A.disable') }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="mt-5 text-sm text-[var(--ks-text-muted)]">{{ t('kingdomP7A.noBatches') }}</p>
    </section>

    <section class="ks-surface mt-6 p-5" aria-labelledby="candidates-heading">
      <h2 id="candidates-heading" class="ks-display text-xl font-semibold">
        {{ t('kingdomP7A.recentCandidates') }}
      </h2>
      <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('kingdomP7A.candidatesHelp') }}
      </p>
      <div v-if="candidates.length" class="mt-5 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <caption class="sr-only">
            {{
              t('kingdomP7A.recentCandidates')
            }}
          </caption>
          <thead class="text-xs text-[var(--ks-text-muted)] uppercase">
            <tr>
              <th class="px-3 py-3">{{ t('kingdomP7A.adapter') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7A.target') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7A.stableGameId') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7A.captured') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7A.state') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7A.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="candidate in candidates" :key="candidate.id">
              <td class="px-3 py-4">{{ candidate.adapterKey }}</td>
              <td class="px-3 py-4">{{ label(candidate.targetKind) }}</td>
              <td class="px-3 py-4">{{ candidate.stableGameId ?? t('kingdomP7A.missing') }}</td>
              <td class="px-3 py-4">{{ date(candidate.capturedAt) }}</td>
              <td class="px-3 py-4">
                <span
                  :class="tone(candidate.state)"
                  class="rounded-full border px-2 py-1 text-xs font-semibold"
                  >{{ label(candidate.state) }}</span
                ><span v-if="candidate.quarantineCode" class="mt-2 block text-xs text-amber-200">{{
                  label(candidate.quarantineCode)
                }}</span>
              </td>
              <td class="px-3 py-4">
                <div v-if="candidate.state === 'quarantined'" class="flex flex-wrap gap-2">
                  <button
                    class="rounded border border-[var(--ks-border)] px-3 py-1.5 text-xs font-semibold text-[var(--ks-gold-bright)]"
                    type="button"
                    @click="replayCandidate(candidate)"
                  >
                    {{ t('kingdomP7A.replay') }}</button
                  ><button
                    class="rounded border border-red-400/25 px-3 py-1.5 text-xs font-semibold text-red-200"
                    type="button"
                    @click="rejectCandidate(candidate)"
                  >
                    {{ t('kingdomP7A.reject') }}
                  </button>
                </div>
                <span v-else class="text-xs text-[var(--ks-text-muted)]">{{
                  t('kingdomP7A.noManagerAction')
                }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="mt-5 text-sm text-[var(--ks-text-muted)]">
        {{ t('kingdomP7A.noCandidates') }}
      </p>
    </section>
  </AppLayout>
</template>
