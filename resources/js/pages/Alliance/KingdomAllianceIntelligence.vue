<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

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
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  canManage: boolean;
  intelligence: Intelligence;
}>();

const { t, formatDate: localeDate, formatNumber } = useLocale();
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
  return value === null
    ? t('kingdomP7B.notSet')
    : localeDate(value, { dateStyle: 'medium', timeStyle: 'short' });
}

function stateLabel(value: string): string {
  if (value === 'unknown') return t('kingdomP7B.unknown');
  if (value === 'neutral') return t('kingdomP7B.neutral');
  if (value === 'friendly') return t('kingdomP7B.friendly');
  if (value === 'nap') return t('kingdomP7B.nap');
  if (value === 'ally') return t('kingdomP7B.ally');
  if (value === 'rival') return t('kingdomP7B.rival');
  return value.charAt(0).toUpperCase() + value.slice(1).replaceAll('_', ' ');
}

function changeText(change: Change | null): string {
  if (change === null) return t('kingdomP7B.insufficientHistory');
  return `Power ${formatSignedDecimal(change.powerChange)} · Members ${formatSignedNumber(change.memberChange)} · ${t('kingdomP7B.baseline', { date: formatDate(change.baselineCapturedAt) })}`;
}

function freshnessLabel(row: IntelligenceRow): string {
  if (row.freshness === 'missing') return 'Missing observation';
  if (row.observationAgeDays === null) return stateLabel(row.freshness);
  return `${stateLabel(row.freshness)} · ${row.observationAgeDays} day${row.observationAgeDays === 1 ? '' : 's'} old`;
}
</script>

<template>
  <Head :title="`${t('kingdomP7B.intelligenceTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link
          class="text-sm font-semibold text-[var(--ks-gold)] hover:text-[var(--ks-gold)]"
          href="/alliance/kingdom-alliances"
        >
          ← Tracked alliances
        </Link>
        <p class="mt-5 text-sm font-semibold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          Kingdom {{ alliance.kingdom ?? 'not set' }}
        </p>
        <h1 class="mt-2 text-3xl font-bold">{{ t('kingdomP7B.intelligenceTitle') }}</h1>
        <p class="mt-2 max-w-4xl text-sm text-[var(--ks-text-secondary)]">
          Descriptive operational trends derived only from accepted observations and explicit
          diplomacy state. Missing values remain missing; this view does not calculate threat,
          desirability, target, or composite scores and never recommends or executes diplomacy or
          transfer actions.
        </p>
      </div>
      <Link
        v-if="canManage"
        class="rounded-lg border border-cyan-800 px-4 py-2 text-sm font-semibold text-[var(--ks-gold)] hover:border-cyan-600"
        href="/alliance/kingdom-alliances/manage"
      >
        {{ t('kingdomP7B.manageTracking') }}
      </Link>
    </header>

    <section
      class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
      aria-label="{{ t('kingdomP7B.intelligenceTitle') }} summary"
    >
      <div class="ks-surface p-5">
        <p class="text-sm text-[var(--ks-text-secondary)]">{{ t('kingdomP7B.activeTracked') }}</p>
        <p class="mt-2 text-3xl font-bold">{{ intelligence.summary.activeTrackedAlliances }}</p>
      </div>
      <div class="ks-surface p-5">
        <p class="text-sm text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7B.currentObservations') }}
        </p>
        <p class="mt-2 text-3xl font-bold">{{ intelligence.summary.observationQuality.current }}</p>
        <p class="mt-2 text-xs text-[var(--ks-text-muted)]">
          Current means captured within
          {{ intelligence.summary.observationQuality.staleAfterDays }} days.
        </p>
      </div>
      <div class="ks-surface p-5">
        <p class="text-sm text-[var(--ks-text-secondary)]">{{ t('kingdomP7B.staleMissing') }}</p>
        <p class="mt-2 text-3xl font-bold">
          {{ intelligence.summary.observationQuality.stale }} /
          {{ intelligence.summary.observationQuality.missing }}
        </p>
      </div>
      <div class="ks-surface p-5">
        <p class="text-sm text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7B.relationshipsReview') }}
        </p>
        <p class="mt-2 text-3xl font-bold">{{ intelligence.summary.relationshipsNeedingReview }}</p>
        <p class="mt-2 text-xs text-[var(--ks-text-muted)]">
          Review/expiry dates are advisory and never change diplomacy automatically.
        </p>
      </div>
    </section>

    <section class="ks-surface mt-6 p-5" aria-labelledby="diplomacy-summary">
      <h2 id="diplomacy-summary" class="text-lg font-semibold">
        {{ t('kingdomP7B.diplomacyStates') }}
      </h2>
      <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <div
          v-for="(count, state) in intelligence.summary.diplomacyStates"
          :key="state"
          class="rounded-xl bg-slate-950/60 p-3 text-center"
        >
          <dt class="text-xs text-[var(--ks-text-muted)]">{{ stateLabel(state) }}</dt>
          <dd class="mt-1 text-2xl font-bold">{{ count }}</dd>
        </div>
      </dl>
    </section>

    <section
      v-if="canManage && intelligence.managerSummary"
      class="mt-6 grid gap-4 md:grid-cols-2"
      aria-label="Private contact diagnostics"
    >
      <div class="ks-surface p-5">
        <p class="text-sm text-[var(--ks-text-secondary)]">{{ t('kingdomP7B.activeContact') }}</p>
        <p class="mt-2 text-3xl font-bold">
          {{ intelligence.managerSummary.trackedWithActiveContact }}
        </p>
        <p class="mt-2 text-xs text-[var(--ks-text-muted)]">
          {{ t('kingdomP7B.managerDiagnostic') }}
        </p>
      </div>
      <div class="ks-surface p-5">
        <p class="text-sm text-[var(--ks-text-secondary)]">{{ t('kingdomP7B.verificationDue') }}</p>
        <p class="mt-2 text-3xl font-bold">
          {{ intelligence.managerSummary.trackedWithVerificationDue }}
        </p>
        <p class="mt-2 text-xs text-[var(--ks-text-muted)]">
          Due means an active contact is unverified or last verified more than
          {{ intelligence.managerSummary.verificationStaleAfterDays }} days ago.
        </p>
      </div>
    </section>

    <section class="ks-surface mt-8 p-5" aria-labelledby="intelligence-filters">
      <h2 id="intelligence-filters" class="text-lg font-semibold">
        {{ t('kingdomP7B.filtersTitle') }}
      </h2>
      <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">
        Default order is alphabetical. Optional factual sorting changes navigation order only; it is
        not a best/worst, threat, target, or desirability ranking.
      </p>

      <form class="mt-5 grid gap-4 md:grid-cols-3 xl:grid-cols-6" @submit.prevent="applyFilters">
        <div>
          <label class="text-sm font-medium" for="tracking-filter">{{
            t('kingdomP7B.trackingState')
          }}</label>
          <select
            id="tracking-filter"
            v-model="filters.tracking"
            name="tracking"
            class="ks-input mt-2 w-full"
          >
            <option value="active">Active</option>
            <option value="archived">Archived</option>
            <option value="all">All</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="freshness-filter">{{
            t('kingdomP7B.observationFreshness')
          }}</label>
          <select
            id="freshness-filter"
            v-model="filters.freshness"
            name="freshness"
            class="ks-input mt-2 w-full"
          >
            <option value="all">All</option>
            <option value="current">Current</option>
            <option value="stale">Stale</option>
            <option value="missing">Missing</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="diplomacy-filter">{{
            t('kingdomP7B.diplomacyState')
          }}</label>
          <select
            id="diplomacy-filter"
            v-model="filters.diplomacy"
            name="diplomacy"
            class="ks-input mt-2 w-full"
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
          <label class="text-sm font-medium" for="sort-filter">{{ t('kingdomP7B.sortBy') }}</label>
          <select id="sort-filter" v-model="filters.sort" name="sort" class="ks-input mt-2 w-full">
            <option value="name">Name</option>
            <option value="tag">Tag</option>
            <option value="power">Latest power</option>
            <option value="members">Latest members</option>
            <option value="age">Observation age</option>
            <option value="diplomacy">{{ t('kingdomP7B.diplomacyState') }}</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="direction-filter">{{
            t('kingdomP7B.direction')
          }}</label>
          <select
            id="direction-filter"
            v-model="filters.direction"
            name="direction"
            class="ks-input mt-2 w-full"
          >
            <option value="asc">Ascending</option>
            <option value="desc">Descending</option>
          </select>
        </div>
        <div class="flex items-end gap-2">
          <button
            class="rounded-lg bg-[var(--ks-blue)] px-4 py-2 font-semibold text-white"
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

    <section class="ks-surface mt-6 p-5" aria-labelledby="trend-rules">
      <h2 id="trend-rules" class="text-lg font-semibold">{{ t('kingdomP7B.trendRules') }}</h2>
      <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
        The current point is the latest accepted observation at or before the dashboard time. Prior
        change uses the immediately preceding accepted point. For an N-day trend, the baseline is
        the closest accepted observation at or before the N-day target but no older than 2N days. A
        newer point is never substituted for the target, older history outside the bounded window is
        ignored, and missing history is never interpolated.
      </p>
      <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
        Current windows: {{ intelligence.windows.sevenDay.days }}–{{
          intelligence.windows.sevenDay.oldestDays
        }}
        days for the 7-day comparison and {{ intelligence.windows.thirtyDay.days }}–{{
          intelligence.windows.thirtyDay.oldestDays
        }}
        days for the 30-day comparison.
      </p>
    </section>

    <section class="ks-surface mt-8 overflow-hidden" aria-labelledby="alliance-intelligence-table">
      <div class="border-b border-[var(--ks-border)] px-5 py-4">
        <h2 id="alliance-intelligence-table" class="text-xl font-semibold">
          {{ t('kingdomP7B.allianceIntelligence') }}
        </h2>
        <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">
          {{ intelligence.rows.length }} record(s) match the current filters. Zero is a recorded
          value; “missing” means no supported value exists.
        </p>
      </div>

      <div v-if="intelligence.rows.length" class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <caption class="sr-only">
            Tracked game-side alliance descriptive intelligence
          </caption>
          <thead class="bg-slate-950/60 text-[var(--ks-text-secondary)]">
            <tr>
              <th class="px-4 py-3">{{ t('kingdomP7B.name') }}</th>
              <th class="px-4 py-3">{{ t('kingdomP7B.latestFacts') }}</th>
              <th class="px-4 py-3">Data quality</th>
              <th class="px-4 py-3">{{ t('kingdomP7B.priorChange') }}</th>
              <th class="px-4 py-3">{{ t('kingdomP7B.sevenDay') }}</th>
              <th class="px-4 py-3">{{ t('kingdomP7B.thirtyDay') }}</th>
              <th class="px-4 py-3">{{ t('kingdomP7B.diplomacy') }}</th>
              <th v-if="canManage" class="px-4 py-3">{{ t('kingdomP7B.contactsColumn') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="row in intelligence.rows" :key="row.historyUrl">
              <td class="px-4 py-4 align-top">
                <Link
                  class="font-semibold text-[var(--ks-gold)] hover:text-[var(--ks-gold)]"
                  :href="row.historyUrl"
                >
                  {{ row.name }}
                </Link>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  {{ row.tag ?? 'No tag recorded' }}
                </p>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
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
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
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
              <td class="px-4 py-4 align-top text-[var(--ks-text-secondary)]">
                {{ changeText(row.priorChange) }}
              </td>
              <td class="px-4 py-4 align-top text-[var(--ks-text-secondary)]">
                {{ changeText(row.sevenDayChange) }}
              </td>
              <td class="px-4 py-4 align-top text-[var(--ks-text-secondary)]">
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
                <p v-if="row.diplomacy.reviewAt" class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  Review {{ formatDate(row.diplomacy.reviewAt) }}
                </p>
                <p v-if="row.diplomacy.expiresAt" class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  Expiry {{ formatDate(row.diplomacy.expiresAt) }}
                </p>
                <Link
                  v-if="canManage && row.diplomacyUrl"
                  class="mt-2 inline-block text-xs font-semibold text-[var(--ks-gold)] hover:text-[var(--ks-gold)]"
                  :href="row.diplomacyUrl"
                >
                  Manage diplomacy
                </Link>
              </td>
              <td v-if="canManage" class="px-4 py-4 align-top text-slate-300">
                <template v-if="row.contactDiagnostics">
                  <p>{{ row.contactDiagnostics.activeContacts }} active contact(s)</p>
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                    {{ row.contactDiagnostics.verificationDue }} verification due
                  </p>
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                    Latest verified {{ formatDate(row.contactDiagnostics.latestVerifiedAt) }}
                  </p>
                </template>
                <Link
                  v-if="row.contactsUrl"
                  class="mt-2 inline-block text-xs font-semibold text-[var(--ks-gold)] hover:text-[var(--ks-gold)]"
                  :href="row.contactsUrl"
                >
                  Contact directory
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-else class="p-8 text-sm text-[var(--ks-text-secondary)]">
        No tracked alliances match the selected filters.
      </p>
    </section>

    <p class="mt-6 text-xs text-[var(--ks-text-muted)]">
      Calculated at {{ formatDate(intelligence.asOf) }} from tenant-owned accepted observations and
      explicit human-maintained diplomacy state.
    </p>
  </AppLayout>
</template>
