<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

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

const { t, formatDate: localeDate } = useLocale();
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
  if (value === null) return t('kingdomP7B.missing');

  const negative = value.startsWith('-');
  const unsigned = negative ? value.slice(1) : value;
  const grouped = unsigned.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

  return negative ? `-${grouped}` : grouped;
}

function formatSignedDecimal(value: string | null): string {
  if (value === null) return t('kingdomP7B.missing');
  if (value.startsWith('-') || value === '0') return formatDecimal(value);
  return `+${formatDecimal(value)}`;
}

function formatSignedNumber(value: number | null): string {
  if (value === null) return t('kingdomP7B.missing');
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
  if (value === 'active') return t('kingdomP7B.active');
  if (value === 'archived') return t('kingdomP7B.archived');
  if (value === 'current') return t('kingdomP7B.current');
  if (value === 'stale') return t('kingdomP7B.stale');
  if (value === 'missing') return t('kingdomP7B.missing');
  return value.replaceAll('_', ' ');
}

function changeText(change: Change | null): string {
  if (change === null) return t('kingdomP7B.insufficientHistory');
  return `${t('kingdomP7B.power')} ${formatSignedDecimal(change.powerChange)} · ${t('kingdomP7B.members')} ${formatSignedNumber(change.memberChange)} · ${t('kingdomP7B.baseline', { date: formatDate(change.baselineCapturedAt) })}`;
}

function freshnessLabel(row: IntelligenceRow): string {
  if (row.freshness === 'missing') return t('kingdomP7B.missingObservation');
  if (row.observationAgeDays === null) return stateLabel(row.freshness);
  return `${stateLabel(row.freshness)} · ${t('kingdomP7B.daysOld', { days: row.observationAgeDays })}`;
}
</script>

<template>
  <Head :title="`${t('kingdomP7B.intelligenceTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link
          class="text-sm font-semibold text-[var(--ks-gold)] hover:text-[var(--ks-gold)]"
          href="/alliance/kingdom-alliances"
        >
          ← {{ t('kingdomP7A.overviewTitle') }}
        </Link>
        <p class="mt-5 text-sm font-semibold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('kingdomP7A.kingdom') }} {{ alliance.kingdom ?? t('kingdomP7B.notSet') }}
        </p>
        <h1 class="mt-2 text-3xl font-bold">{{ t('kingdomP7B.intelligenceTitle') }}</h1>
        <p class="mt-2 max-w-4xl text-sm text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7B.intelligenceSubtitle') }}
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
      :aria-label="t('kingdomP7B.intelligenceTitle')"
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
          {{
            t('kingdomP7B.currentWithinDays', {
              days: intelligence.summary.observationQuality.staleAfterDays,
            })
          }}
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
          {{ t('kingdomP7B.reviewAdvisory') }}
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
          class="rounded-xl bg-[rgba(7,12,13,.78)] p-3 text-center"
        >
          <dt class="text-xs text-[var(--ks-text-muted)]">{{ stateLabel(state) }}</dt>
          <dd class="mt-1 text-2xl font-bold">{{ count }}</dd>
        </div>
      </dl>
    </section>

    <section
      v-if="canManage && intelligence.managerSummary"
      class="mt-6 grid gap-4 md:grid-cols-2"
      :aria-label="t('kingdomP7B.contacts')"
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
          {{
            t('kingdomP7B.verificationDueHelp', {
              days: intelligence.managerSummary.verificationStaleAfterDays,
            })
          }}
        </p>
      </div>
    </section>

    <section class="ks-surface mt-8 p-5" aria-labelledby="intelligence-filters">
      <h2 id="intelligence-filters" class="text-lg font-semibold">
        {{ t('kingdomP7B.filtersTitle') }}
      </h2>
      <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">
        {{ t('kingdomP7B.filtersHelp') }}
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
            <option value="active">{{ t('kingdomP7B.active') }}</option>
            <option value="archived">{{ t('kingdomP7B.archived') }}</option>
            <option value="all">{{ t('kingdomP7B.all') }}</option>
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
            <option value="all">{{ t('kingdomP7B.all') }}</option>
            <option value="current">{{ t('kingdomP7B.current') }}</option>
            <option value="stale">{{ t('kingdomP7B.stale') }}</option>
            <option value="missing">{{ t('kingdomP7B.missing') }}</option>
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
            <option value="all">{{ t('kingdomP7B.all') }}</option>
            <option value="unknown">{{ t('kingdomP7B.unknown') }}</option>
            <option value="neutral">{{ t('kingdomP7B.neutral') }}</option>
            <option value="friendly">{{ t('kingdomP7B.friendly') }}</option>
            <option value="nap">{{ t('kingdomP7B.nap') }}</option>
            <option value="ally">{{ t('kingdomP7B.ally') }}</option>
            <option value="rival">{{ t('kingdomP7B.rival') }}</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="sort-filter">{{ t('kingdomP7B.sortBy') }}</label>
          <select id="sort-filter" v-model="filters.sort" name="sort" class="ks-input mt-2 w-full">
            <option value="name">{{ t('kingdomP7B.name') }}</option>
            <option value="tag">{{ t('kingdomP7B.tag') }}</option>
            <option value="power">{{ t('kingdomP7B.latestPowerSort') }}</option>
            <option value="members">{{ t('kingdomP7B.latestMembersSort') }}</option>
            <option value="age">{{ t('kingdomP7B.observationAge') }}</option>
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
            <option value="asc">{{ t('kingdomP7B.ascending') }}</option>
            <option value="desc">{{ t('kingdomP7B.descending') }}</option>
          </select>
        </div>
        <div class="flex items-end gap-2">
          <button
            class="rounded-lg bg-[var(--ks-blue)] px-4 py-2 font-semibold text-[var(--ks-ivory)]"
            type="submit"
          >
            {{ t('kingdomP7B.apply') }}
          </button>
          <button
            class="rounded-lg border border-[var(--ks-border)] px-4 py-2 font-semibold text-[var(--ks-ivory)]"
            type="button"
            @click="resetFilters"
          >
            {{ t('kingdomP7B.reset') }}
          </button>
        </div>
      </form>
    </section>

    <section class="ks-surface mt-6 p-5" aria-labelledby="trend-rules">
      <h2 id="trend-rules" class="text-lg font-semibold">{{ t('kingdomP7B.trendRules') }}</h2>
      <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
        {{ t('kingdomP7B.trendRulesHelp') }}
      </p>
      <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
        {{
          t('kingdomP7B.windowsHelp', {
            seven: intelligence.windows.sevenDay.days,
            sevenOldest: intelligence.windows.sevenDay.oldestDays,
            thirty: intelligence.windows.thirtyDay.days,
            thirtyOldest: intelligence.windows.thirtyDay.oldestDays,
          })
        }}
      </p>
    </section>

    <section class="ks-surface mt-8 overflow-hidden" aria-labelledby="alliance-intelligence-table">
      <div class="border-b border-[var(--ks-border)] px-5 py-4">
        <h2 id="alliance-intelligence-table" class="text-xl font-semibold">
          {{ t('kingdomP7B.allianceIntelligence') }}
        </h2>
      </div>

      <div v-if="intelligence.rows.length" class="grid gap-3 p-4 lg:hidden">
        <article
          v-for="row in intelligence.rows"
          :key="row.historyUrl"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/10 p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <Link class="font-semibold text-[var(--ks-gold)]" :href="row.historyUrl">{{
                row.name
              }}</Link>
              <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                {{ row.tag ?? '—' }} · {{ t('kingdomP7A.kingdom') }} {{ row.kingdom }}
              </p>
            </div>
            <span class="text-xs font-semibold">{{ freshnessLabel(row) }}</span>
          </div>
          <div v-if="row.latestObservation" class="mt-3 grid grid-cols-2 gap-3 text-sm">
            <div>
              <p class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7B.power') }}</p>
              <p>{{ formatDecimal(row.latestObservation.power) }}</p>
            </div>
            <div>
              <p class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7B.members') }}</p>
              <p>{{ row.latestObservation.memberCount ?? t('kingdomP7B.missing') }}</p>
            </div>
          </div>
          <div class="mt-3 space-y-1 text-xs text-[var(--ks-text-secondary)]">
            <p>{{ t('kingdomP7B.priorChange') }}: {{ changeText(row.priorChange) }}</p>
            <p>{{ t('kingdomP7B.sevenDay') }}: {{ changeText(row.sevenDayChange) }}</p>
            <p>{{ t('kingdomP7B.thirtyDay') }}: {{ changeText(row.thirtyDayChange) }}</p>
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <span
              class="rounded-full border border-[var(--ks-border)] px-2 py-1 text-xs font-semibold"
              >{{ stateLabel(row.diplomacy.state) }}</span
            ><span
              v-if="row.diplomacy.needsReview"
              class="rounded-full border border-amber-400/25 bg-amber-500/10 px-2 py-1 text-xs font-semibold text-amber-200"
              >{{ t('kingdomP7B.reviewDue') }}</span
            >
          </div>
          <div class="mt-3 flex flex-wrap gap-3 text-xs">
            <Link :href="row.historyUrl" class="font-semibold text-[var(--ks-gold)]">{{
              t('kingdomP7B.history')
            }}</Link
            ><Link
              v-if="canManage && row.diplomacyUrl"
              :href="row.diplomacyUrl"
              class="font-semibold text-[var(--ks-gold)]"
              >{{ t('kingdomP7B.diplomacy') }}</Link
            ><Link
              v-if="canManage && row.contactsUrl"
              :href="row.contactsUrl"
              class="font-semibold text-[var(--ks-gold)]"
              >{{ t('kingdomP7B.contacts') }}</Link
            >
          </div>
        </article>
      </div>

      <div v-if="intelligence.rows.length" class="hidden overflow-x-auto lg:block">
        <table class="min-w-full text-left text-sm">
          <caption class="sr-only">
            {{
              t('kingdomP7B.allianceIntelligence')
            }}
          </caption>
          <thead class="bg-[rgba(7,12,13,.78)] text-[var(--ks-text-secondary)]">
            <tr>
              <th class="px-4 py-3">{{ t('kingdomP7B.name') }}</th>
              <th class="px-4 py-3">{{ t('kingdomP7B.latestFacts') }}</th>
              <th class="px-4 py-3">{{ t('kingdomP7B.observationFreshness') }}</th>
              <th class="px-4 py-3">{{ t('kingdomP7B.priorChange') }}</th>
              <th class="px-4 py-3">{{ t('kingdomP7B.sevenDay') }}</th>
              <th class="px-4 py-3">{{ t('kingdomP7B.thirtyDay') }}</th>
              <th class="px-4 py-3">{{ t('kingdomP7B.diplomacy') }}</th>
              <th v-if="canManage" class="px-4 py-3">{{ t('kingdomP7B.contactsColumn') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="row in intelligence.rows" :key="row.historyUrl">
              <td class="px-4 py-4 align-top">
                <Link
                  class="font-semibold text-[var(--ks-gold)] hover:text-[var(--ks-gold)]"
                  :href="row.historyUrl"
                >
                  {{ row.name }}
                </Link>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  {{ row.tag ?? t('kingdomP7A.noTag') }}
                </p>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  {{ stateLabel(row.trackingState) }} · {{ t('kingdomP7A.kingdom') }}
                  {{ row.kingdom }}
                </p>
                <p v-if="!row.contextCurrent" class="mt-1 text-xs font-semibold text-amber-300">
                  {{ t('kingdomP7A.historicalContext') }}
                </p>
              </td>
              <td class="px-4 py-4 align-top text-[var(--ks-muted)]">
                <template v-if="row.latestObservation">
                  <p>
                    {{ t('kingdomP7B.power') }} {{ formatDecimal(row.latestObservation.power) }}
                  </p>
                  <p>
                    {{ t('kingdomP7B.members') }}
                    {{ row.latestObservation.memberCount ?? t('kingdomP7B.missing') }}
                  </p>
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                    {{ formatDate(row.latestObservation.capturedAt) }}
                  </p>
                </template>
                <span v-else>{{ t('kingdomP7A.noAcceptedObservation') }}</span>
              </td>
              <td class="px-4 py-4 align-top text-[var(--ks-muted)]">
                <span
                  class="rounded-full border border-[var(--ks-border)] px-2 py-1 text-xs font-semibold"
                >
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
              <td class="px-4 py-4 align-top text-[var(--ks-muted)]">
                <p class="font-semibold">{{ stateLabel(row.diplomacy.state) }}</p>
                <p
                  v-if="row.diplomacy.needsReview"
                  class="mt-1 text-xs font-semibold text-amber-300"
                >
                  {{ t('kingdomP7B.reviewDue') }}
                </p>
                <p v-if="row.diplomacy.reviewAt" class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  {{ t('kingdomP7B.review') }} {{ formatDate(row.diplomacy.reviewAt) }}
                </p>
                <p v-if="row.diplomacy.expiresAt" class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  {{ t('kingdomP7B.expiry') }} {{ formatDate(row.diplomacy.expiresAt) }}
                </p>
                <Link
                  v-if="canManage && row.diplomacyUrl"
                  class="mt-2 inline-block text-xs font-semibold text-[var(--ks-gold)] hover:text-[var(--ks-gold)]"
                  :href="row.diplomacyUrl"
                >
                  {{ t('kingdomP7B.diplomacy') }}
                </Link>
              </td>
              <td v-if="canManage" class="px-4 py-4 align-top text-[var(--ks-muted)]">
                <template v-if="row.contactDiagnostics">
                  <p>
                    {{
                      t('kingdomP7B.activeContacts', {
                        count: row.contactDiagnostics.activeContacts,
                      })
                    }}
                  </p>
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                    {{
                      t('kingdomP7B.verificationDueShort', {
                        count: row.contactDiagnostics.verificationDue,
                      })
                    }}
                  </p>
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                    {{
                      t('kingdomP7B.latestVerified', {
                        date: formatDate(row.contactDiagnostics.latestVerifiedAt),
                      })
                    }}
                  </p>
                </template>
                <Link
                  v-if="row.contactsUrl"
                  class="mt-2 inline-block text-xs font-semibold text-[var(--ks-gold)] hover:text-[var(--ks-gold)]"
                  :href="row.contactsUrl"
                >
                  {{ t('kingdomP7B.directory') }}
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-else class="p-8 text-sm text-[var(--ks-text-secondary)]">
        {{ t('kingdomP7B.noRows') }}
      </p>
    </section>

    <p class="mt-6 text-xs text-[var(--ks-text-muted)]">
      {{ t('kingdomP7B.asOf', { date: formatDate(intelligence.asOf) }) }}
    </p>
  </AppLayout>
</template>
