<script lang="ts">
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
</script>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { useLocale } from '@/localization';

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
  <section class="ks-surface" aria-labelledby="intelligence-change-heading">
    <header>
      <p class="ks-kicker">{{ t('intelligenceChange.title') }}</p>
      <h2 id="intelligence-change-heading" class="ks-display">
        {{ t('intelligenceChange.title') }}
      </h2>
      <p v-if="!compact">
        {{ t('intelligenceChange.subtitle') }}
      </p>
      <span v-if="signals.length" class="ks-status" data-tone="neutral">
        {{ signals.length }}
      </span>
    </header>

    <div v-if="signals.length === 0" class="ks-fantasy-empty" role="status">
      {{ t('intelligenceChange.empty') }}
    </div>

    <ol v-else>
      <li v-for="signal in signals" :key="signal.fingerprint">
        <article>
          <header>
            <p>{{ displayMetric(signal) }}</p>
            <p>{{ signal.summary }}</p>
            <span class="ks-status" :data-tone="tone(signal)">
              {{
                signal.materiality === 'attention'
                  ? t('intelligenceChange.attention')
                  : t('intelligenceChange.material')
              }}
            </span>
          </header>

          <dl>
            <div>
              <dt>{{ t('intelligenceChange.observed') }}</dt>
              <dd>{{ formatDate(signal.observedAt) }}</dd>
            </div>
            <div v-if="signal.baselineObservedAt">
              <dt>{{ t('intelligenceChange.baseline') }}</dt>
              <dd>{{ formatDate(signal.baselineObservedAt) }}</dd>
            </div>
            <div v-if="displayValue(signal.currentValue) !== null">
              <dt>{{ t('intelligenceChange.current') }}</dt>
              <dd>{{ displayValue(signal.currentValue) }}</dd>
            </div>
            <div v-if="displayValue(signal.previousValue) !== null">
              <dt>{{ t('intelligenceChange.previous') }}</dt>
              <dd>{{ displayValue(signal.previousValue) }}</dd>
            </div>
          </dl>

          <footer>
            <span>{{ t('intelligenceChange.source') }}: {{ signal.sourceOwner }}</span>
            <Link v-if="signal.canonicalUrl" :href="signal.canonicalUrl">
              {{ t('intelligenceChange.viewSource') }}
            </Link>
          </footer>
        </article>
      </li>
    </ol>
  </section>
</template>
