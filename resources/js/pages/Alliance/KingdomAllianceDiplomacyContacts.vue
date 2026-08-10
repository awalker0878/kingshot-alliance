<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

 type Contact = {
  id: string;
  displayName: string;
  gameRole: string | null;
  channelType: string;
  handle: string;
  state: 'active' | 'inactive';
  lastVerifiedAt: string | null;
  managerNotes: string | null;
  createdByName: string | null;
  updatedByName: string | null;
  deactivatedByName: string | null;
  createdAt: string;
  updatedAt: string;
  deactivatedAt: string | null;
};

const props = defineProps<{
  alliance: {
    id: string;
    name: string;
    kingdom: string | null;
  };
  tracking: {
    id: string;
    name: string;
    tag: string | null;
    state: string;
    kingdom: string;
    contextCurrent: boolean;
  };
  channels: { value: string; label: string }[];
  contactLimit: number;
  contacts: Contact[];
}>();

let editingId: string | null = null;

function toLocalInput(value: string | null): string {
  if (value === null) return '';
  const date = new Date(value);
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
  return local.toISOString().slice(0, 16);
}

function toNullableIso(value: string): string | null {
  return value.trim() === '' ? null : new Date(value).toISOString();
}

function formatDate(value: string | null): string {
  if (value === null) return 'Not set';
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function channelLabel(value: string): string {
  return props.channels.find((channel) => channel.value === value)?.label ?? value;
}

const form = useForm({
  display_name: '',
  game_role: '',
  channel_type: props.channels[0]?.value ?? 'in_game',
  handle: '',
  last_verified_at: '',
  manager_notes: '',
});

function contactError(): string | undefined {
  return (form.errors as Record<string, string | undefined>).contact;
}

function resetForm(): void {
  editingId = null;
  form.reset();
  form.channel_type = props.channels[0]?.value ?? 'in_game';
  form.clearErrors();
}

function beginEdit(contact: Contact): void {
  if (contact.state !== 'active') return;

  editingId = contact.id;
  form.display_name = contact.displayName;
  form.game_role = contact.gameRole ?? '';
  form.channel_type = contact.channelType;
  form.handle = contact.handle;
  form.last_verified_at = toLocalInput(contact.lastVerifiedAt);
  form.manager_notes = contact.managerNotes ?? '';
  form.clearErrors();
}

function submitContact(): void {
  const request = form.transform((data) => ({
    ...data,
    game_role: data.game_role.trim() === '' ? null : data.game_role,
    last_verified_at: toNullableIso(data.last_verified_at),
    manager_notes: data.manager_notes.trim() === '' ? null : data.manager_notes,
  }));

  const options = {
    preserveScroll: true,
    onSuccess: () => resetForm(),
  };

  if (editingId === null) {
    request.post(`/alliance/kingdom-alliances/${props.tracking.id}/diplomacy/contacts`, options);
    return;
  }

  request.patch(
    `/alliance/kingdom-alliances/${props.tracking.id}/diplomacy/contacts/${editingId}`,
    options,
  );
}

function deactivateContact(contact: Contact): void {
  if (contact.state !== 'active') return;
  if (!window.confirm(`Deactivate ${contact.displayName} as a diplomacy contact?`)) return;

  router.post(
    `/alliance/kingdom-alliances/${props.tracking.id}/diplomacy/contacts/${contact.id}/deactivate`,
    {},
    { preserveScroll: true },
  );
}
</script>

<template>
  <Head :title="`Diplomacy contacts · ${tracking.name}`" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Diplomacy coordination
        </p>
        <h1 class="mt-2 text-3xl font-bold">{{ tracking.name }} contacts</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-400">
          {{ alliance.name }} · Kingdom {{ tracking.kingdom }}. This is a manager-private handle
          directory for diplomacy coordination. Contacts do not create users, memberships, player
          identity, or permissions.
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <Link
          class="rounded-lg border border-cyan-800 px-4 py-2 text-sm font-semibold text-cyan-300"
          :href="`/alliance/kingdom-alliances/${tracking.id}/diplomacy`"
        >
          Diplomacy
        </Link>
        <Link
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200"
          :href="`/alliance/kingdom-alliances/${tracking.id}/history`"
        >
          Observation history
        </Link>
        <Link
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200"
          href="/alliance/kingdom-alliances/manage"
        >
          Tracking workspace
        </Link>
      </div>
    </header>

    <div
      class="mt-8 rounded-xl border border-amber-900 bg-amber-950/30 p-4 text-sm text-amber-100"
    >
      Store handles only. Do not put phone numbers, home addresses, passwords, recovery material, or
      other private secrets in this directory. Handles and notes stay manager-private and are not
      copied into audit/outbox payloads.
    </div>

    <div
      v-if="tracking.state !== 'active' || !tracking.contextCurrent"
      class="mt-5 rounded-xl border border-amber-900 bg-amber-950/30 p-4 text-sm text-amber-200"
    >
      This tracking record is read-only because it is archived or belongs to historical Kingdom
      context. Existing contact history remains visible, but contacts cannot be created, edited, or
      deactivated.
    </div>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">{{ editingId === null ? 'Add contact' : 'Edit contact' }}</h2>
      <p class="mt-1 text-sm text-slate-400">
        Display names and handles are coordination labels only. They never auto-link a Kingdom
        player or platform account.
      </p>

      <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="submitContact">
        <div>
          <label class="block text-sm font-medium" for="contact-display-name">Display name</label>
          <input
            id="contact-display-name"
            v-model="form.display_name"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
            maxlength="160"
            required
            type="text"
          />
          <p v-if="form.errors.display_name" class="mt-1 text-sm text-rose-300">
            {{ form.errors.display_name }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="contact-game-role">Game-side role/title</label>
          <input
            id="contact-game-role"
            v-model="form.game_role"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
            maxlength="120"
            type="text"
          />
          <p v-if="form.errors.game_role" class="mt-1 text-sm text-rose-300">
            {{ form.errors.game_role }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="contact-channel">Contact channel</label>
          <select
            id="contact-channel"
            v-model="form.channel_type"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
          >
            <option v-for="channel in channels" :key="channel.value" :value="channel.value">
              {{ channel.label }}
            </option>
          </select>
          <p v-if="form.errors.channel_type" class="mt-1 text-sm text-rose-300">
            {{ form.errors.channel_type }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="contact-handle">Handle / identifier</label>
          <input
            id="contact-handle"
            v-model="form.handle"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
            maxlength="255"
            required
            type="text"
          />
          <p v-if="form.errors.handle" class="mt-1 text-sm text-rose-300">
            {{ form.errors.handle }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="contact-last-verified">Last verified</label>
          <input
            id="contact-last-verified"
            v-model="form.last_verified_at"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
            type="datetime-local"
          />
          <p class="mt-1 text-xs text-slate-500">Optional factual verification time; future values are rejected.</p>
          <p v-if="form.errors.last_verified_at" class="mt-1 text-sm text-rose-300">
            {{ form.errors.last_verified_at }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="contact-manager-notes">Private manager notes</label>
          <textarea
            id="contact-manager-notes"
            v-model="form.manager_notes"
            class="mt-2 min-h-28 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
            maxlength="5000"
          />
          <p v-if="form.errors.manager_notes" class="mt-1 text-sm text-rose-300">
            {{ form.errors.manager_notes }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 md:col-span-2">
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            :disabled="form.processing || tracking.state !== 'active' || !tracking.contextCurrent"
            type="submit"
          >
            {{ editingId === null ? 'Add contact' : 'Save contact' }}
          </button>
          <button
            v-if="editingId !== null"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold"
            type="button"
            @click="resetForm"
          >
            Cancel edit
          </button>
          <p v-if="contactError()" class="text-sm text-rose-300">{{ contactError() }}</p>
        </div>
      </form>
    </section>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 class="text-xl font-semibold">Private contact directory</h2>
          <p class="mt-1 text-sm text-slate-400">
            Inactive entries remain visible as coordination history and are not destructively
            deleted or edited.
          </p>
        </div>
        <p class="text-sm text-slate-500">Up to {{ contactLimit }} contacts</p>
      </div>

      <div v-if="contacts.length" class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <thead class="text-xs tracking-wide text-slate-400 uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">Contact</th>
              <th class="px-3 py-3 font-semibold">Channel</th>
              <th class="px-3 py-3 font-semibold">Verification</th>
              <th class="px-3 py-3 font-semibold">Private notes</th>
              <th class="px-3 py-3 font-semibold">Lifecycle</th>
              <th class="px-3 py-3 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="contact in contacts" :key="contact.id">
              <td class="px-3 py-4 text-slate-200">
                <p class="font-semibold">{{ contact.displayName }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ contact.gameRole ?? 'No role recorded' }}</p>
              </td>
              <td class="px-3 py-4 text-slate-300">
                <p>{{ channelLabel(contact.channelType) }}</p>
                <p class="mt-1 font-mono text-xs text-slate-400">{{ contact.handle }}</p>
              </td>
              <td class="px-3 py-4 text-slate-300">{{ formatDate(contact.lastVerifiedAt) }}</td>
              <td class="px-3 py-4 whitespace-pre-wrap text-slate-300">
                {{ contact.managerNotes ?? 'No private notes' }}
              </td>
              <td class="px-3 py-4 text-slate-300">
                <p class="font-semibold">{{ contact.state }}</p>
                <p class="mt-1 text-xs text-slate-500">Updated {{ formatDate(contact.updatedAt) }}</p>
                <p v-if="contact.deactivatedAt" class="mt-1 text-xs text-slate-500">
                  Deactivated {{ formatDate(contact.deactivatedAt) }} by
                  {{ contact.deactivatedByName ?? 'former/deleted user' }}
                </p>
                <p v-else class="mt-1 text-xs text-slate-500">
                  Last manager {{ contact.updatedByName ?? contact.createdByName ?? 'former/deleted user' }}
                </p>
              </td>
              <td class="px-3 py-4">
                <div v-if="contact.state === 'active'" class="flex flex-wrap gap-2">
                  <button
                    class="rounded-lg border border-cyan-800 px-3 py-2 text-xs font-semibold text-cyan-300 disabled:opacity-60"
                    :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
                    type="button"
                    @click="beginEdit(contact)"
                  >
                    Edit
                  </button>
                  <button
                    class="rounded-lg border border-rose-900 px-3 py-2 text-xs font-semibold text-rose-300 disabled:opacity-60"
                    :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
                    type="button"
                    @click="deactivateContact(contact)"
                  >
                    Deactivate
                  </button>
                </div>
                <span v-else class="text-xs text-slate-500">Historical</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-else
        class="mt-6 rounded-xl border border-dashed border-slate-700 p-5 text-sm text-slate-400"
      >
        No diplomacy contacts have been recorded for this tracked alliance.
      </p>
    </section>
  </main>
</template>
