<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

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
  alliance: {
    id: string;
    name: string;
    kingdom: string | null;
  };
  adapters: AdapterDefinition[];
  subscriptions: SubscriptionRow[];
  candidates: CandidateRow[];
}>();

const createForm = useForm({
  adapter_key: props.adapters[0]?.key ?? '',
});

function createSubscription(): void {
  createForm.post('/alliance/kingdom-ingestion/subscriptions', {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  });
}

function transition(subscription: SubscriptionRow, state: 'active' | 'paused' | 'disabled'): void {
  if (
    state === 'disabled' &&
    !window.confirm(`Disable automated ingestion for ${subscription.adapterLabel}?`)
  ) {
    return;
  }

  router.patch(
    `/alliance/kingdom-ingestion/subscriptions/${subscription.id}/state`,
    { state },
    { preserveScroll: true },
  );
}

function replayCandidate(candidate: CandidateRow): void {
  if (
    !window.confirm(
      'Replay this quarantined candidate through the existing Kingdoms promotion rules?',
    )
  ) {
    return;
  }

  router.post(
    `/alliance/kingdom-ingestion/subscriptions/${candidate.subscriptionId}/candidates/${candidate.id}/replay`,
    {},
    { preserveScroll: true },
  );
}

function rejectCandidate(candidate: CandidateRow): void {
  if (
    !window.confirm(
      'Reject this quarantined ingestion candidate? Promoted Kingdoms history is not affected.',
    )
  ) {
    return;
  }

  router.post(
    `/alliance/kingdom-ingestion/subscriptions/${candidate.subscriptionId}/candidates/${candidate.id}/reject`,
    {},
    { preserveScroll: true },
  );
}

function label(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}
</script>

<template>
  <Head title="Manage automated ingestion" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Kingdom intelligence
        </p>
        <h1 class="mt-2 text-3xl font-bold">Automated ingestion</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-400">
          {{ alliance.name }} · current Kingdom {{ alliance.kingdom ?? 'not configured' }}. Approved
          adapters can stage and promote factual player or game-alliance observations only through
          existing Kingdoms relationships. Production remains empty-by-default until a concrete
          source receives separate approval.
        </p>
      </div>
      <Link
        class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200"
        href="/alliance/kingdom-alliances/manage"
      >
        Kingdom alliances
      </Link>
    </header>

    <section class="mt-10 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">Approved source subscription</h2>
      <p class="mt-1 text-sm text-slate-400">
        Source network locations and authentication are operator-owned. Managers cannot enter URLs,
        headers, cookies, or source credentials here.
      </p>

      <form
        class="mt-6 grid gap-5 md:grid-cols-[minmax(0,1fr)_auto] md:items-end"
        @submit.prevent="createSubscription"
      >
        <div>
          <label class="block text-sm font-medium" for="ingestion-adapter">Source adapter</label>
          <select
            id="ingestion-adapter"
            v-model="createForm.adapter_key"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            :disabled="adapters.length === 0"
          >
            <option v-if="adapters.length === 0" value="">No source adapters approved</option>
            <option v-for="adapter in adapters" :key="adapter.key" :value="adapter.key">
              {{ adapter.label }} · {{ adapter.version }}
              {{ adapter.acquisitionEnabled ? '· scheduled' : '· manual pipeline only' }}
            </option>
          </select>
          <p v-if="createForm.errors.adapter_key" class="mt-1 text-sm text-rose-300" role="alert">
            {{ createForm.errors.adapter_key }}
          </p>
          <p v-else class="mt-1 text-xs text-slate-500">
            Scheduled adapters use repository-defined bounded polling; managers cannot change the
            destination or frequency.
          </p>
        </div>
        <button
          class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
          :disabled="createForm.processing || adapters.length === 0 || alliance.kingdom === null"
          type="submit"
        >
          Enable adapter
        </button>
      </form>
    </section>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">Subscriptions</h2>
      <p class="mt-1 text-sm text-slate-400">
        A captured Kingdom never silently follows a later Alliance Kingdom change. Scheduler claims,
        bounded failure state, and circuit timing are visible without exposing source responses or
        secrets.
      </p>

      <div v-if="subscriptions.length" class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <caption class="sr-only">
            Automated ingestion subscriptions, scheduling, and health
          </caption>
          <thead class="text-xs tracking-wide text-slate-400 uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">Adapter</th>
              <th class="px-3 py-3 font-semibold">Kingdom</th>
              <th class="px-3 py-3 font-semibold">State</th>
              <th class="px-3 py-3 font-semibold">Scheduling</th>
              <th class="px-3 py-3 font-semibold">Candidates</th>
              <th class="px-3 py-3 font-semibold">Latest batch</th>
              <th class="px-3 py-3 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="subscription in subscriptions" :key="subscription.id">
              <td class="px-3 py-4">
                <p class="font-medium text-slate-100">{{ subscription.adapterLabel }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ subscription.adapterKey }} · {{ subscription.adapterVersion }}
                </p>
              </td>
              <td class="px-3 py-4 text-slate-300">
                {{ subscription.kingdom }}
                <span
                  v-if="!subscription.contextCurrent"
                  class="ml-2 rounded-full bg-amber-950 px-2 py-1 text-xs font-semibold text-amber-300"
                >
                  Historical
                </span>
              </td>
              <td class="px-3 py-4 text-slate-300">
                <p>{{ label(subscription.state) }}</p>
                <p v-if="subscription.lastFailureCode" class="mt-1 text-xs text-amber-300">
                  {{ label(subscription.lastFailureCode) }} · {{ subscription.consecutiveFailures }}
                  failure(s)
                </p>
              </td>
              <td class="px-3 py-4 text-slate-300">
                <p v-if="subscription.nextRunAt">Next: {{ subscription.nextRunAt }}</p>
                <p v-else class="text-slate-500">Not scheduled</p>
                <p v-if="subscription.circuitOpenUntil" class="mt-1 text-xs text-amber-300">
                  Circuit until {{ subscription.circuitOpenUntil }}
                </p>
                <p v-else-if="subscription.lastClaimedAt" class="mt-1 text-xs text-slate-500">
                  Last claimed {{ subscription.lastClaimedAt }}
                </p>
              </td>
              <td class="px-3 py-4 text-slate-300">
                {{ subscription.pendingCandidates }} pending ·
                {{ subscription.quarantinedCandidates }} quarantined ·
                {{ subscription.rejectedCandidates }} rejected
              </td>
              <td class="px-3 py-4 text-slate-300">
                <template v-if="subscription.latestBatch">
                  <p>{{ label(subscription.latestBatch.state) }}</p>
                  <p class="mt-1 text-xs text-slate-500">
                    {{ subscription.latestBatch.recordsStaged }} staged ·
                    {{ subscription.latestBatch.recordsQuarantined }} quarantined
                  </p>
                  <p
                    v-if="subscription.latestBatch.failureCode"
                    class="mt-1 text-xs text-amber-300"
                  >
                    {{ label(subscription.latestBatch.failureCode) }}
                  </p>
                </template>
                <span v-else class="text-slate-500">No batches yet</span>
              </td>
              <td class="px-3 py-4">
                <div class="flex flex-wrap gap-2">
                  <button
                    v-if="subscription.state === 'active'"
                    class="rounded border border-amber-700 px-3 py-1.5 text-xs font-semibold text-amber-300"
                    type="button"
                    @click="transition(subscription, 'paused')"
                  >
                    Pause
                  </button>
                  <button
                    v-else
                    class="rounded border border-cyan-700 px-3 py-1.5 text-xs font-semibold text-cyan-300 disabled:opacity-50"
                    :disabled="!subscription.contextCurrent"
                    type="button"
                    @click="transition(subscription, 'active')"
                  >
                    Enable
                  </button>
                  <button
                    v-if="subscription.state !== 'disabled'"
                    class="rounded border border-rose-800 px-3 py-1.5 text-xs font-semibold text-rose-300"
                    type="button"
                    @click="transition(subscription, 'disabled')"
                  >
                    Disable
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="mt-6 text-sm text-slate-500">
        No automated-ingestion subscriptions configured.
      </p>
    </section>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">Recent candidates</h2>
      <p class="mt-1 text-sm text-slate-400">
        Only bounded provenance/status is displayed here. Raw source responses and source secrets
        are not retained as candidate data. Replay re-runs the existing stable-ID and tenant checks.
      </p>

      <div v-if="candidates.length" class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <caption class="sr-only">
            Recent normalized automated-ingestion candidates
          </caption>
          <thead class="text-xs tracking-wide text-slate-400 uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">Adapter</th>
              <th class="px-3 py-3 font-semibold">Target</th>
              <th class="px-3 py-3 font-semibold">Stable game ID</th>
              <th class="px-3 py-3 font-semibold">Captured</th>
              <th class="px-3 py-3 font-semibold">State</th>
              <th class="px-3 py-3 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="candidate in candidates" :key="candidate.id">
              <td class="px-3 py-4 text-slate-300">{{ candidate.adapterKey }}</td>
              <td class="px-3 py-4 text-slate-300">{{ label(candidate.targetKind) }}</td>
              <td class="px-3 py-4 text-slate-300">
                {{ candidate.stableGameId ?? 'Missing' }}
              </td>
              <td class="px-3 py-4 text-slate-300">{{ candidate.capturedAt }}</td>
              <td class="px-3 py-4 text-slate-300">
                {{ label(candidate.state) }}
                <span v-if="candidate.quarantineCode" class="mt-1 block text-xs text-amber-300">
                  {{ label(candidate.quarantineCode) }}
                </span>
              </td>
              <td class="px-3 py-4">
                <div v-if="candidate.state === 'quarantined'" class="flex flex-wrap gap-2">
                  <button
                    class="rounded border border-cyan-700 px-3 py-1.5 text-xs font-semibold text-cyan-300"
                    type="button"
                    @click="replayCandidate(candidate)"
                  >
                    Replay
                  </button>
                  <button
                    class="rounded border border-rose-800 px-3 py-1.5 text-xs font-semibold text-rose-300"
                    type="button"
                    @click="rejectCandidate(candidate)"
                  >
                    Reject
                  </button>
                </div>
                <span v-else class="text-xs text-slate-500">No manager action</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="mt-6 text-sm text-slate-500">No normalized candidates have been staged.</p>
    </section>
  </main>
</template>
