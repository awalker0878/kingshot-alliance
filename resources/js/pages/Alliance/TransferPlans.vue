<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

type Plan = {
  id: string;
  label: string;
  homeKingdom: string;
  startsOn: string | null;
  endsOn: string | null;
  state: string;
  createdAt: string | null;
};

type Group = {
  name: string;
  direction: 'incoming' | 'outgoing';
  destinationKingdom: string | null;
  coordinator: { name: string } | null;
};

type Readiness = 'not_started' | 'preparing' | 'ready' | 'blocked' | 'confirmed' | 'withdrawn';

type Participant = {
  id: string;
  direction: 'staying' | 'outgoing' | 'incoming';
  readiness: Readiness;
  name: string;
  gamePlayerId: string | null;
  sourceKingdom: string | null;
  destinationKingdom: string | null;
  membership: { name: string } | null;
  group: Group | null;
  completedAt: string | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  canManage: boolean;
  plan: Plan | null;
  groups: Group[];
  participants: Participant[];
}>();

const { t, formatDate, formatNumber } = useLocale();

const participantCounts = computed(() => ({
  incoming: props.participants.filter((participant) => participant.direction === 'incoming').length,
  outgoing: props.participants.filter((participant) => participant.direction === 'outgoing').length,
  staying: props.participants.filter((participant) => participant.direction === 'staying').length,
  completed: props.participants.filter((participant) => participant.completedAt !== null).length,
}));

function stateLabel(state: string): string {
  const key: Record<string, string> = {
    draft: 'stateDraft',
    open: 'stateOpen',
    locked: 'stateLocked',
    closed: 'stateClosed',
    cancelled: 'stateCancelled',
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

function directionLabel(direction: Participant['direction'] | Group['direction']): string {
  const key = {
    incoming: 'directionIncoming',
    outgoing: 'directionOutgoing',
    staying: 'directionStaying',
  } as const;
  return t(`kingdomP7D.${key[direction]}`);
}

function destinationLabel(participant: Participant): string {
  if (participant.direction === 'staying') return t('kingdomP7D.staying');
  if (participant.direction === 'outgoing' && participant.destinationKingdom === null) {
    return t('kingdomP7D.undecided');
  }
  return participant.destinationKingdom ?? '—';
}

function groupDestinationLabel(group: Group): string {
  if (group.direction === 'outgoing' && group.destinationKingdom === null) {
    return t('kingdomP7D.undecided');
  }
  return group.destinationKingdom ?? '—';
}

function dateOnly(value: string | null): string {
  if (!value) return t('kingdomP7D.notSpecified');
  return formatDate(`${value}T12:00:00`, { dateStyle: 'medium' });
}

function timestamp(value: string): string {
  return formatDate(value, { dateStyle: 'medium', timeStyle: 'short' });
}

function readinessTone(state: Readiness): string {
  if (state === 'confirmed' || state === 'ready')
    return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (state === 'blocked') return 'border-red-400/25 bg-red-500/10 text-red-200';
  if (state === 'preparing') return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
  if (state === 'withdrawn')
    return 'border-[var(--ks-border)] bg-white/5 text-[var(--ks-text-muted)]';
  return 'border-blue-400/25 bg-blue-500/10 text-blue-200';
}
</script>

<template>
  <Head :title="`${t('kingdomP7D.title')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-5">
      <div class="max-w-3xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('kingdomP7D.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">{{ t('kingdomP7D.title') }}</h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7D.subtitle', { alliance: alliance.name }) }}
        </p>
      </div>
      <nav :aria-label="t('kingdomP7D.overviewNavigation')" class="flex flex-wrap gap-2">
        <Link
          class="rounded-lg border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          href="/alliance/roster"
        >
          {{ t('kingdomP7D.roster') }}
        </Link>
        <Link
          v-if="canManage"
          class="rounded-lg border border-blue-400/30 px-3 py-2 text-sm font-semibold text-blue-200"
          href="/alliance/transfers/readiness"
        >
          {{ t('kingdomP7D.readinessBoard') }}
        </Link>
        <Link
          v-if="canManage"
          class="rounded-lg border border-green-400/30 px-3 py-2 text-sm font-semibold text-green-200"
          href="/alliance/transfers/completion"
        >
          {{ t('kingdomP7D.completion') }}
        </Link>
        <Link
          v-if="canManage"
          class="rounded-lg bg-[var(--ks-gold)] px-3 py-2 text-sm font-bold text-slate-950"
          href="/alliance/transfers/manage"
        >
          {{ t('kingdomP7D.manageTransfers') }}
        </Link>
      </nav>
    </header>

    <section
      class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5"
      :aria-label="t('kingdomP7D.summary')"
    >
      <article class="ks-surface p-4 sm:col-span-2 xl:col-span-1">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7D.currentCycle') }}
        </p>
        <p class="mt-2 text-lg font-bold">{{ plan?.label ?? '—' }}</p>
        <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
          {{ plan ? stateLabel(plan.state) : t('kingdomP7D.noCurrentCycle') }}
        </p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7D.incoming') }}
        </p>
        <p class="mt-2 text-2xl font-bold">{{ formatNumber(participantCounts.incoming) }}</p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7D.outgoing') }}
        </p>
        <p class="mt-2 text-2xl font-bold">{{ formatNumber(participantCounts.outgoing) }}</p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7D.staying') }}
        </p>
        <p class="mt-2 text-2xl font-bold">{{ formatNumber(participantCounts.staying) }}</p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7D.completed') }}
        </p>
        <p class="mt-2 text-2xl font-bold text-green-200">
          {{ formatNumber(participantCounts.completed) }}
        </p>
      </article>
    </section>

    <section class="ks-surface mt-6 p-5 sm:p-6" aria-labelledby="current-cycle-heading">
      <h2 id="current-cycle-heading" class="text-xl font-semibold">
        {{ t('kingdomP7D.currentCycle') }}
      </h2>
      <div v-if="plan" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-[var(--ks-border)] p-4 sm:col-span-2">
          <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
            {{ t('kingdomP7D.cycle') }}
          </p>
          <p class="mt-2 text-lg font-bold">{{ plan.label }}</p>
        </div>
        <div class="rounded-xl border border-[var(--ks-border)] p-4">
          <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
            {{ t('kingdomP7D.state') }}
          </p>
          <p class="mt-2 font-semibold text-blue-200">{{ stateLabel(plan.state) }}</p>
        </div>
        <div class="rounded-xl border border-[var(--ks-border)] p-4">
          <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
            {{ t('kingdomP7D.homeKingdom') }}
          </p>
          <p class="mt-2 font-semibold">{{ plan.homeKingdom }}</p>
        </div>
        <div class="rounded-xl border border-[var(--ks-border)] p-4">
          <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
            {{ t('kingdomP7D.starts') }} / {{ t('kingdomP7D.ends') }}
          </p>
          <p class="mt-2 text-sm">{{ dateOnly(plan.startsOn) }} → {{ dateOnly(plan.endsOn) }}</p>
        </div>
      </div>
      <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">
        {{ t('kingdomP7D.noCurrentCycle') }}
      </p>
    </section>

    <section
      v-if="plan"
      class="ks-surface mt-6 p-5 sm:p-6"
      aria-labelledby="transfer-groups-heading"
    >
      <h2 id="transfer-groups-heading" class="text-xl font-semibold">
        {{ t('kingdomP7D.transferGroups') }}
      </h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-text-muted)]">
        {{ t('kingdomP7D.groupsHelp') }}
      </p>
      <div v-if="groups.length" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="group in groups"
          :key="`${group.direction}-${group.name}`"
          class="rounded-xl border border-[var(--ks-border)] bg-white/[0.02] p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <h3 class="font-semibold">{{ group.name }}</h3>
            <span
              class="rounded-full border border-blue-400/25 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-200"
              >{{ directionLabel(group.direction) }}</span
            >
          </div>
          <p class="mt-3 text-sm text-[var(--ks-text-secondary)]">
            {{ t('kingdomP7D.kingdomValue', { kingdom: groupDestinationLabel(group) }) }}
          </p>
          <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
            {{ t('kingdomP7D.coordinator') }}:
            {{ group.coordinator?.name ?? t('kingdomP7D.unassigned') }}
          </p>
        </article>
      </div>
      <p v-else class="mt-5 text-sm text-[var(--ks-text-muted)]">{{ t('kingdomP7D.noGroups') }}</p>
    </section>

    <section v-if="plan" class="ks-surface mt-6 p-5 sm:p-6" aria-labelledby="participants-heading">
      <h2 id="participants-heading" class="text-xl font-semibold">
        {{ t('kingdomP7D.plannedParticipants') }}
      </h2>
      <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-text-muted)]">
        {{ t('kingdomP7D.participantsHelp') }}
      </p>

      <div v-if="participants.length" class="mt-5 grid gap-3 md:hidden">
        <article
          v-for="participant in participants"
          :key="participant.id"
          class="rounded-xl border border-[var(--ks-border)] bg-white/[0.02] p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="font-semibold">{{ participant.name }}</h3>
              <p v-if="participant.gamePlayerId" class="mt-1 text-xs text-[var(--ks-text-muted)]">
                {{ t('kingdomP7D.stableId', { id: participant.gamePlayerId }) }}
              </p>
            </div>
            <span
              :class="[
                'rounded-full border px-2.5 py-1 text-xs font-semibold',
                readinessTone(participant.readiness),
              ]"
              >{{ readinessLabel(participant.readiness) }}</span
            >
          </div>
          <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7D.direction') }}</dt>
              <dd class="mt-1">{{ directionLabel(participant.direction) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7D.destination') }}</dt>
              <dd class="mt-1">{{ destinationLabel(participant) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7D.group') }}</dt>
              <dd class="mt-1">{{ participant.group?.name ?? t('kingdomP7D.unassigned') }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7D.outcome') }}</dt>
              <dd class="mt-1">
                {{ participant.completedAt ? t('kingdomP7D.completed') : t('kingdomP7D.planning') }}
              </dd>
            </div>
          </dl>
        </article>
      </div>

      <div
        v-if="participants.length"
        class="mt-5 hidden overflow-x-auto rounded-xl border border-[var(--ks-border)] md:block"
      >
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <caption class="sr-only">
            {{
              t('kingdomP7D.plannedParticipants')
            }}
          </caption>
          <thead
            class="bg-white/[0.03] text-xs tracking-wide text-[var(--ks-text-muted)] uppercase"
          >
            <tr>
              <th class="px-4 py-3" scope="col">{{ t('kingdomP7D.player') }}</th>
              <th class="px-4 py-3" scope="col">{{ t('kingdomP7D.direction') }}</th>
              <th class="px-4 py-3" scope="col">{{ t('kingdomP7D.readiness') }}</th>
              <th class="px-4 py-3" scope="col">{{ t('kingdomP7D.outcome') }}</th>
              <th class="px-4 py-3" scope="col">{{ t('kingdomP7D.source') }}</th>
              <th class="px-4 py-3" scope="col">{{ t('kingdomP7D.destination') }}</th>
              <th class="px-4 py-3" scope="col">{{ t('kingdomP7D.group') }}</th>
              <th class="px-4 py-3" scope="col">{{ t('kingdomP7D.membership') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="participant in participants" :key="participant.id">
              <td class="px-4 py-4">
                <span class="font-semibold">{{ participant.name }}</span
                ><span
                  v-if="participant.gamePlayerId"
                  class="block text-xs text-[var(--ks-text-muted)]"
                  >{{ t('kingdomP7D.stableId', { id: participant.gamePlayerId }) }}</span
                >
              </td>
              <td class="px-4 py-4">{{ directionLabel(participant.direction) }}</td>
              <td class="px-4 py-4">
                <span
                  :class="[
                    'rounded-full border px-2.5 py-1 text-xs font-semibold',
                    readinessTone(participant.readiness),
                  ]"
                  >{{ readinessLabel(participant.readiness) }}</span
                >
              </td>
              <td class="px-4 py-4">
                <template v-if="participant.completedAt"
                  ><span class="font-semibold text-green-200">{{ t('kingdomP7D.completed') }}</span
                  ><span class="block text-xs text-[var(--ks-text-muted)]">{{
                    timestamp(participant.completedAt)
                  }}</span></template
                ><span v-else>{{ t('kingdomP7D.planning') }}</span>
              </td>
              <td class="px-4 py-4">{{ participant.sourceKingdom ?? t('kingdomP7D.unknown') }}</td>
              <td class="px-4 py-4">{{ destinationLabel(participant) }}</td>
              <td class="px-4 py-4">
                <span>{{ participant.group?.name ?? t('kingdomP7D.unassigned') }}</span
                ><span v-if="participant.group" class="block text-xs text-[var(--ks-text-muted)]"
                  >{{ t('kingdomP7D.coordinator') }}:
                  {{ participant.group.coordinator?.name ?? t('kingdomP7D.unassigned') }}</span
                >
              </td>
              <td class="px-4 py-4">
                {{ participant.membership?.name ?? t('kingdomP7D.notLinked') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="mt-5 text-sm text-[var(--ks-text-muted)]">
        {{ t('kingdomP7D.noParticipants') }}
      </p>
    </section>
  </AppLayout>
</template>
