<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type LatestObservation = { observedName: string; observedTag: string | null; power: string | null; memberCount: number | null; capturedAt: string; source: string };
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
const currentCount = computed(() => props.tracking.filter((entry) => entry.freshness === 'current').length);
const staleCount = computed(() => props.tracking.filter((entry) => entry.freshness === 'stale').length);
const missingCount = computed(() => props.tracking.filter((entry) => entry.freshness === 'missing').length);
const attentionCount = computed(() => props.tracking.length - currentCount.value);
const reviewCount = computed(() => props.tracking.filter((entry) => entry.diplomacyNeedsReview).length);
const topByPower = computed(() => [...props.tracking].filter((entry) => entry.latestObservation?.power).slice(0, 5));
function date(value: string): string { return formatDate(value, { dateStyle: 'medium', timeStyle: 'short' }); }
function label(value: string): string { if (value === 'nap') return 'NAP'; return value.charAt(0).toUpperCase() + value.slice(1).replaceAll('_', ' '); }
function freshnessLabel(value: TrackingSummary['freshness']): string {
  if (value === 'current') return t('kingdomP7A.current');
  if (value === 'stale') return t('kingdomP7A.stale');
  return t('kingdomP7A.missing');
}
function freshnessTone(value: TrackingSummary['freshness']): 'success' | 'warning' | 'danger' {
  if (value === 'current') return 'success';
  if (value === 'stale') return 'warning';
  return 'danger';
}
function diplomacyTone(value: string): 'success' | 'warning' | 'danger' | 'info' {
  if (['friendly', 'ally', 'nap'].includes(value)) return 'success';
  if (['hostile', 'war', 'enemy'].includes(value)) return 'danger';
  if (['cautious', 'review'].includes(value)) return 'warning';
  return 'info';
}
</script>

<template>
  <Head :title="`${t('kingdomP7A.overviewTitle')} · ${alliance.name}`" />
  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('navigation.kingdom')"
      :title="t('kingdomP7A.overviewTitle')"
      :subtitle="t('kingdomP7A.trackingHelp')"
      image="/images/kingshot/v4/intel-room.svg"
    >
      <template #actions>
        <Link v-if="canManage" href="/alliance/kingdom-alliances/manage" class="ks-command-link">{{ t('kingdomP7A.trackingState') }}</Link>
        <Link href="/alliance/kingdom-alliances/intelligence" class="ks-command-link" data-variant="secondary">{{ t('kingdomP7A.allianceTracking') }}</Link>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
      <StatSeal :label="t('kingdomP7A.currentKingdom')" :value="alliance.kingdom ? `#${alliance.kingdom}` : t('kingdomP7A.notConfigured')" icon="♚" />
      <StatSeal :label="t('kingdomP7A.trackedAlliances')" :value="formatNumber(tracking.length)" icon="◇" tone="teal" />
      <StatSeal :label="t('kingdomP7A.currentObservations')" :value="formatNumber(currentCount)" icon="◉" tone="stone" />
      <StatSeal :label="t('kingdomP7A.reviewDue')" :value="formatNumber(reviewCount)" icon="⌛" />
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.38fr)_minmax(22rem,.62fr)]">
      <div class="min-w-0 space-y-5">
        <section class="ks-surface overflow-hidden" aria-labelledby="tracking-heading">
          <div class="flex flex-wrap items-end justify-between gap-4 border-b border-[var(--ks-border)] p-5">
            <div class="max-w-3xl"><p class="ks-kicker">{{ t('kingdomP7A.allianceTracking') }}</p><h2 id="tracking-heading" class="ks-display mt-1 text-2xl font-semibold">{{ t('kingdomP7A.trackedAlliances') }}</h2><p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">{{ t('kingdomP7A.trackingHelp') }}</p></div>
            <div class="flex flex-wrap gap-2"><span class="ks-status" data-tone="success">{{ currentCount }} {{ t('kingdomP7A.current') }}</span><span v-if="staleCount" class="ks-status" data-tone="warning">{{ staleCount }} {{ t('kingdomP7A.stale') }}</span><span v-if="missingCount" class="ks-status" data-tone="danger">{{ missingCount }} {{ t('kingdomP7A.missing') }}</span></div>
          </div>

          <div v-if="tracking.length" class="lg:hidden">
            <article v-for="(entry, index) in tracking" :key="`${entry.name}-${entry.kingdom}-${index}`" class="border-b border-[var(--ks-border)] p-4 last:border-b-0">
              <div class="flex items-start gap-3"><div class="grid h-11 w-10 shrink-0 place-items-center border border-[var(--ks-gold-dark)] bg-[linear-gradient(160deg,#185d59,#102a29)] font-[var(--ks-font-display)] text-[var(--ks-gold-bright)] [clip-path:polygon(50%_0,95%_16%,86%_76%,50%_100%,14%_76%,5%_16%)]">{{ entry.name.slice(0, 1).toUpperCase() }}</div><div class="min-w-0 flex-1"><Link :href="entry.historyUrl" class="block truncate font-[var(--ks-font-display)] text-lg font-semibold text-[var(--ks-gold-bright)]">{{ entry.name }}</Link><p class="mt-1 text-xs text-[var(--ks-muted)]">{{ entry.tag ?? t('kingdomP7A.noTag') }} · #{{ entry.kingdom }}</p></div><span class="ks-status" :data-tone="freshnessTone(entry.freshness)">{{ freshnessLabel(entry.freshness) }}</span></div>
              <dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="ks-kicker">{{ t('kingdomP7A.latestFacts') }}</dt><dd class="mt-2 text-[var(--ks-text-secondary)]">{{ entry.latestObservation?.power ?? t('kingdomP7A.missing') }} · {{ entry.latestObservation?.memberCount ?? t('kingdomP7A.missing') }}</dd></div><div><dt class="ks-kicker">{{ t('kingdomP7A.diplomacy') }}</dt><dd class="mt-2"><span class="ks-status" :data-tone="diplomacyTone(entry.diplomacyState)">{{ label(entry.diplomacyState) }}</span></dd></div></dl>
              <p v-if="entry.latestObservation" class="mt-3 text-xs text-[var(--ks-muted)]">{{ date(entry.latestObservation.capturedAt) }}</p>
            </article>
          </div>

          <div v-if="tracking.length" class="hidden overflow-x-auto lg:block">
            <table class="w-full min-w-[66rem] text-start text-sm">
              <thead class="bg-black/20 text-[.66rem] font-extrabold tracking-[.09em] text-[var(--ks-muted)] uppercase"><tr><th class="px-5 py-3 text-start">{{ t('kingdomP7A.alliance') }}</th><th class="px-4 py-3 text-start">{{ t('kingdomP7A.latestFacts') }}</th><th class="px-4 py-3 text-start">{{ t('kingdomP7A.freshness') }}</th><th class="px-4 py-3 text-start">{{ t('kingdomP7A.diplomacy') }}</th><th class="px-4 py-3 text-start">{{ t('kingdomP7A.kingdomContext') }}</th><th class="px-4 py-3 text-start">{{ t('kingdomP7A.trackingState') }}</th></tr></thead>
              <tbody class="divide-y divide-[var(--ks-border)]"><tr v-for="(entry, index) in tracking" :key="`${entry.name}-${entry.kingdom}-${index}`" class="transition hover:bg-white/[0.018]"><td class="px-5 py-4"><div class="flex items-center gap-3"><div class="grid h-10 w-9 shrink-0 place-items-center border border-[var(--ks-gold-dark)] bg-[linear-gradient(160deg,#185d59,#102a29)] font-[var(--ks-font-display)] text-[var(--ks-gold-bright)] [clip-path:polygon(50%_0,95%_16%,86%_76%,50%_100%,14%_76%,5%_16%)]">{{ entry.name.slice(0, 1).toUpperCase() }}</div><div><Link :href="entry.historyUrl" class="font-[var(--ks-font-display)] font-semibold text-[var(--ks-gold-bright)] hover:text-[var(--ks-ivory)]">{{ entry.name }}</Link><p class="mt-1 text-xs text-[var(--ks-muted)]">{{ entry.tag ?? t('kingdomP7A.noTag') }}</p></div></div></td><td class="px-4 py-4"><template v-if="entry.latestObservation"><p class="font-semibold">{{ entry.latestObservation.power ?? t('kingdomP7A.missing') }}</p><p class="mt-1 text-xs text-[var(--ks-muted)]">{{ entry.latestObservation.memberCount ?? t('kingdomP7A.missing') }} · {{ date(entry.latestObservation.capturedAt) }}</p></template><span v-else class="text-[var(--ks-muted)]">{{ t('kingdomP7A.noAcceptedObservation') }}</span></td><td class="px-4 py-4"><span class="ks-status" :data-tone="freshnessTone(entry.freshness)">{{ freshnessLabel(entry.freshness) }}</span></td><td class="px-4 py-4"><span class="ks-status" :data-tone="diplomacyTone(entry.diplomacyState)">{{ label(entry.diplomacyState) }}</span><span v-if="entry.diplomacyNeedsReview" class="mt-2 block text-xs text-amber-200">{{ t('kingdomP7A.reviewDueShort') }}</span></td><td class="px-4 py-4"><span class="font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]">#{{ entry.kingdom }}</span><span v-if="!entry.contextCurrent" class="mt-1 block text-xs text-amber-200">{{ t('kingdomP7A.historicalContext') }}</span></td><td class="px-4 py-4"><span class="ks-chip" data-active="true">{{ label(entry.state) }}</span></td></tr></tbody>
            </table>
          </div>
          <div v-else class="ks-fantasy-empty m-5">{{ t('kingdomP7A.noTracking') }}</div>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
          <section class="ks-surface p-5"><div class="flex items-end justify-between gap-3"><div><p class="ks-kicker">{{ t('kingdomP7A.currentObservations') }}</p><h2 class="ks-display mt-1 text-xl font-semibold">{{ t('kingdomP7A.latestFacts') }}</h2></div><span class="ks-status" :data-tone="attentionCount ? 'warning' : 'success'">{{ attentionCount }}</span></div><div class="mt-4 space-y-2"><article v-for="entry in tracking.slice(0, 4)" :key="`fact-${entry.name}`" class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3"><div class="flex items-center justify-between gap-3"><strong class="truncate text-sm">{{ entry.name }}</strong><span class="ks-status" :data-tone="freshnessTone(entry.freshness)">{{ freshnessLabel(entry.freshness) }}</span></div><p class="mt-2 text-xs text-[var(--ks-muted)]">{{ entry.latestObservation ? date(entry.latestObservation.capturedAt) : t('kingdomP7A.noAcceptedObservation') }}</p></article></div></section>
          <section class="ks-surface p-5"><div class="flex items-end justify-between gap-3"><div><p class="ks-kicker">{{ t('kingdomP7A.reviewDue') }}</p><h2 class="ks-display mt-1 text-xl font-semibold">{{ t('kingdomP7A.diplomacy') }}</h2></div><span class="ks-status" :data-tone="reviewCount ? 'warning' : 'success'">{{ reviewCount }}</span></div><div class="mt-4 space-y-2"><article v-for="entry in tracking.filter((item) => item.diplomacyNeedsReview).slice(0, 4)" :key="`review-${entry.name}`" class="rounded-[var(--ks-radius-sm)] border border-amber-400/20 bg-amber-500/[.035] p-3"><strong class="text-sm">{{ entry.name }}</strong><p class="mt-1 text-xs text-amber-200">{{ label(entry.diplomacyState) }} · {{ t('kingdomP7A.reviewDueShort') }}</p></article><p v-if="!reviewCount" class="text-sm text-[var(--ks-muted)]">{{ t('kingdomP7A.currentObservations') }}</p></div></section>
        </div>
      </div>

      <aside class="space-y-5">
        <section class="ks-surface overflow-hidden">
          <div class="border-b border-[var(--ks-border)] px-4 py-3"><p class="ks-kicker">{{ t('kingdomP7A.currentKingdom') }}</p></div>
          <img src="/images/kingshot/v4/kingdom-map.svg" alt="" class="aspect-[3/2] w-full object-cover" aria-hidden="true" />
          <div class="grid grid-cols-3 divide-x divide-[var(--ks-border)] border-t border-[var(--ks-border)] text-center"><div class="p-3"><p class="text-[.62rem] text-[var(--ks-muted)] uppercase">{{ t('kingdomP7A.current') }}</p><strong class="mt-1 block text-green-200">{{ currentCount }}</strong></div><div class="p-3"><p class="text-[.62rem] text-[var(--ks-muted)] uppercase">{{ t('kingdomP7A.stale') }}</p><strong class="mt-1 block text-amber-200">{{ staleCount }}</strong></div><div class="p-3"><p class="text-[.62rem] text-[var(--ks-muted)] uppercase">{{ t('kingdomP7A.missing') }}</p><strong class="mt-1 block text-red-200">{{ missingCount }}</strong></div></div>
        </section>

        <section class="ks-surface p-5">
          <div class="flex items-end justify-between gap-3"><div><p class="ks-kicker">{{ t('kingdomP7A.trackedAlliances') }}</p><h2 class="ks-display mt-1 text-xl font-semibold">{{ t('kingdomP7A.latestFacts') }}</h2></div><Link href="/alliance/kingdom-alliances/intelligence" class="text-xs text-[var(--ks-teal-bright)]">{{ t('kingdomP7A.allianceTracking') }} →</Link></div>
          <ol class="mt-4 space-y-2"><li v-for="(entry, index) in topByPower" :key="`top-${entry.name}`" class="flex items-center gap-3 rounded border border-[var(--ks-border)] bg-black/15 px-3 py-2"><span class="w-5 text-center font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]">{{ index + 1 }}</span><span class="min-w-0 flex-1 truncate text-sm">{{ entry.name }}</span><span class="text-xs text-[var(--ks-muted)]">{{ entry.latestObservation?.power }}</span></li></ol>
        </section>

        <section class="ks-surface p-5">
          <p class="ks-kicker">{{ t('kingdomP7A.allianceTracking') }}</p><div class="mt-4 grid grid-cols-2 gap-2"><Link href="/alliance/kingdom-alliances/intelligence" class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3 text-center transition hover:border-[var(--ks-border-strong)]"><span class="block text-xl text-[var(--ks-gold-bright)]">◇</span><span class="mt-2 block text-xs">{{ t('kingdomP7A.latestFacts') }}</span></Link><Link v-if="canManage" href="/alliance/kingdom-alliances/manage" class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3 text-center transition hover:border-[var(--ks-border-strong)]"><span class="block text-xl text-[var(--ks-gold-bright)]">⚙</span><span class="mt-2 block text-xs">{{ t('kingdomP7A.trackingState') }}</span></Link></div>
        </section>
      </aside>
    </div>
  </AppLayout>
</template>
