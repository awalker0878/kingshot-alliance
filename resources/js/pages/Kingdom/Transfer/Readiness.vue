<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

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
  completedAt: string | null;
  blockers: Blocker[];
  readinessHistory: ReadinessHistory[];
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  plan: Plan | null;
  participants: Participant[];
}>();

const { t, formatDate, formatNumber } = useLocale();

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
  Object.fromEntries(
    props.participants.map((participant) => [participant.id, participant.readiness]),
  ) as Record<string, Readiness>,
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

const readinessCounts = computed(
  () =>
    Object.fromEntries(
      readinessStates.map((state) => [
        state,
        props.participants.filter((participant) => participant.readiness === state).length,
      ]),
    ) as Record<Readiness, number>,
);

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

function directionLabel(direction: Direction): string {
  const key = {
    staying: 'directionStaying',
    outgoing: 'directionOutgoing',
    incoming: 'directionIncoming',
  } as const;
  return t(`kingdomP7D.${key[direction]}`);
}

function blockerStateLabel(state: BlockerState): string {
  return t(state === 'active' ? 'kingdomP7D.blockerActive' : 'kingdomP7D.blockerResolved');
}

function activeBlockers(participant: Participant): Blocker[] {
  return participant.blockers.filter((blocker) => blocker.state === 'active');
}

function allowedTransitions(participant: Participant): Readiness[] {
  const current = participant.readiness;
  if (current === 'withdrawn') return ['withdrawn'];
  const allowed: Record<Exclude<Readiness, 'withdrawn'>, Readiness[]> = {
    not_started: ['not_started', 'preparing', 'blocked'],
    preparing: ['preparing', 'ready', 'blocked'],
    ready: ['ready', 'preparing', 'blocked', 'confirmed'],
    blocked: ['blocked', 'preparing', 'ready'],
    confirmed: ['confirmed', 'ready', 'blocked'],
  };
  return allowed[current];
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
  const draft = blockerDrafts[participant.id]!;
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
  if (!window.confirm(t('kingdomP7D.withdrawConfirm', { name: participant.name }))) return;
  router.post(
    `/alliance/transfers/${props.plan.id}/participants/${participant.id}/withdraw`,
    {},
    { preserveScroll: true },
  );
}

function destinationLabel(participant: Participant): string {
  if (participant.direction === 'staying') return t('kingdomP7D.staying');
  if (participant.direction === 'outgoing' && participant.destinationKingdom === null)
    return t('kingdomP7D.undecided');
  return participant.destinationKingdom ?? '—';
}

function timestamp(value: string | null): string {
  if (!value) return '—';
  return formatDate(value, { dateStyle: 'medium', timeStyle: 'short' });
}

function readinessTone(state: Readiness): string {
  if (state === 'confirmed' || state === 'ready')
    return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (state === 'blocked') return 'border-red-400/25 bg-red-500/10 text-red-200';
  if (state === 'preparing') return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
  if (state === 'withdrawn')
    return 'border-[var(--ks-border)] bg-[rgba(210,163,75,.05)] text-[var(--ks-text-muted)]';
  return 'border-[var(--ks-border)] bg-[var(--ks-teal-soft)] text-[var(--ks-gold-bright)]';
}

const inputClass =
  'mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 text-sm text-[var(--ks-text)] disabled:cursor-not-allowed disabled:opacity-50';
</script>

<template>
  <Head :title="`${t('kingdomP7D.readinessTitle')} · ${alliance.name}`" />

  <AppLayout>
    <header class="flex flex-wrap items-start justify-between gap-5">
      <div class="max-w-3xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('kingdomP7D.readinessEyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('kingdomP7D.readinessTitle') }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{
            t('kingdomP7D.readinessSubtitle', {
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
          class="rounded-lg border border-green-400/30 px-3 py-2 text-sm font-semibold text-green-200"
          href="/alliance/transfers/completion"
          >{{ t('kingdomP7D.completion') }}</Link
        >
        <Link
          class="rounded-lg bg-[var(--ks-gold)] px-3 py-2 text-sm font-bold text-[var(--ks-ink)]"
          href="/alliance/transfers/manage"
          >{{ t('kingdomP7D.manageTransfers') }}</Link
        >
      </nav>
    </header>

    <section
      v-if="plan"
      class="mt-6 grid gap-3 sm:grid-cols-3 lg:grid-cols-6"
      :aria-label="t('kingdomP7D.summary')"
    >
      <article v-for="state in readinessStates" :key="state" class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ readinessLabel(state) }}
        </p>
        <p class="mt-2 text-2xl font-bold">{{ formatNumber(readinessCounts[state]) }}</p>
      </article>
    </section>

    <section v-if="plan" class="ks-surface mt-6 p-5 sm:p-6">
      <div class="flex flex-wrap items-start justify-between gap-5">
        <div>
          <h2 class="text-xl font-semibold">{{ plan.label }}</h2>
          <p class="mt-2 text-sm text-[var(--ks-text-muted)]">
            {{ t('kingdomP7D.homeKingdom') }} {{ plan.homeKingdom }} · {{ stateLabel(plan.state) }}
          </p>
        </div>
        <div class="min-w-56">
          <label
            class="block text-xs font-semibold tracking-wide text-[var(--ks-text-muted)] uppercase"
            for="readiness-filter"
            >{{ t('kingdomP7D.readinessFilter') }}</label
          >
          <select id="readiness-filter" v-model="filter" :class="inputClass">
            <option value="all">{{ t('kingdomP7D.allReadinessStates') }}</option>
            <option v-for="state in readinessStates" :key="state" :value="state">
              {{ readinessLabel(state) }}
            </option>
          </select>
        </div>
      </div>
      <p class="mt-5 max-w-4xl text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('kingdomP7D.readinessHelp') }}
      </p>
      <p
        v-if="!plan.mutable"
        class="mt-3 rounded-lg border border-amber-400/25 bg-amber-500/10 p-3 text-sm font-semibold text-amber-200"
      >
        {{ t('kingdomP7D.readinessReadOnly') }}
      </p>
    </section>

    <section v-if="plan && filteredParticipants.length" class="mt-6 grid gap-5">
      <article
        v-for="participant in filteredParticipants"
        :key="participant.id"
        class="ks-surface p-5 sm:p-6"
      >
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h2 class="text-xl font-semibold">{{ participant.name }}</h2>
            <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
              {{ directionLabel(participant.direction) }} · {{ t('kingdomP7D.destination') }}
              {{ destinationLabel(participant) }} · {{ t('kingdomP7D.group') }}
              {{ participant.groupName ?? t('kingdomP7D.unassigned') }}
            </p>
            <p v-if="participant.completedAt" class="mt-2 text-sm font-semibold text-green-200">
              {{ t('kingdomP7D.completedStatus') }} · {{ timestamp(participant.completedAt) }}
            </p>
          </div>
          <span
            :class="[
              'rounded-full border px-3 py-1 text-sm font-semibold',
              readinessTone(participant.readiness),
            ]"
            >{{ readinessLabel(participant.readiness) }}</span
          >
        </div>

        <div class="mt-6 grid gap-5 lg:grid-cols-2">
          <fieldset
            class="rounded-xl border border-[var(--ks-border)] bg-[var(--ks-parchment)]/[0.02] p-4"
          >
            <legend class="px-2 font-semibold">{{ t('kingdomP7D.readiness') }}</legend>
            <template v-if="participant.withdrawnAt === null">
              <label :for="`readiness-${participant.id}`" class="block text-sm font-semibold">{{
                t('kingdomP7D.planningState')
              }}</label>
              <select
                :id="`readiness-${participant.id}`"
                v-model="readinessDrafts[participant.id]"
                :disabled="!plan.mutable"
                :class="inputClass"
              >
                <option
                  v-for="state in allowedTransitions(participant)"
                  :key="state"
                  :value="state"
                >
                  {{ readinessLabel(state) }}
                </option>
              </select>
              <div class="mt-3 flex flex-wrap gap-2">
                <button
                  class="rounded-lg border border-[var(--ks-border-strong)] px-3 py-2 text-sm font-semibold text-[var(--ks-gold-bright)] disabled:opacity-40"
                  :disabled="
                    !plan.mutable || readinessDrafts[participant.id] === participant.readiness
                  "
                  type="button"
                  @click="saveReadiness(participant)"
                >
                  {{ t('kingdomP7D.saveReadiness') }}
                </button>
                <button
                  class="rounded-lg border border-red-400/30 px-3 py-2 text-sm font-semibold text-red-200 disabled:opacity-40"
                  :disabled="!plan.mutable"
                  type="button"
                  @click="withdrawParticipant(participant)"
                >
                  {{ t('kingdomP7D.withdraw') }}
                </button>
              </div>
              <p v-if="participant.readiness === 'blocked'" class="mt-3 text-sm text-amber-200">
                {{ t('kingdomP7D.blockedHelp') }}
              </p>
            </template>
            <p v-else class="text-sm text-[var(--ks-text-muted)]">
              {{ t('kingdomP7D.withdrawnHistory') }}
            </p>
          </fieldset>

          <fieldset
            class="rounded-xl border border-[var(--ks-border)] bg-[var(--ks-parchment)]/[0.02] p-4"
          >
            <legend class="px-2 font-semibold">{{ t('kingdomP7D.addBlocker') }}</legend>
            <template v-if="participant.withdrawnAt === null">
              <label
                :for="`blocker-summary-${participant.id}`"
                class="block text-sm font-semibold"
                >{{ t('kingdomP7D.blockerSummary') }}</label
              >
              <input
                :id="`blocker-summary-${participant.id}`"
                v-model="blockerDrafts[participant.id]!.summary"
                :disabled="!plan.mutable"
                :class="inputClass"
                maxlength="255"
                type="text"
              />
              <label
                :for="`blocker-details-${participant.id}`"
                class="mt-3 block text-sm font-semibold"
                >{{ t('kingdomP7D.privateDetails') }}</label
              >
              <textarea
                :id="`blocker-details-${participant.id}`"
                v-model="blockerDrafts[participant.id]!.details"
                :disabled="!plan.mutable"
                :class="inputClass"
                maxlength="5000"
                rows="3"
              />
              <button
                class="mt-3 rounded-lg bg-[var(--ks-gold)] px-3 py-2 text-sm font-bold text-[var(--ks-ink)] disabled:opacity-40"
                :disabled="!plan.mutable || blockerDrafts[participant.id]!.summary.trim() === ''"
                type="button"
                @click="addBlocker(participant)"
              >
                {{ t('kingdomP7D.addBlockerAction') }}
              </button>
              <p class="mt-2 text-xs leading-5 text-[var(--ks-text-muted)]">
                {{ t('kingdomP7D.blockerPrivacy') }}
              </p>
            </template>
            <p v-else class="text-sm text-[var(--ks-text-muted)]">
              {{ t('kingdomP7D.noNewBlockers') }}
            </p>
          </fieldset>
        </div>

        <section class="mt-6 border-t border-[var(--ks-border)] pt-5">
          <h3 class="font-semibold">{{ t('kingdomP7D.blockers') }}</h3>
          <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
            {{
              t('kingdomP7D.blockerCounts', {
                active: activeBlockers(participant).length,
                total: participant.blockers.length,
              })
            }}
          </p>
          <div v-if="participant.blockers.length" class="mt-3 grid gap-3 lg:grid-cols-2">
            <article
              v-for="blocker in participant.blockers"
              :key="blocker.id"
              class="rounded-xl border border-[var(--ks-border)] bg-[var(--ks-parchment)]/[0.02] p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h4 class="font-semibold">{{ blocker.summary }}</h4>
                  <p
                    v-if="blocker.details"
                    class="mt-2 text-sm whitespace-pre-wrap text-[var(--ks-text-secondary)]"
                  >
                    {{ blocker.details }}
                  </p>
                </div>
                <span
                  :class="blocker.state === 'active' ? 'text-amber-200' : 'text-green-200'"
                  class="text-xs font-semibold"
                  >{{ blockerStateLabel(blocker.state) }}</span
                >
              </div>
              <p class="mt-3 text-xs text-[var(--ks-text-muted)]">
                {{
                  t('kingdomP7D.addedBy', {
                    actor: blocker.createdBy?.name ?? t('kingdomP7D.unknownActor'),
                  })
                }}<template v-if="blocker.createdAt">
                  · {{ timestamp(blocker.createdAt) }}</template
                >
              </p>
              <p
                v-if="blocker.state === 'resolved'"
                class="mt-1 text-xs text-[var(--ks-text-muted)]"
              >
                {{
                  t('kingdomP7D.resolvedBy', {
                    actor: blocker.resolvedBy?.name ?? t('kingdomP7D.unknownActor'),
                  })
                }}<template v-if="blocker.resolvedAt">
                  · {{ timestamp(blocker.resolvedAt) }}</template
                >
              </p>
              <button
                v-if="blocker.state === 'active' && participant.withdrawnAt === null"
                class="mt-3 rounded-lg border border-green-400/30 px-3 py-1.5 text-xs font-semibold text-green-200 disabled:opacity-40"
                :disabled="!plan.mutable"
                type="button"
                @click="resolveBlocker(participant, blocker)"
              >
                {{ t('kingdomP7D.resolve') }}
              </button>
            </article>
          </div>
          <p v-else class="mt-2 text-sm text-[var(--ks-text-muted)]">
            {{ t('kingdomP7D.noBlockers') }}
          </p>
        </section>

        <section class="mt-6 border-t border-[var(--ks-border)] pt-5">
          <h3 class="font-semibold">{{ t('kingdomP7D.readinessHistory') }}</h3>
          <ol
            v-if="participant.readinessHistory.length"
            class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3"
          >
            <li
              v-for="(entry, index) in participant.readinessHistory"
              :key="`${entry.changedAt}-${index}`"
              class="rounded-xl border border-[var(--ks-border)] p-3 text-sm"
            >
              <p class="font-semibold">
                {{ entry.from === null ? t('kingdomP7D.initial') : readinessLabel(entry.from) }} →
                {{ readinessLabel(entry.to) }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                {{
                  t('kingdomP7D.transitionBy', {
                    actor: entry.actor?.name ?? t('kingdomP7D.unknownActor'),
                    date: timestamp(entry.changedAt),
                  })
                }}
              </p>
            </li>
          </ol>
          <p v-else class="mt-2 text-sm text-[var(--ks-text-muted)]">
            {{ t('kingdomP7D.noReadinessHistory') }}
          </p>
        </section>
      </article>
    </section>

    <section v-else-if="plan" class="ks-surface mt-6 p-6">
      <p class="text-sm text-[var(--ks-text-muted)]">{{ t('kingdomP7D.noReadinessMatch') }}</p>
    </section>
    <section v-else class="ks-surface mt-6 p-6">
      <h2 class="text-xl font-semibold">{{ t('kingdomP7D.noCompletionCycle') }}</h2>
      <p class="mt-2 text-sm text-[var(--ks-text-muted)]">
        {{ t('kingdomP7D.noCompletionCycleHelp') }}
      </p>
    </section>
  </AppLayout>
</template>
