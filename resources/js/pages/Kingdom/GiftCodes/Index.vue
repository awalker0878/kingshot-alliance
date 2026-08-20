<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import ActionNotice from '@/components/ui/ActionNotice.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import FormError from '@/components/ui/FormError.vue';
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
type Governor = {
  id: string;
  name: string;
  gamePlayerId: string | null;
  kingdomNumber: number | null;
};
type GovernorRedemption = Redemption & { playerId: string; playerName: string };
type Provenance = {
  id: string;
  sourceType: string;
  sourceLabel: string | null;
  sourceUrl: string | null;
  observedAt: string;
};
type RedemptionResult = {
  action: string;
  items: Array<{
    itemId: string;
    label: string;
    outcome: 'succeeded' | 'failed' | 'skipped';
    code: string;
  }>;
  succeeded: number;
  failed: number;
  skipped: number;
  failedItemIds: string[];
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
  redemptions: GovernorRedemption[];
  provenances: Provenance[];
};

const props = defineProps<{
  user: { name: string; email: string };
  player: { id: string; name: string; gamePlayerId: string | null; kingdomNumber: number | null };
  governors: Governor[];
  officialRedemptionUrl: string;
  codes: GiftCode[];
  giftCodeRedemptionResult: RedemptionResult | null;
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
const redemption = useForm({ player_ids: [] as string[] });
const redemptionProcessing = ref<{ giftCodeId: string; key: 'current' | 'all' | 'failed' } | null>(
  null,
);
const confirmingCode = ref<string | null>(null);
const operationError = ref<string | null>(null);
const reportTarget = ref<{ giftCode: GiftCode; issue: 'invalid' | 'expired' } | null>(null);
const reportBusy = ref(false);

const activeCodes = computed(
  () => props.codes.filter((giftCode) => redeemable(giftCode) && !expired(giftCode)).length,
);
const allGovernorCount = computed(() => props.governors.length);
const redeemedCodes = computed(
  () => props.codes.filter((giftCode) => giftCode.redemption?.status === 'redeemed').length,
);
const pendingCodes = computed(
  () =>
    props.codes.filter((giftCode) => giftCode.redemption?.status === 'awaiting_confirmation')
      .length,
);
function expired(giftCode: GiftCode): boolean {
  return giftCode.expiresAt !== null && new Date(giftCode.expiresAt).getTime() < Date.now();
}

function submit(): void {
  operationError.value = null;
  submission.post('/gift-codes', {
    preserveScroll: true,
    onError: captureOperationError,
    onSuccess: () => submission.reset(),
  });
}

function begin(giftCode: GiftCode, playerIds: string[], key: 'current' | 'all' | 'failed'): void {
  if (redemptionProcessing.value !== null || playerIds.length === 0) return;

  operationError.value = null;
  redemptionProcessing.value = { giftCodeId: giftCode.id, key };
  window.open(
    giftCode.redemption?.redemptionUrl ?? props.officialRedemptionUrl,
    '_blank',
    'noopener',
  );
  redemption.player_ids = playerIds;
  redemption.post(`/gift-codes/${giftCode.id}/redeem`, {
    preserveScroll: true,
    onError: captureOperationError,
    onFinish: () => (redemptionProcessing.value = null),
  });
}

function confirm(giftCode: GiftCode): void {
  if (confirmingCode.value !== null) return;

  operationError.value = null;
  confirmingCode.value = giftCode.id;
  router.post(
    `/gift-codes/${giftCode.id}/confirm`,
    {},
    {
      preserveScroll: true,
      onError: captureOperationError,
      onFinish: () => (confirmingCode.value = null),
    },
  );
}

async function copy(giftCode: GiftCode): Promise<void> {
  try {
    await navigator.clipboard.writeText(giftCode.code);
    copied.value = giftCode.id;
    window.setTimeout(() => {
      if (copied.value === giftCode.id) copied.value = null;
    }, 1800);
  } catch {
    operationError.value = t('giftCodes.copyFailed');
  }
}

function captureOperationError(errors: Record<string, string>): void {
  operationError.value = Object.values(errors)[0] ?? t('giftCodes.operationFailed');
}

function redemptionComplete(giftCode: GiftCode): boolean {
  return ['redeemed', 'already_redeemed'].includes(giftCode.redemption?.status ?? '');
}

function retryBlocked(giftCode: GiftCode): boolean {
  const redemptionState = giftCode.redemption;
  return (
    redemptionState !== null &&
    ['rate_limited', 'transient_failure'].includes(redemptionState.status) &&
    redemptionState.nextAttemptAt !== null &&
    new Date(redemptionState.nextAttemptAt).getTime() > Date.now()
  );
}

function redemptionBusy(giftCode: GiftCode, key: 'current' | 'all' | 'failed'): boolean {
  return (
    redemptionProcessing.value?.giftCodeId === giftCode.id && redemptionProcessing.value.key === key
  );
}

function redeemable(giftCode: GiftCode): boolean {
  return ['pending', 'valid', 'disputed'].includes(giftCode.status);
}

function failedGovernorIds(giftCode: GiftCode): string[] {
  const now = Date.now();
  return giftCode.redemptions
    .filter((item) => {
      if (!['rate_limited', 'transient_failure', 'permanent_failure'].includes(item.status)) {
        return false;
      }
      return item.nextAttemptAt === null || new Date(item.nextAttemptAt).getTime() <= now;
    })
    .map((item) => item.playerId);
}

function codeStatusLabel(status: string): string {
  return t(`giftCodes.trust.${status}`);
}

function codeStatusTone(status: string): 'success' | 'warning' | 'danger' | 'info' {
  if (status === 'valid') return 'success';
  if (status === 'pending' || status === 'disputed') return 'warning';
  if (status === 'invalid' || status === 'expired') return 'danger';
  return 'info';
}

function redemptionResultLabel(code: string): string {
  return t(`giftCodes.results.${code}`);
}

function requestReport(giftCode: GiftCode, issue: 'invalid' | 'expired'): void {
  reportTarget.value = { giftCode, issue };
}

function reportIssue(): void {
  const target = reportTarget.value;
  if (!target || reportBusy.value) return;

  reportBusy.value = true;
  router.post(
    `/gift-codes/${target.giftCode.id}/report`,
    { issue: target.issue },
    {
      preserveScroll: true,
      onError: captureOperationError,
      onFinish: () => {
        reportBusy.value = false;
        reportTarget.value = null;
      },
    },
  );
}

function redemptionMessage(redemptionState: Redemption): string | null {
  const keys: Record<string, string> = {
    official_handoff_ready: 'officialHandoffReady',
    missing_player_id: 'missingPlayerIdMessage',
    code_unavailable: 'codeUnavailableMessage',
    code_expired: 'codeExpiredMessage',
  };
  const key = redemptionState.resultCode ? keys[redemptionState.resultCode] : undefined;

  return key ? t(`giftCodes.${key}`) : redemptionState.message;
}

function attemptLabel(count: number): string {
  return t(count === 1 ? 'giftCodes.singleAttempt' : 'giftCodes.attemptCount', { count });
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
  if (status === 'expired' || status === 'invalid_code' || status === 'permanent_failure')
    return 'danger';
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
        <a :href="officialRedemptionUrl" target="_blank" rel="noreferrer" class="ks-command-link">
          {{ t('giftCodes.openOfficialCenter') }}
        </a>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <StatSeal :label="t('giftCodes.activeCodes')" :value="formatNumber(activeCodes)" icon="◆" />
      <StatSeal
        :label="t('giftCodes.redeemed')"
        :value="formatNumber(redeemedCodes)"
        icon="✓"
        tone="teal"
      />
      <StatSeal
        :label="t('giftCodes.awaitingConfirmation')"
        :value="formatNumber(pendingCodes)"
        icon="◇"
        tone="stone"
      />
      <StatSeal
        :label="t('giftCodes.governors')"
        :value="formatNumber(allGovernorCount)"
        icon="♛"
      />
    </section>

    <ActionNotice class="mt-5" :message="operationError" tone="danger" />

    <section
      v-if="giftCodeRedemptionResult"
      class="ks-surface mt-5 p-5"
      aria-labelledby="gift-code-redemption-result-title"
    >
      <p class="ks-kicker">{{ t('giftCodes.perGovernorResult') }}</p>
      <h2 id="gift-code-redemption-result-title" class="mt-1 text-lg font-semibold">
        {{
          t('giftCodes.resultSummary', {
            succeeded: giftCodeRedemptionResult.succeeded,
            failed: giftCodeRedemptionResult.failed,
            skipped: giftCodeRedemptionResult.skipped,
          })
        }}
      </h2>
      <ul class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
        <li
          v-for="item in giftCodeRedemptionResult.items"
          :key="item.itemId"
          class="flex items-center justify-between gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 px-3 py-2 text-sm"
        >
          <span class="truncate">{{ item.label }}</span>
          <span
            class="ks-status"
            :data-tone="
              item.outcome === 'succeeded'
                ? 'success'
                : item.outcome === 'skipped'
                  ? 'warning'
                  : 'danger'
            "
          >
            {{ redemptionResultLabel(item.code) }}
          </span>
        </li>
      </ul>
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
                  <code class="text-lg font-bold tracking-[.08em] text-[var(--ks-gold-bright)]">{{
                    giftCode.code
                  }}</code>
                  <span
                    class="ks-status"
                    :data-tone="codeStatusTone(expired(giftCode) ? 'expired' : giftCode.status)"
                  >
                    {{ codeStatusLabel(expired(giftCode) ? 'expired' : giftCode.status) }}
                  </span>
                  <span
                    class="ks-status"
                    :data-tone="
                      giftCode.redemption ? statusTone(giftCode.redemption.status) : 'info'
                    "
                  >
                    {{
                      giftCode.redemption
                        ? statusLabel(giftCode.redemption.status)
                        : t('giftCodes.notStarted')
                    }}
                  </span>
                </div>
                <p class="mt-2 text-xs text-[var(--ks-muted)]">
                  {{ giftCode.sourceLabel ?? t(`giftCodes.source_${giftCode.sourceType}`) }}
                  · {{ formatDate(giftCode.discoveredAt, { dateStyle: 'medium' }) }}
                  <template v-if="giftCode.expiresAt">
                    · {{ t('giftCodes.expires') }}
                    {{ formatDate(giftCode.expiresAt, { dateStyle: 'medium' }) }}
                  </template>
                </p>
                <p
                  v-if="giftCode.redemption && redemptionMessage(giftCode.redemption)"
                  class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]"
                >
                  {{ redemptionMessage(giftCode.redemption) }}
                </p>
                <p
                  v-if="giftCode.redemption?.lastAttemptAt"
                  class="mt-2 text-xs text-[var(--ks-muted)]"
                >
                  {{ t('giftCodes.lastAttempt') }}:
                  {{
                    formatDate(giftCode.redemption.lastAttemptAt, {
                      dateStyle: 'medium',
                      timeStyle: 'short',
                    })
                  }}
                  · {{ attemptLabel(giftCode.redemption.attempts) }}
                </p>
                <p
                  v-if="retryBlocked(giftCode) && giftCode.redemption?.nextAttemptAt"
                  class="mt-1 text-xs text-amber-200"
                >
                  {{ t('giftCodes.retryAfter') }}
                  {{
                    formatDate(giftCode.redemption.nextAttemptAt, {
                      dateStyle: 'medium',
                      timeStyle: 'short',
                    })
                  }}
                </p>
              </div>

              <button
                type="button"
                class="ks-command-link"
                data-variant="secondary"
                @click="copy(giftCode)"
              >
                {{ copied === giftCode.id ? t('giftCodes.copied') : t('giftCodes.copyCode') }}
              </button>
            </div>

            <div
              v-if="redeemable(giftCode) && !expired(giftCode)"
              class="mt-4 flex flex-wrap gap-2"
            >
              <AppButton
                type="button"
                :busy="redemptionBusy(giftCode, 'current')"
                :busy-label="t('giftCodes.preparingHandoff')"
                :disabled="
                  redemptionProcessing !== null ||
                  !player.gamePlayerId ||
                  retryBlocked(giftCode) ||
                  redemptionComplete(giftCode)
                "
                @click="begin(giftCode, [player.id], 'current')"
              >
                {{ t('giftCodes.redeemForGovernor', { governor: player.name }) }}
              </AppButton>
              <AppButton
                v-if="allGovernorCount > 1"
                type="button"
                variant="secondary"
                :busy="redemptionBusy(giftCode, 'all')"
                :busy-label="t('giftCodes.preparingHandoff')"
                :disabled="redemptionProcessing !== null"
                @click="
                  begin(
                    giftCode,
                    governors.map((governor) => governor.id),
                    'all',
                  )
                "
              >
                {{ t('giftCodes.prepareAllGovernors') }}
              </AppButton>
              <AppButton
                v-if="failedGovernorIds(giftCode).length"
                type="button"
                variant="secondary"
                :busy="redemptionBusy(giftCode, 'failed')"
                :busy-label="t('giftCodes.retryingFailed')"
                :disabled="redemptionProcessing !== null"
                @click="begin(giftCode, failedGovernorIds(giftCode), 'failed')"
              >
                {{
                  t('giftCodes.retryFailedGovernors', {
                    count: failedGovernorIds(giftCode).length,
                  })
                }}
              </AppButton>
              <AppButton
                v-if="giftCode.redemption?.status === 'awaiting_confirmation'"
                variant="secondary"
                :busy="confirmingCode === giftCode.id"
                :busy-label="t('giftCodes.confirmingDelivered')"
                :disabled="confirmingCode !== null && confirmingCode !== giftCode.id"
                @click="confirm(giftCode)"
              >
                {{ t('giftCodes.confirmDelivered') }}
              </AppButton>
              <a
                v-if="giftCode.redemption?.status === 'awaiting_confirmation'"
                :href="giftCode.redemption.redemptionUrl ?? officialRedemptionUrl"
                target="_blank"
                rel="noreferrer"
                class="ks-command-link"
                data-variant="secondary"
              >
                {{ t('giftCodes.continueOfficialCenter') }}
              </a>
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
              <AppButton variant="ghost" @click="requestReport(giftCode, 'invalid')">
                {{ t('giftCodes.reportInvalid') }}
              </AppButton>
              <AppButton variant="ghost" @click="requestReport(giftCode, 'expired')">
                {{ t('giftCodes.reportExpired') }}
              </AppButton>
            </div>

            <details v-if="giftCode.provenances.length" class="mt-4">
              <summary class="cursor-pointer text-sm font-semibold text-[var(--ks-text-secondary)]">
                {{ t('giftCodes.sourceHistory', { count: giftCode.provenances.length }) }}
              </summary>
              <ul class="mt-3 grid gap-2 md:grid-cols-2">
                <li
                  v-for="source in giftCode.provenances"
                  :key="source.id"
                  class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3 text-xs"
                >
                  <a
                    v-if="source.sourceUrl"
                    :href="source.sourceUrl"
                    target="_blank"
                    rel="noreferrer"
                    class="font-semibold text-[var(--ks-blue-strong)]"
                  >
                    {{ source.sourceLabel ?? t(`giftCodes.source_${source.sourceType}`) }}
                  </a>
                  <strong v-else>{{
                    source.sourceLabel ?? t(`giftCodes.source_${source.sourceType}`)
                  }}</strong>
                  <p class="mt-1 text-[var(--ks-muted)]">
                    {{ formatDate(source.observedAt, { dateStyle: 'medium' }) }}
                  </p>
                </li>
              </ul>
            </details>

            <div v-if="giftCode.redemptions.length" class="mt-4">
              <p class="ks-kicker">{{ t('giftCodes.governorReceipts') }}</p>
              <ul class="mt-2 grid gap-2 md:grid-cols-2">
                <li
                  v-for="item in giftCode.redemptions"
                  :key="item.playerId"
                  class="flex items-center justify-between gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 px-3 py-2 text-sm"
                >
                  <span class="truncate">{{ item.playerName }}</span>
                  <span class="ks-status" :data-tone="statusTone(item.status)">
                    {{ statusLabel(item.status) }}
                  </span>
                </li>
              </ul>
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
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('giftCodes.addCodeHelp') }}
          </p>

          <form class="mt-5 space-y-4" @submit.prevent="submit">
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.code') }}</span>
              <input
                v-model="submission.code"
                required
                maxlength="64"
                class="ks-input mt-2 w-full"
                autocomplete="off"
                :aria-invalid="submission.errors.code ? 'true' : undefined"
                :aria-describedby="submission.errors.code ? 'gift-code-error' : undefined"
              />
              <FormError id="gift-code-error" :message="submission.errors.code" />
            </label>
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.source') }}</span>
              <select
                v-model="submission.source_type"
                class="ks-input mt-2 w-full"
                :aria-invalid="submission.errors.source_type ? 'true' : undefined"
                :aria-describedby="
                  submission.errors.source_type ? 'gift-code-source-error' : undefined
                "
              >
                <option value="manual">{{ t('giftCodes.source_manual') }}</option>
                <option value="official">{{ t('giftCodes.source_official') }}</option>
                <option value="community">{{ t('giftCodes.source_community') }}</option>
              </select>
              <FormError id="gift-code-source-error" :message="submission.errors.source_type" />
            </label>
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.sourceLabel') }}</span>
              <input
                v-model="submission.source_label"
                maxlength="160"
                class="ks-input mt-2 w-full"
                :aria-invalid="submission.errors.source_label ? 'true' : undefined"
                :aria-describedby="
                  submission.errors.source_label ? 'gift-code-source-label-error' : undefined
                "
              />
              <FormError
                id="gift-code-source-label-error"
                :message="submission.errors.source_label"
              />
            </label>
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.sourceUrl') }}</span>
              <input
                v-model="submission.source_url"
                type="url"
                maxlength="2048"
                class="ks-input mt-2 w-full"
                :aria-invalid="submission.errors.source_url ? 'true' : undefined"
                :aria-describedby="
                  submission.errors.source_url ? 'gift-code-source-url-error' : undefined
                "
              />
              <FormError id="gift-code-source-url-error" :message="submission.errors.source_url" />
            </label>
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.expiresAt') }}</span>
              <input
                v-model="submission.expires_at"
                type="date"
                class="ks-input mt-2 w-full"
                :aria-invalid="submission.errors.expires_at ? 'true' : undefined"
                :aria-describedby="
                  submission.errors.expires_at ? 'gift-code-expires-error' : undefined
                "
              />
              <FormError id="gift-code-expires-error" :message="submission.errors.expires_at" />
            </label>
            <AppButton
              type="submit"
              class="w-full"
              :busy="submission.processing"
              :busy-label="t('giftCodes.addingToLedger')"
            >
              {{ t('giftCodes.addToLedger') }}
            </AppButton>
          </form>
        </section>

        <section v-if="!player.gamePlayerId" class="ks-surface p-5 sm:p-6">
          <p class="ks-kicker">{{ t('giftCodes.playerIdRequired') }}</p>
          <h2 class="ks-display mt-1 text-xl font-semibold">{{ t('giftCodes.addPlayerId') }}</h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('giftCodes.addPlayerIdHelp') }}
          </p>
          <Link href="/profile" class="ks-command-link mt-4">{{ t('navigation.profile') }}</Link>
        </section>
      </aside>
    </div>

    <ConfirmActionDialog
      id="gift-code-report-confirmation"
      :open="reportTarget !== null"
      :title="t('giftCodes.reportIssueTitle')"
      :description="
        t('giftCodes.reportIssueDescription', {
          code: reportTarget?.giftCode.code ?? '',
          issue:
            reportTarget?.issue === 'expired'
              ? t('giftCodes.expiredLower')
              : t('giftCodes.invalidLower'),
        })
      "
      :confirm-label="t('giftCodes.reportIssueConfirm')"
      :cancel-label="t('common.cancel')"
      :busy="reportBusy"
      :busy-label="t('giftCodes.reportingIssue')"
      danger
      @confirm="reportIssue"
      @cancel="reportTarget = null"
    />
  </AppLayout>
</template>
