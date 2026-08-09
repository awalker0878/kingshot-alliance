<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

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

type Participant = {
  id: string;
  direction: 'staying' | 'outgoing' | 'incoming';
  name: string;
  gamePlayerId: string | null;
  sourceKingdom: string | null;
  destinationKingdom: string | null;
  membership: { name: string } | null;
  group: Group | null;
};

defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  canManage: boolean;
  plan: Plan | null;
  groups: Group[];
  participants: Participant[];
}>();

function stateLabel(state: string): string {
  return state.charAt(0).toUpperCase() + state.slice(1);
}

function directionLabel(direction: Participant['direction'] | Group['direction']): string {
  return direction.charAt(0).toUpperCase() + direction.slice(1);
}

function destinationLabel(participant: Participant): string {
  if (participant.direction === 'staying') return 'Staying';
  if (participant.direction === 'outgoing' && participant.destinationKingdom === null) {
    return 'Undecided';
  }

  return participant.destinationKingdom ?? '—';
}

function groupDestinationLabel(group: Group): string {
  if (group.direction === 'outgoing' && group.destinationKingdom === null) return 'Undecided';

  return group.destinationKingdom ?? '—';
}
</script>

<template>
  <Head :title="`Transfers · ${alliance.name}`" />

  <main class="mx-auto min-h-screen max-w-5xl px-6 py-12 text-slate-100 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/alliance">
          ← Alliance
        </Link>
        <h1 class="mt-3 text-3xl font-bold">Transfer planning</h1>
        <p class="mt-2 text-sm text-slate-400">
          {{ alliance.name }} · Kingdom {{ alliance.kingdom ?? 'not set' }}
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <Link
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold hover:border-slate-500"
          href="/alliance/roster"
        >
          Roster
        </Link>
        <Link
          v-if="canManage"
          class="rounded-lg bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950"
          href="/alliance/transfers/manage"
        >
          Manage transfers
        </Link>
      </div>
    </header>

    <section class="mt-10 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">Current cycle</h2>

      <div v-if="plan" class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2">
          <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Cycle</p>
          <p class="mt-1 text-lg font-semibold">{{ plan.label }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">State</p>
          <p class="mt-1 font-semibold">{{ stateLabel(plan.state) }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Home kingdom</p>
          <p class="mt-1 font-semibold">{{ plan.homeKingdom }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Starts</p>
          <p class="mt-1">{{ plan.startsOn ?? 'Not specified' }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Ends</p>
          <p class="mt-1">{{ plan.endsOn ?? 'Not specified' }}</p>
        </div>
      </div>

      <p v-else class="mt-4 text-sm text-slate-400">
        There is no current transfer cycle for this alliance.
      </p>
    </section>

    <section v-if="plan" class="mt-10">
      <h2 class="text-xl font-semibold">Transfer groups</h2>
      <p class="mt-2 text-sm text-slate-400">
        Groups coordinate players moving in the same direction. Coordinator assignment is workflow
        responsibility only and does not grant management permissions.
      </p>

      <div v-if="groups.length" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="group in groups"
          :key="`${group.direction}-${group.name}`"
          class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5"
        >
          <h3 class="font-semibold">{{ group.name }}</h3>
          <p class="mt-2 text-sm text-slate-400">
            {{ directionLabel(group.direction) }} · Kingdom {{ groupDestinationLabel(group) }}
          </p>
          <p class="mt-1 text-sm text-slate-400">
            Coordinator: {{ group.coordinator?.name ?? 'Unassigned' }}
          </p>
        </article>
      </div>
      <p v-else class="mt-5 text-sm text-slate-400">No transfer groups have been created yet.</p>
    </section>

    <section v-if="plan" class="mt-10">
      <h2 class="text-xl font-semibold">Planned participants</h2>
      <p class="mt-2 text-sm text-slate-400">
        Incoming, outgoing, staying, group, and destination intent is manually maintained by alliance
        leadership.
      </p>

      <div
        v-if="participants.length"
        class="mt-5 overflow-x-auto rounded-2xl border border-slate-800"
      >
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <thead class="bg-slate-900/80 text-xs tracking-wide text-slate-400 uppercase">
            <tr>
              <th class="px-4 py-3" scope="col">Player</th>
              <th class="px-4 py-3" scope="col">Direction</th>
              <th class="px-4 py-3" scope="col">Source</th>
              <th class="px-4 py-3" scope="col">Destination</th>
              <th class="px-4 py-3" scope="col">Group</th>
              <th class="px-4 py-3" scope="col">Membership</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 bg-slate-950/40">
            <tr v-for="participant in participants" :key="participant.id">
              <td class="px-4 py-4">
                <span class="font-semibold">{{ participant.name }}</span>
                <span v-if="participant.gamePlayerId" class="block text-xs text-slate-500">
                  ID {{ participant.gamePlayerId }}
                </span>
              </td>
              <td class="px-4 py-4">{{ directionLabel(participant.direction) }}</td>
              <td class="px-4 py-4">{{ participant.sourceKingdom ?? 'Unknown' }}</td>
              <td class="px-4 py-4">{{ destinationLabel(participant) }}</td>
              <td class="px-4 py-4">
                <template v-if="participant.group">
                  <span class="font-semibold">{{ participant.group.name }}</span>
                  <span class="block text-xs text-slate-500">
                    Coordinator: {{ participant.group.coordinator?.name ?? 'Unassigned' }}
                  </span>
                </template>
                <span v-else>Unassigned</span>
              </td>
              <td class="px-4 py-4">{{ participant.membership?.name ?? 'Not linked' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-else class="mt-5 text-sm text-slate-400">
        No participants have been added to this transfer cycle yet.
      </p>
    </section>
  </main>
</template>
