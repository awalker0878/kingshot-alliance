<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppLayout from '../../../layouts/AppLayout.vue';
import { useLocale } from '../../../localization';

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
    revokedAt: string | null;
  }>;
  recentDeliveries: Array<{
    id: string;
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
  status: string | null;
}>();

const { t, formatDate, formatNumber } = useLocale();
const credentialForm = useForm({ name: '', scopes: [] as string[], expires_at: '' });
const webhookForm = useForm({ name: '', url: '', events: ['alliance.created'] as string[] });
const webhookEventsText = ref('alliance.created');

const activeCredentialCount = computed(
  () => props.credentials.filter((credential) => credential.revokedAt === null).length,
);
const activeWebhookCount = computed(
  () => props.webhooks.filter((webhook) => webhook.active && webhook.revokedAt === null).length,
);

function createCredential(): void {
  credentialForm.post('/alliance/integrations/api-credentials', {
    preserveScroll: true,
    onSuccess: () => credentialForm.reset(),
  });
}

function createWebhook(): void {
  webhookForm.events = webhookEventsText.value
    .split(/[,\n]/)
    .map((event) => event.trim())
    .filter(Boolean);
  webhookForm.post('/alliance/integrations/webhooks', {
    preserveScroll: true,
    onSuccess: () => {
      webhookForm.reset();
      webhookEventsText.value = 'alliance.created';
    },
  });
}

function date(value: string | null): string {
  return value ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

function credentialState(revokedAt: string | null): string {
  return revokedAt ? t('integrationExperience.revoked') : t('integrationExperience.active');
}

function stateTone(state: string): string {
  const normalized = state.toLowerCase();
  if (['active', 'delivered', 'success', 'succeeded'].includes(normalized)) {
    return 'border-green-400/25 bg-green-500/10 text-green-200';
  }
  if (['failed', 'revoked', 'dead', 'exhausted'].includes(normalized)) {
    return 'border-red-400/25 bg-red-500/10 text-red-200';
  }
  if (['pending', 'queued', 'retrying'].includes(normalized)) {
    return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
  }
  return 'border-blue-400/25 bg-blue-500/10 text-blue-200';
}
</script>

<template>
  <Head :title="`${t('integrationExperience.title')} · ${alliance.name}`" />

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="max-w-3xl">
      <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
        {{ t('integrationExperience.eyebrow') }}
      </p>
      <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
        {{ t('integrationExperience.title') }}
      </h1>
      <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('integrationExperience.subtitle', { alliance: alliance.name }) }}
      </p>
      <p
        v-if="status"
        class="mt-4 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3 text-sm text-[var(--ks-text-secondary)]"
        role="status"
      >
        {{ status }}
      </p>
    </header>

    <section class="ks-surface-gold mt-6 overflow-hidden">
      <dl class="grid grid-cols-2 divide-x divide-y divide-[var(--ks-border)] md:grid-cols-5 md:divide-y-0">
        <div class="p-4 sm:p-5">
          <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-green-300 uppercase">
            {{ t('integrationExperience.activeCredentials') }}
          </dt>
          <dd class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(activeCredentialCount) }}</dd>
        </div>
        <div class="p-4 sm:p-5">
          <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase">
            {{ t('integrationExperience.credentialLimit') }}
          </dt>
          <dd class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(limits.apiCredentials) }}</dd>
        </div>
        <div class="p-4 sm:p-5">
          <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-green-300 uppercase">
            {{ t('integrationExperience.activeWebhooks') }}
          </dt>
          <dd class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(activeWebhookCount) }}</dd>
        </div>
        <div class="p-4 sm:p-5">
          <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase">
            {{ t('integrationExperience.webhookLimit') }}
          </dt>
          <dd class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(limits.webhookSubscriptions) }}</dd>
        </div>
        <div class="col-span-2 p-4 sm:p-5 md:col-span-1">
          <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase">
            {{ t('integrationExperience.recentDeliveries') }}
          </dt>
          <dd class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(recentDeliveries.length) }}</dd>
        </div>
      </dl>
    </section>

    <section
      v-if="issuedCredential"
      class="mt-5 rounded-[var(--ks-radius-md)] border border-amber-400/30 bg-amber-500/10 p-5"
      aria-labelledby="credential-secret-heading"
    >
      <h2 id="credential-secret-heading" class="ks-display text-xl font-semibold text-amber-100">
        {{ t('integrationExperience.saveCredentialNow') }}
      </h2>
      <p class="mt-2 text-sm text-amber-100/80">{{ t('integrationExperience.credentialOneTime') }}</p>
      <code class="mt-3 block overflow-x-auto rounded-[var(--ks-radius-sm)] bg-black/35 p-3 text-sm text-white">{{ issuedCredential.token }}</code>
    </section>

    <section
      v-if="issuedWebhookSecret"
      class="mt-5 rounded-[var(--ks-radius-md)] border border-amber-400/30 bg-amber-500/10 p-5"
      aria-labelledby="webhook-secret-heading"
    >
      <h2 id="webhook-secret-heading" class="ks-display text-xl font-semibold text-amber-100">
        {{ t('integrationExperience.saveWebhookNow') }}
      </h2>
      <p class="mt-2 text-sm text-amber-100/80">{{ t('integrationExperience.webhookOneTime') }}</p>
      <code class="mt-3 block overflow-x-auto rounded-[var(--ks-radius-sm)] bg-black/35 p-3 text-sm text-white">{{ issuedWebhookSecret.secret }}</code>
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
      <section class="ks-surface overflow-hidden" aria-labelledby="api-heading">
        <div class="border-b border-[var(--ks-border)] p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 id="api-heading" class="ks-display text-xl font-semibold">{{ t('integrationExperience.apiCredentials') }}</h2>
              <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
                {{ settings.apiAccessEnabled ? t('integrationExperience.apiEnabled') : t('integrationExperience.apiDisabled') }}
                {{ t('integrationExperience.apiLimitHelp', { limit: limits.apiCredentials }) }}
              </p>
            </div>
            <span
              :class="settings.apiAccessEnabled ? 'border-green-400/25 bg-green-500/10 text-green-200' : 'border-red-400/25 bg-red-500/10 text-red-200'"
              class="rounded-full border px-2.5 py-1 text-xs font-semibold"
            >
              {{ settings.apiAccessEnabled ? t('integrationExperience.enabled') : t('integrationExperience.disabled') }}
            </span>
          </div>
        </div>

        <form v-if="settings.apiAccessEnabled" class="grid gap-4 border-b border-[var(--ks-border)] p-5 sm:grid-cols-2" @submit.prevent="createCredential">
          <div>
            <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="credential-name">{{ t('integrationExperience.name') }}</label>
            <input id="credential-name" v-model="credentialForm.name" class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm" maxlength="100" required />
          </div>
          <div>
            <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="credential-expiry">{{ t('integrationExperience.expiresAt') }} · {{ t('integrationExperience.optional') }}</label>
            <input id="credential-expiry" v-model="credentialForm.expires_at" class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm" type="datetime-local" />
          </div>
          <fieldset class="sm:col-span-2">
            <legend class="text-xs font-semibold text-[var(--ks-text-secondary)]">{{ t('integrationExperience.scopes') }}</legend>
            <div class="mt-2 flex flex-wrap gap-3">
              <label v-for="scope in allowedScopes" :key="scope" class="flex items-center gap-2 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 px-3 py-2 text-sm">
                <input v-model="credentialForm.scopes" type="checkbox" :value="scope" />
                <span class="font-mono text-xs">{{ scope }}</span>
              </label>
            </div>
          </fieldset>
          <button class="min-h-10 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white sm:col-span-2" type="submit" :disabled="credentialForm.processing">
            {{ t('integrationExperience.createCredential') }}
          </button>
        </form>

        <div v-if="credentials.length" class="lg:hidden">
          <article v-for="credential in credentials" :key="credential.id" class="border-b border-[var(--ks-border)] p-4 last:border-b-0">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate font-semibold">{{ credential.name }}</p>
                <p class="mt-1 font-mono text-xs text-[var(--ks-text-muted)]">{{ credential.prefix }}</p>
              </div>
              <span :class="stateTone(credential.revokedAt ? 'revoked' : 'active')" class="rounded-full border px-2.5 py-1 text-xs font-semibold">{{ credentialState(credential.revokedAt) }}</span>
            </div>
            <p class="mt-3 text-xs text-[var(--ks-text-secondary)]">{{ credential.scopes.join(', ') }}</p>
            <p class="mt-2 text-xs text-[var(--ks-text-muted)]">{{ t('integrationExperience.lastUsed') }}: {{ credential.lastUsedAt ? date(credential.lastUsedAt) : t('integrationExperience.never') }}</p>
            <button v-if="!credential.revokedAt" class="mt-3 rounded-[var(--ks-radius-sm)] border border-red-400/20 bg-red-500/5 px-3 py-1.5 text-xs font-semibold text-red-300" type="button" @click="router.delete(`/alliance/integrations/api-credentials/${credential.id}`)">{{ t('integrationExperience.revoke') }}</button>
          </article>
        </div>
        <div v-if="credentials.length" class="hidden overflow-x-auto lg:block">
          <table class="w-full min-w-[44rem] text-sm">
            <thead class="bg-black/25 text-[0.68rem] font-bold tracking-[0.08em] text-[var(--ks-text-muted)] uppercase"><tr><th class="px-4 py-3 text-start">{{ t('integrationExperience.name') }}</th><th class="px-4 py-3 text-start">{{ t('integrationExperience.prefix') }}</th><th class="px-4 py-3 text-start">{{ t('integrationExperience.scopes') }}</th><th class="px-4 py-3 text-start">{{ t('integrationExperience.lastUsed') }}</th><th class="px-4 py-3 text-start">{{ t('integrationExperience.state') }}</th><th class="px-4 py-3 text-start"></th></tr></thead>
            <tbody class="divide-y divide-[var(--ks-border)]"><tr v-for="credential in credentials" :key="credential.id"><td class="px-4 py-3.5 font-semibold">{{ credential.name }}</td><td class="px-4 py-3.5 font-mono text-xs">{{ credential.prefix }}</td><td class="px-4 py-3.5 text-xs text-[var(--ks-text-secondary)]">{{ credential.scopes.join(', ') }}</td><td class="px-4 py-3.5 text-xs text-[var(--ks-text-secondary)]">{{ credential.lastUsedAt ? date(credential.lastUsedAt) : t('integrationExperience.never') }}</td><td class="px-4 py-3.5"><span :class="stateTone(credential.revokedAt ? 'revoked' : 'active')" class="rounded-full border px-2.5 py-1 text-xs font-semibold">{{ credentialState(credential.revokedAt) }}</span></td><td class="px-4 py-3.5"><button v-if="!credential.revokedAt" class="rounded-[var(--ks-radius-sm)] border border-red-400/20 px-2.5 py-1.5 text-xs font-semibold text-red-300" type="button" @click="router.delete(`/alliance/integrations/api-credentials/${credential.id}`)">{{ t('integrationExperience.revoke') }}</button></td></tr></tbody>
          </table>
        </div>
        <p v-if="!credentials.length" class="p-6 text-sm text-[var(--ks-text-muted)]">{{ t('integrationExperience.noCredentials') }}</p>
      </section>

      <section class="ks-surface overflow-hidden" aria-labelledby="webhook-heading">
        <div class="border-b border-[var(--ks-border)] p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 id="webhook-heading" class="ks-display text-xl font-semibold">{{ t('integrationExperience.webhookSubscriptions') }}</h2>
              <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
                {{ settings.webhooksEnabled ? t('integrationExperience.webhooksEnabled') : t('integrationExperience.webhooksDisabled') }}
                {{ t('integrationExperience.webhookLimitHelp', { limit: limits.webhookSubscriptions }) }}
              </p>
            </div>
            <span :class="settings.webhooksEnabled ? 'border-green-400/25 bg-green-500/10 text-green-200' : 'border-red-400/25 bg-red-500/10 text-red-200'" class="rounded-full border px-2.5 py-1 text-xs font-semibold">{{ settings.webhooksEnabled ? t('integrationExperience.enabled') : t('integrationExperience.disabled') }}</span>
          </div>
        </div>

        <form v-if="settings.webhooksEnabled" class="grid gap-4 border-b border-[var(--ks-border)] p-5 sm:grid-cols-2" @submit.prevent="createWebhook">
          <div><label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="webhook-name">{{ t('integrationExperience.name') }}</label><input id="webhook-name" v-model="webhookForm.name" class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm" maxlength="100" required /></div>
          <div><label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="webhook-url">{{ t('integrationExperience.httpsEndpoint') }}</label><input id="webhook-url" v-model="webhookForm.url" class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm" type="url" maxlength="2048" required /></div>
          <div class="sm:col-span-2"><label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="webhook-events">{{ t('integrationExperience.events') }} · {{ t('integrationExperience.eventsHelp') }}</label><textarea id="webhook-events" v-model="webhookEventsText" class="mt-1.5 min-h-24 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 font-mono text-sm" required /></div>
          <button class="min-h-10 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white sm:col-span-2" type="submit" :disabled="webhookForm.processing">{{ t('integrationExperience.createWebhook') }}</button>
        </form>

        <div v-if="webhooks.length" class="space-y-px bg-[var(--ks-border)]">
          <article v-for="webhook in webhooks" :key="webhook.id" class="bg-[var(--ks-surface-1)] p-4">
            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="font-semibold">{{ webhook.name }}</p><p class="mt-1 break-all text-xs text-[var(--ks-text-muted)]">{{ webhook.url }}</p></div><span :class="stateTone(webhook.active && !webhook.revokedAt ? 'active' : 'revoked')" class="rounded-full border px-2.5 py-1 text-xs font-semibold">{{ webhook.active && !webhook.revokedAt ? t('integrationExperience.active') : t('integrationExperience.revoked') }}</span></div>
            <p class="mt-3 font-mono text-xs text-[var(--ks-text-secondary)]">{{ webhook.events.join(', ') }}</p>
            <button v-if="webhook.active && !webhook.revokedAt" class="mt-3 rounded-[var(--ks-radius-sm)] border border-red-400/20 bg-red-500/5 px-3 py-1.5 text-xs font-semibold text-red-300" type="button" @click="router.delete(`/alliance/integrations/webhooks/${webhook.id}`)">{{ t('integrationExperience.revoke') }}</button>
          </article>
        </div>
        <p v-if="!webhooks.length" class="p-6 text-sm text-[var(--ks-text-muted)]">{{ t('integrationExperience.noWebhooks') }}</p>
      </section>
    </div>

    <section class="ks-surface mt-5 overflow-hidden" aria-labelledby="delivery-heading">
      <div class="border-b border-[var(--ks-border)] p-5">
        <h2 id="delivery-heading" class="ks-display text-xl font-semibold">{{ t('integrationExperience.deliveryLog') }}</h2>
        <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">{{ t('integrationExperience.deliveryHelp') }}</p>
      </div>
      <div v-if="recentDeliveries.length" class="lg:hidden">
        <article v-for="delivery in recentDeliveries" :key="delivery.id" class="border-b border-[var(--ks-border)] p-4 last:border-b-0">
          <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate font-mono text-sm font-semibold">{{ delivery.event }}</p><p class="mt-1 text-xs text-[var(--ks-text-muted)]">{{ t('integrationExperience.attempts') }}: {{ delivery.attempts }} · HTTP {{ delivery.responseCode ?? '—' }}</p></div><span :class="stateTone(delivery.status)" class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize">{{ delivery.status }}</span></div>
          <p class="mt-3 text-xs text-[var(--ks-text-secondary)]">{{ t('integrationExperience.lastAttempt') }}: {{ date(delivery.lastAttemptAt) }}</p>
          <p v-if="delivery.deliveredAt" class="mt-1 text-xs text-green-300">{{ t('integrationExperience.deliveredAt') }}: {{ date(delivery.deliveredAt) }}</p>
          <p class="mt-2 text-xs text-[var(--ks-text-muted)]">{{ delivery.lastError || t('integrationExperience.noError') }}</p>
        </article>
      </div>
      <div v-if="recentDeliveries.length" class="hidden overflow-x-auto lg:block">
        <table class="w-full min-w-[62rem] text-sm">
          <thead class="bg-black/25 text-[0.68rem] font-bold tracking-[0.08em] text-[var(--ks-text-muted)] uppercase"><tr><th class="px-4 py-3 text-start">{{ t('integrationExperience.event') }}</th><th class="px-4 py-3 text-start">{{ t('integrationExperience.status') }}</th><th class="px-4 py-3 text-start">{{ t('integrationExperience.attempts') }}</th><th class="px-4 py-3 text-start">{{ t('integrationExperience.responseCode') }}</th><th class="px-4 py-3 text-start">{{ t('integrationExperience.lastAttempt') }}</th><th class="px-4 py-3 text-start">{{ t('integrationExperience.lastError') }}</th></tr></thead>
          <tbody class="divide-y divide-[var(--ks-border)]"><tr v-for="delivery in recentDeliveries" :key="delivery.id"><td class="px-4 py-3.5 font-mono text-xs">{{ delivery.event }}</td><td class="px-4 py-3.5"><span :class="stateTone(delivery.status)" class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize">{{ delivery.status }}</span></td><td class="px-4 py-3.5">{{ delivery.attempts }}</td><td class="px-4 py-3.5">{{ delivery.responseCode ?? '—' }}</td><td class="px-4 py-3.5 text-xs text-[var(--ks-text-secondary)]">{{ date(delivery.lastAttemptAt ?? delivery.deliveredAt) }}</td><td class="max-w-md px-4 py-3.5 text-xs text-[var(--ks-text-secondary)]">{{ delivery.lastError || t('integrationExperience.noError') }}</td></tr></tbody>
        </table>
      </div>
      <p v-if="!recentDeliveries.length" class="p-8 text-center text-sm text-[var(--ks-text-muted)]">{{ t('integrationExperience.noDeliveries') }}</p>
    </section>
  </AppLayout>
</template>
