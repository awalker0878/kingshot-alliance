<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

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
  user: { name: string; email: string };
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

const { t, formatDate: localeDate, formatNumber } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
const editingId = ref<string | null>(null);
const activeCount = computed(
  () => props.contacts.filter((contact) => contact.state === 'active').length,
);

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
  return value === null
    ? t('kingdomP7B.notSet')
    : localeDate(value, { dateStyle: 'medium', timeStyle: 'short' });
}

function channelLabel(value: string): string {
  if (value === 'in_game') return t('kingdomP7B.inGame');
  if (value === 'discord') return t('kingdomP7B.discord');
  if (value === 'other_handle') return t('kingdomP7B.otherHandle');
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
  editingId.value = null;
  form.reset();
  form.channel_type = props.channels[0]?.value ?? 'in_game';
  form.clearErrors();
}

function beginEdit(contact: Contact): void {
  if (contact.state !== 'active') return;

  editingId.value = contact.id;
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

  if (editingId.value === null) {
    request.post(`/alliance/kingdom-alliances/${props.tracking.id}/diplomacy/contacts`, options);
    return;
  }

  request.patch(
    `/alliance/kingdom-alliances/${props.tracking.id}/diplomacy/contacts/${editingId.value}`,
    options,
  );
}

function deactivateContact(contact: Contact): void {
  if (contact.state !== 'active') return;
  requestConfirmation({
    id: 'diplomacy-contact-deactivation-confirmation',
    title: t('kingdomP7B.deactivate'),
    description: t('kingdomP7B.deactivateConfirm', { name: contact.displayName }),
    confirmLabel: t('kingdomP7B.deactivate'),
    cancelLabel: t('common.cancel'),
    perform: (finish) =>
      router.post(
        `/alliance/kingdom-alliances/${props.tracking.id}/diplomacy/contacts/${contact.id}/deactivate`,
        {},
        { preserveScroll: true, onFinish: finish },
      ),
  });
}
</script>

<template>
  <Head
    :title="`${t('kingdomP7B.contactsTitle', { alliance: tracking.name })} · ${alliance.name}`"
  />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('kingdomP7B.contactsEyebrow') }}
        </p>
        <h1 class="mt-2 text-3xl font-bold">
          {{ t('kingdomP7B.contactsTitle', { alliance: tracking.name }) }}
        </h1>
        <p class="mt-2 max-w-3xl text-sm text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7B.contactsSubtitle', { kingdom: tracking.kingdom }) }}
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <Link
          class="rounded-lg border border-cyan-800 px-4 py-2 text-sm font-semibold text-[var(--ks-gold)]"
          :href="`/alliance/kingdom-alliances/${tracking.id}/diplomacy`"
        >
          Diplomacy
        </Link>
        <Link
          class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-ivory)]"
          :href="`/alliance/kingdom-alliances/${tracking.id}/history`"
        >
          {{ t('kingdomP7B.observationHistory') }}
        </Link>
        <Link
          class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-ivory)]"
          href="/alliance/kingdom-alliances/manage"
        >
          {{ t('kingdomP7B.trackingWorkspace') }}
        </Link>
      </div>
    </header>

    <section class="mt-6 grid gap-3 sm:grid-cols-3" aria-label="Contact summary">
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7B.directory') }}
        </p>
        <p class="mt-2 text-2xl font-bold">{{ formatNumber(contacts.length) }}</p>
        <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
          {{ t('kingdomP7B.contactLimit', { count: contactLimit }) }}
        </p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7B.active') }}
        </p>
        <p class="mt-2 text-2xl font-bold text-green-200">{{ formatNumber(activeCount) }}</p>
      </article>
      <article class="ks-surface p-4">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7B.inactive') }}
        </p>
        <p class="mt-2 text-2xl font-bold">{{ formatNumber(contacts.length - activeCount) }}</p>
      </article>
    </section>

    <div class="mt-4 rounded-xl border border-amber-900 bg-amber-950/30 p-4 text-sm text-amber-100">
      {{ t('kingdomP7B.handlesSafety') }}
    </div>

    <div
      v-if="tracking.state !== 'active' || !tracking.contextCurrent"
      class="mt-5 rounded-xl border border-amber-900 bg-amber-950/30 p-4 text-sm text-amber-200"
    >
      {{ t('kingdomP7B.contactReadOnly') }}
    </div>

    <section class="ks-surface mt-8 p-6">
      <h2 class="text-xl font-semibold">
        {{ editingId === null ? t('kingdomP7B.addContact') : t('kingdomP7B.editContact') }}
      </h2>
      <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">
        {{ t('kingdomP7B.contactHelp') }}
      </p>

      <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="submitContact">
        <div>
          <label class="block text-sm font-medium" for="contact-display-name">{{
            t('kingdomP7B.displayName')
          }}</label>
          <input
            id="contact-display-name"
            v-model="form.display_name"
            class="ks-input mt-2 w-full"
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
          <label class="block text-sm font-medium" for="contact-game-role">{{
            t('kingdomP7B.gameRole')
          }}</label>
          <input
            id="contact-game-role"
            v-model="form.game_role"
            class="ks-input mt-2 w-full"
            :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
            maxlength="120"
            type="text"
          />
          <p v-if="form.errors.game_role" class="mt-1 text-sm text-rose-300">
            {{ form.errors.game_role }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="contact-channel">{{
            t('kingdomP7B.contactChannel')
          }}</label>
          <select
            id="contact-channel"
            v-model="form.channel_type"
            class="ks-input mt-2 w-full"
            :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
          >
            <option v-for="channel in channels" :key="channel.value" :value="channel.value">
              {{ channelLabel(channel.value) }}
            </option>
          </select>
          <p v-if="form.errors.channel_type" class="mt-1 text-sm text-rose-300">
            {{ form.errors.channel_type }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="contact-handle">{{
            t('kingdomP7B.handle')
          }}</label>
          <input
            id="contact-handle"
            v-model="form.handle"
            class="ks-input mt-2 w-full"
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
          <label class="block text-sm font-medium" for="contact-last-verified">{{
            t('kingdomP7B.lastVerified')
          }}</label>
          <input
            id="contact-last-verified"
            v-model="form.last_verified_at"
            class="ks-input mt-2 w-full"
            :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
            type="datetime-local"
          />
          <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
            {{ t('kingdomP7B.verificationHelp') }}
          </p>
          <p v-if="form.errors.last_verified_at" class="mt-1 text-sm text-rose-300">
            {{ form.errors.last_verified_at }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="contact-manager-notes">
            {{ t('kingdomP7B.managerNotes') }}
          </label>
          <textarea
            id="contact-manager-notes"
            v-model="form.manager_notes"
            class="ks-input mt-2 min-h-28 w-full"
            :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
            maxlength="5000"
          />
          <p v-if="form.errors.manager_notes" class="mt-1 text-sm text-rose-300">
            {{ form.errors.manager_notes }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 md:col-span-2">
          <button
            class="rounded-lg bg-[var(--ks-blue)] px-4 py-2 font-semibold text-[var(--ks-ivory)] disabled:opacity-60"
            :disabled="form.processing || tracking.state !== 'active' || !tracking.contextCurrent"
            type="submit"
          >
            {{ editingId === null ? t('kingdomP7B.addContact') : t('kingdomP7B.saveContact') }}
          </button>
          <button
            v-if="editingId !== null"
            class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold"
            type="button"
            @click="resetForm"
          >
            {{ t('kingdomP7B.cancelEdit') }}
          </button>
          <p v-if="contactError()" class="text-sm text-rose-300">{{ contactError() }}</p>
        </div>
      </form>
    </section>

    <section class="ks-surface mt-8 p-6">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 class="text-xl font-semibold">{{ t('kingdomP7B.directory') }}</h2>
          <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">
            {{ t('kingdomP7B.directoryHelp') }}
          </p>
        </div>
        <p class="text-sm text-[var(--ks-text-muted)]">
          {{ t('kingdomP7B.contactLimit', { count: contactLimit }) }}
        </p>
      </div>

      <div v-if="contacts.length" class="mt-6 grid gap-3 lg:hidden">
        <article
          v-for="contact in contacts"
          :key="contact.id"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/10 p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="font-semibold">{{ contact.displayName }}</p>
              <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                {{ contact.gameRole ?? t('kingdomP7B.noRole') }}
              </p>
            </div>
            <span class="text-xs font-semibold">{{
              contact.state === 'active' ? t('kingdomP7B.active') : t('kingdomP7B.inactive')
            }}</span>
          </div>
          <p class="mt-3 text-sm">
            {{ channelLabel(contact.channelType) }} ·
            <span class="font-mono text-xs">{{ contact.handle }}</span>
          </p>
          <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
            {{ t('kingdomP7B.lastVerified') }}: {{ formatDate(contact.lastVerifiedAt) }}
          </p>
          <p
            v-if="contact.managerNotes"
            class="mt-3 text-sm whitespace-pre-wrap text-[var(--ks-text-secondary)]"
          >
            {{ contact.managerNotes }}
          </p>
          <div v-if="contact.state === 'active'" class="mt-3 flex gap-2">
            <button
              class="rounded-lg border border-[var(--ks-border)] px-3 py-2 text-xs font-semibold"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
              type="button"
              @click="beginEdit(contact)"
            >
              {{ t('kingdomP7B.edit') }}</button
            ><button
              class="rounded-lg border border-rose-900 px-3 py-2 text-xs font-semibold text-rose-300"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
              type="button"
              @click="deactivateContact(contact)"
            >
              {{ t('kingdomP7B.deactivate') }}
            </button>
          </div>
        </article>
      </div>

      <div v-if="contacts.length" class="mt-6 hidden overflow-x-auto lg:block">
        <table class="min-w-full divide-y divide-[var(--ks-border)] text-left text-sm">
          <thead class="text-xs tracking-wide text-[var(--ks-text-secondary)] uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7B.contact') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7B.channel') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7B.verification') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7B.managerNotes') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7B.lifecycle') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7B.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="contact in contacts" :key="contact.id">
              <td class="px-3 py-4 text-[var(--ks-ivory)]">
                <p class="font-semibold">{{ contact.displayName }}</p>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  {{ contact.gameRole ?? t('kingdomP7B.noRole') }}
                </p>
              </td>
              <td class="px-3 py-4 text-[var(--ks-muted)]">
                <p>{{ channelLabel(contact.channelType) }}</p>
                <p class="mt-1 font-mono text-xs text-[var(--ks-text-secondary)]">
                  {{ contact.handle }}
                </p>
              </td>
              <td class="px-3 py-4 text-[var(--ks-muted)]">
                {{ formatDate(contact.lastVerifiedAt) }}
              </td>
              <td class="px-3 py-4 whitespace-pre-wrap text-[var(--ks-muted)]">
                {{ contact.managerNotes ?? t('kingdomP7B.noNotes') }}
              </td>
              <td class="px-3 py-4 text-[var(--ks-muted)]">
                <p class="font-semibold">{{ contact.state }}</p>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  Updated {{ formatDate(contact.updatedAt) }}
                </p>
                <p v-if="contact.deactivatedAt" class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  Deactivated {{ formatDate(contact.deactivatedAt) }} by
                  {{ contact.deactivatedByName ?? 'former/deleted user' }}
                </p>
                <p v-else class="mt-1 text-xs text-[var(--ks-text-muted)]">
                  Last officer
                  {{ contact.updatedByName ?? contact.createdByName ?? 'former/deleted user' }}
                </p>
              </td>
              <td class="px-3 py-4">
                <div v-if="contact.state === 'active'" class="flex flex-wrap gap-2">
                  <button
                    class="rounded-lg border border-cyan-800 px-3 py-2 text-xs font-semibold text-[var(--ks-gold)] disabled:opacity-60"
                    :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
                    type="button"
                    @click="beginEdit(contact)"
                  >
                    {{ t('kingdomP7B.edit') }}
                  </button>
                  <button
                    class="rounded-lg border border-rose-900 px-3 py-2 text-xs font-semibold text-rose-300 disabled:opacity-60"
                    :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
                    type="button"
                    @click="deactivateContact(contact)"
                  >
                    {{ t('kingdomP7B.deactivate') }}
                  </button>
                </div>
                <span v-else class="text-xs text-[var(--ks-text-muted)]">Historical</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-else
        class="mt-6 rounded-xl border border-dashed border-[var(--ks-border)] p-5 text-sm text-[var(--ks-text-secondary)]"
      >
        {{ t('kingdomP7B.noContacts') }}
      </p>
    </section>
    <ConfirmActionDialog
      v-bind="dialog"
      @confirm="confirmAction"
      @cancel="cancelConfirmation"
    />
  </AppLayout>
</template>
