<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AppLayout from '../layouts/AppLayout.vue';
import { useLocale } from '../localization';

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
  rank: string;
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
  activePlayer: {
    id: string;
    name: string;
    gamePlayerId: string | null;
    kingdomNumber: number | null;
  } | null;
  membership: MembershipSummary | null;
  canCreateAlliance: boolean;
}>();

const { t } = useLocale();

const allianceForm = useForm({
  name: '',
  slug: '',
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
    onSuccess: () => allianceForm.reset('name', 'slug'),
  });
}

function rolesFor(membership: MembershipSummary): string {
  return [membership.rank.toUpperCase(), ...membership.roles.map((role) => role.name)].join(' · ');
}
</script>

<template>
  <Head :title="t('application.dashboard.title')" />

  <AppLayout
    :user="user"
    :has-player-alliance="membership !== null"
    :player-alliance-name="membership?.alliance.name ?? null"
  >
    <header class="mb-8 grid gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
      <div>
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('application.dashboard.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('application.dashboard.welcome', { name: user.name }) }}
        </h1>
        <div
          class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-[var(--ks-text-muted)]"
        >
          <span>{{ user.email }}</span>
          <span aria-hidden="true">•</span>
          <span>{{ user.timezone }}</span>
          <span
            v-if="!user.emailVerified"
            class="rounded-full border border-amber-500/25 bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-200"
          >
            {{ t('application.dashboard.verificationPending') }}
          </span>
        </div>
      </div>

      <Link
        v-if="membership"
        class="w-fit rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-strong)] bg-[var(--ks-gold)] px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-[var(--ks-gold-strong)]"
        href="/alliance"
      >
        {{ t('application.dashboard.openPlayerAlliance') }}
      </Link>
    </header>

    <section
      v-if="membership"
      class="ks-surface-gold relative overflow-hidden p-6 sm:p-7"
      aria-labelledby="player-alliance-heading"
    >
      <div
        class="pointer-events-none absolute inset-y-0 end-0 w-1/2 bg-[radial-gradient(circle_at_70%_35%,rgba(212,175,55,0.12),transparent_65%)]"
        aria-hidden="true"
      />
      <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
        <div>
          <div class="flex flex-wrap items-center gap-3">
            <p class="text-xs font-bold tracking-[0.18em] text-[var(--ks-gold)] uppercase">
              {{ t('application.dashboard.playerAllianceTitle') }}
            </p>
            <span
              class="rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-300"
            >
              {{ t('application.dashboard.active') }}
            </span>
          </div>
          <h2
            id="player-alliance-heading"
            class="ks-display mt-3 text-2xl font-semibold sm:text-3xl"
          >
            {{ membership.alliance.name }}
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--ks-text-muted)]">
            {{ t('application.dashboard.playerAllianceIntro') }}
          </p>
          <dl class="mt-5 flex flex-wrap gap-x-8 gap-y-4 text-sm">
            <div>
              <dt class="text-[var(--ks-text-muted)]">{{ t('application.dashboard.roles') }}</dt>
              <dd class="mt-1 font-semibold text-[var(--ks-text)]">
                {{ rolesFor(membership) }}
              </dd>
            </div>
            <div>
              <dt class="text-[var(--ks-text-muted)]">{{ t('application.dashboard.timezone') }}</dt>
              <dd class="mt-1 font-semibold text-[var(--ks-text)]">
                {{ membership.alliance.timezone }}
              </dd>
            </div>
          </dl>
        </div>

        <div class="grid gap-2 sm:grid-cols-2 lg:w-[26rem]">
          <Link class="ks-command-link" href="/alliance/roster">
            {{ t('application.dashboard.roster') }}
          </Link>
          <Link class="ks-command-link" href="/alliance/kingdom-alliances">
            {{ t('application.dashboard.kingdomAlliances') }}
          </Link>
          <Link class="ks-command-link" href="/alliance/transfers">
            {{ t('application.dashboard.transfers') }}
          </Link>
          <Link
            v-if="membership.canManageAlliance"
            class="ks-command-link border-[rgba(75,143,247,0.4)] bg-[var(--ks-blue-soft)] text-[var(--ks-blue-strong)]"
            href="/alliance/settings/kingdom"
          >
            {{ t('application.dashboard.kingdomSettings') }}
          </Link>
        </div>
      </div>
    </section>

    <section
      v-else
      class="ks-surface border-dashed p-6 sm:p-7"
      aria-labelledby="no-player-alliance-heading"
    >
      <p class="text-xs font-bold tracking-[0.18em] text-[var(--ks-blue-strong)] uppercase">
        {{ t('application.dashboard.playerAllianceTitle') }}
      </p>
      <h2 id="no-player-alliance-heading" class="ks-display mt-2 text-2xl font-semibold">
        {{ t('application.dashboard.noPlayerAllianceTitle') }}
      </h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-text-muted)]">
        {{ t('application.dashboard.noPlayerAllianceIntro') }}
      </p>
    </section>

    <div class="mt-10 grid gap-8 2xl:grid-cols-[minmax(0,1.35fr)_minmax(24rem,0.65fr)]">
      <section aria-labelledby="player-context-heading">
        <div>
          <h2 id="player-context-heading" class="ks-display text-2xl font-semibold">
            {{ t('application.dashboard.playerContextTitle') }}
          </h2>
          <p class="mt-2 text-sm text-[var(--ks-text-muted)]">
            {{ t('application.dashboard.playerContextIntro') }}
          </p>
        </div>

        <div v-if="activePlayer" class="ks-surface mt-5 p-5">
          <h3 class="text-lg font-semibold">{{ activePlayer.name }}</h3>
          <p v-if="activePlayer.gamePlayerId" class="mt-1 text-sm text-[var(--ks-text-muted)]">
            {{ activePlayer.gamePlayerId }}
          </p>
          <p v-if="activePlayer.kingdomNumber" class="mt-2 text-sm text-[var(--ks-text-secondary)]">
            {{ t('application.dashboard.playerKingdom', { kingdom: activePlayer.kingdomNumber }) }}
          </p>
          <p class="mt-4 text-sm leading-6 text-[var(--ks-text-muted)]">
            {{ t('application.dashboard.playerAuthorityIntro') }}
          </p>
        </div>
        <p v-else class="ks-surface mt-5 p-5 text-sm text-[var(--ks-text-muted)]">
          {{ t('application.dashboard.selectPlayer') }}
        </p>
      </section>

      <section
        v-if="canCreateAlliance"
        class="ks-surface-gold p-6 sm:p-7"
        aria-labelledby="create-alliance-heading"
      >
        <h2 id="create-alliance-heading" class="ks-display text-2xl font-semibold">
          {{ t('application.dashboard.createTitle') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('application.dashboard.createIntro') }}
        </p>

        <form class="mt-6 grid gap-5" @submit.prevent="createAlliance">
          <div>
            <label class="block text-sm font-medium" for="alliance-name">
              {{ t('application.dashboard.allianceName') }}
            </label>
            <input
              id="alliance-name"
              v-model="allianceForm.name"
              class="ks-input mt-2"
              required
              type="text"
              @blur="slugifyName"
            />
            <p v-if="allianceForm.errors.name" class="mt-1.5 text-sm text-[var(--ks-red)]">
              {{ allianceForm.errors.name }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium" for="alliance-slug">
              {{ t('application.dashboard.slug') }}
            </label>
            <input
              id="alliance-slug"
              v-model="allianceForm.slug"
              class="ks-input mt-2"
              pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
              required
              type="text"
            />
            <p v-if="allianceForm.errors.slug" class="mt-1.5 text-sm text-[var(--ks-red)]">
              {{ allianceForm.errors.slug }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium" for="alliance-timezone">
              {{ t('application.dashboard.timezone') }}
            </label>
            <input
              id="alliance-timezone"
              v-model="allianceForm.timezone"
              class="ks-input mt-2"
              required
              type="text"
            />
            <p v-if="allianceForm.errors.timezone" class="mt-1.5 text-sm text-[var(--ks-red)]">
              {{ allianceForm.errors.timezone }}
            </p>
          </div>

          <button
            class="mt-1 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-strong)] bg-[var(--ks-gold)] px-4 py-2.5 font-bold text-slate-950 transition hover:bg-[var(--ks-gold-strong)] disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="allianceForm.processing"
            type="submit"
          >
            {{ t('application.dashboard.create') }}
          </button>
        </form>
      </section>
    </div>
  </AppLayout>
</template>
