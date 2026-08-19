<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Observation = {
  observedName: string;
  observedTag: string | null;
  power: string | null;
  memberCount: number | null;
  capturedAt: string;
};

type CurrentRow = {
  shareTargetId: string;
  sourceAlliance: { id: string; name: string };
  gameAlliance: { name: string; tag: string | null };
  freshness: 'current' | 'stale' | 'missing';
  latestObservation: Observation | null;
};

type HistoryItem = Observation & { freshness: 'current' | 'stale' };
type History = {
  shareTargetId: string;
  sourceAlliance: { id: string; name: string };
  gameAlliance: { name: string; tag: string | null };
  items: HistoryItem[];
  nextCursor: string | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  canManage: boolean;
  current: CurrentRow[];
  selectedHistory: History | null;
}>();

const { t, formatDate, formatNumber } = useLocale();

const freshnessCounts = computed(() => ({
  current: props.current.filter((entry) => entry.freshness === 'current').length,
  stale: props.current.filter((entry) => entry.freshness === 'stale').length,
  missing: props.current.filter((entry) => entry.freshness === 'missing').length,
}));

function date(value: string): string {
  return formatDate(value, { dateStyle: 'medium', timeStyle: 'short' });
}

function number(value: string | number | null): string {
  if (value === null) return t('kingdomP7C.missing');
  const numeric = typeof value === 'number' ? value : Number(value);
  return Number.isFinite(numeric) ? formatNumber(numeric) : String(value);
}

function freshnessLabel(value: CurrentRow['freshness'] | HistoryItem['freshness']): string {
  return t(`kingdomP7C.${value}`);
}

function freshnessTone(value: CurrentRow['freshness'] | HistoryItem['freshness']): string {
  if (value === 'current') return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (value === 'stale') return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
  return 'border-[var(--ks-border)] bg-[rgba(210,163,75,.05)] text-[var(--ks-text-secondary)]';
}

function historyUrl(target: string, cursor?: string | null): string {
  const parameters = new URLSearchParams({ target });
  if (cursor) parameters.set('cursor', cursor);
  return `/alliance/kingdom-sharing?${parameters.toString()}`;
}
</script>

<template>
  <Head :title="`${t('kingdomP7C.title')} · ${alliance.name}`" />

  <AppLayout>
    <RoomBanner
      :eyebrow="t('kingdomP7C.eyebrow')"
      :title="t('kingdomP7C.title')"
      :subtitle="t('kingdomP7C.subtitle', { alliance: alliance.name })"
      image="/images/kingshot/v4/connections.svg"
      compact
    >
      <template #actions>
        <Link v-if="canManage" href="/alliance/kingdom-sharing/manage" class="ks-command-link">
          {{ t('kingdomP7C.manageSharing') }}
        </Link>
      </template>
    </RoomBanner>

    <section
      class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
      :aria-label="t('kingdomP7C.sharingSummary')"
    >
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7C.sharedTargets') }}
        </p>
        <p class="mt-2 text-2xl font-bold">{{ formatNumber(current.length) }}</p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7C.currentCount') }}
        </p>
        <p class="mt-2 text-2xl font-bold text-green-200">
          {{ formatNumber(freshnessCounts.current) }}
        </p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7C.staleCount') }}
        </p>
        <p class="mt-2 text-2xl font-bold text-amber-200">
          {{ formatNumber(freshnessCounts.stale) }}
        </p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7C.missingCount') }}
        </p>
        <p class="mt-2 text-2xl font-bold">{{ formatNumber(freshnessCounts.missing) }}</p>
      </article>
    </section>

    <section aria-labelledby="shared-current-heading" class="ks-surface mt-6 p-5 sm:p-6">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 id="shared-current-heading" class="ks-display text-xl font-semibold">
            {{ t('kingdomP7C.currentSharedFacts') }}
          </h2>
          <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{ t('kingdomP7C.currentSharedFactsHelp') }}
          </p>
        </div>
        <p class="text-sm text-[var(--ks-text-muted)]">
          {{ formatNumber(current.length) }} {{ t('kingdomP7C.sharedTargets') }}
        </p>
      </div>

      <div v-if="current.length" class="mt-5 space-y-3 md:hidden">
        <article
          v-for="entry in current"
          :key="entry.shareTargetId"
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-parchment)]/[0.02] p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs text-[var(--ks-text-muted)]">{{ entry.sourceAlliance.name }}</p>
              <h3 class="mt-1 font-semibold">{{ entry.gameAlliance.name }}</h3>
              <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                {{ entry.gameAlliance.tag ?? t('kingdomP7C.noTag') }}
              </p>
            </div>
            <span
              class="rounded-full border px-2.5 py-1 text-xs font-bold"
              :class="freshnessTone(entry.freshness)"
              >{{ freshnessLabel(entry.freshness) }}</span
            >
          </div>
          <div class="mt-4 text-sm text-[var(--ks-text-secondary)]">
            <template v-if="entry.latestObservation">
              <p>
                {{
                  t('kingdomP7C.powerMembers', {
                    power: number(entry.latestObservation.power),
                    members: number(entry.latestObservation.memberCount),
                  })
                }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                {{ t('kingdomP7C.captured', { date: date(entry.latestObservation.capturedAt) }) }}
              </p>
            </template>
            <p v-else>{{ t('kingdomP7C.noAcceptedObservation') }}</p>
          </div>
          <Link
            class="mt-4 inline-flex text-sm font-semibold text-[var(--ks-blue-strong)]"
            :href="historyUrl(entry.shareTargetId)"
            >{{ t('kingdomP7C.viewHistory') }}</Link
          >
        </article>
      </div>

      <div v-if="current.length" class="mt-5 hidden overflow-x-auto md:block">
        <table class="min-w-full text-start text-sm">
          <caption class="sr-only">
            {{
              t('kingdomP7C.currentSharedFacts')
            }}
          </caption>
          <thead
            class="border-b border-[var(--ks-border)] text-xs text-[var(--ks-text-muted)] uppercase"
          >
            <tr>
              <th class="px-3 py-3 text-start font-semibold">
                {{ t('kingdomP7C.sourceAlliance') }}
              </th>
              <th class="px-3 py-3 text-start font-semibold">{{ t('kingdomP7C.gameAlliance') }}</th>
              <th class="px-3 py-3 text-start font-semibold">{{ t('kingdomP7C.latestFacts') }}</th>
              <th class="px-3 py-3 text-start font-semibold">{{ t('kingdomP7C.freshness') }}</th>
              <th class="px-3 py-3 text-start font-semibold">{{ t('kingdomP7C.history') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="entry in current" :key="entry.shareTargetId">
              <td class="px-3 py-4 font-medium">{{ entry.sourceAlliance.name }}</td>
              <td class="px-3 py-4">
                <p class="font-medium">{{ entry.gameAlliance.name }}</p>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  {{ entry.gameAlliance.tag ?? t('kingdomP7C.noTag') }}
                </p>
              </td>
              <td class="px-3 py-4 text-[var(--ks-text-secondary)]">
                <template v-if="entry.latestObservation"
                  ><p>
                    {{
                      t('kingdomP7C.powerMembers', {
                        power: number(entry.latestObservation.power),
                        members: number(entry.latestObservation.memberCount),
                      })
                    }}
                  </p>
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                    {{
                      t('kingdomP7C.captured', { date: date(entry.latestObservation.capturedAt) })
                    }}
                  </p></template
                >
                <span v-else>{{ t('kingdomP7C.noAcceptedObservation') }}</span>
              </td>
              <td class="px-3 py-4">
                <span
                  class="rounded-full border px-2.5 py-1 text-xs font-bold"
                  :class="freshnessTone(entry.freshness)"
                  >{{ freshnessLabel(entry.freshness) }}</span
                >
              </td>
              <td class="px-3 py-4">
                <Link
                  class="font-semibold text-[var(--ks-blue-strong)] hover:underline"
                  :href="historyUrl(entry.shareTargetId)"
                  >{{ t('kingdomP7C.viewHistory') }}</Link
                >
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p
        v-else
        class="mt-5 rounded-[var(--ks-radius-md)] border border-dashed border-[var(--ks-border)] p-5 text-sm text-[var(--ks-text-muted)]"
      >
        {{ t('kingdomP7C.noSharedTargets') }}
      </p>
    </section>

    <section
      v-if="selectedHistory"
      aria-labelledby="shared-history-heading"
      class="ks-surface mt-6 p-5 sm:p-6"
    >
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 id="shared-history-heading" class="ks-display text-xl font-semibold">
            {{ t('kingdomP7C.historyTitle') }}
          </h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{
              t('kingdomP7C.historySubtitle', {
                alliance: selectedHistory.gameAlliance.name,
                source: selectedHistory.sourceAlliance.name,
              })
            }}
          </p>
        </div>
        <Link
          href="/alliance/kingdom-sharing"
          class="text-sm font-semibold text-[var(--ks-blue-strong)]"
          >{{ t('kingdomP7C.closeHistory') }}</Link
        >
      </div>

      <div v-if="selectedHistory.items.length" class="mt-5 space-y-3 md:hidden">
        <article
          v-for="item in selectedHistory.items"
          :key="`${item.capturedAt}-${item.observedName}`"
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="font-semibold">{{ item.observedName }}</h3>
              <p class="text-xs text-[var(--ks-text-muted)]">
                {{ item.observedTag ?? t('kingdomP7C.noTag') }}
              </p>
            </div>
            <span
              class="rounded-full border px-2 py-1 text-xs font-bold"
              :class="freshnessTone(item.freshness)"
              >{{ freshnessLabel(item.freshness) }}</span
            >
          </div>
          <p class="mt-3 text-sm text-[var(--ks-text-secondary)]">
            {{
              t('kingdomP7C.powerMembers', {
                power: number(item.power),
                members: number(item.memberCount),
              })
            }}
          </p>
          <p class="mt-1 text-xs text-[var(--ks-text-muted)]">{{ date(item.capturedAt) }}</p>
        </article>
      </div>

      <div v-if="selectedHistory.items.length" class="mt-5 hidden overflow-x-auto md:block">
        <table class="min-w-full text-start text-sm">
          <caption class="sr-only">
            {{
              t('kingdomP7C.historyTitle')
            }}
          </caption>
          <thead
            class="border-b border-[var(--ks-border)] text-xs text-[var(--ks-text-muted)] uppercase"
          >
            <tr>
              <th class="px-3 py-3 text-start">{{ t('kingdomP7C.capturedAt') }}</th>
              <th class="px-3 py-3 text-start">{{ t('kingdomP7C.observedIdentity') }}</th>
              <th class="px-3 py-3 text-start">{{ t('kingdomP7C.power') }}</th>
              <th class="px-3 py-3 text-start">{{ t('kingdomP7C.members') }}</th>
              <th class="px-3 py-3 text-start">{{ t('kingdomP7C.freshness') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr
              v-for="item in selectedHistory.items"
              :key="`${item.capturedAt}-${item.observedName}`"
            >
              <td class="px-3 py-4">{{ date(item.capturedAt) }}</td>
              <td class="px-3 py-4">
                <p class="font-medium">{{ item.observedName }}</p>
                <p class="text-xs text-[var(--ks-text-muted)]">
                  {{ item.observedTag ?? t('kingdomP7C.noTag') }}
                </p>
              </td>
              <td class="px-3 py-4">{{ number(item.power) }}</td>
              <td class="px-3 py-4">{{ number(item.memberCount) }}</td>
              <td class="px-3 py-4">
                <span
                  class="rounded-full border px-2 py-1 text-xs font-bold"
                  :class="freshnessTone(item.freshness)"
                  >{{ freshnessLabel(item.freshness) }}</span
                >
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="mt-5 text-sm text-[var(--ks-text-muted)]">{{ t('kingdomP7C.noHistory') }}</p>

      <div
        class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-[var(--ks-border)] pt-4"
      >
        <p class="max-w-2xl text-xs leading-5 text-[var(--ks-text-muted)]">
          {{ t('kingdomP7C.historySecurity') }}
        </p>
        <Link
          v-if="selectedHistory.nextCursor"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-blue-strong)]"
          :href="historyUrl(selectedHistory.shareTargetId, selectedHistory.nextCursor)"
          >{{ t('kingdomP7C.olderObservations') }}</Link
        >
      </div>
    </section>
  </AppLayout>
</template>
