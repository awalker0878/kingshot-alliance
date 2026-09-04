<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type ReconciliationItem = {
  observationId: string | null;
  observedName: string;
  gamePlayerId: string | null;
  observedRank: string | null;
  power: number | null;
  matchedPlayerId: string | null;
  identityState: string;
  reasons: string[];
  handoff: string;
};

type Reconciliation = {
  batch: {
    id: string;
    capturedAt: string;
    evidenceId: string;
    reviewId: string;
    completeRoster: boolean;
  } | null;
  items: ReconciliationItem[];
  summary: { needsReview: number; matched: number };
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  reconciliation: Reconciliation;
}>();

const { t, formatDate, formatNumber } = useLocale();

function reasonLabel(reason: string): string {
  const key = `allianceExpansion.reconciliationReasons.${reason}`;
  const translated = t(key);
  return translated === key ? reason.replaceAll('_', ' ') : translated;
}

function identityLabel(state: string): string {
  const key = `allianceExpansion.identityStates.${state}`;
  const translated = t(key);
  return translated === key ? state.replaceAll('_', ' ') : translated;
}
</script>

<template>
  <Head :title="`${t('allianceExpansion.reconciliationTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('allianceExpansion.reconciliationEyebrow')"
      :title="t('allianceExpansion.reconciliationTitle')"
      :subtitle="t('allianceExpansion.reconciliationSubtitle')"
      image="/images/kingshot/v4/roster-hall.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/roster/evidence" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navRosterEvidence') }}
        </Link>
        <Link href="/alliance/members/bulk" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navBulk') }}
        </Link>
      </template>
    </RoomBanner>

    <section v-if="reconciliation.batch" class="ks-surface mt-6 p-5">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="ks-kicker">{{ t('allianceExpansion.latestBatch') }}</p>
          <p class="mt-1 font-semibold">{{ formatDate(reconciliation.batch.capturedAt) }}</p>
          <p class="mt-1 text-sm text-[var(--ks-muted)]">
            {{
              reconciliation.batch.completeRoster
                ? t('allianceExpansion.completeObservation')
                : t('allianceExpansion.partialObservation')
            }}
          </p>
        </div>
        <div class="grid grid-cols-2 gap-3 text-center">
          <div class="ks-stat-card">
            <span class="ks-kicker">{{ t('allianceExpansion.matched', { count: formatNumber(reconciliation.summary.matched) }) }}</span>
            <strong>{{ formatNumber(reconciliation.summary.matched) }}</strong>
          </div>
          <div class="ks-stat-card">
            <span class="ks-kicker">{{ t('allianceExpansion.needsReview', { count: formatNumber(reconciliation.summary.needsReview) }) }}</span>
            <strong>{{ formatNumber(reconciliation.summary.needsReview) }}</strong>
          </div>
        </div>
      </div>
    </section>

    <div v-else class="ks-fantasy-empty mt-6">
      {{ t('allianceExpansion.noBatch') }}
    </div>

    <section v-if="reconciliation.items.length" class="mt-6 space-y-4" aria-labelledby="reconciliation-items-title">
      <h2 id="reconciliation-items-title" class="sr-only">
        {{ t('allianceExpansion.reconciliationTitle') }}
      </h2>
      <article v-for="item in reconciliation.items" :key="item.observationId ?? `membership-${item.matchedPlayerId}`" class="ks-surface p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h3 class="ks-display text-xl font-semibold">{{ item.observedName }}</h3>
            <p class="mt-1 text-sm text-[var(--ks-muted)]">
              {{ t('allianceExpansion.identityState') }}: {{ identityLabel(item.identityState) }}
            </p>
          </div>
          <Link :href="item.handoff" class="ks-command-link" data-variant="secondary">
            {{ t('allianceExpansion.currentMembershipAction') }}
          </Link>
        </div>

        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
          <div>
            <dt class="text-xs text-[var(--ks-muted)]">{{ t('allianceExpansion.gamePlayerId') }}</dt>
            <dd class="mt-1 text-sm">{{ item.gamePlayerId ?? t('common.none') }}</dd>
          </div>
          <div>
            <dt class="text-xs text-[var(--ks-muted)]">{{ t('allianceExpansion.observedRank') }}</dt>
            <dd class="mt-1 text-sm">{{ item.observedRank ?? t('common.none') }}</dd>
          </div>
          <div>
            <dt class="text-xs text-[var(--ks-muted)]">{{ t('allianceExpansion.power') }}</dt>
            <dd class="mt-1 text-sm">{{ item.power === null ? t('common.none') : formatNumber(item.power) }}</dd>
          </div>
        </dl>

        <div class="mt-4">
          <p class="text-xs font-semibold text-[var(--ks-muted)]">{{ t('allianceExpansion.reasons') }}</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <span
              v-for="reason in item.reasons"
              :key="reason"
              class="ks-status"
              :data-tone="reason === 'matches_membership' ? 'success' : 'warning'"
            >
              {{ reasonLabel(reason) }}
            </span>
          </div>
        </div>
      </article>
    </section>
  </AppLayout>
</template>
