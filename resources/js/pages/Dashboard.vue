<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

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

defineProps<{
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

function logout(): void {
  router.delete('/logout');
}
</script>

<template>
  <Head title="Dashboard" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Kingshot Alliance
        </p>
        <h1 class="mt-2 text-3xl font-bold">Welcome, {{ user.name }}</h1>
        <p class="mt-2 text-sm text-slate-400">
          {{ user.email }} · {{ user.timezone }}
          <span v-if="!user.emailVerified"> · email verification pending</span>
        </p>
      </div>
      <button
        class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold"
        type="button"
        @click="logout"
      >
        Sign out
      </button>
    </header>

    <section class="mt-10">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h2 class="text-xl font-semibold">Your alliances</h2>
          <p class="mt-1 text-sm text-slate-400">
            Choose the tenant context used for alliance-scoped routes.
          </p>
        </div>
        <Link
          v-if="activeAllianceId"
          class="rounded-lg bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950"
          href="/alliance"
        >
          Open active alliance
        </Link>
      </div>

      <div v-if="memberships.length" class="mt-5 grid gap-4 md:grid-cols-2">
        <article
          v-for="membership in memberships"
          :key="membership.id"
          class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="font-semibold">{{ membership.alliance.name }}</h3>
              <p class="mt-1 text-sm text-slate-400">{{ membership.alliance.timezone }}</p>
            </div>
            <span
              v-if="activeAllianceId === membership.alliance.id"
              class="rounded-full bg-emerald-950 px-3 py-1 text-xs font-semibold text-emerald-300"
            >
              Active
            </span>
          </div>
          <p class="mt-4 text-xs text-slate-500">
            Roles: {{ membership.roles.map((role) => role.name).join(', ') || 'None' }}
          </p>
          <div class="mt-4 flex flex-wrap gap-3">
            <button
              v-if="activeAllianceId !== membership.alliance.id"
              class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold hover:border-slate-500"
              type="button"
              @click="activateAlliance(membership.alliance.id)"
            >
              Switch to this alliance
            </button>
            <Link
              v-if="activeAllianceId === membership.alliance.id"
              class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-200 hover:border-slate-500"
              href="/alliance/roster"
            >
              Roster
            </Link>
            <Link
              v-if="activeAllianceId === membership.alliance.id"
              class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-200 hover:border-slate-500"
              href="/alliance/kingdom-alliances"
            >
              Kingdom alliances
            </Link>
            <Link
              v-if="activeAllianceId === membership.alliance.id"
              class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-200 hover:border-slate-500"
              href="/alliance/transfers"
            >
              Transfers
            </Link>
            <Link
              v-if="activeAllianceId === membership.alliance.id && membership.canManageAlliance"
              class="rounded-lg border border-cyan-800 px-3 py-2 text-sm font-semibold text-cyan-300 hover:border-cyan-600"
              href="/alliance/settings/kingdom"
            >
              Kingdom settings
            </Link>
          </div>
        </article>
      </div>
      <p
        v-else
        class="mt-5 rounded-xl border border-dashed border-slate-700 p-5 text-sm text-slate-400"
      >
        You do not have an active alliance membership yet. Create an alliance below.
      </p>
    </section>

    <section class="mt-12 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">Create an alliance</h2>
      <p class="mt-1 text-sm text-slate-400">You become the initial owner in one transaction.</p>

      <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="createAlliance">
        <div>
          <label class="block text-sm font-medium" for="alliance-name">Name</label>
          <input
            id="alliance-name"
            v-model="allianceForm.name"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            type="text"
            @blur="slugifyName"
          />
          <p v-if="allianceForm.errors.name" class="mt-1 text-sm text-rose-300">
            {{ allianceForm.errors.name }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="alliance-slug">Slug</label>
          <input
            id="alliance-slug"
            v-model="allianceForm.slug"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
            required
            type="text"
          />
          <p v-if="allianceForm.errors.slug" class="mt-1 text-sm text-rose-300">
            {{ allianceForm.errors.slug }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="alliance-kingdom">Kingdom number</label>
          <input
            id="alliance-kingdom"
            v-model="allianceForm.kingdom"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            inputmode="numeric"
            pattern="[1-9][0-9]*"
            type="text"
          />
          <p v-if="allianceForm.errors.kingdom" class="mt-1 text-sm text-rose-300">
            {{ allianceForm.errors.kingdom }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="alliance-timezone">Time zone</label>
          <input
            id="alliance-timezone"
            v-model="allianceForm.timezone"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            type="text"
          />
          <p v-if="allianceForm.errors.timezone" class="mt-1 text-sm text-rose-300">
            {{ allianceForm.errors.timezone }}
          </p>
        </div>

        <div class="md:col-span-2">
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            :disabled="allianceForm.processing"
            type="submit"
          >
            Create alliance
          </button>
        </div>
      </form>
    </section>
  </main>
</template>
