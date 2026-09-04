<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type TextChange = { from: string | null; to: string | null };
type SnapshotChange = {
  fromCapturedAt: string;
  power: string;
  observedName: TextChange | null;
  progressionLevel: TextChange | null;
  observedAllianceTag: TextChange | null;
};

type Snapshot = {
  id: string;
  observedName: string;
  power: string;
  progressionLevel: string | null;
  observedAllianceTag: string | null;
  capturedAt: string;
  source: string;
  change: SnapshotChange | null;
  actorName?: string;
};
type CapabilityProfile = {
  eventAccess: 'available' | 'unavailable';
  events: {
    count: number;
    completed: number;
    absent: number;
    excused: number;
    unresolved: number;
    recent: Array<{
      occurrenceId: string;
      nameKey: string;
      slug: string;
      startsAt: string | null;
      outcome: string | null;
      score: number | null;
      rank: number | null;
      recordedAt: string | null;
    }>;
  };
  bearHunt: {
    runCount: number;
    recordedResultCount: number;
    latestRecorded: {
      occurrenceId: string;
      startsAt: string | null;
      damage: number | null;
      rank: number | null;
      recordedAt: string | null;
    } | null;
  };
  rallies: Array<{
    id: string;
    occurrenceId: string;
    startsAt: string;
    groupName: string;
    role: string;
    status: string;
    respondedAt: string | null;
    recordedAt: string | null;
  }>;
  battleAssignments: Array<{
    id: string;
    occurrenceId: string;
    startsAt: string;
    objectiveName: string;
    objectiveType: string;
    status: string;
    assignedAt: string | null;
  }>;
  transfer: {
    access: 'available' | 'unavailable';
    assessment: {
      participantId: string;
      planId: string;
      direction: string;
      readinessState: string;
      outcome: string;
      evaluatedAt: string;
      primaryAction: string | null;
      requirements: Array<{
        key: string;
        state: string;
        sourceReference: string | null;
        observedAt: string | null;
        validUntil: string | null;
      }>;
    } | null;
  };
  evidence: {
    access: 'available' | 'unavailable';
    total: number;
    pending: number;
    needsReview: number;
    committed: number;
    failed: number;
    latestAt: string | null;
  };
  membershipGovernance: {
    access: 'available' | 'unavailable';
    href: string;
    history: Array<{
      id: string;
      type: string;
      occurredAt: string;
      source: string;
    }>;
  };
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  entry: {
    id: string;
    gamePlayerId: string | null;
    name: string;
    gameRole: string | null;
    state: string;
    membership: { name: string } | null;
  };
  canManage: boolean;
  latest: Snapshot | null;
  snapshots: Snapshot[];
  hasMoreSnapshots: boolean;
  staleAfterDays: number;
  capabilityProfile: CapabilityProfile;
}>();

const { locale, t, formatDate, formatNumber } = useLocale();

function localDateTimeValue(date = new Date()): string {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
  return local.toISOString().slice(0, 16);
}

function formatPower(value: string): string {
  try {
    return new Intl.NumberFormat(locale.value).format(BigInt(value));
  } catch {
    return value;
  }
}

function formatSignedPower(value: string): string {
  const formatted = formatPower(value);
  return value.startsWith('-') || value === '0' ? formatted : `+${formatted}`;
}

function powerChangeTone(value: string): string {
  if (value.startsWith('-')) return 'text-red-300';
  if (value === '0') return 'text-[var(--ks-text-muted)]';
  return 'text-green-300';
}

function observedValue(value: string | null): string {
  return value ?? '—';
}

function formatCaptured(value: string): string {
  return formatDate(value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

function snapshotState(snapshot: Snapshot | null): 'current' | 'stale' | 'missing' {
  if (snapshot === null) return 'missing';

  const staleAt = Date.now() - props.staleAfterDays * 24 * 60 * 60 * 1000;
  return new Date(snapshot.capturedAt).getTime() < staleAt ? 'stale' : 'current';
}

function stateLabel(value: string): string {
  const key = `roster.${value}`;
  const translated = t(key);
  return translated === key ? value.replaceAll('_', ' ') : translated;
}

function freshnessTone(value: 'current' | 'stale' | 'missing'): string {
  if (value === 'current') return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (value === 'stale') return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
  return 'border-red-400/25 bg-red-500/10 text-red-200';
}

const snapshotForm = useForm({
  observed_name: props.entry.name,
  power: '',
  progression_level: props.latest?.progressionLevel ?? '',
  observed_alliance_tag: props.latest?.observedAllianceTag ?? '',
  captured_at: localDateTimeValue(),
});

function recordSnapshot(): void {
  snapshotForm
    .transform((data) => ({
      ...data,
      captured_at: new Date(data.captured_at).toISOString(),
    }))
    .post(`/alliance/roster/${props.entry.id}/snapshots`, {
      preserveScroll: true,
      onSuccess: () => {
        snapshotForm.power = '';
        snapshotForm.progression_level = props.latest?.progressionLevel ?? '';
        snapshotForm.observed_alliance_tag = props.latest?.observedAllianceTag ?? '';
        snapshotForm.captured_at = localDateTimeValue();
      },
    });
}
</script>

<template>
  <Head :title="`${t('rosterHistory.title')} · ${entry.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('roster.eyebrow', { kingdom: alliance.kingdom ?? t('roster.kingdomNotSet') })"
      :title="entry.name"
      :subtitle="`${t('roster.gameId')}: ${entry.gamePlayerId ?? t('rosterManage.unknown')} · ${entry.membership?.name ?? t('roster.unlinked')}`"
      image="/images/kingshot/v4/roster-hall.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/roster" class="ks-command-link" data-variant="secondary">
          ← {{ t('roster.title') }}
        </Link>
        <Link v-if="canManage" href="/alliance/roster/manage" class="ks-command-link">
          {{ t('roster.manage') }}
        </Link>
      </template>
    </RoomBanner>

    <section class="ks-surface-gold mt-6 overflow-hidden" :aria-label="t('rosterHistory.title')">
      <div
        class="grid grid-cols-2 divide-x divide-y divide-[var(--ks-border)] md:grid-cols-4 md:divide-y-0"
      >
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('roster.snapshotState') }}
          </p>
          <span
            :class="freshnessTone(snapshotState(latest))"
            class="mt-3 inline-flex rounded-full border px-3 py-1 text-sm font-semibold"
          >
            {{ stateLabel(snapshotState(latest)) }}
          </span>
        </article>
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('rosterManage.latestPower') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold text-[var(--ks-gold-strong)]">
            {{ latest ? formatPower(latest.power) : '—' }}
          </p>
          <p
            v-if="latest?.change"
            class="mt-1 text-xs font-semibold"
            :class="powerChangeTone(latest.change.power)"
          >
            {{ formatSignedPower(latest.change.power) }}
            {{ t('rosterHistory.sincePriorObservation') }}
          </p>
        </article>
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('roster.progression') }}
          </p>
          <p class="ks-display mt-2 text-2xl font-semibold">
            {{ latest?.progressionLevel ?? '—' }}
          </p>
          <p
            v-if="latest?.change?.progressionLevel"
            class="mt-1 text-xs text-[var(--ks-text-muted)]"
          >
            {{ observedValue(latest.change.progressionLevel.from) }} →
            {{ observedValue(latest.change.progressionLevel.to) }}
          </p>
        </article>
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('roster.allianceTag') }}
          </p>
          <p class="ks-display mt-2 text-2xl font-semibold">
            {{ latest?.observedAllianceTag ?? '—' }}
          </p>
        </article>
      </div>
    </section>

    <p class="mt-3 text-xs leading-5 text-[var(--ks-text-muted)]">
      {{ t('rosterHistory.currentHelp', { days: staleAfterDays }) }}
    </p>

    <section class="ks-surface-gold mt-6 p-5 sm:p-6" :aria-labelledby="'member-capability-profile'">
      <p class="ks-kicker">{{ t('rosterHistory.capability.eyebrow') }}</p>
      <h2 id="member-capability-profile" class="mt-1 text-xl font-semibold">
        {{ t('rosterHistory.capability.title') }}
      </h2>
      <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-text-muted)]">
        {{ t('rosterHistory.capability.help') }}
      </p>

      <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <article class="ks-surface p-4">
          <div class="flex items-center justify-between gap-2">
            <h3 class="font-semibold">{{ t('rosterHistory.capability.events') }}</h3>
            <Link
              :href="`/alliances/${alliance.id}/events/history`"
              class="text-xs font-semibold text-[var(--ks-gold)]"
            >
              {{ t('rosterHistory.capability.openOwner') }}
            </Link>
          </div>
          <p
            v-if="capabilityProfile.eventAccess === 'unavailable'"
            class="mt-3 text-sm text-[var(--ks-text-muted)]"
          >
            {{ t('rosterHistory.capability.unavailable') }}
          </p>
          <template v-else>
            <p class="ks-display mt-3 text-3xl font-semibold">
              {{ capabilityProfile.events.count }}
            </p>
            <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
              {{
                t('rosterHistory.capability.eventSummary', {
                  completed: capabilityProfile.events.completed,
                  absent: capabilityProfile.events.absent,
                  unresolved: capabilityProfile.events.unresolved,
                })
              }}
            </p>
            <ul v-if="capabilityProfile.events.recent.length" class="mt-3 space-y-2 text-sm">
              <li
                v-for="event in capabilityProfile.events.recent.slice(0, 3)"
                :key="event.occurrenceId"
              >
                <Link
                  :href="`/events/${event.occurrenceId}`"
                  class="font-semibold text-[var(--ks-gold)]"
                >
                  {{ t(event.nameKey) }}
                </Link>
                <span v-if="event.startsAt" class="ms-2 text-xs text-[var(--ks-text-muted)]">{{
                  formatCaptured(event.startsAt)
                }}</span>
              </li>
            </ul>
            <p v-else class="mt-3 text-sm text-[var(--ks-text-muted)]">
              {{ t('rosterHistory.capability.noEvents') }}
            </p>
          </template>
        </article>

        <article class="ks-surface p-4">
          <h3 class="font-semibold">{{ t('rosterHistory.capability.bearHunt') }}</h3>
          <p class="ks-display mt-3 text-3xl font-semibold">
            {{ capabilityProfile.bearHunt.recordedResultCount }}
          </p>
          <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
            {{
              t('rosterHistory.capability.recordedRuns', {
                count: capabilityProfile.bearHunt.recordedResultCount,
              })
            }}
          </p>
          <template v-if="capabilityProfile.bearHunt.latestRecorded">
            <p class="mt-3 text-sm">
              {{ t('rosterHistory.capability.latestDamage') }}:
              <strong>{{
                capabilityProfile.bearHunt.latestRecorded.damage === null
                  ? '—'
                  : formatPower(String(capabilityProfile.bearHunt.latestRecorded.damage))
              }}</strong>
            </p>
            <Link
              :href="`/events/${capabilityProfile.bearHunt.latestRecorded.occurrenceId}/debrief`"
              class="mt-2 inline-flex text-xs font-semibold text-[var(--ks-gold)]"
            >
              {{ t('rosterHistory.capability.openDebrief') }}
            </Link>
          </template>
          <p v-else class="mt-3 text-sm text-[var(--ks-text-muted)]">
            {{ t('rosterHistory.capability.noRecordedRuns') }}
          </p>
        </article>

        <article class="ks-surface p-4">
          <h3 class="font-semibold">{{ t('rosterHistory.capability.rallies') }}</h3>
          <p class="ks-display mt-3 text-3xl font-semibold">
            {{ capabilityProfile.rallies.length }}
          </p>
          <ul v-if="capabilityProfile.rallies.length" class="mt-3 space-y-2 text-sm">
            <li v-for="rally in capabilityProfile.rallies.slice(0, 3)" :key="rally.id">
              <Link
                :href="`/events/${rally.occurrenceId}`"
                class="font-semibold text-[var(--ks-gold)]"
                >{{ rally.groupName }}</Link
              >
              <span class="ms-2 text-xs text-[var(--ks-text-muted)]"
                >{{ stateLabel(rally.role) }} · {{ stateLabel(rally.status) }}</span
              >
            </li>
          </ul>
          <p v-else class="mt-3 text-sm text-[var(--ks-text-muted)]">
            {{ t('rosterHistory.capability.noRallies') }}
          </p>
        </article>

        <article class="ks-surface p-4">
          <h3 class="font-semibold">{{ t('rosterHistory.capability.battlePlans') }}</h3>
          <p class="ks-display mt-3 text-3xl font-semibold">
            {{ capabilityProfile.battleAssignments.length }}
          </p>
          <ul v-if="capabilityProfile.battleAssignments.length" class="mt-3 space-y-2 text-sm">
            <li
              v-for="assignment in capabilityProfile.battleAssignments.slice(0, 3)"
              :key="assignment.id"
            >
              <Link
                :href="`/events/${assignment.occurrenceId}`"
                class="font-semibold text-[var(--ks-gold)]"
                >{{ assignment.objectiveName }}</Link
              >
              <span class="ms-2 text-xs text-[var(--ks-text-muted)]">{{
                stateLabel(assignment.status)
              }}</span>
            </li>
          </ul>
          <p v-else class="mt-3 text-sm text-[var(--ks-text-muted)]">
            {{ t('rosterHistory.capability.noBattlePlans') }}
          </p>
        </article>

        <article class="ks-surface p-4">
          <div class="flex items-center justify-between gap-2">
            <h3 class="font-semibold">{{ t('rosterHistory.capability.transfer') }}</h3>
            <Link
              href="/alliance/transfers/readiness"
              class="text-xs font-semibold text-[var(--ks-gold)]"
              >{{ t('rosterHistory.capability.openOwner') }}</Link
            >
          </div>
          <p
            v-if="capabilityProfile.transfer.access === 'unavailable'"
            class="mt-3 text-sm text-[var(--ks-text-muted)]"
          >
            {{ t('rosterHistory.capability.unavailable') }}
          </p>
          <template v-else-if="capabilityProfile.transfer.assessment">
            <p class="ks-display mt-3 text-2xl font-semibold">
              {{ stateLabel(capabilityProfile.transfer.assessment.outcome) }}
            </p>
            <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
              {{ stateLabel(capabilityProfile.transfer.assessment.readinessState) }} ·
              {{ formatCaptured(capabilityProfile.transfer.assessment.evaluatedAt) }}
            </p>
          </template>
          <p v-else class="mt-3 text-sm text-[var(--ks-text-muted)]">
            {{ t('rosterHistory.capability.noTransfer') }}
          </p>
        </article>

        <article class="ks-surface p-4">
          <h3 class="font-semibold">{{ t('rosterHistory.capability.evidence') }}</h3>
          <p
            v-if="capabilityProfile.evidence.access === 'unavailable'"
            class="mt-3 text-sm text-[var(--ks-text-muted)]"
          >
            {{ t('rosterHistory.capability.unavailable') }}
          </p>
          <p v-else class="ks-display mt-3 text-3xl font-semibold">
            {{ capabilityProfile.evidence.total }}
          </p>
          <p
            v-if="capabilityProfile.evidence.access === 'available'"
            class="mt-1 text-xs text-[var(--ks-text-muted)]"
          >
            {{
              t('rosterHistory.capability.evidenceSummary', {
                review: capabilityProfile.evidence.needsReview,
                pending: capabilityProfile.evidence.pending,
                committed: capabilityProfile.evidence.committed,
                failed: capabilityProfile.evidence.failed,
              })
            }}
          </p>
          <p
            v-if="
              capabilityProfile.evidence.access === 'available' &&
              capabilityProfile.evidence.latestAt
            "
            class="mt-3 text-xs text-[var(--ks-text-muted)]"
          >
            {{
              t('rosterHistory.capability.latestEvidence', {
                date: formatCaptured(capabilityProfile.evidence.latestAt),
              })
            }}
          </p>
        </article>

        <article class="ks-surface p-4">
          <div class="flex items-center justify-between gap-2">
            <h3 class="font-semibold">{{ t('allianceExpansion.memberHistoryEyebrow') }}</h3>
            <Link
              v-if="capabilityProfile.membershipGovernance.access === 'available'"
              :href="capabilityProfile.membershipGovernance.href"
              class="text-xs font-semibold text-[var(--ks-gold)]"
            >
              {{ t('allianceExpansion.openOwner') }}
            </Link>
          </div>
          <p
            v-if="capabilityProfile.membershipGovernance.access === 'unavailable'"
            class="mt-3 text-sm text-[var(--ks-text-muted)]"
          >
            {{ t('rosterHistory.capability.unavailable') }}
          </p>
          <template v-else>
            <p class="ks-display mt-3 text-3xl font-semibold">
              {{ capabilityProfile.membershipGovernance.history.length }}
            </p>
            <ul
              v-if="capabilityProfile.membershipGovernance.history.length"
              class="mt-3 space-y-2 text-sm"
            >
              <li
                v-for="event in capabilityProfile.membershipGovernance.history.slice(0, 3)"
                :key="event.id"
              >
                <p class="font-semibold">{{ event.type.replaceAll('_', ' ') }}</p>
                <p class="text-xs text-[var(--ks-text-muted)]">
                  {{ formatCaptured(event.occurredAt) }} · {{ event.source }}
                </p>
              </li>
            </ul>
            <p v-else class="mt-3 text-sm text-[var(--ks-text-muted)]">
              {{ t('allianceExpansion.noHistory') }}
            </p>
          </template>
        </article>
      </div>
    </section>

    <div class="mt-6 grid gap-5 xl:grid-cols-3">
      <section
        v-if="canManage"
        class="ks-surface p-5 sm:p-6 xl:sticky xl:top-24 xl:col-span-1 xl:self-start"
        aria-labelledby="record-snapshot"
      >
        <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
          {{ t('rosterHistory.recordSnapshot') }}
        </p>
        <h2 id="record-snapshot" class="ks-display mt-1 text-xl font-semibold">
          {{ entry.name }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('rosterHistory.recordHelp') }}
        </p>

        <form class="mt-5 space-y-4" @submit.prevent="recordSnapshot">
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="snapshot-name"
            >
              {{ t('rosterHistory.observedPlayerName') }}
            </label>
            <input
              id="snapshot-name"
              v-model="snapshotForm.observed_name"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              maxlength="160"
              required
            />
          </div>
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="snapshot-power"
            >
              {{ t('roster.power') }}
            </label>
            <input
              id="snapshot-power"
              v-model="snapshotForm.power"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              inputmode="numeric"
              maxlength="19"
              pattern="[0-9]+"
              required
            />
            <p v-if="snapshotForm.errors.power" class="mt-1 text-sm text-red-300" role="alert">
              {{ snapshotForm.errors.power }}
            </p>
          </div>
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="snapshot-level"
            >
              {{ t('roster.progression') }}
            </label>
            <input
              id="snapshot-level"
              v-model="snapshotForm.progression_level"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              maxlength="64"
            />
            <p class="mt-1 text-xs leading-5 text-[var(--ks-text-muted)]">
              {{ t('rosterHistory.progressionHelp') }}
            </p>
          </div>
          <div>
            <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="snapshot-tag">
              {{ t('roster.allianceTag') }}
            </label>
            <input
              id="snapshot-tag"
              v-model="snapshotForm.observed_alliance_tag"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              maxlength="32"
            />
          </div>
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="snapshot-captured"
            >
              {{ t('rosterHistory.capturedAt') }}
            </label>
            <input
              id="snapshot-captured"
              v-model="snapshotForm.captured_at"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              type="datetime-local"
              required
            />
            <p
              v-if="snapshotForm.errors.captured_at"
              class="mt-1 text-sm text-red-300"
              role="alert"
            >
              {{ snapshotForm.errors.captured_at }}
            </p>
          </div>
          <button
            class="min-h-11 w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-[var(--ks-ivory)] transition hover:bg-[var(--ks-blue-strong)] disabled:opacity-60"
            :disabled="snapshotForm.processing"
            type="submit"
          >
            {{ t('rosterHistory.recordAction') }}
          </button>
        </form>
      </section>

      <section
        class="ks-surface min-w-0 overflow-hidden"
        :class="canManage ? 'xl:col-span-2' : 'xl:col-span-3'"
      >
        <div class="border-b border-[var(--ks-border)] p-4 sm:p-5">
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
                {{ t('rosterHistory.historyHeading') }}
              </p>
              <h2 class="ks-display mt-1 text-xl font-semibold">
                {{ formatNumber(snapshots.length) }}
              </h2>
            </div>
            <div class="max-w-xl text-xs leading-5 text-[var(--ks-text-muted)]">
              <p>{{ t('rosterHistory.historyHelp') }}</p>
              <p v-if="hasMoreSnapshots" class="mt-1 text-amber-200">
                {{ t('rosterHistory.earlierHistoryNotShown') }}
              </p>
            </div>
          </div>
        </div>

        <div v-if="snapshots.length" class="lg:hidden">
          <article
            v-for="snapshot in snapshots"
            :key="snapshot.id"
            class="border-b border-[var(--ks-border)] p-4 last:border-b-0"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate font-semibold">{{ snapshot.observedName }}</p>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  {{ formatCaptured(snapshot.capturedAt) }}
                </p>
              </div>
              <p class="shrink-0 text-end">
                <span
                  class="block text-[0.65rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
                >
                  {{ t('roster.power') }}
                </span>
                <strong class="mt-1 block text-base">{{ formatPower(snapshot.power) }}</strong>
                <span
                  v-if="snapshot.change"
                  class="mt-1 block text-xs font-semibold"
                  :class="powerChangeTone(snapshot.change.power)"
                >
                  {{ formatSignedPower(snapshot.change.power) }}
                </span>
              </p>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
              <div>
                <dt class="text-[var(--ks-text-muted)]">{{ t('roster.progression') }}</dt>
                <dd class="mt-1 text-[var(--ks-text-secondary)]">
                  {{ snapshot.progressionLevel ?? '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-[var(--ks-text-muted)]">{{ t('roster.allianceTag') }}</dt>
                <dd class="mt-1 text-[var(--ks-text-secondary)]">
                  {{ snapshot.observedAllianceTag ?? '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-[var(--ks-text-muted)]">{{ t('rosterHistory.source') }}</dt>
                <dd class="mt-1 text-[var(--ks-text-secondary)]">{{ snapshot.source }}</dd>
              </div>
              <div v-if="canManage">
                <dt class="text-[var(--ks-text-muted)]">{{ t('rosterHistory.recordedBy') }}</dt>
                <dd class="mt-1 text-[var(--ks-text-secondary)]">
                  {{ snapshot.actorName ?? '—' }}
                </dd>
              </div>
              <div v-if="snapshot.change" class="col-span-2">
                <dt class="text-[var(--ks-text-muted)]">
                  {{ t('rosterHistory.observedChanges') }}
                </dt>
                <dd class="mt-2 flex flex-wrap gap-2">
                  <span v-if="snapshot.change.observedName" class="ks-chip">
                    {{ t('rosterHistory.nameChanged') }}:
                    {{ observedValue(snapshot.change.observedName.from) }} →
                    {{ observedValue(snapshot.change.observedName.to) }}
                  </span>
                  <span v-if="snapshot.change.progressionLevel" class="ks-chip">
                    {{ t('rosterHistory.progressionChanged') }}:
                    {{ observedValue(snapshot.change.progressionLevel.from) }} →
                    {{ observedValue(snapshot.change.progressionLevel.to) }}
                  </span>
                  <span v-if="snapshot.change.observedAllianceTag" class="ks-chip">
                    {{ t('rosterHistory.allianceChanged') }}:
                    {{ observedValue(snapshot.change.observedAllianceTag.from) }} →
                    {{ observedValue(snapshot.change.observedAllianceTag.to) }}
                  </span>
                </dd>
              </div>
            </dl>
          </article>
        </div>

        <div v-if="snapshots.length" class="hidden overflow-x-auto lg:block">
          <table class="w-full min-w-[58rem] text-sm">
            <thead
              class="bg-black/25 text-[0.68rem] font-bold tracking-[0.08em] text-[var(--ks-text-muted)] uppercase"
            >
              <tr>
                <th class="px-4 py-3 text-start">{{ t('rosterHistory.capturedAt') }}</th>
                <th class="px-4 py-3 text-start">{{ t('roster.player') }}</th>
                <th class="px-4 py-3 text-start">{{ t('roster.power') }}</th>
                <th class="px-4 py-3 text-start">{{ t('rosterHistory.observedChanges') }}</th>
                <th class="px-4 py-3 text-start">{{ t('roster.progression') }}</th>
                <th class="px-4 py-3 text-start">{{ t('roster.allianceTag') }}</th>
                <th class="px-4 py-3 text-start">{{ t('rosterHistory.source') }}</th>
                <th v-if="canManage" class="px-4 py-3 text-start">
                  {{ t('rosterHistory.recordedBy') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ks-border)]">
              <tr
                v-for="snapshot in snapshots"
                :key="snapshot.id"
                class="transition hover:bg-[var(--ks-parchment)]/[0.025]"
              >
                <td class="px-4 py-3.5 text-xs text-[var(--ks-text-muted)]">
                  {{ formatCaptured(snapshot.capturedAt) }}
                </td>
                <td class="px-4 py-3.5 font-semibold">{{ snapshot.observedName }}</td>
                <td class="px-4 py-3.5 font-semibold">
                  {{ formatPower(snapshot.power) }}
                  <span
                    v-if="snapshot.change"
                    class="mt-1 block text-xs"
                    :class="powerChangeTone(snapshot.change.power)"
                  >
                    {{ formatSignedPower(snapshot.change.power) }}
                  </span>
                </td>
                <td class="max-w-72 px-4 py-3.5">
                  <div v-if="snapshot.change" class="flex flex-wrap gap-1.5">
                    <span v-if="snapshot.change.observedName" class="ks-chip">
                      {{ t('rosterHistory.nameChanged') }}
                    </span>
                    <span v-if="snapshot.change.progressionLevel" class="ks-chip">
                      {{ t('rosterHistory.progressionChanged') }}
                    </span>
                    <span v-if="snapshot.change.observedAllianceTag" class="ks-chip">
                      {{ t('rosterHistory.allianceChanged') }}
                    </span>
                    <span
                      v-if="
                        snapshot.change.power === '0' &&
                        !snapshot.change.observedName &&
                        !snapshot.change.progressionLevel &&
                        !snapshot.change.observedAllianceTag
                      "
                      class="text-xs text-[var(--ks-text-muted)]"
                    >
                      {{ t('rosterHistory.noObservedChange') }}
                    </span>
                  </div>
                  <span v-else>—</span>
                </td>
                <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                  {{ snapshot.progressionLevel ?? '—' }}
                </td>
                <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                  {{ snapshot.observedAllianceTag ?? '—' }}
                </td>
                <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">{{ snapshot.source }}</td>
                <td v-if="canManage" class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                  {{ snapshot.actorName ?? '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-if="!snapshots.length" class="p-8 text-center text-sm text-[var(--ks-text-muted)]">
          {{ t('rosterHistory.noSnapshots') }}
        </p>
      </section>
    </div>
  </AppLayout>
</template>
