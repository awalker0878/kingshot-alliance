<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
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

const props = defineProps<{
  user: { name: string; email: string };
  player: { id: string; name: string } | null;
  deliveries: Delivery[];
  endpoints: Endpoint[];
  preferences: Record<string, boolean>;
  notificationTypes: string[];
  channels: Channel[];
  status: string | null;
}>();

const { formatDate } = useLocale();
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
  if (type === 'alliance.announcement') return 'Alliance announcements';
  if (type === 'event.reminder') return 'Event reminders';
  if (type === 'king_perks.reminder') return 'King Perk reminders';
  return type;
}
</script>

<template>
  <Head title="Notifications" />
  <AppLayout :user="props.user">
    <RoomBanner
      eyebrow="Governor communications"
      title="Notification Center"
      subtitle="Keep reminders in one inbox and deliver them to Discord or Telegram for the active Governor."
      image="/images/kingshot/v4/event-command.svg"
    />

    <section class="mt-4 grid gap-3 sm:grid-cols-3">
      <StatSeal label="Unread" :value="unread" icon="✦" tone="teal" />
      <StatSeal label="External channels" :value="props.endpoints.length" icon="↗" />
      <StatSeal label="Needs attention" :value="failures" icon="!" tone="stone" />
    </section>

    <p
      v-if="props.status"
      class="mt-5 rounded-[var(--ks-radius-md)] border border-emerald-400/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200"
      role="status"
    >
      Settings saved.
    </p>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.2fr)_minmax(22rem,.8fr)]">
      <section class="ks-surface p-4 sm:p-5" aria-labelledby="notification-inbox-title">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <p class="ks-kicker">Latest notification activity</p>
            <h2 id="notification-inbox-title" class="ks-display mt-1 text-2xl font-semibold">
              Inbox
            </h2>
          </div>
          <span class="ks-status" data-tone="info">{{ props.deliveries.length }}</span>
        </div>

        <div v-if="props.deliveries.length" class="mt-4 divide-y divide-[var(--ks-border)]">
          <article
            v-for="delivery in props.deliveries"
            :key="delivery.id"
            class="grid gap-3 py-4 sm:grid-cols-[minmax(0,1fr)_auto]"
            :class="delivery.readAt === null ? 'text-[var(--ks-text)]' : 'opacity-70'"
          >
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <span
                  class="ks-status"
                  :data-tone="delivery.status === 'failed' ? 'danger' : 'info'"
                >
                  {{ delivery.channel }} · {{ delivery.status }}
                </span>
                <span v-if="delivery.readAt === null" class="h-2 w-2 rounded-full bg-cyan-300" />
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
                >Open</Link
              >
              <button
                v-if="delivery.readAt === null"
                type="button"
                class="ks-chip"
                @click="markRead(delivery)"
              >
                Mark read
              </button>
              <button type="button" class="ks-chip" @click="dismiss(delivery)">Dismiss</button>
            </div>
          </article>
        </div>
        <div v-else class="ks-fantasy-empty mt-4">No notifications yet.</div>
      </section>

      <aside class="space-y-5">
        <section class="ks-surface p-4 sm:p-5" aria-labelledby="delivery-channels-title">
          <p class="ks-kicker">Delivery setup</p>
          <h2 id="delivery-channels-title" class="ks-display mt-1 text-xl font-semibold">
            Discord & Telegram
          </h2>
          <p class="mt-2 text-sm text-[var(--ks-muted)]">
            Credentials are encrypted at rest and never shown again. External delivery is scoped to
            the active Governor.
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
                  <p class="mt-1 text-xs text-[var(--ks-muted)]">{{ endpoint.channel }}</p>
                </div>
                <button type="button" class="ks-chip" @click="removeEndpoint(endpoint)">
                  Remove
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
                {{ channel === 'discord' ? 'Discord' : 'Telegram' }}
              </button>
            </div>
            <form class="mt-4 space-y-3" @submit.prevent="saveEndpoint">
              <label class="block text-sm">
                <span class="text-[var(--ks-text-secondary)]">Label</span>
                <input v-model="endpointForm.label" class="ks-input mt-1 w-full" required />
              </label>
              <label v-if="selectedChannel === 'discord'" class="block text-sm">
                <span class="text-[var(--ks-text-secondary)]">Webhook URL</span>
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
                  <span class="text-[var(--ks-text-secondary)]">Bot token</span>
                  <input
                    v-model="endpointForm.bot_token"
                    class="ks-input mt-1 w-full"
                    type="password"
                    autocomplete="new-password"
                    required
                  />
                </label>
                <label class="block text-sm">
                  <span class="text-[var(--ks-text-secondary)]">Chat ID</span>
                  <input v-model="endpointForm.chat_id" class="ks-input mt-1 w-full" required />
                </label>
              </template>
              <button
                class="ks-command-link w-full justify-center"
                :disabled="endpointForm.processing"
              >
                Save {{ selectedChannel === 'discord' ? 'Discord' : 'Telegram' }} channel
              </button>
              <p v-if="Object.keys(endpointForm.errors).length" class="text-xs text-rose-300">
                {{ Object.values(endpointForm.errors)[0] }}
              </p>
            </form>
          </div>
          <p v-else class="mt-4 text-sm text-amber-200">
            Select a Governor before configuring external delivery.
          </p>
        </section>

        <section class="ks-surface p-4 sm:p-5" aria-labelledby="notification-preferences-title">
          <p class="ks-kicker">Preferences</p>
          <h2 id="notification-preferences-title" class="ks-display mt-1 text-xl font-semibold">
            Reminder routing
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
                  <span>{{ channel.label }}</span>
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
  </AppLayout>
</template>
