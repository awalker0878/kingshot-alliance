<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

import AppLayout from '../../../layouts/AppLayout.vue';
import { useLocale } from '../../../localization';

type EventItem = {
  id: string;
  eventId: string;
  title: string;
  startsAt: string;
  endsAt: string;
  allianceTimezone: string;
  capacity: number | null;
  status: string;
  registrationOpensAt: string | null;
  registrationClosesAt: string | null;
  registration: { status: string; waitlistPosition: number | null } | null;
};

type EventReminder = {
  id: string;
  occurrenceId: string;
  title: string;
  startsAt: string;
  sentAt: string;
  allianceTimezone: string;
};

defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; timezone: string };
  userTimezone: string;
  canManage: boolean;
  events: EventItem[];
  eventReminders: EventReminder[];
  exports: { csvUrl: string; icalUrl: string };
}>();

const { t, formatDate, formatNumber } = useLocale();

function formatInZone(value: string, timeZone: string): string {
  return formatDate(value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    timeZone,
    timeZoneName: 'short',
  });
}

function canJoin(event: EventItem): boolean {
  if (event.registration && event.registration.status !== 'cancelled') return false;

  const now = Date.now();
  if (event.registrationOpensAt && now < new Date(event.registrationOpensAt).getTime())
    return false;
  if (event.registrationClosesAt && now > new Date(event.registrationClosesAt).getTime())
    return false;

  return new Date(event.startsAt).getTime() > now;
}

function canCancel(event: EventItem): boolean {
  return ['registered', 'waitlisted'].includes(event.registration?.status ?? '');
}

function register(id: string): void {
  router.post(`/alliance/events/${id}/registration`, {}, { preserveScroll: true });
}

function cancel(id: string): void {
  router.delete(`/alliance/events/${id}/registration`, { preserveScroll: true });
}

function statusLabel(value: string): string {
  const key = `allianceOperations.status.${value === 'no_show' ? 'noShow' : value}`;
  const translated = t(key);
  return translated === key ? value.replaceAll('_', ' ') : translated;
}

function registrationLabel(event: EventItem): string {
  if (!event.registration) return t('allianceOperations.events.notRegistered');
  if (event.registration.status === 'waitlisted' && event.registration.waitlistPosition) {
    return t('allianceOperations.events.waitlistedPosition', {
      position: formatNumber(event.registration.waitlistPosition),
    });
  }

  return statusLabel(event.registration.status);
}
</script>

<template>
  <Head :title="`${alliance.name} · ${t('navigation.events')}`" />

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
      <div>
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('allianceOperations.events.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">{{ t('navigation.events') }}</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('allianceOperations.events.intro', { alliance: alliance.name }) }}
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <a
          class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-border-strong)] hover:text-white"
          :href="exports.csvUrl"
          >{{ t('allianceOperations.events.exportCsv') }}</a
        >
        <a
          class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-border-strong)] hover:text-white"
          :href="exports.icalUrl"
          >{{ t('allianceOperations.events.icalFeed') }}</a
        >
        <Link
          v-if="canManage"
          class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--ks-blue-strong)]"
          href="/alliance/events/manage"
          >{{ t('allianceOperations.events.coordinate') }}</Link
        >
      </div>
    </header>

    <section
      v-if="eventReminders.length"
      class="mt-8 rounded-[var(--ks-radius-lg)] border border-blue-400/25 bg-[var(--ks-blue-soft)] p-5 sm:p-6"
      aria-labelledby="event-reminders-heading"
      aria-live="polite"
    >
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs font-bold tracking-[0.18em] text-[var(--ks-blue-strong)] uppercase">
            {{ t('allianceOperations.events.reminders') }}
          </p>
          <h2 id="event-reminders-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ t('allianceOperations.events.recentReminders') }}
          </h2>
        </div>
        <span
          class="rounded-full border border-blue-400/20 bg-blue-400/5 px-3 py-1 text-xs text-[var(--ks-text-muted)]"
        >
          {{ formatNumber(eventReminders.length) }}
        </span>
      </div>

      <div class="mt-4 grid gap-3 lg:grid-cols-2">
        <article
          v-for="reminder in eventReminders"
          :key="reminder.id"
          class="rounded-[var(--ks-radius-md)] border border-blue-400/20 bg-[var(--ks-bg)]/45 p-4"
        >
          <h3 class="font-semibold">{{ reminder.title }}</h3>
          <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
            {{
              t('allianceOperations.events.starts', {
                time: formatInZone(reminder.startsAt, userTimezone),
              })
            }}
          </p>
          <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
            {{
              t('allianceOperations.events.delivered', {
                time: formatInZone(reminder.sentAt, userTimezone),
              })
            }}
          </p>
          <Link
            class="mt-4 inline-flex min-h-10 items-center rounded-[var(--ks-radius-sm)] border border-blue-400/30 px-3 py-2 text-sm font-semibold text-[var(--ks-blue-strong)] transition hover:bg-blue-400/10 hover:text-white"
            :href="`/alliance/events/${reminder.occurrenceId}`"
            >{{ t('allianceOperations.events.openEvent') }}</Link
          >
        </article>
      </div>
    </section>

    <section class="mt-9" aria-labelledby="upcoming-events-heading">
      <div class="flex items-center justify-between gap-4">
        <h2 id="upcoming-events-heading" class="ks-display text-2xl font-semibold">
          {{ t('allianceOperations.events.upcoming') }}
        </h2>
        <span v-if="events.length" class="text-sm text-[var(--ks-text-muted)]">
          {{ formatNumber(events.length) }}
        </span>
      </div>

      <div v-if="events.length" class="mt-5 space-y-4">
        <article
          v-for="event in events"
          :key="event.id"
          class="ks-surface overflow-hidden p-5 sm:p-6"
        >
          <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-xl font-semibold sm:text-2xl">
                  <Link
                    class="hover:text-[var(--ks-blue-strong)]"
                    :href="`/alliance/events/${event.id}`"
                  >
                    {{ event.title }}
                  </Link>
                </h3>
                <span
                  class="rounded-full border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-2.5 py-1 text-xs font-semibold text-[var(--ks-text-secondary)]"
                  >{{ registrationLabel(event) }}</span
                >
              </div>

              <dl class="mt-5 grid gap-3 lg:grid-cols-2">
                <div
                  class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/35 p-4"
                >
                  <dt
                    class="text-xs font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
                  >
                    {{ t('allianceOperations.events.yourTime', { zone: userTimezone }) }}
                  </dt>
                  <dd class="mt-2 font-semibold">
                    {{ formatInZone(event.startsAt, userTimezone) }}
                  </dd>
                </div>
                <div
                  class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/35 p-4"
                >
                  <dt
                    class="text-xs font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
                  >
                    {{
                      t('allianceOperations.events.allianceTime', { zone: event.allianceTimezone })
                    }}
                  </dt>
                  <dd class="mt-2 font-semibold">
                    {{ formatInZone(event.startsAt, event.allianceTimezone) }}
                  </dd>
                </div>
              </dl>

              <p v-if="event.capacity" class="mt-3 text-sm text-[var(--ks-text-muted)]">
                {{
                  t('allianceOperations.events.capacity', {
                    capacity: formatNumber(event.capacity),
                  })
                }}
              </p>
            </div>

            <div class="flex flex-wrap gap-2 xl:max-w-56 xl:justify-end">
              <button
                v-if="canJoin(event)"
                class="min-h-11 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--ks-blue-strong)]"
                type="button"
                @click="register(event.id)"
              >
                {{ t('allianceOperations.events.join') }}
              </button>
              <button
                v-if="canCancel(event)"
                class="min-h-11 rounded-[var(--ks-radius-sm)] border border-red-400/30 bg-red-500/10 px-4 py-2 text-sm font-semibold text-red-200 transition hover:bg-red-500/20"
                type="button"
                @click="cancel(event.id)"
              >
                {{ t('allianceOperations.events.cancelRegistration') }}
              </button>
              <Link
                class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-border-strong)] hover:text-white"
                :href="`/alliance/events/${event.id}`"
                >{{ t('allianceOperations.events.details') }}</Link
              >
            </div>
          </div>
        </article>
      </div>

      <div v-else class="ks-surface mt-5 border-dashed p-10 text-center">
        <h2 class="ks-display text-xl font-semibold">
          {{ t('allianceOperations.events.noEvents') }}
        </h2>
        <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('allianceOperations.events.noEventsIntro') }}
        </p>
      </div>
    </section>
  </AppLayout>
</template>
