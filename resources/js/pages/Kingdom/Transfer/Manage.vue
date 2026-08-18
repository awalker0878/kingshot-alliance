<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

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
  id: string;
  name: string;
  direction: 'incoming' | 'outgoing';
  destinationKingdom: string | null;
  state: 'active' | 'archived';
  coordinator: { name: string } | null;
  coordinatorPlayerId: string | null;
  managerNotes: string | null;
};

type ParticipantGroup = {
  name: string;
  direction: 'incoming' | 'outgoing';
  destinationKingdom: string | null;
  coordinator: { name: string } | null;
};

type Participant = {
  id: string;
  direction: 'staying' | 'outgoing' | 'incoming';
  name: string;
  gamePlayerId: string | null;
  rosterEntryId: string | null;
  transferGroupId: string | null;
  sourceKingdom: string | null;
  destinationKingdom: string | null;
  player: { id: string; name: string };
  group: ParticipantGroup | null;
  managerNotes: string | null;
  withdrawnAt: string | null;
};

type RosterOption = {
  id: string;
  name: string;
  gamePlayerId: string | null;
  playerId: string;
};

type PlayerOption = {
  id: string;
  name: string;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  plans: Plan[];
  mutablePlan: Plan | null;
  groups: Group[];
  participants: Participant[];
  rosterOptions: RosterOption[];
  players: PlayerOption[];
}>();

const { t, formatDate } = useLocale();

const createForm = useForm({ label: '', starts_on: '', ends_on: '' });
const transitionForm = useForm<Record<string, string>>({});
const groupForm = useForm({
  name: '',
  direction: 'incoming' as 'incoming' | 'outgoing',
  destination_kingdom: '',
  coordinator_player_id: '',
  manager_notes: '',
});
const participantForm = useForm({
  direction: 'staying',
  roster_entry_id: '',
  name: '',
  game_player_id: '',

  source_kingdom: '',
  destination_kingdom: '',
  manager_notes: '',
});
const createPlanError = computed(() => (createForm.errors as Record<string, string>).plan);
const groupError = computed(() => (groupForm.errors as Record<string, string>).group);
const participantError = computed(
  () => (participantForm.errors as Record<string, string>).participant,
);
const activeGroups = computed(() => props.groups.filter((group) => group.state === 'active'));

const groupDrafts = reactive(
  Object.fromEntries(
    props.groups.map((group) => [
      group.id,
      {
        name: group.name,
        direction: group.direction,
        destination_kingdom: group.destinationKingdom ?? '',
        coordinator_player_id: group.coordinatorPlayerId ?? '',
        manager_notes: group.managerNotes ?? '',
      },
    ]),
  ) as Record<
    string,
    {
      name: string;
      direction: 'incoming' | 'outgoing';
      destination_kingdom: string;
      coordinator_player_id: string;
      manager_notes: string;
    }
  >,
);

const groupAssignments = reactive(
  Object.fromEntries(
    props.participants.map((participant) => [participant.id, participant.transferGroupId ?? '']),
  ) as Record<string, string>,
);

const drafts = reactive(
  Object.fromEntries(
    props.participants.map((participant) => [
      participant.id,
      {
        direction: participant.direction,
        roster_entry_id: participant.rosterEntryId ?? '',
        name: participant.name,
        game_player_id: participant.gamePlayerId ?? '',

        source_kingdom: participant.sourceKingdom ?? '',
        destination_kingdom: participant.destinationKingdom ?? '',
        manager_notes: participant.managerNotes ?? '',
      },
    ]),
  ) as Record<
    string,
    {
      direction: 'staying' | 'outgoing' | 'incoming';
      roster_entry_id: string;
      name: string;
      game_player_id: string;

      source_kingdom: string;
      destination_kingdom: string;
      manager_notes: string;
    }
  >,
);

function createPlan(): void {
  createForm.post('/alliance/transfers', {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  });
}

function transition(plan: Plan, action: 'open' | 'lock' | 'close' | 'cancel'): void {
  if (
    action === 'cancel' &&
    !window.confirm(t('kingdomP7D.cancelCycleConfirm', { label: plan.label }))
  )
    return;
  transitionForm.post(`/alliance/transfers/${plan.id}/${action}`, { preserveScroll: true });
}

function createGroup(): void {
  if (props.mutablePlan === null) return;
  groupForm.post(`/alliance/transfers/${props.mutablePlan.id}/groups`, {
    preserveScroll: true,
    onSuccess: () => groupForm.reset(),
  });
}

function saveGroup(group: Group): void {
  if (props.mutablePlan === null || group.state !== 'active') return;
  router.patch(
    `/alliance/transfers/${props.mutablePlan.id}/groups/${group.id}`,
    groupDrafts[group.id],
    { preserveScroll: true },
  );
}

function archiveGroup(group: Group): void {
  if (props.mutablePlan === null || group.state !== 'active') return;
  if (!window.confirm(t('kingdomP7D.archiveGroupConfirm', { name: group.name }))) return;
  router.post(
    `/alliance/transfers/${props.mutablePlan.id}/groups/${group.id}/archive`,
    {},
    { preserveScroll: true },
  );
}

function createParticipant(): void {
  if (props.mutablePlan === null) return;
  participantForm.post(`/alliance/transfers/${props.mutablePlan.id}/participants`, {
    preserveScroll: true,
    onSuccess: () => participantForm.reset(),
  });
}

function saveParticipant(participant: Participant): void {
  if (props.mutablePlan === null) return;
  router.patch(
    `/alliance/transfers/${props.mutablePlan.id}/participants/${participant.id}`,
    drafts[participant.id],
    { preserveScroll: true },
  );
}

function assignParticipantGroup(participant: Participant): void {
  if (props.mutablePlan === null || participant.withdrawnAt !== null) return;
  router.patch(
    `/alliance/transfers/${props.mutablePlan.id}/participants/${participant.id}/group`,
    { transfer_group_id: groupAssignments[participant.id] || null },
    { preserveScroll: true },
  );
}

function withdrawParticipant(participant: Participant): void {
  if (props.mutablePlan === null || participant.withdrawnAt !== null) return;
  if (!window.confirm(t('kingdomP7D.withdrawConfirm', { name: participant.name }))) return;
  router.post(
    `/alliance/transfers/${props.mutablePlan.id}/participants/${participant.id}/withdraw`,
    {},
    { preserveScroll: true },
  );
}

function stateLabel(state: string): string {
  const key: Record<string, string> = {
    draft: 'stateDraft',
    open: 'stateOpen',
    locked: 'stateLocked',
    closed: 'stateClosed',
    cancelled: 'stateCancelled',
    active: 'groupActive',
    archived: 'groupArchived',
  };
  return t(`kingdomP7D.${key[state] ?? 'state'}`);
}

function directionLabel(direction: 'staying' | 'outgoing' | 'incoming'): string {
  const key = {
    staying: 'directionStaying',
    outgoing: 'directionOutgoing',
    incoming: 'directionIncoming',
  } as const;
  return t(`kingdomP7D.${key[direction]}`);
}

function canOpen(plan: Plan): boolean {
  return plan.state === 'draft' && !props.plans.some((candidate) => candidate.state === 'open');
}

function isRosterBound(direction: string): boolean {
  return direction === 'staying' || direction === 'outgoing';
}

function compatibleGroups(participant: Participant): Group[] {
  if (participant.direction === 'staying') return [];
  return activeGroups.value.filter((group) => {
    if (group.direction !== participant.direction) return false;
    if (group.direction === 'outgoing' && group.destinationKingdom !== null) {
      return group.destinationKingdom === participant.destinationKingdom;
    }
    return true;
  });
}

function dateOnly(value: string | null): string {
  if (!value) return '—';
  return formatDate(`${value}T12:00:00`, { dateStyle: 'medium' });
}

const inputClass =
  'mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 text-sm text-[var(--ks-text)] disabled:cursor-not-allowed disabled:opacity-50';
const labelClass =
  'block text-xs font-semibold tracking-wide text-[var(--ks-text-muted)] uppercase';
</script>

<template>
  <Head :title="`${t('kingdomP7D.manageTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-5">
      <div class="max-w-3xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('kingdomP7D.manageEyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('kingdomP7D.manageTitle') }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{
            t('kingdomP7D.manageSubtitle', {
              alliance: alliance.name,
              kingdom: alliance.kingdom ?? t('kingdomP7D.notConfigured'),
            })
          }}
        </p>
      </div>
      <nav :aria-label="t('kingdomP7D.overviewNavigation')" class="flex flex-wrap gap-2">
        <Link
          class="rounded-lg border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          href="/alliance/transfers"
          >{{ t('kingdomP7D.title') }}</Link
        >
        <Link
          class="rounded-lg border border-[var(--ks-border-strong)] px-3 py-2 text-sm font-semibold text-[var(--ks-gold-bright)]"
          href="/alliance/transfers/readiness"
          >{{ t('kingdomP7D.readinessBoard') }}</Link
        >
        <Link
          class="rounded-lg border border-green-400/30 px-3 py-2 text-sm font-semibold text-green-200"
          href="/alliance/transfers/completion"
          >{{ t('kingdomP7D.completion') }}</Link
        >
        <Link
          class="rounded-lg border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          href="/alliance/roster/manage"
          >{{ t('kingdomP7D.manageRoster') }}</Link
        >
      </nav>
    </header>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
      <div class="ks-surface p-5 sm:p-6">
        <h2 class="text-xl font-semibold">{{ t('kingdomP7D.createCycle') }}</h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('kingdomP7D.createCycleHelp') }}
        </p>
        <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="createPlan">
          <div class="sm:col-span-2">
            <label :class="labelClass" for="transfer-label">{{ t('kingdomP7D.cycleLabel') }}</label>
            <input
              id="transfer-label"
              v-model="createForm.label"
              :class="inputClass"
              maxlength="160"
              required
              type="text"
            />
            <p v-if="createForm.errors.label" class="mt-1 text-xs text-red-200">
              {{ createForm.errors.label }}
            </p>
          </div>
          <div>
            <label :class="labelClass" for="transfer-start">{{ t('kingdomP7D.startDate') }}</label
            ><input
              id="transfer-start"
              v-model="createForm.starts_on"
              :class="inputClass"
              type="date"
            />
          </div>
          <div>
            <label :class="labelClass" for="transfer-end">{{ t('kingdomP7D.endDate') }}</label
            ><input
              id="transfer-end"
              v-model="createForm.ends_on"
              :class="inputClass"
              type="date"
            />
          </div>
          <div class="sm:col-span-2">
            <button
              class="rounded-lg bg-[var(--ks-gold)] px-4 py-2 text-sm font-bold text-[var(--ks-ink)] disabled:opacity-50"
              :disabled="createForm.processing"
              type="submit"
            >
              {{ t('kingdomP7D.createDraft') }}
            </button>
          </div>
        </form>
        <p v-if="createPlanError" role="alert" class="mt-3 text-sm text-red-200">
          {{ createPlanError }}
        </p>
      </div>

      <div class="ks-surface p-5 sm:p-6">
        <h2 class="text-xl font-semibold">{{ t('kingdomP7D.transferCycles') }}</h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('kingdomP7D.cycleLifecycle') }}
        </p>
        <p v-if="transitionForm.errors.plan" role="alert" class="mt-3 text-sm text-red-200">
          {{ transitionForm.errors.plan }}
        </p>
        <div
          v-if="plans.length"
          class="mt-5 overflow-x-auto rounded-xl border border-[var(--ks-border)]"
        >
          <table class="min-w-full divide-y divide-[var(--ks-border)] text-left text-sm">
            <caption class="sr-only">
              {{
                t('kingdomP7D.transferCycles')
              }}
            </caption>
            <thead
              class="bg-[var(--ks-parchment)]/[0.03] text-xs text-[var(--ks-text-muted)] uppercase"
            >
              <tr>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.cycle') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.homeKingdom') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.dates') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.state') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ks-border)]">
              <tr v-for="plan in plans" :key="plan.id">
                <td class="px-3 py-4 font-semibold">{{ plan.label }}</td>
                <td class="px-3 py-4">{{ plan.homeKingdom }}</td>
                <td class="px-3 py-4 whitespace-nowrap">
                  {{ dateOnly(plan.startsOn) }} → {{ dateOnly(plan.endsOn) }}
                </td>
                <td class="px-3 py-4">{{ stateLabel(plan.state) }}</td>
                <td class="px-3 py-4">
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-if="plan.state === 'draft'"
                      class="rounded-lg border border-[var(--ks-border-strong)] px-2.5 py-1.5 text-xs font-semibold text-[var(--ks-gold-bright)] disabled:opacity-40"
                      :disabled="!canOpen(plan)"
                      type="button"
                      @click="transition(plan, 'open')"
                    >
                      {{ t('kingdomP7D.open') }}</button
                    ><button
                      v-if="plan.state === 'open'"
                      class="rounded-lg border border-amber-400/30 px-2.5 py-1.5 text-xs font-semibold text-amber-200"
                      type="button"
                      @click="transition(plan, 'lock')"
                    >
                      {{ t('kingdomP7D.lock') }}</button
                    ><button
                      v-if="plan.state === 'locked'"
                      class="rounded-lg border border-green-400/30 px-2.5 py-1.5 text-xs font-semibold text-green-200"
                      type="button"
                      @click="transition(plan, 'close')"
                    >
                      {{ t('kingdomP7D.close') }}</button
                    ><button
                      v-if="['draft', 'open', 'locked'].includes(plan.state)"
                      class="rounded-lg border border-red-400/30 px-2.5 py-1.5 text-xs font-semibold text-red-200"
                      type="button"
                      @click="transition(plan, 'cancel')"
                    >
                      {{ t('kingdomP7D.cancel') }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="mt-5 text-sm text-[var(--ks-text-muted)]">
          {{ t('kingdomP7D.noCycles') }}
        </p>
      </div>
    </section>

    <template v-if="mutablePlan">
      <section class="ks-surface mt-6 p-5 sm:p-6" aria-labelledby="groups-manage-heading">
        <h2 id="groups-manage-heading" class="text-xl font-semibold">
          {{ t('kingdomP7D.groupsForCycle', { label: mutablePlan.label }) }}
        </h2>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('kingdomP7D.groupsHelp') }}
        </p>
        <form class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="createGroup">
          <div>
            <label :class="labelClass" for="group-name">{{ t('kingdomP7D.groupName') }}</label
            ><input
              id="group-name"
              v-model="groupForm.name"
              :class="inputClass"
              maxlength="160"
              required
              type="text"
            />
            <p v-if="groupForm.errors.name" class="mt-1 text-xs text-red-200">
              {{ groupForm.errors.name }}
            </p>
          </div>
          <div>
            <label :class="labelClass" for="group-direction">{{ t('kingdomP7D.direction') }}</label
            ><select id="group-direction" v-model="groupForm.direction" :class="inputClass">
              <option value="incoming">{{ t('kingdomP7D.directionIncoming') }}</option>
              <option value="outgoing">{{ t('kingdomP7D.directionOutgoing') }}</option>
            </select>
          </div>
          <div v-if="groupForm.direction === 'outgoing'">
            <label :class="labelClass" for="group-destination">{{
              t('kingdomP7D.destinationKingdom')
            }}</label
            ><input
              id="group-destination"
              v-model="groupForm.destination_kingdom"
              :class="inputClass"
              inputmode="numeric"
              type="text"
            />
            <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
              {{ t('kingdomP7D.outgoingDestinationHelp') }}
            </p>
          </div>
          <div
            v-else
            class="rounded-lg border border-[var(--ks-border)] p-3 text-sm text-[var(--ks-text-secondary)]"
          >
            {{ t('kingdomP7D.incomingDestinationFixed', { kingdom: mutablePlan.homeKingdom }) }}
          </div>
          <div>
            <label :class="labelClass" for="group-coordinator">{{
              t('kingdomP7D.coordinator')
            }}</label
            ><select
              id="group-coordinator"
              v-model="groupForm.coordinator_player_id"
              :class="inputClass"
            >
              <option value="">{{ t('kingdomP7D.unassigned') }}</option>
              <option v-for="player in players" :key="player.id" :value="player.id">
                {{ player.name }}
              </option>
            </select>
          </div>
          <div>
            <label :class="labelClass" for="group-notes">{{ t('kingdomP7D.managerNotes') }}</label
            ><textarea
              id="group-notes"
              v-model="groupForm.manager_notes"
              :class="inputClass"
              maxlength="5000"
              rows="3"
            />
          </div>
          <div class="xl:col-span-5">
            <button
              class="rounded-lg bg-[var(--ks-gold)] px-4 py-2 text-sm font-bold text-[var(--ks-ink)] disabled:opacity-50"
              :disabled="groupForm.processing"
              type="submit"
            >
              {{ t('kingdomP7D.createGroup') }}
            </button>
            <p v-if="groupError" role="alert" class="mt-2 text-sm text-red-200">{{ groupError }}</p>
            <p v-if="groupForm.hasErrors" role="alert" class="mt-2 text-sm text-red-200">
              {{ t('kingdomP7D.correctGroupFields') }}
            </p>
          </div>
        </form>

        <div
          v-if="groups.length"
          class="mt-6 overflow-x-auto rounded-xl border border-[var(--ks-border)]"
        >
          <table class="min-w-[1100px] divide-y divide-[var(--ks-border)] text-left text-sm">
            <caption class="sr-only">
              {{
                t('kingdomP7D.groupTableCaption')
              }}
            </caption>
            <thead
              class="bg-[var(--ks-parchment)]/[0.03] text-xs text-[var(--ks-text-muted)] uppercase"
            >
              <tr>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.group') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.directionDestination') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.coordinator') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.managerNotes') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.state') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ks-border)]">
              <tr v-for="group in groups" :key="group.id" class="align-top">
                <td class="px-3 py-4">
                  <label class="sr-only" :for="`group-name-${group.id}`">{{
                    t('kingdomP7D.name')
                  }}</label
                  ><input
                    :id="`group-name-${group.id}`"
                    v-model="groupDrafts[group.id].name"
                    :class="inputClass"
                    :disabled="group.state !== 'active'"
                    maxlength="160"
                    type="text"
                  />
                </td>
                <td class="px-3 py-4">
                  <label class="sr-only" :for="`group-direction-${group.id}`">{{
                    t('kingdomP7D.direction')
                  }}</label
                  ><select
                    :id="`group-direction-${group.id}`"
                    v-model="groupDrafts[group.id].direction"
                    :class="inputClass"
                    :disabled="group.state !== 'active'"
                  >
                    <option value="incoming">{{ t('kingdomP7D.directionIncoming') }}</option>
                    <option value="outgoing">
                      {{ t('kingdomP7D.directionOutgoing') }}
                    </option></select
                  ><template v-if="groupDrafts[group.id].direction === 'outgoing'"
                    ><label class="sr-only" :for="`group-destination-${group.id}`">{{
                      t('kingdomP7D.destinationKingdom')
                    }}</label
                    ><input
                      :id="`group-destination-${group.id}`"
                      v-model="groupDrafts[group.id].destination_kingdom"
                      :class="inputClass"
                      :disabled="group.state !== 'active'"
                      inputmode="numeric"
                      type="text"
                  /></template>
                  <p v-else class="mt-2 text-xs text-[var(--ks-text-muted)]">
                    {{ t('kingdomP7D.kingdomValue', { kingdom: mutablePlan.homeKingdom }) }}
                  </p>
                </td>
                <td class="px-3 py-4">
                  <label class="sr-only" :for="`group-coordinator-${group.id}`">{{
                    t('kingdomP7D.coordinator')
                  }}</label
                  ><select
                    :id="`group-coordinator-${group.id}`"
                    v-model="groupDrafts[group.id].coordinator_player_id"
                    :class="inputClass"
                    :disabled="group.state !== 'active'"
                  >
                    <option value="">{{ t('kingdomP7D.unassigned') }}</option>
                    <option v-for="player in players" :key="player.id" :value="player.id">
                      {{ player.name }}
                    </option>
                  </select>
                </td>
                <td class="px-3 py-4">
                  <label class="sr-only" :for="`group-notes-${group.id}`">{{
                    t('kingdomP7D.managerNotes')
                  }}</label
                  ><textarea
                    :id="`group-notes-${group.id}`"
                    v-model="groupDrafts[group.id].manager_notes"
                    :class="inputClass"
                    :disabled="group.state !== 'active'"
                    maxlength="5000"
                    rows="2"
                  />
                </td>
                <td class="px-3 py-4">{{ stateLabel(group.state) }}</td>
                <td class="px-3 py-4">
                  <div class="flex gap-2">
                    <button
                      v-if="group.state === 'active'"
                      class="rounded-lg border border-[var(--ks-border-strong)] px-2.5 py-1.5 text-xs font-semibold text-[var(--ks-gold-bright)]"
                      type="button"
                      @click="saveGroup(group)"
                    >
                      {{ t('kingdomP7D.save') }}</button
                    ><button
                      v-if="group.state === 'active'"
                      class="rounded-lg border border-red-400/30 px-2.5 py-1.5 text-xs font-semibold text-red-200"
                      type="button"
                      @click="archiveGroup(group)"
                    >
                      {{ t('kingdomP7D.archive') }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="mt-5 text-sm text-[var(--ks-text-muted)]">
          {{ t('kingdomP7D.noGroups') }}
        </p>
      </section>

      <section class="ks-surface mt-6 p-5 sm:p-6" aria-labelledby="participants-manage-heading">
        <h2 id="participants-manage-heading" class="text-xl font-semibold">
          {{ t('kingdomP7D.participantsForCycle', { label: mutablePlan.label }) }}
        </h2>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('kingdomP7D.addParticipantHelp') }}
        </p>
        <form
          class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4"
          @submit.prevent="createParticipant"
        >
          <div>
            <label :class="labelClass" for="participant-direction">{{
              t('kingdomP7D.direction')
            }}</label
            ><select
              id="participant-direction"
              v-model="participantForm.direction"
              :class="inputClass"
            >
              <option value="staying">{{ t('kingdomP7D.directionStaying') }}</option>
              <option value="outgoing">{{ t('kingdomP7D.directionOutgoing') }}</option>
              <option value="incoming">{{ t('kingdomP7D.directionIncoming') }}</option>
            </select>
          </div>
          <div v-if="isRosterBound(participantForm.direction)">
            <label :class="labelClass" for="participant-roster">{{
              t('kingdomP7D.rosterEntry')
            }}</label
            ><select
              id="participant-roster"
              v-model="participantForm.roster_entry_id"
              :class="inputClass"
              required
            >
              <option value="">{{ t('kingdomP7D.chooseRosterEntry') }}</option>
              <option v-for="entry in rosterOptions" :key="entry.id" :value="entry.id">
                {{ entry.name }}
              </option>
            </select>
          </div>
          <div v-if="participantForm.direction === 'outgoing'">
            <label :class="labelClass" for="participant-destination">{{
              t('kingdomP7D.destinationKingdom')
            }}</label
            ><input
              id="participant-destination"
              v-model="participantForm.destination_kingdom"
              :class="inputClass"
              inputmode="numeric"
              type="text"
            />
            <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
              {{ t('kingdomP7D.outgoingDestinationHelp') }}
            </p>
          </div>
          <template v-if="participantForm.direction === 'incoming'"
            ><div>
              <label :class="labelClass" for="participant-name">{{
                t('kingdomP7D.observedName')
              }}</label
              ><input
                id="participant-name"
                v-model="participantForm.name"
                :class="inputClass"
                maxlength="160"
                type="text"
              />
            </div>
            <div>
              <label :class="labelClass" for="participant-game-id">{{
                t('kingdomP7D.gamePlayerId')
              }}</label
              ><input
                id="participant-game-id"
                v-model="participantForm.game_player_id"
                :class="inputClass"
                maxlength="100"
                type="text"
              />
            </div>
            <div>
              <label :class="labelClass" for="participant-source">{{
                t('kingdomP7D.sourceKingdom')
              }}</label
              ><input
                id="participant-source"
                v-model="participantForm.source_kingdom"
                :class="inputClass"
                inputmode="numeric"
                type="text"
              />
            </div>
          </template>
          <div class="md:col-span-2">
            <label :class="labelClass" for="participant-notes">{{
              t('kingdomP7D.participantNotes')
            }}</label
            ><textarea
              id="participant-notes"
              v-model="participantForm.manager_notes"
              :class="inputClass"
              maxlength="5000"
              rows="3"
            />
          </div>
          <div class="md:col-span-2 xl:col-span-4">
            <button
              class="rounded-lg bg-[var(--ks-gold)] px-4 py-2 text-sm font-bold text-[var(--ks-ink)] disabled:opacity-50"
              :disabled="participantForm.processing"
              type="submit"
            >
              {{ t('kingdomP7D.createParticipant') }}
            </button>
            <p v-if="participantError" role="alert" class="mt-2 text-sm text-red-200">
              {{ participantError }}
            </p>
            <p v-if="participantForm.hasErrors" role="alert" class="mt-2 text-sm text-red-200">
              {{ t('kingdomP7D.correctParticipantFields') }}
            </p>
          </div>
        </form>

        <div
          v-if="participants.length"
          class="mt-6 overflow-x-auto rounded-xl border border-[var(--ks-border)]"
        >
          <table class="min-w-[1350px] divide-y divide-[var(--ks-border)] text-left text-sm">
            <caption class="sr-only">
              {{
                t('kingdomP7D.participantTableCaption')
              }}
            </caption>
            <thead
              class="bg-[var(--ks-parchment)]/[0.03] text-xs text-[var(--ks-text-muted)] uppercase"
            >
              <tr>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.player') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.directionDestination') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.player') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.groupAssignment') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.managerNotes') }}</th>
                <th class="px-3 py-3" scope="col">{{ t('kingdomP7D.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ks-border)]">
              <tr v-for="participant in participants" :key="participant.id" class="align-top">
                <td class="px-3 py-4">
                  <template v-if="participant.rosterEntryId"
                    ><p class="font-semibold">{{ participant.name }}</p>
                    <input
                      v-model="drafts[participant.id].roster_entry_id"
                      type="hidden" /></template
                  ><template v-else
                    ><label class="sr-only" :for="`incoming-name-${participant.id}`">{{
                      t('kingdomP7D.observedName')
                    }}</label
                    ><input
                      :id="`incoming-name-${participant.id}`"
                      v-model="drafts[participant.id].name"
                      :class="inputClass"
                      :disabled="participant.withdrawnAt !== null"
                      maxlength="160"
                      type="text" /><label class="sr-only" :for="`game-id-${participant.id}`">{{
                      t('kingdomP7D.gamePlayerId')
                    }}</label
                    ><input
                      :id="`game-id-${participant.id}`"
                      v-model="drafts[participant.id].game_player_id"
                      :class="inputClass"
                      :disabled="participant.withdrawnAt !== null"
                      maxlength="100"
                      type="text"
                  /></template>
                  <p
                    v-if="participant.withdrawnAt"
                    class="mt-2 text-xs font-semibold text-[var(--ks-text-muted)]"
                  >
                    {{ t('kingdomP7D.withdrawn') }}
                  </p>
                </td>
                <td class="px-3 py-4">
                  <template v-if="participant.rosterEntryId"
                    ><label class="sr-only" :for="`direction-${participant.id}`">{{
                      t('kingdomP7D.direction')
                    }}</label
                    ><select
                      :id="`direction-${participant.id}`"
                      v-model="drafts[participant.id].direction"
                      :class="inputClass"
                      :disabled="participant.withdrawnAt !== null"
                    >
                      <option value="staying">{{ t('kingdomP7D.directionStaying') }}</option>
                      <option value="outgoing">
                        {{ t('kingdomP7D.directionOutgoing') }}
                      </option></select
                    ><template v-if="drafts[participant.id].direction === 'outgoing'"
                      ><label class="sr-only" :for="`destination-${participant.id}`">{{
                        t('kingdomP7D.destinationKingdom')
                      }}</label
                      ><input
                        :id="`destination-${participant.id}`"
                        v-model="drafts[participant.id].destination_kingdom"
                        :class="inputClass"
                        :disabled="participant.withdrawnAt !== null"
                        inputmode="numeric"
                        type="text" /></template></template
                  ><template v-else
                    ><p class="text-sm">
                      {{ directionLabel('incoming') }} →
                      {{ t('kingdomP7D.kingdomValue', { kingdom: mutablePlan.homeKingdom }) }}
                    </p>
                    <label class="sr-only" :for="`source-${participant.id}`">{{
                      t('kingdomP7D.sourceKingdom')
                    }}</label
                    ><input
                      :id="`source-${participant.id}`"
                      v-model="drafts[participant.id].source_kingdom"
                      :class="inputClass"
                      :disabled="participant.withdrawnAt !== null"
                      inputmode="numeric"
                      type="text"
                  /></template>
                </td>
                <td class="px-3 py-4">
                  <p class="text-sm font-medium text-[var(--ks-text)]">
                    {{ participant.player.name }}
                  </p>
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                    {{
                      participant.rosterEntryId
                        ? t('kingdomP7D.rosterEntry')
                        : t('kingdomP7D.player')
                    }}
                  </p>
                </td>
                <td class="px-3 py-4">
                  <template v-if="participant.direction === 'staying'"
                    ><p class="text-sm text-[var(--ks-text-muted)]">
                      {{ t('kingdomP7D.staying') }} · {{ t('kingdomP7D.noGroup') }}
                    </p></template
                  ><template v-else
                    ><label class="sr-only" :for="`group-${participant.id}`">{{
                      t('kingdomP7D.groupAssignment')
                    }}</label
                    ><select
                      :id="`group-${participant.id}`"
                      v-model="groupAssignments[participant.id]"
                      :class="inputClass"
                      :disabled="participant.withdrawnAt !== null"
                    >
                      <option value="">{{ t('kingdomP7D.unassigned') }}</option>
                      <option
                        v-for="group in compatibleGroups(participant)"
                        :key="group.id"
                        :value="group.id"
                      >
                        {{ group.name }}
                      </option></select
                    ><button
                      v-if="participant.withdrawnAt === null"
                      class="mt-2 rounded-lg border border-[var(--ks-border-strong)] px-2.5 py-1.5 text-xs font-semibold text-[var(--ks-gold-bright)]"
                      type="button"
                      @click="assignParticipantGroup(participant)"
                    >
                      {{ t('kingdomP7D.saveGroupAssignment') }}
                    </button></template
                  >
                </td>
                <td class="px-3 py-4">
                  <label class="sr-only" :for="`notes-${participant.id}`">{{
                    t('kingdomP7D.managerNotes')
                  }}</label
                  ><textarea
                    :id="`notes-${participant.id}`"
                    v-model="drafts[participant.id].manager_notes"
                    :class="inputClass"
                    :disabled="participant.withdrawnAt !== null"
                    maxlength="5000"
                    rows="2"
                  />
                </td>
                <td class="px-3 py-4">
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-if="participant.withdrawnAt === null"
                      class="rounded-lg border border-[var(--ks-border-strong)] px-2.5 py-1.5 text-xs font-semibold text-[var(--ks-gold-bright)]"
                      type="button"
                      @click="saveParticipant(participant)"
                    >
                      {{ t('kingdomP7D.save') }}</button
                    ><button
                      v-if="participant.withdrawnAt === null"
                      class="rounded-lg border border-red-400/30 px-2.5 py-1.5 text-xs font-semibold text-red-200"
                      type="button"
                      @click="withdrawParticipant(participant)"
                    >
                      {{ t('kingdomP7D.withdraw') }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="mt-5 text-sm text-[var(--ks-text-muted)]">
          {{ t('kingdomP7D.noParticipants') }}
        </p>
      </section>
    </template>

    <section v-else class="ks-surface mt-6 p-6">
      <h2 class="text-xl font-semibold">
        {{ t('kingdomP7D.participants') }} &amp; {{ t('kingdomP7D.transferGroups') }}
      </h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-text-muted)]">
        {{ t('kingdomP7D.noMutableCycle') }}
      </p>
    </section>
  </AppLayout>
</template>
