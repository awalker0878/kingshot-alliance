<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

type Readiness = 'not_started' | 'preparing' | 'ready' | 'blocked' | 'confirmed' | 'withdrawn';
type Direction = 'staying' | 'outgoing' | 'incoming';
type BlockerState = 'active' | 'resolved';

type Plan = {
  id: string;
  label: string;
  homeKingdom: string;
  state: string;
  mutable: boolean;
};

type Blocker = {
  id: string;
  state: BlockerState;
  summary: string;
  details: string | null;
  createdAt: string | null;
  resolvedAt: string | null;
  createdBy: { name: string } | null;
  resolvedBy: { name: string } | null;
};

type ReadinessHistory = {
  from: Readiness | null;
  to: Readiness;
  changedAt: string;
  actor: { name: string } | null;
};

type Participant = {
  id: string;
  name: string;
  direction: Direction;
  readiness: Readiness;
  groupName: string | null;
  destinationKingdom: string | null;
  withdrawnAt: string | null;
  blockers: Blocker[];
  readinessHistory: ReadinessHistory[];
};

const props = defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  plan: Plan | null;
  participants: Participant[];
}>();

const readinessStates: Readiness[] = [
  'not_started',
  'preparing',
  'ready',
  'blocked',
  'confirmed',
  'withdrawn',
];

const filter = ref<'all' | Readiness>('all');
const readinessDrafts = reactive(
  Object.fromEntries(props.participants.map((participant) => [participant.id, participant.readiness])) as Record<
    string,
    Readiness
  >,
);
const blockerDrafts = reactive(
  Object.fromEntries(
    props.participants.map((participant) => [participant.id, { summary: '', details: '' }]),
  ) as Record<string, { summary: string; details: string }>,
);

const filteredParticipants = computed(() => {
  if (filter.value === 'all') return props.participants;

  return props.participants.filter((participant) => participant.readiness === filter.value);
});

function stateLabel(state: string): string {
  return state
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function directionLabel(direction: Direction): string {
  return direction.charAt(0).toUpperCase() + direction.slice(1);
}

function activeBlockers(participant: Participant): Blocker[] {
  return participant.blockers.filter((blocker) => blocker.state === 'active');
}

function allowedTransitions(participant: Participant): Readiness[] {
  if (participant.readiness === 'withdrawn') return ['withdrawn'];

  const allowed: Record<Exclude<Readiness, 'withdrawn'>, Readiness[]> = {
    not_started: ['not_started', 'preparing', 'blocked'],
    preparing: ['preparing', 'ready', 'blocked'],
    ready: ['ready', 'preparing', 'blocked', 'confirmed'],
    blocked: ['blocked', 'preparing', 'ready'],
    confirmed: ['confirmed', 'ready', 'blocked'],
  };

  return allowed[participant.readiness];
}

function saveReadiness(participant: Participant): void {
  if (props.plan === null || !props.plan.mutable || participant.withdrawnAt !== null) return;

  router.patch(
    `/alliance/transfers/${props.plan.id}/participants/${participant.id}/readiness`,
    { readiness: readinessDrafts[participant.id] },
    { preserveScroll: true },
  );
}

function addBlocker(participant: Participant): void {
  if (props.plan === null || !props.plan.mutable || participant.withdrawnAt !== null) return;

  const draft = blockerDrafts[participant.id];
  router.post(
    `/alliance/transfers/${props.plan.id}/participants/${participant.id}/blockers`,
    { summary: draft.summary, details: draft.details || null },
    {
      preserveScroll: true,
      onSuccess: () => {
        draft.summary = '';
        draft.details = '';
      },
    },
  );
}

function resolveBlocker(participant: Participant, blocker: Blocker): void {
  if (props.plan === null || !props.plan.mutable || blocker.state !== 'active') return;

  router.post(
    `/alliance/transfers/${props.plan.id}/participants/${participant.id}/blockers/${blocker.id}/resolve`,
    {},
    { preserveScroll: true },
  );
}

function withdrawParticipant(participant: Participant): void {
  if (props.plan === null || !props.plan.mutable || participant.withdrawnAt !== null) return;
  if (!window.confirm(`Withdraw ${participant.name} from this transfer cycle?`)) return;

  router.post(
    `/alliance/transfers/${props.plan.id}/participants/${participant.id}/withdraw`,
    {},
    { preserveScroll: true },
  );
}

function destinationLabel(participant: Participant): string {
  if (participant.direction === 'staying') return 'Staying';
  if (participant.direction === 'outgoing' && participant.destinationKingdom === null) return 'Undecided';

  return participant.destinationKingdom ?? '—';
}
</script>

<template>
  <Head :title="`Transfer readiness · ${alliance.name}`" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 text-slate-100 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/alliance/transfers">
          ← Transfer planning
        </Link>
        <h1 class="mt-3 text-3xl font-bold">Transfer readiness</h1>
        <p class="mt-2 text-sm text-slate-400">
          {{ alliance.name }} · Kingdom {{ alliance.kingdom ?? 'not set' }}
        </p>
      </div>
      <Link
        class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold hover:border-slate-500"
        href="/alliance/transfers/manage"
      >
        Manage transfers
      </Link>
    </header>

    <section v-if="plan" class="mt-10 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h2 class="text-xl font-semibold">{{ plan.label }}</h2>
          <p class="mt-2 text-sm text-slate-400">
            Home Kingdom {{ plan.homeKingdom }} · {{ stateLabel(plan.state) }}
          </p>
        </div>
        <div>
          <label class="block text-sm font-semibold" for="readiness-filter">Readiness filter</label>
          <select id="readiness-filter" v-model="filter" class="mt-2 text-slate-950">
            <option value="all">All readiness states</option>
            <option v-for="state in readinessStates" :key="state" :value="state">
              {{ stateLabel(state) }}
            </option>
          </select>
        </div>
      </div>

      <p class="mt-4 text-sm text-slate-300">
        Readiness is manually maintained. Resolving blockers never advances readiness automatically,
        and Confirmed remains a planning state until an explicit roster handoff is implemented in a later slice.
      </p>
      <p v-if="!plan.mutable" class="mt-3 text-sm font-semibold text-amber-200">
        This cycle is read-only. Readiness history remains visible, but transitions and blockers cannot change.
      </p>
    </section>

    <section v-if="plan && filteredParticipants.length" class="mt-8 grid gap-6">
      <article
        v-for="participant in filteredParticipants"
        :key="participant.id"
        class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6"
      >
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h2 class="text-xl font-semibold">{{ participant.name }}</h2>
            <p class="mt-1 text-sm text-slate-400">
              {{ directionLabel(participant.direction) }} · Destination {{ destinationLabel(participant) }} ·
              Group {{ participant.groupName ?? 'Unassigned' }}
            </p>
          </div>
          <p class="rounded-full border border-slate-700 px-3 py-1 text-sm font-semibold">
            {{ stateLabel(participant.readiness) }}
          </p>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
          <fieldset class="rounded-xl border border-slate-800 p-4">
            <legend class="px-2 font-semibold">Readiness</legend>
            <template v-if="participant.withdrawnAt === null">
              <label :for="`readiness-${participant.id}`" class="block text-sm font-semibold">
                Planning state
              </label>
              <select
                :id="`readiness-${participant.id}`"
                v-model="readinessDrafts[participant.id]"
                :disabled="!plan.mutable"
                class="mt-2 text-slate-950"
              >
                <option v-for="state in allowedTransitions(participant)" :key="state" :value="state">
                  {{ stateLabel(state) }}
                </option>
              </select>
              <div class="mt-3 flex flex-wrap gap-3">
                <button
                  :disabled="!plan.mutable || readinessDrafts[participant.id] === participant.readiness"
                  type="button"
                  @click="saveReadiness(participant)"
                >
                  Save readiness
                </button>
                <button
                  :disabled="!plan.mutable"
                  type="button"
                  @click="withdrawParticipant(participant)"
                >
                  Withdraw
                </button>
              </div>
              <p v-if="participant.readiness === 'blocked'" class="mt-3 text-sm text-slate-400">
                Resolve every active blocker before leaving Blocked. The next state is still chosen explicitly.
              </p>
            </template>
            <p v-else class="text-sm text-slate-400">Withdrawn participants are retained as history.</p>
          </fieldset>

          <fieldset class="rounded-xl border border-slate-800 p-4">
            <legend class="px-2 font-semibold">Add blocker</legend>
            <template v-if="participant.withdrawnAt === null">
              <label :for="`blocker-summary-${participant.id}`" class="block text-sm font-semibold">
                Summary
              </label>
              <input
                :id="`blocker-summary-${participant.id}`"
                v-model="blockerDrafts[participant.id].summary"
                :disabled="!plan.mutable"
                maxlength="255"
                type="text"
              />
              <label :for="`blocker-details-${participant.id}`" class="mt-3 block text-sm font-semibold">
                Private details
              </label>
              <textarea
                :id="`blocker-details-${participant.id}`"
                v-model="blockerDrafts[participant.id].details"
                :disabled="!plan.mutable"
                maxlength="5000"
                rows="3"
              />
              <button
                class="mt-3"
                :disabled="!plan.mutable || blockerDrafts[participant.id].summary.trim() === ''"
                type="button"
                @click="addBlocker(participant)"
              >
                Add blocker
              </button>
              <p class="mt-2 text-xs text-slate-500">
                Blocker text is management-only and is not copied into audit or outbox payloads.
              </p>
            </template>
            <p v-else class="text-sm text-slate-400">No new blockers can be added after withdrawal.</p>
          </fieldset>
        </div>

        <section class="mt-6">
          <h3 class="font-semibold">Blockers</h3>
          <p class="mt-1 text-sm text-slate-400">
            {{ activeBlockers(participant).length }} active · {{ participant.blockers.length }} recorded
          </p>
          <div v-if="participant.blockers.length" class="mt-3 grid gap-3">
            <article
              v-for="blocker in participant.blockers"
              :key="blocker.id"
              class="rounded-xl border border-slate-800 p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h4 class="font-semibold">{{ blocker.summary }}</h4>
                  <p v-if="blocker.details" class="mt-2 whitespace-pre-wrap text-sm text-slate-300">
                    {{ blocker.details }}
                  </p>
                  <p class="mt-2 text-xs text-slate-500">
                    Added by {{ blocker.createdBy?.name ?? 'Unknown actor' }}
                    <template v-if="blocker.createdAt"> · {{ blocker.createdAt }}</template>
                  </p>
                  <p v-if="blocker.state === 'resolved'" class="mt-1 text-xs text-slate-500">
                    Resolved by {{ blocker.resolvedBy?.name ?? 'Unknown actor' }}
                    <template v-if="blocker.resolvedAt"> · {{ blocker.resolvedAt }}</template>
                  </p>
                </div>
                <div>
                  <span class="text-sm font-semibold">{{ stateLabel(blocker.state) }}</span>
                  <button
                    v-if="blocker.state === 'active' && participant.withdrawnAt === null"
                    class="ml-3"
                    :disabled="!plan.mutable"
                    type="button"
                    @click="resolveBlocker(participant, blocker)"
                  >
                    Resolve
                  </button>
                </div>
              </div>
            </article>
          </div>
          <p v-else class="mt-2 text-sm text-slate-400">No blockers recorded.</p>
        </section>

        <section class="mt-6">
          <h3 class="font-semibold">Readiness history</h3>
          <ol v-if="participant.readinessHistory.length" class="mt-3 grid gap-2">
            <li
              v-for="(entry, index) in participant.readinessHistory"
              :key="`${entry.changedAt}-${index}`"
              class="rounded-xl border border-slate-800 p-3 text-sm"
            >
              {{ entry.from === null ? 'Initial' : stateLabel(entry.from) }} → {{ stateLabel(entry.to) }}
              <span class="text-slate-500">
                · {{ entry.actor?.name ?? 'Unknown actor' }} · {{ entry.changedAt }}
              </span>
            </li>
          </ol>
          <p v-else class="mt-2 text-sm text-slate-400">No readiness transitions recorded yet.</p>
        </section>
      </article>
    </section>

    <section v-else-if="plan" class="mt-8 rounded-2xl border border-slate-800 p-6">
      <p>No participants match the selected readiness filter.</p>
    </section>

    <section v-else class="mt-10 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">No current transfer cycle</h2>
      <p class="mt-2 text-sm text-slate-400">
        Create a Draft cycle from transfer management before maintaining readiness.
      </p>
    </section>
  </main>
</template>
