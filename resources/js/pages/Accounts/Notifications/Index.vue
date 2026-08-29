<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Channel = { value: 'in_app' | 'discord' | 'telegram'; label: string; external: boolean };
type Endpoint = {
  id: string;
  channel: 'discord' | 'telegram';
  label: string;
  enabled: boolean;
  lastVerifiedAt: string | null;
  lastError: string | null;
};
type Delivery = {
  id: string;
  type: string;
  channel: string;
  status: string;
  title: string;
  body: string | null;
  actionUrl: string | null;
  dueAt: string | null;
  sentAt: string | null;
  readAt: string | null;
  lastError: string | null;
};
type BulkOperation = 'mark_read' | 'dismiss';
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

const props = defineProps<{
  user: { name: string; email: string };
  player: { id: string; name: string } | null;
  deliveries: Delivery[];
  endpoints: Endpoint[];
  preferences: Record<string, boolean>;
  notificationTypes: string[];
  channels: Channel[];
  notificationBulkPreview: NotificationBulkPreview | null;
  notificationBulkResult: NotificationBulkResult | null;
}>();

const { formatDate, t } = useLocale();
const selectedChannel = ref<'discord' | 'telegram'>('discord');
const externalChannels = ['discord', 'telegram'] as const;
const endpointForm = useForm({
  channel: selectedChannel.value,
  label: '',
  webhook_url: '',
  bot_token: '',
  chat_id: '',
});
const unread = computed(
  () => props.deliveries.filter((delivery) => delivery.readAt === null).length,
);
const failures = computed(
  () => props.deliveries.filter((delivery) => delivery.status === 'failed').length,
);
const selectableDeliveryIds = computed(() => props.deliveries.slice(0, 50).map(({ id }) => id));
const selectedDeliveryIds = ref<string[]>(props.notificationBulkResult?.failedItemIds ?? []);
const bulkOperation = ref<BulkOperation>('mark_read');
const bulkBusy = ref(false);
const bulkConfirmationOpen = ref(false);
const allVisibleSelected = computed(
  () =>
    selectableDeliveryIds.value.length > 0 &&
    selectableDeliveryIds.value.every((id) => selectedDeliveryIds.value.includes(id)),
);
const bulkPreviewMatchesSelection = computed(() => {
  const preview = props.notificationBulkPreview;
  if (!preview || preview.operation !== bulkOperation.value) return false;

  const selected = [...selectedDeliveryIds.value].sort();
  const previewed = preview.items.map((item) => item.itemId).sort();
  return (
    selected.length === previewed.length && selected.every((id, index) => id === previewed[index])
  );
});

watch(
  () => props.notificationBulkResult,
  (result) => {
    if (result) selectedDeliveryIds.value = [...result.failedItemIds];
  },
);

function saveEndpoint(): void {
  endpointForm.channel = selectedChannel.value;
  endpointForm.put('/notifications/endpoints', { preserveScroll: true });
}
function removeEndpoint(endpoint: Endpoint): void {
  router.delete(`/notifications/endpoints/${endpoint.id}`, { preserveScroll: true });
}
function preference(type: string, channel: string): boolean {
  return props.preferences[`${type}:${channel}`] ?? true;
}
function setPreference(type: string, channel: string, enabled: boolean): void {
  router.put(
    '/notifications/preferences',
    { notification_type: type, channel, enabled },
    { preserveScroll: true },
  );
}
function markRead(delivery: Delivery): void {
  router.put(`/notifications/${delivery.id}/read`, {}, { preserveScroll: true });
}
function dismiss(delivery: Delivery): void {
  router.delete(`/notifications/${delivery.id}`, { preserveScroll: true });
}
function typeLabel(type: string): string {
  const labels: Record<string, string> = {
    'alliance.announcement': t('notifications.types.allianceAnnouncement'),
    'event.reminder': t('notifications.types.eventReminder'),
    'gift_code.expiring': t('notifications.types.giftCodeExpiring'),
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
function deliverySelected(deliveryId: string): boolean {
  return selectedDeliveryIds.value.includes(deliveryId);
}
function setDeliverySelected(deliveryId: string, selected: boolean): void {
  if (!selected) {
    selectedDeliveryIds.value = selectedDeliveryIds.value.filter((id) => id !== deliveryId);
    return;
  }

  selectedDeliveryIds.value = [...new Set([...selectedDeliveryIds.value, deliveryId])].slice(0, 50);
}
function toggleVisibleSelection(): void {
  if (allVisibleSelected.value) {
    const visible = new Set(selectableDeliveryIds.value);
    selectedDeliveryIds.value = selectedDeliveryIds.value.filter((id) => !visible.has(id));
    return;
  }

  selectedDeliveryIds.value = [
    ...new Set([...selectedDeliveryIds.value, ...selectableDeliveryIds.value]),
  ].slice(0, 50);
}
function previewBulkUpdate(): void {
  if (selectedDeliveryIds.value.length === 0 || bulkBusy.value) return;

  bulkBusy.value = true;
  router.post(
    '/notifications/bulk-inbox/preview',
    { delivery_ids: selectedDeliveryIds.value, operation: bulkOperation.value },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => (bulkBusy.value = false),
    },
  );
}
function commitBulkUpdate(): void {
  if (!props.notificationBulkPreview || !bulkPreviewMatchesSelection.value || bulkBusy.value)
    return;

  bulkBusy.value = true;
  router.post(
    '/notifications/bulk-inbox',
    {
      delivery_ids: props.notificationBulkPreview.items.map((item) => item.itemId),
      operation: bulkOperation.value,
    },
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
      <StatSeal
        :label="t('notifications.externalChannels')"
        :value="props.endpoints.length"
        icon="↗"
      />
      <StatSeal
        :label="t('notifications.needsAttention')"
        :value="failures"
        icon="!"
        tone="stone"
      />
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.2fr)_minmax(22rem,.8fr)]">
      <section class="ks-surface overflow-hidden" aria-labelledby="notification-inbox-title">
        <div class="flex flex-wrap items-end justify-between gap-3 p-4 sm:p-5">
          <div>
            <p class="ks-kicker">{{ t('notifications.latestActivity') }}</p>
            <h2 id="notification-inbox-title" class="ks-display mt-1 text-2xl font-semibold">
              {{ t('notifications.inbox') }}
            </h2>
          </div>
          <div class="flex items-center gap-2">
            <span class="ks-status" data-tone="info">{{ props.deliveries.length }}</span>
            <button
              v-if="props.deliveries.length"
              type="button"
              class="ks-chip text-xs"
              :aria-pressed="allVisibleSelected"
              :data-active="allVisibleSelected"
              @click="toggleVisibleSelection"
            >
              {{
                allVisibleSelected
                  ? t('notifications.bulk.clearSelection')
                  : t('notifications.bulk.selectVisible')
              }}
            </button>
          </div>
        </div>

        <div
          v-if="selectedDeliveryIds.length"
          class="border-t border-[var(--ks-border)] bg-[var(--ks-teal-soft)] p-4 sm:p-5"
        >
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[14rem] flex-1">
              <p class="font-semibold">
                {{ t('notifications.bulk.selected', { count: selectedDeliveryIds.length }) }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('notifications.bulk.help') }}
              </p>
            </div>
            <label class="text-sm">
              <span class="sr-only">{{ t('notifications.bulk.operation') }}</span>
              <select v-model="bulkOperation" class="ks-input min-h-11">
                <option value="mark_read">{{ t('notifications.bulk.markRead') }}</option>
                <option value="dismiss">{{ t('notifications.bulk.dismiss') }}</option>
              </select>
            </label>
            <AppButton
              :busy="bulkBusy"
              :busy-label="t('notifications.bulk.previewing')"
              @click="previewBulkUpdate"
            >
              {{ t('notifications.bulk.preview') }}
            </AppButton>
          </div>
        </div>

        <div
          v-if="bulkPreviewMatchesSelection && notificationBulkPreview"
          class="border-t border-[var(--ks-border)] p-4 sm:p-5"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('notifications.bulk.previewTitle') }}</p>
              <p class="mt-1 font-semibold">
                {{
                  t('notifications.bulk.previewSummary', {
                    ready: notificationBulkPreview.ready,
                    blocked: notificationBulkPreview.blocked,
                  })
                }}
              </p>
            </div>
            <AppButton
              :variant="bulkOperation === 'dismiss' ? 'danger' : 'primary'"
              :disabled="notificationBulkPreview.ready === 0"
              @click="bulkConfirmationOpen = true"
            >
              {{ t('notifications.bulk.confirm') }}
            </AppButton>
          </div>
          <ul class="mt-4 grid gap-2 md:grid-cols-2">
            <li
              v-for="item in notificationBulkPreview.items"
              :key="item.itemId"
              class="flex items-center justify-between gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 px-3 py-2 text-sm"
            >
              <span class="truncate">{{ item.label }}</span>
              <span
                class="ks-status"
                :data-tone="
                  item.outcome === 'ready'
                    ? 'success'
                    : item.outcome === 'skipped'
                      ? 'warning'
                      : 'danger'
                "
              >
                {{ bulkOutcomeLabel(item.code) }}
              </span>
            </li>
          </ul>
        </div>

        <div
          v-if="notificationBulkResult"
          class="border-t border-[var(--ks-border)] p-4 sm:p-5"
          aria-labelledby="notification-bulk-result-title"
        >
          <p class="ks-kicker">{{ t('notifications.bulk.resultTitle') }}</p>
          <h3 id="notification-bulk-result-title" class="mt-1 text-lg font-semibold">
            {{
              t('notifications.bulk.resultSummary', {
                succeeded: notificationBulkResult.succeeded,
                failed: notificationBulkResult.failed,
                skipped: notificationBulkResult.skipped,
              })
            }}
          </h3>
          <ul class="mt-4 grid gap-2 md:grid-cols-2">
            <li
              v-for="item in notificationBulkResult.items"
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
                {{ bulkOutcomeLabel(item.code) }}
              </span>
            </li>
          </ul>
          <p v-if="notificationBulkResult.failed" class="mt-3 text-xs text-[var(--ks-muted)]">
            {{ t('notifications.bulk.failedSelected') }}
          </p>
        </div>

        <div v-if="props.deliveries.length" class="divide-y divide-[var(--ks-border)] px-4 sm:px-5">
          <article
            v-for="delivery in props.deliveries"
            :key="delivery.id"
            class="grid gap-3 py-4 sm:grid-cols-[auto_minmax(0,1fr)_auto]"
            :class="delivery.readAt === null ? 'text-[var(--ks-text)]' : 'opacity-70'"
          >
            <div class="pt-1">
              <input
                type="checkbox"
                :checked="deliverySelected(delivery.id)"
                :aria-label="t('notifications.bulk.selectNotification', { title: delivery.title })"
                @change="
                  setDeliverySelected(delivery.id, ($event.target as HTMLInputElement).checked)
                "
              />
            </div>
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <span
                  class="ks-status"
                  :data-tone="delivery.status === 'failed' ? 'danger' : 'info'"
                >
                  {{ channelLabel(delivery.channel) }} · {{ statusLabel(delivery.status) }}
                </span>
                <span
                  v-if="delivery.readAt === null"
                  class="h-2 w-2 rounded-full bg-cyan-300"
                  :title="t('notifications.unread')"
                />
              </div>
              <h3 class="mt-2 text-base font-semibold">{{ delivery.title }}</h3>
              <p v-if="delivery.body" class="mt-1 text-sm text-[var(--ks-text-secondary)]">
                {{ delivery.body }}
              </p>
              <p class="mt-2 text-xs text-[var(--ks-muted)]">
                {{ formatDate(delivery.sentAt || delivery.dueAt || new Date().toISOString()) }}
              </p>
              <p v-if="delivery.lastError" class="mt-2 text-xs text-rose-300">
                {{ delivery.lastError }}
              </p>
            </div>
            <div class="flex flex-wrap items-start gap-2 sm:justify-end">
              <Link
                v-if="delivery.actionUrl"
                :href="delivery.actionUrl"
                class="ks-command-link"
                data-variant="secondary"
                >{{ t('notifications.open') }}</Link
              >
              <button
                v-if="delivery.readAt === null"
                type="button"
                class="ks-chip"
                @click="markRead(delivery)"
              >
                {{ t('notifications.markRead') }}
              </button>
              <button type="button" class="ks-chip" @click="dismiss(delivery)">
                {{ t('notifications.dismiss') }}
              </button>
            </div>
          </article>
        </div>
        <div v-else class="ks-fantasy-empty m-4 sm:m-5">{{ t('notifications.empty') }}</div>
      </section>

      <aside class="space-y-5">
        <section class="ks-surface p-4 sm:p-5" aria-labelledby="delivery-channels-title">
          <p class="ks-kicker">{{ t('notifications.deliverySetup') }}</p>
          <h2 id="delivery-channels-title" class="ks-display mt-1 text-xl font-semibold">
            {{ t('notifications.deliveryChannels') }}
          </h2>
          <p class="mt-2 text-sm text-[var(--ks-muted)]">
            {{ t('notifications.deliverySecurity') }}
          </p>

          <div v-if="props.endpoints.length" class="mt-4 space-y-2">
            <div
              v-for="endpoint in props.endpoints"
              :key="endpoint.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-3"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <strong class="text-sm">{{ endpoint.label }}</strong>
                  <p class="mt-1 text-xs text-[var(--ks-muted)]">
                    {{ channelLabel(endpoint.channel) }}
                  </p>
                </div>
                <button type="button" class="ks-chip" @click="removeEndpoint(endpoint)">
                  {{ t('notifications.remove') }}
                </button>
              </div>
              <p v-if="endpoint.lastError" class="mt-2 text-xs text-rose-300">
                {{ endpoint.lastError }}
              </p>
            </div>
          </div>

          <div v-if="props.player" class="mt-5">
            <div class="flex gap-2">
              <button
                v-for="channel in externalChannels"
                :key="channel"
                type="button"
                class="ks-chip"
                :data-active="selectedChannel === channel"
                @click="selectedChannel = channel"
              >
                {{ channelLabel(channel) }}
              </button>
            </div>
            <form class="mt-4 space-y-3" @submit.prevent="saveEndpoint">
              <label class="block text-sm">
                <span class="text-[var(--ks-text-secondary)]">{{ t('notifications.label') }}</span>
                <input v-model="endpointForm.label" class="ks-input mt-1 w-full" required />
              </label>
              <label v-if="selectedChannel === 'discord'" class="block text-sm">
                <span class="text-[var(--ks-text-secondary)]">{{
                  t('notifications.webhookUrl')
                }}</span>
                <input
                  v-model="endpointForm.webhook_url"
                  class="ks-input mt-1 w-full"
                  type="url"
                  autocomplete="off"
                  required
                />
              </label>
              <template v-else>
                <label class="block text-sm">
                  <span class="text-[var(--ks-text-secondary)]">{{
                    t('notifications.botToken')
                  }}</span>
                  <input
                    v-model="endpointForm.bot_token"
                    class="ks-input mt-1 w-full"
                    type="password"
                    autocomplete="new-password"
                    required
                  />
                </label>
                <label class="block text-sm">
                  <span class="text-[var(--ks-text-secondary)]">{{
                    t('notifications.chatId')
                  }}</span>
                  <input v-model="endpointForm.chat_id" class="ks-input mt-1 w-full" required />
                </label>
              </template>
              <button
                class="ks-command-link w-full justify-center"
                :disabled="endpointForm.processing"
              >
                {{
                  t('notifications.saveChannel', {
                    channel: channelLabel(selectedChannel),
                  })
                }}
              </button>
              <p v-if="Object.keys(endpointForm.errors).length" class="text-xs text-rose-300">
                {{ Object.values(endpointForm.errors)[0] }}
              </p>
            </form>
          </div>
          <p v-else class="mt-4 text-sm text-amber-200">
            {{ t('notifications.selectGovernor') }}
          </p>
        </section>

        <section class="ks-surface p-4 sm:p-5" aria-labelledby="notification-preferences-title">
          <p class="ks-kicker">{{ t('notifications.preferences') }}</p>
          <h2 id="notification-preferences-title" class="ks-display mt-1 text-xl font-semibold">
            {{ t('notifications.reminderRouting') }}
          </h2>
          <div v-if="props.player" class="mt-4 space-y-4">
            <div v-for="type in props.notificationTypes" :key="type">
              <strong class="text-sm">{{ typeLabel(type) }}</strong>
              <div class="mt-2 grid gap-2 sm:grid-cols-3 2xl:grid-cols-1">
                <label
                  v-for="channel in props.channels"
                  :key="`${type}:${channel.value}`"
                  class="flex min-h-11 items-center justify-between gap-3 rounded border border-[var(--ks-border)] px-3 text-sm"
                >
                  <span>{{ channelLabel(channel.value) }}</span>
                  <input
                    type="checkbox"
                    :checked="preference(type, channel.value)"
                    @change="
                      setPreference(
                        type,
                        channel.value,
                        ($event.target as HTMLInputElement).checked,
                      )
                    "
                  />
                </label>
              </div>
            </div>
          </div>
        </section>
      </aside>
    </div>

    <ConfirmActionDialog
      id="notification-bulk-update-confirmation"
      :open="bulkConfirmationOpen"
      :title="t('notifications.bulk.confirmTitle')"
      :description="
        t('notifications.bulk.confirmDescription', {
          count: notificationBulkPreview?.ready ?? 0,
          operation:
            bulkOperation === 'dismiss'
              ? t('notifications.bulk.dismissLower')
              : t('notifications.bulk.markReadLower'),
        })
      "
      :confirm-label="t('notifications.bulk.confirm')"
      :cancel-label="t('common.cancel')"
      :busy="bulkBusy"
      :busy-label="t('notifications.bulk.applying')"
      :danger="bulkOperation === 'dismiss'"
      @confirm="commitBulkUpdate"
      @cancel="bulkConfirmationOpen = false"
    />
  </AppLayout>
</template>
