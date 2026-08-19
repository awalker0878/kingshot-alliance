<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useContextForm } from '@/composables/useContextForm';
import { computed, reactive } from 'vue';

import EventSigil from '@/components/game/EventSigil.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type RallyGuidance = {
  id: string;
  name: string;
  mode: string;
  troopType: string | null;
  notes: string | null;
};
type PlayerFormation = {
  id: string;
  name: string;
  heroOne: string;
  heroTwo: string | null;
  heroThree: string | null;
  troopType: string | null;
  notes: string | null;
  active: boolean;
};
type RallyFormation = {
  id: string;
  name: string;
  status: string;
  rallyCapacity: number | null;
  rallyLeadPlayerId: string | null;
  rallyLeadName: string | null;
  guidanceRule: { id: string; name: string } | null;
};
type RallyAssignment = {
  id: string;
  role: string;
  response: string;
  note: string | null;
  player: { id: string; name: string };
  formation: { id: string; name: string } | null;
  playerFormation: { id: string; name: string } | null;
};
type BattlePlan = {
  id: string;
  title: string;
  objective: string | null;
  content: string | null;
  status: string;
  publishedAt: string | null;
  phases: Array<{
    id: string;
    label: string;
    startsAt: string;
    endsAt: string | null;
    sortOrder: number;
    rallyFormation: { id: string; name: string } | null;
  }>;
};

type Poll = {
  id: string;
  question: string;
  closesAt: string | null;
  allowMultiple: boolean;
  options: Array<{ id: string; label: string; votes: number; selected: boolean }>;
};

type Roster = {
  id: string;
  name: string;
  description: string | null;
  members: Array<{
    id: string;
    player: { id: string; name: string };
    response: string;
    attendance: string | null;
  }>;
};

const props = defineProps<{
  user: { name: string; email: string };
  userTimezone: string;
  occurrence: {
    id: string;
    eventId: string;
    eventTypeSlug: string;
    nameKey: string;
    title: string | null;
    description: string | null;
    scope: 'player' | 'alliance' | 'kingdom';
    targetLabel: string;
    targetAllianceId: string | null;
    startsAt: string;
    endsAt: string;
    timezone: string;
    status: string;
    capabilities: string[];
    registrationMode: string;
    visibility: string;
    canManage: boolean;
    responses: Array<{ playerId: string; response: string; attendance: string | null }>;
    registrations: Array<{ playerId: string; status: string }>;
    phases: Array<{ id: string; label: string; startsAt: string; endsAt: string | null }>;
    polls: Poll[];
    rosters: Roster[];
    reminders: Array<{
      id: string;
      minutesBefore: number;
      channel: string;
      requiredCapability: string | null;
    }>;
    formations: RallyFormation[];
    assignments: RallyAssignment[];
    battlePlans: BattlePlan[];
  };
  activePlayer: { id: string; name: string };
  currentResponse: string | null;
  currentRegistration: string | null;
  playerAssignments: RallyAssignment[];
  availablePlayerFormations: PlayerFormation[];
  availableRallyGuidance: RallyGuidance[];
}>();

const { t, formatDate, formatNumber } = useLocale();
const responseForm = useContextForm({ response: props.currentResponse ?? 'yes', attendance: '' });
const registrationForm = useContextForm({});
const assignmentResponses = reactive<Record<string, { response: string; note: string }>>(
  Object.fromEntries(
    props.playerAssignments.map((assignment) => [
      assignment.id,
      { response: assignment.response, note: assignment.note ?? '' },
    ]),
  ),
);

const displayName = computed(() => props.occurrence.title || t(props.occurrence.nameKey));
const durationLabel = computed(() => {
  const start = new Date(props.occurrence.startsAt).getTime();
  const end = new Date(props.occurrence.endsAt).getTime();
  const minutes = Math.max(0, Math.round((end - start) / 60000));
  if (minutes >= 1440 && minutes % 1440 === 0) return `${minutes / 1440}d`;
  if (minutes >= 60 && minutes % 60 === 0) return `${minutes / 60}h`;
  return `${minutes}m`;
});
const responseCounts = computed(() => {
  const counts: Record<string, number> = {};
  for (const response of props.occurrence.responses)
    counts[response.response] = (counts[response.response] ?? 0) + 1;
  return counts;
});

function respond(): void {
  responseForm.post(`/events/${props.occurrence.id}/responses`, { preserveScroll: true });
}
function register(): void {
  registrationForm.post(`/events/${props.occurrence.id}/registrations`, { preserveScroll: true });
}
function cancelRegistration(): void {
  router.delete(`/events/${props.occurrence.id}/registrations`, { preserveScroll: true });
}
function vote(pollId: string, optionId: string): void {
  router.put(
    `/events/${props.occurrence.id}/polls/${pollId}/vote`,
    { option_id: optionId },
    { preserveScroll: true },
  );
}
function updateRosterResponse(memberId: string, response: string): void {
  router.put(
    `/events/${props.occurrence.id}/roster-members/${memberId}/response`,
    { response },
    { preserveScroll: true },
  );
}
function respondToAssignment(assignmentId: string): void {
  const draft = assignmentResponses[assignmentId];
  if (!draft) return;
  router.put(`/events/${props.occurrence.id}/rally-assignments/${assignmentId}/response`, draft, {
    preserveScroll: true,
  });
}
function eventDate(value: string): string {
  return formatDate(value, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}
function shortDate(value: string): string {
  return formatDate(value, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}
function humanize(value: string): string {
  return value.replaceAll('_', ' ');
}
function statusTone(value: string): 'success' | 'warning' | 'danger' | 'info' {
  if (
    ['completed', 'confirmed', 'accepted', 'yes', 'present', 'published', 'ready'].includes(value)
  )
    return 'success';
  if (['cancelled', 'declined', 'no', 'absent', 'blocked'].includes(value)) return 'danger';
  if (['pending', 'maybe', 'scheduled', 'draft'].includes(value)) return 'warning';
  return 'info';
}
</script>

<template>
  <Head :title="`${displayName} · ${t('events.calendar.title')}`" />

  <AppLayout>
    <RoomBanner
      :eyebrow="t(`events.scope.${occurrence.scope}`)"
      :title="displayName"
      :subtitle="occurrence.description || occurrence.targetLabel"
      image="/images/kingshot/v4/event-command.svg"
      compact
    >
      <template #actions>
        <Link href="/events" class="ks-command-link">← {{ t('events.calendar.title') }}</Link>
        <Link
          v-if="occurrence.canManage"
          :href="`/events/${occurrence.eventId}/manage`"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('events.management.manage') }}
        </Link>
      </template>
      <template #aside>
        <span class="ks-status" :data-tone="statusTone(occurrence.status)">{{
          humanize(occurrence.status)
        }}</span>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-5">
      <StatSeal
        :label="t('events.management.startsAt')"
        :value="shortDate(occurrence.startsAt)"
        icon="◷"
      />
      <StatSeal
        :label="t('events.management.durationMinutes')"
        :value="durationLabel"
        icon="⌛"
        tone="stone"
      />
      <StatSeal
        :label="t('events.management.registrationMode')"
        :value="humanize(occurrence.registrationMode)"
        icon="♟"
        tone="teal"
      />
      <StatSeal
        :label="t('events.management.polls')"
        :value="formatNumber(occurrence.polls.length)"
        icon="◎"
      />
      <StatSeal
        :label="t('events.management.rosters')"
        :value="formatNumber(occurrence.rosters.length)"
        icon="▤"
        tone="teal"
      />
    </section>

    <section class="ks-surface-gold mt-5 p-5 sm:p-6" aria-labelledby="event-response-heading">
      <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center">
        <div>
          <p class="ks-kicker">{{ activePlayer.name }}</p>
          <h2 id="event-response-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('events.participation.yourResponse') }}
          </h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{ eventDate(occurrence.startsAt) }} · {{ occurrence.targetLabel }}
          </p>
          <div class="mt-4 flex flex-wrap gap-2">
            <span v-for="(count, response) in responseCounts" :key="response" class="ks-chip">
              {{ humanize(String(response)) }} · {{ count }}
            </span>
          </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-[minmax(13rem,1fr)_auto]">
          <select v-model="responseForm.response" class="ks-input">
            <option value="yes">{{ t('events.responses.yes') }}</option>
            <option value="maybe">{{ t('events.responses.maybe') }}</option>
            <option value="no">{{ t('events.responses.no') }}</option>
          </select>
          <AppButton type="button" :disabled="responseForm.processing" @click="respond">
            {{ t('events.participation.respond') }}
          </AppButton>

          <template v-if="occurrence.registrationMode !== 'none'">
            <div class="flex flex-wrap gap-2 sm:col-span-2">
              <AppButton
                v-if="!currentRegistration"
                type="button"
                variant="secondary"
                :disabled="registrationForm.processing"
                @click="register"
              >
                {{ t('events.participation.register') }}
              </AppButton>
              <AppButton v-else type="button" variant="danger" @click="cancelRegistration">
                {{ t('events.participation.cancelRegistration') }}
              </AppButton>
              <span v-if="currentRegistration" class="ks-status" data-tone="success">{{
                humanize(currentRegistration)
              }}</span>
            </div>
          </template>
        </div>
      </div>
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.42fr)_minmax(20rem,.58fr)]">
      <div class="min-w-0 space-y-5">
        <section
          v-if="occurrence.phases.length"
          class="ks-surface p-5"
          aria-labelledby="phases-heading"
        >
          <p class="ks-kicker">{{ t('events.operations.phases') }}</p>
          <h2 id="phases-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ t('events.operations.phases') }}
          </h2>
          <ol class="mt-5 space-y-4">
            <li
              v-for="phase in occurrence.phases"
              :key="phase.id"
              class="relative border-s border-[var(--ks-border-strong)] ps-5"
            >
              <span
                class="absolute -start-1.5 top-1 h-3 w-3 rounded-full border border-[var(--ks-gold-dark)] bg-[var(--ks-teal)]"
                aria-hidden="true"
              />
              <h3 class="text-lg font-[var(--ks-font-display)] font-semibold">{{ phase.label }}</h3>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ eventDate(phase.startsAt)
                }}<template v-if="phase.endsAt"> → {{ eventDate(phase.endsAt) }}</template>
              </p>
            </li>
          </ol>
        </section>

        <section
          v-if="occurrence.polls.length"
          class="ks-surface p-5 sm:p-6"
          aria-labelledby="polls-heading"
        >
          <div class="flex items-end justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('events.management.polls') }}</p>
              <h2 id="polls-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{ t('events.operations.polls') }}
              </h2>
            </div>
            <span class="ks-chip">{{ occurrence.polls.length }}</span>
          </div>
          <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <article
              v-for="poll in occurrence.polls"
              :key="poll.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <h3 class="text-lg font-[var(--ks-font-display)] font-semibold">
                {{ poll.question }}
              </h3>
              <p v-if="poll.closesAt" class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('events.operations.closes') }} · {{ eventDate(poll.closesAt) }}
              </p>
              <div class="mt-4 space-y-2">
                <button
                  v-for="option in poll.options"
                  :key="option.id"
                  type="button"
                  class="flex w-full items-center justify-between gap-3 rounded-[var(--ks-radius-sm)] border px-3 py-2 text-start text-sm transition"
                  :class="
                    option.selected
                      ? 'border-[rgba(32,178,163,.48)] bg-[var(--ks-teal-soft)]'
                      : 'border-[var(--ks-border)] bg-black/15 hover:border-[var(--ks-border-strong)]'
                  "
                  @click="vote(poll.id, option.id)"
                >
                  <span>{{ option.label }}</span
                  ><span class="text-xs text-[var(--ks-muted)]">{{ option.votes }}</span>
                </button>
              </div>
            </article>
          </div>
        </section>

        <section
          v-if="occurrence.rosters.length"
          class="ks-surface overflow-hidden"
          aria-labelledby="rosters-heading"
        >
          <div class="border-b border-[var(--ks-border)] p-5">
            <p class="ks-kicker">{{ t('events.management.rosters') }}</p>
            <h2 id="rosters-heading" class="ks-display mt-1 text-2xl font-semibold">
              {{ t('events.operations.rosters') }}
            </h2>
          </div>
          <div class="divide-y divide-[var(--ks-border)]">
            <article v-for="roster in occurrence.rosters" :key="roster.id" class="p-5">
              <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                  <h3 class="text-xl font-[var(--ks-font-display)] font-semibold">
                    {{ roster.name }}
                  </h3>
                  <p v-if="roster.description" class="mt-1 text-sm text-[var(--ks-muted)]">
                    {{ roster.description }}
                  </p>
                </div>
                <span class="ks-chip">{{ roster.members.length }}</span>
              </div>
              <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                <div
                  v-for="member in roster.members"
                  :key="member.id"
                  class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3"
                >
                  <div class="flex items-center justify-between gap-3">
                    <strong class="truncate text-sm">{{ member.player.name }}</strong
                    ><span class="ks-status" :data-tone="statusTone(member.response)">{{
                      humanize(member.response)
                    }}</span>
                  </div>
                  <select
                    v-if="member.player.id === activePlayer.id"
                    class="ks-input mt-3"
                    :value="member.response"
                    @change="
                      updateRosterResponse(member.id, ($event.target as HTMLSelectElement).value)
                    "
                  >
                    <option value="confirmed">{{ t('events.responses.confirmed') }}</option>
                    <option value="declined">{{ t('events.responses.declined') }}</option>
                    <option value="pending">{{ t('events.responses.pending') }}</option>
                  </select>
                </div>
              </div>
            </article>
          </div>
        </section>

        <section
          v-if="playerAssignments.length"
          class="ks-surface p-5 sm:p-6"
          aria-labelledby="assignments-heading"
        >
          <p class="ks-kicker">{{ t('events.rallies.assignments') }}</p>
          <h2 id="assignments-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('events.rallies.yourAssignments') }}
          </h2>
          <div class="mt-4 space-y-3">
            <article
              v-for="assignment in playerAssignments"
              :key="assignment.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h3 class="text-lg font-[var(--ks-font-display)] font-semibold">
                    {{ assignment.formation?.name ?? t('events.rallies.unassignedFormation') }}
                  </h3>
                  <p class="mt-1 text-xs text-[var(--ks-muted)]">
                    {{ humanize(assignment.role)
                    }}<template v-if="assignment.playerFormation">
                      · {{ assignment.playerFormation.name }}</template
                    >
                  </p>
                </div>
                <span class="ks-status" :data-tone="statusTone(assignment.response)">{{
                  humanize(assignment.response)
                }}</span>
              </div>
              <div class="mt-4 grid gap-3 sm:grid-cols-[10rem_1fr_auto]">
                <select v-model="assignmentResponses[assignment.id]!.response" class="ks-input">
                  <option value="pending">{{ t('events.responses.pending') }}</option>
                  <option value="accepted">{{ t('events.responses.accepted') }}</option>
                  <option value="declined">{{ t('events.responses.declined') }}</option>
                </select>
                <input
                  v-model="assignmentResponses[assignment.id]!.note"
                  class="ks-input"
                  :placeholder="t('events.rallies.note')"
                />
                <AppButton
                  type="button"
                  variant="secondary"
                  @click="respondToAssignment(assignment.id)"
                  >{{ t('events.participation.respond') }}</AppButton
                >
              </div>
            </article>
          </div>
        </section>

        <section
          v-if="occurrence.battlePlans.length"
          class="ks-surface p-5 sm:p-6"
          aria-labelledby="battle-plans-heading"
        >
          <p class="ks-kicker">{{ t('events.battlePlans.title') }}</p>
          <h2 id="battle-plans-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('events.battlePlans.title') }}
          </h2>
          <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <article
              v-for="plan in occurrence.battlePlans"
              :key="plan.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <h3 class="text-lg font-[var(--ks-font-display)] font-semibold">
                  {{ plan.title }}
                </h3>
                <span class="ks-status" :data-tone="statusTone(plan.status)">{{
                  humanize(plan.status)
                }}</span>
              </div>
              <p
                v-if="plan.objective"
                class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]"
              >
                {{ plan.objective }}
              </p>
              <p
                v-if="plan.content"
                class="mt-3 line-clamp-5 text-sm leading-6 whitespace-pre-line text-[var(--ks-muted)]"
              >
                {{ plan.content }}
              </p>
              <ol
                v-if="plan.phases.length"
                class="mt-4 space-y-2 border-t border-[var(--ks-border)] pt-4"
              >
                <li
                  v-for="phase in plan.phases"
                  :key="phase.id"
                  class="flex items-start justify-between gap-3 text-xs"
                >
                  <span>{{ phase.label }}</span
                  ><span class="text-[var(--ks-muted)]">{{ shortDate(phase.startsAt) }}</span>
                </li>
              </ol>
            </article>
          </div>
        </section>
      </div>

      <aside class="space-y-5">
        <section class="ks-surface p-5 2xl:sticky 2xl:top-[6.5rem]">
          <div class="flex items-center gap-3">
            <EventSigil :name="displayName" />
            <div class="min-w-0">
              <p class="ks-kicker">{{ t('events.calendar.title') }}</p>
              <h2 class="ks-display truncate text-xl font-semibold">{{ displayName }}</h2>
            </div>
          </div>
          <dl class="mt-5 space-y-4 text-sm">
            <div>
              <dt class="text-xs text-[var(--ks-muted)]">{{ t('events.management.startsAt') }}</dt>
              <dd class="mt-1 font-semibold">{{ eventDate(occurrence.startsAt) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-muted)]">{{ t('events.management.endsAt') }}</dt>
              <dd class="mt-1 font-semibold">{{ eventDate(occurrence.endsAt) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-muted)]">
                {{ t('events.management.visibility') }}
              </dt>
              <dd class="mt-1">
                <span class="ks-chip">{{ humanize(occurrence.visibility) }}</span>
              </dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-muted)]">
                {{ t('events.management.capabilities') }}
              </dt>
              <dd class="mt-2 flex flex-wrap gap-1.5">
                <span
                  v-for="capability in occurrence.capabilities"
                  :key="capability"
                  class="ks-chip"
                  >{{ humanize(capability) }}</span
                >
              </dd>
            </div>
          </dl>

          <template v-if="availablePlayerFormations.length">
            <div class="ks-divider my-5" />
            <p class="ks-kicker">{{ t('events.rallies.savedFormations') }}</p>
            <div class="mt-3 space-y-2">
              <article
                v-for="formation in availablePlayerFormations.slice(0, 4)"
                :key="formation.id"
                class="rounded border border-[var(--ks-border)] bg-black/15 p-3"
              >
                <strong class="text-sm">{{ formation.name }}</strong>
                <p class="mt-1 text-xs text-[var(--ks-muted)]">
                  {{ formation.heroOne
                  }}<template v-if="formation.heroTwo"> · {{ formation.heroTwo }}</template
                  ><template v-if="formation.heroThree"> · {{ formation.heroThree }}</template>
                </p>
              </article>
            </div>
          </template>

          <template v-if="availableRallyGuidance.length">
            <div class="ks-divider my-5" />
            <p class="ks-kicker">{{ t('events.rallies.guidance') }}</p>
            <div class="mt-3 space-y-2">
              <article
                v-for="guidance in availableRallyGuidance.slice(0, 4)"
                :key="guidance.id"
                class="rounded border border-[var(--ks-border)] bg-black/15 p-3"
              >
                <strong class="text-sm">{{ guidance.name }}</strong>
                <p class="mt-1 text-xs text-[var(--ks-muted)]">
                  {{ humanize(guidance.mode)
                  }}<template v-if="guidance.troopType"> · {{ guidance.troopType }}</template>
                </p>
              </article>
            </div>
          </template>
        </section>
      </aside>
    </div>
  </AppLayout>
</template>
