<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

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

function freshnessTone(value: 'current' | 'stale' | 'missing'): 'success' | 'warning' | 'danger' {
  if (value === 'current') return 'success';
  if (value === 'stale') return 'warning';
  return 'danger';
}

function rosterTone(value: string): 'success' | 'warning' | 'info' {
  if (value === 'active') return 'success';
  if (value === 'tracked') return 'info';
  return 'warning';
}

function snapshotPercent(value: number): string {
  if (props.entries.length === 0) return '0%';
  return `${Math.round((value / props.entries.length) * 100)}%`;
}
</script>

<template>
  <Head :title="`${t('roster.title')} · ${alliance.name}`" />

  <AppLayout>
    <RoomBanner
      :eyebrow="t('roster.eyebrow', { kingdom: alliance.kingdom ?? t('roster.kingdomNotSet') })"
      :title="t('roster.title')"
      :subtitle="t('roster.subtitle')"
      image="/images/kingshot/v4/roster-hall.svg"
    >
      <template #actions>
        <Link href="/alliance/roster/intelligence" class="ks-command-link">
          {{ t('roster.intelligence') }}
        </Link>
        <Link
          v-if="canManage"
          href="/alliance/roster/manage"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('roster.manage') }}
        </Link>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
      <StatSeal :label="t('roster.results')" :value="formatNumber(entries.length)" icon="♟" />
      <StatSeal
        :label="t('roster.current')"
        :value="formatNumber(snapshotCounts.current)"
        icon="●"
        tone="teal"
      />
      <StatSeal
        :label="t('roster.stale')"
        :value="formatNumber(snapshotCounts.stale)"
        icon="◷"
        tone="stone"
      />
      <StatSeal
        :label="t('roster.missing')"
        :value="formatNumber(snapshotCounts.missing)"
        icon="?"
      />
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.42fr)_minmax(20rem,.58fr)]">
      <div class="min-w-0 space-y-5">
        <section class="ks-surface p-4 sm:p-5" aria-labelledby="roster-filter-heading">
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('roster.filters') }}</p>
              <h2 id="roster-filter-heading" class="ks-display mt-1 text-xl font-semibold">
                {{ t('roster.title') }}
              </h2>
            </div>
            <p class="text-xs text-[var(--ks-muted)]">
              {{ t('roster.freshnessHelp', { days: staleAfterDays }) }}
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
                class="ks-input mt-1.5"
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
              <select id="roster-state-filter" v-model="filters.state" class="ks-input mt-1.5">
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
              <select id="roster-linkage-filter" v-model="filters.linkage" class="ks-input mt-1.5">
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
              <select id="roster-role-filter" v-model="filters.role" class="ks-input mt-1.5">
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
                class="ks-input mt-1.5"
              >
                <option value="">{{ t('roster.anyFreshness') }}</option>
                <option value="current">{{ t('roster.current') }}</option>
                <option value="stale">{{ t('roster.stale') }}</option>
                <option value="missing">{{ t('roster.missing') }}</option>
              </select>
            </div>
            <div class="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-6">
              <button type="submit" class="ks-command-button">{{ t('roster.apply') }}</button>
              <button
                type="button"
                class="ks-command-button"
                data-variant="secondary"
                @click="clearFilters"
              >
                {{ t('roster.clear') }}
              </button>
            </div>
          </form>
        </section>

        <section class="ks-surface overflow-hidden" aria-labelledby="roster-results-heading">
          <div
            class="flex items-center justify-between gap-4 border-b border-[var(--ks-border)] px-4 py-3 sm:px-5"
          >
            <div>
              <p class="ks-kicker">{{ t('roster.title') }}</p>
              <h2 id="roster-results-heading" class="sr-only">{{ t('roster.results') }}</h2>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
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
              <div class="flex items-start gap-3">
                <div
                  class="grid h-11 w-11 shrink-0 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-black/20 font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
                  aria-hidden="true"
                >
                  {{ (entry.latestSnapshot?.observedName ?? entry.name).slice(0, 1).toUpperCase() }}
                </div>
                <div class="min-w-0 flex-1">
                  <Link
                    class="block truncate text-lg font-[var(--ks-font-display)] font-semibold text-[var(--ks-gold-bright)] hover:text-[var(--ks-ivory)]"
                    :href="`/alliance/roster/${entry.id}/history`"
                  >
                    {{ entry.latestSnapshot?.observedName ?? entry.name }}
                  </Link>
                  <p class="mt-1 truncate text-xs text-[var(--ks-muted)]">
                    {{ entry.gamePlayerId ?? '—' }}
                  </p>
                </div>
                <div class="text-end">
                  <p class="ks-kicker">{{ t('roster.power') }}</p>
                  <strong class="mt-1 block text-sm">
                    {{ entry.latestSnapshot ? formatPower(entry.latestSnapshot.power) : '—' }}
                  </strong>
                </div>
              </div>
              <div class="mt-3 flex flex-wrap gap-2">
                <span class="ks-status" :data-tone="rosterTone(entry.state)">
                  {{ stateLabel(entry.state) }}
                </span>
                <span class="ks-status" :data-tone="freshnessTone(snapshotState(entry))">
                  {{ stateLabel(snapshotState(entry)) }}
                </span>
                <span v-if="entry.gameRole" class="ks-chip">{{ entry.gameRole }}</span>
              </div>
              <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('roster.progression') }}</dt>
                  <dd class="mt-1 font-medium">
                    {{ entry.latestSnapshot?.progressionLevel ?? '—' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('roster.allianceTag') }}</dt>
                  <dd class="mt-1 font-medium">
                    {{ entry.latestSnapshot?.observedAllianceTag ?? '—' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('roster.linkedMember') }}</dt>
                  <dd class="mt-1 truncate font-medium">
                    {{ entry.membership?.name ?? t('roster.unlinked') }}
                  </dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('roster.snapshotCaptured') }}</dt>
                  <dd class="mt-1 font-medium">
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
                class="bg-black/20 text-[.66rem] font-extrabold tracking-[.08em] text-[var(--ks-muted)] uppercase"
              >
                <tr>
                  <th class="px-4 py-3 text-start">{{ t('roster.player') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('roster.role') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('roster.state') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('roster.power') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('roster.progression') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('roster.snapshotCaptured') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('roster.linkedMember') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[var(--ks-border)]">
                <tr
                  v-for="entry in entries"
                  :key="entry.id"
                  class="transition hover:bg-white/[0.018]"
                >
                  <td class="px-4 py-4">
                    <div class="flex items-center gap-3">
                      <div
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-black/20 font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
                        aria-hidden="true"
                      >
                        {{
                          (entry.latestSnapshot?.observedName ?? entry.name)
                            .slice(0, 1)
                            .toUpperCase()
                        }}
                      </div>
                      <div class="min-w-0">
                        <Link
                          class="font-[var(--ks-font-display)] font-semibold text-[var(--ks-gold-bright)] hover:text-[var(--ks-ivory)]"
                          :href="`/alliance/roster/${entry.id}/history`"
                        >
                          {{ entry.latestSnapshot?.observedName ?? entry.name }}
                        </Link>
                        <p class="mt-1 text-xs text-[var(--ks-muted)]">
                          {{ entry.gamePlayerId ?? '—' }}
                        </p>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-4">
                    <span v-if="entry.gameRole" class="ks-chip">{{ entry.gameRole }}</span
                    ><span v-else class="text-[var(--ks-muted)]">—</span>
                  </td>
                  <td class="px-4 py-4">
                    <span class="ks-status" :data-tone="rosterTone(entry.state)">{{
                      stateLabel(entry.state)
                    }}</span>
                  </td>
                  <td class="px-4 py-4 font-semibold">
                    {{ entry.latestSnapshot ? formatPower(entry.latestSnapshot.power) : '—' }}
                  </td>
                  <td class="px-4 py-4 text-[var(--ks-text-secondary)]">
                    {{ entry.latestSnapshot?.progressionLevel ?? '—' }}
                  </td>
                  <td class="px-4 py-4">
                    <span class="ks-status" :data-tone="freshnessTone(snapshotState(entry))">{{
                      stateLabel(snapshotState(entry))
                    }}</span>
                    <p class="mt-1.5 text-xs text-[var(--ks-muted)]">
                      {{
                        entry.latestSnapshot ? formatCaptured(entry.latestSnapshot.capturedAt) : '—'
                      }}
                    </p>
                  </td>
                  <td class="px-4 py-4 text-[var(--ks-text-secondary)]">
                    {{ entry.membership?.name ?? t('roster.unlinked') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="!entries.length" class="ks-fantasy-empty m-5">
            <p class="ks-display text-xl font-semibold">{{ t('roster.noResults') }}</p>
            <p class="mt-2 text-sm">{{ t('roster.noResultsBody') }}</p>
          </div>
        </section>
      </div>

      <aside class="space-y-5">
        <section
          class="ks-surface p-5 2xl:sticky 2xl:top-[6.5rem]"
          aria-labelledby="roster-quality-heading"
        >
          <p class="ks-kicker">{{ t('roster.snapshotQuality') }}</p>
          <h2 id="roster-quality-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ t('roster.freshness') }}
          </h2>

          <div class="mt-5 space-y-5">
            <div
              v-for="item in [
                { key: 'current', value: snapshotCounts.current, tone: 'bg-[var(--ks-green)]' },
                { key: 'stale', value: snapshotCounts.stale, tone: 'bg-[var(--ks-amber)]' },
                { key: 'missing', value: snapshotCounts.missing, tone: 'bg-[var(--ks-red)]' },
              ]"
              :key="item.key"
            >
              <div class="flex items-center justify-between gap-3 text-sm">
                <span>{{ t(`roster.${item.key}`) }}</span>
                <strong>{{ formatNumber(item.value) }}</strong>
              </div>
              <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/[.05]">
                <div
                  class="h-full rounded-full"
                  :class="item.tone"
                  :style="{ width: snapshotPercent(item.value) }"
                />
              </div>
            </div>
          </div>

          <div class="ks-divider my-5" />

          <div
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
          >
            <p class="ks-kicker">{{ t('roster.membershipLinkage') }}</p>
            <p class="ks-display mt-2 text-3xl font-semibold text-[var(--ks-gold-bright)]">
              {{ formatNumber(linkedResults) }} / {{ formatNumber(entries.length) }}
            </p>
            <p class="mt-1 text-xs text-[var(--ks-muted)]">{{ t('roster.linked') }}</p>
          </div>

          <div class="mt-5 grid gap-2">
            <Link href="/alliance/roster/intelligence" class="ks-command-link w-full">
              {{ t('roster.intelligence') }}
            </Link>
            <Link
              v-if="canManage"
              href="/alliance/roster/manage"
              class="ks-command-link w-full"
              data-variant="secondary"
            >
              {{ t('roster.manage') }}
            </Link>
          </div>
        </section>
      </aside>
    </div>
  </AppLayout>
</template>
