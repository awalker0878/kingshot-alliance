<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

type LatestSnapshot = {
  observedName: string;
  power: string;
  progressionLevel: string | null;
  observedAllianceTag: string | null;
  capturedAt: string;
  source: string;
};

type Entry = {
  id: string;
  gamePlayerId: string | null;
  name: string;
  gameRole: string | null;
  state: string;
  joinedAt: string | null;
  leftAt: string | null;
  lastObservedAt: string | null;
  source: string;
  membership: { name: string } | null;
  latestSnapshot: LatestSnapshot | null;
};

type Filters = {
  q: string;
  state: string;
  linkage: string;
  role: string;
  observation: string;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  canManage: boolean;
  entries: Entry[];
  filters: Filters;
  roleOptions: string[];
  staleAfterDays: number;
}>();

const { locale, t, formatDate, formatNumber } = useLocale();
const filters = reactive<Filters>({ ...props.filters });

const snapshotCounts = computed(() => {
  const counts = { current: 0, stale: 0, missing: 0 };
  for (const entry of props.entries) counts[snapshotState(entry)] += 1;
  return counts;
});

function applyFilters(): void {
  router.get(
    '/alliance/roster',
    Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')),
    { preserveScroll: true, preserveState: true, replace: true },
  );
}

function clearFilters(): void {
  filters.q = '';
  filters.state = '';
  filters.linkage = '';
  filters.role = '';
  filters.observation = '';
  applyFilters();
}

function snapshotState(entry: Entry): 'current' | 'stale' | 'missing' {
  if (!entry.latestSnapshot) return 'missing';
  const staleAt = Date.now() - props.staleAfterDays * 24 * 60 * 60 * 1000;
  return new Date(entry.latestSnapshot.capturedAt).getTime() < staleAt ? 'stale' : 'current';
}

function stateLabel(value: string): string {
  const key = `roster.${value}`;
  const translated = t(key);
  return translated === key ? value.replaceAll('_', ' ') : translated;
}

function formatPower(value: string): string {
  try {
    return new Intl.NumberFormat(locale.value).format(BigInt(value));
  } catch {
    return value;
  }
}

function formatCaptured(value: string): string {
  return formatDate(value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

function freshnessClass(value: string): string {
  if (value === 'current') return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (value === 'stale') return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
  return 'border-red-400/25 bg-red-500/10 text-red-200';
}
</script>

<template>
  <Head :title="`${t('roster.title')} · ${alliance.name}`" />

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div class="max-w-3xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('roster.eyebrow', { kingdom: alliance.kingdom ?? t('roster.kingdomNotSet') }) }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">{{ t('roster.title') }}</h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('roster.subtitle') }}
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <Link
          class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-border-strong)] hover:text-white"
          href="/alliance/roster/intelligence"
        >
          {{ t('roster.intelligence') }}
        </Link>
        <Link
          v-if="canManage"
          class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--ks-blue-strong)]"
          href="/alliance/roster/manage"
        >
          {{ t('roster.manage') }}
        </Link>
      </div>
    </header>

    <section
      class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
      :aria-label="t('roster.results')"
    >
      <article class="ks-surface p-4 sm:p-5">
        <p class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
          {{ t('roster.results') }}
        </p>
        <p class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(entries.length) }}</p>
      </article>
      <article class="ks-surface p-4 sm:p-5">
        <p class="text-xs font-bold tracking-[0.12em] text-green-300 uppercase">
          {{ t('roster.currentSnapshots') }}
        </p>
        <p class="ks-display mt-2 text-3xl font-semibold">
          {{ formatNumber(snapshotCounts.current) }}
        </p>
      </article>
      <article class="ks-surface p-4 sm:p-5">
        <p class="text-xs font-bold tracking-[0.12em] text-amber-300 uppercase">
          {{ t('roster.staleSnapshots') }}
        </p>
        <p class="ks-display mt-2 text-3xl font-semibold">
          {{ formatNumber(snapshotCounts.stale) }}
        </p>
      </article>
      <article class="ks-surface p-4 sm:p-5">
        <p class="text-xs font-bold tracking-[0.12em] text-red-300 uppercase">
          {{ t('roster.missingSnapshots') }}
        </p>
        <p class="ks-display mt-2 text-3xl font-semibold">
          {{ formatNumber(snapshotCounts.missing) }}
        </p>
      </article>
    </section>

    <section class="ks-surface mt-6 p-5 sm:p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
            {{ t('roster.filters') }}
          </p>
          <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
            {{ t('roster.freshnessHelp', { days: staleAfterDays }) }}
          </p>
        </div>
      </div>

      <form
        class="mt-5 grid gap-4 lg:grid-cols-6"
        :aria-label="t('roster.filters')"
        @submit.prevent="applyFilters"
      >
        <div class="lg:col-span-2">
          <label class="text-sm font-medium" for="roster-search">{{ t('roster.search') }}</label>
          <input
            id="roster-search"
            v-model="filters.q"
            class="mt-1 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
            maxlength="160"
            :placeholder="t('roster.searchPlaceholder')"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="roster-state-filter">{{
            t('roster.state')
          }}</label>
          <select
            id="roster-state-filter"
            v-model="filters.state"
            class="mt-1 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
          >
            <option value="">{{ t('roster.allStates') }}</option>
            <option value="active">{{ t('roster.active') }}</option>
            <option value="tracked">{{ t('roster.tracked') }}</option>
            <option value="left">{{ t('roster.left') }}</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="roster-linkage-filter">{{
            t('roster.linkage')
          }}</label>
          <select
            id="roster-linkage-filter"
            v-model="filters.linkage"
            class="mt-1 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
          >
            <option value="">{{ t('roster.allLinks') }}</option>
            <option value="linked">{{ t('roster.linked') }}</option>
            <option value="unlinked">{{ t('roster.unlinked') }}</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="roster-role-filter">{{ t('roster.role') }}</label>
          <select
            id="roster-role-filter"
            v-model="filters.role"
            class="mt-1 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
          >
            <option value="">{{ t('roster.allRoles') }}</option>
            <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="roster-observation-filter">{{
            t('roster.freshness')
          }}</label>
          <select
            id="roster-observation-filter"
            v-model="filters.observation"
            class="mt-1 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
          >
            <option value="">{{ t('roster.anyFreshness') }}</option>
            <option value="current">{{ t('roster.current') }}</option>
            <option value="stale">{{ t('roster.stale') }}</option>
            <option value="missing">{{ t('roster.missing') }}</option>
          </select>
        </div>
        <div class="flex flex-wrap items-end gap-3 lg:col-span-6">
          <button
            class="min-h-11 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white"
            type="submit"
          >
            {{ t('roster.apply') }}
          </button>
          <button
            class="min-h-11 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-text-secondary)]"
            type="button"
            @click="clearFilters"
          >
            {{ t('roster.clear') }}
          </button>
        </div>
      </form>
    </section>

    <section class="ks-surface mt-6 overflow-hidden">
      <div v-if="entries.length" class="overflow-x-auto">
        <table class="w-full min-w-[70rem] text-start text-sm">
          <thead class="border-b border-[var(--ks-border)] bg-black/20 text-[var(--ks-text-muted)]">
            <tr>
              <th class="px-4 py-3 text-start">{{ t('roster.player') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.gameId') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.role') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.state') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.power') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.progression') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.allianceTag') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.snapshotCaptured') }}</th>
              <th class="px-4 py-3 text-start">{{ t('roster.linkedMember') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="entry in entries" :key="entry.id" class="transition hover:bg-white/[0.025]">
              <td class="px-4 py-4 font-semibold">
                <Link
                  class="text-[var(--ks-blue-strong)] hover:text-white"
                  :href="`/alliance/roster/${entry.id}/history`"
                >
                  {{ entry.latestSnapshot?.observedName ?? entry.name }}
                </Link>
              </td>
              <td class="px-4 py-4 text-[var(--ks-text-muted)]">{{ entry.gamePlayerId ?? '—' }}</td>
              <td class="px-4 py-4 text-[var(--ks-text-secondary)]">{{ entry.gameRole ?? '—' }}</td>
              <td class="px-4 py-4">
                <span class="capitalize">{{ stateLabel(entry.state) }}</span>
              </td>
              <td class="px-4 py-4 font-semibold">
                {{ entry.latestSnapshot ? formatPower(entry.latestSnapshot.power) : '—' }}
              </td>
              <td class="px-4 py-4 text-[var(--ks-text-secondary)]">
                {{ entry.latestSnapshot?.progressionLevel ?? '—' }}
              </td>
              <td class="px-4 py-4 text-[var(--ks-text-secondary)]">
                {{ entry.latestSnapshot?.observedAllianceTag ?? '—' }}
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-col gap-1.5">
                  <span
                    :class="freshnessClass(snapshotState(entry))"
                    class="w-fit rounded-full border px-2.5 py-1 text-xs font-semibold"
                    >{{ stateLabel(snapshotState(entry)) }}</span
                  >
                  <span class="text-xs text-[var(--ks-text-muted)]">{{
                    entry.latestSnapshot ? formatCaptured(entry.latestSnapshot.capturedAt) : '—'
                  }}</span>
                </div>
              </td>
              <td class="px-4 py-4 text-[var(--ks-text-secondary)]">
                {{ entry.membership?.name ?? t('roster.unlinked') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="p-8 text-center">
        <p class="ks-display text-xl font-semibold">{{ t('roster.noResults') }}</p>
        <p class="mt-2 text-sm text-[var(--ks-text-muted)]">{{ t('roster.noResultsBody') }}</p>
      </div>
    </section>
  </AppLayout>
</template>
