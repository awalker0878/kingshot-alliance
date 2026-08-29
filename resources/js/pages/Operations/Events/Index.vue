<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import EventSigil from '@/components/game/EventSigil.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

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
  workflowDimensions: string[];
  canManage: boolean;
};

type EventBulkPreview = {
  operation: 'cancel';
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

type EventBulkResult = {
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
  eventBulkPreview: EventBulkPreview | null;
  eventBulkResult: EventBulkResult | null;
}>();

const { t, formatDate } = useLocale();
const scope = ref<ScopeFilter>('all');
const view = ref<'agenda' | 'calendar'>('agenda');
const monthCursor = ref(new Date());
const selectedEventIds = ref<string[]>(props.eventBulkResult?.failedItemIds ?? []);
const bulkBusy = ref(false);
const bulkConfirmationOpen = ref(false);
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
const manageableVisibleEventIds = computed(() => [
  ...new Set(visibleEvents.value.filter((event) => event.canManage).map((event) => event.eventId)),
]);
const allVisibleEventsSelected = computed(
  () =>
    manageableVisibleEventIds.value.length > 0 &&
    manageableVisibleEventIds.value.every((eventId) => selectedEventIds.value.includes(eventId)),
);
const bulkPreviewMatchesSelection = computed(() => {
  const preview = props.eventBulkPreview;
  if (!preview) return false;

  const selected = [...selectedEventIds.value].sort();
  const previewed = preview.items.map((item) => item.itemId).sort();
  return (
    selected.length === previewed.length && selected.every((id, index) => id === previewed[index])
  );
});
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

watch(
  () => props.eventBulkResult,
  (result) => {
    if (result) selectedEventIds.value = [...result.failedItemIds];
  },
);

function eventSelected(eventId: string): boolean {
  return selectedEventIds.value.includes(eventId);
}

function setEventSelected(eventId: string, selected: boolean): void {
  if (!selected) {
    selectedEventIds.value = selectedEventIds.value.filter((id) => id !== eventId);
    return;
  }

  selectedEventIds.value = [...new Set([...selectedEventIds.value, eventId])].slice(0, 50);
}

function toggleVisibleEventSelection(): void {
  if (allVisibleEventsSelected.value) {
    const visibleIds = new Set(manageableVisibleEventIds.value);
    selectedEventIds.value = selectedEventIds.value.filter((id) => !visibleIds.has(id));
    return;
  }

  selectedEventIds.value = [
    ...new Set([...selectedEventIds.value, ...manageableVisibleEventIds.value]),
  ].slice(0, 50);
}

function previewBulkCancellation(): void {
  if (selectedEventIds.value.length === 0 || bulkBusy.value) return;

  bulkBusy.value = true;
  router.post(
    '/events/bulk-cancel/preview',
    { event_ids: selectedEventIds.value },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => (bulkBusy.value = false),
    },
  );
}

function commitBulkCancellation(): void {
  if (!props.eventBulkPreview || !bulkPreviewMatchesSelection.value || bulkBusy.value) return;

  bulkBusy.value = true;
  router.post(
    '/events/bulk-cancel',
    { event_ids: props.eventBulkPreview.items.map((item) => item.itemId) },
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
  return t(`events.bulk.outcomes.${code}`);
}
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
function scopeLabel(value: ScopeFilter): string {
  return value === 'all' ? t('events.calendar.all') : t(`events.scope.${value}`);
}
function statusTone(status: string): 'success' | 'warning' | 'danger' | 'info' {
  if (status === 'completed') return 'success';
  if (status === 'cancelled') return 'danger';
  if (status === 'scheduled' || status === 'published') return 'info';
  return 'warning';
}
</script>

<template>
  <Head :title="t('events.calendar.title')" />
  <AppLayout :user="props.user">
    <RoomBanner
      :eyebrow="t('events.calendar.eyebrow')"
      :title="t('navigation.events')"
      :subtitle="t('events.calendar.description')"
      image="/images/kingshot/v4/event-command.svg"
    >
      <template #actions>
        <Link v-if="props.canCreate" href="/events/create" class="ks-command-link">{{
          t('events.calendar.create')
        }}</Link>
        <a href="/events/export.csv" class="ks-command-link" data-variant="secondary">{{
          t('events.calendar.exportCsv')
        }}</a>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
      <StatSeal :label="t('events.calendar.title')" :value="props.events.length" icon="▦" />
      <StatSeal
        :label="t('events.attention.eyebrow')"
        :value="props.attention.length"
        icon="!"
        tone="teal"
      />
      <StatSeal
        :label="t('events.reminders.title')"
        :value="props.reminders.length"
        icon="⌛"
        tone="stone"
      />
      <StatSeal
        :label="t('events.calendar.viewOptions')"
        :value="view === 'agenda' ? t('events.calendar.agenda') : t('events.calendar.month')"
        icon="◉"
      />
    </section>

    <section
      v-if="props.attention.length"
      class="ks-surface-gold mt-5 p-4 sm:p-5"
      aria-labelledby="attention-title"
    >
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p class="ks-kicker">{{ t('events.attention.eyebrow') }}</p>
          <h2 id="attention-title" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('events.attention.title') }}
          </h2>
        </div>
        <span class="ks-status" data-tone="warning">{{ props.attention.length }}</span>
      </div>
      <div class="mt-4 grid gap-3 md:grid-cols-2 2xl:grid-cols-4">
        <Link
          v-for="item in props.attention"
          :key="`${item.occurrenceId}:${item.action}:${item.pollId ?? ''}:${item.rosterMemberId ?? ''}`"
          :href="`/events/${item.occurrenceId}`"
          class="group rounded-[var(--ks-radius-md)] border border-[rgba(226,170,73,.22)] bg-[linear-gradient(145deg,rgba(87,45,24,.18),rgba(8,13,13,.72))] p-4 transition hover:border-[rgba(226,170,73,.45)]"
        >
          <div class="flex items-start gap-3">
            <div
              class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-amber-400/30 bg-amber-500/10 font-bold text-amber-200"
            >
              !
            </div>
            <div class="min-w-0">
              <strong class="block truncate text-sm">{{ item.title || t(item.nameKey) }}</strong>
              <p class="mt-1 text-xs text-amber-200">{{ t(`events.attention.${item.action}`) }}</p>
              <p class="mt-2 text-xs text-[var(--ks-muted)]">
                {{
                  formatDate(new Date(item.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </p>
            </div>
          </div>
        </Link>
      </div>
    </section>

    <div class="ks-toolbar mt-5">
      <div
        class="flex flex-wrap gap-2"
        role="group"
        :aria-label="t('events.calendar.scopeFilters')"
      >
        <button
          v-for="value in scopeOptions"
          :key="value"
          type="button"
          class="ks-chip"
          :data-active="scope === value"
          :aria-pressed="scope === value"
          @click="scope = value"
        >
          {{ scopeLabel(value) }}
        </button>
        <button
          v-if="manageableVisibleEventIds.length"
          type="button"
          class="ks-chip"
          :data-active="allVisibleEventsSelected"
          :aria-pressed="allVisibleEventsSelected"
          @click="toggleVisibleEventSelection"
        >
          {{
            allVisibleEventsSelected
              ? t('events.bulk.clearVisible')
              : t('events.bulk.selectVisible')
          }}
        </button>
      </div>
      <div class="flex gap-2" role="group" :aria-label="t('events.calendar.viewOptions')">
        <button
          type="button"
          class="ks-chip"
          :data-active="view === 'agenda'"
          :aria-pressed="view === 'agenda'"
          @click="view = 'agenda'"
        >
          {{ t('events.calendar.agenda') }}
        </button>
        <button
          type="button"
          class="ks-chip"
          :data-active="view === 'calendar'"
          :aria-pressed="view === 'calendar'"
          @click="view = 'calendar'"
        >
          {{ t('events.calendar.month') }}
        </button>
      </div>
    </div>

    <section
      v-if="selectedEventIds.length"
      class="ks-surface mt-5 overflow-hidden"
      aria-labelledby="event-bulk-heading"
    >
      <div class="flex flex-wrap items-end gap-3 bg-[var(--ks-teal-soft)] p-5">
        <div class="min-w-[14rem] flex-1">
          <p class="ks-kicker">{{ t('events.bulk.eyebrow') }}</p>
          <h2 id="event-bulk-heading" class="mt-1 text-lg font-semibold">
            {{ t('events.bulk.selected', { count: selectedEventIds.length }) }}
          </h2>
          <p class="mt-1 text-xs text-[var(--ks-muted)]">{{ t('events.bulk.help') }}</p>
        </div>
        <AppButton
          :busy="bulkBusy"
          :busy-label="t('events.bulk.previewing')"
          @click="previewBulkCancellation"
        >
          {{ t('events.bulk.preview') }}
        </AppButton>
      </div>

      <div v-if="bulkPreviewMatchesSelection && eventBulkPreview" class="p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="ks-kicker">{{ t('events.bulk.previewTitle') }}</p>
            <p class="mt-1 font-semibold">
              {{
                t('events.bulk.previewSummary', {
                  ready: eventBulkPreview.ready,
                  blocked: eventBulkPreview.blocked,
                })
              }}
            </p>
          </div>
          <AppButton
            variant="danger"
            :disabled="eventBulkPreview.ready === 0"
            @click="bulkConfirmationOpen = true"
          >
            {{ t('events.bulk.confirm') }}
          </AppButton>
        </div>
        <ul class="mt-4 grid gap-2 md:grid-cols-2">
          <li
            v-for="item in eventBulkPreview.items"
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
        v-if="eventBulkResult"
        class="border-t border-[var(--ks-border)] p-5"
        aria-labelledby="event-bulk-result-heading"
      >
        <p class="ks-kicker">{{ t('events.bulk.resultTitle') }}</p>
        <h3 id="event-bulk-result-heading" class="mt-1 text-lg font-semibold">
          {{
            t('events.bulk.resultSummary', {
              succeeded: eventBulkResult.succeeded,
              failed: eventBulkResult.failed,
              skipped: eventBulkResult.skipped,
            })
          }}
        </h3>
        <ul class="mt-4 grid gap-2 md:grid-cols-2">
          <li
            v-for="item in eventBulkResult.items"
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
        <p v-if="eventBulkResult.failed" class="mt-3 text-xs text-[var(--ks-muted)]">
          {{ t('events.bulk.failedSelected') }}
        </p>
      </div>
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,.6fr)]">
      <div class="min-w-0">
        <div v-if="view === 'agenda'" class="space-y-5">
          <section v-for="[date, rows] in grouped" :key="date" class="ks-surface overflow-hidden">
            <div class="border-b border-[var(--ks-border)] bg-black/15 px-4 py-3">
              <p class="ks-kicker">
                {{
                  formatDate(new Date(`${date}T12:00:00`), {
                    weekday: 'long',
                    month: 'long',
                    day: 'numeric',
                  })
                }}
              </p>
            </div>
            <div class="divide-y divide-[var(--ks-border)]">
              <div
                v-for="event in rows"
                :key="event.id"
                class="group grid gap-4 p-4 transition hover:bg-white/[0.018] sm:grid-cols-[1.5rem_7rem_minmax(0,1fr)_auto] sm:items-center"
              >
                <div class="min-h-5">
                  <input
                    v-if="event.canManage"
                    type="checkbox"
                    :checked="eventSelected(event.eventId)"
                    :aria-label="t('events.bulk.selectEvent', { event: displayName(event) })"
                    @change="
                      setEventSelected(event.eventId, ($event.target as HTMLInputElement).checked)
                    "
                  />
                </div>
                <div>
                  <div class="text-xl font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]">
                    {{
                      formatDate(new Date(event.startsAt), { hour: 'numeric', minute: '2-digit' })
                    }}
                  </div>
                  <div class="mt-1 text-[.66rem] text-[var(--ks-muted)]">{{ event.timezone }}</div>
                </div>
                <div class="min-w-0">
                  <div class="flex items-center gap-3">
                    <EventSigil :name="displayName(event)" />
                    <div class="min-w-0">
                      <h3 class="truncate text-lg font-[var(--ks-font-display)] font-semibold">
                        <Link
                          :href="`/events/${event.id}`"
                          class="hover:text-[var(--ks-gold-bright)]"
                        >
                          {{ displayName(event) }}
                        </Link>
                      </h3>
                      <p class="mt-1 truncate text-xs text-[var(--ks-muted)]">
                        {{ event.targetLabel }}
                      </p>
                    </div>
                  </div>
                  <div class="mt-3 flex flex-wrap gap-1.5">
                    <span class="ks-chip">{{ t(`events.scope.${event.scope}`) }}</span
                    ><span class="ks-status" :data-tone="statusTone(event.status)">{{
                      t(`events.occurrenceStatuses.${event.status}`)
                    }}</span>
                  </div>
                </div>
                <Link
                  :href="`/events/${event.id}`"
                  class="ks-status justify-self-start sm:justify-self-end"
                  data-tone="info"
                  >{{
                    event.canManage ? t('events.calendar.manageable') : t('events.calendar.open')
                  }}</Link
                >
              </div>
            </div>
          </section>
          <div v-if="grouped.length === 0" class="ks-fantasy-empty">
            {{ t('events.calendar.empty') }}
          </div>
        </div>

        <section v-else class="ks-surface p-4 sm:p-5" aria-labelledby="events-month-title">
          <div class="mb-4 flex items-center justify-between gap-3">
            <button
              type="button"
              class="grid h-10 w-10 place-items-center rounded border border-[var(--ks-border)]"
              :aria-label="t('events.calendar.previousMonth')"
              @click="moveMonth(-1)"
            >
              ‹
            </button>
            <h2 id="events-month-title" class="ks-display text-xl font-semibold">
              {{ formatDate(monthCursor, { month: 'long', year: 'numeric' }) }}
            </h2>
            <button
              type="button"
              class="grid h-10 w-10 place-items-center rounded border border-[var(--ks-border)]"
              :aria-label="t('events.calendar.nextMonth')"
              @click="moveMonth(1)"
            >
              ›
            </button>
          </div>
          <div class="overflow-x-auto pb-1">
            <div
              class="grid min-w-[44rem] grid-cols-7 gap-px overflow-hidden rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-border)]"
            >
              <div
                v-for="date in weekdayDates"
                :key="date.getDay()"
                class="bg-[var(--ks-surface-2)] p-2 text-center text-xs font-bold text-[var(--ks-muted)]"
              >
                {{ formatDate(date, { weekday: 'short' }) }}
              </div>
              <div
                v-for="day in monthDays"
                :key="day.key"
                class="min-h-28 bg-[var(--ks-bg-elevated)] p-2"
                :class="day.current ? '' : 'opacity-35'"
              >
                <div class="text-xs font-semibold">{{ day.date.getDate() }}</div>
                <div class="mt-2 space-y-1">
                  <Link
                    v-for="event in day.events.slice(0, 3)"
                    :key="event.id"
                    :href="`/events/${event.id}`"
                    class="block truncate rounded border border-[var(--ks-border)] bg-black/20 px-2 py-1 text-[.65rem] hover:border-[var(--ks-border-strong)]"
                    >{{ displayName(event) }}</Link
                  >
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>

      <aside class="space-y-5">
        <section class="ks-surface p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('events.reminders.eyebrow') }}</p>
              <h2 class="ks-display mt-1 text-xl font-semibold">
                {{ t('events.reminders.title') }}
              </h2>
            </div>
            <span class="ks-status" data-tone="info">{{ props.reminders.length }}</span>
          </div>
          <div v-if="props.reminders.length" class="mt-4 space-y-2">
            <Link
              v-for="reminder in props.reminders"
              :key="reminder.id"
              :href="`/events/${reminder.occurrenceId}`"
              class="block rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-3 transition hover:border-[var(--ks-border-strong)]"
              ><strong class="block truncate text-sm">{{
                reminder.title || t(reminder.nameKey)
              }}</strong>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('events.reminders.starts') }} ·
                {{
                  formatDate(new Date(reminder.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </p></Link
            >
          </div>
          <p v-else class="mt-4 text-sm text-[var(--ks-muted)]">{{ t('events.calendar.empty') }}</p>
        </section>

        <section class="ks-surface p-5">
          <p class="ks-kicker">{{ t('events.calendar.viewOptions') }}</p>
          <h2 class="ks-display mt-1 text-xl font-semibold">
            {{ formatDate(monthCursor, { month: 'long', year: 'numeric' }) }}
          </h2>
          <div class="mt-4 grid grid-cols-7 gap-1 text-center text-[.68rem]">
            <div
              v-for="date in weekdayDates"
              :key="date.getDay()"
              class="py-1 text-[var(--ks-muted)]"
            >
              {{ formatDate(date, { weekday: 'narrow' }) }}
            </div>
            <div
              v-for="day in monthDays"
              :key="`mini-${day.key}`"
              class="relative grid aspect-square place-items-center rounded"
              :class="[
                day.current
                  ? 'text-[var(--ks-text-secondary)]'
                  : 'text-[var(--ks-muted)] opacity-35',
                day.events.length
                  ? 'border border-[rgba(32,178,163,.32)] bg-[var(--ks-teal-soft)]'
                  : '',
              ]"
            >
              {{ day.date.getDate()
              }}<span
                v-if="day.events.length"
                class="absolute bottom-1 h-1 w-1 rounded-full bg-[var(--ks-teal-bright)]"
              />
            </div>
          </div>
        </section>
      </aside>
    </div>
    <ConfirmActionDialog
      id="event-bulk-cancellation-confirmation"
      :open="bulkConfirmationOpen"
      :title="t('events.bulk.confirmTitle')"
      :description="t('events.bulk.confirmDescription', { count: eventBulkPreview?.ready ?? 0 })"
      :confirm-label="t('events.bulk.confirm')"
      :cancel-label="t('common.cancel')"
      :busy="bulkBusy"
      :busy-label="t('events.bulk.applying')"
      danger
      @confirm="commitBulkCancellation"
      @cancel="bulkConfirmationOpen = false"
    />
  </AppLayout>
</template>
