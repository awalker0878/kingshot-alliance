<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AppButton from '@/components/ui/AppButton.vue';
import FormError from '@/components/ui/FormError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Source = {
  id: string;
  name: string;
  classification: string;
  canonicalDomain: string | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  sources: Source[];
}>();

const { t } = useLocale();
const evidence = useForm({
  source_id: '',
  code: '',
  assertion: 'available',
  source_url: '',
  published_at: '',
  expires_at: '',
  expiry_precision: 'day',
  expiry_timezone: '',
  reward_name: '',
  reward_quantity: '',
  applicability_kingdoms: '',
});

function assertionPayload(data: typeof evidence.data): Record<string, unknown> | null {
  if (data.assertion === 'reward') {
    const quantity = Number.parseInt(data.reward_quantity, 10);
    return {
      items: [
        {
          name: data.reward_name.trim(),
          quantity: Number.isFinite(quantity) ? quantity : 0,
        },
      ],
    };
  }

  if (data.assertion === 'applicability') {
    const kingdoms = data.applicability_kingdoms
      .split(/[\s,]+/)
      .map((value) => Number.parseInt(value, 10))
      .filter((value) => Number.isInteger(value) && value > 0);
    return { kingdoms: [...new Set(kingdoms)] };
  }

  return null;
}

function recordEvidence(): void {
  evidence
    .transform((data) => ({
      source_id: data.source_id,
      code: data.code,
      assertion: data.assertion,
      source_url: data.source_url,
      published_at: data.published_at || null,
      expires_at: data.assertion === 'expires' ? data.expires_at || null : null,
      expiry_precision: data.assertion === 'expires' ? data.expiry_precision || null : null,
      expiry_timezone: data.assertion === 'expires' ? data.expiry_timezone || null : null,
      assertion_payload: assertionPayload(data),
    }))
    .post('/platform/gift-codes/sources/evidence', {
      preserveScroll: true,
      onSuccess: () => evidence.reset(),
    });
}
</script>

<template>
  <Head :title="t('giftCodes.acquisitionOperations.evidencePageTitle')" />

  <AppLayout :user="user">
    <header class="ks-surface p-5 sm:p-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="ks-kicker">{{ t('giftCodes.acquisitionOperations.eyebrow') }}</p>
          <h1 class="ks-display mt-1 text-3xl font-semibold">
            {{ t('giftCodes.acquisitionOperations.evidenceTitle') }}
          </h1>
          <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('giftCodes.acquisitionOperations.evidenceHelp') }}
          </p>
        </div>
        <Link href="/platform/gift-codes/sources/operations" class="ks-command-link" data-variant="secondary">
          {{ t('giftCodes.acquisitionOperations.backOperations') }}
        </Link>
      </div>
    </header>

    <section class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="evidence-entry">
      <h2 id="evidence-entry" class="ks-display text-xl font-semibold">
        {{ t('giftCodes.acquisitionOperations.recordEvidence') }}
      </h2>
      <p class="mt-2 text-sm text-[var(--ks-muted)]">
        {{ t('giftCodes.acquisitionOperations.evidenceBoundary') }}
      </p>

      <form v-if="sources.length" class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="recordEvidence">
        <label>
          <span class="ks-kicker">{{ t('giftCodes.acquisitionOperations.registeredSource') }}</span>
          <select v-model="evidence.source_id" required class="ks-input mt-2 w-full">
            <option value="" disabled>{{ t('giftCodes.acquisitionOperations.selectSource') }}</option>
            <option v-for="source in props.sources" :key="source.id" :value="source.id">
              {{ source.name }} · {{ source.classification }}
            </option>
          </select>
          <FormError :message="evidence.errors.source_id" />
        </label>

        <label>
          <span class="ks-kicker">{{ t('giftCodes.acquisitionOperations.code') }}</span>
          <input v-model="evidence.code" required maxlength="64" class="ks-input mt-2 w-full font-mono" />
          <FormError :message="evidence.errors.code" />
        </label>

        <label>
          <span class="ks-kicker">{{ t('giftCodes.acquisitionOperations.assertion') }}</span>
          <select v-model="evidence.assertion" class="ks-input mt-2 w-full">
            <option value="available">{{ t('giftCodes.acquisitionOperations.available') }}</option>
            <option value="invalid">{{ t('giftCodes.acquisitionOperations.invalid') }}</option>
            <option value="expires">{{ t('giftCodes.acquisitionOperations.expires') }}</option>
            <option value="reward">{{ t('giftCodes.acquisitionOperations.reward') }}</option>
            <option value="applicability">{{ t('giftCodes.acquisitionOperations.applicability') }}</option>
          </select>
        </label>

        <label>
          <span class="ks-kicker">{{ t('giftCodes.acquisitionOperations.sourceUrl') }}</span>
          <input v-model="evidence.source_url" required type="url" class="ks-input mt-2 w-full" />
          <FormError :message="evidence.errors.source_url" />
        </label>

        <label>
          <span class="ks-kicker">{{ t('giftCodes.acquisitionOperations.publishedAt') }}</span>
          <input v-model="evidence.published_at" type="datetime-local" class="ks-input mt-2 w-full" />
        </label>

        <template v-if="evidence.assertion === 'expires'">
          <label>
            <span class="ks-kicker">{{ t('giftCodes.acquisitionOperations.expiresAt') }}</span>
            <input v-model="evidence.expires_at" required type="datetime-local" class="ks-input mt-2 w-full" />
          </label>
          <label>
            <span class="ks-kicker">{{ t('giftCodes.acquisitionOperations.expiryPrecision') }}</span>
            <select v-model="evidence.expiry_precision" class="ks-input mt-2 w-full">
              <option value="instant">{{ t('giftCodes.acquisitionOperations.precisionInstant') }}</option>
              <option value="minute">{{ t('giftCodes.acquisitionOperations.precisionMinute') }}</option>
              <option value="hour">{{ t('giftCodes.acquisitionOperations.precisionHour') }}</option>
              <option value="day">{{ t('giftCodes.acquisitionOperations.precisionDay') }}</option>
              <option value="approximate">{{ t('giftCodes.acquisitionOperations.precisionApproximate') }}</option>
            </select>
          </label>
          <label>
            <span class="ks-kicker">{{ t('giftCodes.acquisitionOperations.expiryTimezone') }}</span>
            <input v-model="evidence.expiry_timezone" maxlength="80" class="ks-input mt-2 w-full" />
          </label>
        </template>

        <template v-if="evidence.assertion === 'reward'">
          <label>
            <span class="ks-kicker">{{ t('giftCodes.acquisitionOperations.rewardName') }}</span>
            <input v-model="evidence.reward_name" required maxlength="120" class="ks-input mt-2 w-full" />
          </label>
          <label>
            <span class="ks-kicker">{{ t('giftCodes.acquisitionOperations.rewardQuantity') }}</span>
            <input v-model="evidence.reward_quantity" required type="number" min="1" max="2147483647" class="ks-input mt-2 w-full" />
          </label>
        </template>

        <label v-if="evidence.assertion === 'applicability'" class="md:col-span-2">
          <span class="ks-kicker">{{ t('giftCodes.acquisitionOperations.kingdoms') }}</span>
          <input
            v-model="evidence.applicability_kingdoms"
            required
            class="ks-input mt-2 w-full"
            :placeholder="t('giftCodes.acquisitionOperations.kingdomsPlaceholder')"
          />
          <p class="mt-1 text-xs text-[var(--ks-muted)]">
            {{ t('giftCodes.acquisitionOperations.kingdomsHelp') }}
          </p>
        </label>

        <div class="md:col-span-2">
          <AppButton type="submit" :busy="evidence.processing">
            {{ t('giftCodes.acquisitionOperations.submitEvidence') }}
          </AppButton>
        </div>
      </form>

      <p v-else class="mt-4 text-sm text-[var(--ks-muted)]">
        {{ t('giftCodes.acquisitionOperations.noManualSources') }}
      </p>
    </section>
  </AppLayout>
</template>
