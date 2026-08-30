<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

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
  canManageRecruitment: boolean;
};
type DashboardOverview = {
  unreadNotifications: number;
  pendingGiftCodes: number;
  upcomingEvents: Array<{
    id: string;
    title: string | null;
    nameKey: string;
    scope: string;
    startsAt: string;
  }>;
  eventActions: Array<{
    occurrenceId: string;
    title: string | null;
    nameKey: string;
    action: string;
    startsAt: string;
  }>;
  giftCodes: Array<{
    id: string;
    code: string;
    status: string;
    expiresAt: string | null;
  }>;
  recruitment: { pending: number; overdue: number } | null;
  allianceCommand: {
    asOf: string;
    actionCount: number;
    items: Array<{
      code: string;
      owner: string;
      state: string;
      reasonKey: string;
      count: number;
      observedAt: string | null;
      actionable: boolean;
      affectedIds: string[];
      handoff: { href: string };
      metadata: Record<string, unknown>;
    }>;
  } | null;
  officerBriefs: Array<{
    group: string;
    state: string;
    count: number;
    owner: string;
    canonicalUrl: string;
    fingerprint: string;
    facts: Array<Record<string, unknown>>;
  }>;
  actionCount: number;
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
  overview: DashboardOverview | null;
  canCreateAlliance: boolean;
}>();

const { t, formatDate } = useLocale();
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
  allianceForm.post('/alliances', { onSuccess: () => allianceForm.reset('name', 'slug') });
}
function rolesFor(membership: MembershipSummary): string {
  return [membership.rank.toUpperCase(), ...membership.roles.map((role) => role.name)].join(' · ');
}
function eventActionLabel(action: string): string {
  const key = {
    response: 'application.dashboard.eventActionResponse',
    registration: 'application.dashboard.eventActionRegistration',
    vote: 'application.dashboard.eventActionVote',
    roster_confirmation: 'application.dashboard.eventActionRosterConfirmation',
  }[action];

  return key ? t(key) : t('application.dashboard.eventActionRequired');
}
</script>

<template>
  <Head :title="t('application.dashboard.title')" />

  <AppLayout
    :user="user"
    :has-player-alliance="membership !== null"
    :player-alliance-name="membership?.alliance.name ?? null"
  >
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

    <section v-if="overview" class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
      <StatSeal
        :label="t('application.dashboard.needsAttention')"
        :value="overview.actionCount"
        icon="!"
        tone="teal"
      />
      <StatSeal :label="t('navigation.events')" :value="overview.upcomingEvents.length" icon="⚔" />
      <StatSeal
        :label="t('navigation.giftCodes')"
        :value="overview.pendingGiftCodes"
        icon="✦"
        tone="stone"
      />
      <StatSeal
        :label="t('navigation.notifications')"
        :value="overview.unreadNotifications"
        icon="⌁"
      />
    </section>

    <section
      v-if="overview"
      class="ks-surface-gold mt-5 p-5 sm:p-6"
      aria-labelledby="dashboard-attention-heading"
    >
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p class="ks-kicker">{{ t('application.dashboard.governorBriefing') }}</p>
          <h2 id="dashboard-attention-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('application.dashboard.needsYourAttention') }}
          </h2>
        </div>
        <span class="ks-status" :data-tone="overview.actionCount ? 'warning' : 'success'">
          {{
            overview.actionCount
              ? t('application.dashboard.openActions', { count: overview.actionCount })
              : t('application.dashboard.caughtUp')
          }}
        </span>
      </div>

      <div v-if="overview.actionCount" class="mt-4 grid gap-3 md:grid-cols-2 2xl:grid-cols-4">
        <Link
          v-if="overview.unreadNotifications"
          href="/notifications"
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 transition hover:border-[var(--ks-border-strong)]"
        >
          <p class="ks-kicker">{{ t('navigation.notifications') }}</p>
          <strong class="mt-2 block text-lg">{{
            t('application.dashboard.unreadNotifications', { count: overview.unreadNotifications })
          }}</strong>
          <span class="mt-2 block text-xs text-[var(--ks-muted)]">{{
            t('application.dashboard.reviewNotifications')
          }}</span>
        </Link>
        <Link
          v-if="overview.pendingGiftCodes"
          href="/gift-codes"
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 transition hover:border-[var(--ks-border-strong)]"
        >
          <p class="ks-kicker">{{ t('navigation.giftCodes') }}</p>
          <strong class="mt-2 block text-lg">{{
            t('application.dashboard.giftCodesToRedeem', { count: overview.pendingGiftCodes })
          }}</strong>
          <span
            v-if="overview.giftCodes[0]"
            class="mt-2 block truncate font-mono text-xs text-[var(--ks-muted)]"
          >
            {{ overview.giftCodes[0].code }}
          </span>
        </Link>
        <Link
          v-for="action in overview.eventActions"
          :key="`${action.occurrenceId}:${action.action}`"
          :href="`/events/${action.occurrenceId}`"
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 transition hover:border-[var(--ks-border-strong)]"
        >
          <p class="ks-kicker">{{ t('navigation.events') }}</p>
          <strong class="mt-2 block truncate">{{ action.title || t(action.nameKey) }}</strong>
          <span class="mt-2 block text-xs text-[var(--ks-muted)]">{{
            eventActionLabel(action.action)
          }}</span>
        </Link>
        <Link
          v-if="overview.recruitment?.overdue"
          href="/alliance/recruitment"
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 transition hover:border-[var(--ks-border-strong)]"
        >
          <p class="ks-kicker">{{ t('navigation.recruitment') }}</p>
          <strong class="mt-2 block text-lg">{{
            t('application.dashboard.overdueRecruitment', { count: overview.recruitment.overdue })
          }}</strong>
          <span class="mt-2 block text-xs text-[var(--ks-muted)]">{{
            t('application.dashboard.recruitmentFollowUpDue')
          }}</span>
        </Link>
      </div>
      <div v-else class="ks-fantasy-empty mt-4">
        {{ t('application.dashboard.noUrgentActions') }}
      </div>
    </section>

    <div v-if="membership" class="mt-5 grid gap-5 2xl:grid-cols-[1.35fr_.65fr]">
      <div class="space-y-5">
        <section
          v-if="overview?.allianceCommand"
          class="ks-surface-gold p-5 sm:p-6"
          aria-labelledby="alliance-command-heading"
        >
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-0">
              <p class="ks-kicker">{{ t('application.dashboard.allianceCommandEyebrow') }}</p>
              <h2 id="alliance-command-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{ t('application.dashboard.allianceCommand') }}
              </h2>
              <p class="mt-2 max-w-3xl text-sm text-[var(--ks-text-secondary)]">
                {{ t('application.dashboard.allianceCommandHelp') }}
              </p>
            </div>
            <span
              class="ks-status"
              :data-tone="overview.allianceCommand.actionCount ? 'warning' : 'success'"
            >
              {{
                overview.allianceCommand.actionCount
                  ? t('application.dashboard.commandActionCount', {
                      count: overview.allianceCommand.actionCount,
                    })
                  : t('application.dashboard.commandClear')
              }}
            </span>
          </div>

          <div v-if="overview.allianceCommand.items.length" class="mt-5 grid gap-3 md:grid-cols-2">
            <Link
              v-for="item in overview.allianceCommand.items"
              :key="item.code"
              :href="item.handoff.href"
              class="min-w-0 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 transition hover:border-[var(--ks-border-strong)]"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="ks-kicker break-words">
                    {{ t(`application.dashboard.commandOwners.${item.owner}`) }}
                  </p>
                  <strong class="mt-2 block break-words text-base text-[var(--ks-ivory)]">
                    {{ t(item.reasonKey, { count: item.count }) }}
                  </strong>
                </div>
                <span
                  class="ks-status shrink-0"
                  :data-tone="item.actionable ? 'warning' : 'success'"
                >
                  {{
                    item.count
                      ? t('application.dashboard.commandItemCount', { count: item.count })
                      : t(`application.dashboard.commandStates.${item.state}`)
                  }}
                </span>
              </div>
              <span v-if="item.observedAt" class="mt-3 block text-xs text-[var(--ks-muted)]">
                {{
                  t('application.dashboard.commandObservedAt', {
                    date: formatDate(item.observedAt),
                  })
                }}
              </span>
            </Link>
          </div>
          <div v-else class="ks-fantasy-empty mt-4">
            {{ t('application.dashboard.commandNoAuthorizedItems') }}
          </div>

          <div
            v-if="overview.officerBriefs.length"
            class="mt-5 border-t border-[var(--ks-border)] pt-5"
          >
            <div class="flex flex-wrap items-end justify-between gap-3">
              <div>
                <p class="ks-kicker">{{ t('application.dashboard.officerBriefsEyebrow') }}</p>
                <h3 class="ks-display mt-1 text-xl font-semibold">
                  {{ t('application.dashboard.officerBriefs') }}
                </h3>
              </div>
              <span class="text-xs text-[var(--ks-muted)]">
                {{ t('application.dashboard.officerBriefsDeliveryHelp') }}
              </span>
            </div>
            <div class="mt-3 grid gap-3 lg:grid-cols-3">
              <Link
                v-for="brief in overview.officerBriefs"
                :key="brief.group"
                :href="brief.canonicalUrl"
                class="min-w-0 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 transition hover:border-[var(--ks-border-strong)]"
              >
                <p class="ks-kicker break-words">
                  {{ t(`application.dashboard.officerBriefGroups.${brief.group}`) }}
                </p>
                <strong class="mt-2 block text-sm text-[var(--ks-ivory)]">
                  {{ t(`application.dashboard.commandStates.${brief.state}`) }}
                </strong>
                <span class="mt-2 block text-xs text-[var(--ks-muted)]">
                  {{
                    t('application.dashboard.officerBriefFactCount', { count: brief.facts.length })
                  }}
                </span>
              </Link>
            </div>
          </div>
        </section>

        <section class="ks-surface-gold p-5 sm:p-6" aria-labelledby="alliance-overview-heading">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl">
              <p class="ks-kicker">{{ t('common.playerAlliance') }}</p>
              <h2 id="alliance-overview-heading" class="ks-display mt-2 text-3xl font-semibold">
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

        <section class="ks-surface p-5 sm:p-6" aria-labelledby="dashboard-actions-heading">
          <div
            class="flex flex-wrap items-end justify-between gap-3 border-b border-[var(--ks-border)] pb-4"
          >
            <div>
              <p class="ks-kicker">{{ t('navigation.allianceOperations') }}</p>
              <h2 id="dashboard-actions-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{ t('application.dashboard.allianceShortcuts') }}
              </h2>
            </div>
            <p class="max-w-xl text-sm text-[var(--ks-muted)]">
              {{ t('application.dashboard.allianceShortcutsHelp') }}
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
        <section
          v-if="overview?.upcomingEvents.length"
          class="ks-surface p-5"
          aria-labelledby="upcoming-events-heading"
        >
          <div class="flex items-end justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('application.dashboard.nextOnCalendar') }}</p>
              <h2 id="upcoming-events-heading" class="ks-display mt-1 text-xl font-semibold">
                {{ t('application.dashboard.upcomingEvents') }}
              </h2>
            </div>
            <Link href="/events" class="ks-chip">{{ t('application.dashboard.viewAll') }}</Link>
          </div>
          <div class="mt-4 space-y-2">
            <Link
              v-for="event in overview.upcomingEvents"
              :key="event.id"
              :href="`/events/${event.id}`"
              class="block rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-3 transition hover:border-[var(--ks-border-strong)]"
            >
              <strong class="block truncate text-sm">{{ event.title || t(event.nameKey) }}</strong>
              <span class="mt-1 block text-xs text-[var(--ks-muted)]">
                {{ formatDate(event.startsAt) }} · {{ event.scope }}
              </span>
            </Link>
          </div>
        </section>

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
