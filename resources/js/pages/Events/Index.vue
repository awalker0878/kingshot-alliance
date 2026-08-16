<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

type EventScope = 'player' | 'alliance' | 'kingdom';
type ScopeFilter = 'all' | EventScope;
type EventRow = {
  id: string;
  eventId: string;
  eventTypeSlug: string;
  nameKey: string;
  title: string | null;
  scope: EventScope;
  targetLabel: string;
  startsAt: string;
  endsAt: string;
  timezone: string;
  status: string;
  capabilities: string[];
  canManage: boolean;
};

const props = defineProps<{
  user: { name: string; email: string };
  userTimezone: string;
  events: EventRow[];
  attention: Array<{
    occurrenceId: string;
    eventId: string;
    eventTypeSlug: string;
    nameKey: string;
    title: string | null;
    action: 'response' | 'registration' | 'vote' | 'roster_confirmation';
    pollId: string | null;
    rosterMemberId: string | null;
    playerId: string;
    startsAt: string;
  }>;
  reminders: Array<{
    id: string;
    occurrenceId: string;
    eventTypeSlug: string;
    nameKey: string;
    title: string | null;
    startsAt: string;
    sentAt: string | null;
    playerId: string;
  }>;
  canCreate: boolean;
  status: string | null;
}>();

const { t, formatDate } = useLocale();
const scope = ref<ScopeFilter>('all');
const view = ref<'agenda' | 'calendar'>('agenda');
const monthCursor = ref(new Date());
const scopeOptions: ScopeFilter[] = ['all', 'player', 'alliance', 'kingdom'];
const weekdayDates = Array.from({ length: 7 }, (_, index) => new Date(2024, 0, 7 + index, 12));

function dateKey(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

const visibleEvents = computed(() =>
  props.events.filter((event) => scope.value === 'all' || event.scope === scope.value),
);
const grouped = computed(() => {
  const groups = new Map<string, EventRow[]>();
  for (const event of visibleEvents.value) {
    const key = dateKey(new Date(event.startsAt));
    groups.set(key, [...(groups.get(key) ?? []), event]);
  }
  return [...groups.entries()];
});
const monthDays = computed(() => {
  const year = monthCursor.value.getFullYear();
  const month = monthCursor.value.getMonth();
  const first = new Date(year, month, 1);
  const last = new Date(year, month + 1, 0);
  const eventsByDay = new Map<string, EventRow[]>();
  for (const event of visibleEvents.value) {
    const key = dateKey(new Date(event.startsAt));
    eventsByDay.set(key, [...(eventsByDay.get(key) ?? []), event]);
  }

  const days: Array<{ date: Date; key: string; current: boolean; events: EventRow[] }> = [];
  const start = new Date(first);
  start.setDate(first.getDate() - first.getDay());
  const end = new Date(last);
  end.setDate(last.getDate() + (6 - last.getDay()));
  for (const cursor = new Date(start); cursor <= end; cursor.setDate(cursor.getDate() + 1)) {
    const date = new Date(cursor);
    const key = dateKey(date);
    days.push({
      date,
      key,
      current: date.getMonth() === month,
      events: eventsByDay.get(key) ?? [],
    });
  }
  return days;
});

function moveMonth(delta: number): void {
  monthCursor.value = new Date(
    monthCursor.value.getFullYear(),
    monthCursor.value.getMonth() + delta,
    1,
  );
}
function displayName(event: EventRow): string {
  return event.title || t(event.nameKey);
}
</script>

<template>
  <Head :title="t('events.calendar.title')" />
  <AppLayout :user="props.user">
    <header class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('events.calendar.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
          {{ t('events.calendar.title') }}
        </h1>
        <p class="mt-3 text-sm text-[var(--ks-text-muted)]">
          {{ t('events.calendar.description') }}
        </p>
      </div>
      <Link
        v-if="props.canCreate"
        href="/events/create"
        class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2.5 text-sm font-bold text-slate-950"
      >
        {{ t('events.calendar.create') }}
      </Link>
    </header>

    <p
      v-if="props.status"
      class="mb-5 rounded border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200"
      role="status"
    >
      {{ props.status }}
    </p>

    <section
      v-if="props.attention.length"
      class="mb-5 rounded-[var(--ks-radius-lg)] border border-[var(--ks-gold)]/30 bg-[var(--ks-surface-1)] p-4"
      aria-labelledby="event-attention-title"
    >
      <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
        {{ t('events.attention.eyebrow') }}
      </p>
      <h2 id="event-attention-title" class="mt-1 text-lg font-semibold">
        {{ t('events.attention.title') }}
      </h2>
      <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
        <Link
          v-for="item in props.attention"
          :key="`${item.occurrenceId}:${item.action}:${item.pollId ?? ''}:${item.rosterMemberId ?? ''}`"
          :href="`/events/${item.occurrenceId}`"
          class="rounded border border-[var(--ks-border)] p-3 transition hover:border-[var(--ks-border-strong)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-gold)]"
        >
          <div class="text-sm font-semibold">{{ item.title || t(item.nameKey) }}</div>
          <div class="mt-1 text-xs text-[var(--ks-text-muted)]">
            {{ t(`events.attention.${item.action}`) }} ·
            {{
              formatDate(new Date(item.startsAt), {
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
              })
            }}
          </div>
        </Link>
      </div>
    </section>

    <section
      v-if="props.reminders.length"
      class="mb-5 rounded-[var(--ks-radius-lg)] border border-[var(--ks-blue-strong)]/25 bg-[var(--ks-surface-1)] p-4"
      aria-labelledby="event-reminders-title"
    >
      <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-blue-strong)] uppercase">
        {{ t('events.reminders.eyebrow') }}
      </p>
      <h2 id="event-reminders-title" class="mt-1 text-lg font-semibold">
        {{ t('events.reminders.title') }}
      </h2>
      <div class="mt-3 space-y-2">
        <Link
          v-for="reminder in props.reminders"
          :key="reminder.id"
          :href="`/events/${reminder.occurrenceId}`"
          class="flex flex-wrap items-center justify-between gap-2 rounded border border-[var(--ks-border)] p-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-blue-strong)]"
        >
          <div>
            <div class="text-sm font-semibold">{{ reminder.title || t(reminder.nameKey) }}</div>
            <div class="mt-1 text-xs text-[var(--ks-text-muted)]">
              {{ t('events.reminders.starts') }}
              {{
                formatDate(new Date(reminder.startsAt), {
                  month: 'short',
                  day: 'numeric',
                  hour: 'numeric',
                  minute: '2-digit',
                })
              }}
            </div>
          </div>
          <span class="text-xs text-[var(--ks-text-muted)]">{{
            reminder.sentAt
              ? formatDate(new Date(reminder.sentAt), { hour: 'numeric', minute: '2-digit' })
              : ''
          }}</span>
        </Link>
      </div>
    </section>

    <div
      class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-3"
    >
      <div
        class="flex flex-wrap gap-2"
        role="group"
        :aria-label="t('events.calendar.scopeFilters')"
      >
        <button
          v-for="value in scopeOptions"
          :key="value"
          type="button"
          class="rounded-full px-3 py-1.5 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-gold)]"
          :class="
            scope === value
              ? 'bg-[var(--ks-gold)] text-slate-950'
              : 'border border-[var(--ks-border)]'
          "
          :aria-pressed="scope === value"
          @click="scope = value"
        >
          {{ value === 'all' ? t('events.calendar.all') : t(`events.scope.${value}`) }}
        </button>
      </div>
      <div class="flex gap-2" role="group" :aria-label="t('events.calendar.viewOptions')">
        <button
          type="button"
          class="rounded px-3 py-1.5 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-blue-strong)]"
          :class="view === 'agenda' ? 'bg-[var(--ks-blue-soft)] text-[var(--ks-blue-strong)]' : ''"
          :aria-pressed="view === 'agenda'"
          @click="view = 'agenda'"
        >
          {{ t('events.calendar.agenda') }}
        </button>
        <button
          type="button"
          class="rounded px-3 py-1.5 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-blue-strong)]"
          :class="
            view === 'calendar' ? 'bg-[var(--ks-blue-soft)] text-[var(--ks-blue-strong)]' : ''
          "
          :aria-pressed="view === 'calendar'"
          @click="view = 'calendar'"
        >
          {{ t('events.calendar.month') }}
        </button>
      </div>
    </div>

    <div v-if="view === 'agenda'" class="space-y-6">
      <section v-for="[date, rows] in grouped" :key="date">
        <h2 class="mb-2 text-sm font-bold tracking-[0.13em] text-[var(--ks-text-muted)] uppercase">
          {{
            formatDate(new Date(`${date}T12:00:00`), {
              weekday: 'long',
              month: 'long',
              day: 'numeric',
            })
          }}
        </h2>
        <div class="space-y-2">
          <Link
            v-for="event in rows"
            :key="event.id"
            :href="`/events/${event.id}`"
            class="grid gap-3 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-4 transition hover:border-[var(--ks-border-strong)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-gold)] sm:grid-cols-[8rem_minmax(0,1fr)_auto]"
          >
            <div class="text-sm font-semibold">
              {{ formatDate(new Date(event.startsAt), { hour: 'numeric', minute: '2-digit' }) }}
            </div>
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold">{{ displayName(event) }}</h3>
                <span
                  class="rounded-full border border-[var(--ks-border)] px-2 py-0.5 text-[0.68rem] uppercase"
                  >{{ t(`events.scope.${event.scope}`) }}</span
                >
              </div>
              <p class="mt-1 text-xs text-[var(--ks-text-muted)]">{{ event.targetLabel }}</p>
            </div>
            <span v-if="event.canManage" class="text-xs font-semibold text-[var(--ks-gold)]">{{
              t('events.calendar.manageable')
            }}</span>
          </Link>
        </div>
      </section>
      <div
        v-if="grouped.length === 0"
        class="rounded-[var(--ks-radius-lg)] border border-dashed border-[var(--ks-border)] p-10 text-center text-sm text-[var(--ks-text-muted)]"
      >
        {{ t('events.calendar.empty') }}
      </div>
    </div>

    <section
      v-else
      class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-4"
      aria-labelledby="events-month-title"
    >
      <div class="mb-4 flex items-center justify-between gap-3">
        <button
          type="button"
          class="rounded border border-[var(--ks-border)] px-3 py-1.5 text-lg leading-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-gold)]"
          :aria-label="t('events.calendar.previousMonth')"
          @click="moveMonth(-1)"
        >
          ‹
        </button>
        <h2 id="events-month-title" class="font-semibold">
          {{ formatDate(monthCursor, { month: 'long', year: 'numeric' }) }}
        </h2>
        <button
          type="button"
          class="rounded border border-[var(--ks-border)] px-3 py-1.5 text-lg leading-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-gold)]"
          :aria-label="t('events.calendar.nextMonth')"
          @click="moveMonth(1)"
        >
          ›
        </button>
      </div>
      <div class="overflow-x-auto pb-1">
        <div
          class="grid min-w-[44rem] grid-cols-7 gap-px overflow-hidden rounded border border-[var(--ks-border)] bg-[var(--ks-border)]"
        >
          <div
            v-for="date in weekdayDates"
            :key="date.getDay()"
            class="bg-[var(--ks-surface-2)] p-2 text-center text-xs font-bold text-[var(--ks-text-muted)]"
          >
            {{ formatDate(date, { weekday: 'short' }) }}
          </div>
          <div
            v-for="day in monthDays"
            :key="day.key"
            class="min-h-28 bg-[var(--ks-surface-1)] p-2"
            :class="!day.current ? 'opacity-35' : ''"
          >
            <div class="mb-2 text-xs font-semibold">{{ day.date.getDate() }}</div>
            <Link
              v-for="event in day.events.slice(0, 3)"
              :key="event.id"
              :href="`/events/${event.id}`"
              class="mb-1 block truncate rounded bg-[var(--ks-blue-soft)] px-2 py-1 text-[0.68rem] text-[var(--ks-blue-strong)] focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--ks-blue-strong)]"
              >{{ displayName(event) }}</Link
            >
          </div>
        </div>
      </div>
    </section>
  </AppLayout>
</template>
