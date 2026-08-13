<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

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

const { t } = useLocale();

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

function rolesFor(membership: MembershipSummary): string {
  return membership.roles.map((role) => role.name).join(', ') || t('application.dashboard.noRoles');
}
</script>

<template>
  <Head :title="t('application.dashboard.title')" />

  <AppLayout
    :user="user"
    :has-active-alliance="activeAllianceId !== null"
    :alliance-name="activeMembership?.alliance.name ?? null"
  >
    <header class="mb-8 grid gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
      <div>
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('application.dashboard.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('application.dashboard.welcome', { name: user.name }) }}
        </h1>
        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-[var(--ks-text-muted)]">
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
        v-if="activeAllianceId"
        class="w-fit rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-strong)] bg-[var(--ks-gold)] px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-[var(--ks-gold-strong)]"
        href="/alliance"
      >
        {{ t('application.dashboard.openActiveAlliance') }}
      </Link>
    </header>

    <section
      v-if="activeMembership"
      class="ks-surface-gold relative overflow-hidden p-6 sm:p-7"
      aria-labelledby="active-alliance-heading"
    >
      <div
        class="pointer-events-none absolute inset-y-0 end-0 w-1/2 bg-[radial-gradient(circle_at_70%_35%,rgba(212,175,55,0.12),transparent_65%)]"
        aria-hidden="true"
      />
      <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
        <div>
          <div class="flex flex-wrap items-center gap-3">
            <p class="text-xs font-bold tracking-[0.18em] text-[var(--ks-gold)] uppercase">
              {{ t('application.dashboard.activeAllianceTitle') }}
            </p>
            <span
              class="rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-300"
            >
              {{ t('application.dashboard.active') }}
            </span>
          </div>
          <h2 id="active-alliance-heading" class="ks-display mt-3 text-2xl font-semibold sm:text-3xl">
            {{ activeMembership.alliance.name }}
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--ks-text-muted)]">
            {{ t('application.dashboard.activeAllianceIntro') }}
          </p>
          <dl class="mt-5 flex flex-wrap gap-x-8 gap-y-4 text-sm">
            <div>
              <dt class="text-[var(--ks-text-muted)]">{{ t('application.dashboard.roles') }}</dt>
              <dd class="mt-1 font-semibold text-[var(--ks-text)]">{{ rolesFor(activeMembership) }}</dd>
            </div>
            <div>
              <dt class="text-[var(--ks-text-muted)]">{{ t('application.dashboard.timezone') }}</dt>
              <dd class="mt-1 font-semibold text-[var(--ks-text)]">
                {{ activeMembership.alliance.timezone }}
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
            v-if="activeMembership.canManageAlliance"
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
      aria-labelledby="choose-alliance-heading"
    >
      <p class="text-xs font-bold tracking-[0.18em] text-[var(--ks-blue-strong)] uppercase">
        {{ t('application.dashboard.activeAllianceTitle') }}
      </p>
      <h2 id="choose-alliance-heading" class="ks-display mt-2 text-2xl font-semibold">
        {{ t('application.dashboard.noActiveAllianceTitle') }}
      </h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-text-muted)]">
        {{ t('application.dashboard.noActiveAllianceIntro') }}
      </p>
    </section>

    <div class="mt-10 grid gap-8 2xl:grid-cols-[minmax(0,1.35fr)_minmax(24rem,0.65fr)]">
      <section aria-labelledby="alliances-heading">
        <div>
          <h2 id="alliances-heading" class="ks-display text-2xl font-semibold">
            {{ t('application.dashboard.alliancesTitle') }}
          </h2>
          <p class="mt-2 text-sm text-[var(--ks-text-muted)]">
            {{ t('application.dashboard.alliancesIntro') }}
          </p>
        </div>

        <div v-if="memberships.length" class="mt-5 grid gap-4 md:grid-cols-2">
          <article
            v-for="membership in memberships"
            :key="membership.id"
            class="ks-surface p-5 transition"
            :class="
              activeAllianceId === membership.alliance.id
                ? 'ring-1 ring-[var(--ks-border-strong)]'
                : 'hover:border-[var(--ks-border-strong)]'
            "
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <h3 class="truncate text-lg font-semibold">{{ membership.alliance.name }}</h3>
                <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
                  {{ membership.alliance.timezone }}
                </p>
              </div>
              <span
                v-if="activeAllianceId === membership.alliance.id"
                class="rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-300"
              >
                {{ t('application.dashboard.active') }}
              </span>
            </div>

            <div class="mt-5 border-t border-[var(--ks-border)] pt-4">
              <p class="text-xs font-bold tracking-[0.14em] text-[var(--ks-text-muted)] uppercase">
                {{ t('application.dashboard.roles') }}
              </p>
              <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
                {{ rolesFor(membership) }}
              </p>
            </div>

            <button
              v-if="activeAllianceId !== membership.alliance.id"
              class="mt-5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2.5 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-border-strong)] hover:bg-[var(--ks-surface-2)] hover:text-[var(--ks-text)]"
              type="button"
              @click="activateAlliance(membership.alliance.id)"
            >
              {{ t('application.dashboard.switchAlliance') }}
            </button>
          </article>
        </div>

        <p
          v-else
          class="mt-5 rounded-[var(--ks-radius-lg)] border border-dashed border-[var(--ks-border)] bg-[var(--ks-surface-1)]/60 p-5 text-sm leading-6 text-[var(--ks-text-muted)]"
        >
          {{ t('application.dashboard.empty') }}
        </p>
      </section>

      <section class="ks-surface-gold p-6 sm:p-7" aria-labelledby="create-alliance-heading">
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
            <label class="block text-sm font-medium" for="alliance-kingdom">
              {{ t('application.dashboard.kingdomNumber') }}
            </label>
            <input
              id="alliance-kingdom"
              v-model="allianceForm.kingdom"
              class="ks-input mt-2"
              inputmode="numeric"
              pattern="[1-9][0-9]*"
              type="text"
            />
            <p v-if="allianceForm.errors.kingdom" class="mt-1.5 text-sm text-[var(--ks-red)]">
              {{ allianceForm.errors.kingdom }}
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
