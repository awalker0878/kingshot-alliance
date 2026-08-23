<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import EventSigil from '@/components/game/EventSigil.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Phase = {
  id: string;
  key: string;
  nameKey: string | null;
  name: string | null;
  startsAt: string | null;
  endsAt: string | null;
  status: string;
};
type Poll = {
  id: string;
  questionKey: string | null;
  question: string | null;
  closesAt: string | null;
  status: string;
  votingOpen: boolean;
  maxChoices: number;
  selectedOptionIds: string[];
  options: Array<{ id: string; label: string; votes: number | null }>;
};
type Assignment = {
  id: string;
  rosterName: string | null;
  rosterNameKey: string | null;
  role: string | null;
  slotNumber: number | null;
  status: string;
  warnings: string[];
  notes: string | null;
};
type RallyAssignment = {
  id: string;
  groupName: string;
  allianceName: string;
  role: string;
  slotNumber: number | null;
  status: string;
  notes: string | null;
  recommendedFormationName: string | null;
};
type Objective = {
  id: string;
  name: string;
  description: string | null;
  priority: number;
  startsAt: string | null;
  endsAt: string | null;
  status: string;
  assignments: Array<{ id: string; rosterName: string | null; playerName: string | null }>;
};
type KnowledgeItem = {
  id: string;
  slug: string;
  title: string;
  summary: string | null;
  typeLabel: string;
  freshness: { status: string; dueAt: string | null };
};
type ResultSummary = {
  outcome: string | null;
  score: number | null;
  opponentScore: number | null;
  rank: number | null;
  notes: string | null;
};
type Participation = {
  playerId: string;
  playerName: string;
  response: {
    response: 'going' | 'maybe' | 'unavailable';
    preferredRole: string | null;
    preferredTeam: string | null;
    note: string | null;
  } | null;
  registration: { status: string; waitlistPosition: number | null } | null;
  attendance: { status: string; notes: string | null } | null;
  registrationWindow: { opensAt: string | null; closesAt: string; isOpen: boolean };
};

const props = defineProps<{
  user: { name: string; email: string };
  userTimezone: string;
  event: {
    id: string;
    eventId: string;
    eventTypeSlug: string;
    nameKey: string;
    title: string | null;
    scope: 'player' | 'alliance' | 'kingdom';
    targetLabel: string;
    startsAt: string;
    endsAt: string;
    timezone: string;
    status: string;
    capabilities: string[];
    instructions: string | null;
    capacity: number | null;
    recurrenceFrequency: string;
    recurrenceInterval: number;
    canManage: boolean;
    participation: Participation | null;
    operations: { phases: Phase[]; polls: Poll[] };
    battlePlan: { objectives: Objective[]; myAssignmentIds: string[] };
    results: { summary: ResultSummary | null; player: ResultSummary | null };
    rosters: Assignment[];
    rallies: {
      savedFormations: Array<{ id: string; name: string; heroes: string[] }>;
      guidance: Array<{ id: string; name: string; allianceName: string; rationale: string | null }>;
      myAssignments: RallyAssignment[];
    };
    knowledge: KnowledgeItem[];
  };
}>();

const { t, formatDate, formatNumber } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
const displayName = computed(() => props.event.title || t(props.event.nameKey));
const responseForm = useForm({
  response: props.event.participation?.response?.response ?? 'going',
  preferred_role: props.event.participation?.response?.preferredRole ?? '',
  preferred_team: props.event.participation?.response?.preferredTeam ?? '',
  note: props.event.participation?.response?.note ?? '',
});
const registrationForm = useForm({});
const hasActiveRegistration = computed(() =>
  ['registered', 'waitlisted'].includes(props.event.participation?.registration?.status ?? ''),
);
const pollSelections = reactive<Record<string, string[]>>(
  Object.fromEntries(props.event.operations.polls.map((poll) => [poll.id, poll.selectedOptionIds])),
);

const durationLabel = computed(() => {
  const minutes = Math.max(
    0,
    Math.round(
      (new Date(props.event.endsAt).getTime() - new Date(props.event.startsAt).getTime()) / 60000,
    ),
  );
  if (minutes >= 1440 && minutes % 1440 === 0) return `${minutes / 1440}d`;
  if (minutes >= 60 && minutes % 60 === 0) return `${minutes / 60}h`;
  return `${minutes}m`;
});

function respond(): void {
  responseForm.post(`/events/${props.event.id}/responses`, { preserveScroll: true });
}
function register(): void {
  registrationForm.post(`/events/${props.event.id}/registrations`, { preserveScroll: true });
}
function cancelRegistration(): void {
  requestConfirmation({
    id: 'event-registration-cancellation',
    title: t('events.participation.cancelRegistrationTitle'),
    description: t('events.participation.cancelRegistrationDescription'),
    confirmLabel: t('events.participation.cancelRegistration'),
    cancelLabel: t('common.cancel'),
    busyLabel: t('events.participation.cancellingRegistration'),
    perform: (finish) =>
      router.delete(`/events/${props.event.id}/registrations`, {
        preserveScroll: true,
        onFinish: finish,
      }),
  });
}
function vote(poll: Poll, optionId: string): void {
  if (!poll.votingOpen || !props.event.participation) return;
  const selected = pollSelections[poll.id] ?? [];
  const next =
    poll.maxChoices === 1
      ? [optionId]
      : selected.includes(optionId)
        ? selected.filter((id) => id !== optionId)
        : [...selected, optionId].slice(-poll.maxChoices);
  if (!next.length) return;
  pollSelections[poll.id] = next;
  router.put(
    `/events/${props.event.id}/polls/${poll.id}/vote`,
    { option_ids: next },
    { preserveScroll: true },
  );
}
function respondToRoster(assignmentId: string, status: 'confirmed' | 'declined'): void {
  router.put(
    `/events/${props.event.id}/roster-members/${assignmentId}/response`,
    { status },
    { preserveScroll: true },
  );
}
function respondToRally(assignmentId: string, status: 'confirmed' | 'declined'): void {
  router.put(
    `/events/${props.event.id}/rally-assignments/${assignmentId}/response`,
    { status },
    { preserveScroll: true },
  );
}
function eventDate(value: string | null): string {
  return value
    ? formatDate(value, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
      })
    : '—';
}
function humanize(value: string): string {
  return value.replaceAll('_', ' ');
}
function phaseName(phase: Phase): string {
  return phase.name || (phase.nameKey ? t(phase.nameKey) : humanize(phase.key));
}
function pollQuestion(poll: Poll): string {
  return poll.question || (poll.questionKey ? t(poll.questionKey) : '—');
}
function statusTone(value: string): 'success' | 'warning' | 'danger' | 'info' {
  if (
    ['completed', 'confirmed', 'registered', 'going', 'present', 'published', 'current'].includes(
      value,
    )
  )
    return 'success';
  if (['cancelled', 'declined', 'unavailable', 'absent', 'stale'].includes(value)) return 'danger';
  if (['pending', 'maybe', 'scheduled', 'waitlisted', 'due_soon'].includes(value)) return 'warning';
  return 'info';
}
</script>

<template>
  <Head :title="`${displayName} · ${t('events.calendar.title')}`" />

  <AppLayout :user="user">
    <RoomBanner
      :eyebrow="t(`events.scope.${event.scope}`)"
      :title="displayName"
      :subtitle="event.targetLabel"
      image="/images/kingshot/v4/event-command.svg"
      compact
    >
      <template #actions>
        <Link href="/events" class="ks-command-link">← {{ t('events.show.back') }}</Link>
        <Link
          v-if="event.canManage"
          :href="`/events/${event.eventId}/manage`"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('events.show.manage') }}
        </Link>
        <Link
          v-if="event.eventTypeSlug === 'bear-hunt' && event.capabilities.includes('results')"
          :href="`/events/${event.id}/debrief`"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('debrief.title') }}
        </Link>
        <Link
          v-if="
            event.canManage &&
            event.eventTypeSlug === 'bear-hunt' &&
            event.capabilities.includes('results')
          "
          :href="`/events/${event.id}/screenshot-intake`"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('evidence.openIntake') }}
        </Link>
      </template>
      <template #aside>
        <span class="ks-status" :data-tone="statusTone(event.status)">{{
          humanize(event.status)
        }}</span>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <StatSeal :label="t('events.create.start')" :value="eventDate(event.startsAt)" icon="◷" />
      <StatSeal
        :label="t('events.create.duration')"
        :value="durationLabel"
        icon="⌛"
        tone="stone"
      />
      <StatSeal
        :label="t('events.show.capacity')"
        :value="event.capacity === null ? '—' : formatNumber(event.capacity)"
        icon="♟"
        tone="teal"
      />
      <StatSeal
        :label="t('events.show.modules')"
        :value="formatNumber(event.capabilities.length)"
        icon="▤"
      />
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.42fr)_minmax(20rem,.58fr)]">
      <div class="min-w-0 space-y-5">
        <section
          v-if="event.participation"
          class="ks-surface-gold p-5 sm:p-6"
          aria-labelledby="participation-heading"
        >
          <p class="ks-kicker">{{ event.participation.playerName }}</p>
          <h2 id="participation-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('events.participation.response') }}
          </h2>
          <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="respond">
            <label class="block text-sm">
              <span>{{ t('events.participation.response') }}</span>
              <select v-model="responseForm.response" class="ks-input mt-1.5">
                <option value="going">{{ t('events.responses.going') }}</option>
                <option value="maybe">{{ t('events.responses.maybe') }}</option>
                <option value="unavailable">{{ t('events.responses.unavailable') }}</option>
              </select>
            </label>
            <label class="block text-sm">
              <span>{{ t('events.show.preferredRole') }}</span>
              <input v-model="responseForm.preferred_role" class="ks-input mt-1.5" maxlength="64" />
            </label>
            <label class="block text-sm md:col-span-2">
              <span>{{ t('events.show.responseNote') }}</span>
              <textarea
                v-model="responseForm.note"
                class="ks-input mt-1.5 min-h-20"
                maxlength="2000"
              />
            </label>
            <AppButton class="md:w-fit" type="submit" :disabled="responseForm.processing">
              {{ t('events.show.saveResponse') }}
            </AppButton>
          </form>
          <div
            v-if="event.capabilities.includes('registration')"
            class="mt-5 border-t border-[var(--ks-border)] pt-4"
          >
            <div class="flex flex-wrap items-center gap-3">
              <span
                v-if="event.participation.registration"
                class="ks-status"
                :data-tone="statusTone(event.participation.registration.status)"
              >
                {{ humanize(event.participation.registration.status) }}
              </span>
              <AppButton
                v-if="!hasActiveRegistration && event.participation.registrationWindow.isOpen"
                type="button"
                variant="secondary"
                :disabled="registrationForm.processing"
                @click="register"
              >
                {{ t('events.participation.register') }}
              </AppButton>
              <AppButton
                v-else-if="hasActiveRegistration"
                type="button"
                variant="danger"
                @click="cancelRegistration"
              >
                {{ t('events.participation.cancelRegistration') }}
              </AppButton>
              <p v-else class="text-sm text-[var(--ks-muted)]">
                {{ t('events.participation.registrationClosed') }}
              </p>
            </div>
          </div>
        </section>

        <section
          v-if="event.knowledge.length"
          class="ks-surface p-5 sm:p-6"
          aria-labelledby="event-knowledge-heading"
        >
          <p class="ks-kicker">{{ t('events.show.knowledgeEyebrow') }}</p>
          <h2 id="event-knowledge-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('events.show.knowledge') }}
          </h2>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <Link
              v-for="item in event.knowledge"
              :key="item.id"
              :href="`/alliance/content/${item.slug}`"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 transition hover:border-[var(--ks-border-strong)]"
            >
              <div class="flex items-start justify-between gap-3">
                <strong>{{ item.title }}</strong>
                <span class="ks-status" :data-tone="statusTone(item.freshness.status)">{{
                  t(`contentExperience.freshness.${item.freshness.status}`)
                }}</span>
              </div>
              <p v-if="item.summary" class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
                {{ item.summary }}
              </p>
            </Link>
          </div>
        </section>

        <section
          v-if="event.operations.phases.length"
          class="ks-surface p-5 sm:p-6"
          aria-labelledby="phases-heading"
        >
          <p class="ks-kicker">{{ t('events.phases.eyebrow') }}</p>
          <h2 id="phases-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('events.phases.title') }}
          </h2>
          <ol class="mt-4 space-y-3">
            <li
              v-for="phase in event.operations.phases"
              :key="phase.id"
              class="border-s border-[var(--ks-border-strong)] ps-4"
            >
              <div class="flex flex-wrap items-center justify-between gap-2">
                <strong>{{ phaseName(phase) }}</strong>
                <span class="ks-status" :data-tone="statusTone(phase.status)">{{
                  humanize(phase.status)
                }}</span>
              </div>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ eventDate(phase.startsAt) }} → {{ eventDate(phase.endsAt) }}
              </p>
            </li>
          </ol>
        </section>

        <section
          v-if="event.operations.polls.length"
          class="ks-surface p-5 sm:p-6"
          aria-labelledby="polls-heading"
        >
          <p class="ks-kicker">{{ t('events.show.pollsEyebrow') }}</p>
          <h2 id="polls-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('events.show.polls') }}
          </h2>
          <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <article
              v-for="poll in event.operations.polls"
              :key="poll.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <strong>{{ pollQuestion(poll) }}</strong>
                <span class="ks-status" :data-tone="statusTone(poll.status)">{{
                  humanize(poll.status)
                }}</span>
              </div>
              <p v-if="poll.closesAt" class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('events.show.closes') }} {{ eventDate(poll.closesAt) }}
              </p>
              <div class="mt-3 space-y-2">
                <button
                  v-for="option in poll.options"
                  :key="option.id"
                  type="button"
                  class="flex w-full items-center justify-between gap-3 rounded border border-[var(--ks-border)] p-3 text-start text-sm"
                  :class="
                    pollSelections[poll.id]?.includes(option.id)
                      ? 'bg-[var(--ks-teal-soft)]'
                      : 'bg-black/10'
                  "
                  :disabled="!poll.votingOpen || !event.participation"
                  @click="vote(poll, option.id)"
                >
                  <span>{{ option.label }}</span>
                  <span v-if="option.votes !== null" class="text-xs text-[var(--ks-muted)]">{{
                    option.votes
                  }}</span>
                </button>
              </div>
            </article>
          </div>
        </section>

        <section
          v-if="event.rosters.length"
          class="ks-surface p-5 sm:p-6"
          aria-labelledby="rosters-heading"
        >
          <p class="ks-kicker">{{ t('events.rosters.eyebrow') }}</p>
          <h2 id="rosters-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('events.rosters.title') }}
          </h2>
          <div class="mt-4 space-y-3">
            <article
              v-for="assignment in event.rosters"
              :key="assignment.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <strong>{{
                    assignment.rosterName ||
                    (assignment.rosterNameKey ? t(assignment.rosterNameKey) : '—')
                  }}</strong>
                  <p class="mt-1 text-xs text-[var(--ks-muted)]">{{ assignment.role || '—' }}</p>
                </div>
                <span class="ks-status" :data-tone="statusTone(assignment.status)">{{
                  humanize(assignment.status)
                }}</span>
              </div>
              <div
                v-if="['assigned', 'confirmed', 'declined'].includes(assignment.status)"
                class="mt-3 flex flex-wrap gap-2"
              >
                <AppButton
                  type="button"
                  variant="secondary"
                  @click="respondToRoster(assignment.id, 'confirmed')"
                  >{{ t('events.rosters.confirm') }}</AppButton
                >
                <AppButton
                  type="button"
                  variant="danger"
                  @click="respondToRoster(assignment.id, 'declined')"
                  >{{ t('events.rosters.decline') }}</AppButton
                >
              </div>
            </article>
          </div>
        </section>

        <section
          v-if="event.rallies.myAssignments.length"
          class="ks-surface p-5 sm:p-6"
          aria-labelledby="rallies-heading"
        >
          <p class="ks-kicker">{{ t('events.rallies.eyebrow') }}</p>
          <h2 id="rallies-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('events.rallies.myAssignments') }}
          </h2>
          <div class="mt-4 space-y-3">
            <article
              v-for="assignment in event.rallies.myAssignments"
              :key="assignment.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <strong>{{ assignment.groupName }}</strong>
                  <p class="mt-1 text-xs text-[var(--ks-muted)]">
                    {{ assignment.allianceName }} · {{ humanize(assignment.role) }}
                  </p>
                </div>
                <span class="ks-status" :data-tone="statusTone(assignment.status)">{{
                  humanize(assignment.status)
                }}</span>
              </div>
              <div
                v-if="['assigned', 'confirmed', 'declined'].includes(assignment.status)"
                class="mt-3 flex flex-wrap gap-2"
              >
                <AppButton
                  type="button"
                  variant="secondary"
                  @click="respondToRally(assignment.id, 'confirmed')"
                  >{{ t('events.rallies.confirm') }}</AppButton
                >
                <AppButton
                  type="button"
                  variant="danger"
                  @click="respondToRally(assignment.id, 'declined')"
                  >{{ t('events.rallies.decline') }}</AppButton
                >
              </div>
            </article>
          </div>
        </section>

        <section
          v-if="event.battlePlan.objectives.length"
          class="ks-surface p-5 sm:p-6"
          aria-labelledby="objectives-heading"
        >
          <p class="ks-kicker">{{ t('events.objectives.eyebrow') }}</p>
          <h2 id="objectives-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('events.objectives.title') }}
          </h2>
          <div class="mt-4 grid gap-3 lg:grid-cols-2">
            <article
              v-for="objective in event.battlePlan.objectives"
              :key="objective.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <strong>{{ objective.name }}</strong>
                <span class="ks-status" :data-tone="statusTone(objective.status)">{{
                  humanize(objective.status)
                }}</span>
              </div>
              <p v-if="objective.description" class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
                {{ objective.description }}
              </p>
            </article>
          </div>
        </section>

        <section
          v-if="event.results.summary || event.results.player"
          class="ks-surface p-5 sm:p-6"
          aria-labelledby="results-heading"
        >
          <p class="ks-kicker">{{ t('events.results.eyebrow') }}</p>
          <h2 id="results-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('events.results.title') }}
          </h2>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <article
              v-for="(result, key) in {
                event: event.results.summary,
                player: event.results.player,
              }"
              v-show="result"
              :key="key"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <p class="ks-kicker">{{ t(`events.show.${key}Result`) }}</p>
              <p class="mt-2 text-2xl font-semibold">{{ result?.score ?? '—' }}</p>
              <p class="mt-1 text-sm text-[var(--ks-muted)]">{{ result?.outcome || '—' }}</p>
            </article>
          </div>
        </section>
      </div>

      <aside class="space-y-5">
        <section class="ks-surface p-5 2xl:sticky 2xl:top-[6.5rem]">
          <div class="flex items-center gap-3">
            <EventSigil :name="displayName" />
            <div class="min-w-0">
              <p class="ks-kicker">{{ t('events.show.details') }}</p>
              <h2 class="ks-display truncate text-xl font-semibold">{{ displayName }}</h2>
            </div>
          </div>
          <dl class="mt-5 space-y-4 text-sm">
            <div>
              <dt class="text-xs text-[var(--ks-muted)]">{{ t('events.create.start') }}</dt>
              <dd class="mt-1 font-semibold">{{ eventDate(event.startsAt) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-muted)]">{{ t('events.show.endsAt') }}</dt>
              <dd class="mt-1 font-semibold">{{ eventDate(event.endsAt) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-muted)]">{{ t('contentExperience.timezone') }}</dt>
              <dd class="mt-1 font-semibold">{{ event.timezone }}</dd>
            </div>
          </dl>
          <div class="ks-divider my-5" />
          <p class="ks-kicker">{{ t('events.show.instructions') }}</p>
          <p class="mt-2 text-sm leading-6 whitespace-pre-line text-[var(--ks-muted)]">
            {{ event.instructions || t('events.show.noInstructions') }}
          </p>
          <div class="ks-divider my-5" />
          <p class="ks-kicker">{{ t('events.show.modules') }}</p>
          <div class="mt-3 flex flex-wrap gap-1.5">
            <span v-for="capability in event.capabilities" :key="capability" class="ks-chip">{{
              t(`events.capabilities.${capability}`)
            }}</span>
          </div>
        </section>
      </aside>
    </div>
    <ConfirmActionDialog v-bind="dialog" @confirm="confirmAction" @cancel="cancelConfirmation" />
  </AppLayout>
</template>
