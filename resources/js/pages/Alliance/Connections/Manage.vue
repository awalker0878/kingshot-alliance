<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import ActionNotice from '@/components/ui/ActionNotice.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import FormError from '@/components/ui/FormError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  settings: { apiAccessEnabled: boolean; webhooksEnabled: boolean };
  limits: {
    members: number;
    storageBytes: number;
    apiCredentials: number;
    webhookSubscriptions: number;
  };
  allowedScopes: string[];
  publicWebhookEvents: string[];
  credentials: Array<{
    id: string;
    name: string;
    prefix: string;
    scopes: string[];
    expiresAt: string | null;
    lastUsedAt: string | null;
    revokedAt: string | null;
  }>;
  webhooks: Array<{
    id: string;
    name: string;
    url: string;
    events: string[];
    active: boolean;
    secretRotatedAt: string | null;
    revokedAt: string | null;
  }>;
  recentDeliveries: Array<{
    id: string;
    subscriptionId: string;
    event: string;
    status: string;
    attempts: number;
    responseCode: number | null;
    lastError: string | null;
    lastAttemptAt: string | null;
    deliveredAt: string | null;
  }>;
  issuedCredential: { id: string; name: string; token: string } | null;
  issuedWebhookSecret: { id: string; name: string; secret: string } | null;
}>();

const { t, formatDate, formatNumber } = useLocale();
const credentialForm = useForm({ name: '', scopes: [] as string[], expires_at: '' });
const webhookForm = useForm({ name: '', url: '', events: [] as string[] });
const pendingRevoke = ref<{ kind: 'credential' | 'webhook'; id: string; name: string } | null>(
  null,
);
const revoking = ref(false);
const testingWebhook = ref<string | null>(null);
const pendingSecretRotation = ref<{ id: string; name: string } | null>(null);
const rotatingSecret = ref(false);
const retryingDelivery = ref<string | null>(null);
const mutationError = ref<string | null>(null);
const commandEndpoints = [
  { method: 'GET', path: '/api/v1/commands/overview', scope: 'commands:read' },
  { method: 'GET', path: '/api/v1/gift-codes', scope: 'gift-codes:read' },
  { method: 'GET', path: '/api/v1/commands/knowledge', scope: 'content:read' },
  { method: 'POST', path: '/api/v1/actor-links/claims', scope: 'actor-links:write' },
  {
    method: 'PUT',
    path: '/api/v1/me/events/{occurrence}/response',
    scope: 'event-participation:write',
  },
  {
    method: 'PUT',
    path: '/api/v1/me/events/{occurrence}/registration',
    scope: 'event-participation:write',
  },
];

const scopeDescriptions: Record<string, string> = {
  'alliance:read': t('integrationExperience.scopeAlliance'),
  'events:read': t('integrationExperience.scopeEvents'),
  'contributions:read': t('integrationExperience.scopeContributions'),
  'commands:read': t('integrationExperience.scopeCommands'),
  'gift-codes:read': t('integrationExperience.scopeGiftCodes'),
  'content:read': t('integrationExperience.scopeContent'),
  'actor-links:write': t('integrationExperience.scopeActorLinks'),
  'event-participation:write': t('integrationExperience.scopeEventParticipation'),
};

const webhookEventDescriptions: Record<string, string> = {
  '*': t('integrationExperience.eventAllDescription'),
  'content.published': t('integrationExperience.eventContentPublished'),
  'event.created': t('integrationExperience.eventCreated'),
  'event.updated': t('integrationExperience.eventUpdated'),
  'event.cancelled': t('integrationExperience.eventCancelled'),
  'member.updated': t('integrationExperience.eventMemberUpdated'),
  'member.left': t('integrationExperience.eventMemberLeft'),
  'recruitment.candidate.stage_changed': t('integrationExperience.eventCandidateStageChanged'),
  'recruitment.candidate.joined': t('integrationExperience.eventCandidateJoined'),
  'gift_code.created': t('integrationExperience.eventGiftCodeCreated'),
  'gift_code.provenance_added': t('integrationExperience.eventGiftCodeProvenanceAdded'),
  'gift_code.status_changed': t('integrationExperience.eventGiftCodeStatusChanged'),
  'gift_code.expiry_changed': t('integrationExperience.eventGiftCodeExpiryChanged'),
  'broadcast.schedule.updated': t('integrationExperience.eventBroadcastScheduleUpdated'),
  'broadcast.schedule.cancelled': t('integrationExperience.eventBroadcastScheduleCancelled'),
  'broadcast.run.queued': t('integrationExperience.eventBroadcastRunQueued'),
  'broadcast.delivery.succeeded': t('integrationExperience.eventBroadcastDeliverySucceeded'),
  'broadcast.delivery.failed': t('integrationExperience.eventBroadcastDeliveryFailed'),
};

const webhookEventOptions = computed(() => ['*', ...props.publicWebhookEvents]);

function scopeDescription(scope: string): string {
  return scopeDescriptions[scope] ?? scope;
}

const activeCredentialCount = computed(
  () => props.credentials.filter((credential) => credential.revokedAt === null).length,
);
const activeWebhookCount = computed(
  () => props.webhooks.filter((webhook) => webhook.active && webhook.revokedAt === null).length,
);
const revokeDescription = computed(() =>
  pendingRevoke.value
    ? t('integrationExperience.revokeDescription', { name: pendingRevoke.value.name })
    : '',
);

function createCredential(): void {
  credentialForm.post('/alliance/integrations/api-credentials', {
    preserveScroll: true,
    onSuccess: () => credentialForm.reset(),
  });
}

function createWebhook(): void {
  webhookForm.post('/alliance/integrations/webhooks', {
    preserveScroll: true,
    onSuccess: () => webhookForm.reset(),
  });
}

function normalizeEventSelection(event: string): void {
  if (event === '*' && webhookForm.events.includes('*')) {
    webhookForm.events = ['*'];
    return;
  }

  if (event !== '*' && webhookForm.events.includes(event)) {
    webhookForm.events = webhookForm.events.filter((selected) => selected !== '*');
  }
}

function eventDescription(event: string): string {
  return webhookEventDescriptions[event] ?? event;
}

function eventInputId(event: string): string {
  return `webhook-event-${event.replace(/[^a-z]+/gi, '-')}`;
}

function requestRevoke(kind: 'credential' | 'webhook', id: string, name: string): void {
  pendingRevoke.value = { kind, id, name };
}

function testWebhook(subscriptionId: string): void {
  if (testingWebhook.value !== null) return;

  mutationError.value = null;
  testingWebhook.value = subscriptionId;
  router.post(
    `/alliance/integrations/webhooks/${subscriptionId}/test`,
    {},
    {
      preserveScroll: true,
      onError: captureMutationError,
      onFinish: () => (testingWebhook.value = null),
    },
  );
}

function requestSecretRotation(id: string, name: string): void {
  pendingSecretRotation.value = { id, name };
}

function confirmSecretRotation(): void {
  const target = pendingSecretRotation.value;
  if (!target || rotatingSecret.value) return;

  mutationError.value = null;
  rotatingSecret.value = true;
  router.post(
    `/alliance/integrations/webhooks/${target.id}/rotate-secret`,
    {},
    {
      preserveScroll: true,
      onSuccess: () => (pendingSecretRotation.value = null),
      onError: captureMutationError,
      onFinish: () => (rotatingSecret.value = false),
    },
  );
}

function retryDelivery(deliveryId: string): void {
  if (retryingDelivery.value !== null) return;

  mutationError.value = null;
  retryingDelivery.value = deliveryId;
  router.post(
    `/alliance/integrations/webhook-deliveries/${deliveryId}/retry`,
    {},
    {
      preserveScroll: true,
      onError: captureMutationError,
      onFinish: () => (retryingDelivery.value = null),
    },
  );
}

function captureMutationError(errors: Record<string, string>): void {
  mutationError.value = Object.values(errors)[0] ?? t('integrationExperience.operationFailed');
}

function confirmRevoke(): void {
  const target = pendingRevoke.value;
  if (!target || revoking.value) return;

  revoking.value = true;
  const path =
    target.kind === 'credential'
      ? `/alliance/integrations/api-credentials/${target.id}`
      : `/alliance/integrations/webhooks/${target.id}`;
  router.delete(path, {
    preserveScroll: true,
    onFinish: () => {
      revoking.value = false;
      pendingRevoke.value = null;
    },
  });
}

function date(value: string | null): string {
  return value ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

function credentialState(revokedAt: string | null): string {
  return revokedAt ? t('integrationExperience.revoked') : t('integrationExperience.active');
}

function stateTone(state: string): 'success' | 'warning' | 'danger' | 'info' {
  const normalized = state.toLowerCase();
  if (['active', 'delivered', 'success', 'succeeded'].includes(normalized)) return 'success';
  if (['failed', 'revoked', 'dead', 'exhausted'].includes(normalized)) return 'danger';
  if (['pending', 'queued', 'retrying'].includes(normalized)) return 'warning';
  return 'info';
}

function deliveryStatusLabel(status: string): string {
  const keys: Record<string, string> = {
    pending: 'deliveryPending',
    delivering: 'deliveryDelivering',
    delivered: 'deliveryDelivered',
    failed: 'deliveryFailed',
  };

  return t(`integrationExperience.${keys[status] ?? 'deliveryUnknown'}`);
}

function webhookName(subscriptionId: string): string {
  return props.webhooks.find((webhook) => webhook.id === subscriptionId)?.name ?? '—';
}
</script>

<template>
  <Head :title="`${t('integrationExperience.title')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('integrationExperience.eyebrow')"
      :title="t('integrationExperience.title')"
      :subtitle="t('integrationExperience.subtitle', { alliance: alliance.name })"
      image="/images/kingshot/v4/connections.svg"
    />

    <section class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-5">
      <StatSeal
        :label="t('integrationExperience.activeCredentials')"
        :value="formatNumber(activeCredentialCount)"
        icon="⌘"
      />
      <StatSeal
        :label="t('integrationExperience.credentialLimit')"
        :value="formatNumber(limits.apiCredentials)"
        icon="◇"
        tone="stone"
      />
      <StatSeal
        :label="t('integrationExperience.activeWebhooks')"
        :value="formatNumber(activeWebhookCount)"
        icon="↗"
        tone="teal"
      />
      <StatSeal
        :label="t('integrationExperience.webhookLimit')"
        :value="formatNumber(limits.webhookSubscriptions)"
        icon="◎"
      />
      <StatSeal
        :label="t('integrationExperience.recentDeliveries')"
        :value="formatNumber(recentDeliveries.length)"
        icon="▤"
        tone="teal"
      />
    </section>

    <section class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="command-api-heading">
      <p class="ks-kicker">{{ t('integrationExperience.commandApi') }}</p>
      <h2 id="command-api-heading" class="ks-display mt-1 text-2xl font-semibold">
        {{ t('integrationExperience.botReadyReads') }}
      </h2>
      <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('integrationExperience.commandApiHelp') }}
      </p>
      <div class="mt-5 grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
        <article
          v-for="endpoint in commandEndpoints"
          :key="endpoint.path"
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
        >
          <p class="text-xs font-bold tracking-[.12em] text-[var(--ks-gold)] uppercase">
            {{ endpoint.method }}
          </p>
          <code class="mt-2 block overflow-x-auto text-sm text-[var(--ks-ivory)]">
            {{ endpoint.path }}
          </code>
          <p class="mt-3 font-mono text-xs text-[var(--ks-muted)]">{{ endpoint.scope }}</p>
        </article>
      </div>
      <p class="mt-4 text-xs leading-5 text-[var(--ks-muted)]">
        {{ t('integrationExperience.commandApiBoundary') }}
      </p>
    </section>

    <ActionNotice class="mt-5" :message="mutationError" tone="danger" />

    <div v-if="issuedCredential || issuedWebhookSecret" class="mt-5 grid gap-4 lg:grid-cols-2">
      <section
        v-if="issuedCredential"
        class="rounded-[var(--ks-radius-md)] border border-amber-400/30 bg-amber-500/[.07] p-5"
        aria-labelledby="credential-secret-heading"
      >
        <p class="ks-kicker">{{ t('integrationExperience.apiCredentials') }}</p>
        <h2
          id="credential-secret-heading"
          class="ks-display mt-1 text-xl font-semibold text-amber-100"
        >
          {{ t('integrationExperience.saveCredentialNow') }}
        </h2>
        <p class="mt-2 text-sm text-amber-100/80">
          {{ t('integrationExperience.credentialOneTime') }}
        </p>
        <code
          class="mt-4 block overflow-x-auto rounded-[var(--ks-radius-sm)] bg-black/35 p-3 text-sm text-[var(--ks-ivory)]"
        >
          {{ issuedCredential.token }}
        </code>
      </section>

      <section
        v-if="issuedWebhookSecret"
        class="rounded-[var(--ks-radius-md)] border border-amber-400/30 bg-amber-500/[.07] p-5"
        aria-labelledby="webhook-secret-heading"
      >
        <p class="ks-kicker">{{ t('integrationExperience.webhookSubscriptions') }}</p>
        <h2
          id="webhook-secret-heading"
          class="ks-display mt-1 text-xl font-semibold text-amber-100"
        >
          {{ t('integrationExperience.saveWebhookNow') }}
        </h2>
        <p class="mt-2 text-sm text-amber-100/80">
          {{ t('integrationExperience.webhookOneTime') }}
        </p>
        <code
          class="mt-4 block overflow-x-auto rounded-[var(--ks-radius-sm)] bg-black/35 p-3 text-sm text-[var(--ks-ivory)]"
        >
          {{ issuedWebhookSecret.secret }}
        </code>
      </section>
    </div>

    <div class="mt-5 grid gap-5 2xl:grid-cols-2">
      <section class="ks-surface overflow-hidden" aria-labelledby="api-heading">
        <div class="border-b border-[var(--ks-border)] p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="max-w-2xl">
              <p class="ks-kicker">{{ t('integrationExperience.apiCredentials') }}</p>
              <h2 id="api-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{ t('integrationExperience.apiCredentials') }}
              </h2>
              <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
                {{
                  settings.apiAccessEnabled
                    ? t('integrationExperience.apiEnabled')
                    : t('integrationExperience.apiDisabled')
                }}
                {{ t('integrationExperience.apiLimitHelp', { limit: limits.apiCredentials }) }}
              </p>
            </div>
            <span class="ks-status" :data-tone="settings.apiAccessEnabled ? 'success' : 'danger'">
              {{
                settings.apiAccessEnabled
                  ? t('integrationExperience.enabled')
                  : t('integrationExperience.disabled')
              }}
            </span>
          </div>
        </div>

        <form
          v-if="settings.apiAccessEnabled"
          class="grid gap-4 border-b border-[var(--ks-border)] p-5 sm:grid-cols-2"
          @submit.prevent="createCredential"
        >
          <div>
            <label class="text-xs font-semibold" for="credential-name">{{
              t('integrationExperience.name')
            }}</label>
            <input
              id="credential-name"
              v-model="credentialForm.name"
              class="ks-input mt-1.5"
              maxlength="100"
              :aria-invalid="credentialForm.errors.name ? 'true' : undefined"
              :aria-describedby="credentialForm.errors.name ? 'credential-name-error' : undefined"
              required
            />
            <FormError id="credential-name-error" :message="credentialForm.errors.name" />
          </div>
          <div>
            <label class="text-xs font-semibold" for="credential-expiry">
              {{ t('integrationExperience.expiresAt') }} · {{ t('integrationExperience.optional') }}
            </label>
            <input
              id="credential-expiry"
              v-model="credentialForm.expires_at"
              class="ks-input mt-1.5"
              type="datetime-local"
              :aria-invalid="credentialForm.errors.expires_at ? 'true' : undefined"
              :aria-describedby="
                credentialForm.errors.expires_at ? 'credential-expiry-error' : undefined
              "
            />
            <FormError id="credential-expiry-error" :message="credentialForm.errors.expires_at" />
          </div>
          <fieldset
            class="sm:col-span-2"
            :aria-describedby="credentialForm.errors.scopes ? 'credential-scopes-error' : undefined"
          >
            <legend class="text-xs font-semibold">{{ t('integrationExperience.scopes') }}</legend>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
              <label
                v-for="scope in allowedScopes"
                :key="scope"
                class="cursor-pointer rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3"
              >
                <span class="flex items-center gap-2">
                  <input v-model="credentialForm.scopes" type="checkbox" :value="scope" />
                  <span class="font-mono text-xs font-semibold">{{ scope }}</span>
                </span>
                <span class="mt-1.5 block text-xs leading-5 text-[var(--ks-muted)]">
                  {{ scopeDescription(scope) }}
                </span>
              </label>
            </div>
            <FormError id="credential-scopes-error" :message="credentialForm.errors.scopes" />
          </fieldset>
          <AppButton
            class="sm:col-span-2"
            type="submit"
            :busy="credentialForm.processing"
            :busy-label="t('integrationExperience.creatingCredential')"
          >
            {{ t('integrationExperience.createCredential') }}
          </AppButton>
        </form>

        <div v-if="credentials.length" class="divide-y divide-[var(--ks-border)]">
          <article v-for="credential in credentials" :key="credential.id" class="p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <strong class="text-lg font-[var(--ks-font-display)]">{{
                    credential.name
                  }}</strong>
                  <span
                    class="ks-status"
                    :data-tone="stateTone(credential.revokedAt ? 'revoked' : 'active')"
                  >
                    {{ credentialState(credential.revokedAt) }}
                  </span>
                </div>
                <p class="mt-1 font-mono text-xs text-[var(--ks-muted)]">{{ credential.prefix }}</p>
              </div>
              <AppButton
                v-if="!credential.revokedAt"
                variant="danger"
                @click="requestRevoke('credential', credential.id, credential.name)"
              >
                {{ t('integrationExperience.revoke') }}
              </AppButton>
            </div>
            <div class="mt-3 flex flex-wrap gap-1.5">
              <span v-for="scope in credential.scopes" :key="scope" class="ks-chip font-mono">{{
                scope
              }}</span>
            </div>
            <div class="mt-4 grid gap-3 text-xs text-[var(--ks-muted)] sm:grid-cols-2">
              <p>
                {{ t('integrationExperience.lastUsed') }}:
                {{
                  credential.lastUsedAt
                    ? date(credential.lastUsedAt)
                    : t('integrationExperience.never')
                }}
              </p>
              <p>{{ t('integrationExperience.expiresAt') }}: {{ date(credential.expiresAt) }}</p>
            </div>
          </article>
        </div>
        <div v-else class="ks-fantasy-empty m-5">
          {{ t('integrationExperience.noCredentials') }}
        </div>
      </section>

      <section class="ks-surface overflow-hidden" aria-labelledby="webhook-heading">
        <div class="border-b border-[var(--ks-border)] p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="max-w-2xl">
              <p class="ks-kicker">{{ t('integrationExperience.webhookSubscriptions') }}</p>
              <h2 id="webhook-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{ t('integrationExperience.webhookSubscriptions') }}
              </h2>
              <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
                {{
                  settings.webhooksEnabled
                    ? t('integrationExperience.webhooksEnabled')
                    : t('integrationExperience.webhooksDisabled')
                }}
                {{
                  t('integrationExperience.webhookLimitHelp', {
                    limit: limits.webhookSubscriptions,
                  })
                }}
              </p>
            </div>
            <span class="ks-status" :data-tone="settings.webhooksEnabled ? 'success' : 'danger'">
              {{
                settings.webhooksEnabled
                  ? t('integrationExperience.enabled')
                  : t('integrationExperience.disabled')
              }}
            </span>
          </div>
        </div>

        <form
          v-if="settings.webhooksEnabled"
          class="grid gap-4 border-b border-[var(--ks-border)] p-5 sm:grid-cols-2"
          @submit.prevent="createWebhook"
        >
          <div>
            <label class="text-xs font-semibold" for="webhook-name">{{
              t('integrationExperience.name')
            }}</label>
            <input
              id="webhook-name"
              v-model="webhookForm.name"
              class="ks-input mt-1.5"
              maxlength="100"
              :aria-invalid="webhookForm.errors.name ? 'true' : undefined"
              :aria-describedby="webhookForm.errors.name ? 'webhook-name-error' : undefined"
              required
            />
            <FormError id="webhook-name-error" :message="webhookForm.errors.name" />
          </div>
          <div>
            <label class="text-xs font-semibold" for="webhook-url">{{
              t('integrationExperience.httpsEndpoint')
            }}</label>
            <input
              id="webhook-url"
              v-model="webhookForm.url"
              class="ks-input mt-1.5"
              type="url"
              maxlength="2048"
              :aria-invalid="webhookForm.errors.url ? 'true' : undefined"
              :aria-describedby="webhookForm.errors.url ? 'webhook-url-error' : undefined"
              required
            />
            <FormError id="webhook-url-error" :message="webhookForm.errors.url" />
          </div>
          <fieldset
            class="sm:col-span-2"
            :aria-invalid="webhookForm.errors.events ? 'true' : undefined"
            :aria-describedby="
              webhookForm.errors.events
                ? 'webhook-events-help webhook-events-error'
                : 'webhook-events-help'
            "
          >
            <legend class="text-xs font-semibold">{{ t('integrationExperience.events') }}</legend>
            <p id="webhook-events-help" class="mt-1 text-xs text-[var(--ks-muted)]">
              {{ t('integrationExperience.eventsHelp') }}
            </p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
              <label
                v-for="event in webhookEventOptions"
                :key="event"
                :for="eventInputId(event)"
                class="flex min-h-16 cursor-pointer gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/10 p-3 transition hover:border-[var(--ks-border-strong)]"
              >
                <input
                  :id="eventInputId(event)"
                  v-model="webhookForm.events"
                  type="checkbox"
                  :value="event"
                  class="mt-1"
                  @change="normalizeEventSelection(event)"
                />
                <span class="min-w-0">
                  <span class="block font-mono text-xs text-[var(--ks-gold-bright)]">{{
                    event
                  }}</span>
                  <span class="mt-1 block text-xs leading-5 text-[var(--ks-muted)]">{{
                    eventDescription(event)
                  }}</span>
                </span>
              </label>
            </div>
            <FormError id="webhook-events-error" :message="webhookForm.errors.events" />
          </fieldset>
          <AppButton
            class="sm:col-span-2"
            type="submit"
            :disabled="webhookForm.events.length === 0"
            :busy="webhookForm.processing"
            :busy-label="t('integrationExperience.creatingWebhook')"
          >
            {{ t('integrationExperience.createWebhook') }}
          </AppButton>
        </form>

        <div v-if="webhooks.length" class="divide-y divide-[var(--ks-border)]">
          <article v-for="webhook in webhooks" :key="webhook.id" class="p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <strong class="text-lg font-[var(--ks-font-display)]">{{ webhook.name }}</strong>
                  <span
                    class="ks-status"
                    :data-tone="
                      stateTone(webhook.active && !webhook.revokedAt ? 'active' : 'revoked')
                    "
                  >
                    {{
                      webhook.active && !webhook.revokedAt
                        ? t('integrationExperience.active')
                        : t('integrationExperience.revoked')
                    }}
                  </span>
                </div>
                <p class="mt-1 text-xs break-all text-[var(--ks-muted)]">{{ webhook.url }}</p>
                <p v-if="webhook.secretRotatedAt" class="mt-1 text-xs text-[var(--ks-muted)]">
                  {{ t('integrationExperience.secretRotated') }}:
                  {{ date(webhook.secretRotatedAt) }}
                </p>
              </div>
              <div v-if="webhook.active && !webhook.revokedAt" class="flex flex-wrap gap-2">
                <AppButton
                  variant="secondary"
                  :busy="testingWebhook === webhook.id"
                  :disabled="testingWebhook !== null && testingWebhook !== webhook.id"
                  :busy-label="t('integrationExperience.sendingTest')"
                  @click="testWebhook(webhook.id)"
                >
                  {{ t('integrationExperience.sendTest') }}
                </AppButton>
                <AppButton
                  variant="ghost"
                  :disabled="rotatingSecret"
                  @click="requestSecretRotation(webhook.id, webhook.name)"
                >
                  {{ t('integrationExperience.rotateSecret') }}
                </AppButton>
                <AppButton
                  variant="danger"
                  @click="requestRevoke('webhook', webhook.id, webhook.name)"
                >
                  {{ t('integrationExperience.revoke') }}
                </AppButton>
              </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-1.5">
              <span v-for="event in webhook.events" :key="event" class="ks-chip font-mono">{{
                event
              }}</span>
            </div>
          </article>
        </div>
        <div v-else class="ks-fantasy-empty m-5">{{ t('integrationExperience.noWebhooks') }}</div>
      </section>
    </div>

    <section class="ks-surface mt-5 overflow-hidden" aria-labelledby="delivery-heading">
      <div
        class="flex flex-wrap items-end justify-between gap-3 border-b border-[var(--ks-border)] p-5"
      >
        <div>
          <p class="ks-kicker">{{ t('integrationExperience.recentDeliveries') }}</p>
          <h2 id="delivery-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('integrationExperience.deliveryLog') }}
          </h2>
        </div>
        <p class="max-w-2xl text-xs text-[var(--ks-muted)]">
          {{ t('integrationExperience.deliveryHelp') }}
        </p>
      </div>

      <div v-if="recentDeliveries.length" class="lg:hidden">
        <article
          v-for="delivery in recentDeliveries"
          :key="delivery.id"
          class="border-b border-[var(--ks-border)] p-4 last:border-b-0"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate font-mono text-sm font-semibold">{{ delivery.event }}</p>
              <p class="mt-1 truncate text-xs text-[var(--ks-muted)]">
                {{ webhookName(delivery.subscriptionId) }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('integrationExperience.attempts') }}: {{ delivery.attempts }} · HTTP
                {{ delivery.responseCode ?? '—' }}
              </p>
            </div>
            <span class="ks-status" :data-tone="stateTone(delivery.status)">{{
              deliveryStatusLabel(delivery.status)
            }}</span>
          </div>
          <p class="mt-3 text-xs text-[var(--ks-text-secondary)]">
            {{ t('integrationExperience.lastAttempt') }}: {{ date(delivery.lastAttemptAt) }}
          </p>
          <p v-if="delivery.deliveredAt" class="mt-1 text-xs text-[var(--ks-green)]">
            {{ t('integrationExperience.deliveredAt') }}: {{ date(delivery.deliveredAt) }}
          </p>
          <p class="mt-2 text-xs text-[var(--ks-muted)]">
            {{ delivery.lastError || t('integrationExperience.noError') }}
          </p>
          <AppButton
            v-if="delivery.status === 'failed'"
            class="mt-3"
            variant="secondary"
            :busy="retryingDelivery === delivery.id"
            :disabled="retryingDelivery !== null && retryingDelivery !== delivery.id"
            :busy-label="t('integrationExperience.retryingDelivery')"
            @click="retryDelivery(delivery.id)"
          >
            {{ t('integrationExperience.retryDelivery') }}
          </AppButton>
        </article>
      </div>

      <div v-if="recentDeliveries.length" class="hidden overflow-x-auto lg:block">
        <table class="w-full min-w-[62rem] text-sm">
          <thead
            class="bg-black/20 text-[.66rem] font-extrabold tracking-[.08em] text-[var(--ks-muted)] uppercase"
          >
            <tr>
              <th class="px-4 py-3 text-start">{{ t('integrationExperience.event') }}</th>
              <th class="px-4 py-3 text-start">{{ t('integrationExperience.status') }}</th>
              <th class="px-4 py-3 text-start">{{ t('integrationExperience.attempts') }}</th>
              <th class="px-4 py-3 text-start">{{ t('integrationExperience.responseCode') }}</th>
              <th class="px-4 py-3 text-start">{{ t('integrationExperience.lastAttempt') }}</th>
              <th class="px-4 py-3 text-start">{{ t('integrationExperience.lastError') }}</th>
              <th class="px-4 py-3 text-start">{{ t('integrationExperience.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr
              v-for="delivery in recentDeliveries"
              :key="delivery.id"
              class="transition hover:bg-white/[0.018]"
            >
              <td class="px-4 py-4">
                <span class="block font-mono text-xs">{{ delivery.event }}</span>
                <span class="mt-1 block text-xs text-[var(--ks-muted)]">{{
                  webhookName(delivery.subscriptionId)
                }}</span>
              </td>
              <td class="px-4 py-4">
                <span class="ks-status" :data-tone="stateTone(delivery.status)">{{
                  deliveryStatusLabel(delivery.status)
                }}</span>
              </td>
              <td class="px-4 py-4">{{ delivery.attempts }}</td>
              <td class="px-4 py-4">{{ delivery.responseCode ?? '—' }}</td>
              <td class="px-4 py-4 text-xs text-[var(--ks-text-secondary)]">
                {{ date(delivery.lastAttemptAt ?? delivery.deliveredAt) }}
              </td>
              <td class="max-w-md px-4 py-4 text-xs text-[var(--ks-text-secondary)]">
                {{ delivery.lastError || t('integrationExperience.noError') }}
              </td>
              <td class="px-4 py-4">
                <AppButton
                  v-if="delivery.status === 'failed'"
                  variant="secondary"
                  :busy="retryingDelivery === delivery.id"
                  :disabled="retryingDelivery !== null && retryingDelivery !== delivery.id"
                  :busy-label="t('integrationExperience.retryingDelivery')"
                  @click="retryDelivery(delivery.id)"
                >
                  {{ t('integrationExperience.retryDelivery') }}
                </AppButton>
                <span v-else aria-hidden="true">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!recentDeliveries.length" class="ks-fantasy-empty m-5">
        {{ t('integrationExperience.noDeliveries') }}
      </div>
    </section>

    <ConfirmActionDialog
      id="integration-revoke"
      :open="pendingRevoke !== null"
      :title="t('integrationExperience.revokeTitle')"
      :description="revokeDescription"
      :confirm-label="t('integrationExperience.revoke')"
      :cancel-label="t('common.cancel')"
      :busy="revoking"
      :busy-label="t('integrationExperience.revoking')"
      danger
      @confirm="confirmRevoke"
      @cancel="pendingRevoke = null"
    />
    <ConfirmActionDialog
      id="webhook-secret-rotation"
      :open="pendingSecretRotation !== null"
      :title="t('integrationExperience.rotateSecretTitle')"
      :description="
        t('integrationExperience.rotateSecretDescription', {
          name: pendingSecretRotation?.name ?? '',
        })
      "
      :confirm-label="t('integrationExperience.rotateSecret')"
      :cancel-label="t('common.cancel')"
      :busy="rotatingSecret"
      :busy-label="t('integrationExperience.rotatingSecret')"
      danger
      @confirm="confirmSecretRotation"
      @cancel="pendingSecretRotation = null"
    />
  </AppLayout>
</template>
