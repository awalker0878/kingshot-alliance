<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

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
  rosterEntry: {
    id: string;
    name: string;
    state: string;
    gamePlayerId: string | null;
  } | null;
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

type RosterOption = {
  id: string;
  name: string;
  state: string;
  gamePlayerId: string | null;
  membershipId: string | null;
};

const props = defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  plan: Plan | null;
  participants: Participant[];
  rosterOptions: RosterOption[];
}>();

const rosterDrafts = reactive(
  Object.fromEntries(props.participants.map((participant) => [participant.id, ''])) as Record<
    string,
    string
  >,
);

function stateLabel(state: string): string {
  return state
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function directionLabel(direction: Direction): string {
  return direction.charAt(0).toUpperCase() + direction.slice(1);
}

function destinationLabel(participant: Participant): string {
  if (participant.direction === 'staying') return 'Staying';
  if (participant.direction === 'outgoing' && participant.destinationKingdom === null) {
    return 'Undecided';
  }

  return participant.destinationKingdom ?? '—';
}

function eligibleRosterOptions(participant: Participant): RosterOption[] {
  if (participant.gamePlayerId === null) return props.rosterOptions;

  return props.rosterOptions.filter(
    (option) => option.gamePlayerId === participant.gamePlayerId,
  );
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
  if (participant.direction === 'incoming') {
    return `Confirm that ${participant.name} has actually arrived and hand this participant off to the alliance roster?`;
  }

  if (participant.direction === 'outgoing') {
    return `Confirm that ${participant.name} has actually left and mark the accepted roster entry Left?`;
  }

  return `Confirm that ${participant.name} is staying? This records completion but does not change roster lifecycle state.`;
}

function completeParticipant(participant: Participant): void {
  if (props.plan === null || !canComplete(participant)) return;
  if (!window.confirm(confirmationText(participant))) return;

  const rosterEntryId = participant.direction === 'incoming' ? rosterDrafts[participant.id] : '';

  router.post(
    `/alliance/transfers/${props.plan.id}/participants/${participant.id}/complete`,
    { roster_entry_id: rosterEntryId || null },
    { preserveScroll: true },
  );
}
</script>

<template>
  <Head :title="`Transfer completion · ${alliance.name}`" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 text-slate-100 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link
          class="text-sm font-semibold text-cyan-300 hover:text-cyan-200"
          href="/alliance/transfers"
        >
          ← Transfer planning
        </Link>
        <h1 class="mt-3 text-3xl font-bold">Explicit transfer completion</h1>
        <p class="mt-2 text-sm text-slate-400">
          {{ alliance.name }} · Kingdom {{ alliance.kingdom ?? 'not set' }}
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <Link
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold hover:border-slate-500"
          href="/alliance/transfers/readiness"
        >
          Readiness board
        </Link>
        <Link
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold hover:border-slate-500"
          href="/alliance/transfers/manage"
        >
          Manage transfers
        </Link>
      </div>
    </header>

    <section v-if="plan" class="mt-10 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">{{ plan.label }}</h2>
      <p class="mt-2 text-sm text-slate-400">
        Home Kingdom {{ plan.homeKingdom }} · {{ stateLabel(plan.state) }}
      </p>
      <p class="mt-4 text-sm text-slate-300">
        Readiness confirmation is still planning state. Roster handoff happens only when a manager
        explicitly records the real-world outcome here. Each participant is completed separately;
        there is no automatic or bulk completion action.
      </p>
      <p v-if="!plan.completable" class="mt-3 text-sm font-semibold text-amber-200">
        Lock this transfer cycle before recording actual completion. Participant planning remains
        editable only before the cycle is Locked.
      </p>
    </section>

    <section v-if="plan && participants.length" class="mt-8 grid gap-6">
      <article
        v-for="participant in participants"
        :key="participant.id"
        class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6"
      >
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h2 class="text-xl font-semibold">{{ participant.name }}</h2>
            <p class="mt-1 text-sm text-slate-400">
              {{ directionLabel(participant.direction) }} · Destination
              {{ destinationLabel(participant) }} · Readiness
              {{ stateLabel(participant.readiness) }}
            </p>
            <p v-if="participant.gamePlayerId" class="mt-1 text-xs text-slate-500">
              Stable game ID {{ participant.gamePlayerId }}
            </p>
          </div>
          <span
            class="rounded-full border border-slate-700 px-3 py-1 text-sm font-semibold"
          >
            {{ participant.completion ? 'Completed' : 'Not completed' }}
          </span>
        </div>

        <section
          v-if="participant.completion"
          class="mt-5 rounded-xl border border-emerald-900/70 bg-emerald-950/20 p-4"
        >
          <h3 class="font-semibold">Roster handoff recorded</h3>
          <p class="mt-2 text-sm text-slate-300">
            {{ participant.completion.completedAt }} ·
            {{ participant.completion.completedBy?.name ?? 'Unknown actor' }}
          </p>
          <p v-if="participant.completion.rosterEntry" class="mt-2 text-sm text-slate-300">
            Roster result: {{ participant.completion.rosterEntry.name }} ·
            {{ stateLabel(participant.completion.rosterEntry.state) }}
          </p>
          <p class="mt-2 text-xs text-slate-500">
            Completion is idempotent. Retrying this participant does not repeat roster lifecycle
            mutations.
          </p>
        </section>

        <section v-else-if="participant.withdrawnAt !== null" class="mt-5 text-sm text-slate-400">
          This participant was withdrawn and cannot be completed.
        </section>

        <section v-else class="mt-5 rounded-xl border border-slate-800 p-4">
          <template v-if="participant.direction === 'incoming'">
            <label :for="`roster-result-${participant.id}`" class="block text-sm font-semibold">
              Existing roster result (optional)
            </label>
            <select
              :id="`roster-result-${participant.id}`"
              v-model="rosterDrafts[participant.id]"
              :disabled="!canComplete(participant)"
              class="mt-2 text-slate-950"
            >
              <option value="">Create a new accepted roster entry</option>
              <option
                v-for="option in eligibleRosterOptions(participant)"
                :key="option.id"
                :value="option.id"
              >
                {{ option.name }} · {{ stateLabel(option.state) }}
                {{ option.gamePlayerId ? `· ID ${option.gamePlayerId}` : '' }}
              </option>
            </select>
            <p class="mt-2 text-xs text-slate-500">
              Existing roster entries are selected explicitly. The system never links an incoming
              player by display name alone. Existing accepted roster/private fields are preserved.
            </p>
          </template>

          <p v-if="participant.direction === 'outgoing'" class="text-sm text-slate-300">
            Completion delegates to the accepted roster mark-left action. Existing snapshots and
            neutral identity are preserved.
          </p>
          <p v-if="participant.direction === 'staying'" class="text-sm text-slate-300">
            Completion records the planning outcome only. It performs no roster lifecycle mutation.
          </p>

          <button
            class="mt-4"
            :disabled="!canComplete(participant)"
            type="button"
            @click="completeParticipant(participant)"
          >
            Record actual completion
          </button>
          <p v-if="participant.readiness !== 'confirmed'" class="mt-2 text-xs text-amber-200">
            This participant must be explicitly Confirmed before completion.
          </p>
        </section>
      </article>
    </section>

    <section v-else-if="plan" class="mt-8 rounded-2xl border border-slate-800 p-6">
      <p>No participants are recorded for this transfer cycle.</p>
    </section>

    <section v-else class="mt-10 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">No current transfer cycle</h2>
      <p class="mt-2 text-sm text-slate-400">
        Create and manage a transfer cycle before recording completion.
      </p>
    </section>
  </main>
</template>
