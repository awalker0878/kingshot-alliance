<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLayout from '../../../layouts/AppLayout.vue';
import { useLocale } from '../../../localization';
import { defaultLocale, locales } from '../../../localization/locales';
import { hasMessageCatalogue } from '../../../localization/messages';

type AllianceRow = {
  id: string;
  name: string;
  slug: string;
  status: string;
  timezone: string;
  activeMembers: number;
  storageBytes: number;
  apiCredentials: number;
  webhooks: number;
  pendingOutbox: number;
  plan: string;
  retentionDays: number;
  queuePartition: string;
  apiAccessEnabled: boolean;
  webhooksEnabled: boolean;
  retentionUntil: string | null;
  lifecycleReason: string | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  platform: {
    metrics: Record<string, number>;
    alliances: AllianceRow[];
    administrators: Array<{
      id: string;
      userId: number;
      name: string | null;
      email: string | null;
      mfaEnabled: boolean;
      grantedAt: string;
      revokedAt: string | null;
    }>;
    plans: Array<{ code: string; name: string; entitlements: Record<string, number> }>;
    legalHolds: Array<{
      id: string;
      subjectType: string;
      subjectId: string;
      reason: string;
      placedAt: string;
    }>;
  };
  selectedAlliance: null | {
    id: string;
    name: string;
    features: Array<{
      key: string;
      enabled: boolean;
      configuration: Record<string, unknown> | null;
    }>;
    members: Array<{ id: string; name: string | null; email: string | null; status: string }>;
  };
  currentUserId: number;
  status: string | null;
}>();

const { t, formatDate, formatNumber } = useLocale();

const adminForm = useForm({ email: '' });
const provisionForm = useForm({
  owner_email: '',
  name: '',
  slug: '',
  kingdom: '',
  language: 'en',
  timezone: 'UTC',
});
const holdForm = useForm({ subject_type: 'alliance', subject_id: '', reason: '' });
const lifecycleForm = useForm({ reason: '' });
const ownershipForm = useForm({ membership_id: '' });
const featureForm = useForm({ feature_key: '', enabled: true });

const selected = computed(() =>
  props.selectedAlliance
    ? (props.platform.alliances.find((alliance) => alliance.id === props.selectedAlliance?.id) ??
      null)
    : null,
);

const planForm = useForm({ plan_code: selected.value?.plan ?? 'standard' });
const settingsForm = useForm({
  retention_days: selected.value?.retentionDays ?? 30,
  queue_partition: selected.value?.queuePartition ?? 'standard',
  api_access_enabled: selected.value?.apiAccessEnabled ?? true,
  webhooks_enabled: selected.value?.webhooksEnabled ?? true,
});

const fleetMetricKeys = [
  'alliances',
  'activeAlliances',
  'suspendedAlliances',
  'closedAlliances',
  'deletedAlliances',
] as const;

const queueMetricKeys = [
  'pendingOutbox',
  'pendingWebhooks',
  'failedWebhooks',
  'failedJobs',
  'defaultQueue',
  'notificationsQueue',
  'integrationsQueue',
  'maintenanceQueue',
] as const;

const metricLabels: Record<string, string> = {
  alliances: 'platformAdmin.metricAlliances',
  activeAlliances: 'platformAdmin.metricActiveAlliances',
  suspendedAlliances: 'platformAdmin.metricSuspendedAlliances',
  closedAlliances: 'platformAdmin.metricClosedAlliances',
  deletedAlliances: 'platformAdmin.metricDeletedAlliances',
  pendingOutbox: 'platformAdmin.metricPendingOutbox',
  pendingWebhooks: 'platformAdmin.metricPendingWebhooks',
  failedWebhooks: 'platformAdmin.metricFailedWebhooks',
  failedJobs: 'platformAdmin.metricFailedJobs',
  defaultQueue: 'platformAdmin.metricDefaultQueue',
  notificationsQueue: 'platformAdmin.metricNotificationsQueue',
  integrationsQueue: 'platformAdmin.metricIntegrationsQueue',
  maintenanceQueue: 'platformAdmin.metricMaintenanceQueue',
};

const localeRows = computed(() =>
  locales.map((locale) => ({
    ...locale,
    catalogueRegistered: hasMessageCatalogue(locale.code),
  })),
);

const rtlLocaleCount = computed(
  () => locales.filter((locale) => locale.direction === 'rtl').length,
);

const statusMessage = computed(() => {
  const messages: Record<string, string> = {
    'platform-administrator-granted': 'platformAdmin.statusAdministratorGranted',
    'platform-administrator-revoked': 'platformAdmin.statusAdministratorRevoked',
    'alliance-provisioned': 'platformAdmin.statusAllianceProvisioned',
    'alliance-lifecycle-updated': 'platformAdmin.statusLifecycleUpdated',
    'alliance-ownership-transferred': 'platformAdmin.statusOwnershipTransferred',
    'alliance-plan-updated': 'platformAdmin.statusPlanUpdated',
    'alliance-platform-settings-updated': 'platformAdmin.statusSettingsUpdated',
    'alliance-feature-updated': 'platformAdmin.statusFeatureUpdated',
    'legal-hold-placed': 'platformAdmin.statusLegalHoldPlaced',
    'legal-hold-released': 'platformAdmin.statusLegalHoldReleased',
    'alliance-usage-captured': 'platformAdmin.statusUsageCaptured',
  };

  return props.status ? t(messages[props.status] ?? props.status) : null;
});

function metricLabel(key: string): string {
  return t(metricLabels[key] ?? key);
}

function allianceStateLabel(state: string): string {
  const labels: Record<string, string> = {
    active: 'platformAdmin.statusActive',
    suspended: 'platformAdmin.statusSuspended',
    closed: 'platformAdmin.statusClosed',
    deleted: 'platformAdmin.statusDeleted',
  };

  return t(labels[state] ?? 'platformAdmin.statusUnknown');
}

function stateClass(state: string): string {
  if (state === 'active') return 'border-emerald-500/25 bg-emerald-500/10 text-emerald-200';
  if (state === 'suspended') return 'border-amber-500/25 bg-amber-500/10 text-amber-200';
  if (state === 'deleted') return 'border-red-500/25 bg-red-500/10 text-red-200';
  return 'border-[var(--ks-border)] bg-[var(--ks-surface-2)] text-[var(--ks-text-secondary)]';
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${formatNumber(bytes)} B`;
  const units = ['KB', 'MB', 'GB', 'TB'];
  let value = bytes / 1024;
  let unitIndex = 0;

  while (value >= 1024 && unitIndex < units.length - 1) {
    value /= 1024;
    unitIndex += 1;
  }

  return `${formatNumber(value, { maximumFractionDigits: 1 })} ${units[unitIndex]}`;
}

function lifecycle(operation: 'suspend' | 'close' | 'delete' | 'restore'): void {
  if (!props.selectedAlliance || lifecycleForm.reason.trim() === '') return;
  lifecycleForm.post(`/platform/alliances/${props.selectedAlliance.id}/lifecycle/${operation}`, {
    preserveScroll: true,
  });
}
</script>

<template>
  <Head :title="t('platformAdmin.title')" />

  <AppLayout :user="props.user">
    <header class="mb-8 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
      <div class="max-w-4xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('platformAdmin.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
          {{ t('platformAdmin.title') }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-muted)] sm:text-base">
          {{ t('platformAdmin.subtitle') }}
        </p>
      </div>
      <Link
        href="/dashboard"
        class="inline-flex w-fit items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-4 py-2.5 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-border-strong)] hover:text-[var(--ks-text)]"
      >
        {{ t('platformAdmin.backDashboard') }}
      </Link>
    </header>

    <div
      class="mb-6 rounded-[var(--ks-radius-md)] border border-[var(--ks-blue)]/25 bg-[var(--ks-blue-soft)] px-4 py-3 text-sm leading-6 text-[var(--ks-text-secondary)]"
    >
      {{ t('platformAdmin.accessBoundary') }}
    </div>

    <p
      v-if="statusMessage"
      role="status"
      class="mb-6 rounded-[var(--ks-radius-md)] border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200"
    >
      {{ statusMessage }}
    </p>

    <section aria-labelledby="capacity-heading" class="mb-8 space-y-4">
      <div>
        <h2 id="capacity-heading" class="ks-display text-2xl font-semibold">
          {{ t('platformAdmin.capacityTitle') }}
        </h2>
        <p class="mt-1 max-w-4xl text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('platformAdmin.capacityHelp') }}
        </p>
      </div>

      <div class="grid gap-5 xl:grid-cols-2">
        <div class="ks-surface p-5">
          <h3 class="text-sm font-bold tracking-[0.15em] text-[var(--ks-text-secondary)] uppercase">
            {{ t('platformAdmin.fleetFacts') }}
          </h3>
          <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div
              v-for="key in fleetMetricKeys"
              :key="key"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-4"
            >
              <p class="text-xs leading-5 text-[var(--ks-text-muted)]">{{ metricLabel(key) }}</p>
              <p class="mt-1 text-2xl font-bold">{{ formatNumber(platform.metrics[key] ?? 0) }}</p>
            </div>
          </div>
        </div>

        <div class="ks-surface p-5">
          <h3 class="text-sm font-bold tracking-[0.15em] text-[var(--ks-text-secondary)] uppercase">
            {{ t('platformAdmin.deliveryQueues') }}
          </h3>
          <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
              v-for="key in queueMetricKeys"
              :key="key"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-3"
            >
              <p class="text-[0.72rem] leading-5 text-[var(--ks-text-muted)]">
                {{ metricLabel(key) }}
              </p>
              <p class="mt-1 text-xl font-bold">{{ formatNumber(platform.metrics[key] ?? 0) }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="grid gap-6 2xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
      <section aria-labelledby="admins-heading" class="ks-surface p-5 sm:p-6">
        <div>
          <h2 id="admins-heading" class="ks-display text-2xl font-semibold">
            {{ t('platformAdmin.administrators') }}
          </h2>
          <p class="mt-1 text-sm leading-6 text-[var(--ks-text-muted)]">
            {{ t('platformAdmin.administratorHelp') }}
          </p>
        </div>

        <form
          class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end"
          @submit.prevent="adminForm.post('/platform/administrators')"
        >
          <label class="grid flex-1 gap-1.5 text-sm" for="platform-admin-email">
            {{ t('platformAdmin.userEmail') }}
            <input
              id="platform-admin-email"
              v-model="adminForm.email"
              type="email"
              required
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 focus:border-[var(--ks-blue)]"
            />
          </label>
          <button
            type="submit"
            class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2.5 font-bold text-slate-950 transition hover:bg-[var(--ks-gold-strong)] disabled:opacity-60"
            :disabled="adminForm.processing"
          >
            {{ t('platformAdmin.grantAdministrator') }}
          </button>
        </form>

        <div class="mt-5 hidden overflow-x-auto md:block">
          <table class="min-w-full text-start text-sm">
            <thead class="text-xs tracking-wide text-[var(--ks-text-muted)] uppercase">
              <tr>
                <th class="px-3 py-2 text-start">{{ t('platformAdmin.account') }}</th>
                <th class="px-3 py-2 text-start">{{ t('platformAdmin.mfa') }}</th>
                <th class="px-3 py-2 text-start">{{ t('platformAdmin.granted') }}</th>
                <th class="px-3 py-2 text-start">{{ t('platformAdmin.state') }}</th>
                <th class="px-3 py-2 text-start">{{ t('platformAdmin.action') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="admin in platform.administrators"
                :key="admin.id"
                class="border-t border-[var(--ks-border)]"
              >
                <td class="px-3 py-3">
                  <strong>{{ admin.name ?? '—' }}</strong>
                  <div class="text-xs text-[var(--ks-text-muted)]">{{ admin.email ?? '—' }}</div>
                </td>
                <td class="px-3 py-3">
                  {{ admin.mfaEnabled ? t('platformAdmin.enabled') : t('platformAdmin.required') }}
                </td>
                <td class="px-3 py-3">{{ formatDate(admin.grantedAt) }}</td>
                <td class="px-3 py-3">
                  {{ admin.revokedAt ? t('platformAdmin.revoked') : t('platformAdmin.active') }}
                </td>
                <td class="px-3 py-3">
                  <button
                    v-if="!admin.revokedAt && admin.userId !== currentUserId"
                    type="button"
                    class="rounded-[var(--ks-radius-sm)] border border-red-500/30 px-2.5 py-1.5 text-xs font-semibold text-red-200 transition hover:bg-red-500/10"
                    @click="router.delete(`/platform/administrators/${admin.id}`)"
                  >
                    {{ t('platformAdmin.revoke') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-5 grid gap-3 md:hidden">
          <article
            v-for="admin in platform.administrators"
            :key="admin.id"
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <h3 class="truncate font-semibold">{{ admin.name ?? '—' }}</h3>
                <p class="truncate text-xs text-[var(--ks-text-muted)]">{{ admin.email ?? '—' }}</p>
              </div>
              <span class="text-xs text-[var(--ks-text-secondary)]">
                {{ admin.revokedAt ? t('platformAdmin.revoked') : t('platformAdmin.active') }}
              </span>
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-3 text-xs">
              <div>
                <dt class="text-[var(--ks-text-muted)]">{{ t('platformAdmin.mfa') }}</dt>
                <dd class="mt-1">
                  {{ admin.mfaEnabled ? t('platformAdmin.enabled') : t('platformAdmin.required') }}
                </dd>
              </div>
              <div>
                <dt class="text-[var(--ks-text-muted)]">{{ t('platformAdmin.granted') }}</dt>
                <dd class="mt-1">{{ formatDate(admin.grantedAt) }}</dd>
              </div>
            </dl>
            <button
              v-if="!admin.revokedAt && admin.userId !== currentUserId"
              type="button"
              class="mt-3 rounded-[var(--ks-radius-sm)] border border-red-500/30 px-2.5 py-1.5 text-xs font-semibold text-red-200"
              @click="router.delete(`/platform/administrators/${admin.id}`)"
            >
              {{ t('platformAdmin.revoke') }}
            </button>
          </article>
        </div>
      </section>

      <section aria-labelledby="provision-heading" class="ks-surface p-5 sm:p-6">
        <h2 id="provision-heading" class="ks-display text-2xl font-semibold">
          {{ t('platformAdmin.provisionAlliance') }}
        </h2>
        <p class="mt-1 text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('platformAdmin.provisionHelp') }}
        </p>

        <form
          class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3"
          @submit.prevent="provisionForm.post('/platform/alliances')"
        >
          <label class="grid gap-1.5 text-sm" for="provision-owner">
            {{ t('platformAdmin.ownerEmail') }}
            <input
              id="provision-owner"
              v-model="provisionForm.owner_email"
              type="email"
              required
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
            />
          </label>
          <label class="grid gap-1.5 text-sm" for="provision-name">
            {{ t('platformAdmin.allianceName') }}
            <input
              id="provision-name"
              v-model="provisionForm.name"
              required
              maxlength="120"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
            />
          </label>
          <label class="grid gap-1.5 text-sm" for="provision-slug">
            {{ t('platformAdmin.slug') }}
            <input
              id="provision-slug"
              v-model="provisionForm.slug"
              required
              maxlength="120"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
            />
          </label>
          <label class="grid gap-1.5 text-sm" for="provision-kingdom">
            {{ t('platformAdmin.kingdom') }}
            <input
              id="provision-kingdom"
              v-model="provisionForm.kingdom"
              inputmode="numeric"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
            />
          </label>
          <label class="grid gap-1.5 text-sm" for="provision-language">
            {{ t('platformAdmin.language') }}
            <input
              id="provision-language"
              v-model="provisionForm.language"
              list="platform-locale-codes"
              required
              maxlength="16"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
            />
            <datalist id="platform-locale-codes">
              <option v-for="locale in locales" :key="locale.code" :value="locale.code">
                {{ locale.nativeName }}
              </option>
            </datalist>
          </label>
          <label class="grid gap-1.5 text-sm" for="provision-timezone">
            {{ t('platformAdmin.timezone') }}
            <input
              id="provision-timezone"
              v-model="provisionForm.timezone"
              required
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
            />
          </label>
          <div class="md:col-span-2 xl:col-span-3">
            <button
              type="submit"
              class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2.5 font-bold text-slate-950"
              :disabled="provisionForm.processing"
            >
              {{ t('platformAdmin.provision') }}
            </button>
          </div>
        </form>
      </section>
    </div>

    <section aria-labelledby="alliances-heading" class="mt-8 space-y-4">
      <div>
        <h2 id="alliances-heading" class="ks-display text-2xl font-semibold">
          {{ t('platformAdmin.allianceFleet') }}
        </h2>
        <p class="mt-1 max-w-4xl text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('platformAdmin.allianceFleetHelp') }}
        </p>
      </div>

      <div
        class="hidden overflow-x-auto rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] md:block"
      >
        <table class="min-w-full text-start text-sm">
          <thead
            class="bg-[var(--ks-surface-2)] text-xs tracking-wide text-[var(--ks-text-muted)] uppercase"
          >
            <tr>
              <th class="px-3 py-3 text-start">{{ t('platformAdmin.alliance') }}</th>
              <th class="px-3 py-3 text-start">{{ t('platformAdmin.status') }}</th>
              <th class="px-3 py-3 text-start">{{ t('platformAdmin.members') }}</th>
              <th class="px-3 py-3 text-start">{{ t('platformAdmin.storage') }}</th>
              <th class="px-3 py-3 text-start">{{ t('platformAdmin.integrations') }}</th>
              <th class="px-3 py-3 text-start">{{ t('platformAdmin.pendingOutbox') }}</th>
              <th class="px-3 py-3 text-start">{{ t('platformAdmin.plan') }}</th>
              <th class="px-3 py-3 text-start">{{ t('platformAdmin.operations') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="alliance in platform.alliances"
              :key="alliance.id"
              class="border-t border-[var(--ks-border)] align-top"
            >
              <td class="px-3 py-3">
                <strong>{{ alliance.name }}</strong>
                <div class="text-xs text-[var(--ks-text-muted)]">
                  {{ alliance.slug }} · {{ alliance.timezone }}
                </div>
              </td>
              <td class="px-3 py-3">
                <span
                  class="inline-flex rounded-full border px-2 py-1 text-xs font-semibold"
                  :class="stateClass(alliance.status)"
                  >{{ allianceStateLabel(alliance.status) }}</span
                >
              </td>
              <td class="px-3 py-3">{{ formatNumber(alliance.activeMembers) }}</td>
              <td class="px-3 py-3">{{ formatBytes(alliance.storageBytes) }}</td>
              <td class="px-3 py-3 text-xs leading-5">
                {{ formatNumber(alliance.apiCredentials) }} {{ t('platformAdmin.apiCredentials')
                }}<br />
                {{ formatNumber(alliance.webhooks) }} {{ t('platformAdmin.webhooks') }}
              </td>
              <td class="px-3 py-3">{{ formatNumber(alliance.pendingOutbox) }}</td>
              <td class="px-3 py-3">{{ alliance.plan }}</td>
              <td class="px-3 py-3">
                <div class="flex flex-wrap gap-2">
                  <Link
                    :href="`/platform?alliance=${alliance.id}`"
                    class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-2.5 py-1.5 text-xs font-semibold"
                    >{{ t('platformAdmin.manage') }}</Link
                  >
                  <a
                    :href="`/platform/alliances/${alliance.id}/export.json`"
                    class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-2.5 py-1.5 text-xs font-semibold"
                    >{{ t('platformAdmin.exportJson') }}</a
                  >
                  <button
                    type="button"
                    class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-2.5 py-1.5 text-xs font-semibold"
                    @click="router.post(`/platform/alliances/${alliance.id}/usage`)"
                  >
                    {{ t('platformAdmin.captureUsage') }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="grid gap-3 md:hidden">
        <article v-for="alliance in platform.alliances" :key="alliance.id" class="ks-surface p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h3 class="truncate font-semibold">{{ alliance.name }}</h3>
              <p class="truncate text-xs text-[var(--ks-text-muted)]">
                {{ alliance.slug }} · {{ alliance.timezone }}
              </p>
            </div>
            <span
              class="rounded-full border px-2 py-1 text-xs font-semibold"
              :class="stateClass(alliance.status)"
              >{{ allianceStateLabel(alliance.status) }}</span
            >
          </div>
          <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
            <div>
              <dt class="text-[var(--ks-text-muted)]">{{ t('platformAdmin.members') }}</dt>
              <dd class="mt-1 font-semibold">{{ formatNumber(alliance.activeMembers) }}</dd>
            </div>
            <div>
              <dt class="text-[var(--ks-text-muted)]">{{ t('platformAdmin.storage') }}</dt>
              <dd class="mt-1 font-semibold">{{ formatBytes(alliance.storageBytes) }}</dd>
            </div>
            <div>
              <dt class="text-[var(--ks-text-muted)]">{{ t('platformAdmin.pendingOutbox') }}</dt>
              <dd class="mt-1 font-semibold">{{ formatNumber(alliance.pendingOutbox) }}</dd>
            </div>
            <div>
              <dt class="text-[var(--ks-text-muted)]">{{ t('platformAdmin.plan') }}</dt>
              <dd class="mt-1 font-semibold">{{ alliance.plan }}</dd>
            </div>
          </dl>
          <div class="mt-4 flex flex-wrap gap-2">
            <Link
              :href="`/platform?alliance=${alliance.id}`"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-2.5 py-1.5 text-xs font-semibold"
              >{{ t('platformAdmin.manage') }}</Link
            >
            <a
              :href="`/platform/alliances/${alliance.id}/export.json`"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-2.5 py-1.5 text-xs font-semibold"
              >{{ t('platformAdmin.exportJson') }}</a
            >
            <button
              type="button"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-2.5 py-1.5 text-xs font-semibold"
              @click="router.post(`/platform/alliances/${alliance.id}/usage`)"
            >
              {{ t('platformAdmin.captureUsage') }}
            </button>
          </div>
        </article>
      </div>
    </section>

    <section
      v-if="selectedAlliance && selected"
      aria-labelledby="selected-heading"
      class="ks-surface mt-8 p-5 sm:p-6"
    >
      <div
        class="flex flex-col gap-3 border-b border-[var(--ks-border)] pb-5 lg:flex-row lg:items-start lg:justify-between"
      >
        <div>
          <h2 id="selected-heading" class="ks-display text-2xl font-semibold">
            {{ t('platformAdmin.manageAlliance', { alliance: selectedAlliance.name }) }}
          </h2>
          <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
            {{ t('platformAdmin.lifecycleState') }}:
            <strong class="text-[var(--ks-text-secondary)]">{{
              allianceStateLabel(selected.status)
            }}</strong>
          </p>
        </div>
        <div class="grid gap-1 text-xs text-[var(--ks-text-muted)] lg:text-end">
          <span v-if="selected.lifecycleReason"
            >{{ t('platformAdmin.lifecycleReason') }}: {{ selected.lifecycleReason }}</span
          >
          <span v-if="selected.retentionUntil"
            >{{ t('platformAdmin.retentionUntil') }}:
            {{ formatDate(selected.retentionUntil) }}</span
          >
        </div>
      </div>

      <div class="mt-6 grid gap-5 xl:grid-cols-2">
        <form
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-4"
          @submit.prevent="settingsForm.put(`/platform/alliances/${selectedAlliance.id}/settings`)"
        >
          <h3 class="font-semibold">{{ t('platformAdmin.operationalSettings') }}</h3>
          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="grid gap-1.5 text-sm" for="platform-retention"
              >{{ t('platformAdmin.retentionDays')
              }}<input
                id="platform-retention"
                v-model.number="settingsForm.retention_days"
                type="number"
                min="1"
                max="3650"
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
            /></label>
            <label class="grid gap-1.5 text-sm" for="platform-queue"
              >{{ t('platformAdmin.queuePartition')
              }}<select
                id="platform-queue"
                v-model="settingsForm.queue_partition"
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
              >
                <option>standard</option>
                <option>high-volume</option>
                <option>maintenance-sensitive</option>
              </select></label
            >
          </div>
          <label class="mt-4 flex items-center gap-2 text-sm"
            ><input v-model="settingsForm.api_access_enabled" type="checkbox" />{{
              t('platformAdmin.apiAccessEnabled')
            }}</label
          >
          <label class="mt-3 flex items-center gap-2 text-sm"
            ><input v-model="settingsForm.webhooks_enabled" type="checkbox" />{{
              t('platformAdmin.webhooksEnabled')
            }}</label
          >
          <button
            type="submit"
            class="mt-4 rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-3.5 py-2.5 font-bold text-slate-950"
          >
            {{ t('platformAdmin.saveSettings') }}
          </button>
        </form>

        <form
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-4"
          @submit.prevent="planForm.put(`/platform/alliances/${selectedAlliance.id}/plan`)"
        >
          <h3 class="font-semibold">{{ t('platformAdmin.planEntitlement') }}</h3>
          <label class="mt-4 grid gap-1.5 text-sm" for="platform-plan"
            >{{ t('platformAdmin.plan')
            }}<select
              id="platform-plan"
              v-model="planForm.plan_code"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
            >
              <option v-for="plan in platform.plans" :key="plan.code" :value="plan.code">
                {{ plan.name }} ({{ plan.code }})
              </option>
            </select></label
          >
          <div
            class="mt-4 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] p-3"
          >
            <p class="text-xs font-bold tracking-wide text-[var(--ks-text-muted)] uppercase">
              {{ t('platformAdmin.entitlements') }}
            </p>
            <ul
              v-if="
                Object.keys(
                  platform.plans.find((plan) => plan.code === planForm.plan_code)?.entitlements ??
                    {},
                ).length
              "
              class="mt-2 grid gap-1 text-xs text-[var(--ks-text-secondary)]"
            >
              <li
                v-for="(value, key) in platform.plans.find(
                  (plan) => plan.code === planForm.plan_code,
                )?.entitlements"
                :key="key"
                class="flex justify-between gap-4"
              >
                <span>{{ key }}</span
                ><strong>{{ formatNumber(value) }}</strong>
              </li>
            </ul>
            <p v-else class="mt-2 text-xs text-[var(--ks-text-muted)]">
              {{ t('platformAdmin.noEntitlements') }}
            </p>
          </div>
          <button
            type="submit"
            class="mt-4 rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-3.5 py-2.5 font-bold text-slate-950"
          >
            {{ t('platformAdmin.assignPlan') }}
          </button>
        </form>

        <form
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-4"
          @submit.prevent="
            ownershipForm.post(`/platform/alliances/${selectedAlliance.id}/ownership`)
          "
        >
          <h3 class="font-semibold">{{ t('platformAdmin.ownershipTransfer') }}</h3>
          <label class="mt-4 grid gap-1.5 text-sm" for="platform-owner"
            >{{ t('platformAdmin.newOwner')
            }}<select
              id="platform-owner"
              v-model="ownershipForm.membership_id"
              required
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
            >
              <option value="" disabled>{{ t('platformAdmin.selectActiveMember') }}</option>
              <option
                v-for="member in selectedAlliance.members.filter(
                  (item) => item.status === 'active',
                )"
                :key="member.id"
                :value="member.id"
              >
                {{ member.name }} — {{ member.email }}
              </option>
            </select></label
          >
          <button
            type="submit"
            class="mt-4 rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-3.5 py-2.5 font-bold text-slate-950"
          >
            {{ t('platformAdmin.transferOwnership') }}
          </button>
        </form>

        <form
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-4"
          @submit.prevent="featureForm.put(`/platform/alliances/${selectedAlliance.id}/features`)"
        >
          <h3 class="font-semibold">{{ t('platformAdmin.featureFlag') }}</h3>
          <label class="mt-4 grid gap-1.5 text-sm" for="platform-feature"
            >{{ t('platformAdmin.featureKey')
            }}<input
              id="platform-feature"
              v-model="featureForm.feature_key"
              required
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
          /></label>
          <label class="mt-3 flex items-center gap-2 text-sm"
            ><input v-model="featureForm.enabled" type="checkbox" />{{
              t('platformAdmin.enabled')
            }}</label
          >
          <button
            type="submit"
            class="mt-4 rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-3.5 py-2.5 font-bold text-slate-950"
          >
            {{ t('platformAdmin.setFeature') }}
          </button>
          <div class="mt-4 border-t border-[var(--ks-border)] pt-4">
            <p class="text-xs font-bold tracking-wide text-[var(--ks-text-muted)] uppercase">
              {{ t('platformAdmin.configuredFeatures') }}
            </p>
            <ul
              v-if="selectedAlliance.features.length"
              class="mt-2 grid gap-1 text-xs text-[var(--ks-text-secondary)]"
            >
              <li
                v-for="feature in selectedAlliance.features"
                :key="feature.key"
                class="flex justify-between gap-4"
              >
                <span>{{ feature.key }}</span
                ><strong>{{
                  feature.enabled ? t('platformAdmin.on') : t('platformAdmin.off')
                }}</strong>
              </li>
            </ul>
            <p v-else class="mt-2 text-xs text-[var(--ks-text-muted)]">
              {{ t('platformAdmin.noFeatures') }}
            </p>
          </div>
        </form>
      </div>

      <div class="mt-5 rounded-[var(--ks-radius-md)] border border-amber-500/35 bg-amber-500/5 p-4">
        <h3 class="font-semibold text-amber-100">{{ t('platformAdmin.lifecycleControls') }}</h3>
        <p class="mt-1 text-sm leading-6 text-amber-100/70">
          {{ t('platformAdmin.lifecycleHelp') }}
        </p>
        <label class="mt-4 grid gap-1.5 text-sm" for="platform-lifecycle-reason"
          >{{ t('platformAdmin.reason')
          }}<input
            id="platform-lifecycle-reason"
            v-model="lifecycleForm.reason"
            maxlength="500"
            class="rounded-[var(--ks-radius-sm)] border border-amber-500/25 bg-[var(--ks-bg)] px-3 py-2.5"
        /></label>
        <div class="mt-4 flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-[var(--ks-radius-sm)] border border-amber-500/30 px-3 py-2 text-sm font-semibold text-amber-100 disabled:opacity-40"
            :disabled="lifecycleForm.reason.trim() === ''"
            @click="lifecycle('suspend')"
          >
            {{ t('platformAdmin.suspend') }}
          </button>
          <button
            type="button"
            class="rounded-[var(--ks-radius-sm)] border border-amber-500/30 px-3 py-2 text-sm font-semibold text-amber-100 disabled:opacity-40"
            :disabled="lifecycleForm.reason.trim() === ''"
            @click="lifecycle('close')"
          >
            {{ t('platformAdmin.close') }}
          </button>
          <button
            type="button"
            class="rounded-[var(--ks-radius-sm)] border border-red-500/40 bg-red-500/5 px-3 py-2 text-sm font-semibold text-red-200 disabled:opacity-40"
            :disabled="lifecycleForm.reason.trim() === ''"
            @click="lifecycle('delete')"
          >
            {{ t('platformAdmin.deleteLogically') }}
          </button>
          <button
            type="button"
            class="rounded-[var(--ks-radius-sm)] border border-emerald-500/30 px-3 py-2 text-sm font-semibold text-emerald-200 disabled:opacity-40"
            :disabled="lifecycleForm.reason.trim() === ''"
            @click="lifecycle('restore')"
          >
            {{ t('platformAdmin.restore') }}
          </button>
        </div>
      </div>
    </section>

    <div class="mt-8 grid gap-6 2xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
      <section aria-labelledby="holds-heading" class="ks-surface p-5 sm:p-6">
        <h2 id="holds-heading" class="ks-display text-2xl font-semibold">
          {{ t('platformAdmin.legalHolds') }}
        </h2>
        <p class="mt-1 text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('platformAdmin.legalHoldsHelp') }}
        </p>
        <form class="mt-5 grid gap-4" @submit.prevent="holdForm.post('/platform/legal-holds')">
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="grid gap-1.5 text-sm" for="hold-subject-type"
              >{{ t('platformAdmin.subjectType')
              }}<select
                id="hold-subject-type"
                v-model="holdForm.subject_type"
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
              >
                <option value="alliance">{{ t('platformAdmin.alliance') }}</option>
                <option value="user">{{ t('platformAdmin.user') }}</option>
              </select></label
            >
            <label class="grid gap-1.5 text-sm" for="hold-subject-id"
              >{{ t('platformAdmin.subjectId')
              }}<input
                id="hold-subject-id"
                v-model="holdForm.subject_id"
                required
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
            /></label>
          </div>
          <label class="grid gap-1.5 text-sm" for="hold-reason"
            >{{ t('platformAdmin.reason')
            }}<input
              id="hold-reason"
              v-model="holdForm.reason"
              required
              maxlength="1000"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
          /></label>
          <button
            type="submit"
            class="w-fit rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-3.5 py-2.5 font-bold text-slate-950"
          >
            {{ t('platformAdmin.placeHold') }}
          </button>
        </form>
        <ul v-if="platform.legalHolds.length" class="mt-5 grid gap-3 text-sm">
          <li
            v-for="hold in platform.legalHolds"
            :key="hold.id"
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-3"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <strong>{{ hold.subjectType }} {{ hold.subjectId }}</strong>
                <p class="mt-1 text-xs leading-5 text-[var(--ks-text-muted)]">{{ hold.reason }}</p>
                <p class="mt-1 text-[0.7rem] text-[var(--ks-text-muted)]">
                  {{ formatDate(hold.placedAt) }}
                </p>
              </div>
              <button
                type="button"
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-2.5 py-1.5 text-xs font-semibold"
                @click="router.delete(`/platform/legal-holds/${hold.id}`)"
              >
                {{ t('platformAdmin.release') }}
              </button>
            </div>
          </li>
        </ul>
        <p v-else class="mt-5 text-sm text-[var(--ks-text-muted)]">
          {{ t('platformAdmin.noLegalHolds') }}
        </p>
      </section>

      <section aria-labelledby="localization-heading" class="ks-surface p-5 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <h2 id="localization-heading" class="ks-display text-2xl font-semibold">
              {{ t('platformAdmin.localizationRuntime') }}
            </h2>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-[var(--ks-text-muted)]">
              {{ t('platformAdmin.localizationRuntimeHelp') }}
            </p>
          </div>
          <dl class="grid grid-cols-3 gap-2 text-center text-xs">
            <div
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-3"
            >
              <dt class="text-[var(--ks-text-muted)]">
                {{ t('platformAdmin.registeredLocales') }}
              </dt>
              <dd class="mt-1 text-lg font-bold">{{ formatNumber(localeRows.length) }}</dd>
            </div>
            <div
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-3"
            >
              <dt class="text-[var(--ks-text-muted)]">{{ t('platformAdmin.defaultLocale') }}</dt>
              <dd class="mt-1 text-lg font-bold">{{ defaultLocale }}</dd>
            </div>
            <div
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-3"
            >
              <dt class="text-[var(--ks-text-muted)]">{{ t('platformAdmin.rtlLocales') }}</dt>
              <dd class="mt-1 text-lg font-bold">{{ formatNumber(rtlLocaleCount) }}</dd>
            </div>
          </dl>
        </div>

        <div class="mt-5 hidden overflow-x-auto md:block">
          <table class="min-w-full text-start text-sm">
            <thead class="text-xs tracking-wide text-[var(--ks-text-muted)] uppercase">
              <tr>
                <th class="px-3 py-2 text-start">{{ t('platformAdmin.locale') }}</th>
                <th class="px-3 py-2 text-start">{{ t('platformAdmin.nativeName') }}</th>
                <th class="px-3 py-2 text-start">{{ t('platformAdmin.englishName') }}</th>
                <th class="px-3 py-2 text-start">{{ t('platformAdmin.direction') }}</th>
                <th class="px-3 py-2 text-start">{{ t('platformAdmin.catalogue') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="locale in localeRows"
                :key="locale.code"
                class="border-t border-[var(--ks-border)]"
              >
                <td class="px-3 py-3 font-mono text-xs">
                  {{ locale.code }}
                  <span
                    v-if="locale.code === defaultLocale"
                    class="ms-1 rounded bg-[var(--ks-gold)]/15 px-1.5 py-0.5 font-sans text-[var(--ks-gold)]"
                    >{{ t('platformAdmin.default') }}</span
                  >
                </td>
                <td class="px-3 py-3 font-semibold">{{ locale.nativeName }}</td>
                <td class="px-3 py-3 text-[var(--ks-text-secondary)]">{{ locale.englishName }}</td>
                <td class="px-3 py-3">
                  {{ locale.direction === 'rtl' ? t('platformAdmin.rtl') : t('platformAdmin.ltr') }}
                </td>
                <td class="px-3 py-3">
                  <span :class="locale.catalogueRegistered ? 'text-emerald-200' : 'text-red-200'">{{
                    locale.catalogueRegistered
                      ? t('platformAdmin.registered')
                      : t('platformAdmin.missing')
                  }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 md:hidden">
          <article
            v-for="locale in localeRows"
            :key="locale.code"
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-3"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="font-semibold">{{ locale.nativeName }}</h3>
                <p class="text-xs text-[var(--ks-text-muted)]">
                  {{ locale.englishName }} · {{ locale.code }}
                </p>
              </div>
              <span class="text-xs text-[var(--ks-text-secondary)]">{{
                locale.direction === 'rtl' ? t('platformAdmin.rtl') : t('platformAdmin.ltr')
              }}</span>
            </div>
            <p
              class="mt-3 text-xs"
              :class="locale.catalogueRegistered ? 'text-emerald-200' : 'text-red-200'"
            >
              {{
                locale.catalogueRegistered
                  ? t('platformAdmin.registered')
                  : t('platformAdmin.missing')
              }}
            </p>
          </article>
        </div>

        <p
          class="mt-5 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-xs leading-5 text-[var(--ks-text-muted)]"
        >
          {{ t('platformAdmin.localizationBoundary') }}
        </p>
      </section>
    </div>
  </AppLayout>
</template>
