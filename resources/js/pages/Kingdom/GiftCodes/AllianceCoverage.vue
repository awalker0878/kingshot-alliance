<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type CoverageCode = {
  id: string;
  code: string;
  expiresAt: string | null;
  completed: number;
  incomplete: number;
  retryReady: number;
  unknown: number;
};

defineProps<{
  user: { name: string; email: string };
  player: { id: string; name: string };
  allianceId: string;
  coverage: { eligibleGovernors: number; codes: CoverageCode[] };
}>();

const { t, formatDate, formatNumber } = useLocale();
</script>

<template>
  <Head :title="t('giftCodes.allianceCoverage.title')" />
  <AppLayout :user="user">
    <RoomBanner
      :eyebrow="t('giftCodes.allianceCoverage.eyebrow')"
      :title="t('giftCodes.allianceCoverage.title')"
      :subtitle="t('giftCodes.allianceCoverage.subtitle')"
      image="/images/kingshot/v4/account-vault.svg"
    >
      <template #actions>
        <Link href="/gift-codes/workspace" class="ks-command-link" data-variant="secondary">
          {{ t('giftCodes.allianceCoverage.backToWorkspace') }}
        </Link>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <StatSeal
        :label="t('giftCodes.allianceCoverage.eligibleGovernors')"
        :value="formatNumber(coverage.eligibleGovernors)"
        icon="♛"
      />
      <StatSeal
        :label="t('giftCodes.allianceCoverage.completed')"
        :value="formatNumber(coverage.codes.reduce((sum, item) => sum + item.completed, 0))"
        icon="✓"
        tone="teal"
      />
      <StatSeal
        :label="t('giftCodes.allianceCoverage.incomplete')"
        :value="formatNumber(coverage.codes.reduce((sum, item) => sum + item.incomplete, 0))"
        icon="◇"
      />
      <StatSeal
        :label="t('giftCodes.allianceCoverage.retryReady')"
        :value="formatNumber(coverage.codes.reduce((sum, item) => sum + item.retryReady, 0))"
        icon="↻"
      />
    </section>

    <section class="ks-surface mt-5 overflow-hidden">
      <div v-if="coverage.codes.length" class="divide-y divide-[var(--ks-border)]">
        <article v-for="item in coverage.codes" :key="item.id" class="p-5 sm:p-6">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h2 class="font-mono text-lg font-bold tracking-[.08em] text-[var(--ks-gold-bright)]">
                {{ item.code }}
              </h2>
              <p class="mt-2 text-xs text-[var(--ks-muted)]">
                {{ t('giftCodes.allianceCoverage.expiry') }}:
                {{
                  item.expiresAt
                    ? formatDate(item.expiresAt)
                    : t('giftCodes.allianceCoverage.noExpiry')
                }}
              </p>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
              <span class="ks-status" data-tone="success">
                {{ t('giftCodes.allianceCoverage.completed') }} {{ formatNumber(item.completed) }}
              </span>
              <span class="ks-status" data-tone="warning">
                {{ t('giftCodes.allianceCoverage.incomplete') }} {{ formatNumber(item.incomplete) }}
              </span>
              <span class="ks-status" data-tone="warning">
                {{ t('giftCodes.allianceCoverage.retryReady') }} {{ formatNumber(item.retryReady) }}
              </span>
              <span class="ks-status" data-tone="info">
                {{ t('giftCodes.allianceCoverage.unknown') }} {{ formatNumber(item.unknown) }}
              </span>
            </div>
          </div>
        </article>
      </div>
      <p v-else class="p-8 text-center text-sm text-[var(--ks-muted)]">
        {{ t('giftCodes.allianceCoverage.empty') }}
      </p>
    </section>
  </AppLayout>
</template>
