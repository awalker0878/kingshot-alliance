<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

type Membership = {
  id: string;
  name: string;
  email: string;
  linkedRosterEntryId: string | null;
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
};

const props = defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  entries: Entry[];
  memberships: Membership[];
  states: string[];
  gaps: {
    membershipsWithoutRoster: Membership[];
    rosterWithoutMembership: number;
  };
}>();

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
  if (!window.confirm(`Mark ${entry.name} as left? Historical identity and linkage are retained.`)) {
    return;
  }

  router.post(`/alliance/roster/${entry.id}/leave`, {}, { preserveScroll: true });
}

function membershipUnavailable(membership: Membership, entryId?: string): boolean {
  return (
    membership.linkedRosterEntryId !== null && membership.linkedRosterEntryId !== (entryId ?? null)
  );
}
</script>

<template>
  <Head :title="`Manage roster · ${alliance.name}`" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 text-slate-100 lg:px-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link
          class="text-sm font-semibold text-cyan-300 hover:text-cyan-200"
          href="/alliance/roster"
        >
          ← Roster
        </Link>
        <p class="mt-5 text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Kingdom {{ alliance.kingdom ?? 'not set' }}
        </p>
        <h1 class="mt-2 text-3xl font-bold">Manage roster</h1>
        <p class="mt-2 text-sm text-slate-400">
          Manual roster identity and membership linkage. Player names are never used as identity
          keys, and manager notes remain private to roster managers.
        </p>
      </div>
    </div>

    <section class="mt-8 grid gap-4 md:grid-cols-2" aria-label="Roster linkage gaps">
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Roster profiles without application membership</p>
        <p class="mt-2 text-3xl font-bold">{{ gaps.rosterWithoutMembership }}</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <p class="text-sm text-slate-400">Application memberships without roster profile</p>
        <p class="mt-2 text-3xl font-bold">{{ gaps.membershipsWithoutRoster.length }}</p>
        <ul v-if="gaps.membershipsWithoutRoster.length" class="mt-3 space-y-1 text-sm text-slate-400">
          <li v-for="membership in gaps.membershipsWithoutRoster" :key="membership.id">
            {{ membership.name }} · {{ membership.email }}
          </li>
        </ul>
      </div>
    </section>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
      <h2 class="text-xl font-semibold">Add player</h2>
      <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="createEntry">
        <div>
          <label class="text-sm font-medium" for="roster-name">Player name</label>
          <input
            id="roster-name"
            v-model="createForm.name"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="160"
            required
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="roster-game-id">Stable game-player ID</label>
          <input
            id="roster-game-id"
            v-model="createForm.game_player_id"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="100"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="roster-member">Application member</label>
          <select
            id="roster-member"
            v-model="createForm.membership_id"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="">Unlinked</option>
            <option
              v-for="membership in memberships"
              :key="membership.id"
              :disabled="membershipUnavailable(membership)"
              :value="membership.id"
            >
              {{ membership.name }} · {{ membership.email }}
              {{ membership.linkedRosterEntryId ? ' · already linked' : '' }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="roster-role">Game role / rank</label>
          <input
            id="roster-role"
            v-model="createForm.game_role"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="64"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="roster-state">Roster state</label>
          <select
            id="roster-state"
            v-model="createForm.state"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="roster-joined">Joined date</label>
          <input
            id="roster-joined"
            v-model="createForm.joined_at"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            type="date"
          />
        </div>
        <div class="md:col-span-2">
          <label class="text-sm font-medium" for="roster-notes">Private manager notes</label>
          <textarea
            id="roster-notes"
            v-model="createForm.manager_notes"
            class="mt-1 min-h-24 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="5000"
          />
        </div>
        <div class="md:col-span-2">
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            :disabled="createForm.processing"
            type="submit"
          >
            Add to roster
          </button>
          <p v-if="Object.keys(createForm.errors).length" class="mt-2 text-sm text-rose-300">
            Please correct the roster entry values.
          </p>
        </div>
      </form>
    </section>

    <section class="mt-8 space-y-4" aria-labelledby="roster-existing">
      <h2 id="roster-existing" class="text-xl font-semibold">Tracked players</h2>
      <article
        v-for="entry in entries"
        :key="entry.id"
        class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6"
      >
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h3 class="text-lg font-semibold">{{ entry.name }}</h3>
            <p class="mt-1 text-sm text-slate-400">
              Game ID: {{ entry.gamePlayerId ?? 'unknown' }} · State: {{ entry.state }}
            </p>
          </div>
          <button
            v-if="entry.state !== 'left'"
            class="font-semibold text-rose-300 hover:text-rose-200"
            type="button"
            @click="markLeft(entry)"
          >
            Mark left
          </button>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
          <div>
            <label class="text-sm font-medium" :for="`name-${entry.id}`">Observed name</label>
            <input
              :id="`name-${entry.id}`"
              v-model="drafts[entry.id].name"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              maxlength="160"
            />
          </div>
          <div>
            <label class="text-sm font-medium" :for="`member-${entry.id}`">Linked member</label>
            <select
              :id="`member-${entry.id}`"
              v-model="drafts[entry.id].membership_id"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="">Unlinked</option>
              <option
                v-for="membership in memberships"
                :key="membership.id"
                :disabled="membershipUnavailable(membership, entry.id)"
                :value="membership.id"
              >
                {{ membership.name }} · {{ membership.email }}
                {{ membershipUnavailable(membership, entry.id) ? ' · already linked' : '' }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium" :for="`role-${entry.id}`">Game role / rank</label>
            <input
              :id="`role-${entry.id}`"
              v-model="drafts[entry.id].game_role"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              maxlength="64"
            />
          </div>
          <div>
            <label class="text-sm font-medium" :for="`state-${entry.id}`">Roster state</label>
            <select
              :id="`state-${entry.id}`"
              v-model="drafts[entry.id].state"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium" :for="`joined-${entry.id}`">Joined date</label>
            <input
              :id="`joined-${entry.id}`"
              v-model="drafts[entry.id].joined_at"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              type="date"
            />
          </div>
          <div class="md:col-span-2">
            <label class="text-sm font-medium" :for="`notes-${entry.id}`">Private manager notes</label>
            <textarea
              :id="`notes-${entry.id}`"
              v-model="drafts[entry.id].manager_notes"
              class="mt-1 min-h-20 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              maxlength="5000"
            />
          </div>
        </div>

        <button
          class="mt-5 rounded-lg border border-cyan-800 px-4 py-2 font-semibold text-cyan-300 hover:border-cyan-600"
          type="button"
          @click="saveEntry(entry)"
        >
          Save player
        </button>
      </article>
      <p v-if="!entries.length" class="text-sm text-slate-400">No roster entries yet.</p>
    </section>
  </main>
</template>
