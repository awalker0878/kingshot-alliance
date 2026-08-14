<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

type Snapshot = {
  id: string;
  observedName: string;
  power: string;
  progressionLevel: string | null;
  observedAllianceTag: string | null;
  capturedAt: string;
  source: string;
  actorName?: string;
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
  staleAfterDays: number;
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
  progression_level: '',
  observed_alliance_tag: '',
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
        snapshotForm.progression_level = '';
        snapshotForm.observed_alliance_tag = '';
        snapshotForm.captured_at = localDateTimeValue();
      },
    });
}
</script>

<template>
  <Head :title="`${t('rosterHistory.title')} · ${entry.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div class="max-w-3xl min-w-0">
        <Link
          class="inline-flex min-h-10 items-center text-sm font-semibold text-[var(--ks-blue-strong)] hover:text-white"
          href="/alliance/roster"
        >
          ← {{ t('roster.title') }}
        </Link>
        <p class="mt-4 text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('roster.eyebrow', { kingdom: alliance.kingdom ?? t('roster.kingdomNotSet') }) }}
        </p>
        <h1 class="ks-display mt-2 truncate text-3xl font-bold sm:text-4xl">{{ entry.name }}</h1>
        <div class="mt-3 flex flex-wrap gap-2">
          <span
            class="rounded-full border border-[var(--ks-border)] bg-black/15 px-2.5 py-1 text-xs text-[var(--ks-text-secondary)]"
          >
            {{ t('roster.gameId') }}: {{ entry.gamePlayerId ?? t('rosterManage.unknown') }}
          </span>
          <span
            v-if="entry.gameRole"
            class="rounded-full border border-purple-400/20 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-200"
          >
            {{ entry.gameRole }}
          </span>
          <span
            class="rounded-full border border-blue-400/20 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-200"
          >
            {{ stateLabel(entry.state) }}
          </span>
          <span
            class="rounded-full border border-[var(--ks-border)] bg-black/15 px-2.5 py-1 text-xs text-[var(--ks-text-secondary)]"
          >
            {{ entry.membership?.name ?? t('roster.unlinked') }}
          </span>
        </div>
      </div>
      <Link
        v-if="canManage"
        class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-gold)] hover:text-white"
        href="/alliance/roster/manage"
      >
        {{ t('roster.manage') }}
      </Link>
    </header>

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
            class="min-h-11 w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--ks-blue-strong)] disabled:opacity-60"
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
            <p class="max-w-xl text-xs leading-5 text-[var(--ks-text-muted)]">
              {{ t('rosterHistory.historyHelp') }}
            </p>
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
                class="transition hover:bg-white/[0.025]"
              >
                <td class="px-4 py-3.5 text-xs text-[var(--ks-text-muted)]">
                  {{ formatCaptured(snapshot.capturedAt) }}
                </td>
                <td class="px-4 py-3.5 font-semibold">{{ snapshot.observedName }}</td>
                <td class="px-4 py-3.5 font-semibold">{{ formatPower(snapshot.power) }}</td>
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
