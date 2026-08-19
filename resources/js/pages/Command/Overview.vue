<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useContextForm } from '@/composables/useContextForm';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type RoleSummary = { key: string; name: string };
type MembershipSummary = {
  id: string;
  alliance: { id: string; name: string; slug: string; timezone: string };
  rank: string;
  roles: RoleSummary[];
  canManageAlliance: boolean;
};

defineProps<{
  user: { id: number; name: string; email: string; emailVerified: boolean; timezone: string };
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
const allianceForm = useContextForm({
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
  allianceForm.post('/alliances', { onSuccess: () => allianceForm.reset('name', 'slug') });
}
function rolesFor(membership: MembershipSummary): string {
  return [membership.rank.toUpperCase(), ...membership.roles.map((role) => role.name)].join(' · ');
}
</script>

<template>
  <Head :title="t('application.dashboard.title')" />

  <AppLayout>
    <RoomBanner
      :eyebrow="t('application.dashboard.eyebrow')"
      :title="membership?.alliance.name ?? t('application.dashboard.title')"
      :subtitle="
        membership
          ? t('application.dashboard.playerAllianceIntro')
          : t('application.dashboard.noPlayerAllianceIntro')
      "
      image="/images/kingshot/v4/command-overview.svg"
    >
      <template #actions>
        <Link v-if="membership" href="/alliance" class="ks-command-link">
          {{ t('application.dashboard.openPlayerAlliance') }}
        </Link>
        <Link href="/events" class="ks-command-link" data-variant="secondary">
          {{ t('navigation.events') }}
        </Link>
      </template>
    </RoomBanner>

    <section v-if="membership" class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
      <StatSeal :label="t('navigation.alliance')" :value="membership.alliance.name" icon="♜" />
      <StatSeal
        :label="t('application.dashboard.roles')"
        :value="membership.rank.toUpperCase()"
        icon="♛"
        tone="teal"
      />
      <StatSeal
        :label="t('application.dashboard.roles')"
        :value="membership.roles.length"
        icon="⚑"
        tone="stone"
      />
      <StatSeal
        :label="t('navigation.kingdom')"
        :value="activePlayer?.kingdomNumber ? `K${activePlayer.kingdomNumber}` : '—'"
        icon="♚"
      />
    </section>

    <div v-if="membership" class="mt-5 grid gap-5 2xl:grid-cols-[1.35fr_.65fr]">
      <div class="space-y-5">
        <section class="ks-surface-gold p-5 sm:p-6" aria-labelledby="alliance-command-heading">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl">
              <p class="ks-kicker">{{ t('common.playerAlliance') }}</p>
              <h2 id="alliance-command-heading" class="ks-display mt-2 text-3xl font-semibold">
                {{ membership.alliance.name }}
              </h2>
              <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
                {{ t('application.dashboard.playerAuthorityIntro') }}
              </p>
            </div>
            <span class="ks-status" data-tone="success">{{ t('common.active') }}</span>
          </div>

          <dl class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <dt class="ks-kicker">{{ t('application.dashboard.roles') }}</dt>
              <dd class="mt-2 text-sm font-semibold text-[var(--ks-ivory)]">
                {{ rolesFor(membership) }}
              </dd>
            </div>
            <div
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <dt class="ks-kicker">{{ t('application.dashboard.timezone') }}</dt>
              <dd class="mt-2 text-sm font-semibold text-[var(--ks-ivory)]">
                {{ membership.alliance.timezone }}
              </dd>
            </div>
            <div
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <dt class="ks-kicker">{{ t('application.dashboard.playerContextTitle') }}</dt>
              <dd class="mt-2 text-sm font-semibold text-[var(--ks-ivory)]">
                {{ activePlayer?.name ?? '—' }}
              </dd>
            </div>
            <div
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <dt class="ks-kicker">{{ t('navigation.kingdom') }}</dt>
              <dd class="mt-2 text-sm font-semibold text-[var(--ks-teal-bright)]">
                {{ activePlayer?.kingdomNumber ? `K${activePlayer.kingdomNumber}` : '—' }}
              </dd>
            </div>
          </dl>
        </section>

        <section class="ks-surface p-5 sm:p-6" aria-labelledby="command-actions-heading">
          <div
            class="flex flex-wrap items-end justify-between gap-3 border-b border-[var(--ks-border)] pb-4"
          >
            <div>
              <p class="ks-kicker">{{ t('navigation.allianceOperations') }}</p>
              <h2 id="command-actions-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{ t('application.dashboard.title') }}
              </h2>
            </div>
            <p class="max-w-xl text-sm text-[var(--ks-muted)]">
              {{ t('application.dashboard.playerAllianceIntro') }}
            </p>
          </div>

          <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
              href="/events"
              class="group rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 transition hover:border-[var(--ks-border-strong)] hover:bg-white/[0.025]"
            >
              <div class="text-2xl text-[var(--ks-gold-bright)]">⚔</div>
              <h3 class="ks-display mt-3 text-lg">{{ t('navigation.events') }}</h3>
            </Link>
            <Link
              href="/alliance/roster"
              class="group rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 transition hover:border-[var(--ks-border-strong)] hover:bg-white/[0.025]"
            >
              <div class="text-2xl text-[var(--ks-gold-bright)]">♟</div>
              <h3 class="ks-display mt-3 text-lg">{{ t('navigation.roster') }}</h3>
            </Link>
            <Link
              href="/alliance/kingdom-alliances"
              class="group rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 transition hover:border-[var(--ks-border-strong)] hover:bg-white/[0.025]"
            >
              <div class="text-2xl text-[var(--ks-gold-bright)]">◈</div>
              <h3 class="ks-display mt-3 text-lg">{{ t('navigation.kingdom') }}</h3>
            </Link>
            <Link
              href="/alliance/contributions"
              class="group rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 transition hover:border-[var(--ks-border-strong)] hover:bg-white/[0.025]"
            >
              <div class="text-2xl text-[var(--ks-gold-bright)]">✦</div>
              <h3 class="ks-display mt-3 text-lg">{{ t('navigation.contributions') }}</h3>
            </Link>
            <Link
              href="/alliance/content"
              class="group rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 transition hover:border-[var(--ks-border-strong)] hover:bg-white/[0.025]"
            >
              <div class="text-2xl text-[var(--ks-gold-bright)]">▤</div>
              <h3 class="ks-display mt-3 text-lg">{{ t('navigation.content') }}</h3>
            </Link>
            <Link
              href="/alliance/transfers"
              class="group rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 transition hover:border-[var(--ks-border-strong)] hover:bg-white/[0.025]"
            >
              <div class="text-2xl text-[var(--ks-gold-bright)]">✧</div>
              <h3 class="ks-display mt-3 text-lg">{{ t('navigation.transfers') }}</h3>
            </Link>
          </div>
        </section>
      </div>

      <aside class="space-y-5">
        <section class="ks-surface p-5" aria-labelledby="active-governor-heading">
          <p class="ks-kicker">{{ t('common.currentPlayer') }}</p>
          <div class="mt-4 flex items-center gap-4">
            <div
              class="grid h-16 w-16 shrink-0 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-[radial-gradient(circle_at_35%_28%,#5a4c38,#16201e_66%)] text-2xl font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
            >
              {{ activePlayer?.name?.slice(0, 1).toUpperCase() ?? '—' }}
            </div>
            <div class="min-w-0">
              <h2 id="active-governor-heading" class="ks-display truncate text-2xl font-semibold">
                {{ activePlayer?.name ?? t('common.noPlayers') }}
              </h2>
              <p
                v-if="activePlayer?.gamePlayerId"
                class="mt-1 truncate text-xs text-[var(--ks-muted)]"
              >
                {{ activePlayer.gamePlayerId }}
              </p>
              <p
                v-if="activePlayer?.kingdomNumber"
                class="mt-1 text-sm text-[var(--ks-teal-bright)]"
              >
                {{
                  t('application.dashboard.playerKingdom', { kingdom: activePlayer.kingdomNumber })
                }}
              </p>
            </div>
          </div>
          <div class="ks-divider my-5" />
          <p class="text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{ t('application.dashboard.playerContextIntro') }}
          </p>
        </section>

        <section class="ks-surface p-5">
          <p class="ks-kicker">{{ t('navigation.profile') }}</p>
          <h2 class="ks-display mt-2 text-xl font-semibold">{{ user.name }}</h2>
          <p class="mt-1 truncate text-xs text-[var(--ks-muted)]">{{ user.email }}</p>
          <Link href="/profile" class="ks-command-link mt-5 w-full" data-variant="secondary">{{
            t('navigation.profile')
          }}</Link>
        </section>
      </aside>
    </div>

    <div v-else class="mt-5 grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
      <section class="ks-surface-gold p-6 sm:p-8" aria-labelledby="no-alliance-heading">
        <p class="ks-kicker">{{ t('application.dashboard.playerAllianceTitle') }}</p>
        <h2 id="no-alliance-heading" class="ks-display mt-2 text-3xl font-semibold">
          {{ t('application.dashboard.noPlayerAllianceTitle') }}
        </h2>
        <p class="mt-3 max-w-3xl text-sm leading-7 text-[var(--ks-text-secondary)]">
          {{ t('application.dashboard.noPlayerAllianceIntro') }}
        </p>
        <div
          v-if="activePlayer"
          class="mt-6 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
        >
          <p class="ks-kicker">{{ t('common.currentPlayer') }}</p>
          <p class="ks-display mt-2 text-xl">{{ activePlayer.name }}</p>
          <p v-if="activePlayer.kingdomNumber" class="mt-1 text-sm text-[var(--ks-teal-bright)]">
            {{ t('application.dashboard.playerKingdom', { kingdom: activePlayer.kingdomNumber }) }}
          </p>
        </div>
      </section>

      <section
        v-if="canCreateAlliance"
        class="ks-surface p-6"
        aria-labelledby="create-alliance-heading"
      >
        <p class="ks-kicker">{{ t('application.dashboard.createTitle') }}</p>
        <h2 id="create-alliance-heading" class="ks-display mt-2 text-2xl font-semibold">
          {{ t('application.dashboard.createTitle') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
          {{ t('application.dashboard.createIntro') }}
        </p>
        <form class="mt-6 grid gap-4" @submit.prevent="createAlliance">
          <div>
            <label for="alliance-name" class="text-sm font-semibold">{{
              t('application.dashboard.allianceName')
            }}</label>
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
            <label for="alliance-slug" class="text-sm font-semibold">{{
              t('application.dashboard.slug')
            }}</label>
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
            <label for="alliance-timezone" class="text-sm font-semibold">{{
              t('application.dashboard.timezone')
            }}</label>
            <input
              id="alliance-timezone"
              v-model="allianceForm.timezone"
              class="ks-input mt-2"
              required
              type="text"
            />
          </div>
          <AppButton type="submit" :disabled="allianceForm.processing">{{
            t('application.dashboard.create')
          }}</AppButton>
        </form>
      </section>
    </div>
  </AppLayout>
</template>
