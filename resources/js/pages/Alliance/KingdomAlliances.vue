<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

type LatestObservation = {
  observedName: string;
  observedTag: string | null;
  power: string | null;
  memberCount: number | null;
  capturedAt: string;
  source: string;
};

type TrackingSummary = {
  name: string;
  tag: string | null;
  state: string;
  kingdom: string;
  contextCurrent: boolean;
  historyUrl: string;
  freshness: 'current' | 'stale' | 'missing';
  latestObservation: LatestObservation | null;
  diplomacyState: string;
  diplomacyNeedsReview: boolean;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  canManage: boolean;
  canManageKingdomRoles: boolean;
  tracking: TrackingSummary[];
}>();

const { t, formatDate, formatNumber } = useLocale();
const currentCount = computed(
  () => props.tracking.filter((entry) => entry.freshness === 'current').length,
);
const attentionCount = computed(() => props.tracking.length - currentCount.value);
const reviewCount = computed(
  () => props.tracking.filter((entry) => entry.diplomacyNeedsReview).length,
);

function date(value: string): string {
  return formatDate(value, { dateStyle: 'medium', timeStyle: 'short' });
}

function label(value: string): string {
  if (value === 'nap') return 'NAP';
  return value.charAt(0).toUpperCase() + value.slice(1).replaceAll('_', ' ');
}

function freshnessLabel(value: TrackingSummary['freshness']): string {
  if (value === 'current') return t('kingdomP7A.current');
  if (value === 'stale') return t('kingdomP7A.stale');
  return t('kingdomP7A.missing');
}

function freshnessTone(value: TrackingSummary['freshness']): string {
  if (value === 'current') return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (value === 'stale') return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
  return 'border-red-400/25 bg-red-500/10 text-red-200';
}
</script>

<template>
  <Head :title="`${t('kingdomP7A.overviewTitle')} · ${alliance.name}`" />
  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-5">
      <div class="max-w-3xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('kingdomP7A.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('kingdomP7A.overviewTitle') }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7A.overviewSubtitle', { alliance: alliance.name }) }}
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <Link
          v-if="canManage"
          href="/alliance/kingdom-alliances/manage"
          class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-3 py-2 text-sm font-semibold text-white"
          >{{ t('kingdomP7A.manageTracking') }}</Link
        >
        <Link
          href="/alliance/kingdom-alliances/intelligence"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          >{{ t('kingdomP7A.intelligence') }}</Link
        >
        <Link
          v-if="canManage"
          href="/alliance/settings/kingdom"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          >{{ t('kingdomP7A.settings') }}</Link
        >
        <Link
          v-if="canManageKingdomRoles"
          href="/alliance/settings/kingdom/roles"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          >{{ t('kingdomP7A.rolesManage') }}</Link
        >
        <Link
          v-if="canManage"
          href="/alliance/kingdom-ingestion/manage"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          >{{ t('kingdomP7A.ingestion') }}</Link
        >
      </div>
    </header>

    <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Kingdom summary">
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
          {{ t('kingdomP7A.trackedAlliances') }}
        </p>
        <p class="mt-2 text-2xl font-bold">{{ formatNumber(tracking.length) }}</p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7A.currentObservations') }}
        </p>
        <p class="mt-2 text-2xl font-bold text-green-200">{{ formatNumber(currentCount) }}</p>
        <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
          {{ formatNumber(attentionCount) }} · {{ t('kingdomP7A.staleOrMissing') }}
        </p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7A.reviewDue') }}
        </p>
        <p
          class="mt-2 text-2xl font-bold"
          :class="reviewCount ? 'text-amber-200' : 'text-green-200'"
        >
          {{ formatNumber(reviewCount) }}
        </p>
      </article>
    </section>

    <section class="ks-surface mt-6 p-4 sm:p-5" aria-labelledby="tracking-heading">
      <div
        class="flex flex-wrap items-end justify-between gap-3 border-b border-[var(--ks-border)] pb-4"
      >
        <div class="max-w-3xl">
          <h2 id="tracking-heading" class="ks-display text-xl font-semibold">
            {{ t('kingdomP7A.allianceTracking') }}
          </h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{ t('kingdomP7A.trackingHelp') }}
          </p>
        </div>
        <p class="text-sm text-[var(--ks-text-muted)]">
          {{ formatNumber(tracking.length) }} {{ t('kingdomP7A.trackedAlliances') }}
        </p>
      </div>

      <div v-if="tracking.length" class="mt-4 space-y-3 lg:hidden">
        <article
          v-for="(entry, index) in tracking"
          :key="`${entry.name}-${entry.kingdom}-${index}`"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <Link
                :href="entry.historyUrl"
                class="font-semibold text-[var(--ks-blue-soft)] hover:underline"
                >{{ entry.name }}</Link
              >
              <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                {{ entry.tag ?? t('kingdomP7A.noTag') }}
              </p>
            </div>
            <span
              :class="freshnessTone(entry.freshness)"
              class="rounded-full border px-2 py-1 text-xs font-semibold"
              >{{ freshnessLabel(entry.freshness) }}</span
            >
          </div>
          <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <p>
              <span class="block text-xs text-[var(--ks-text-muted)]">{{
                t('kingdomP7A.latestFacts')
              }}</span
              ><span v-if="entry.latestObservation"
                >{{ t('kingdomP7A.power') }}
                {{ entry.latestObservation.power ?? t('kingdomP7A.missing') }} ·
                {{ t('kingdomP7A.members') }}
                {{ entry.latestObservation.memberCount ?? t('kingdomP7A.missing') }}</span
              ><span v-else>{{ t('kingdomP7A.noAcceptedObservation') }}</span>
            </p>
            <p>
              <span class="block text-xs text-[var(--ks-text-muted)]">{{
                t('kingdomP7A.diplomacy')
              }}</span
              >{{ label(entry.diplomacyState)
              }}<span v-if="entry.diplomacyNeedsReview" class="block text-xs text-amber-200">{{
                t('kingdomP7A.reviewDueShort')
              }}</span>
            </p>
            <p>
              <span class="block text-xs text-[var(--ks-text-muted)]">{{
                t('kingdomP7A.kingdomContext')
              }}</span
              >#{{ entry.kingdom
              }}<span v-if="!entry.contextCurrent" class="block text-xs text-amber-200">{{
                t('kingdomP7A.historicalContext')
              }}</span>
            </p>
            <p>
              <span class="block text-xs text-[var(--ks-text-muted)]">{{
                t('kingdomP7A.trackingState')
              }}</span
              >{{ label(entry.state) }}
            </p>
          </div>
          <p v-if="entry.latestObservation" class="mt-3 text-xs text-[var(--ks-text-muted)]">
            {{ date(entry.latestObservation.capturedAt) }}
          </p>
        </article>
      </div>

      <div v-if="tracking.length" class="mt-4 hidden overflow-x-auto lg:block">
        <table class="min-w-full text-left text-sm">
          <caption class="sr-only">
            {{
              t('kingdomP7A.allianceTracking')
            }}
          </caption>
          <thead class="text-xs text-[var(--ks-text-muted)] uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7A.alliance') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7A.latestFacts') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7A.freshness') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7A.diplomacy') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7A.kingdomContext') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7A.trackingState') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="(entry, index) in tracking" :key="`${entry.name}-${entry.kingdom}-${index}`">
              <td class="px-3 py-4 align-top">
                <Link
                  :href="entry.historyUrl"
                  class="font-semibold text-[var(--ks-blue-soft)] hover:underline"
                  >{{ entry.name }}</Link
                >
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  {{ entry.tag ?? t('kingdomP7A.noTag') }}
                </p>
              </td>
              <td class="px-3 py-4 align-top text-[var(--ks-text-secondary)]">
                <template v-if="entry.latestObservation"
                  ><p>
                    {{ t('kingdomP7A.power') }}
                    {{ entry.latestObservation.power ?? t('kingdomP7A.missing') }} ·
                    {{ t('kingdomP7A.members') }}
                    {{ entry.latestObservation.memberCount ?? t('kingdomP7A.missing') }}
                  </p>
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                    {{ date(entry.latestObservation.capturedAt) }}
                  </p></template
                ><span v-else>{{ t('kingdomP7A.noAcceptedObservation') }}</span>
              </td>
              <td class="px-3 py-4 align-top">
                <span
                  :class="freshnessTone(entry.freshness)"
                  class="rounded-full border px-2 py-1 text-xs font-semibold"
                  >{{ freshnessLabel(entry.freshness) }}</span
                >
              </td>
              <td class="px-3 py-4 align-top">
                {{ label(entry.diplomacyState)
                }}<span
                  v-if="entry.diplomacyNeedsReview"
                  class="mt-2 block text-xs font-semibold text-amber-200"
                  >{{ t('kingdomP7A.reviewDueShort') }}</span
                >
              </td>
              <td class="px-3 py-4 align-top">
                #{{ entry.kingdom
                }}<span
                  v-if="!entry.contextCurrent"
                  class="mt-2 block text-xs font-semibold text-amber-200"
                  >{{ t('kingdomP7A.historicalContext') }}</span
                >
              </td>
              <td class="px-3 py-4 align-top">{{ label(entry.state) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p
        v-else
        class="mt-5 rounded-[var(--ks-radius-sm)] border border-dashed border-[var(--ks-border)] p-5 text-sm text-[var(--ks-text-muted)]"
      >
        {{ t('kingdomP7A.noTracking') }}
      </p>
    </section>
  </AppLayout>
</template>
