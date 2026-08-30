<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import ActionNotice from '@/components/ui/ActionNotice.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormError from '@/components/ui/FormError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type ReviewItem = {
  id: string;
  code: string;
  status: string;
  reasonCode: string | null;
  statusRevision: number;
  expiresAt: string | null;
  provenanceCount: number;
  redemptionCount: number;
  negativeRedemptionCount: number;
};
type Evidence = {
  id: string;
  sourceType: string;
  sourceLabel: string | null;
  sourceUrl: string | null;
  registeredSourceName: string | null;
  assertion: string;
  verificationState: string;
  classification: string;
  claimedExpiresAt: string | null;
  observedAt: string;
};
type SelectedCode = ReviewItem & {
  statusEvidenceIds: string[];
  expiresPrecision: string | null;
  redemptionDistribution: Record<string, number>;
  affectedGovernors: Array<{
    playerId: string;
    kingdomId: string;
    status: string;
    attempts: number;
  }>;
  evidence: Evidence[];
  decisions: Array<{
    id: string;
    action: string;
    reason: string | null;
    previousStatus: string | null;
    proposedStatus: string | null;
    evidenceIds: string[];
    decidedAt: string;
  }>;
};
type IngestionSource = {
  id: string;
  key: string;
  name: string;
  canonicalDomain: string | null;
  active: boolean;
  ingestionEnabled: boolean;
  lastAttemptAt: string | null;
  lastSuccessAt: string | null;
  failureCode: string | null;
  stale: boolean;
};
type Curator = {
  id: string;
  userId: number;
  name: string;
  email: string;
  grantedAt: string;
};

const props = defineProps<{
  user: { name: string; email: string };
  queue: string;
  queues: string[];
  items: ReviewItem[];
  nextCursor: string | null;
  previousCursor: string | null;
  selected: SelectedCode | null;
  bulkPreview: {
    action: string;
    requested: number;
    eligible: number;
    giftCodeIds: string[];
  } | null;
  bulkResult: { action: string; succeeded: number; failed: number } | null;
  ingestionHealth: IngestionSource[];
  canManagePlatformPolicy: boolean;
  adapterKeys: string[];
  curators: Curator[];
}>();

const { t, formatDate, formatNumber } = useLocale();
const selectedIds = ref<string[]>([]);
const decision = useForm({
  action: 'verify',
  reason: '',
  evidence_ids: [] as string[],
  proposed_status: 'valid',
  expires_at: '',
  expiry_precision: 'day',
});
const bulk = useForm({
  gift_code_ids: [] as string[],
  action: 'verify',
  confirmed: false,
  reason: '',
  proposed_status: 'valid',
  expires_at: '',
  expiry_precision: 'day',
});
const sourcePolicy = useForm({
  source_key: '',
  name: '',
  classification: 'official',
  canonical_domain: '',
  verification_method: 'manual_review',
  adapter_key: '',
  auto_verify: false,
  ingestion_enabled: false,
});
const sourceRevocation = useForm({ reason: '' });
const curatorGrant = useForm({ email: '' });
const curatorRevocation = useForm({});
const actionOptions = computed(() => [
  { value: 'verify', label: t('platformGiftCodes.actions.verify') },
  { value: 'reject', label: t('platformGiftCodes.actions.reject') },
  { value: 'quarantine', label: t('platformGiftCodes.actions.quarantine') },
  { value: 'restore', label: t('platformGiftCodes.actions.restore') },
  { value: 'correct_expiry', label: t('platformGiftCodes.actions.correctExpiry') },
  { value: 'resolve_dispute', label: t('platformGiftCodes.actions.resolveDispute') },
]);

function reviewUrl(options: { queue?: string; giftCode?: string; cursor?: string }): string {
  const params = new URLSearchParams();
  params.set('queue', options.queue ?? props.queue);
  if (options.giftCode) params.set('gift_code', options.giftCode);
  if (options.cursor) params.set('cursor', options.cursor);
  return `/platform/gift-codes?${params.toString()}`;
}

function submitDecision(): void {
  if (!props.selected) return;
  decision.post(`/platform/gift-codes/${props.selected.id}`, { preserveScroll: true });
}

function previewBulk(): void {
  bulk.gift_code_ids = [...selectedIds.value];
  bulk.confirmed = false;
  bulk.post('/platform/gift-codes/bulk', { preserveScroll: true });
}

function confirmBulk(): void {
  if (!props.bulkPreview) return;
  bulk.gift_code_ids = [...props.bulkPreview.giftCodeIds];
  bulk.confirmed = true;
  bulk.post('/platform/gift-codes/bulk', {
    preserveScroll: true,
    onSuccess: () => {
      selectedIds.value = [];
      bulk.reset();
    },
  });
}

function requiresReason(action: string): boolean {
  return ['reject', 'quarantine', 'correct_expiry', 'resolve_dispute'].includes(action);
}

function saveSource(): void {
  sourcePolicy.post('/platform/gift-codes/sources', {
    preserveScroll: true,
    onSuccess: () => sourcePolicy.reset(),
  });
}

function revokeSource(sourceId: string): void {
  sourceRevocation.post(`/platform/gift-codes/sources/${sourceId}/revoke`, {
    preserveScroll: true,
    onSuccess: () => sourceRevocation.reset(),
  });
}

function grantCurator(): void {
  curatorGrant.post('/platform/gift-codes/curators', {
    preserveScroll: true,
    onSuccess: () => curatorGrant.reset(),
  });
}

function revokeCurator(grantId: string): void {
  curatorRevocation.post(`/platform/gift-codes/curators/${grantId}/revoke`, {
    preserveScroll: true,
  });
}
</script>

<template>
  <Head :title="t('platformGiftCodes.title')" />

  <AppLayout :user="user">
    <header class="ks-surface p-5 sm:p-6">
      <p class="ks-kicker">{{ t('platformGiftCodes.eyebrow') }}</p>
      <h1 class="ks-display mt-1 text-3xl font-semibold">{{ t('platformGiftCodes.title') }}</h1>
      <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-muted)]">
        {{ t('platformGiftCodes.help') }}
      </p>
    </header>

    <ActionNotice
      class="mt-4"
      :message="
        bulkResult
          ? t('platformGiftCodes.bulkResult', {
              succeeded: bulkResult.succeeded,
              failed: bulkResult.failed,
            })
          : null
      "
      :tone="bulkResult?.failed ? 'warning' : 'success'"
    />

    <nav class="mt-4 flex flex-wrap gap-2" :aria-label="t('platformGiftCodes.reviewQueues')">
      <Link
        v-for="queueOption in queues"
        :key="queueOption"
        :href="reviewUrl({ queue: queueOption })"
        class="ks-command-link"
        :data-variant="queueOption === queue ? undefined : 'secondary'"
      >
        {{ t(`platformGiftCodes.queues.${queueOption}`) }}
      </Link>
    </nav>

    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,.8fr)_minmax(26rem,1.2fr)]">
      <section class="ks-surface overflow-hidden" :aria-labelledby="'gift-code-review-queue'">
        <div class="border-b border-[var(--ks-border)] p-5">
          <h2 id="gift-code-review-queue" class="ks-display text-xl font-semibold">
            {{ t('platformGiftCodes.queueTitle') }}
          </h2>
        </div>
        <ul v-if="items.length" class="divide-y divide-[var(--ks-border)]">
          <li v-for="item in items" :key="item.id" class="flex items-start gap-3 p-4">
            <input
              v-model="selectedIds"
              type="checkbox"
              :value="item.id"
              :aria-label="t('platformGiftCodes.selectCode', { code: item.code })"
              class="mt-1"
            />
            <Link :href="reviewUrl({ giftCode: item.id })" class="min-w-0 flex-1">
              <span class="block truncate font-mono font-semibold text-[var(--ks-gold-bright)]">{{
                item.code
              }}</span>
              <span class="mt-1 block text-xs text-[var(--ks-muted)]">
                {{ t(`giftCodes.trust.${item.status}`) }} ·
                {{ t('platformGiftCodes.revision', { revision: item.statusRevision }) }} ·
                {{ t('platformGiftCodes.evidenceCount', { count: item.provenanceCount }) }}
              </span>
            </Link>
          </li>
        </ul>
        <p v-else class="p-8 text-center text-sm text-[var(--ks-muted)]">
          {{ t('platformGiftCodes.emptyQueue') }}
        </p>
        <div class="flex justify-between border-t border-[var(--ks-border)] p-4">
          <Link
            v-if="previousCursor"
            :href="reviewUrl({ cursor: previousCursor })"
            class="ks-command-link"
            data-variant="secondary"
          >
            {{ t('common.previous') }}
          </Link>
          <span v-else />
          <Link
            v-if="nextCursor"
            :href="reviewUrl({ cursor: nextCursor })"
            class="ks-command-link"
            data-variant="secondary"
          >
            {{ t('common.next') }}
          </Link>
        </div>
      </section>

      <section
        v-if="selected"
        class="ks-surface p-5 sm:p-6"
        aria-labelledby="gift-code-review-detail"
      >
        <p class="ks-kicker">{{ t('platformGiftCodes.reviewDetail') }}</p>
        <h2 id="gift-code-review-detail" class="ks-display mt-1 text-2xl font-semibold">
          {{ selected.code }}
        </h2>
        <p class="mt-2 text-sm text-[var(--ks-muted)]">
          {{ t(`giftCodes.trust.${selected.status}`) }} ·
          {{ selected.reasonCode ?? t('common.none') }}
        </p>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
          <div class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-3">
            <span class="ks-kicker">{{ t('platformGiftCodes.evidence') }}</span>
            <strong class="mt-1 block">{{ formatNumber(selected.evidence.length) }}</strong>
          </div>
          <div class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-3">
            <span class="ks-kicker">{{ t('platformGiftCodes.startedGovernors') }}</span>
            <strong class="mt-1 block">{{
              formatNumber(selected.affectedGovernors.length)
            }}</strong>
          </div>
          <div class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-3">
            <span class="ks-kicker">{{ t('platformGiftCodes.decisions') }}</span>
            <strong class="mt-1 block">{{ formatNumber(selected.decisions.length) }}</strong>
          </div>
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="submitDecision">
          <label class="block">
            <span class="ks-kicker">{{ t('platformGiftCodes.action') }}</span>
            <select v-model="decision.action" class="ks-input mt-2 w-full">
              <option v-for="option in actionOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>
          <label v-if="requiresReason(decision.action)" class="block">
            <span class="ks-kicker">{{ t('platformGiftCodes.reason') }}</span>
            <textarea
              v-model="decision.reason"
              required
              maxlength="1000"
              class="ks-input mt-2 w-full"
            />
            <FormError :message="decision.errors.reason" />
          </label>
          <label v-if="decision.action === 'resolve_dispute'" class="block">
            <span class="ks-kicker">{{ t('platformGiftCodes.resolvedStatus') }}</span>
            <select v-model="decision.proposed_status" class="ks-input mt-2 w-full">
              <option value="valid">{{ t('giftCodes.trust.valid') }}</option>
              <option value="invalid">{{ t('giftCodes.trust.invalid') }}</option>
              <option value="expired">{{ t('giftCodes.trust.expired') }}</option>
            </select>
          </label>
          <label v-if="decision.action === 'correct_expiry'" class="block">
            <span class="ks-kicker">{{ t('giftCodes.expiresAt') }}</span>
            <input
              v-model="decision.expires_at"
              required
              type="datetime-local"
              class="ks-input mt-2 w-full"
            />
          </label>

          <fieldset v-if="selected.evidence.length" class="space-y-2">
            <legend class="ks-kicker">{{ t('platformGiftCodes.supportingEvidence') }}</legend>
            <label
              v-for="item in selected.evidence"
              :key="item.id"
              class="flex gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-3 text-sm"
            >
              <input v-model="decision.evidence_ids" type="checkbox" :value="item.id" />
              <span class="min-w-0">
                <strong class="block truncate">{{
                  item.registeredSourceName ?? item.sourceLabel ?? item.sourceType
                }}</strong>
                <span class="mt-1 block text-xs text-[var(--ks-muted)]">
                  {{ item.assertion }} · {{ item.verificationState }} ·
                  {{ formatDate(item.observedAt, { dateStyle: 'medium', timeStyle: 'short' }) }}
                </span>
              </span>
            </label>
          </fieldset>

          <AppButton type="submit" :busy="decision.processing" :busy-label="t('common.saving')">
            {{ t('platformGiftCodes.recordDecision') }}
          </AppButton>
        </form>
      </section>
      <section v-else class="ks-surface p-8 text-center text-sm text-[var(--ks-muted)]">
        {{ t('platformGiftCodes.selectReviewItem') }}
      </section>
    </div>

    <section
      v-if="selectedIds.length"
      class="ks-surface mt-5 p-5 sm:p-6"
      aria-labelledby="gift-code-bulk-review"
    >
      <h2 id="gift-code-bulk-review" class="ks-display text-xl font-semibold">
        {{ t('platformGiftCodes.bulkReview') }}
      </h2>
      <div class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_auto]">
        <select v-model="bulk.action" class="ks-input">
          <option v-for="option in actionOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <input
          v-model="bulk.reason"
          class="ks-input"
          maxlength="1000"
          :placeholder="t('platformGiftCodes.reason')"
        />
        <AppButton type="button" :busy="bulk.processing" @click="previewBulk">
          {{ t('platformGiftCodes.previewBulk') }}
        </AppButton>
      </div>
      <div v-if="bulkPreview" class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm">
          {{
            t('platformGiftCodes.bulkPreview', {
              requested: bulkPreview.requested,
              eligible: bulkPreview.eligible,
            })
          }}
        </p>
        <AppButton type="button" :busy="bulk.processing" @click="confirmBulk">
          {{ t('platformGiftCodes.confirmBulk') }}
        </AppButton>
      </div>
    </section>

    <section class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="gift-code-ingestion-health">
      <h2 id="gift-code-ingestion-health" class="ks-display text-xl font-semibold">
        {{ t('platformGiftCodes.ingestionHealth') }}
      </h2>
      <ul v-if="ingestionHealth.length" class="mt-4 grid gap-3 md:grid-cols-2">
        <li
          v-for="source in ingestionHealth"
          :key="source.id"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <strong>{{ source.name }}</strong>
            <span
              class="ks-status"
              :data-tone="source.failureCode || source.stale ? 'warning' : 'success'"
            >
              {{
                source.failureCode
                  ? t('platformGiftCodes.sourceFailed')
                  : source.stale
                    ? t('platformGiftCodes.sourceStale')
                    : t('platformGiftCodes.sourceHealthy')
              }}
            </span>
          </div>
          <p class="mt-2 text-xs text-[var(--ks-muted)]">
            {{ source.canonicalDomain ?? t('common.none') }} ·
            {{
              source.lastSuccessAt
                ? formatDate(source.lastSuccessAt)
                : t('platformGiftCodes.neverSucceeded')
            }}
          </p>
          <AppButton
            v-if="canManagePlatformPolicy && source.active"
            type="button"
            variant="secondary"
            class="mt-3"
            :busy="sourceRevocation.processing"
            @click="revokeSource(source.id)"
          >
            {{ t('platformGiftCodes.revokeSource') }}
          </AppButton>
        </li>
      </ul>
      <p v-else class="mt-3 text-sm text-[var(--ks-muted)]">
        {{ t('platformGiftCodes.noApprovedSources') }}
      </p>

      <form
        v-if="canManagePlatformPolicy"
        class="mt-5 grid gap-3 border-t border-[var(--ks-border)] pt-5 md:grid-cols-2"
        @submit.prevent="saveSource"
      >
        <h3 class="ks-display text-lg font-semibold md:col-span-2">
          {{ t('platformGiftCodes.sourcePolicy') }}
        </h3>
        <label>
          <span class="ks-kicker">{{ t('platformGiftCodes.sourceKey') }}</span>
          <input
            v-model="sourcePolicy.source_key"
            required
            maxlength="120"
            class="ks-input mt-2 w-full"
          />
          <FormError :message="sourcePolicy.errors.source_key" />
        </label>
        <label>
          <span class="ks-kicker">{{ t('platformGiftCodes.sourceName') }}</span>
          <input
            v-model="sourcePolicy.name"
            required
            maxlength="160"
            class="ks-input mt-2 w-full"
          />
          <FormError :message="sourcePolicy.errors.name" />
        </label>
        <label>
          <span class="ks-kicker">{{ t('platformGiftCodes.sourceClassification') }}</span>
          <select v-model="sourcePolicy.classification" class="ks-input mt-2 w-full">
            <option value="official">{{ t('platformGiftCodes.officialSource') }}</option>
            <option value="independent">{{ t('platformGiftCodes.independentSource') }}</option>
          </select>
        </label>
        <label>
          <span class="ks-kicker">{{ t('platformGiftCodes.canonicalDomain') }}</span>
          <input
            v-model="sourcePolicy.canonical_domain"
            required
            maxlength="255"
            class="ks-input mt-2 w-full"
          />
          <FormError :message="sourcePolicy.errors.canonical_domain" />
        </label>
        <label>
          <span class="ks-kicker">{{ t('platformGiftCodes.verificationMethod') }}</span>
          <input
            v-model="sourcePolicy.verification_method"
            required
            maxlength="80"
            class="ks-input mt-2 w-full"
          />
        </label>
        <label>
          <span class="ks-kicker">{{ t('platformGiftCodes.adapter') }}</span>
          <select v-model="sourcePolicy.adapter_key" class="ks-input mt-2 w-full">
            <option value="">{{ t('common.none') }}</option>
            <option v-for="adapterKey in adapterKeys" :key="adapterKey" :value="adapterKey">
              {{ adapterKey }}
            </option>
          </select>
          <FormError :message="sourcePolicy.errors.adapter_key" />
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="sourcePolicy.auto_verify" type="checkbox" />
          {{ t('platformGiftCodes.autoVerify') }}
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input
            v-model="sourcePolicy.ingestion_enabled"
            type="checkbox"
            :disabled="!sourcePolicy.adapter_key"
          />
          {{ t('platformGiftCodes.enableIngestion') }}
        </label>
        <label class="md:col-span-2">
          <span class="ks-kicker">{{ t('platformGiftCodes.revocationReason') }}</span>
          <input
            v-model="sourceRevocation.reason"
            maxlength="1000"
            class="ks-input mt-2 w-full"
            :placeholder="t('platformGiftCodes.revocationReasonHelp')"
          />
          <FormError :message="sourceRevocation.errors.reason" />
        </label>
        <div class="md:col-span-2">
          <AppButton type="submit" :busy="sourcePolicy.processing">
            {{ t('platformGiftCodes.saveSourcePolicy') }}
          </AppButton>
        </div>
      </form>
    </section>

    <section
      v-if="canManagePlatformPolicy"
      class="ks-surface mt-5 p-5 sm:p-6"
      aria-labelledby="gift-code-curators"
    >
      <h2 id="gift-code-curators" class="ks-display text-xl font-semibold">
        {{ t('platformGiftCodes.curators') }}
      </h2>
      <p class="mt-2 text-sm text-[var(--ks-muted)]">
        {{ t('platformGiftCodes.curatorHelp') }}
      </p>
      <form class="mt-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="grantCurator">
        <label class="min-w-0 flex-1">
          <span class="sr-only">{{ t('platformGiftCodes.curatorEmail') }}</span>
          <input
            v-model="curatorGrant.email"
            required
            type="email"
            maxlength="254"
            class="ks-input w-full"
            :placeholder="t('platformGiftCodes.curatorEmail')"
          />
          <FormError :message="curatorGrant.errors.email" />
        </label>
        <AppButton type="submit" :busy="curatorGrant.processing">
          {{ t('platformGiftCodes.grantCurator') }}
        </AppButton>
      </form>
      <ul v-if="curators.length" class="mt-4 divide-y divide-[var(--ks-border)]">
        <li
          v-for="curator in curators"
          :key="curator.id"
          class="flex items-center justify-between gap-3 py-3"
        >
          <span class="min-w-0 text-sm">
            <strong class="block truncate">{{ curator.name }}</strong>
            <span class="block truncate text-[var(--ks-muted)]">{{ curator.email }}</span>
          </span>
          <AppButton
            type="button"
            variant="secondary"
            :busy="curatorRevocation.processing"
            @click="revokeCurator(curator.id)"
          >
            {{ t('platformGiftCodes.revokeCurator') }}
          </AppButton>
        </li>
      </ul>
      <p v-else class="mt-4 text-sm text-[var(--ks-muted)]">
        {{ t('platformGiftCodes.noCurators') }}
      </p>
    </section>
  </AppLayout>
</template>
