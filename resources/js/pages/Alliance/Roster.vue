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

const linkedResults = computed(
  () => props.entries.filter((entry) => entry.membership !== null).length,
);

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

function rosterStateClass(value: string): string {
  if (value === 'active') return 'border-green-400/20 bg-green-500/10 text-green-200';
  if (value === 'tracked') return 'border-blue-400/20 bg-blue-500/10 text-blue-200';
  return 'border-slate-400/20 bg-slate-500/10 text-slate-300';
}

function snapshotPercent(value: number): string {
  if (props.entries.length === 0) return '0%';
  return `${Math.round((value / props.entries.length) * 100)}%`;
}
</script>

<template>
  <Head :title="`${t('roster.title')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
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
          class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-gold)] hover:text-white"
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

    <section class="ks-surface-gold mt-6 overflow-hidden" :aria-label="t('roster.freshness')">
      <div class="border-b border-[var(--ks-border)] px-4 py-3 sm:px-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
            {{ t('roster.freshness') }}
          </p>
          <p class="text-xs text-[var(--ks-text-muted)]">
            {{ t('roster.freshnessHelp', { days: staleAfterDays }) }}
          </p>
        </div>
      </div>
      <div
        class="grid grid-cols-2 divide-x divide-y divide-[var(--ks-border)] sm:grid-cols-4 sm:divide-y-0"
      >
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('roster.results') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(entries.length) }}</p>
        </article>
        <article class="p-4 sm:p-5">
          <p class="text-[0.68rem] font-bold tracking-[0.12em] text-green-300 uppercase">
            {{ t('roster.current') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold text-green-100">
            {{ formatNumber(snapshotCounts.current) }}
          </p>
        </article>
        <article class="p-4 sm:p-5">
          <p class="text-[0.68rem] font-bold tracking-[0.12em] text-amber-300 uppercase">
            {{ t('roster.stale') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold text-amber-100">
            {{ formatNumber(snapshotCounts.stale) }}
          </p>
        </article>
        <article class="p-4 sm:p-5">
          <p class="text-[0.68rem] font-bold tracking-[0.12em] text-red-300 uppercase">
            {{ t('roster.missing') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold text-red-100">
            {{ formatNumber(snapshotCounts.missing) }}
          </p>
        </article>
      </div>
    </section>

    <div class="mt-6 grid gap-5 xl:grid-cols-4">
      <div class="min-w-0 space-y-5 xl:col-span-3">
        <section class="ks-surface p-4 sm:p-5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
              {{ t('roster.filters') }}
            </p>
          </div>

          <form
            class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-6"
            :aria-label="t('roster.filters')"
            @submit.prevent="applyFilters"
          >
            <div class="md:col-span-2 xl:col-span-2">
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="roster-search"
              >
                {{ t('roster.search') }}
              </label>
              <input
                id="roster-search"
                v-model="filters.q"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm transition outline-none focus:border-[var(--ks-blue-strong)]"
                maxlength="160"
                :placeholder="t('roster.searchPlaceholder')"
              />
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="roster-state-filter"
              >
                {{ t('roster.state') }}
              </label>
              <select
                id="roster-state-filter"
                v-model="filters.state"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              >
                <option value="">{{ t('roster.allStates') }}</option>
                <option value="active">{{ t('roster.active') }}</option>
                <option value="tracked">{{ t('roster.tracked') }}</option>
                <option value="left">{{ t('roster.left') }}</option>
              </select>
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="roster-linkage-filter"
              >
                {{ t('roster.linkage') }}
              </label>
              <select
                id="roster-linkage-filter"
                v-model="filters.linkage"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              >
                <option value="">{{ t('roster.allLinks') }}</option>
                <option value="linked">{{ t('roster.linked') }}</option>
                <option value="unlinked">{{ t('roster.unlinked') }}</option>
              </select>
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="roster-role-filter"
              >
                {{ t('roster.role') }}
              </label>
              <select
                id="roster-role-filter"
                v-model="filters.role"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              >
                <option value="">{{ t('roster.allRoles') }}</option>
                <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
              </select>
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="roster-observation-filter"
              >
                {{ t('roster.freshness') }}
              </label>
              <select
                id="roster-observation-filter"
                v-model="filters.observation"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              >
                <option value="">{{ t('roster.anyFreshness') }}</option>
                <option value="current">{{ t('roster.current') }}</option>
                <option value="stale">{{ t('roster.stale') }}</option>
                <option value="missing">{{ t('roster.missing') }}</option>
              </select>
            </div>
            <div class="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-6">
              <button
                class="min-h-10 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--ks-blue-strong)]"
                type="submit"
              >
                {{ t('roster.apply') }}
              </button>
              <button
                class="min-h-10 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-border-strong)] hover:text-white"
                type="button"
                @click="clearFilters"
              >
                {{ t('roster.clear') }}
              </button>
            </div>
          </form>
        </section>

        <section class="ks-surface overflow-hidden">
          <div
            class="flex items-center justify-between gap-4 border-b border-[var(--ks-border)] px-4 py-3 sm:px-5"
          >
            <div>
              <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
                {{ t('roster.title') }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                {{ t('roster.results') }}: {{ formatNumber(entries.length) }}
              </p>
            </div>
          </div>

          <div v-if="entries.length" class="lg:hidden">
            <article
              v-for="entry in entries"
              :key="entry.id"
              class="border-b border-[var(--ks-border)] p-4 last:border-b-0"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <Link
                    class="block truncate text-base font-semibold text-[var(--ks-blue-strong)] hover:text-white"
                    :href="`/alliance/roster/${entry.id}/history`"
                  >
                    {{ entry.latestSnapshot?.observedName ?? entry.name }}
                  </Link>
                  <p class="mt-1 truncate text-xs text-[var(--ks-text-muted)]">
                    {{ entry.gamePlayerId ?? t('roster.gameId') }}
                  </p>
                </div>
                <p class="shrink-0 text-end">
                  <span
                    class="block text-[0.65rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
                  >
                    {{ t('roster.power') }}
                  </span>
                  <strong class="mt-1 block text-base">
                    {{ entry.latestSnapshot ? formatPower(entry.latestSnapshot.power) : '—' }}
                  </strong>
                </p>
              </div>

              <div class="mt-3 flex flex-wrap gap-2">
                <span
                  :class="rosterStateClass(entry.state)"
                  class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                >
                  {{ stateLabel(entry.state) }}
                </span>
                <span
                  :class="freshnessClass(snapshotState(entry))"
                  class="rounded-full border px-2.5 py-1 text-xs font-semibold"
                >
                  {{ stateLabel(snapshotState(entry)) }}
                </span>
                <span
                  v-if="entry.gameRole"
                  class="rounded-full border border-purple-400/20 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-200"
                >
                  {{ entry.gameRole }}
                </span>
              </div>

              <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-xs">
                <div>
                  <dt class="text-[var(--ks-text-muted)]">{{ t('roster.progression') }}</dt>
                  <dd class="mt-1 font-medium text-[var(--ks-text-secondary)]">
                    {{ entry.latestSnapshot?.progressionLevel ?? '—' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-text-muted)]">{{ t('roster.allianceTag') }}</dt>
                  <dd class="mt-1 font-medium text-[var(--ks-text-secondary)]">
                    {{ entry.latestSnapshot?.observedAllianceTag ?? '—' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-text-muted)]">{{ t('roster.linkedMember') }}</dt>
                  <dd class="mt-1 truncate font-medium text-[var(--ks-text-secondary)]">
                    {{ entry.membership?.name ?? t('roster.unlinked') }}
                  </dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-text-muted)]">{{ t('roster.snapshotCaptured') }}</dt>
                  <dd class="mt-1 font-medium text-[var(--ks-text-secondary)]">
                    {{
                      entry.latestSnapshot ? formatCaptured(entry.latestSnapshot.capturedAt) : '—'
                    }}
                  </dd>
                </div>
              </dl>
            </article>
          </div>

          <div v-if="entries.length" class="hidden overflow-x-auto lg:block">
            <table class="w-full min-w-[70rem] text-start text-sm">
              <thead
                class="bg-black/25 text-[0.68rem] font-bold tracking-[0.08em] text-[var(--ks-text-muted)] uppercase"
              >
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
                <tr
                  v-for="entry in entries"
                  :key="entry.id"
                  class="transition hover:bg-white/[0.025]"
                >
                  <td class="px-4 py-3.5 font-semibold">
                    <Link
                      class="text-[var(--ks-blue-strong)] hover:text-white"
                      :href="`/alliance/roster/${entry.id}/history`"
                    >
                      {{ entry.latestSnapshot?.observedName ?? entry.name }}
                    </Link>
                  </td>
                  <td class="px-4 py-3.5 text-xs text-[var(--ks-text-muted)]">
                    {{ entry.gamePlayerId ?? '—' }}
                  </td>
                  <td class="px-4 py-3.5">
                    <span
                      v-if="entry.gameRole"
                      class="rounded-full border border-purple-400/20 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-200"
                    >
                      {{ entry.gameRole }}
                    </span>
                    <span v-else class="text-[var(--ks-text-muted)]">—</span>
                  </td>
                  <td class="px-4 py-3.5">
                    <span
                      :class="rosterStateClass(entry.state)"
                      class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                    >
                      {{ stateLabel(entry.state) }}
                    </span>
                  </td>
                  <td class="px-4 py-3.5 font-semibold">
                    {{ entry.latestSnapshot ? formatPower(entry.latestSnapshot.power) : '—' }}
                  </td>
                  <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                    {{ entry.latestSnapshot?.progressionLevel ?? '—' }}
                  </td>
                  <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                    {{ entry.latestSnapshot?.observedAllianceTag ?? '—' }}
                  </td>
                  <td class="px-4 py-3.5">
                    <div class="flex flex-col gap-1.5">
                      <span
                        :class="freshnessClass(snapshotState(entry))"
                        class="w-fit rounded-full border px-2.5 py-1 text-xs font-semibold"
                      >
                        {{ stateLabel(snapshotState(entry)) }}
                      </span>
                      <span class="text-xs text-[var(--ks-text-muted)]">
                        {{
                          entry.latestSnapshot
                            ? formatCaptured(entry.latestSnapshot.capturedAt)
                            : '—'
                        }}
                      </span>
                    </div>
                  </td>
                  <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                    {{ entry.membership?.name ?? t('roster.unlinked') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="!entries.length" class="p-8 text-center">
            <p class="ks-display text-xl font-semibold">{{ t('roster.noResults') }}</p>
            <p class="mt-2 text-sm text-[var(--ks-text-muted)]">{{ t('roster.noResultsBody') }}</p>
          </div>
        </section>
      </div>

      <aside class="space-y-4 xl:col-span-1" :aria-label="t('roster.intelligence')">
        <section class="ks-surface p-5 xl:sticky xl:top-24">
          <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
            {{ t('roster.snapshotQuality') }}
          </p>

          <div class="mt-5 space-y-4">
            <div>
              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="text-green-200">{{ t('roster.current') }}</span>
                <strong>{{ formatNumber(snapshotCounts.current) }}</strong>
              </div>
              <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/30">
                <div
                  class="h-full rounded-full bg-green-400"
                  :style="{ width: snapshotPercent(snapshotCounts.current) }"
                />
              </div>
            </div>
            <div>
              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="text-amber-200">{{ t('roster.stale') }}</span>
                <strong>{{ formatNumber(snapshotCounts.stale) }}</strong>
              </div>
              <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/30">
                <div
                  class="h-full rounded-full bg-amber-400"
                  :style="{ width: snapshotPercent(snapshotCounts.stale) }"
                />
              </div>
            </div>
            <div>
              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="text-red-200">{{ t('roster.missing') }}</span>
                <strong>{{ formatNumber(snapshotCounts.missing) }}</strong>
              </div>
              <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/30">
                <div
                  class="h-full rounded-full bg-red-400"
                  :style="{ width: snapshotPercent(snapshotCounts.missing) }"
                />
              </div>
            </div>
          </div>

          <div class="my-5 border-t border-[var(--ks-border)]" />

          <div
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
          >
            <p class="text-xs font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase">
              {{ t('roster.membershipLinkage') }}
            </p>
            <p class="ks-display mt-2 text-2xl font-semibold">
              {{ formatNumber(linkedResults) }} / {{ formatNumber(entries.length) }}
            </p>
            <p class="mt-1 text-xs text-[var(--ks-text-muted)]">{{ t('roster.linked') }}</p>
          </div>

          <div class="my-5 border-t border-[var(--ks-border)]" />

          <h2 class="ks-display text-xl font-semibold">{{ t('roster.intelligence') }}</h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{ t('roster.intelligenceSubtitle') }}
          </p>
          <Link
            class="mt-4 inline-flex min-h-10 w-full items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-gold)]/45 bg-[var(--ks-gold-soft)] px-4 py-2 text-sm font-semibold text-[var(--ks-gold-strong)] transition hover:border-[var(--ks-gold)] hover:text-white"
            href="/alliance/roster/intelligence"
          >
            {{ t('roster.intelligence') }}
          </Link>
          <Link
            v-if="canManage"
            class="mt-2 inline-flex min-h-10 w-full items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-border-strong)] hover:text-white"
            href="/alliance/roster/manage"
          >
            {{ t('roster.manage') }}
          </Link>
        </section>
      </aside>
    </div>
  </AppLayout>
</template>
