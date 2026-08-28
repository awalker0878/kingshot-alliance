<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { useLocale } from '@/localization';

export type IntelligenceSignal = {
  type: string;
  subjectType: string;
  subjectId: string;
  metric: string | null;
  summary: string;
  detectedAsOf: string;
  observedAt: string;
  baselineObservedAt: string | null;
  currentValue: unknown;
  previousValue: unknown;
  delta: string | number | null;
  percentChange: number | null;
  state: string;
  materiality: string;
  sourceClassification: string;
  sourceOwner: string;
  sourceRecordIds: string[];
  canonicalUrl: string | null;
  fingerprint: string;
  ruleVersion: string;
};

withDefaults(
  defineProps<{
    signals: IntelligenceSignal[];
    compact?: boolean;
  }>(),
  { compact: false },
);

const { t, formatDate } = useLocale();

function displayMetric(signal: IntelligenceSignal): string {
  return (signal.metric ?? signal.type).replaceAll('_', ' ');
}

function displayValue(value: unknown): string | null {
  if (value === null || value === undefined || value === '') return null;
  if (typeof value === 'number') return new Intl.NumberFormat().format(value);
  if (typeof value === 'string') {
    if (/^-?\d+$/.test(value)) return new Intl.NumberFormat().format(Number(value));
    return value;
  }
  return null;
}

function tone(signal: IntelligenceSignal): string {
  if (['stale', 'expired', 'expiring'].includes(signal.state)) return 'warning';
  return 'neutral';
}
</script>

<template>
  <section class="ks-surface p-5 sm:p-6" aria-labelledby="intelligence-change-heading">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="max-w-3xl">
        <p class="ks-kicker">{{ t('intelligenceChange.title') }}</p>
        <h2 id="intelligence-change-heading" class="ks-display mt-1 text-xl font-semibold sm:text-2xl">
          {{ t('intelligenceChange.title') }}
        </h2>
        <p v-if="!compact" class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('intelligenceChange.subtitle') }}
        </p>
      </div>
      <span v-if="signals.length" class="ks-status" data-tone="neutral">{{ signals.length }}</span>
    </div>

    <div v-if="signals.length === 0" class="ks-fantasy-empty mt-4" role="status">
      {{ t('intelligenceChange.empty') }}
    </div>

    <ol v-else class="mt-4 grid gap-3" :class="compact ? '' : 'lg:grid-cols-2'">
      <li
        v-for="signal in signals"
        :key="signal.fingerprint"
        class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
      >
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--ks-muted)]">
              {{ displayMetric(signal) }}
            </p>
            <p class="mt-2 text-sm leading-6 text-[var(--ks-ivory)]">{{ signal.summary }}</p>
          </div>
          <span class="ks-status" :data-tone="tone(signal)">
            {{
              signal.materiality === 'attention'
                ? t('intelligenceChange.attention')
                : t('intelligenceChange.material')
            }}
          </span>
        </div>

        <dl class="mt-3 grid gap-x-4 gap-y-2 text-xs sm:grid-cols-2">
          <div>
            <dt class="text-[var(--ks-muted)]">{{ t('intelligenceChange.observed') }}</dt>
            <dd class="mt-0.5 text-[var(--ks-text-secondary)]">{{ formatDate(signal.observedAt) }}</dd>
          </div>
          <div v-if="signal.baselineObservedAt">
            <dt class="text-[var(--ks-muted)]">{{ t('intelligenceChange.baseline') }}</dt>
            <dd class="mt-0.5 text-[var(--ks-text-secondary)]">
              {{ formatDate(signal.baselineObservedAt) }}
            </dd>
          </div>
          <div v-if="displayValue(signal.currentValue) !== null">
            <dt class="text-[var(--ks-muted)]">{{ t('intelligenceChange.current') }}</dt>
            <dd class="mt-0.5 break-words text-[var(--ks-text-secondary)]">
              {{ displayValue(signal.currentValue) }}
            </dd>
          </div>
          <div v-if="displayValue(signal.previousValue) !== null">
            <dt class="text-[var(--ks-muted)]">{{ t('intelligenceChange.previous') }}</dt>
            <dd class="mt-0.5 break-words text-[var(--ks-text-secondary)]">
              {{ displayValue(signal.previousValue) }}
            </dd>
          </div>
        </dl>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-[var(--ks-border)] pt-3 text-xs">
          <span class="text-[var(--ks-muted)]">
            {{ t('intelligenceChange.source') }}: {{ signal.sourceOwner }}
          </span>
          <Link
            v-if="signal.canonicalUrl"
            :href="signal.canonicalUrl"
            class="font-semibold text-[var(--ks-teal-bright)] hover:underline"
          >
            {{ t('intelligenceChange.viewSource') }}
          </Link>
        </div>
      </li>
    </ol>
  </section>
</template>
