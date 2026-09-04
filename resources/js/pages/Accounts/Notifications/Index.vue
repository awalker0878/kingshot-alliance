<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type ChannelValue = 'in_app' | 'discord' | 'telegram' | 'web_push' | 'email';
type StoredChannel = 'discord' | 'telegram' | 'web_push';
type Channel = {
  value: ChannelValue;
  label: string;
  external: boolean;
  storedEndpoint: boolean;
};
type Endpoint = {
  id: string;
  channel: StoredChannel;
  label: string;
  enabled: boolean;
  healthStatus: 'never_tested' | 'healthy' | 'degraded' | 'paused';
  lastVerifiedAt: string | null;
  lastSuccessfulDeliveryAt: string | null;
  lastFailedDeliveryAt: string | null;
  consecutiveFailures: number;
  lastError: string | null;
};
type DeliveryRoute = {
  id: string;
  channel: ChannelValue;
  status: string;
  targetLabel: string | null;
  digestCadence: string;
  routingReason: string | null;
  dueAt: string;
  sentAt: string | null;
  failedAt: string | null;
  nextAttemptAt: string | null;
  attemptCount: number;
  maxAttempts: number;
  lastError: string | null;
};
type NotificationMessage = {
  id: string;
  type: string;
  title: string;
  body: string | null;
  actionUrl: string | null;
  urgency: string;
  scope: 'account' | 'governor';
  playerId: string | null;
  availableAt: string;
  createdAt: string | null;
  readAt: string | null;
  archivedAt: string | null;
  deliverySummary: { total: number; statuses: Record<string, number> };
  deliveries: DeliveryRoute[];
};
type Inbox = { items: NotificationMessage[]; nextCursor: string | null; hasMore: boolean };
type InboxFilters = {
  view?: 'all' | 'unread' | 'archived';
  type?: string | null;
  scope?: 'all' | 'account' | 'governor';
  delivery_status?: string | null;
  date_from?: string | null;
  date_to?: string | null;
  cursor?: string | null;
  limit?: number;
};
type BulkOperation = 'mark_read' | 'mark_unread' | 'archive' | 'restore';
type NotificationBulkPreview = {
  operation: BulkOperation;
  items: Array<{
    itemId: string;
    label: string;
    fromStatus: string | null;
    outcome: 'ready' | 'blocked' | 'skipped';
    code: string;
  }>;
  ready: number;
  blocked: number;
  readyItemIds: string[];
};
type NotificationBulkResult = {
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
type RoutingPolicy = {
  timezone: string;
  quietHoursEnabled: boolean;
  quietHoursStart: string | null;
  quietHoursEnd: string | null;
  allowUrgentDuringQuietHours: boolean;
  mutedUntil: string | null;
  digestCadence: string;
  dailyDigestTime: string;
  digestUrgent: boolean;
};
type RoutingForm = {
  timezone: string;
  quiet_hours_enabled: boolean;
  quiet_hours_start: string;
  quiet_hours_end: string;
  allow_urgent_during_quiet_hours: boolean;
  muted_until: string;
  digest_cadence: string;
  daily_digest_time: string;
  digest_urgent: boolean;
};

const props = defineProps<{
  user: { name: string; email: string };
  player: { id: string; name: string } | null;
  inbox: Inbox;
  inboxFilters: InboxFilters;
  endpoints: Endpoint[];
  preferences: Record<string, boolean>;
  routingPolicies: Record<string, RoutingPolicy>;
  routingDefaults: { timezone: string; digestCadence: string; dailyDigestTime: string };
  notificationTypes: string[];
  channels: Channel[];
  deliveryStatuses: string[];
  digestCadences: string[];
  webPushPublicKey: string | null;
  notificationBulkPreview: NotificationBulkPreview | null;
  notificationBulkResult: NotificationBulkResult | null;
}>();

const { formatDate, t } = useLocale();
const filterView = ref<InboxFilters['view']>(props.inboxFilters.view ?? 'all');
const filterType = ref(props.inboxFilters.type ?? '');
const filterScope = ref<InboxFilters['scope']>(props.inboxFilters.scope ?? 'all');
const filterStatus = ref(props.inboxFilters.delivery_status ?? '');
const filterDateFrom = ref(props.inboxFilters.date_from ?? '');
const filterDateTo = ref(props.inboxFilters.date_to ?? '');
const selectedChannel = ref<'discord' | 'telegram'>('discord');
const endpointForm = useForm({
  channel: selectedChannel.value,
  label: '',
  webhook_url: '',
  bot_token: '',
  chat_id: '',
  endpoint: '',
  p256dh: '',
  auth: '',
});
const webPushBusy = ref(false);
const webPushError = ref<string | null>(null);
const endpointToRemove = ref<Endpoint | null>(null);
const selectedMessageIds = ref<string[]>(props.notificationBulkResult?.failedItemIds ?? []);
const bulkOperation = ref<BulkOperation>('mark_read');
const bulkBusy = ref(false);
const bulkConfirmationOpen = ref(false);
const preferenceScope = ref<'account' | 'governor'>('account');

function policyForm(policy: RoutingPolicy | undefined): RoutingForm {
  return {
    timezone: policy?.timezone ?? props.routingDefaults.timezone,
    quiet_hours_enabled: policy?.quietHoursEnabled ?? false,
    quiet_hours_start: policy?.quietHoursStart ?? '22:00',
    quiet_hours_end: policy?.quietHoursEnd ?? '07:00',
    allow_urgent_during_quiet_hours: policy?.allowUrgentDuringQuietHours ?? false,
    muted_until: policy?.mutedUntil ? policy.mutedUntil.slice(0, 16) : '',
    digest_cadence: policy?.digestCadence ?? props.routingDefaults.digestCadence,
    daily_digest_time: policy?.dailyDigestTime ?? props.routingDefaults.dailyDigestTime,
    digest_urgent: policy?.digestUrgent ?? false,
  };
}

const accountRouting = ref<RoutingForm>(policyForm(props.routingPolicies.account));
const governorRouting = ref<RoutingForm>(
  policyForm(props.player ? props.routingPolicies[props.player.id] : undefined),
);
const governorHasRoutingOverride = computed(
  () => props.player !== null && props.routingPolicies[props.player.id] !== undefined,
);
const visibleMessages = computed(() => props.inbox.items);
const unread = computed(() => visibleMessages.value.filter((message) => message.readAt === null).length);
const failures = computed(() =>
  visibleMessages.value.reduce(
    (count, message) => count + message.deliveries.filter((route) => route.status === 'failed').length,
    0,
  ),
);
const selectableMessageIds = computed(() => visibleMessages.value.slice(0, 50).map(({ id }) => id));
const allVisibleSelected = computed(
  () =>
    selectableMessageIds.value.length > 0 &&
    selectableMessageIds.value.every((id) => selectedMessageIds.value.includes(id)),
);
const bulkPreviewMatchesSelection = computed(() => {
  const preview = props.notificationBulkPreview;
  if (!preview || preview.operation !== bulkOperation.value) return false;
  const selected = [...selectedMessageIds.value].sort();
  const previewed = preview.items.map((item) => item.itemId).sort();
  return selected.length === previewed.length && selected.every((id, index) => id === previewed[index]);
});
const canEnableWebPush = computed(
  () =>
    props.player !== null &&
    props.webPushPublicKey !== null &&
    props.webPushPublicKey !== '' &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window,
);

watch(
  () => props.notificationBulkResult,
  (result) => {
    if (result) selectedMessageIds.value = [...result.failedItemIds];
  },
);

function currentFilters(cursor: string | null = null): Record<string, string | number> {
  const query: Record<string, string | number> = { view: filterView.value ?? 'all', scope: filterScope.value ?? 'all', limit: 25 };
  if (filterType.value) query.type = filterType.value;
  if (filterStatus.value) query.delivery_status = filterStatus.value;
  if (filterDateFrom.value) query.date_from = filterDateFrom.value;
  if (filterDateTo.value) query.date_to = filterDateTo.value;
  if (cursor) query.cursor = cursor;
  return query;
}
function applyFilters(): void {
  router.get('/notifications', currentFilters(), { preserveScroll: true, preserveState: true });
}
function clearFilters(): void {
  filterView.value = 'all';
  filterType.value = '';
  filterScope.value = 'all';
  filterStatus.value = '';
  filterDateFrom.value = '';
  filterDateTo.value = '';
  router.get('/notifications', {}, { preserveScroll: true });
}
function loadOlder(): void {
  if (!props.inbox.nextCursor) return;
  router.get('/notifications', currentFilters(props.inbox.nextCursor), { preserveScroll: true });
}
function saveEndpoint(): void {
  endpointForm.channel = selectedChannel.value;
  endpointForm.put('/notifications/endpoints', {
    preserveScroll: true,
    onSuccess: () => endpointForm.reset('label', 'webhook_url', 'bot_token', 'chat_id'),
  });
}
function setEndpointState(endpoint: Endpoint, enabled: boolean): void {
  router.patch(`/notifications/endpoints/${endpoint.id}/state`, { enabled }, { preserveScroll: true });
}
function testEndpoint(endpoint: Endpoint): void {
  router.post(`/notifications/endpoints/${endpoint.id}/test`, {}, { preserveScroll: true });
}
function reverifyEndpoint(endpoint: Endpoint): void {
  router.post(`/notifications/endpoints/${endpoint.id}/reverify`, {}, { preserveScroll: true });
}
function removeEndpoint(): void {
  if (!endpointToRemove.value) return;
  router.delete(`/notifications/endpoints/${endpointToRemove.value.id}`, {
    preserveScroll: true,
    onFinish: () => (endpointToRemove.value = null),
  });
}
function preferenceKey(scope: string, type: string, channel: ChannelValue): string {
  return `${scope}:${type}:${channel}`;
}
function defaultPreference(channel: ChannelValue): boolean {
  return channel !== 'email';
}
function accountPreference(type: string, channel: ChannelValue): boolean {
  return props.preferences[preferenceKey('account', type, channel)] ?? defaultPreference(channel);
}
function governorOverride(type: string, channel: ChannelValue): boolean | undefined {
  if (!props.player) return undefined;
  return props.preferences[preferenceKey(props.player.id, type, channel)];
}
function governorPreference(type: string, channel: ChannelValue): boolean {
  return governorOverride(type, channel) ?? accountPreference(type, channel);
}
function setPreference(scope: 'account' | 'governor', type: string, channel: ChannelValue, enabled: boolean): void {
  router.put(
    '/notifications/preferences',
    { notification_type: type, channel, enabled, scope },
    { preserveScroll: true },
  );
}
function resetPreference(type: string, channel: ChannelValue): void {
  router.delete('/notifications/preferences', {
    data: { notification_type: type, channel },
    preserveScroll: true,
  });
}
function setMessageState(message: NotificationMessage, operation: BulkOperation): void {
  const path = operation === 'mark_read' ? 'read' : operation === 'mark_unread' ? 'unread' : operation;
  router.put(`/notifications/${message.id}/${path}`, {}, { preserveScroll: true });
}
function typeLabel(type: string): string {
  const labels: Record<string, string> = {
    'account.security': t('notifications.types.accountSecurity'),
    'alliance.announcement': t('notifications.types.allianceAnnouncement'),
    'event.reminder': t('notifications.types.eventReminder'),
    'gift_code.expiring': t('notifications.types.giftCodeExpiring'),
    'gift_code.available': t('notifications.types.giftCodeAvailable'),
    'gift_code.trust_changed': t('notifications.types.giftCodeTrustChanged'),
    'intelligence.change': t('notifications.types.intelligenceChange'),
    'king_perks.reminder': t('notifications.types.kingPerkReminder'),
    'officer.brief': t('notifications.types.officerBrief'),
  };
  return labels[type] ?? type;
}
function channelLabel(channel: string): string {
  return t(`notifications.channels.${channel}`);
}
function statusLabel(status: string): string {
  return t(`notifications.statuses.${status}`);
}
function healthLabel(status: Endpoint['healthStatus']): string {
  return t(`notifications.healthStatuses.${status}`);
}
function messageSelected(messageId: string): boolean {
  return selectedMessageIds.value.includes(messageId);
}
function setMessageSelected(messageId: string, selected: boolean): void {
  if (!selected) {
    selectedMessageIds.value = selectedMessageIds.value.filter((id) => id !== messageId);
    return;
  }
  selectedMessageIds.value = [...new Set([...selectedMessageIds.value, messageId])].slice(0, 50);
}
function toggleVisibleSelection(): void {
  if (allVisibleSelected.value) {
    const visible = new Set(selectableMessageIds.value);
    selectedMessageIds.value = selectedMessageIds.value.filter((id) => !visible.has(id));
    return;
  }
  selectedMessageIds.value = [...new Set([...selectedMessageIds.value, ...selectableMessageIds.value])].slice(0, 50);
}
function previewBulkUpdate(): void {
  if (selectedMessageIds.value.length === 0 || bulkBusy.value) return;
  bulkBusy.value = true;
  router.post(
    '/notifications/bulk/preview',
    { message_ids: selectedMessageIds.value, operation: bulkOperation.value },
    { preserveScroll: true, preserveState: true, onFinish: () => (bulkBusy.value = false) },
  );
}
function commitBulkUpdate(): void {
  if (!props.notificationBulkPreview || !bulkPreviewMatchesSelection.value || bulkBusy.value) return;
  bulkBusy.value = true;
  router.put(
    '/notifications/bulk',
    { message_ids: props.notificationBulkPreview.items.map((item) => item.itemId), operation: bulkOperation.value },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        bulkBusy.value = false;
        bulkConfirmationOpen.value = false;
      },
    },
  );
}
function bulkOutcomeLabel(code: string): string {
  return t(`notifications.bulk.outcomes.${code}`);
}
function saveRoutingPolicy(scope: 'account' | 'governor'): void {
  const form = scope === 'account' ? accountRouting.value : governorRouting.value;
  router.put('/notifications/routing-policy', { scope, ...form }, { preserveScroll: true });
}
function resetRoutingPolicy(): void {
  router.delete('/notifications/routing-policy', { preserveScroll: true });
}
function decodeVapidPublicKey(value: string): Uint8Array {
  const padding = '='.repeat((4 - (value.length % 4)) % 4);
  const decoded = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
  return Uint8Array.from(decoded, (character) => character.charCodeAt(0));
}
async function enableWebPush(): Promise<void> {
  if (!canEnableWebPush.value || !props.webPushPublicKey || webPushBusy.value) return;
  webPushBusy.value = true;
  webPushError.value = null;
  try {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      webPushError.value = t('notifications.webPushDenied');
      return;
    }
    const registration = await navigator.serviceWorker.ready;
    const existing = await registration.pushManager.getSubscription();
    const subscription =
      existing ??
      (await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: decodeVapidPublicKey(props.webPushPublicKey),
      }));
    const json = subscription.toJSON();
    const p256dh = json.keys?.p256dh;
    const auth = json.keys?.auth;
    if (!p256dh || !auth) throw new Error('subscription-key-material');
    router.put(
      '/notifications/endpoints',
      {
        channel: 'web_push',
        label: t('notifications.webPushLabel'),
        endpoint: subscription.endpoint,
        p256dh,
        auth,
      },
      { preserveScroll: true },
    );
  } catch {
    webPushError.value = t('notifications.webPushFailed');
  } finally {
    webPushBusy.value = false;
  }
}
</script>

<template>
  <Head :title="t('notifications.title')" />
  <AppLayout :user="props.user">
    <RoomBanner
      :eyebrow="t('notifications.eyebrow')"
      :title="t('notifications.title')"
      :subtitle="t('notifications.subtitle')"
      image="/images/kingshot/v4/event-command.svg"
    />

    <section class="mt-4 grid gap-3 sm:grid-cols-3">
      <StatSeal :label="t('notifications.unread')" :value="unread" icon="✦" tone="teal" />
      <StatSeal :label="t('notifications.externalChannels')" :value="props.endpoints.length" icon="↗" />
      <StatSeal :label="t('notifications.needsAttention')" :value="failures" icon="!" tone="stone" />
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.3fr)_minmax(24rem,.7fr)]">
      <section class="ks-surface overflow-hidden" aria-labelledby="notification-inbox-title">
        <div class="p-4 sm:p-5">
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('notifications.latestActivity') }}</p>
              <h2 id="notification-inbox-title" class="ks-display mt-1 text-2xl font-semibold">{{ t('notifications.inbox') }}</h2>
            </div>
            <span class="ks-status" data-tone="info">{{ t('notifications.inboxCount', { count: visibleMessages.length }) }}</span>
          </div>

          <form class="mt-4 grid gap-3 md:grid-cols-3" @submit.prevent="applyFilters">
            <label class="text-sm">
              <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.view') }}</span>
              <select v-model="filterView" class="ks-input mt-1 w-full">
                <option value="all">{{ t('notifications.views.all') }}</option>
                <option value="unread">{{ t('notifications.views.unread') }}</option>
                <option value="archived">{{ t('notifications.views.archived') }}</option>
              </select>
            </label>
            <label class="text-sm">
              <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.type') }}</span>
              <select v-model="filterType" class="ks-input mt-1 w-full">
                <option value="">{{ t('notifications.allTypes') }}</option>
                <option v-for="type in props.notificationTypes" :key="type" :value="type">{{ typeLabel(type) }}</option>
              </select>
            </label>
            <label class="text-sm">
              <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.scope') }}</span>
              <select v-model="filterScope" class="ks-input mt-1 w-full">
                <option value="all">{{ t('notifications.scopes.all') }}</option>
                <option value="account">{{ t('notifications.scopes.account') }}</option>
                <option value="governor" :disabled="!props.player">{{ t('notifications.scopes.governor') }}</option>
              </select>
            </label>
            <label class="text-sm">
              <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.deliveryStatus') }}</span>
              <select v-model="filterStatus" class="ks-input mt-1 w-full">
                <option value="">{{ t('notifications.allStatuses') }}</option>
                <option v-for="status in props.deliveryStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
              </select>
            </label>
            <label class="text-sm">
              <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.dateFrom') }}</span>
              <input v-model="filterDateFrom" type="date" class="ks-input mt-1 w-full" />
            </label>
            <label class="text-sm">
              <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.dateTo') }}</span>
              <input v-model="filterDateTo" type="date" class="ks-input mt-1 w-full" />
            </label>
            <div class="flex flex-wrap gap-2 md:col-span-3">
              <AppButton type="submit">{{ t('notifications.applyFilters') }}</AppButton>
              <AppButton variant="secondary" type="button" @click="clearFilters">{{ t('notifications.clearFilters') }}</AppButton>
              <button
                v-if="visibleMessages.length"
                type="button"
                class="ks-chip"
                :aria-pressed="allVisibleSelected"
                :data-active="allVisibleSelected"
                @click="toggleVisibleSelection"
              >
                {{ allVisibleSelected ? t('notifications.bulk.clearSelection') : t('notifications.bulk.selectVisible') }}
              </button>
            </div>
          </form>
        </div>

        <div v-if="selectedMessageIds.length" class="border-t border-[var(--ks-border)] bg-[var(--ks-teal-soft)] p-4 sm:p-5">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[14rem] flex-1">
              <p class="font-semibold">{{ t('notifications.bulk.selected', { count: selectedMessageIds.length }) }}</p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">{{ t('notifications.bulk.help') }}</p>
            </div>
            <label class="text-sm">
              <span class="sr-only">{{ t('notifications.bulk.operation') }}</span>
              <select v-model="bulkOperation" class="ks-input min-h-11">
                <option value="mark_read">{{ t('notifications.bulk.markRead') }}</option>
                <option value="mark_unread">{{ t('notifications.bulk.markUnread') }}</option>
                <option value="archive">{{ t('notifications.bulk.archive') }}</option>
                <option value="restore">{{ t('notifications.bulk.restore') }}</option>
              </select>
            </label>
            <AppButton :busy="bulkBusy" :busy-label="t('notifications.bulk.previewing')" @click="previewBulkUpdate">{{ t('notifications.bulk.preview') }}</AppButton>
          </div>
        </div>

        <div v-if="bulkPreviewMatchesSelection && notificationBulkPreview" class="border-t border-[var(--ks-border)] p-4 sm:p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('notifications.bulk.previewTitle') }}</p>
              <p class="mt-1 font-semibold">{{ t('notifications.bulk.previewSummary', { ready: notificationBulkPreview.ready, blocked: notificationBulkPreview.blocked }) }}</p>
            </div>
            <AppButton :disabled="notificationBulkPreview.ready === 0" @click="bulkConfirmationOpen = true">{{ t('notifications.bulk.confirm') }}</AppButton>
          </div>
          <ul class="mt-4 grid gap-2 md:grid-cols-2">
            <li v-for="item in notificationBulkPreview.items" :key="item.itemId" class="flex items-center justify-between gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 px-3 py-2 text-sm">
              <span class="truncate">{{ item.label }}</span>
              <span class="ks-status" :data-tone="item.outcome === 'ready' ? 'success' : item.outcome === 'skipped' ? 'warning' : 'danger'">{{ bulkOutcomeLabel(item.code) }}</span>
            </li>
          </ul>
        </div>

        <div v-if="notificationBulkResult" class="border-t border-[var(--ks-border)] p-4 sm:p-5" aria-labelledby="notification-bulk-result-title">
          <p class="ks-kicker">{{ t('notifications.bulk.resultTitle') }}</p>
          <h3 id="notification-bulk-result-title" class="mt-1 text-lg font-semibold">{{ t('notifications.bulk.resultSummary', { succeeded: notificationBulkResult.succeeded, failed: notificationBulkResult.failed, skipped: notificationBulkResult.skipped }) }}</h3>
          <ul class="mt-4 grid gap-2 md:grid-cols-2">
            <li v-for="item in notificationBulkResult.items" :key="item.itemId" class="flex items-center justify-between gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 px-3 py-2 text-sm">
              <span class="truncate">{{ item.label }}</span>
              <span class="ks-status" :data-tone="item.outcome === 'succeeded' ? 'success' : item.outcome === 'skipped' ? 'warning' : 'danger'">{{ bulkOutcomeLabel(item.code) }}</span>
            </li>
          </ul>
          <p v-if="notificationBulkResult.failed" class="mt-3 text-xs text-[var(--ks-muted)]">{{ t('notifications.bulk.failedSelected') }}</p>
        </div>

        <div v-if="visibleMessages.length" class="divide-y divide-[var(--ks-border)] px-4 sm:px-5">
          <article v-for="message in visibleMessages" :key="message.id" class="grid gap-3 py-5 sm:grid-cols-[auto_minmax(0,1fr)_auto]" :class="message.readAt === null ? 'text-[var(--ks-text)]' : 'opacity-80'">
            <div class="pt-1">
              <input type="checkbox" :checked="messageSelected(message.id)" :aria-label="t('notifications.bulk.selectNotification', { title: message.title })" @change="setMessageSelected(message.id, ($event.target as HTMLInputElement).checked)" />
            </div>
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <span class="ks-status" data-tone="info">{{ typeLabel(message.type) }}</span>
                <span class="ks-status">{{ message.scope === 'account' ? t('notifications.scopeAccount') : t('notifications.scopeGovernor') }}</span>
                <span v-if="message.readAt === null" class="h-2 w-2 rounded-full bg-cyan-300" :title="t('notifications.unread')" />
              </div>
              <h3 class="mt-2 text-base font-semibold">{{ message.title }}</h3>
              <p v-if="message.body" class="mt-1 text-sm text-[var(--ks-text-secondary)]">{{ message.body }}</p>
              <p class="mt-2 text-xs text-[var(--ks-muted)]">{{ formatDate(message.createdAt || message.availableAt) }}</p>

              <details class="mt-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/10 p-3">
                <summary class="cursor-pointer text-sm font-medium">{{ t('notifications.deliveryRoutes', { count: message.deliverySummary.total }) }}</summary>
                <p v-if="message.deliveries.length === 0" class="mt-2 text-xs text-[var(--ks-muted)]">{{ t('notifications.noDeliveryRoutes') }}</p>
                <ul v-else class="mt-3 space-y-2">
                  <li v-for="delivery in message.deliveries" :key="delivery.id" class="rounded border border-[var(--ks-border)] p-2 text-xs">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="ks-status" :data-tone="delivery.status === 'failed' ? 'danger' : delivery.status === 'sent' ? 'success' : 'info'">{{ channelLabel(delivery.channel) }} · {{ statusLabel(delivery.status) }}</span>
                      <span v-if="delivery.targetLabel" class="text-[var(--ks-text-secondary)]">{{ delivery.targetLabel }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[var(--ks-muted)]">
                      <span>{{ t('notifications.attempts', { attempts: delivery.attemptCount, max: delivery.maxAttempts }) }}</span>
                      <span>{{ t('notifications.dueAt', { date: formatDate(delivery.dueAt) }) }}</span>
                      <span v-if="delivery.sentAt">{{ t('notifications.sentAt', { date: formatDate(delivery.sentAt) }) }}</span>
                      <span v-if="delivery.failedAt">{{ t('notifications.failedAt', { date: formatDate(delivery.failedAt) }) }}</span>
                      <span v-if="delivery.nextAttemptAt">{{ t('notifications.retryAt', { date: formatDate(delivery.nextAttemptAt) }) }}</span>
                    </div>
                    <p v-if="delivery.routingReason" class="mt-1 text-[var(--ks-muted)]">{{ t('notifications.routingReason', { reason: delivery.routingReason }) }}</p>
                    <p v-if="delivery.lastError" class="mt-1 text-rose-300">{{ delivery.lastError }}</p>
                  </li>
                </ul>
              </details>
            </div>
            <div class="flex flex-wrap items-start gap-2 sm:justify-end">
              <Link v-if="message.actionUrl" :href="message.actionUrl" class="ks-command-link" data-variant="secondary">{{ t('notifications.open') }}</Link>
              <button v-if="message.readAt === null" type="button" class="ks-chip" @click="setMessageState(message, 'mark_read')">{{ t('notifications.markRead') }}</button>
              <button v-else type="button" class="ks-chip" @click="setMessageState(message, 'mark_unread')">{{ t('notifications.markUnread') }}</button>
              <button v-if="message.archivedAt === null" type="button" class="ks-chip" @click="setMessageState(message, 'archive')">{{ t('notifications.archive') }}</button>
              <button v-else type="button" class="ks-chip" @click="setMessageState(message, 'restore')">{{ t('notifications.restore') }}</button>
            </div>
          </article>
        </div>
        <div v-else class="ks-fantasy-empty m-4 sm:m-5">{{ t('notifications.empty') }}</div>
        <div class="flex justify-center border-t border-[var(--ks-border)] p-4">
          <AppButton v-if="props.inbox.hasMore && props.inbox.nextCursor" variant="secondary" @click="loadOlder">{{ t('notifications.loadMore') }}</AppButton>
          <span v-else class="text-xs text-[var(--ks-muted)]">{{ t('notifications.endOfInbox') }}</span>
        </div>
      </section>

      <aside class="space-y-5">
        <section class="ks-surface p-4 sm:p-5" aria-labelledby="delivery-channels-title">
          <p class="ks-kicker">{{ t('notifications.deliverySetup') }}</p>
          <h2 id="delivery-channels-title" class="ks-display mt-1 text-xl font-semibold">{{ t('notifications.deliveryChannels') }}</h2>
          <p class="mt-2 text-sm text-[var(--ks-muted)]">{{ t('notifications.deliverySecurity') }}</p>

          <div v-if="props.endpoints.length" class="mt-4 space-y-3">
            <div v-for="endpoint in props.endpoints" :key="endpoint.id" class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-3">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <strong class="text-sm">{{ endpoint.label }}</strong>
                  <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                    <span>{{ channelLabel(endpoint.channel) }}</span>
                    <span class="ks-status" :data-tone="endpoint.healthStatus === 'healthy' ? 'success' : endpoint.healthStatus === 'degraded' ? 'danger' : 'info'">{{ healthLabel(endpoint.healthStatus) }}</span>
                  </div>
                </div>
                <button type="button" class="ks-chip" @click="endpointToRemove = endpoint">{{ t('notifications.remove') }}</button>
              </div>
              <div class="mt-2 space-y-1 text-xs text-[var(--ks-muted)]">
                <p v-if="endpoint.lastVerifiedAt">{{ t('notifications.lastVerified', { date: formatDate(endpoint.lastVerifiedAt) }) }}</p>
                <p v-if="endpoint.lastSuccessfulDeliveryAt">{{ t('notifications.lastSuccessfulDelivery', { date: formatDate(endpoint.lastSuccessfulDeliveryAt) }) }}</p>
                <p v-if="endpoint.lastFailedDeliveryAt">{{ t('notifications.lastFailedDelivery', { date: formatDate(endpoint.lastFailedDeliveryAt) }) }}</p>
                <p v-if="endpoint.consecutiveFailures">{{ t('notifications.consecutiveFailures', { count: endpoint.consecutiveFailures }) }}</p>
                <p v-if="endpoint.lastError" class="text-rose-300">{{ endpoint.lastError }}</p>
              </div>
              <div class="mt-3 flex flex-wrap gap-2">
                <button v-if="endpoint.enabled" type="button" class="ks-chip" @click="setEndpointState(endpoint, false)">{{ t('notifications.pause') }}</button>
                <button v-else type="button" class="ks-chip" @click="setEndpointState(endpoint, true)">{{ t('notifications.resume') }}</button>
                <button type="button" class="ks-chip" :disabled="!endpoint.enabled" @click="testEndpoint(endpoint)">{{ t('notifications.test') }}</button>
                <button type="button" class="ks-chip" :disabled="!endpoint.enabled" @click="reverifyEndpoint(endpoint)">{{ t('notifications.reverify') }}</button>
              </div>
            </div>
          </div>

          <div v-if="props.player" class="mt-5">
            <div class="flex gap-2">
              <button v-for="channel in (['discord', 'telegram'] as const)" :key="channel" type="button" class="ks-chip" :data-active="selectedChannel === channel" @click="selectedChannel = channel">{{ channelLabel(channel) }}</button>
            </div>
            <form class="mt-4 space-y-3" @submit.prevent="saveEndpoint">
              <label class="block text-sm">
                <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.label') }}</span>
                <input v-model="endpointForm.label" class="ks-input mt-1 w-full" required />
              </label>
              <label v-if="selectedChannel === 'discord'" class="block text-sm">
                <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.webhookUrl') }}</span>
                <input v-model="endpointForm.webhook_url" class="ks-input mt-1 w-full" type="url" autocomplete="off" required />
              </label>
              <template v-else>
                <label class="block text-sm">
                  <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.botToken') }}</span>
                  <input v-model="endpointForm.bot_token" class="ks-input mt-1 w-full" type="password" autocomplete="new-password" required />
                </label>
                <label class="block text-sm">
                  <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.chatId') }}</span>
                  <input v-model="endpointForm.chat_id" class="ks-input mt-1 w-full" required />
                </label>
              </template>
              <AppButton class="w-full" type="submit" :busy="endpointForm.processing">{{ t('notifications.saveChannel', { channel: channelLabel(selectedChannel) }) }}</AppButton>
              <p v-if="Object.keys(endpointForm.errors).length" class="text-xs text-rose-300">{{ Object.values(endpointForm.errors)[0] }}</p>
            </form>

            <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
              <p class="font-semibold">{{ t('notifications.webPushSetup') }}</p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">{{ t('notifications.webPushHelp') }}</p>
              <AppButton v-if="canEnableWebPush" class="mt-3" :busy="webPushBusy" :busy-label="t('notifications.enablingWebPush')" @click="enableWebPush">{{ t('notifications.enableWebPush') }}</AppButton>
              <p v-else class="mt-2 text-xs text-amber-200">{{ t('notifications.webPushUnavailable') }}</p>
              <p v-if="webPushError" class="mt-2 text-xs text-rose-300">{{ webPushError }}</p>
            </div>
          </div>
          <p v-else class="mt-4 text-sm text-amber-200">{{ t('notifications.selectGovernor') }}</p>
        </section>

        <section class="ks-surface p-4 sm:p-5" aria-labelledby="notification-preferences-title">
          <p class="ks-kicker">{{ t('notifications.preferences') }}</p>
          <h2 id="notification-preferences-title" class="ks-display mt-1 text-xl font-semibold">{{ t('notifications.reminderRouting') }}</h2>
          <p class="mt-2 text-xs text-[var(--ks-muted)]">{{ t('notifications.preferenceHelp') }}</p>
          <div class="mt-4 flex gap-2">
            <button type="button" class="ks-chip" :data-active="preferenceScope === 'account'" @click="preferenceScope = 'account'">{{ t('notifications.accountDefaults') }}</button>
            <button v-if="props.player" type="button" class="ks-chip" :data-active="preferenceScope === 'governor'" @click="preferenceScope = 'governor'">{{ t('notifications.governorOverrides') }}</button>
          </div>
          <div class="mt-4 space-y-4">
            <div v-for="type in props.notificationTypes" :key="type">
              <strong class="text-sm">{{ typeLabel(type) }}</strong>
              <div class="mt-2 grid gap-2">
                <label v-for="channel in props.channels" :key="`${preferenceScope}:${type}:${channel.value}`" class="flex min-h-11 items-center justify-between gap-3 rounded border border-[var(--ks-border)] px-3 text-sm">
                  <span>
                    {{ channelLabel(channel.value) }}
                    <small v-if="preferenceScope === 'governor' && governorOverride(type, channel.value) === undefined" class="ml-1 text-[var(--ks-muted)]">{{ t('notifications.inherited') }}</small>
                  </span>
                  <span class="flex items-center gap-2">
                    <button v-if="preferenceScope === 'governor' && governorOverride(type, channel.value) !== undefined" type="button" class="ks-chip text-xs" @click.prevent="resetPreference(type, channel.value)">{{ t('notifications.resetOverride') }}</button>
                    <input
                      type="checkbox"
                      :checked="preferenceScope === 'account' ? accountPreference(type, channel.value) : governorPreference(type, channel.value)"
                      @change="setPreference(preferenceScope, type, channel.value, ($event.target as HTMLInputElement).checked)"
                    />
                  </span>
                </label>
              </div>
            </div>
          </div>
          <p class="mt-3 text-xs text-[var(--ks-muted)]">{{ t('notifications.emailOptIn') }}</p>
        </section>

        <section class="ks-surface p-4 sm:p-5" aria-labelledby="routing-policy-title">
          <p class="ks-kicker">{{ t('notifications.routingPolicy') }}</p>
          <h2 id="routing-policy-title" class="ks-display mt-1 text-xl font-semibold">{{ t('notifications.routingPolicy') }}</h2>
          <p class="mt-2 text-xs text-[var(--ks-muted)]">{{ t('notifications.routingPolicyHelp') }}</p>

          <div class="mt-4 flex gap-2">
            <button type="button" class="ks-chip" :data-active="preferenceScope === 'account'" @click="preferenceScope = 'account'">{{ t('notifications.accountDefaults') }}</button>
            <button v-if="props.player" type="button" class="ks-chip" :data-active="preferenceScope === 'governor'" @click="preferenceScope = 'governor'">{{ t('notifications.governorOverrides') }}</button>
          </div>

          <form class="mt-4 space-y-3" @submit.prevent="saveRoutingPolicy(preferenceScope)">
            <template v-for="form in [preferenceScope === 'account' ? accountRouting : governorRouting]" :key="preferenceScope">
              <p v-if="preferenceScope === 'governor' && !governorHasRoutingOverride" class="text-xs text-[var(--ks-muted)]">{{ t('notifications.policyInherited') }}</p>
              <label class="block text-sm">
                <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.timezone') }}</span>
                <input v-model="form.timezone" class="ks-input mt-1 w-full" required />
              </label>
              <label class="flex items-center justify-between gap-3 text-sm">
                <span>{{ t('notifications.quietHours') }}</span>
                <input v-model="form.quiet_hours_enabled" type="checkbox" />
              </label>
              <div v-if="form.quiet_hours_enabled" class="grid grid-cols-2 gap-2">
                <label class="text-sm">
                  <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.quietHoursStart') }}</span>
                  <input v-model="form.quiet_hours_start" type="time" class="ks-input mt-1 w-full" />
                </label>
                <label class="text-sm">
                  <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.quietHoursEnd') }}</span>
                  <input v-model="form.quiet_hours_end" type="time" class="ks-input mt-1 w-full" />
                </label>
                <label class="col-span-2 flex items-center justify-between gap-3 text-sm">
                  <span>{{ t('notifications.allowUrgentDuringQuietHours') }}</span>
                  <input v-model="form.allow_urgent_during_quiet_hours" type="checkbox" />
                </label>
              </div>
              <label class="block text-sm">
                <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.mutedUntil') }}</span>
                <input v-model="form.muted_until" type="datetime-local" class="ks-input mt-1 w-full" />
              </label>
              <label class="block text-sm">
                <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.digestCadence') }}</span>
                <select v-model="form.digest_cadence" class="ks-input mt-1 w-full">
                  <option v-for="cadence in props.digestCadences" :key="cadence" :value="cadence">{{ t(`notifications.cadences.${cadence}`) }}</option>
                </select>
              </label>
              <label v-if="form.digest_cadence === 'daily'" class="block text-sm">
                <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.dailyDigestTime') }}</span>
                <input v-model="form.daily_digest_time" type="time" class="ks-input mt-1 w-full" />
              </label>
              <label v-if="form.digest_cadence !== 'immediate'" class="flex items-center justify-between gap-3 text-sm">
                <span>{{ t('notifications.digestUrgent') }}</span>
                <input v-model="form.digest_urgent" type="checkbox" />
              </label>
              <div class="flex flex-wrap gap-2">
                <AppButton type="submit">{{ t('notifications.savePolicy') }}</AppButton>
                <AppButton v-if="preferenceScope === 'governor' && governorHasRoutingOverride" variant="secondary" type="button" @click="resetRoutingPolicy">{{ t('notifications.resetPolicy') }}</AppButton>
              </div>
            </template>
          </form>
        </section>
      </aside>
    </div>

    <ConfirmActionDialog
      id="notification-bulk-update-confirmation"
      :open="bulkConfirmationOpen"
      :title="t('notifications.bulk.confirmTitle')"
      :description="t('notifications.bulk.confirmDescription', { count: notificationBulkPreview?.ready ?? 0, operation: t(`notifications.bulk.operationLabels.${bulkOperation}`) })"
      :confirm-label="t('notifications.bulk.confirm')"
      :cancel-label="t('common.cancel')"
      :busy="bulkBusy"
      :busy-label="t('notifications.bulk.applying')"
      @confirm="commitBulkUpdate"
      @cancel="bulkConfirmationOpen = false"
    />

    <ConfirmActionDialog
      id="notification-endpoint-remove-confirmation"
      :open="endpointToRemove !== null"
      :title="t('notifications.remove')"
      :description="endpointToRemove?.label ?? ''"
      :confirm-label="t('notifications.remove')"
      :cancel-label="t('common.cancel')"
      danger
      @confirm="removeEndpoint"
      @cancel="endpointToRemove = null"
    />
  </AppLayout>
</template>
