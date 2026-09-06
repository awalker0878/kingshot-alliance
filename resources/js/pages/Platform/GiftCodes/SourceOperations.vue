<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type SmokeCheck = {
  status: string;
  checkedAt: string;
  durationMs: number;
  observationCount: number;
  pushStatus: string;
  failureCode: string | null;
  failureMessage: string | null;
};

type Performance = {
  observations: number;
  uniqueCodesDiscovered: number;
  firstDiscoveries: number;
  qualifiedObservations: number;
  confirmedCorrect: number;
  confirmedIncorrect: number;
  conflictingObservations: number;
  medianDiscoveryLatencySeconds: number | null;
  medianConfirmationLatencySeconds: number | null;
  medianTimeToCodeSeconds: number | null;
  p95TimeToCodeSeconds: number | null;
  usefulObservationRatio: number;
  quarantineRatio: number;
  duplicateRatio: number;
  latencySampleCount: number;
  lastProductiveObservationAt: string | null;
  derivedAt: string;
};

type Source = {
  id: string;
  key: string;
  name: string;
  classification: string;
  adapterKey: string | null;
  active: boolean;
  ingestionEnabled: boolean;
  pushEnabled: boolean;
  headPollEnabled: boolean;
  reconciliationEnabled: boolean;
  backfillEnabled: boolean;
  authorityPromotionEnabled: boolean;
  activationStatus: string;
  healthStatus: string;
  requestCount: number;
  observationCount: number;
  acceptedObservationCount: number;
  quarantinedObservationCount: number;
  duplicateObservationCount: number;
  reconciliationGapCount: number;
  signatureFailureCount: number;
  replayRejectionCount: number;
  latestSmokeCheck: SmokeCheck | null;
  performance: Performance | null;
};

type Effectiveness = {
  codesMeasured: number;
  timeToCodeSamples: number;
  medianTimeToCodeSeconds: number | null;
  p95TimeToCodeSeconds: number | null;
  observations: number;
  distinctSources: number;
  independentSources: number;
  officialSources: number;
  sourcePerformanceRows: number;
};

defineProps<{
  user: { name: string; email: string };
  sources: Source[];
  acquisitionEffectiveness: Effectiveness;
  canManagePlatformPolicy: boolean;
}>();

const { t, formatDate, formatNumber } = useLocale();
const busy = ref<string | null>(null);

function runSourceAction(source: Source, action: string, data: Record<string, unknown> = {}): void {
  const key = `${source.id}:${action}`;
  busy.value = key;
  router.post(`/platform/gift-codes/sources/${source.id}/${action}`, data, {
    preserveScroll: true,
    onFinish: () => {
      busy.value = null;
    },
  });
}

function setControls(source: Source, updates: Partial<Record<keyof Source, boolean>>): void {
  runSourceAction(source, 'controls', {
    ingestion_enabled: updates.ingestionEnabled ?? source.ingestionEnabled,
    push_enabled: updates.pushEnabled ?? source.pushEnabled,
    head_poll_enabled: updates.headPollEnabled ?? source.headPollEnabled,
    reconciliation_enabled: updates.reconciliationEnabled ?? source.reconciliationEnabled,
    backfill_enabled: updates.backfillEnabled ?? source.backfillEnabled,
    authority_promotion_enabled:
      updates.authorityPromotionEnabled ?? source.authorityPromotionEnabled,
  });
}

function rebuildIntelligence(): void {
  busy.value = 'intelligence';
  router.post('/platform/gift-codes/sources/intelligence/rebuild', {}, {
    preserveScroll: true,
    onFinish: () => {
      busy.value = null;
    },
  });
}

function seconds(value: number | null): string {
  return value === null
    ? t('giftCodes.acquisitionOperations.unknown')
    : t('giftCodes.acquisitionOperations.seconds', { count: formatNumber(value) });
}

function percent(value: number): string {
  return `${Math.round(value * 100)}%`;
}
</script>

<template>
  <Head :title="t('giftCodes.acquisitionOperations.pageTitle')" />

  <AppLayout :user="user">
    <header class="ks-surface p-5 sm:p-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="ks-kicker">{{ t('giftCodes.acquisitionOperations.eyebrow') }}</p>
          <h1 class="ks-display mt-1 text-3xl font-semibold">
            {{ t('giftCodes.acquisitionOperations.title') }}
          </h1>
          <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('giftCodes.acquisitionOperations.help') }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link href="/platform/gift-codes/sources/evidence-entry" class="ks-command-link" data-variant="secondary">
            {{ t('giftCodes.acquisitionOperations.evidenceEntry') }}
          </Link>
          <Link href="/platform/gift-codes/sources" class="ks-command-link" data-variant="secondary">
            {{ t('giftCodes.acquisitionOperations.sourcePolicies') }}
          </Link>
        </div>
      </div>
    </header>

    <section class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="acquisition-effectiveness">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h2 id="acquisition-effectiveness" class="ks-display text-xl font-semibold">
            {{ t('giftCodes.acquisitionOperations.effectiveness') }}
          </h2>
          <p class="mt-2 text-sm text-[var(--ks-muted)]">
            {{ t('giftCodes.acquisitionOperations.effectivenessHelp') }}
          </p>
        </div>
        <AppButton
          v-if="canManagePlatformPolicy"
          type="button"
          variant="secondary"
          :busy="busy === 'intelligence'"
          @click="rebuildIntelligence"
        >
          {{ t('giftCodes.acquisitionOperations.rebuild') }}
        </AppButton>
      </div>
      <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded border border-[var(--ks-border)] p-3">
          <dt class="ks-kicker">{{ t('giftCodes.acquisitionOperations.codesMeasured') }}</dt>
          <dd class="mt-1 text-2xl font-semibold">{{ formatNumber(acquisitionEffectiveness.codesMeasured) }}</dd>
        </div>
        <div class="rounded border border-[var(--ks-border)] p-3">
          <dt class="ks-kicker">{{ t('giftCodes.acquisitionOperations.observations') }}</dt>
          <dd class="mt-1 text-2xl font-semibold">{{ formatNumber(acquisitionEffectiveness.observations) }}</dd>
        </div>
        <div class="rounded border border-[var(--ks-border)] p-3">
          <dt class="ks-kicker">{{ t('giftCodes.acquisitionOperations.medianTimeToCode') }}</dt>
          <dd class="mt-1 text-2xl font-semibold">{{ seconds(acquisitionEffectiveness.medianTimeToCodeSeconds) }}</dd>
        </div>
        <div class="rounded border border-[var(--ks-border)] p-3">
          <dt class="ks-kicker">{{ t('giftCodes.acquisitionOperations.p95TimeToCode') }}</dt>
          <dd class="mt-1 text-2xl font-semibold">{{ seconds(acquisitionEffectiveness.p95TimeToCodeSeconds) }}</dd>
        </div>
      </dl>
      <p class="mt-3 text-xs text-[var(--ks-muted)]">
        {{ t('giftCodes.acquisitionOperations.sourceSummary', {
          distinct: acquisitionEffectiveness.distinctSources,
          independent: acquisitionEffectiveness.independentSources,
          official: acquisitionEffectiveness.officialSources,
          samples: acquisitionEffectiveness.timeToCodeSamples,
        }) }}
      </p>
    </section>

    <section class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="source-operations">
      <h2 id="source-operations" class="ks-display text-xl font-semibold">
        {{ t('giftCodes.acquisitionOperations.sources') }}
      </h2>
      <p class="mt-2 text-sm text-[var(--ks-muted)]">
        {{ t('giftCodes.acquisitionOperations.sourcesHelp') }}
      </p>

      <ul v-if="sources.length" class="mt-4 grid gap-4 xl:grid-cols-2">
        <li
          v-for="source in sources"
          :key="source.id"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-4"
        >
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <strong>{{ source.name }}</strong>
              <p class="text-xs text-[var(--ks-muted)]">{{ source.classification }} · <code>{{ source.adapterKey ?? 'manual' }}</code></p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
              <span class="rounded border border-[var(--ks-border)] px-2 py-1">{{ source.activationStatus }}</span>
              <span class="rounded border border-[var(--ks-border)] px-2 py-1">{{ source.healthStatus }}</span>
            </div>
          </div>

          <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
            <div>
              <dt class="text-[var(--ks-muted)]">{{ t('giftCodes.acquisitionOperations.smoke') }}</dt>
              <dd v-if="source.latestSmokeCheck">
                {{ source.latestSmokeCheck.status }} · {{ formatDate(source.latestSmokeCheck.checkedAt) }} ·
                {{ source.latestSmokeCheck.durationMs }} ms
                <template v-if="source.latestSmokeCheck.failureCode"> · <code>{{ source.latestSmokeCheck.failureCode }}</code></template>
              </dd>
              <dd v-else>{{ t('giftCodes.acquisitionOperations.neverChecked') }}</dd>
            </div>
            <div>
              <dt class="text-[var(--ks-muted)]">{{ t('giftCodes.acquisitionOperations.transportHealth') }}</dt>
              <dd>
                {{ formatNumber(source.requestCount) }} {{ t('giftCodes.acquisitionOperations.requests') }} ·
                {{ formatNumber(source.reconciliationGapCount) }} {{ t('giftCodes.acquisitionOperations.gaps') }} ·
                {{ formatNumber(source.signatureFailureCount) }} {{ t('giftCodes.acquisitionOperations.signatureFailures') }} ·
                {{ formatNumber(source.replayRejectionCount) }} {{ t('giftCodes.acquisitionOperations.replays') }}
              </dd>
            </div>
          </dl>

          <div v-if="source.performance" class="mt-3 rounded border border-[var(--ks-border)] p-3 text-xs">
            <strong>{{ t('giftCodes.acquisitionOperations.performance') }}</strong>
            <p class="mt-1 text-[var(--ks-muted)]">
              {{ formatNumber(source.performance.uniqueCodesDiscovered) }} {{ t('giftCodes.acquisitionOperations.uniqueCodes') }} ·
              {{ formatNumber(source.performance.firstDiscoveries) }} {{ t('giftCodes.acquisitionOperations.firstDiscoveries') }} ·
              {{ percent(source.performance.usefulObservationRatio) }} {{ t('giftCodes.acquisitionOperations.useful') }} ·
              {{ percent(source.performance.quarantineRatio) }} {{ t('giftCodes.acquisitionOperations.quarantined') }}
            </p>
            <p class="mt-1 text-[var(--ks-muted)]">
              {{ t('giftCodes.acquisitionOperations.medianTimeToCode') }}: {{ seconds(source.performance.medianTimeToCodeSeconds) }} ·
              {{ t('giftCodes.acquisitionOperations.p95TimeToCode') }}: {{ seconds(source.performance.p95TimeToCodeSeconds) }}
            </p>
          </div>

          <div v-if="canManagePlatformPolicy && source.active" class="mt-4 flex flex-wrap gap-2">
            <AppButton
              v-if="source.adapterKey"
              type="button"
              variant="secondary"
              :busy="busy === `${source.id}:smoke`"
              @click="runSourceAction(source, 'smoke')"
            >
              {{ t('giftCodes.acquisitionOperations.runSmoke') }}
            </AppButton>
            <AppButton
              v-if="source.ingestionEnabled && source.headPollEnabled"
              type="button"
              variant="secondary"
              :busy="busy === `${source.id}:head`"
              @click="runSourceAction(source, 'head')"
            >
              {{ t('giftCodes.acquisitionOperations.runHead') }}
            </AppButton>
            <AppButton
              v-if="source.ingestionEnabled && source.reconciliationEnabled"
              type="button"
              variant="secondary"
              :busy="busy === `${source.id}:reconcile`"
              @click="runSourceAction(source, 'reconcile')"
            >
              {{ t('giftCodes.acquisitionOperations.reconcile') }}
            </AppButton>
            <AppButton
              v-if="source.ingestionEnabled && source.backfillEnabled"
              type="button"
              variant="secondary"
              :busy="busy === `${source.id}:backfill`"
              @click="runSourceAction(source, 'backfill')"
            >
              {{ t('giftCodes.acquisitionOperations.backfill') }}
            </AppButton>
            <AppButton
              v-if="source.adapterKey && !source.ingestionEnabled"
              type="button"
              :busy="busy === `${source.id}:controls`"
              @click="setControls(source, { ingestionEnabled: true })"
            >
              {{ t('giftCodes.acquisitionOperations.enableAcquisition') }}
            </AppButton>
            <AppButton
              v-if="source.ingestionEnabled"
              type="button"
              variant="secondary"
              :busy="busy === `${source.id}:controls`"
              @click="setControls(source, { ingestionEnabled: false, pushEnabled: false })"
            >
              {{ t('giftCodes.acquisitionOperations.disableAcquisition') }}
            </AppButton>
            <AppButton
              v-if="source.classification === 'official'"
              type="button"
              variant="secondary"
              :busy="busy === `${source.id}:controls`"
              @click="setControls(source, { authorityPromotionEnabled: !source.authorityPromotionEnabled })"
            >
              {{ source.authorityPromotionEnabled
                ? t('giftCodes.acquisitionOperations.disableAuthority')
                : t('giftCodes.acquisitionOperations.enableAuthority') }}
            </AppButton>
          </div>
        </li>
      </ul>
      <p v-else class="mt-4 text-sm text-[var(--ks-muted)]">
        {{ t('giftCodes.acquisitionOperations.noSources') }}
      </p>
    </section>
  </AppLayout>
</template>
