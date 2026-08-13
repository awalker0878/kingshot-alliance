<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

type Membership = {
  id: string;
  name: string;
  email: string;
  linkedRosterEntryId: string | null;
};

type LatestSnapshot = {
  observedName: string;
  power: string;
  progressionLevel: string | null;
  observedAllianceTag: string | null;
  capturedAt: string;
  source: string;
};

type Entry = {
  id: string;
  gamePlayerId: string | null;
  name: string;
  gameRole: string | null;
  state: string;
  joinedAt: string | null;
  leftAt: string | null;
  lastObservedAt: string | null;
  membership: { id: string; name: string; email: string } | null;
  managerNotes: string | null;
  latestSnapshot: LatestSnapshot | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  entries: Entry[];
  memberships: Membership[];
  states: string[];
  gaps: {
    membershipsWithoutRoster: Membership[];
    rosterWithoutMembership: number;
  };
}>();

const { locale, t, formatDate, formatNumber } = useLocale();

const createForm = useForm({
  name: '',
  game_player_id: '',
  membership_id: '',
  game_role: '',
  state: 'active',
  joined_at: '',
  manager_notes: '',
});

const drafts = reactive(
  Object.fromEntries(
    props.entries.map((entry) => [
      entry.id,
      {
        name: entry.name,
        membership_id: entry.membership?.id ?? '',
        game_role: entry.gameRole ?? '',
        state: entry.state === 'left' ? 'tracked' : entry.state,
        joined_at: entry.joinedAt ?? '',
        manager_notes: entry.managerNotes ?? '',
      },
    ]),
  ) as Record<
    string,
    {
      name: string;
      membership_id: string;
      game_role: string;
      state: string;
      joined_at: string;
      manager_notes: string;
    }
  >,
);

function createEntry(): void {
  createForm.post('/alliance/roster', {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  });
}

function saveEntry(entry: Entry): void {
  router.patch(`/alliance/roster/${entry.id}`, drafts[entry.id], { preserveScroll: true });
}

function markLeft(entry: Entry): void {
  if (!window.confirm(t('rosterManage.markLeftConfirm', { name: entry.name }))) {
    return;
  }

  router.post(`/alliance/roster/${entry.id}/leave`, {}, { preserveScroll: true });
}

function membershipUnavailable(membership: Membership, entryId?: string): boolean {
  return (
    membership.linkedRosterEntryId !== null && membership.linkedRosterEntryId !== (entryId ?? null)
  );
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

function stateLabel(value: string): string {
  const key = `roster.${value}`;
  const translated = t(key);
  return translated === key ? value.replaceAll('_', ' ') : translated;
}

function stateTone(value: string): string {
  if (value === 'active') return 'border-green-400/20 bg-green-500/10 text-green-200';
  if (value === 'tracked') return 'border-blue-400/20 bg-blue-500/10 text-blue-200';
  return 'border-slate-400/20 bg-slate-500/10 text-slate-300';
}
</script>

<template>
  <Head :title="`${t('roster.manage')} · ${alliance.name}`" />

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div class="max-w-3xl">
        <Link
          class="inline-flex min-h-10 items-center text-sm font-semibold text-[var(--ks-blue-strong)] hover:text-white"
          href="/alliance/roster"
        >
          ← {{ t('roster.title') }}
        </Link>
        <p class="mt-4 text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('roster.eyebrow', { kingdom: alliance.kingdom ?? t('roster.kingdomNotSet') }) }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">{{ t('roster.manage') }}</h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('rosterManage.manageSubtitle') }}
        </p>
      </div>
      <Link
        class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-gold)]/45 bg-[var(--ks-gold-soft)] px-4 py-2 text-sm font-semibold text-[var(--ks-gold-strong)] transition hover:border-[var(--ks-gold)] hover:text-white"
        href="/alliance/roster/import"
      >
        {{ t('rosterManage.csvMigration') }}
      </Link>
    </header>

    <section class="ks-surface-gold mt-6 grid gap-px overflow-hidden bg-[var(--ks-border)] md:grid-cols-2" aria-label="Roster linkage gaps">
      <article class="bg-[var(--ks-surface-1)] p-5 sm:p-6">
        <p class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
          {{ t('rosterManage.profilesWithoutMembership') }}
        </p>
        <p class="ks-display mt-3 text-4xl font-semibold">
          {{ formatNumber(gaps.rosterWithoutMembership) }}
        </p>
      </article>
      <article class="bg-[var(--ks-surface-1)] p-5 sm:p-6">
        <p class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
          {{ t('rosterManage.membershipsWithoutProfile') }}
        </p>
        <p class="ks-display mt-3 text-4xl font-semibold">
          {{ formatNumber(gaps.membershipsWithoutRoster.length) }}
        </p>
        <ul v-if="gaps.membershipsWithoutRoster.length" class="mt-4 space-y-2 text-sm text-[var(--ks-text-secondary)]">
          <li
            v-for="membership in gaps.membershipsWithoutRoster"
            :key="membership.id"
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 px-3 py-2"
          >
            <span class="font-semibold text-[var(--ks-text)]">{{ membership.name }}</span>
            <span class="block truncate text-xs text-[var(--ks-text-muted)]">{{ membership.email }}</span>
          </li>
        </ul>
      </article>
    </section>

    <div class="mt-6 grid gap-5 xl:grid-cols-3">
      <section class="ks-surface p-5 sm:p-6 xl:col-span-1 xl:self-start xl:sticky xl:top-24" aria-labelledby="add-roster-player">
        <div class="border-b border-[var(--ks-border)] pb-4">
          <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
            {{ t('rosterManage.addPlayer') }}
          </p>
          <h2 id="add-roster-player" class="ks-display mt-1 text-xl font-semibold">
            {{ t('rosterManage.playerName') }}
          </h2>
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="createEntry">
          <div>
            <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="roster-name">
              {{ t('rosterManage.playerName') }}
            </label>
            <input
              id="roster-name"
              v-model="createForm.name"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm outline-none transition focus:border-[var(--ks-blue-strong)]"
              maxlength="160"
              required
            />
          </div>
          <div>
            <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="roster-game-id">
              {{ t('rosterManage.stableGameId') }}
            </label>
            <input
              id="roster-game-id"
              v-model="createForm.game_player_id"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              maxlength="100"
            />
          </div>
          <div>
            <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="roster-member">
              {{ t('rosterManage.applicationMember') }}
            </label>
            <select
              id="roster-member"
              v-model="createForm.membership_id"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
            >
              <option value="">{{ t('roster.unlinked') }}</option>
              <option
                v-for="membership in memberships"
                :key="membership.id"
                :disabled="membershipUnavailable(membership)"
                :value="membership.id"
              >
                {{ membership.name }} · {{ membership.email }}
                {{ membership.linkedRosterEntryId ? ` · ${t('rosterManage.alreadyLinked')}` : '' }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="roster-role">
              {{ t('roster.role') }}
            </label>
            <input
              id="roster-role"
              v-model="createForm.game_role"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              maxlength="64"
            />
          </div>
          <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <div>
              <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="roster-state">
                {{ t('roster.state') }}
              </label>
              <select
                id="roster-state"
                v-model="createForm.state"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              >
                <option v-for="state in states" :key="state" :value="state">{{ stateLabel(state) }}</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="roster-joined">
                {{ t('rosterManage.joinedDate') }}
              </label>
              <input
                id="roster-joined"
                v-model="createForm.joined_at"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                type="date"
              />
            </div>
          </div>
          <div>
            <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="roster-notes">
              {{ t('rosterManage.managerNotes') }}
            </label>
            <textarea
              id="roster-notes"
              v-model="createForm.manager_notes"
              class="mt-1.5 min-h-24 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              maxlength="5000"
            />
          </div>
          <button
            class="min-h-11 w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--ks-blue-strong)] disabled:opacity-60"
            :disabled="createForm.processing"
            type="submit"
          >
            {{ t('rosterManage.addToRoster') }}
          </button>
          <p v-if="Object.keys(createForm.errors).length" class="text-sm text-red-300" role="alert">
            {{ t('rosterManage.correctValues') }}
          </p>
        </form>
      </section>

      <section class="min-w-0 space-y-4 xl:col-span-2" aria-labelledby="roster-existing">
        <div class="flex flex-wrap items-end justify-between gap-3 px-1">
          <div>
            <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
              {{ t('rosterManage.trackedPlayers') }}
            </p>
            <h2 id="roster-existing" class="ks-display mt-1 text-2xl font-semibold">
              {{ formatNumber(entries.length) }}
            </h2>
          </div>
        </div>

        <article v-for="entry in entries" :key="entry.id" class="ks-surface overflow-hidden">
          <div class="border-b border-[var(--ks-border)] bg-black/15 p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="ks-display text-xl font-semibold">{{ entry.name }}</h3>
                  <span
                    :class="stateTone(entry.state)"
                    class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                  >
                    {{ stateLabel(entry.state) }}
                  </span>
                  <span
                    v-if="entry.gameRole"
                    class="rounded-full border border-purple-400/20 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-200"
                  >
                    {{ entry.gameRole }}
                  </span>
                </div>
                <p class="mt-2 text-xs text-[var(--ks-text-muted)]">
                  {{ t('roster.gameId') }}: {{ entry.gamePlayerId ?? t('rosterManage.unknown') }}
                </p>
              </div>
              <div class="text-end">
                <p class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase">
                  {{ t('rosterManage.latestPower') }}
                </p>
                <p class="mt-1 text-lg font-semibold">
                  {{ entry.latestSnapshot ? formatPower(entry.latestSnapshot.power) : '—' }}
                </p>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  {{
                    entry.latestSnapshot
                      ? formatCaptured(entry.latestSnapshot.capturedAt)
                      : t('rosterManage.noneRecorded')
                  }}
                </p>
              </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
              <Link
                class="inline-flex min-h-9 items-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-1.5 text-xs font-semibold text-[var(--ks-blue-strong)] transition hover:border-[var(--ks-blue-strong)] hover:text-white"
                :href="`/alliance/roster/${entry.id}/history`"
              >
                {{ t('rosterManage.historyRecordSnapshot') }}
              </Link>
              <button
                v-if="entry.state !== 'left'"
                class="inline-flex min-h-9 items-center rounded-[var(--ks-radius-sm)] border border-red-400/20 bg-red-500/5 px-3 py-1.5 text-xs font-semibold text-red-300 transition hover:border-red-400/40 hover:text-red-200"
                type="button"
                @click="markLeft(entry)"
              >
                {{ t('rosterManage.markLeft') }}
              </button>
            </div>
          </div>

          <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
            <div>
              <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" :for="`name-${entry.id}`">
                {{ t('rosterManage.observedName') }}
              </label>
              <input
                :id="`name-${entry.id}`"
                v-model="drafts[entry.id].name"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                maxlength="160"
              />
            </div>
            <div>
              <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" :for="`member-${entry.id}`">
                {{ t('roster.linkedMember') }}
              </label>
              <select
                :id="`member-${entry.id}`"
                v-model="drafts[entry.id].membership_id"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              >
                <option value="">{{ t('roster.unlinked') }}</option>
                <option
                  v-for="membership in memberships"
                  :key="membership.id"
                  :disabled="membershipUnavailable(membership, entry.id)"
                  :value="membership.id"
                >
                  {{ membership.name }} · {{ membership.email }}
                  {{ membershipUnavailable(membership, entry.id) ? ` · ${t('rosterManage.alreadyLinked')}` : '' }}
                </option>
              </select>
            </div>
            <div>
              <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" :for="`role-${entry.id}`">
                {{ t('roster.role') }}
              </label>
              <input
                :id="`role-${entry.id}`"
                v-model="drafts[entry.id].game_role"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                maxlength="64"
              />
            </div>
            <div>
              <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" :for="`state-${entry.id}`">
                {{ t('roster.state') }}
              </label>
              <select
                :id="`state-${entry.id}`"
                v-model="drafts[entry.id].state"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              >
                <option v-for="state in states" :key="state" :value="state">{{ stateLabel(state) }}</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" :for="`joined-${entry.id}`">
                {{ t('rosterManage.joinedDate') }}
              </label>
              <input
                :id="`joined-${entry.id}`"
                v-model="drafts[entry.id].joined_at"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                type="date"
              />
            </div>
            <div class="sm:col-span-2">
              <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" :for="`notes-${entry.id}`">
                {{ t('rosterManage.managerNotes') }}
              </label>
              <textarea
                :id="`notes-${entry.id}`"
                v-model="drafts[entry.id].manager_notes"
                class="mt-1.5 min-h-20 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                maxlength="5000"
              />
            </div>
            <div class="sm:col-span-2">
              <button
                class="min-h-10 rounded-[var(--ks-radius-sm)] border border-[var(--ks-gold)]/45 bg-[var(--ks-gold-soft)] px-4 py-2 text-sm font-semibold text-[var(--ks-gold-strong)] transition hover:border-[var(--ks-gold)] hover:text-white"
                type="button"
                @click="saveEntry(entry)"
              >
                {{ t('rosterManage.savePlayer') }}
              </button>
            </div>
          </div>
        </article>

        <p v-if="!entries.length" class="ks-surface p-8 text-center text-sm text-[var(--ks-text-muted)]">
          {{ t('rosterManage.noEntries') }}
        </p>
      </section>
    </div>
  </AppLayout>
</template>
