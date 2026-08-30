<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import ActionNotice from '@/components/ui/ActionNotice.vue';
import AppButton from '@/components/ui/AppButton.vue';
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
  registeredSourceName: string | null;
  sourceType: string;
  sourceLabel: string | null;
  sourceUrl: string | null;
  assertion: string;
  verificationState: string;
  evidenceClassification: string;
  claimedExpiresAt: string | null;
  observedAt: string;
};
type GiftCode = {
  id: string;
  code: string;
  status: string;
  statusReasonCode: string | null;
  statusRevision: number;
  sourceCount: number;
  discoveredAt: string;
  expiresAt: string | null;
  expiresPrecision: string | null;
  expiresRevision: number;
  reward: Record<string, unknown> | null;
  rewardState: string;
  applicability: Record<string, unknown> | null;
  applicabilityState: string;
  redemption: Redemption | null;
  redemptions: GovernorRedemption[];
  provenances?: Provenance[];
  moderationDecisions?: Array<{
    id: string;
    action: string;
    reason: string | null;
    evidenceIds: string[];
    decidedAt: string;
  }>;
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

const props = defineProps<{
  user: { name: string; email: string };
  player: Governor;
  governors: Governor[];
  officialRedemptionUrl: string;
  codes: GiftCode[];
  pagination: {
    nextCursor: string | null;
    previousCursor: string | null;
    perPage: number;
    hasMore: boolean;
  };
  filters: {
    view: string;
    q: string;
    status: string;
    source: string;
    expiry: string;
    governorResult: string;
  };
  focusedCode: GiftCode | null;
  giftCodeRedemptionResult: RedemptionResult | null;
}>();

const { t, formatDate, formatNumber } = useLocale();
const copied = ref<string | null>(null);
const operationError = ref<string | null>(null);
const selectedGovernorIds = ref<string[]>([]);
const workflowGovernorIds = ref<string[]>([]);
const workflowIndex = ref(0);
const preparing = ref(false);
const recording = ref(false);
const selectedOutcome = ref('redeemed');
const submission = useForm({
  code: '',
  source_type: 'manual',
  source_label: '',
  source_url: '',
  expires_at: '',
  expiry_precision: 'day',
  expiry_timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
});
const redemption = useForm({ player_ids: [] as string[] });
const search = useForm({
  view: props.filters.view,
  q: props.filters.q,
  status: props.filters.status,
  source: props.filters.source,
  expiry: props.filters.expiry,
  governor_result: props.filters.governorResult,
});
const views = ['active', 'pending_review', 'disputed', 'expired', 'completed', 'history'];
const outcomes = [
  'redeemed',
  'already_redeemed',
  'invalid',
  'expired',
  'wrong_kingdom',
  'rate_limited',
  'temporarily_unavailable',
  'permanent_failure',
];

const activeCodes = computed(() => props.codes.filter((item) => item.status === 'valid').length);
const completedCodes = computed(
  () => props.codes.filter((item) => item.redemption && successful(item.redemption.status)).length,
);
const incompleteCodes = computed(
  () => props.codes.filter((item) => item.redemption && !successful(item.redemption.status)).length,
);
const workflowGovernor = computed(() => {
  const id = workflowGovernorIds.value[workflowIndex.value];
  return props.governors.find((governor) => governor.id === id) ?? null;
});
const workflowComplete = computed(
  () =>
    workflowGovernorIds.value.length > 0 && workflowIndex.value >= workflowGovernorIds.value.length,
);

function filterUrl(view: string, cursor?: string | null): string {
  const params = new URLSearchParams();
  params.set('view', view);
  if (search.q) params.set('q', search.q);
  if (search.status) params.set('status', search.status);
  if (search.source) params.set('source', search.source);
  if (search.expiry) params.set('expiry', search.expiry);
  if (search.governor_result) params.set('governor_result', search.governor_result);
  if (cursor) params.set('cursor', cursor);
  return `/gift-codes?${params.toString()}`;
}

function applyFilters(): void {
  router.get('/gift-codes', search.data(), { preserveState: true, replace: true });
}

function submit(): void {
  operationError.value = null;
  submission.post('/gift-codes', {
    preserveScroll: true,
    onError: captureOperationError,
    onSuccess: () => submission.reset('code', 'source_label', 'source_url', 'expires_at'),
  });
}

function startWorkflow(giftCode: GiftCode, governorIds: string[]): void {
  if (preparing.value || governorIds.length === 0) return;
  operationError.value = null;
  preparing.value = true;
  workflowGovernorIds.value = [...governorIds];
  workflowIndex.value = 0;
  redemption.player_ids = governorIds;
  redemption.post(`/gift-codes/${giftCode.id}/redeem`, {
    preserveScroll: true,
    preserveState: true,
    onError: captureOperationError,
    onFinish: () => (preparing.value = false),
  });
}

function recordOutcome(giftCode: GiftCode): void {
  const governor = workflowGovernor.value;
  if (!governor || recording.value) return;
  recording.value = true;
  router.post(
    `/gift-codes/${giftCode.id}/result`,
    { player_id: governor.id, result: selectedOutcome.value },
    {
      preserveScroll: true,
      preserveState: true,
      onError: captureOperationError,
      onSuccess: () => (workflowIndex.value += 1),
      onFinish: () => (recording.value = false),
    },
  );
}

function failedGovernorIds(giftCode: GiftCode): string[] {
  const now = Date.now();
  return giftCode.redemptions
    .filter(
      (item) =>
        ['rate_limited', 'transient_failure', 'permanent_failure'].includes(item.status) &&
        (!item.nextAttemptAt || new Date(item.nextAttemptAt).getTime() <= now),
    )
    .map((item) => item.playerId);
}

function unfinishedGovernorIds(giftCode: GiftCode): string[] {
  const states = new Map(giftCode.redemptions.map((item) => [item.playerId, item.status]));
  return props.governors
    .filter((governor) => governor.gamePlayerId && !successful(states.get(governor.id) ?? ''))
    .map((governor) => governor.id);
}

function successful(status: string): boolean {
  return ['redeemed', 'already_redeemed'].includes(status);
}

function copyValue(key: string, value: string): void {
  navigator.clipboard
    .writeText(value)
    .then(() => {
      copied.value = key;
      window.setTimeout(() => {
        if (copied.value === key) copied.value = null;
      }, 1800);
    })
    .catch(() => (operationError.value = t('giftCodes.copyFailed')));
}

function captureOperationError(errors: Record<string, string>): void {
  operationError.value = Object.values(errors)[0] ?? t('giftCodes.operationFailed');
}

function statusTone(status: string): 'success' | 'warning' | 'danger' | 'info' {
  if (['valid', 'redeemed', 'already_redeemed'].includes(status)) return 'success';
  if (['pending', 'disputed', 'awaiting_confirmation', 'rate_limited'].includes(status))
    return 'warning';
  if (['invalid', 'expired', 'quarantined', 'invalid_code', 'permanent_failure'].includes(status))
    return 'danger';
  return 'info';
}

function redemptionLabel(status: string): string {
  return t(`giftCodes.redemption.${status}`);
}

function factSummary(value: Record<string, unknown> | null, unknownKey: string): string {
  return value ? JSON.stringify(value) : t(unknownKey);
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
        :value="formatNumber(completedCodes)"
        icon="✓"
        tone="teal"
      />
      <StatSeal
        :label="t('giftCodes.incomplete')"
        :value="formatNumber(incompleteCodes)"
        icon="◇"
        tone="stone"
      />
      <StatSeal
        :label="t('giftCodes.governors')"
        :value="formatNumber(governors.length)"
        icon="♛"
      />
    </section>

    <ActionNotice class="mt-5" :message="operationError" tone="danger" />

    <nav class="mt-5 flex flex-wrap gap-2" :aria-label="t('giftCodes.catalogViews')">
      <Link
        v-for="view in views"
        :key="view"
        :href="filterUrl(view)"
        class="ks-command-link"
        :data-variant="view === filters.view ? undefined : 'secondary'"
      >
        {{ t(`giftCodes.views.${view}`) }}
      </Link>
    </nav>

    <form class="ks-surface mt-4 grid gap-3 p-4 md:grid-cols-5" @submit.prevent="applyFilters">
      <input v-model="search.q" class="ks-input" :placeholder="t('giftCodes.searchCode')" />
      <input v-model="search.source" class="ks-input" :placeholder="t('giftCodes.searchSource')" />
      <select v-model="search.expiry" class="ks-input">
        <option value="">{{ t('giftCodes.anyExpiry') }}</option>
        <option value="24h">{{ t('giftCodes.expiry24h') }}</option>
        <option value="7d">{{ t('giftCodes.expiry7d') }}</option>
        <option value="none">{{ t('giftCodes.noExpiry') }}</option>
        <option value="expired">{{ t('giftCodes.expired') }}</option>
      </select>
      <select v-model="search.governor_result" class="ks-input">
        <option value="">{{ t('giftCodes.anyGovernorResult') }}</option>
        <option
          v-for="outcome in outcomes"
          :key="outcome"
          :value="outcome === 'invalid' ? 'invalid_code' : outcome"
        >
          {{ t(`giftCodes.outcomes.${outcome}`) }}
        </option>
      </select>
      <AppButton type="submit">{{ t('giftCodes.applyFilters') }}</AppButton>
    </form>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.35fr)_minmax(22rem,.65fr)]">
      <section class="ks-surface overflow-hidden" aria-labelledby="gift-code-ledger-heading">
        <div class="border-b border-[var(--ks-border)] p-5 sm:p-6">
          <p class="ks-kicker">{{ t('giftCodes.sharedLedger') }}</p>
          <h2 id="gift-code-ledger-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('giftCodes.availableCodes') }}
          </h2>
        </div>

        <div v-if="codes.length" class="divide-y divide-[var(--ks-border)]">
          <article v-for="giftCode in codes" :key="giftCode.id" class="p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <Link
                    :href="`/gift-codes/${giftCode.id}`"
                    class="font-mono text-lg font-bold tracking-[.08em] text-[var(--ks-gold-bright)]"
                  >
                    {{ giftCode.code }}
                  </Link>
                  <span class="ks-status" :data-tone="statusTone(giftCode.status)">
                    {{ t(`giftCodes.trust.${giftCode.status}`) }}
                  </span>
                  <span
                    v-if="giftCode.redemption"
                    class="ks-status"
                    :data-tone="statusTone(giftCode.redemption.status)"
                  >
                    {{ redemptionLabel(giftCode.redemption.status) }}
                  </span>
                </div>
                <p class="mt-2 text-xs text-[var(--ks-muted)]">
                  {{ t('giftCodes.sourceCount', { count: giftCode.sourceCount }) }} ·
                  {{ t('giftCodes.statusRevision', { revision: giftCode.statusRevision }) }} ·
                  {{ formatDate(giftCode.discoveredAt, { dateStyle: 'medium' }) }}
                  <template v-if="giftCode.expiresAt">
                    · {{ t('giftCodes.expires') }} {{ formatDate(giftCode.expiresAt) }}
                  </template>
                </p>
              </div>
              <AppButton
                type="button"
                variant="secondary"
                @click="copyValue(giftCode.id, giftCode.code)"
              >
                {{ copied === giftCode.id ? t('giftCodes.copied') : t('giftCodes.copyCode') }}
              </AppButton>
            </div>

            <div v-if="giftCode.status === 'valid'" class="mt-4 flex flex-wrap gap-2">
              <AppButton
                type="button"
                :busy="preparing"
                :disabled="!player.gamePlayerId || successful(giftCode.redemption?.status ?? '')"
                @click="startWorkflow(giftCode, [player.id])"
              >
                {{ t('giftCodes.redeemForGovernor', { governor: player.name }) }}
              </AppButton>
              <AppButton
                type="button"
                variant="secondary"
                :busy="preparing"
                :disabled="unfinishedGovernorIds(giftCode).length === 0"
                @click="startWorkflow(giftCode, unfinishedGovernorIds(giftCode))"
              >
                {{ t('giftCodes.prepareAllGovernors') }}
              </AppButton>
              <AppButton
                v-if="failedGovernorIds(giftCode).length"
                type="button"
                variant="secondary"
                :busy="preparing"
                @click="startWorkflow(giftCode, failedGovernorIds(giftCode))"
              >
                {{
                  t('giftCodes.retryFailedGovernors', { count: failedGovernorIds(giftCode).length })
                }}
              </AppButton>
            </div>

            <details v-if="giftCode.redemptions.length" class="mt-4">
              <summary class="cursor-pointer text-sm font-semibold text-[var(--ks-text-secondary)]">
                {{ t('giftCodes.governorReceipts') }}
              </summary>
              <ul class="mt-3 grid gap-2 md:grid-cols-2">
                <li
                  v-for="item in giftCode.redemptions"
                  :key="item.playerId"
                  class="flex items-center justify-between gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-3 text-sm"
                >
                  <span>{{ item.playerName }}</span>
                  <span class="ks-status" :data-tone="statusTone(item.status)">{{
                    redemptionLabel(item.status)
                  }}</span>
                </li>
              </ul>
            </details>
          </article>
        </div>
        <p v-else class="p-8 text-center text-sm text-[var(--ks-muted)]">
          {{ t('giftCodes.empty') }}
        </p>

        <div class="flex justify-between border-t border-[var(--ks-border)] p-4">
          <Link
            v-if="pagination.previousCursor"
            :href="filterUrl(filters.view, pagination.previousCursor)"
            class="ks-command-link"
            data-variant="secondary"
          >
            {{ t('common.previous') }}
          </Link>
          <span v-else />
          <Link
            v-if="pagination.nextCursor"
            :href="filterUrl(filters.view, pagination.nextCursor)"
            class="ks-command-link"
            data-variant="secondary"
          >
            {{ t('common.next') }}
          </Link>
        </div>
      </section>

      <aside class="space-y-5">
        <section
          v-if="focusedCode"
          class="ks-surface-gold p-5 sm:p-6"
          aria-labelledby="gift-code-detail"
        >
          <p class="ks-kicker">{{ t('giftCodes.codeDetail') }}</p>
          <h2 id="gift-code-detail" class="ks-display mt-1 text-2xl font-semibold">
            {{ focusedCode.code }}
          </h2>
          <p class="mt-2 text-sm text-[var(--ks-muted)]">
            {{ focusedCode.statusReasonCode ?? t('giftCodes.noTrustReason') }}
          </p>

          <dl class="mt-4 space-y-3 text-sm">
            <div>
              <dt class="ks-kicker">{{ t('giftCodes.rewardDetails') }}</dt>
              <dd class="mt-1 break-words">
                {{ factSummary(focusedCode.reward, 'giftCodes.rewardUnknown') }}
              </dd>
            </div>
            <div>
              <dt class="ks-kicker">{{ t('giftCodes.applicability') }}</dt>
              <dd class="mt-1 break-words">
                {{ factSummary(focusedCode.applicability, 'giftCodes.applicabilityUnknown') }}
              </dd>
            </div>
          </dl>

          <div v-if="focusedCode.status === 'valid'" class="mt-5">
            <p class="ks-kicker">{{ t('giftCodes.chooseGovernors') }}</p>
            <div class="mt-3 space-y-2">
              <label
                v-for="governor in governors"
                :key="governor.id"
                class="flex items-center gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-3 text-sm"
              >
                <input
                  v-model="selectedGovernorIds"
                  type="checkbox"
                  :value="governor.id"
                  :disabled="!governor.gamePlayerId"
                />
                <span>{{ governor.name }}</span>
                <span class="ml-auto text-xs text-[var(--ks-muted)]">
                  {{
                    governor.kingdomNumber
                      ? t('giftCodes.kingdom', { kingdom: governor.kingdomNumber })
                      : t('common.none')
                  }}
                </span>
              </label>
            </div>
            <AppButton
              type="button"
              class="mt-3 w-full"
              :busy="preparing"
              :disabled="selectedGovernorIds.length === 0"
              @click="startWorkflow(focusedCode, selectedGovernorIds)"
            >
              {{ t('giftCodes.prepareSelectedGovernors', { count: selectedGovernorIds.length }) }}
            </AppButton>
          </div>

          <div
            v-if="workflowGovernor && !workflowComplete"
            class="mt-5 border-t border-[var(--ks-border)] pt-5"
          >
            <p class="ks-kicker">
              {{
                t('giftCodes.workflowStep', {
                  current: workflowIndex + 1,
                  total: workflowGovernorIds.length,
                })
              }}
            </p>
            <h3 class="mt-1 text-lg font-semibold">{{ workflowGovernor.name }}</h3>
            <p class="mt-1 text-xs text-[var(--ks-muted)]">
              {{
                workflowGovernor.kingdomNumber
                  ? t('giftCodes.kingdom', { kingdom: workflowGovernor.kingdomNumber })
                  : t('common.none')
              }}
              · {{ workflowGovernor.gamePlayerId ?? t('giftCodes.missingPlayerIdMessage') }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
              <AppButton
                type="button"
                variant="secondary"
                @click="
                  copyValue(`player:${workflowGovernor.id}`, workflowGovernor.gamePlayerId ?? '')
                "
              >
                {{ t('giftCodes.copyPlayerId') }}
              </AppButton>
              <AppButton
                type="button"
                variant="secondary"
                @click="copyValue(`code:${focusedCode.id}`, focusedCode.code)"
              >
                {{ t('giftCodes.copyCode') }}
              </AppButton>
              <a
                :href="officialRedemptionUrl"
                target="_blank"
                rel="noreferrer"
                class="ks-command-link"
              >
                {{ t('giftCodes.openOfficialCenter') }}
              </a>
            </div>
            <label class="mt-4 block">
              <span class="ks-kicker">{{ t('giftCodes.observedOutcome') }}</span>
              <select v-model="selectedOutcome" class="ks-input mt-2 w-full">
                <option v-for="outcome in outcomes" :key="outcome" :value="outcome">
                  {{ t(`giftCodes.outcomes.${outcome}`) }}
                </option>
              </select>
            </label>
            <AppButton
              type="button"
              class="mt-3 w-full"
              :busy="recording"
              @click="recordOutcome(focusedCode)"
            >
              {{ t('giftCodes.recordAndContinue') }}
            </AppButton>
          </div>
          <ActionNotice
            v-if="workflowComplete"
            class="mt-4"
            :message="t('giftCodes.workflowComplete')"
            tone="success"
          />

          <details
            v-if="focusedCode.provenances?.length"
            class="mt-5 border-t border-[var(--ks-border)] pt-4"
          >
            <summary class="cursor-pointer text-sm font-semibold">
              {{ t('giftCodes.sourceHistory', { count: focusedCode.provenances.length }) }}
            </summary>
            <ul class="mt-3 space-y-2">
              <li
                v-for="source in focusedCode.provenances"
                :key="source.id"
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-3 text-xs"
              >
                <a
                  v-if="source.sourceUrl"
                  :href="source.sourceUrl"
                  target="_blank"
                  rel="noreferrer"
                  class="font-semibold text-[var(--ks-blue-strong)]"
                >
                  {{ source.registeredSourceName ?? source.sourceLabel ?? source.sourceType }}
                </a>
                <strong v-else>{{
                  source.registeredSourceName ?? source.sourceLabel ?? source.sourceType
                }}</strong>
                <p class="mt-1 text-[var(--ks-muted)]">
                  {{ source.assertion }} · {{ source.verificationState }} ·
                  {{ formatDate(source.observedAt) }}
                </p>
              </li>
            </ul>
          </details>
        </section>

        <section class="ks-surface p-5 sm:p-6" aria-labelledby="submit-gift-code-heading">
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
              />
              <FormError :message="submission.errors.code" />
            </label>
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.source') }}</span>
              <select v-model="submission.source_type" class="ks-input mt-2 w-full">
                <option value="manual">{{ t('giftCodes.source_manual') }}</option>
                <option value="community">{{ t('giftCodes.source_community') }}</option>
              </select>
              <FormError :message="submission.errors.source_type" />
            </label>
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.sourceLabel') }}</span>
              <input
                v-model="submission.source_label"
                maxlength="160"
                class="ks-input mt-2 w-full"
              />
            </label>
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.sourceUrl') }}</span>
              <input
                v-model="submission.source_url"
                type="url"
                maxlength="2048"
                class="ks-input mt-2 w-full"
              />
            </label>
            <label class="block">
              <span class="ks-kicker">{{ t('giftCodes.claimedExpiry') }}</span>
              <input v-model="submission.expires_at" type="date" class="ks-input mt-2 w-full" />
              <FormError :message="submission.errors.expires_at" />
            </label>
            <p class="text-xs leading-5 text-[var(--ks-muted)]">
              {{ t('giftCodes.communityEvidenceWarning') }}
            </p>
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
      </aside>
    </div>
  </AppLayout>
</template>
