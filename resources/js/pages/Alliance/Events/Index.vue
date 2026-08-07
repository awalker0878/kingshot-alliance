<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

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
  alliance: { id: string; name: string; timezone: string };
  userTimezone: string;
  canManage: boolean;
  events: EventItem[];
  eventReminders: EventReminder[];
  exports: { csvUrl: string; icalUrl: string };
}>();

function formatInZone(value: string, timeZone: string): string {
  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    timeZone,
    timeZoneName: 'short',
  }).format(new Date(value));
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

function registrationLabel(event: EventItem): string {
  if (!event.registration) return 'Not registered';
  if (event.registration.status === 'waitlisted' && event.registration.waitlistPosition) {
    return `Waitlisted · position ${event.registration.waitlistPosition}`;
  }

  return event.registration.status.replace('_', ' ');
}
</script>

<template>
  <Head :title="`${alliance.name} events`" />

  <main class="mx-auto min-h-screen max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/alliance">
          ← Alliance home
        </Link>
        <h1 class="mt-4 text-3xl font-bold sm:text-4xl">Events</h1>
        <p class="mt-2 max-w-2xl text-sm text-slate-400 sm:text-base">
          Times are shown in your local zone and {{ alliance.name }}’s alliance zone.
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <a
          class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold hover:border-cyan-400"
          :href="exports.csvUrl"
        >
          Export CSV
        </a>
        <a
          class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold hover:border-cyan-400"
          :href="exports.icalUrl"
        >
          iCalendar feed
        </a>
        <Link
          v-if="canManage"
          class="rounded-lg bg-cyan-300 px-3 py-2 text-sm font-semibold text-slate-950"
          href="/alliance/events/manage"
        >
          Coordinate events
        </Link>
      </div>
    </div>

    <section
      v-if="eventReminders.length"
      class="mt-8 rounded-2xl border border-cyan-900 bg-cyan-950/30 p-5 sm:p-6"
      aria-labelledby="event-reminders-heading"
      aria-live="polite"
    >
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs font-semibold tracking-[0.18em] text-cyan-300 uppercase">In-app</p>
          <h2 id="event-reminders-heading" class="mt-1 text-xl font-semibold">Event reminders</h2>
        </div>
        <span class="text-xs text-slate-400">Delivered within the last 7 days</span>
      </div>

      <div class="mt-4 space-y-3">
        <article
          v-for="reminder in eventReminders"
          :key="reminder.id"
          class="flex flex-col justify-between gap-3 rounded-xl border border-cyan-900/70 bg-slate-950/40 p-4 sm:flex-row sm:items-center"
        >
          <div>
            <h3 class="font-semibold">{{ reminder.title }}</h3>
            <p class="mt-1 text-sm text-slate-300">
              Starts {{ formatInZone(reminder.startsAt, userTimezone) }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
              Reminder delivered {{ formatInZone(reminder.sentAt, userTimezone) }}
            </p>
          </div>
          <Link
            class="shrink-0 rounded-lg border border-cyan-800 px-3 py-2 text-sm font-semibold text-cyan-200 hover:border-cyan-500"
            :href="`/alliance/events/${reminder.occurrenceId}`"
          >
            Open event
          </Link>
        </article>
      </div>
    </section>

    <section class="mt-8" aria-labelledby="upcoming-events-heading">
      <h2 id="upcoming-events-heading" class="sr-only">Upcoming alliance events</h2>

      <div v-if="events.length" class="space-y-4">
        <article
          v-for="event in events"
          :key="event.id"
          class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 sm:p-6"
        >
          <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-start">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-xl font-semibold">
                  <Link class="hover:text-cyan-200" :href="`/alliance/events/${event.id}`">
                    {{ event.title }}
                  </Link>
                </h3>
                <span
                  class="rounded-full bg-slate-800 px-2.5 py-1 text-xs font-semibold text-slate-300 capitalize"
                >
                  {{ registrationLabel(event) }}
                </span>
              </div>

              <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-xl border border-slate-800 p-3">
                  <dt class="font-medium text-slate-400">Your time · {{ userTimezone }}</dt>
                  <dd class="mt-1 font-semibold">
                    {{ formatInZone(event.startsAt, userTimezone) }}
                  </dd>
                </div>
                <div class="rounded-xl border border-slate-800 p-3">
                  <dt class="font-medium text-slate-400">
                    Alliance time · {{ event.allianceTimezone }}
                  </dt>
                  <dd class="mt-1 font-semibold">
                    {{ formatInZone(event.startsAt, event.allianceTimezone) }}
                  </dd>
                </div>
              </dl>

              <p v-if="event.capacity" class="mt-3 text-sm text-slate-400">
                Capacity: {{ event.capacity }}
              </p>
            </div>

            <div class="flex shrink-0 flex-wrap gap-2">
              <button
                v-if="canJoin(event)"
                class="rounded-lg bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950"
                type="button"
                @click="register(event.id)"
              >
                Join event
              </button>
              <button
                v-if="canCancel(event)"
                class="rounded-lg border border-rose-700 px-4 py-2 text-sm font-semibold text-rose-200"
                type="button"
                @click="cancel(event.id)"
              >
                Cancel registration
              </button>
              <Link
                class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold hover:border-cyan-400"
                :href="`/alliance/events/${event.id}`"
              >
                Details
              </Link>
            </div>
          </div>
        </article>
      </div>

      <div v-else class="rounded-2xl border border-dashed border-slate-700 p-10 text-center">
        <h2 class="text-xl font-semibold">No scheduled events</h2>
        <p class="mt-2 text-sm text-slate-400">
          New alliance events will appear here when published.
        </p>
      </div>
    </section>
  </main>
</template>
