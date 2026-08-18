<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Direction = 'staying' | 'outgoing' | 'incoming';
type Readiness = 'not_started' | 'preparing' | 'ready' | 'blocked' | 'confirmed' | 'withdrawn';

type Plan = {
  id: string;
  label: string;
  homeKingdom: string;
  state: string;
  completable: boolean;
};

type Completion = {
  completedAt: string;
  completedBy: { name: string } | null;
  rosterEntry: { id: string; name: string; state: string; gamePlayerId: string | null } | null;
};

type Participant = {
  id: string;
  name: string;
  direction: Direction;
  readiness: Readiness;
  gamePlayerId: string | null;
  destinationKingdom: string | null;
  withdrawnAt: string | null;
  completion: Completion | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  plan: Plan | null;
  participants: Participant[];
}>();

const { t, formatDate } = useLocale();

const completionCounts = computed(() => ({
  completed: props.participants.filter((participant) => participant.completion !== null).length,
  confirmed: props.participants.filter(
    (participant) =>
      participant.readiness === 'confirmed' &&
      participant.completion === null &&
      participant.withdrawnAt === null,
  ).length,
  withdrawn: props.participants.filter((participant) => participant.withdrawnAt !== null).length,
}));

function stateLabel(state: string): string {
  const key: Record<string, string> = {
    draft: 'stateDraft',
    open: 'stateOpen',
    locked: 'stateLocked',
    closed: 'stateClosed',
    cancelled: 'stateCancelled',
    active: 'rosterActive',
    tracked: 'rosterTracked',
    left: 'rosterLeft',
  };
  return t(`kingdomP7D.${key[state] ?? 'state'}`);
}

function readinessLabel(state: Readiness): string {
  const key: Record<Readiness, string> = {
    not_started: 'readinessNotStarted',
    preparing: 'readinessPreparing',
    ready: 'readinessReady',
    blocked: 'readinessBlocked',
    confirmed: 'readinessConfirmed',
    withdrawn: 'readinessWithdrawn',
  };
  return t(`kingdomP7D.${key[state]}`);
}

function directionLabel(direction: Direction): string {
  const key = {
    staying: 'directionStaying',
    outgoing: 'directionOutgoing',
    incoming: 'directionIncoming',
  } as const;
  return t(`kingdomP7D.${key[direction]}`);
}

function destinationLabel(participant: Participant): string {
  if (participant.direction === 'staying') return t('kingdomP7D.staying');
  if (participant.direction === 'outgoing' && participant.destinationKingdom === null)
    return t('kingdomP7D.undecided');
  return participant.destinationKingdom ?? '—';
}

function canComplete(participant: Participant): boolean {
  return (
    props.plan !== null &&
    props.plan.completable &&
    participant.readiness === 'confirmed' &&
    participant.withdrawnAt === null &&
    participant.completion === null
  );
}

function confirmationText(participant: Participant): string {
  if (participant.direction === 'incoming')
    return t('kingdomP7D.completeIncomingConfirm', { name: participant.name });
  if (participant.direction === 'outgoing')
    return t('kingdomP7D.completeOutgoingConfirm', { name: participant.name });
  return t('kingdomP7D.completeStayingConfirm', { name: participant.name });
}

function completeParticipant(participant: Participant): void {
  if (props.plan === null || !canComplete(participant)) return;
  if (!window.confirm(confirmationText(participant))) return;
  router.post(
    `/alliance/transfers/${props.plan.id}/participants/${participant.id}/complete`,
    {},
    { preserveScroll: true },
  );
}

function timestamp(value: string): string {
  return formatDate(value, { dateStyle: 'medium', timeStyle: 'short' });
}
</script>

<template>
  <Head :title="`${t('kingdomP7D.completionTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('kingdomP7D.completionEyebrow')"
      :title="t('kingdomP7D.completionTitle')"
      :subtitle="
        t('kingdomP7D.completionSubtitle', {
          alliance: alliance.name,
          kingdom: alliance.kingdom ?? t('kingdomP7D.notConfigured'),
        })
      "
      image="/images/kingshot/v4/kingdom-transfer.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/transfers" class="ks-command-link" data-variant="secondary">
          ← {{ t('kingdomP7D.title') }}
        </Link>
        <Link href="/alliance/transfers/readiness" class="ks-command-link">
          {{ t('kingdomP7D.readinessBoard') }}
        </Link>
        <Link href="/alliance/transfers/manage" class="ks-command-link">
          {{ t('kingdomP7D.manageTransfers') }}
        </Link>
      </template>
    </RoomBanner>

    <section
      v-if="plan"
      class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
      :aria-label="t('kingdomP7D.summary')"
    >
      <StatSeal :label="t('kingdomP7D.cycle')" :value="plan.label" icon="◇" />
      <StatSeal
        :label="t('kingdomP7D.participants')"
        :value="participants.length"
        icon="♟"
        tone="stone"
      />
      <StatSeal
        :label="t('kingdomP7D.readinessConfirmed')"
        :value="completionCounts.confirmed"
        icon="✓"
        tone="teal"
      />
      <StatSeal :label="t('kingdomP7D.completed')" :value="completionCounts.completed" icon="✦" />
    </section>

    <section v-if="plan" class="ks-surface mt-6 p-5 sm:p-6">
      <h2 class="text-xl font-semibold">{{ plan.label }}</h2>
      <p class="mt-2 text-sm text-[var(--ks-text-muted)]">
        {{ t('kingdomP7D.homeKingdom') }} {{ plan.homeKingdom }} · {{ stateLabel(plan.state) }}
      </p>
      <p class="mt-4 max-w-4xl text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('kingdomP7D.completionHelp') }}
      </p>
      <p
        v-if="!plan.completable"
        class="mt-3 rounded-lg border border-amber-400/25 bg-amber-500/10 p-3 text-sm font-semibold text-amber-200"
      >
        {{ t('kingdomP7D.completionLockedHelp') }}
      </p>
    </section>

    <section v-if="plan && participants.length" class="mt-6 grid gap-5">
      <article
        v-for="participant in participants"
        :key="participant.id"
        class="ks-surface p-5 sm:p-6"
      >
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h2 class="text-xl font-semibold">{{ participant.name }}</h2>
            <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
              {{ directionLabel(participant.direction) }} · {{ t('kingdomP7D.destination') }}
              {{ destinationLabel(participant) }} · {{ t('kingdomP7D.readiness') }}
              {{ readinessLabel(participant.readiness) }}
            </p>
            <p v-if="participant.gamePlayerId" class="mt-1 text-xs text-[var(--ks-text-muted)]">
              {{ t('kingdomP7D.stableGameId', { id: participant.gamePlayerId }) }}
            </p>
          </div>
          <span
            :class="
              participant.completion
                ? 'border-green-400/25 bg-green-500/10 text-green-200'
                : 'border-[var(--ks-border)] bg-[rgba(210,163,75,.05)] text-[var(--ks-text-secondary)]'
            "
            class="rounded-full border px-3 py-1 text-sm font-semibold"
            >{{
              participant.completion
                ? t('kingdomP7D.completedStatus')
                : t('kingdomP7D.notCompletedStatus')
            }}</span
          >
        </div>

        <section
          v-if="participant.completion"
          class="mt-5 rounded-xl border border-green-400/20 bg-green-500/[0.06] p-4"
        >
          <h3 class="font-semibold text-green-200">{{ t('kingdomP7D.rosterHandoffRecorded') }}</h3>
          <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
            {{ timestamp(participant.completion.completedAt) }} ·
            {{ participant.completion.completedBy?.name ?? t('kingdomP7D.unknownActor') }}
          </p>
          <p
            v-if="participant.completion.rosterEntry"
            class="mt-2 text-sm text-[var(--ks-text-secondary)]"
          >
            {{
              t('kingdomP7D.rosterResult', {
                name: participant.completion.rosterEntry.name,
                state: stateLabel(participant.completion.rosterEntry.state),
              })
            }}
          </p>
          <p class="mt-2 text-xs leading-5 text-[var(--ks-text-muted)]">
            {{ t('kingdomP7D.completionIdempotent') }}
          </p>
        </section>

        <section
          v-else-if="participant.withdrawnAt !== null"
          class="mt-5 rounded-xl border border-[var(--ks-border)] p-4 text-sm text-[var(--ks-text-muted)]"
        >
          {{ t('kingdomP7D.withdrawnCannotComplete') }}
        </section>

        <section
          v-else
          class="mt-5 rounded-xl border border-[var(--ks-border)] bg-[var(--ks-parchment)]/[0.02] p-4"
        >
          <p
            v-if="participant.direction === 'outgoing'"
            class="text-sm leading-6 text-[var(--ks-text-secondary)]"
          >
            {{ t('kingdomP7D.outgoingCompletionHelp') }}
          </p>
          <p
            v-if="participant.direction === 'staying'"
            class="text-sm leading-6 text-[var(--ks-text-secondary)]"
          >
            {{ t('kingdomP7D.stayingCompletionHelp') }}
          </p>

          <button
            class="mt-4 rounded-lg bg-[var(--ks-gold)] px-4 py-2 text-sm font-bold text-[var(--ks-ink)] disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="!canComplete(participant)"
            type="button"
            @click="completeParticipant(participant)"
          >
            {{ t('kingdomP7D.recordCompletion') }}
          </button>
          <p
            v-if="participant.readiness !== 'confirmed'"
            class="mt-2 text-xs font-semibold text-amber-200"
          >
            {{ t('kingdomP7D.mustConfirm') }}
          </p>
        </section>
      </article>
    </section>

    <section v-else-if="plan" class="ks-surface mt-6 p-6">
      <p class="text-sm text-[var(--ks-text-muted)]">
        {{ t('kingdomP7D.noCompletionParticipants') }}
      </p>
    </section>
    <section v-else class="ks-surface mt-6 p-6">
      <h2 class="text-xl font-semibold">{{ t('kingdomP7D.noCompletionCycle') }}</h2>
      <p class="mt-2 text-sm text-[var(--ks-text-muted)]">
        {{ t('kingdomP7D.noCompletionCycleHelp') }}
      </p>
    </section>
  </AppLayout>
</template>
