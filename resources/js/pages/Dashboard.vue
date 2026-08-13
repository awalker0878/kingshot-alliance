<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLayout from '../layouts/AppLayout.vue';

type RoleSummary = {
  key: string;
  name: string;
};

type MembershipSummary = {
  id: string;
  alliance: {
    id: string;
    name: string;
    slug: string;
    timezone: string;
  };
  roles: RoleSummary[];
  canManageAlliance: boolean;
};

const props = defineProps<{
  user: {
    id: number;
    name: string;
    email: string;
    emailVerified: boolean;
    timezone: string;
  };
  memberships: MembershipSummary[];
  activeAllianceId: string | null;
}>();

const activeMembership = computed(
  () =>
    props.memberships.find((membership) => membership.alliance.id === props.activeAllianceId) ??
    null,
);

const allianceForm = useForm({
  name: '',
  slug: '',
  kingdom: '',
  language: 'en',
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
});

function slugifyName(): void {
  if (allianceForm.slug !== '') return;

  allianceForm.slug = allianceForm.name
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function createAlliance(): void {
  allianceForm.post('/alliances', {
    onSuccess: () => allianceForm.reset('name', 'slug', 'kingdom'),
  });
}

function activateAlliance(allianceId: string): void {
  router.put(`/alliances/${allianceId}/active`);
}
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout
    :user="user"
    :has-active-alliance="activeAllianceId !== null"
    :alliance-name="activeMembership?.alliance.name ?? null"
  >
    <header class="mb-8">
      <p class="text-sm font-semibold tracking-[0.18em] text-[var(--ks-gold)] uppercase">
        Kingshot Alliance
      </p>
      <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">Welcome, {{ user.name }}</h1>
      <p class="mt-2 text-sm text-[var(--ks-text-muted)]">
        {{ user.email }} · {{ user.timezone }}
        <span v-if="!user.emailVerified"> · email verification pending</span>
      </p>
    </header>

    <section>
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 class="text-xl font-semibold">Your alliances</h2>
          <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
            Choose the tenant context used for alliance-scoped routes.
          </p>
        </div>
        <Link
          v-if="activeAllianceId"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-strong)] bg-[var(--ks-gold)] px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-[var(--ks-gold-strong)]"
          href="/alliance"
        >
          Open active alliance
        </Link>
      </div>

      <div v-if="memberships.length" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="membership in memberships"
          :key="membership.id"
          class="ks-surface p-5"
          :class="
            activeAllianceId === membership.alliance.id
              ? 'ring-1 ring-[var(--ks-border-strong)]'
              : ''
          "
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h3 class="truncate font-semibold">{{ membership.alliance.name }}</h3>
              <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
                {{ membership.alliance.timezone }}
              </p>
            </div>
            <span
              v-if="activeAllianceId === membership.alliance.id"
              class="rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-300"
            >
              Active
            </span>
          </div>

          <p class="mt-4 text-xs leading-5 text-[var(--ks-text-muted)]">
            Roles: {{ membership.roles.map((role) => role.name).join(', ') || 'None' }}
          </p>

          <div class="mt-5 flex flex-wrap gap-2">
            <button
              v-if="activeAllianceId !== membership.alliance.id"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-border-strong)] hover:bg-[var(--ks-surface-2)] hover:text-[var(--ks-text)]"
              type="button"
              @click="activateAlliance(membership.alliance.id)"
            >
              Switch to this alliance
            </button>
            <Link
              v-if="activeAllianceId === membership.alliance.id"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:bg-[var(--ks-surface-2)]"
              href="/alliance/roster"
            >
              Roster
            </Link>
            <Link
              v-if="activeAllianceId === membership.alliance.id"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:bg-[var(--ks-surface-2)]"
              href="/alliance/kingdom-alliances"
            >
              Kingdom alliances
            </Link>
            <Link
              v-if="activeAllianceId === membership.alliance.id"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:bg-[var(--ks-surface-2)]"
              href="/alliance/transfers"
            >
              Transfers
            </Link>
            <Link
              v-if="activeAllianceId === membership.alliance.id && membership.canManageAlliance"
              class="rounded-[var(--ks-radius-sm)] border border-[rgba(75,143,247,0.4)] bg-[var(--ks-blue-soft)] px-3 py-2 text-sm font-semibold text-[var(--ks-blue-strong)] transition hover:border-[var(--ks-blue)]"
              href="/alliance/settings/kingdom"
            >
              Kingdom settings
            </Link>
          </div>
        </article>
      </div>

      <p
        v-else
        class="mt-5 rounded-[var(--ks-radius-lg)] border border-dashed border-[var(--ks-border)] bg-[var(--ks-surface-1)]/60 p-5 text-sm text-[var(--ks-text-muted)]"
      >
        You do not have an active alliance membership yet. Create an alliance below.
      </p>
    </section>

    <section class="ks-surface-gold mt-10 p-6 sm:p-7">
      <div class="max-w-2xl">
        <h2 class="ks-display text-2xl font-semibold">Create an alliance</h2>
        <p class="mt-2 text-sm text-[var(--ks-text-muted)]">
          You become the initial owner in one transaction.
        </p>
      </div>

      <form class="mt-7 grid gap-5 md:grid-cols-2" @submit.prevent="createAlliance">
        <div>
          <label class="block text-sm font-medium" for="alliance-name">Name</label>
          <input
            id="alliance-name"
            v-model="allianceForm.name"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-[var(--ks-text)] transition focus:border-[var(--ks-blue)]"
            required
            type="text"
            @blur="slugifyName"
          />
          <p v-if="allianceForm.errors.name" class="mt-1.5 text-sm text-[var(--ks-red)]">
            {{ allianceForm.errors.name }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="alliance-slug">Slug</label>
          <input
            id="alliance-slug"
            v-model="allianceForm.slug"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-[var(--ks-text)] transition focus:border-[var(--ks-blue)]"
            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
            required
            type="text"
          />
          <p v-if="allianceForm.errors.slug" class="mt-1.5 text-sm text-[var(--ks-red)]">
            {{ allianceForm.errors.slug }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="alliance-kingdom">Kingdom number</label>
          <input
            id="alliance-kingdom"
            v-model="allianceForm.kingdom"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-[var(--ks-text)] transition focus:border-[var(--ks-blue)]"
            inputmode="numeric"
            pattern="[1-9][0-9]*"
            type="text"
          />
          <p v-if="allianceForm.errors.kingdom" class="mt-1.5 text-sm text-[var(--ks-red)]">
            {{ allianceForm.errors.kingdom }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="alliance-timezone">Time zone</label>
          <input
            id="alliance-timezone"
            v-model="allianceForm.timezone"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-[var(--ks-text)] transition focus:border-[var(--ks-blue)]"
            required
            type="text"
          />
          <p v-if="allianceForm.errors.timezone" class="mt-1.5 text-sm text-[var(--ks-red)]">
            {{ allianceForm.errors.timezone }}
          </p>
        </div>

        <div class="md:col-span-2">
          <button
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-strong)] bg-[var(--ks-gold)] px-4 py-2.5 font-bold text-slate-950 transition hover:bg-[var(--ks-gold-strong)] disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="allianceForm.processing"
            type="submit"
          >
            Create alliance
          </button>
        </div>
      </form>
    </section>
  </AppLayout>
</template>
