<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

type Observation = {
  power: string | null;
  memberCount: number | null;
  capturedAt: string;
};

type Change = {
  baselineCapturedAt: string;
  powerChange: string | null;
  memberChange: number | null;
};

type Diplomacy = {
  state: string;
  needsReview: boolean;
  effectiveAt: string | null;
  reviewAt: string | null;
  expiresAt: string | null;
};

type ContactDiagnostics = {
  activeContacts: number;
  verificationDue: number;
  latestVerifiedAt: string | null;
  staleAfterDays: number;
};

type IntelligenceRow = {
  name: string;
  tag: string | null;
  trackingState: string;
  kingdom: string;
  contextCurrent: boolean;
  historyUrl: string;
  freshness: 'current' | 'stale' | 'missing';
  observationAgeDays: number | null;
  latestObservation: Observation | null;
  priorChange: Change | null;
  sevenDayChange: Change | null;
  thirtyDayChange: Change | null;
  diplomacy: Diplomacy;
  diplomacyUrl?: string;
  contactsUrl?: string;
  contactDiagnostics?: ContactDiagnostics;
};

type Filters = {
  tracking: string;
  freshness: string;
  diplomacy: string;
  sort: string;
  direction: string;
};

type Intelligence = {
  asOf: string;
  summary: {
    activeTrackedAlliances: number;
    observationQuality: {
      current: number;
      stale: number;
      missing: number;
      staleAfterDays: number;
    };
    diplomacyStates: Record<string, number>;
    relationshipsNeedingReview: number;
  };
  managerSummary: {
    trackedWithActiveContact: number;
    trackedWithVerificationDue: number;
    verificationStaleAfterDays: number;
  } | null;
  windows: {
    sevenDay: { days: number; oldestDays: number };
    thirtyDay: { days: number; oldestDays: number };
  };
  filters: Filters;
  rows: IntelligenceRow[];
};

const props = defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  canManage: boolean;
  intelligence: Intelligence;
}>();

const filters = reactive<Filters>({ ...props.intelligence.filters });

function applyFilters(): void {
  router.get(
    '/alliance/kingdom-alliances/intelligence',
    { ...filters },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  );
}

function resetFilters(): void {
  filters.tracking = 'active';
  filters.freshness = 'all';
  filters.diplomacy = 'all';
  filters.sort = 'name';
  filters.direction = 'asc';
  applyFilters();
}

function formatDecimal(value: string | null): string {
  if (value === null) return 'missing';

  const negative = value.startsWith('-');
  const unsigned = negative ? value.slice(1) : value;
  const grouped = unsigned.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

  return negative ? `-${grouped}` : grouped;
}

function formatSignedDecimal(value: string | null): string {
  if (value === null) return 'missing';
  if (value.startsWith('-') || value === '0') return formatDecimal(value);
  return `+${formatDecimal(value)}`;
}

function formatSignedNumber(value: number | null): string {
  if (value === null) return 'missing';
  if (value <= 0) return String(value);
  return `+${value}`;
}

function formatDate(value: string | null): string {
  if (value === null) return 'Not set';

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function stateLabel(value: string): string {
  if (value === 'nap') return 'NAP';
  return value.charAt(0).toUpperCase() + value.slice(1).replaceAll('_', ' ');
}

function changeText(change: Change | null): string {
  if (change === null) return 'Insufficient history';

  return `Power ${formatSignedDecimal(change.powerChange)} · Members ${formatSignedNumber(change.memberChange)} · baseline ${formatDate(change.baselineCapturedAt)}`;
}

function freshnessLabel(row: IntelligenceRow): string {
  if (row.freshness === 'missing') return 'Missing observation';
  if (row.observationAgeDays === null) return stateLabel(row.freshness);
  return `${stateLabel(row.freshness)} · ${row.observationAgeDays} day${row.observationAgeDays === 1 ? '' : 's'} old`;
}
</script>

<template>
  <Head :title="`Alliance intelligence · ${alliance.name}`" />

  <main class="mx-auto min-h-screen max-w-7xl px-6 py-12 text-slate-100 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link
          class="text-sm font-semibold text-cyan-300 hover:text-cyan-200"
          href="/alliance/kingdom-alliances"
        >
          ← Tracked alliances
        </Link>
        <p class="mt-5 text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Kingdom {{ alliance.kingdom ?? 'not set' }}
        </p>
        <h1 class="mt-2 text-3xl font-bold">Alliance intelligence</h1>
        <p class="mt-2 max-w-4xl text-sm text-slate-400">
          Descriptive operational trends derived only from accepted observations and explicit
          diplomacy state. Missing values remain missing; this view does not calculate threat,
          desirability, target, or composite scores and never recommends or executes diplomacy or
          transfer actions.
        </p>
      </div>
      <Link
        v-if="canManage"
        class="rounded-lg border border-cyan-800 px-4 py-2 text-sm font-semibold text-cyan-300 hover:border-cyan-600"
        href="/alliance/kingdom-alliances/manage"
      >
        Manage tracking
      </Link>
    </header>

    <section
      class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
      aria-label="Alliance intelligence summary"
    >
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Active tracked alliances</p>
        <p class="mt-2 text-3xl font-bold">{{ intelligence.summary.activeTrackedAlliances }}</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Current observations</p>
        <p class="mt-2 text-3xl font-bold">{{ intelligence.summary.observationQuality.current }}</p>
        <p class="mt-2 text-xs text-slate-500">
          Current means captured within
          {{ intelligence.summary.observationQuality.staleAfterDays }} days.
        </p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Stale / missing observations</p>
        <p class="mt-2 text-3xl font-bold">
          {{ intelligence.summary.observationQuality.stale }} /
          {{ intelligence.summary.observationQuality.missing }}
        </p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Relationships requiring review</p>
        <p class="mt-2 text-3xl font-bold">{{ intelligence.summary.relationshipsNeedingReview }}</p>
        <p class="mt-2 text-xs text-slate-500">
          Review/expiry dates are advisory and never change diplomacy automatically.
        </p>
      </div>
    </section>

    <section
      class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-5"
      aria-labelledby="diplomacy-summary"
    >
      <h2 id="diplomacy-summary" class="text-lg font-semibold">Current diplomacy states</h2>
      <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <div
          v-for="(count, state) in intelligence.summary.diplomacyStates"
          :key="state"
          class="rounded-xl bg-slate-950/60 p-3 text-center"
        >
          <dt class="text-xs text-slate-500">{{ stateLabel(state) }}</dt>
          <dd class="mt-1 text-2xl font-bold">{{ count }}</dd>
        </div>
      </dl>
    </section>

    <section
      v-if="canManage && intelligence.managerSummary"
      class="mt-6 grid gap-4 md:grid-cols-2"
      aria-label="Private contact diagnostics"
    >
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Tracked alliances with an active contact</p>
        <p class="mt-2 text-3xl font-bold">
          {{ intelligence.managerSummary.trackedWithActiveContact }}
        </p>
        <p class="mt-2 text-xs text-slate-500">Manager-private diagnostic only.</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Tracked alliances with contact verification due</p>
        <p class="mt-2 text-3xl font-bold">
          {{ intelligence.managerSummary.trackedWithVerificationDue }}
        </p>
        <p class="mt-2 text-xs text-slate-500">
          Due means an active contact is unverified or last verified more than
          {{ intelligence.managerSummary.verificationStaleAfterDays }} days ago.
        </p>
      </div>
    </section>

    <section
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/60 p-5"
      aria-labelledby="intelligence-filters"
    >
      <h2 id="intelligence-filters" class="text-lg font-semibold">Filter and factual sorting</h2>
      <p class="mt-1 text-sm text-slate-400">
        Default order is alphabetical. Optional factual sorting changes navigation order only; it is
        not a best/worst, threat, target, or desirability ranking.
      </p>

      <form class="mt-5 grid gap-4 md:grid-cols-3 xl:grid-cols-6" @submit.prevent="applyFilters">
        <div>
          <label class="text-sm font-medium" for="tracking-filter">Tracking state</label>
          <select
            id="tracking-filter"
            v-model="filters.tracking"
            name="tracking"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="active">Active</option>
            <option value="archived">Archived</option>
            <option value="all">All</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="freshness-filter">Observation freshness</label>
          <select
            id="freshness-filter"
            v-model="filters.freshness"
            name="freshness"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="all">All</option>
            <option value="current">Current</option>
            <option value="stale">Stale</option>
            <option value="missing">Missing</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="diplomacy-filter">Diplomacy state</label>
          <select
            id="diplomacy-filter"
            v-model="filters.diplomacy"
            name="diplomacy"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="all">All</option>
            <option value="unknown">Unknown</option>
            <option value="neutral">Neutral</option>
            <option value="friendly">Friendly</option>
            <option value="nap">NAP</option>
            <option value="ally">Ally</option>
            <option value="rival">Rival</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="sort-filter">Sort by</label>
          <select
            id="sort-filter"
            v-model="filters.sort"
            name="sort"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="name">Name</option>
            <option value="tag">Tag</option>
            <option value="power">Latest power</option>
            <option value="members">Latest members</option>
            <option value="age">Observation age</option>
            <option value="diplomacy">Diplomacy state</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="direction-filter">Direction</label>
          <select
            id="direction-filter"
            v-model="filters.direction"
            name="direction"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="asc">Ascending</option>
            <option value="desc">Descending</option>
          </select>
        </div>
        <div class="flex items-end gap-2">
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950"
            type="submit"
          >
            Apply
          </button>
          <button
            class="rounded-lg border border-slate-700 px-4 py-2 font-semibold text-slate-200"
            type="button"
            @click="resetFilters"
          >
            Reset
          </button>
        </div>
      </form>
    </section>

    <section
      class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-5"
      aria-labelledby="trend-rules"
    >
      <h2 id="trend-rules" class="text-lg font-semibold">How descriptive trend windows work</h2>
      <p class="mt-2 text-sm text-slate-400">
        The current point is the latest accepted observation at or before the dashboard time. Prior
        change uses the immediately preceding accepted point. For an N-day trend, the baseline is
        the closest accepted observation at or before the N-day target but no older than 2N days. A
        newer point is never substituted for the target, older history outside the bounded window is
        ignored, and missing history is never interpolated.
      </p>
      <p class="mt-2 text-sm text-slate-400">
        Current windows: {{ intelligence.windows.sevenDay.days }}–{{
          intelligence.windows.sevenDay.oldestDays
        }}
        days for the 7-day comparison and {{ intelligence.windows.thirtyDay.days }}–{{
          intelligence.windows.thirtyDay.oldestDays
        }}
        days for the 30-day comparison.
      </p>
    </section>

    <section
      class="mt-8 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60"
      aria-labelledby="alliance-intelligence-table"
    >
      <div class="border-b border-slate-800 px-5 py-4">
        <h2 id="alliance-intelligence-table" class="text-xl font-semibold">
          Descriptive alliance detail
        </h2>
        <p class="mt-1 text-sm text-slate-400">
          {{ intelligence.rows.length }} record(s) match the current filters. Zero is a recorded
          value; “missing” means no supported value exists.
        </p>
      </div>

      <div v-if="intelligence.rows.length" class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <caption class="sr-only">
            Tracked game-side alliance descriptive intelligence
          </caption>
          <thead class="bg-slate-950/60 text-slate-400">
            <tr>
              <th class="px-4 py-3">Alliance</th>
              <th class="px-4 py-3">Latest facts</th>
              <th class="px-4 py-3">Data quality</th>
              <th class="px-4 py-3">Prior change</th>
              <th class="px-4 py-3">7-day change</th>
              <th class="px-4 py-3">30-day change</th>
              <th class="px-4 py-3">Diplomacy</th>
              <th v-if="canManage" class="px-4 py-3">Private contacts</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="row in intelligence.rows" :key="row.historyUrl">
              <td class="px-4 py-4 align-top">
                <Link
                  class="font-semibold text-cyan-300 hover:text-cyan-200"
                  :href="row.historyUrl"
                >
                  {{ row.name }}
                </Link>
                <p class="mt-1 text-xs text-slate-500">{{ row.tag ?? 'No tag recorded' }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ stateLabel(row.trackingState) }} tracking · Kingdom {{ row.kingdom }}
                </p>
                <p v-if="!row.contextCurrent" class="mt-1 text-xs font-semibold text-amber-300">
                  Historical Kingdom context
                </p>
              </td>
              <td class="px-4 py-4 align-top text-slate-300">
                <template v-if="row.latestObservation">
                  <p>Power {{ formatDecimal(row.latestObservation.power) }}</p>
                  <p>Members {{ row.latestObservation.memberCount ?? 'missing' }}</p>
                  <p class="mt-1 text-xs text-slate-500">
                    {{ formatDate(row.latestObservation.capturedAt) }}
                  </p>
                </template>
                <span v-else>No accepted observation</span>
              </td>
              <td class="px-4 py-4 align-top text-slate-300">
                <span class="rounded-full border border-slate-700 px-2 py-1 text-xs font-semibold">
                  {{ freshnessLabel(row) }}
                </span>
              </td>
              <td class="px-4 py-4 align-top text-slate-400">{{ changeText(row.priorChange) }}</td>
              <td class="px-4 py-4 align-top text-slate-400">
                {{ changeText(row.sevenDayChange) }}
              </td>
              <td class="px-4 py-4 align-top text-slate-400">
                {{ changeText(row.thirtyDayChange) }}
              </td>
              <td class="px-4 py-4 align-top text-slate-300">
                <p class="font-semibold">{{ stateLabel(row.diplomacy.state) }}</p>
                <p
                  v-if="row.diplomacy.needsReview"
                  class="mt-1 text-xs font-semibold text-amber-300"
                >
                  Human review due
                </p>
                <p v-if="row.diplomacy.reviewAt" class="mt-1 text-xs text-slate-500">
                  Review {{ formatDate(row.diplomacy.reviewAt) }}
                </p>
                <p v-if="row.diplomacy.expiresAt" class="mt-1 text-xs text-slate-500">
                  Expiry {{ formatDate(row.diplomacy.expiresAt) }}
                </p>
                <Link
                  v-if="canManage && row.diplomacyUrl"
                  class="mt-2 inline-block text-xs font-semibold text-cyan-300 hover:text-cyan-200"
                  :href="row.diplomacyUrl"
                >
                  Manage diplomacy
                </Link>
              </td>
              <td v-if="canManage" class="px-4 py-4 align-top text-slate-300">
                <template v-if="row.contactDiagnostics">
                  <p>{{ row.contactDiagnostics.activeContacts }} active contact(s)</p>
                  <p class="mt-1 text-xs text-slate-500">
                    {{ row.contactDiagnostics.verificationDue }} verification due
                  </p>
                  <p class="mt-1 text-xs text-slate-500">
                    Latest verified {{ formatDate(row.contactDiagnostics.latestVerifiedAt) }}
                  </p>
                </template>
                <Link
                  v-if="row.contactsUrl"
                  class="mt-2 inline-block text-xs font-semibold text-cyan-300 hover:text-cyan-200"
                  :href="row.contactsUrl"
                >
                  Contact directory
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-else class="p-8 text-sm text-slate-400">
        No tracked alliances match the selected filters.
      </p>
    </section>

    <p class="mt-6 text-xs text-slate-500">
      Calculated at {{ formatDate(intelligence.asOf) }} from tenant-owned accepted observations and
      explicit human-maintained diplomacy state.
    </p>
  </main>
</template>
