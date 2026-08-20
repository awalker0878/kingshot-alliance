<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';

type TrackingRow = {
  id: string;
  kingdomAllianceId: string;
  gameAllianceId: string | null;
  name: string;
  tag: string | null;
  state: string;
  kingdom: string;
  contextCurrent: boolean;
  referenceStatus: string;
  managerNotes: string | null;
  archivedAt: string | null;
  diplomacyState: string;
  diplomacyNeedsReview: boolean;
  diplomacyUrl: string;
};

defineProps<{
  alliance: {
    id: string;
    name: string;
    kingdom: string | null;
  };
  tracking: TrackingRow[];
}>();

const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();

const createForm = useForm({
  current_name: '',
  current_tag: '',
  game_alliance_id: '',
  manager_notes: '',
});

const editForm = useForm({
  id: '',
  current_name: '',
  current_tag: '',
  game_alliance_id: '',
  manager_notes: '',
});

function trackingError(errors: object): string | undefined {
  return (errors as Record<string, string | undefined>).tracking;
}

function stateLabel(value: string): string {
  if (value === 'nap') return 'NAP';
  return value.charAt(0).toUpperCase() + value.slice(1).replaceAll('_', ' ');
}

function createTracking(): void {
  createForm.post('/alliance/kingdom-alliances', {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  });
}

function beginEdit(entry: TrackingRow): void {
  editForm.clearErrors();
  editForm.id = entry.id;
  editForm.current_name = entry.name;
  editForm.current_tag = entry.tag ?? '';
  editForm.game_alliance_id = entry.gameAllianceId ?? '';
  editForm.manager_notes = entry.managerNotes ?? '';
}

function cancelEdit(): void {
  editForm.reset();
  editForm.clearErrors();
}

function saveEdit(): void {
  if (editForm.id === '') return;

  editForm.patch(`/alliance/kingdom-alliances/${editForm.id}`, {
    preserveScroll: true,
    onSuccess: () => cancelEdit(),
  });
}

function archiveTracking(entry: TrackingRow): void {
  requestConfirmation({
    id: 'alliance-tracking-archive-confirmation',
    title: 'Archive tracking',
    description: `Archive tracking for ${entry.name}? Historical tracking remains available.`,
    confirmLabel: 'Archive tracking',
    cancelLabel: 'Cancel',
    perform: (finish) =>
      router.post(
        `/alliance/kingdom-alliances/${entry.id}/archive`,
        {},
        { preserveScroll: true, onFinish: finish },
      ),
  });
}
</script>

<template>
  <Head title="Manage Kingdom alliances" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Kingdom intelligence
        </p>
        <h1 class="mt-2 text-3xl font-bold">Manage tracked game-side alliances</h1>
        <p class="mt-2 max-w-3xl text-sm text-[var(--ks-muted)]">
          {{ alliance.name }} · current Kingdom {{ alliance.kingdom ?? 'not configured' }}. Stable
          game alliance ID is the only automatic identity key. Names and tags never auto-merge
          records.
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <Link
          class="rounded-lg border border-cyan-800 px-4 py-2 text-sm font-semibold text-cyan-300"
          href="/alliance/kingdom-alliances/intelligence"
        >
          Intelligence overview
        </Link>
        <Link
          class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-ivory)]"
          href="/alliance/kingdom-alliances"
        >
          Member view
        </Link>
      </div>
    </header>

    <section class="mt-10 rounded-2xl border border-[var(--ks-border)] bg-[rgba(24,25,21,.78)] p-6">
      <h2 class="text-xl font-semibold">Start tracking</h2>
      <p class="mt-1 text-sm text-[var(--ks-muted)]">
        Without a stable game alliance ID, a new distinct neutral reference is created even if
        another record has the same name or tag.
      </p>

      <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="createTracking">
        <div>
          <label class="block text-sm font-medium" for="tracked-alliance-name">Alliance name</label>
          <input
            id="tracked-alliance-name"
            v-model="createForm.current_name"
            class="mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-night)] px-3 py-2"
            maxlength="160"
            required
            type="text"
          />
          <p v-if="createForm.errors.current_name" class="mt-1 text-sm text-rose-300">
            {{ createForm.errors.current_name }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="tracked-alliance-tag">Alliance tag</label>
          <input
            id="tracked-alliance-tag"
            v-model="createForm.current_tag"
            class="mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-night)] px-3 py-2"
            maxlength="32"
            type="text"
          />
          <p v-if="createForm.errors.current_tag" class="mt-1 text-sm text-rose-300">
            {{ createForm.errors.current_tag }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="tracked-alliance-stable-id">
            Stable game alliance ID
          </label>
          <input
            id="tracked-alliance-stable-id"
            v-model="createForm.game_alliance_id"
            class="mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-night)] px-3 py-2"
            maxlength="100"
            type="text"
          />
          <p class="mt-1 text-xs text-[var(--ks-muted)]">
            Optional. Once assigned, it cannot be cleared or changed in place.
          </p>
          <p v-if="createForm.errors.game_alliance_id" class="mt-1 text-sm text-rose-300">
            {{ createForm.errors.game_alliance_id }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="tracked-alliance-notes"
            >Officer notes</label
          >
          <textarea
            id="tracked-alliance-notes"
            v-model="createForm.manager_notes"
            class="mt-2 min-h-24 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-night)] px-3 py-2"
            maxlength="5000"
          />
          <p class="mt-1 text-xs text-[var(--ks-muted)]">
            Private to authorized Alliance officers.
          </p>
          <p v-if="createForm.errors.manager_notes" class="mt-1 text-sm text-rose-300">
            {{ createForm.errors.manager_notes }}
          </p>
        </div>

        <div class="md:col-span-2">
          <p v-if="trackingError(createForm.errors)" class="mb-3 text-sm text-rose-300">
            {{ trackingError(createForm.errors) }}
          </p>
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-[var(--ks-ink)] disabled:opacity-60"
            :disabled="createForm.processing || alliance.kingdom === null"
            type="submit"
          >
            Start tracking
          </button>
        </div>
      </form>
    </section>

    <section class="mt-8 rounded-2xl border border-[var(--ks-border)] bg-[rgba(24,25,21,.78)] p-6">
      <div>
        <h2 class="text-xl font-semibold">Tracking records</h2>
        <p class="mt-1 text-sm text-[var(--ks-muted)]">
          Earlier Kingdom records are retained. Drifted records may be archived but cannot be
          edited. Diplomacy is a separate explicit officer-maintained diplomacy.
        </p>
      </div>

      <div v-if="tracking.length" class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-[var(--ks-border)] text-left text-sm">
          <thead class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">Alliance</th>
              <th class="px-3 py-3 font-semibold">Stable ID</th>
              <th class="px-3 py-3 font-semibold">Kingdom</th>
              <th class="px-3 py-3 font-semibold">Diplomacy</th>
              <th class="px-3 py-3 font-semibold">State</th>
              <th class="px-3 py-3 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="entry in tracking" :key="entry.id">
              <td class="px-3 py-4">
                <p class="font-medium text-[var(--ks-ivory)]">{{ entry.name }}</p>
                <p class="mt-1 text-xs text-[var(--ks-muted)]">
                  {{ entry.tag ?? 'No tag recorded' }}
                </p>
              </td>
              <td class="px-3 py-4 text-[var(--ks-muted)]">
                {{ entry.gameAllianceId ?? 'Unresolved' }}
              </td>
              <td class="px-3 py-4 text-[var(--ks-muted)]">
                {{ entry.kingdom }}
                <span
                  v-if="!entry.contextCurrent"
                  class="ml-2 rounded-full bg-amber-950 px-2 py-1 text-xs font-semibold text-amber-300"
                >
                  Historical
                </span>
              </td>
              <td class="px-3 py-4 text-[var(--ks-muted)]">
                <span class="font-semibold">{{ stateLabel(entry.diplomacyState) }}</span>
                <span
                  v-if="entry.diplomacyNeedsReview"
                  class="ml-2 rounded-full bg-amber-950 px-2 py-1 text-xs font-semibold text-amber-300"
                >
                  Review due
                </span>
              </td>
              <td class="px-3 py-4 text-[var(--ks-muted)]">{{ entry.state }}</td>
              <td class="px-3 py-4">
                <div class="flex flex-wrap gap-2">
                  <Link
                    class="rounded-lg border border-cyan-800 px-3 py-2 text-xs font-semibold text-cyan-300"
                    :href="entry.diplomacyUrl"
                  >
                    Diplomacy
                  </Link>
                  <button
                    v-if="entry.state === 'active' && entry.contextCurrent"
                    class="rounded-lg border border-[var(--ks-border)] px-3 py-2 text-xs font-semibold"
                    type="button"
                    @click="beginEdit(entry)"
                  >
                    Edit
                  </button>
                  <button
                    v-if="entry.state === 'active'"
                    class="rounded-lg border border-amber-800 px-3 py-2 text-xs font-semibold text-amber-300"
                    type="button"
                    @click="archiveTracking(entry)"
                  >
                    Archive
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p
        v-else
        class="mt-6 rounded-xl border border-dashed border-[var(--ks-border)] p-5 text-sm text-[var(--ks-muted)]"
      >
        No tracking records yet.
      </p>
    </section>

    <section
      v-if="editForm.id !== ''"
      class="mt-8 rounded-2xl border border-cyan-900 bg-[rgba(24,25,21,.78)] p-6"
    >
      <h2 class="text-xl font-semibold">Edit tracked alliance</h2>
      <p class="mt-1 text-sm text-[var(--ks-muted)]">
        Assigning a stable ID is explicit. A conflicting stable ID fails closed rather than merging
        references.
      </p>

      <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="saveEdit">
        <div>
          <label class="block text-sm font-medium" for="edit-tracked-alliance-name"
            >Alliance name</label
          >
          <input
            id="edit-tracked-alliance-name"
            v-model="editForm.current_name"
            class="mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-night)] px-3 py-2"
            maxlength="160"
            required
            type="text"
          />
          <p v-if="editForm.errors.current_name" class="mt-1 text-sm text-rose-300">
            {{ editForm.errors.current_name }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="edit-tracked-alliance-tag"
            >Alliance tag</label
          >
          <input
            id="edit-tracked-alliance-tag"
            v-model="editForm.current_tag"
            class="mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-night)] px-3 py-2"
            maxlength="32"
            type="text"
          />
        </div>

        <div>
          <label class="block text-sm font-medium" for="edit-tracked-alliance-stable-id">
            Stable game alliance ID
          </label>
          <input
            id="edit-tracked-alliance-stable-id"
            v-model="editForm.game_alliance_id"
            class="mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-night)] px-3 py-2"
            maxlength="100"
            type="text"
          />
          <p v-if="editForm.errors.game_alliance_id" class="mt-1 text-sm text-rose-300">
            {{ editForm.errors.game_alliance_id }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="edit-tracked-alliance-notes"
            >Officer notes</label
          >
          <textarea
            id="edit-tracked-alliance-notes"
            v-model="editForm.manager_notes"
            class="mt-2 min-h-24 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-night)] px-3 py-2"
            maxlength="5000"
          />
          <p v-if="editForm.errors.manager_notes" class="mt-1 text-sm text-rose-300">
            {{ editForm.errors.manager_notes }}
          </p>
        </div>

        <div class="flex flex-wrap gap-3 md:col-span-2">
          <p v-if="trackingError(editForm.errors)" class="w-full text-sm text-rose-300">
            {{ trackingError(editForm.errors) }}
          </p>
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-[var(--ks-ink)] disabled:opacity-60"
            :disabled="editForm.processing"
            type="submit"
          >
            Save changes
          </button>
          <button
            class="rounded-lg border border-[var(--ks-border)] px-4 py-2 font-semibold"
            type="button"
            @click="cancelEdit"
          >
            Cancel
          </button>
        </div>
      </form>
    </section>

    <ConfirmActionDialog v-bind="dialog" @confirm="confirmAction" @cancel="cancelConfirmation" />
  </main>
</template>
