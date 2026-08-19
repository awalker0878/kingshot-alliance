<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Redemption = {
  status: string;
  attempts: number;
  resultCode: string | null;
  message: string | null;
  redemptionUrl: string | null;
  lastAttemptAt: string | null;
  nextAttemptAt: string | null;
  redeemedAt: string | null;
};

type GiftCode = {
  id: string;
  code: string;
  sourceType: string;
  sourceLabel: string | null;
  sourceUrl: string | null;
  status: string;
  discoveredAt: string;
  expiresAt: string | null;
  redemption: Redemption | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  player: { id: string; name: string; gamePlayerId: string | null; kingdomNumber: number | null };
  allGovernorCount: number;
  officialRedemptionUrl: string;
  codes: GiftCode[];
}>();

const { t, formatDate, formatNumber } = useLocale();
const copied = ref<string | null>(null);
const submission = useForm({
  code: '',
  source_type: 'manual',
  source_label: '',
  source_url: '',
  expires_at: '',
});
const redemption = useForm({ scope: 'current' });

const activeCodes = computed(
  () => props.codes.filter((giftCode) => giftCode.status === 'active' && !expired(giftCode)).length,
);
const redeemedCodes = computed(
  () => props.codes.filter((giftCode) => giftCode.redemption?.status === 'redeemed').length,
);
const pendingCodes = computed(
  () => props.codes.filter((giftCode) => giftCode.redemption?.status === 'awaiting_confirmation').length,
);

function expired(giftCode: GiftCode): boolean {
  return giftCode.expiresAt !== null && new Date(giftCode.expiresAt).getTime() < Date.now();
}

function submit(): void {
  submission.post('/gift-codes', {
    preserveScroll: true,
    onSuccess: () => submission.reset(),
  });
}

function begin(giftCode: GiftCode, scope: 'current' | 'all'): void {
  redemption.scope = scope;
  redemption.post(`/gift-codes/${giftCode.id}/redeem`, { preserveScroll: true });
}

async function copy(giftCode: GiftCode): Promise<void> {
  await navigator.clipboard.writeText(giftCode.code);
  copied.value = giftCode.id;
  window.setTimeout(() => {
    if (copied.value === giftCode.id) copied.value = null;
  }, 1800);
}

function statusLabel(status: string): string {
  const keys: Record<string, string> = {
    awaiting_confirmation: 'awaitingConfirmation',
    redeemed: 'redeemed',
    already_redeemed: 'alreadyRedeemed',
    invalid_code: 'invalidCode',
    expired: 'expired',
    wrong_kingdom: 'wrongKingdom',
    rate_limited: 'rateLimited',
    transient_failure: 'tryAgain',
    permanent_failure: 'needsAttention',
  };
  return t(`giftCodes.${keys[status] ?? 'notStarted'}`);
}

function statusTone(status: string): 'success' | 'warning' | 'danger' | 'info' {
  if (status === 'redeemed' || status === 'already_redeemed') return 'success';
  if (status === 'awaiting_confirmation' || status === 'rate_limited') return 'warning';
  if (status === 'expired' || status === 'invalid_code' || status === 'permanent_failure') return 'danger';
  return 'info';
}
</script>

<template>
  <Head :title="t('giftCodes.title')" />

  <AppLayout :user="user">
    <RoomBanner
      :eyebrow="t('giftCodes.eyebrow')"
      :title="t('giftCodes.title')"
      :subtitle="t('giftCodes.subtitle', { governor: player.name })"
      image="/images/kingshot/v4/account-vault.svg"
    >
      <template #actions>
        <a
          :href="officialRedemptionUrl"
          target="_blank"
          rel="noreferrer"
          class="ks-command-link"
        >
          {{ t('giftCodes.openOfficialCenter') }}
        </a>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <StatSeal :label="t('giftCodes.activeCodes')" :value="formatNumber(activeCodes)" icon="◆" />
      <StatSeal :label="t('giftCodes.redeemed')" :value="formatNumber(redeemedCodes)" icon="✓" tone="teal" />
      <StatSeal :label="t('giftCodes.awaitingConfirmation')" :value="formatNumber(pendingCodes)" icon="◇" tone="stone" />
      <StatSeal :label="t('giftCodes.governors')" :value="formatNumber(allGovernorCount)" icon="♛" />
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.45fr)_minmax(22rem,.55fr)]">
      <section class="ks-surface overflow-hidden" aria-labelledby="gift-code-ledger-heading">
        <div class="border-b border-[var(--ks-border)] p-5 sm:p-6">
          <p class="ks-kicker">{{ t('giftCodes.sharedLedger') }}</p>
          <h2 id="gift-code-ledger-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('giftCodes.availableCodes') }}
          </h2>
          <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('giftCodes.ledgerHelp') }}
          </p>
        </div>

        <div v-if="codes.length" class="divide-y divide-[var(--ks-border)]">
          <article v-for="giftCode in codes" :key="giftCode.id" class="p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                  <code class="text-lg font-bold tracking-[.08em] text-[var(--ks-gold-bright)]">{{ giftCode.code }}</code>
                  <span
                    class="ks-status"
                    :data-tone="giftCode.redemption ? statusTone(giftCode.redemption.status) : expired(giftCode) ? 'danger' : 'info'"
                  >
                    {{ giftCode.redemption ? statusLabel(giftCode.redemption.status) : expired(giftCode) ? t('giftCodes.expired') : t('giftCodes.notStarted') }}
                  </span>
                </div>
                <p class="mt-2 text-xs text-[var(--ks-muted)]">
                  {{ giftCode.sourceLabel ?? t(`giftCodes.source_${giftCode.sourceType}`) }}
                  · {{ formatDate(giftCode.discoveredAt, { dateStyle: 'medium' }) }}
                  <template v-if="giftCode.expiresAt">
                    · {{ t('giftCodes.expires') }} {{ formatDate(giftCode.expiresAt, { dateStyle: 'medium' }) }}
                  </template>
                </p>
                <p v-if="giftCode.redemption?.message" class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
                  {{ giftCode.redemption.message }}
                </p>
              </div>

              <button type="button" class="ks-command-link" data-variant="secondary" @click="copy(giftCode)">
                {{ copied === giftCode.id ? t('giftCodes.copied') : t('giftCodes.copyCode') }}
              </button>
            </div>

            <div v-if="giftCode.status === 'active' && !expired(giftCode)" class="mt-4 flex flex-wrap gap-2">
              <button
                type="button"
                class="ks-command-link"
                :disabled="redemption.processing || !player.gamePlayerId"
                @click="begin(giftCode, 'current')"
              >
                {{ t('giftCodes.redeemForGovernor', { governor: player.name }) }}
              </button>
              <button
                v-if="allGovernorCount > 1"
                type="button"
                class="ks-command-link"
                data-variant="secondary"
                :disabled="redemption.processing"
                @click="begin(giftCode, 'all')"
              >
                {{ t('giftCodes.prepareAllGovernors') }}
              </button>
              <Link
                v-if="giftCode.redemption?.status === 'awaiting_confirmation'"
                :href="`/gift-codes/${giftCode.id}/confirm`"
                method="post"
                as="button"
                class="ks-command-link"
                data-variant="secondary"
              >
                {{ t('giftCodes.confirmDelivered') }}
              </Link>
              <a
                v-if="giftCode.sourceUrl"
                :href="giftCode.sourceUrl"
                target="_blank"
                rel="noreferrer"
                class="ks-command-link"
                data-variant="secondary"
              >
                {{ t('giftCodes.viewSource') }}
              </a>
            </div>
          </article>
        </div>
        <div v-else class="p-8 text-center text-sm text-[var(--ks-muted)]">
          {{ t('giftCodes.empty') }}
        </div>
      </section>

      <aside class="space-y-5">
        <section class="ks-surface-gold p-5 sm:p-6" aria-labelledby="submit-gift-code-heading">
          <p class="ks-kicker">{{ t('giftCodes.communityDiscovery') }}</p>
          <h2 id="submit-gift-code-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('giftCodes.addCode') }}
          </h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">{{ t('giftCodes.addCodeHelp') }}</p>

          <form class="mt-5 space-y-4" @submit.prevent="submit">
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.code') }}</span>
              <input v-model="submission.code" required maxlength="64" class="ks-input mt-2 w-full" autocomplete="off" />
              <span v-if="submission.errors.code" class="mt-1 block text-xs text-red-300">{{ submission.errors.code }}</span>
            </label>
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.source') }}</span>
              <select v-model="submission.source_type" class="ks-input mt-2 w-full">
                <option value="manual">{{ t('giftCodes.source_manual') }}</option>
                <option value="official">{{ t('giftCodes.source_official') }}</option>
                <option value="community">{{ t('giftCodes.source_community') }}</option>
              </select>
            </label>
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.sourceLabel') }}</span>
              <input v-model="submission.source_label" maxlength="160" class="ks-input mt-2 w-full" />
            </label>
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.sourceUrl') }}</span>
              <input v-model="submission.source_url" type="url" maxlength="2048" class="ks-input mt-2 w-full" />
            </label>
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.expiresAt') }}</span>
              <input v-model="submission.expires_at" type="date" class="ks-input mt-2 w-full" />
            </label>
            <button type="submit" class="ks-command-link w-full justify-center" :disabled="submission.processing">
              {{ submission.processing ? t('common.loading') : t('giftCodes.addToLedger') }}
            </button>
          </form>
        </section>

        <section v-if="!player.gamePlayerId" class="ks-surface p-5 sm:p-6">
          <p class="ks-kicker">{{ t('giftCodes.playerIdRequired') }}</p>
          <h2 class="ks-display mt-1 text-xl font-semibold">{{ t('giftCodes.addPlayerId') }}</h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">{{ t('giftCodes.addPlayerIdHelp') }}</p>
          <Link href="/profile" class="ks-command-link mt-4">{{ t('navigation.profile') }}</Link>
        </section>
      </aside>
    </div>
  </AppLayout>
</template>
