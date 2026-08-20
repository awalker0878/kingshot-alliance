<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Membership = {
  playerId: string;
  name: string;
  rank: string;
  claimed: boolean;
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
  membership: { playerId: string; name: string; rank: string; claimed: boolean } | null;
  managerNotes: string | null;
  latestSnapshot: LatestSnapshot | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  entries: Entry[];
  states: string[];
  gaps: {
    membershipsWithoutRoster: Membership[];
    rosterWithoutMembership: number;
  };
}>();

const { locale, t, formatDate, formatNumber } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();

const createForm = useForm({
  name: '',
  game_player_id: '',
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
  requestConfirmation({
    id: 'roster-mark-left-confirmation',
    title: t('rosterManage.markLeft'),
    description: t('rosterManage.markLeftConfirm', { name: entry.name }),
    confirmLabel: t('rosterManage.markLeft'),
    cancelLabel: t('common.cancel'),
    perform: (finish) =>
      router.post(
        `/alliance/roster/${entry.id}/leave`,
        {},
        { preserveScroll: true, onFinish: finish },
      ),
  });
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

function stateTone(value: string): 'success' | 'warning' | 'info' {
  if (value === 'active') return 'success';
  if (value === 'tracked') return 'info';
  return 'warning';
}
</script>

<template>
  <Head :title="`${t('roster.manage')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('roster.eyebrow', { kingdom: alliance.kingdom ?? t('roster.kingdomNotSet') })"
      :title="t('roster.manage')"
      :subtitle="t('rosterManage.manageSubtitle')"
      image="/images/kingshot/v4/roster-hall.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/roster" class="ks-command-link">
          {{ t('roster.title') }}
        </Link>
        <Link href="/alliance/roster/import" class="ks-command-link" data-variant="secondary">
          {{ t('rosterManage.csvMigration') }}
        </Link>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-3">
      <StatSeal
        :label="t('roster.trackedPlayers')"
        :value="formatNumber(entries.length)"
        icon="♟"
      />
      <StatSeal
        :label="t('rosterManage.profilesWithoutMembership')"
        :value="formatNumber(gaps.rosterWithoutMembership)"
        icon="?"
        tone="stone"
      />
      <StatSeal
        :label="t('rosterManage.membershipsWithoutProfile')"
        :value="formatNumber(gaps.membershipsWithoutRoster.length)"
        icon="◇"
        tone="teal"
      />
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(21rem,.58fr)_minmax(0,1.42fr)]">
      <aside class="space-y-5">
        <section
          class="ks-surface p-5 2xl:sticky 2xl:top-[6.5rem]"
          aria-labelledby="add-roster-player"
        >
          <p class="ks-kicker">{{ t('rosterManage.addPlayer') }}</p>
          <h2 id="add-roster-player" class="ks-display mt-1 text-xl font-semibold">
            {{ t('rosterManage.playerName') }}
          </h2>

          <form class="mt-5 space-y-4" @submit.prevent="createEntry">
            <div>
              <label class="text-xs font-semibold" for="roster-name">{{
                t('rosterManage.playerName')
              }}</label>
              <input
                id="roster-name"
                v-model="createForm.name"
                class="ks-input mt-1.5"
                maxlength="160"
                required
              />
            </div>
            <div>
              <label class="text-xs font-semibold" for="roster-game-id">{{
                t('rosterManage.stableGameId')
              }}</label>
              <input
                id="roster-game-id"
                v-model="createForm.game_player_id"
                class="ks-input mt-1.5"
                maxlength="100"
              />
            </div>
            <div>
              <label class="text-xs font-semibold" for="roster-role">{{ t('roster.role') }}</label>
              <input
                id="roster-role"
                v-model="createForm.game_role"
                class="ks-input mt-1.5"
                maxlength="64"
              />
            </div>
            <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-1">
              <div>
                <label class="text-xs font-semibold" for="roster-state">{{
                  t('roster.state')
                }}</label>
                <select id="roster-state" v-model="createForm.state" class="ks-input mt-1.5">
                  <option v-for="state in states" :key="state" :value="state">
                    {{ stateLabel(state) }}
                  </option>
                </select>
              </div>
              <div>
                <label class="text-xs font-semibold" for="roster-joined">{{
                  t('rosterManage.joinedDate')
                }}</label>
                <input
                  id="roster-joined"
                  v-model="createForm.joined_at"
                  class="ks-input mt-1.5"
                  type="date"
                />
              </div>
            </div>
            <div>
              <label class="text-xs font-semibold" for="roster-notes">{{
                t('rosterManage.managerNotes')
              }}</label>
              <textarea
                id="roster-notes"
                v-model="createForm.manager_notes"
                class="ks-input mt-1.5 min-h-24"
                maxlength="5000"
              />
            </div>
            <AppButton class="w-full" type="submit" :disabled="createForm.processing">
              {{ t('rosterManage.addToRoster') }}
            </AppButton>
            <p
              v-if="Object.keys(createForm.errors).length"
              class="text-sm text-red-300"
              role="alert"
            >
              {{ t('rosterManage.correctValues') }}
            </p>
          </form>
        </section>

        <section
          v-if="gaps.membershipsWithoutRoster.length || gaps.rosterWithoutMembership"
          class="ks-surface p-5"
        >
          <p class="ks-kicker">{{ t('roster.linkage') }}</p>
          <h2 class="ks-display mt-1 text-xl font-semibold">
            {{ t('rosterManage.membershipsWithoutProfile') }}
          </h2>
          <div v-if="gaps.membershipsWithoutRoster.length" class="mt-4 space-y-2">
            <article
              v-for="membership in gaps.membershipsWithoutRoster"
              :key="membership.playerId"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3"
            >
              <strong class="block text-sm">{{ membership.name }}</strong>
              <span class="mt-1 block text-xs text-[var(--ks-muted)]">
                {{ membership.rank.toUpperCase() }} ·
                {{ membership.claimed ? t('roster.linked') : t('roster.unlinked') }}
              </span>
            </article>
          </div>
          <p v-if="gaps.rosterWithoutMembership" class="mt-4 text-sm text-[var(--ks-muted)]">
            {{ t('rosterManage.profilesWithoutMembership') }} ·
            {{ formatNumber(gaps.rosterWithoutMembership) }}
          </p>
        </section>
      </aside>

      <section class="min-w-0" aria-labelledby="roster-existing">
        <div class="flex flex-wrap items-end justify-between gap-3 px-1">
          <div>
            <p class="ks-kicker">{{ t('roster.trackedPlayers') }}</p>
            <h2 id="roster-existing" class="ks-display mt-1 text-2xl font-semibold">
              {{ formatNumber(entries.length) }}
            </h2>
          </div>
        </div>

        <div v-if="entries.length" class="mt-4 space-y-4">
          <article v-for="entry in entries" :key="entry.id" class="ks-surface overflow-hidden">
            <div
              class="flex flex-wrap items-start justify-between gap-4 border-b border-[var(--ks-border)] p-4 sm:p-5"
            >
              <div class="flex min-w-0 items-center gap-3">
                <div
                  class="grid h-12 w-12 shrink-0 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-black/20 text-lg font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
                  aria-hidden="true"
                >
                  {{ entry.name.slice(0, 1).toUpperCase() }}
                </div>
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <h3 class="ks-display truncate text-xl font-semibold">{{ entry.name }}</h3>
                    <span class="ks-status" :data-tone="stateTone(entry.state)">{{
                      stateLabel(entry.state)
                    }}</span>
                    <span v-if="entry.gameRole" class="ks-chip">{{ entry.gameRole }}</span>
                  </div>
                  <p class="mt-1 text-xs text-[var(--ks-muted)]">
                    {{ t('roster.gameId') }}: {{ entry.gamePlayerId ?? t('rosterManage.unknown') }}
                  </p>
                </div>
              </div>
              <div class="text-end">
                <p class="ks-kicker">{{ t('rosterManage.latestPower') }}</p>
                <p class="mt-1 text-lg font-semibold">
                  {{ entry.latestSnapshot ? formatPower(entry.latestSnapshot.power) : '—' }}
                </p>
                <p class="mt-1 text-xs text-[var(--ks-muted)]">
                  {{
                    entry.latestSnapshot
                      ? formatCaptured(entry.latestSnapshot.capturedAt)
                      : t('rosterManage.noneRecorded')
                  }}
                </p>
              </div>
            </div>

            <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5 xl:grid-cols-4">
              <div>
                <label class="text-xs font-semibold" :for="`name-${entry.id}`">{{
                  t('rosterManage.observedName')
                }}</label>
                <input
                  :id="`name-${entry.id}`"
                  v-model="drafts[entry.id]!.name"
                  class="ks-input mt-1.5"
                  maxlength="160"
                />
              </div>
              <div>
                <label class="text-xs font-semibold" :for="`role-${entry.id}`">{{
                  t('roster.role')
                }}</label>
                <input
                  :id="`role-${entry.id}`"
                  v-model="drafts[entry.id]!.game_role"
                  class="ks-input mt-1.5"
                  maxlength="64"
                />
              </div>
              <div>
                <label class="text-xs font-semibold" :for="`state-${entry.id}`">{{
                  t('roster.state')
                }}</label>
                <select
                  :id="`state-${entry.id}`"
                  v-model="drafts[entry.id]!.state"
                  class="ks-input mt-1.5"
                >
                  <option v-for="state in states" :key="state" :value="state">
                    {{ stateLabel(state) }}
                  </option>
                </select>
              </div>
              <div>
                <label class="text-xs font-semibold" :for="`joined-${entry.id}`">{{
                  t('rosterManage.joinedDate')
                }}</label>
                <input
                  :id="`joined-${entry.id}`"
                  v-model="drafts[entry.id]!.joined_at"
                  class="ks-input mt-1.5"
                  type="date"
                />
              </div>
              <div class="sm:col-span-2 xl:col-span-4">
                <label class="text-xs font-semibold" :for="`notes-${entry.id}`">{{
                  t('rosterManage.managerNotes')
                }}</label>
                <textarea
                  :id="`notes-${entry.id}`"
                  v-model="drafts[entry.id]!.manager_notes"
                  class="ks-input mt-1.5 min-h-20"
                  maxlength="5000"
                />
              </div>
              <div class="flex flex-wrap gap-2 sm:col-span-2 xl:col-span-4">
                <AppButton variant="secondary" @click="saveEntry(entry)">
                  {{ t('rosterManage.savePlayer') }}
                </AppButton>
                <Link
                  :href="`/alliance/roster/${entry.id}/history`"
                  class="ks-command-link"
                  data-variant="secondary"
                >
                  {{ t('rosterManage.historyRecordSnapshot') }}
                </Link>
                <button
                  v-if="entry.state !== 'left'"
                  type="button"
                  class="rounded-[var(--ks-radius-sm)] border border-red-400/20 px-4 py-2 text-sm font-semibold text-red-300 transition hover:border-red-400/40"
                  @click="markLeft(entry)"
                >
                  {{ t('rosterManage.markLeft') }}
                </button>
              </div>
            </div>
          </article>
        </div>
        <div v-else class="ks-fantasy-empty mt-4">{{ t('rosterManage.noEntries') }}</div>
      </section>
    </div>
    <ConfirmActionDialog
      v-bind="dialog"
      @confirm="confirmAction"
      @cancel="cancelConfirmation"
    />
  </AppLayout>
</template>
