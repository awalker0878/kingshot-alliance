<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

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

function readinessTone(state: Readiness): 'success' | 'warning' | 'danger' | 'info' {
  if (state === 'confirmed' || state === 'ready') return 'success';
  if (state === 'blocked') return 'danger';
  if (state === 'preparing') return 'warning';
  return 'info';
}

function directionTone(direction: Participant['direction'] | Group['direction']): 'success' | 'warning' | 'info' {
  if (direction === 'incoming') return 'success';
  if (direction === 'outgoing') return 'warning';
  return 'info';
}
</script>

<template>
  <Head :title="`${t('kingdomP7D.title')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('kingdomP7D.eyebrow')"
      :title="t('kingdomP7D.title')"
      :subtitle="t('kingdomP7D.subtitle', { alliance: alliance.name })"
      image="/images/kingshot/v4/kingdom-transfer.svg"
    >
      <template #actions>
        <Link
          v-if="canManage"
          href="/alliance/transfers/readiness"
          class="ks-command-link"
        >
          {{ t('kingdomP7D.readinessBoard') }}
        </Link>
        <Link
          v-if="canManage"
          href="/alliance/transfers/manage"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('kingdomP7D.manageTransfers') }}
        </Link>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-5">
      <StatSeal
        :label="t('kingdomP7D.currentCycle')"
        :value="plan?.label ?? t('kingdomP7D.noCurrentCycle')"
        icon="◇"
      />
      <StatSeal
        :label="t('kingdomP7D.incoming')"
        :value="formatNumber(participantCounts.incoming)"
        icon="←"
        tone="teal"
      />
      <StatSeal
        :label="t('kingdomP7D.outgoing')"
        :value="formatNumber(participantCounts.outgoing)"
        icon="→"
        tone="stone"
      />
      <StatSeal
        :label="t('kingdomP7D.staying')"
        :value="formatNumber(participantCounts.staying)"
        icon="◆"
      />
      <StatSeal
        :label="t('kingdomP7D.completed')"
        :value="formatNumber(participantCounts.completed)"
        icon="✓"
        tone="teal"
      />
    </section>

    <section class="ks-surface-gold mt-5 p-5 sm:p-6" aria-labelledby="current-cycle-heading">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="ks-kicker">{{ t('kingdomP7D.currentCycle') }}</p>
          <h2 id="current-cycle-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ plan?.label ?? t('kingdomP7D.noCurrentCycle') }}
          </h2>
        </div>
        <span v-if="plan" class="ks-status" :data-tone="plan.state === 'cancelled' ? 'danger' : plan.state === 'closed' ? 'success' : 'info'">
          {{ stateLabel(plan.state) }}
        </span>
      </div>

      <dl v-if="plan" class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <dt class="ks-kicker">{{ t('kingdomP7D.homeKingdom') }}</dt>
          <dd class="ks-display mt-2 text-xl text-[var(--ks-gold-bright)]">{{ plan.homeKingdom }}</dd>
        </div>
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <dt class="ks-kicker">{{ t('kingdomP7D.starts') }}</dt>
          <dd class="mt-2 text-sm font-semibold">{{ dateOnly(plan.startsOn) }}</dd>
        </div>
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <dt class="ks-kicker">{{ t('kingdomP7D.ends') }}</dt>
          <dd class="mt-2 text-sm font-semibold">{{ dateOnly(plan.endsOn) }}</dd>
        </div>
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <dt class="ks-kicker">{{ t('kingdomP7D.participants') }}</dt>
          <dd class="ks-display mt-2 text-xl">{{ formatNumber(participants.length) }}</dd>
        </div>
      </dl>
      <p v-else class="mt-4 text-sm text-[var(--ks-muted)]">{{ t('kingdomP7D.noCurrentCycle') }}</p>
    </section>

    <div v-if="plan" class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,.6fr)]">
      <section class="ks-surface overflow-hidden" aria-labelledby="participants-heading">
        <div class="border-b border-[var(--ks-border)] p-5">
          <p class="ks-kicker">{{ t('kingdomP7D.plannedParticipants') }}</p>
          <h2 id="participants-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('kingdomP7D.participants') }}
          </h2>
          <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('kingdomP7D.participantsHelp') }}
          </p>
        </div>

        <div v-if="participants.length" class="md:hidden">
          <article
            v-for="participant in participants"
            :key="participant.id"
            class="border-b border-[var(--ks-border)] p-4 last:border-b-0"
          >
            <div class="flex items-start gap-3">
              <div
                class="grid h-11 w-11 shrink-0 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-black/20 font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
                aria-hidden="true"
              >
                {{ participant.name.slice(0, 1).toUpperCase() }}
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="truncate font-[var(--ks-font-display)] text-lg font-semibold">{{ participant.name }}</h3>
                <p v-if="participant.gamePlayerId" class="mt-1 truncate text-xs text-[var(--ks-muted)]">
                  {{ t('kingdomP7D.stableId', { id: participant.gamePlayerId }) }}
                </p>
              </div>
              <span class="ks-status" :data-tone="readinessTone(participant.readiness)">
                {{ readinessLabel(participant.readiness) }}
              </span>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
              <span class="ks-status" :data-tone="directionTone(participant.direction)">
                {{ directionLabel(participant.direction) }}
              </span>
              <span class="ks-chip">{{ destinationLabel(participant) }}</span>
              <span v-if="participant.group" class="ks-chip">{{ participant.group.name }}</span>
            </div>
            <p v-if="participant.completedAt" class="mt-3 text-xs text-[var(--ks-green)]">
              {{ t('kingdomP7D.completed') }} · {{ timestamp(participant.completedAt) }}
            </p>
          </article>
        </div>

        <div v-if="participants.length" class="hidden overflow-x-auto md:block">
          <table class="w-full min-w-[70rem] text-start text-sm">
            <thead class="bg-black/20 text-[.66rem] font-extrabold tracking-[.08em] text-[var(--ks-muted)] uppercase">
              <tr>
                <th class="px-5 py-3 text-start">{{ t('kingdomP7D.player') }}</th>
                <th class="px-4 py-3 text-start">{{ t('kingdomP7D.direction') }}</th>
                <th class="px-4 py-3 text-start">{{ t('kingdomP7D.readiness') }}</th>
                <th class="px-4 py-3 text-start">{{ t('kingdomP7D.source') }}</th>
                <th class="px-4 py-3 text-start">{{ t('kingdomP7D.destination') }}</th>
                <th class="px-4 py-3 text-start">{{ t('kingdomP7D.group') }}</th>
                <th class="px-4 py-3 text-start">{{ t('kingdomP7D.outcome') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ks-border)]">
              <tr v-for="participant in participants" :key="participant.id" class="transition hover:bg-white/[0.018]">
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div
                      class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-black/20 font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
                      aria-hidden="true"
                    >
                      {{ participant.name.slice(0, 1).toUpperCase() }}
                    </div>
                    <div>
                      <strong>{{ participant.name }}</strong>
                      <span v-if="participant.gamePlayerId" class="mt-1 block text-xs text-[var(--ks-muted)]">
                        {{ t('kingdomP7D.stableId', { id: participant.gamePlayerId }) }}
                      </span>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-4"><span class="ks-status" :data-tone="directionTone(participant.direction)">{{ directionLabel(participant.direction) }}</span></td>
                <td class="px-4 py-4"><span class="ks-status" :data-tone="readinessTone(participant.readiness)">{{ readinessLabel(participant.readiness) }}</span></td>
                <td class="px-4 py-4">{{ participant.sourceKingdom ?? t('kingdomP7D.unknown') }}</td>
                <td class="px-4 py-4 font-semibold">{{ destinationLabel(participant) }}</td>
                <td class="px-4 py-4"><span>{{ participant.group?.name ?? t('kingdomP7D.unassigned') }}</span><span v-if="participant.group" class="mt-1 block text-xs text-[var(--ks-muted)]">{{ t('kingdomP7D.coordinator') }}: {{ participant.group.coordinator?.name ?? t('kingdomP7D.unassigned') }}</span></td>
                <td class="px-4 py-4"><template v-if="participant.completedAt"><span class="text-[var(--ks-green)]">{{ t('kingdomP7D.completed') }}</span><span class="mt-1 block text-xs text-[var(--ks-muted)]">{{ timestamp(participant.completedAt) }}</span></template><span v-else>{{ t('kingdomP7D.planning') }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="!participants.length" class="ks-fantasy-empty m-5">{{ t('kingdomP7D.noParticipants') }}</div>
      </section>

      <aside class="space-y-5">
        <section class="ks-surface p-5" aria-labelledby="transfer-groups-heading">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('kingdomP7D.transferGroups') }}</p>
              <h2 id="transfer-groups-heading" class="ks-display mt-1 text-xl font-semibold">
                {{ t('kingdomP7D.group') }}
              </h2>
            </div>
            <span class="ks-chip" data-active="true">{{ groups.length }}</span>
          </div>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">{{ t('kingdomP7D.groupsHelp') }}</p>
          <div v-if="groups.length" class="mt-4 space-y-2">
            <article v-for="group in groups" :key="`${group.direction}-${group.name}`" class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-3">
              <div class="flex items-start justify-between gap-3">
                <strong class="font-[var(--ks-font-display)]">{{ group.name }}</strong>
                <span class="ks-status" :data-tone="directionTone(group.direction)">{{ directionLabel(group.direction) }}</span>
              </div>
              <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
                {{ t('kingdomP7D.kingdomValue', { kingdom: groupDestinationLabel(group) }) }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('kingdomP7D.coordinator') }}: {{ group.coordinator?.name ?? t('kingdomP7D.unassigned') }}
              </p>
            </article>
          </div>
          <div v-else class="ks-fantasy-empty mt-4">{{ t('kingdomP7D.noGroups') }}</div>
        </section>

        <section v-if="canManage" class="ks-surface p-5">
          <p class="ks-kicker">{{ t('kingdomP7D.overviewNavigation') }}</p>
          <div class="mt-4 grid gap-2">
            <Link href="/alliance/transfers/readiness" class="ks-command-link w-full">
              {{ t('kingdomP7D.readinessBoard') }}
            </Link>
            <Link href="/alliance/transfers/completion" class="ks-command-link w-full" data-variant="secondary">
              {{ t('kingdomP7D.completion') }}
            </Link>
            <Link href="/alliance/transfers/manage" class="ks-command-link w-full" data-variant="secondary">
              {{ t('kingdomP7D.manageTransfers') }}
            </Link>
          </div>
        </section>
      </aside>
    </div>
  </AppLayout>
</template>
