<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppButton from '@/components/ui/AppButton.vue';
import FormError from '@/components/ui/FormError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type ReadinessCheck = { ready: boolean; message: string };
type ActivationReadiness = { ready: boolean; checks: Record<string, ReadinessCheck> };
type SyncState = {
  mode: string;
  latestObservedProviderId: string | null;
  committedHighWater: string | null;
  candidateHighWater: string | null;
  activePageToken: string | null;
  backfillPageToken: string | null;
  lastHeadPollAt: string | null;
  lastReconciliationAt: string | null;
  lastBackfillAt: string | null;
};
type Subscription = {
  provider: string;
  transport: string;
  status: string;
  providerSubscriptionId: string | null;
  activatedAt: string | null;
  expiresAt: string | null;
  lastVerifiedAt: string | null;
  lastEventReceivedAt: string | null;
  lastErrorCode: string | null;
};
type Source = {
  id: string;
  key: string;
  name: string;
  classification: string;
  canonicalDomain: string | null;
  adapterKey: string | null;
  manualEvidenceAllowed: boolean;
  active: boolean;
  ingestionEnabled: boolean;
  pushEnabled: boolean;
  headPollEnabled: boolean;
  reconciliationEnabled: boolean;
  backfillEnabled: boolean;
  authorityPromotionEnabled: boolean;
  activationStatus: string;
  healthStatus: string;
  activationReadiness: ActivationReadiness;
  syncStates: SyncState[];
  subscriptions: Subscription[];
  nextEligibleIngestionAt: string | null;
  consecutiveFailures: number;
  consecutiveQuarantinedRuns: number;
  requestCount: number;
  observationCount: number;
  acceptedObservationCount: number;
  quarantinedObservationCount: number;
  duplicateObservationCount: number;
  acceptanceRatio: number;
  quarantineRatio: number;
  duplicateRatio: number;
  rateLimitEventCount: number;
  reconciliationGapCount: number;
  signatureFailureCount: number;
  replayRejectionCount: number;
  lastObservationAt: string | null;
  lastAcceptedObservationAt: string | null;
  lastQuarantinedObservationAt: string | null;
  lastPushReceivedAt: string | null;
  lastProviderEventAt: string | null;
  lastReconciliationGapAt: string | null;
  lastAttemptAt: string | null;
  lastSuccessAt: string | null;
  lastFailureAt: string | null;
  failureCode: string | null;
  lastQuotaRemaining: number | null;
  lastRateLimitRemaining: number | null;
};

type ResearchedSource = {
  source_key: string;
  name: string;
  stage: number;
  catalogue_state: string;
  evidence_role: string;
  canonical_domain_candidate: string | null;
  transports: string[];
  candidate_adapter_keys: string[];
  gate: string;
  notes: string;
};

type PushCapabilities = {
  youtubeWebSub: boolean;
  facebookWebhook: boolean;
  discordGateway: boolean;
  xFilteredStreamWebhook: boolean;
};

const props = defineProps<{
  user: { name: string; email: string };
  sources: Source[];
  adapterKeys: string[];
  researchedSources: ResearchedSource[];
  canManagePlatformPolicy: boolean;
  pushCapabilities: PushCapabilities;
}>();

const { t, formatDate } = useLocale();
const discordAuthorIds = ref('');
const busySourceAction = ref<string | null>(null);
const sourcePolicy = useForm({
  source_key: '',
  name: '',
  classification: 'official',
  canonical_domain: '',
  verification_method: 'manual_review',
  adapter_key: '',
  ingestion_enabled: false,
  push_enabled: false,
  head_poll_enabled: true,
  reconciliation_enabled: true,
  backfill_enabled: true,
  authority_promotion_enabled: true,
  provenance_policy: {
    auto_verify: false,
    manual_evidence_allowed: false,
    feed_path: '',
    provider_contract_confirmed: false,
    structured_contract_confirmed: false,
    provider_permission_confirmed: false,
    x_user_id: '',
    x_username: '',
    platform_permission_confirmed: false,
    platform_api_access_confirmed: false,
    message_content_access_confirmed: false,
    discord_guild_id: '',
    discord_channel_id: '',
    discord_author_ids: [] as string[],
    youtube_channel_id: '',
    youtube_channel_title: '',
    reddit_subreddit: '',
    facebook_page_id: '',
    facebook_page_name: '',
    instagram_user_id: '',
    instagram_username: '',
  },
});

const evidence = useForm({
  source_id: '',
  code: '',
  assertion: 'available',
  source_url: '',
  expires_at: '',
  expiry_precision: 'day',
  expiry_timezone: '',
  published_at: '',
});

const manualSources = computed(() =>
  props.sources.filter((source) => source.active && source.manualEvidenceAllowed),
);
const selectedAdapter = computed(() => sourcePolicy.adapter_key);
const usesFeedPath = computed(() =>
  [
    'json-feed-v1',
    'rss-atom-v1',
    'structured-html-v1',
    'century-games-kingshot-news-rss-v2',
  ].includes(selectedAdapter.value),
);
const usesProviderContract = computed(() =>
  ['json-feed-v1', 'rss-atom-v1'].includes(selectedAdapter.value),
);
const usesStructuredContract = computed(() => selectedAdapter.value === 'structured-html-v1');
const selectedPushAvailable = computed(() => pushAvailableFor(selectedAdapter.value));

function pushAvailableFor(adapter: string | null): boolean {
  switch (adapter) {
    case 'youtube-channel-v1':
      return props.pushCapabilities.youtubeWebSub;
    case 'facebook-page-v1':
      return props.pushCapabilities.facebookWebhook;
    case 'discord-channel-v1':
      return props.pushCapabilities.discordGateway;
    case 'x-api-v2-kingshot-v2':
      return props.pushCapabilities.xFilteredStreamWebhook;
    default:
      return false;
  }
}

function applyCandidate(candidate: ResearchedSource): void {
  sourcePolicy.source_key = candidate.source_key;
  sourcePolicy.name = candidate.name;
  sourcePolicy.classification = candidate.evidence_role.includes('independent')
    ? 'independent'
    : 'official';
  sourcePolicy.canonical_domain = candidate.canonical_domain_candidate ?? '';
  sourcePolicy.adapter_key = candidate.candidate_adapter_keys[0] ?? '';
  sourcePolicy.provenance_policy.manual_evidence_allowed = candidate.transports.includes(
    'registered_manual_evidence',
  );
  sourcePolicy.provenance_policy.auto_verify = false;
  sourcePolicy.ingestion_enabled = false;
  sourcePolicy.push_enabled = false;
  sourcePolicy.head_poll_enabled = true;
  sourcePolicy.reconciliation_enabled = true;
  sourcePolicy.backfill_enabled = true;
  sourcePolicy.authority_promotion_enabled = sourcePolicy.classification === 'official';
}

function saveSource(): void {
  sourcePolicy.provenance_policy.discord_author_ids = discordAuthorIds.value
    .split(/[\s,]+/)
    .map((value) => value.trim())
    .filter(Boolean);
  if (sourcePolicy.classification === 'independent') {
    sourcePolicy.authority_promotion_enabled = false;
    sourcePolicy.provenance_policy.auto_verify = false;
  }
  if (!sourcePolicy.ingestion_enabled) {
    sourcePolicy.push_enabled = false;
  }
  sourcePolicy.post('/platform/gift-codes/sources/policy', {
    preserveScroll: true,
    onSuccess: () => {
      sourcePolicy.reset();
      discordAuthorIds.value = '';
    },
  });
}

function recordEvidence(): void {
  evidence.post('/platform/gift-codes/sources/evidence', {
    preserveScroll: true,
    onSuccess: () => evidence.reset(),
  });
}

function hasActivePush(source: Source): boolean {
  return source.subscriptions.some((subscription) =>
    ['pending', 'active'].includes(subscription.status),
  );
}

function runSourceAction(
  source: Source,
  action: string,
  data: Record<string, string | number | boolean | null> = {},
): void {
  busySourceAction.value = `${source.id}:${action}`;
  router.post(`/platform/gift-codes/sources/${source.id}/${action}`, data, {
    preserveScroll: true,
    onFinish: () => {
      busySourceAction.value = null;
    },
  });
}

function asPercent(value: number): string {
  return `${Math.round(value * 100)}%`;
}
</script>

<template>
  <Head :title="t('platformGiftCodes.sources.pageTitle')" />

  <AppLayout :user="user">
    <header class="ks-surface p-5 sm:p-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="ks-kicker">{{ t('platformGiftCodes.eyebrow') }}</p>
          <h1 class="ks-display mt-1 text-3xl font-semibold">
            {{ t('platformGiftCodes.sources.heading') }}
          </h1>
          <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('platformGiftCodes.sources.help') }}
          </p>
        </div>
        <Link href="/platform/gift-codes" class="ks-command-link" data-variant="secondary">
          {{ t('platformGiftCodes.sources.backReview') }}
        </Link>
      </div>
    </header>

    <section class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="researched-sources">
      <h2 id="researched-sources" class="ks-display text-xl font-semibold">
        {{ t('platformGiftCodes.sources.catalogueTitle') }}
      </h2>
      <p class="mt-2 text-sm text-[var(--ks-muted)]">
        {{ t('platformGiftCodes.sources.catalogueHelp') }}
      </p>
      <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[58rem] text-left text-sm">
          <thead class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
            <tr>
              <th class="p-2">{{ t('platformGiftCodes.sources.stage') }}</th>
              <th class="p-2">{{ t('platformGiftCodes.sources.source') }}</th>
              <th class="p-2">{{ t('platformGiftCodes.sources.transport') }}</th>
              <th class="p-2">{{ t('platformGiftCodes.sources.gate') }}</th>
              <th class="p-2">{{ t('platformGiftCodes.sources.action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="candidate in researchedSources" :key="candidate.source_key">
              <td class="p-2 font-mono">{{ candidate.stage }}</td>
              <td class="p-2">
                <strong class="block">{{ candidate.name }}</strong>
                <span class="text-xs text-[var(--ks-muted)]">{{ candidate.notes }}</span>
              </td>
              <td class="p-2 text-xs">
                <code>{{
                  candidate.candidate_adapter_keys.join(', ') || candidate.transports.join(', ')
                }}</code>
              </td>
              <td class="p-2 text-xs">
                <code>{{ candidate.gate }}</code>
              </td>
              <td class="p-2">
                <AppButton
                  v-if="canManagePlatformPolicy"
                  type="button"
                  variant="secondary"
                  @click="applyCandidate(candidate)"
                >
                  {{ t('platformGiftCodes.sources.preparePolicy') }}
                </AppButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section
      v-if="canManagePlatformPolicy"
      class="ks-surface mt-5 p-5 sm:p-6"
      aria-labelledby="source-policy"
    >
      <h2 id="source-policy" class="ks-display text-xl font-semibold">
        {{ t('platformGiftCodes.sourcePolicy') }}
      </h2>
      <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="saveSource">
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
            <option value="">{{ t('platformGiftCodes.sources.manualAdapter') }}</option>
            <option v-for="adapter in adapterKeys" :key="adapter" :value="adapter">
              {{ adapter }}
            </option>
          </select>
          <FormError :message="sourcePolicy.errors.adapter_key" />
        </label>

        <label v-if="usesFeedPath">
          <span class="ks-kicker">{{ t('platformGiftCodes.feedPath') }}</span>
          <input
            v-model="sourcePolicy.provenance_policy.feed_path"
            maxlength="2048"
            class="ks-input mt-2 w-full"
          />
          <FormError :message="sourcePolicy.errors['provenance_policy.feed_path']" />
        </label>
        <label v-if="usesProviderContract" class="flex items-center gap-2 pt-7">
          <input
            v-model="sourcePolicy.provenance_policy.provider_contract_confirmed"
            type="checkbox"
          />
          {{ t('platformGiftCodes.sources.providerContractConfirmed') }}
        </label>
        <label v-if="usesStructuredContract" class="flex items-center gap-2 pt-7">
          <input
            v-model="sourcePolicy.provenance_policy.structured_contract_confirmed"
            type="checkbox"
          />
          {{ t('platformGiftCodes.sources.structuredContractConfirmed') }}
        </label>

        <template v-if="selectedAdapter === 'x-api-v2-kingshot-v2'">
          <label
            ><span class="ks-kicker">{{ t('platformGiftCodes.sources.xUserId') }}</span
            ><input v-model="sourcePolicy.provenance_policy.x_user_id" class="ks-input mt-2 w-full"
          /></label>
          <label
            ><span class="ks-kicker">{{ t('platformGiftCodes.sources.xUsername') }}</span
            ><input
              v-model="sourcePolicy.provenance_policy.x_username"
              class="ks-input mt-2 w-full"
          /></label>
          <label class="flex items-center gap-2 md:col-span-2"
            ><input
              v-model="sourcePolicy.provenance_policy.platform_api_access_confirmed"
              type="checkbox"
            />{{ t('platformGiftCodes.sources.xApiConfirmed') }}</label
          >
        </template>

        <template v-if="selectedAdapter === 'century-games-kingshot-news-rss-v2'">
          <label class="flex items-center gap-2 md:col-span-2"
            ><input
              v-model="sourcePolicy.provenance_policy.provider_permission_confirmed"
              type="checkbox"
            />{{ t('platformGiftCodes.sources.providerPermissionConfirmed') }}</label
          >
        </template>

        <template v-if="selectedAdapter === 'discord-channel-v1'">
          <label
            ><span class="ks-kicker">{{ t('platformGiftCodes.sources.discordGuildId') }}</span
            ><input
              v-model="sourcePolicy.provenance_policy.discord_guild_id"
              class="ks-input mt-2 w-full"
          /></label>
          <label
            ><span class="ks-kicker">{{ t('platformGiftCodes.sources.discordChannelId') }}</span
            ><input
              v-model="sourcePolicy.provenance_policy.discord_channel_id"
              class="ks-input mt-2 w-full"
          /></label>
          <label class="md:col-span-2"
            ><span class="ks-kicker">{{ t('platformGiftCodes.sources.discordAuthorIds') }}</span
            ><input
              v-model="discordAuthorIds"
              class="ks-input mt-2 w-full"
              :placeholder="t('platformGiftCodes.sources.authorIdsPlaceholder')"
          /></label>
          <label class="flex items-center gap-2"
            ><input
              v-model="sourcePolicy.provenance_policy.platform_permission_confirmed"
              type="checkbox"
            />{{ t('platformGiftCodes.sources.discordPermissionConfirmed') }}</label
          >
          <label class="flex items-center gap-2"
            ><input
              v-model="sourcePolicy.provenance_policy.message_content_access_confirmed"
              type="checkbox"
            />{{ t('platformGiftCodes.sources.messageContentConfirmed') }}</label
          >
        </template>

        <template v-if="selectedAdapter === 'youtube-channel-v1'">
          <label
            ><span class="ks-kicker">{{ t('platformGiftCodes.sources.youtubeChannelId') }}</span
            ><input
              v-model="sourcePolicy.provenance_policy.youtube_channel_id"
              class="ks-input mt-2 w-full"
          /></label>
          <label
            ><span class="ks-kicker">{{ t('platformGiftCodes.sources.youtubeChannelTitle') }}</span
            ><input
              v-model="sourcePolicy.provenance_policy.youtube_channel_title"
              class="ks-input mt-2 w-full"
          /></label>
          <label class="flex items-center gap-2 md:col-span-2"
            ><input
              v-model="sourcePolicy.provenance_policy.platform_api_access_confirmed"
              type="checkbox"
            />{{ t('platformGiftCodes.sources.youtubeApiConfirmed') }}</label
          >
        </template>

        <template v-if="selectedAdapter === 'reddit-data-api-v1'">
          <label
            ><span class="ks-kicker">{{ t('platformGiftCodes.sources.subreddit') }}</span
            ><input
              v-model="sourcePolicy.provenance_policy.reddit_subreddit"
              class="ks-input mt-2 w-full"
          /></label>
          <label class="flex items-center gap-2 pt-7"
            ><input
              v-model="sourcePolicy.provenance_policy.platform_api_access_confirmed"
              type="checkbox"
            />{{ t('platformGiftCodes.sources.redditApiConfirmed') }}</label
          >
        </template>

        <template v-if="selectedAdapter === 'facebook-page-v1'">
          <label
            ><span class="ks-kicker">{{ t('platformGiftCodes.sources.facebookPageId') }}</span
            ><input
              v-model="sourcePolicy.provenance_policy.facebook_page_id"
              class="ks-input mt-2 w-full"
          /></label>
          <label
            ><span class="ks-kicker">{{ t('platformGiftCodes.sources.facebookPageName') }}</span
            ><input
              v-model="sourcePolicy.provenance_policy.facebook_page_name"
              class="ks-input mt-2 w-full"
          /></label>
          <label class="flex items-center gap-2 md:col-span-2"
            ><input
              v-model="sourcePolicy.provenance_policy.platform_permission_confirmed"
              type="checkbox"
            />{{ t('platformGiftCodes.sources.facebookPermissionConfirmed') }}</label
          >
        </template>

        <template v-if="selectedAdapter === 'instagram-media-v1'">
          <label
            ><span class="ks-kicker">{{ t('platformGiftCodes.sources.instagramUserId') }}</span
            ><input
              v-model="sourcePolicy.provenance_policy.instagram_user_id"
              class="ks-input mt-2 w-full"
          /></label>
          <label
            ><span class="ks-kicker">{{ t('platformGiftCodes.sources.instagramUsername') }}</span
            ><input
              v-model="sourcePolicy.provenance_policy.instagram_username"
              class="ks-input mt-2 w-full"
          /></label>
          <label class="flex items-center gap-2 md:col-span-2"
            ><input
              v-model="sourcePolicy.provenance_policy.platform_permission_confirmed"
              type="checkbox"
            />{{ t('platformGiftCodes.sources.instagramPermissionConfirmed') }}</label
          >
        </template>

        <div class="grid gap-3 sm:grid-cols-2 md:col-span-2 lg:grid-cols-4">
          <label class="flex items-center gap-2"
            ><input
              v-model="sourcePolicy.provenance_policy.auto_verify"
              type="checkbox"
              :disabled="sourcePolicy.classification === 'independent'"
            />{{ t('platformGiftCodes.autoVerify') }}</label
          >
          <label class="flex items-center gap-2"
            ><input
              v-model="sourcePolicy.provenance_policy.manual_evidence_allowed"
              type="checkbox"
            />{{ t('platformGiftCodes.sources.manualEvidenceAllowed') }}</label
          >
          <label class="flex items-center gap-2"
            ><input v-model="sourcePolicy.ingestion_enabled" type="checkbox" />{{
              t('platformGiftCodes.enableIngestion')
            }}</label
          >
          <label class="flex items-center gap-2"
            ><input
              v-model="sourcePolicy.authority_promotion_enabled"
              type="checkbox"
              :disabled="sourcePolicy.classification === 'independent'"
            />
            {{ t('platformGiftCodes.sources.authorityPromotion') }}</label
          >
          <label class="flex items-center gap-2"
            ><input
              v-model="sourcePolicy.push_enabled"
              type="checkbox"
              :disabled="!sourcePolicy.ingestion_enabled || !selectedPushAvailable"
            />
            {{ t('platformGiftCodes.sources.pushDiscovery') }}</label
          >
          <label class="flex items-center gap-2"
            ><input
              v-model="sourcePolicy.head_poll_enabled"
              type="checkbox"
              :disabled="!sourcePolicy.ingestion_enabled"
            />
            {{ t('platformGiftCodes.sources.headPolling') }}</label
          >
          <label class="flex items-center gap-2"
            ><input
              v-model="sourcePolicy.reconciliation_enabled"
              type="checkbox"
              :disabled="!sourcePolicy.ingestion_enabled"
            />
            {{ t('platformGiftCodes.sources.reconciliation') }}</label
          >
          <label class="flex items-center gap-2"
            ><input
              v-model="sourcePolicy.backfill_enabled"
              type="checkbox"
              :disabled="!sourcePolicy.ingestion_enabled"
            />
            {{ t('platformGiftCodes.sources.historicalBackfill') }}</label
          >
        </div>
        <p
          v-if="sourcePolicy.push_enabled && !selectedPushAvailable"
          class="text-xs text-[var(--ks-muted)] md:col-span-2"
        >
          {{ t('platformGiftCodes.sources.pushUnavailable') }}
        </p>
        <FormError class="md:col-span-2" :message="sourcePolicy.errors.ingestion_enabled" />
        <FormError class="md:col-span-2" :message="sourcePolicy.errors.push_enabled" />
        <div class="md:col-span-2">
          <AppButton
            type="submit"
            :busy="sourcePolicy.processing"
            :busy-label="t('common.saving')"
            >{{ t('platformGiftCodes.saveSourcePolicy') }}</AppButton
          >
        </div>
      </form>
    </section>

    <section class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="manual-evidence">
      <h2 id="manual-evidence" class="ks-display text-xl font-semibold">
        {{ t('platformGiftCodes.sources.manualEvidenceTitle') }}
      </h2>
      <p class="mt-2 text-sm text-[var(--ks-muted)]">
        {{ t('platformGiftCodes.sources.manualEvidenceHelp') }}
      </p>
      <form
        v-if="manualSources.length"
        class="mt-4 grid gap-4 md:grid-cols-2"
        @submit.prevent="recordEvidence"
      >
        <label
          ><span class="ks-kicker">{{ t('platformGiftCodes.sources.registeredSource') }}</span
          ><select v-model="evidence.source_id" required class="ks-input mt-2 w-full">
            <option value="" disabled>{{ t('platformGiftCodes.sources.selectSource') }}</option>
            <option v-for="source in manualSources" :key="source.id" :value="source.id">
              {{ source.name }} · {{ source.classification }}
            </option>
          </select></label
        >
        <label
          ><span class="ks-kicker">{{ t('platformGiftCodes.sources.giftCode') }}</span
          ><input
            v-model="evidence.code"
            required
            maxlength="64"
            class="ks-input mt-2 w-full font-mono"
        /></label>
        <label
          ><span class="ks-kicker">{{ t('platformGiftCodes.sources.assertion') }}</span
          ><select v-model="evidence.assertion" class="ks-input mt-2 w-full">
            <option value="available">
              {{ t('platformGiftCodes.sources.assertionAvailable') }}
            </option>
            <option value="invalid">{{ t('platformGiftCodes.sources.assertionInvalid') }}</option>
            <option value="expires">{{ t('platformGiftCodes.sources.assertionExpires') }}</option>
          </select></label
        >
        <label
          ><span class="ks-kicker">{{ t('platformGiftCodes.sources.exactEvidenceUrl') }}</span
          ><input v-model="evidence.source_url" required type="url" class="ks-input mt-2 w-full"
        /></label>
        <label
          ><span class="ks-kicker">{{ t('platformGiftCodes.sources.publishedAt') }}</span
          ><input
            v-model="evidence.published_at"
            type="datetime-local"
            class="ks-input mt-2 w-full"
        /></label>
        <label v-if="evidence.assertion === 'expires'"
          ><span class="ks-kicker">{{ t('platformGiftCodes.sources.expiresAt') }}</span
          ><input v-model="evidence.expires_at" type="datetime-local" class="ks-input mt-2 w-full"
        /></label>
        <label v-if="evidence.assertion === 'expires'"
          ><span class="ks-kicker">{{ t('platformGiftCodes.sources.expiryPrecision') }}</span
          ><select v-model="evidence.expiry_precision" class="ks-input mt-2 w-full">
            <option value="instant">{{ t('platformGiftCodes.sources.precisionInstant') }}</option>
            <option value="minute">{{ t('platformGiftCodes.sources.precisionMinute') }}</option>
            <option value="hour">{{ t('platformGiftCodes.sources.precisionHour') }}</option>
            <option value="day">{{ t('platformGiftCodes.sources.precisionDay') }}</option>
          </select></label
        >
        <label v-if="evidence.assertion === 'expires'"
          ><span class="ks-kicker">{{ t('platformGiftCodes.sources.expiryTimezone') }}</span
          ><input v-model="evidence.expiry_timezone" maxlength="80" class="ks-input mt-2 w-full"
        /></label>
        <div class="md:col-span-2">
          <AppButton type="submit" :busy="evidence.processing">{{
            t('platformGiftCodes.sources.recordEvidence')
          }}</AppButton>
        </div>
      </form>
      <p v-else class="mt-4 text-sm text-[var(--ks-muted)]">
        {{ t('platformGiftCodes.sources.noManualSources') }}
      </p>
    </section>

    <section class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="registered-sources">
      <h2 id="registered-sources" class="ks-display text-xl font-semibold">
        {{ t('platformGiftCodes.sources.registeredSources') }}
      </h2>
      <ul v-if="sources.length" class="mt-4 grid gap-3 lg:grid-cols-2">
        <li
          v-for="source in sources"
          :key="source.id"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <strong>{{ source.name }}</strong
            ><code class="text-xs">{{
              source.adapterKey ?? t('platformGiftCodes.sources.manualShort')
            }}</code>
          </div>
          <p class="mt-1 text-xs text-[var(--ks-muted)]">
            {{ source.canonicalDomain }} · {{ source.classification }}
          </p>
          <div class="mt-3 flex flex-wrap gap-2 text-xs">
            <span class="rounded border border-[var(--ks-border)] px-2 py-1"
              >{{ t('platformGiftCodes.sources.activationLabel') }}:
              {{ source.activationStatus }}</span
            >
            <span class="rounded border border-[var(--ks-border)] px-2 py-1"
              >{{ t('platformGiftCodes.sources.healthLabel') }}: {{ source.healthStatus }}</span
            >
            <span class="rounded border border-[var(--ks-border)] px-2 py-1"
              >{{ t('platformGiftCodes.sources.readinessLabel') }}:
              {{
                source.activationReadiness.ready
                  ? t('platformGiftCodes.sources.readinessReady')
                  : t('platformGiftCodes.sources.readinessBlocked')
              }}</span
            >
          </div>
          <div class="mt-3 flex flex-wrap gap-2 text-xs text-[var(--ks-muted)]">
            <span
              >{{ t('platformGiftCodes.sources.pushLabel') }}
              {{
                source.pushEnabled
                  ? t('platformGiftCodes.sources.stateOn')
                  : t('platformGiftCodes.sources.stateOff')
              }}</span
            ><span
              >{{ t('platformGiftCodes.sources.headLabel') }}
              {{
                source.headPollEnabled
                  ? t('platformGiftCodes.sources.stateOn')
                  : t('platformGiftCodes.sources.stateOff')
              }}</span
            ><span
              >{{ t('platformGiftCodes.sources.reconcileLabel') }}
              {{
                source.reconciliationEnabled
                  ? t('platformGiftCodes.sources.stateOn')
                  : t('platformGiftCodes.sources.stateOff')
              }}</span
            ><span
              >{{ t('platformGiftCodes.sources.backfillLabel') }}
              {{
                source.backfillEnabled
                  ? t('platformGiftCodes.sources.stateOn')
                  : t('platformGiftCodes.sources.stateOff')
              }}</span
            ><span
              >{{ t('platformGiftCodes.sources.authorityLabel') }}
              {{
                source.authorityPromotionEnabled
                  ? t('platformGiftCodes.sources.stateOn')
                  : t('platformGiftCodes.sources.stateOff')
              }}</span
            >
          </div>
          <ul
            v-if="!source.activationReadiness.ready"
            class="mt-3 space-y-1 text-xs text-[var(--ks-muted)]"
          >
            <li
              v-for="(check, key) in source.activationReadiness.checks"
              v-show="!check.ready"
              :key="key"
            >
              <code>{{ key }}</code
              >: {{ check.message }}
            </li>
          </ul>
          <p class="mt-3 text-xs text-[var(--ks-muted)]">
            {{ t('platformGiftCodes.sources.requestCountLabel') }} {{ source.requestCount }} ·
            {{ t('platformGiftCodes.sources.observationCountLabel') }}
            {{ source.observationCount }} · {{ t('platformGiftCodes.sources.acceptedCountLabel') }}
            {{ source.acceptedObservationCount }} ·
            {{ t('platformGiftCodes.sources.quarantinedCountLabel') }}
            {{ source.quarantinedObservationCount }} ·
            {{ t('platformGiftCodes.sources.duplicateCountLabel') }}
            {{ source.duplicateObservationCount }}
          </p>
          <p class="mt-1 text-xs text-[var(--ks-muted)]">
            {{ t('platformGiftCodes.sources.acceptanceRatioLabel') }}
            {{ asPercent(source.acceptanceRatio) }} ·
            {{ t('platformGiftCodes.sources.quarantineRatioLabel') }}
            {{ asPercent(source.quarantineRatio) }} ·
            {{ t('platformGiftCodes.sources.duplicateRatioLabel') }}
            {{ asPercent(source.duplicateRatio) }} ·
            {{ t('platformGiftCodes.sources.reconciliationGapCountLabel') }}
            {{ source.reconciliationGapCount }}
          </p>
          <p v-if="source.nextEligibleIngestionAt" class="mt-1 text-xs text-[var(--ks-muted)]">
            {{ t('platformGiftCodes.sources.nextEligible') }}
            {{ formatDate(source.nextEligibleIngestionAt) }}
          </p>
          <p v-if="source.lastAttemptAt" class="mt-1 text-xs text-[var(--ks-muted)]">
            {{ t('platformGiftCodes.sources.lastAttempt') }} {{ formatDate(source.lastAttemptAt) }}
          </p>
          <p v-if="source.lastPushReceivedAt" class="mt-1 text-xs text-[var(--ks-muted)]">
            {{ t('platformGiftCodes.sources.lastPush') }}
            {{ formatDate(source.lastPushReceivedAt) }}
          </p>
          <p v-if="source.failureCode" class="mt-1 text-xs text-[var(--ks-muted)]">
            {{ t('platformGiftCodes.sources.failureLabel') }}:
            <code>{{ source.failureCode }}</code> ·
            {{ t('platformGiftCodes.sources.consecutiveFailures') }}
            {{ source.consecutiveFailures }}
          </p>
          <p v-if="source.reconciliationGapCount > 0" class="mt-1 text-xs font-medium">
            {{
              t('platformGiftCodes.sources.pushCompletenessWarning', {
                count: source.reconciliationGapCount,
              })
            }}
          </p>

          <div
            v-if="source.subscriptions.length"
            class="mt-3 space-y-1 text-xs text-[var(--ks-muted)]"
          >
            <p
              v-for="subscription in source.subscriptions"
              :key="`${subscription.provider}:${subscription.transport}`"
            >
              <code>{{ subscription.provider }}/{{ subscription.transport }}</code> ·
              {{ subscription.status
              }}<template v-if="subscription.lastEventReceivedAt">
                · {{ t('platformGiftCodes.sources.lastEvent') }}
                {{ formatDate(subscription.lastEventReceivedAt) }}</template
              ><template v-if="subscription.lastErrorCode">
                · {{ subscription.lastErrorCode }}</template
              >
            </p>
          </div>

          <div
            v-if="canManagePlatformPolicy && source.ingestionEnabled"
            class="mt-4 flex flex-wrap gap-2"
          >
            <AppButton
              type="button"
              variant="secondary"
              :busy="busySourceAction === `${source.id}:reconcile`"
              @click="runSourceAction(source, 'reconcile')"
              >{{ t('platformGiftCodes.sources.reconcileNow') }}</AppButton
            >
            <AppButton
              v-if="source.backfillEnabled"
              type="button"
              variant="secondary"
              :busy="busySourceAction === `${source.id}:backfill`"
              @click="runSourceAction(source, 'backfill')"
              >{{ t('platformGiftCodes.sources.backfillAction') }}</AppButton
            >
            <AppButton
              v-if="
                source.pushEnabled && pushAvailableFor(source.adapterKey) && !hasActivePush(source)
              "
              type="button"
              variant="secondary"
              :busy="busySourceAction === `${source.id}:push/subscribe`"
              @click="runSourceAction(source, 'push/subscribe')"
              >{{ t('platformGiftCodes.sources.subscribePush') }}</AppButton
            >
            <AppButton
              v-if="source.pushEnabled && hasActivePush(source)"
              type="button"
              variant="secondary"
              :busy="busySourceAction === `${source.id}:push/unsubscribe`"
              @click="runSourceAction(source, 'push/unsubscribe')"
              >{{ t('platformGiftCodes.sources.disablePushSubscription') }}</AppButton
            >
          </div>
        </li>
      </ul>
      <p v-else class="mt-3 text-sm text-[var(--ks-muted)]">
        {{ t('platformGiftCodes.noApprovedSources') }}
      </p>
    </section>
  </AppLayout>
</template>
