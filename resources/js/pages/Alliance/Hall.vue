<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  user: { name: string; email: string };
  alliance: {
    id: string;
    name: string;
    slug: string;
    kingdom: string | null;
    language: string;
    timezone: string;
    publicUrl: string;
  };
  membership: {
    id: string;
    rank: string;
    roles: Array<{ key: string; name: string }>;
  };
  contentHub: {
    canManage: boolean;
    canManageEvents: boolean;
    canManageRecruitment: boolean;
    canManageIntegrations: boolean;
    notices: Array<{
      id: string;
      title: string;
      slug: string;
      summary: string | null;
      visibility: string;
      publishedAt: string | null;
    }>;
    upcomingActivities: Array<{
      id: string;
      title: string;
      startsAt: string;
      allianceTimezone: string;
    }>;
  };
  invitationManagement: {
    allowed: boolean;
    candidates: Array<{ id: string; name: string; gamePlayerId: string | null; claimed: boolean }>;
    invitations: Array<{
      id: string;
      player: { id: string; name: string; gamePlayerId: string | null };
      email: string;
      status: string;
      expiresAt: string | null;
      createdAt: string | null;
    }>;
    issuedLink: string | null;
  };
  membershipManagement: {
    allowed: boolean;
    rolesAllowed: boolean;
    rankAllowed: boolean;
    rankOptions: string[];
    members: Array<{
      id: string;
      player: { id: string; name: string; gamePlayerId: string | null; claimed: boolean };
      status: string;
      rank: string;
      roles: Array<{ id: string; key: string; name: string }>;
    }>;
    roleCatalog: Array<{ id: string; key: string; name: string }>;
    currentPlayerId: string;
    leadershipTransferAllowed: boolean;
  };
}>();

const { t, formatDate } = useLocale();
const inviteForm = useForm({
  player_id: props.invitationManagement.candidates[0]?.id ?? '',
  email: '',
});
const statusSelections = reactive<Record<string, string>>(
  Object.fromEntries(
    props.membershipManagement.members.map((member) => [member.id, member.status]),
  ),
);
const rankSelections = reactive<Record<string, string>>(
  Object.fromEntries(props.membershipManagement.members.map((member) => [member.id, member.rank])),
);
const roleSelections = reactive<Record<string, string>>({});

function sendInvitation(): void {
  inviteForm.post('/alliance/invitations', {
    preserveScroll: true,
    onSuccess: () => inviteForm.reset(),
  });
}

function resendInvitation(id: string): void {
  router.post(`/alliance/invitations/${id}/resend`, {}, { preserveScroll: true });
}

function revokeInvitation(id: string): void {
  router.delete(`/alliance/invitations/${id}`, { preserveScroll: true });
}

function updateMembershipStatus(id: string): void {
  router.patch(
    `/alliance/memberships/${id}/status`,
    { status: statusSelections[id] },
    { preserveScroll: true },
  );
}

function updateMembershipRank(id: string): void {
  router.patch(
    `/alliance/memberships/${id}/rank`,
    { rank: rankSelections[id] },
    { preserveScroll: true },
  );
}

function assignRole(membershipId: string): void {
  const roleId = roleSelections[membershipId];
  if (!roleId) return;

  router.put(
    `/alliance/memberships/${membershipId}/roles/${roleId}`,
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        roleSelections[membershipId] = '';
      },
    },
  );
}

function removeRole(membershipId: string, roleId: string): void {
  router.delete(`/alliance/memberships/${membershipId}/roles/${roleId}`, {
    preserveScroll: true,
  });
}

function transferLeadership(playerId: string, playerName: string): void {
  if (
    !window.confirm(
      t('allianceOperations.overview.transferLeadershipConfirm', { player: playerName }),
    )
  )
    return;

  router.post('/alliance/leadership/transfer', { player_id: playerId }, { preserveScroll: true });
}

function leaveAlliance(): void {
  if (
    !window.confirm(
      t('allianceOperations.overview.leaveConfirm', { alliance: props.alliance.name }),
    )
  ) {
    return;
  }

  router.delete('/alliance/membership');
}

function statusLabel(value: string): string {
  const key = `allianceOperations.status.${value === 'no_show' ? 'noShow' : value}`;
  const translated = t(key);
  return translated === key ? value.replaceAll('_', ' ') : translated;
}

function formatInZone(value: string, timeZone: string): string {
  return formatDate(value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    timeZone,
    timeZoneName: 'short',
  });
}
</script>

<template>
  <Head :title="alliance.name" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner eyebrow="Alliance Hall" :title="alliance.name" subtitle="Review members and invitations, guide your officers, and keep your Alliance ready for the next call to arms." image="/images/kingshot/alliance-hall.svg">
      <template #actions><a :href="alliance.publicUrl" target="_blank" rel="noopener noreferrer" class="ks-command-link">View Alliance Banner</a><Link v-if="contentHub.canManageRecruitment" href="/alliance/recruitment" class="ks-command-link">Recruitment Hall</Link></template>
    </RoomBanner>
    <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <StatSeal label="Members" :value="membershipManagement.members.length" icon="♟" />
      <StatSeal label="Your Rank" :value="membership.rank.toUpperCase()" icon="♛" tone="teal" />
      <StatSeal label="Your Offices" :value="membership.roles.length" icon="⚑" tone="stone" />
      <StatSeal label="Invitations" :value="invitationManagement.invitations.length" icon="✉" />
    </section>

    <section class="ks-surface-gold mt-7 p-6 sm:p-7" aria-labelledby="alliance-context-heading">
      <h2 id="alliance-context-heading" class="sr-only">{{ alliance.name }}</h2>
      <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/45 p-4"
        >
          <dt class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
            {{ t('navigation.kingdom') }}
          </dt>
          <dd class="mt-2 font-semibold">
            {{ alliance.kingdom ?? t('allianceOperations.overview.notSet') }}
          </dd>
        </div>
        <div
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/45 p-4"
        >
          <dt class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
            {{ t('application.dashboard.timezone') }}
          </dt>
          <dd class="mt-2 font-semibold">{{ alliance.timezone }}</dd>
        </div>
        <div
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/45 p-4"
        >
          <dt class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
            {{ t('common.language') }}
          </dt>
          <dd class="mt-2 font-semibold">{{ alliance.language }}</dd>
        </div>
        <div
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/45 p-4"
        >
          <dt class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
            {{ t('allianceOperations.overview.yourRoles') }}
          </dt>
          <dd class="mt-2 font-semibold">
            {{
              [membership.rank.toUpperCase(), ...membership.roles.map((role) => role.name)].join(
                ' · ',
              )
            }}
          </dd>
        </div>
      </dl>

      <nav
        class="mt-6 grid gap-2 sm:grid-cols-2 lg:grid-cols-4"
        :aria-label="t('navigation.allianceOperations')"
      >
        <Link class="ks-command-link" href="/events">{{ t('navigation.events') }}</Link>
        <Link class="ks-command-link" href="/alliance/content">{{ t('navigation.content') }}</Link>
        <Link class="ks-command-link" href="/alliance/contributions">{{
          t('navigation.contributions')
        }}</Link>
        <Link
          v-if="contentHub.canManageRecruitment"
          class="ks-command-link"
          href="/alliance/recruitment"
        >
          {{ t('navigation.recruitment') }}
        </Link>
        <Link
          v-if="contentHub.canManageIntegrations"
          class="ks-command-link"
          href="/alliance/integrations"
        >
          {{ t('navigation.integrations') }}
        </Link>
        <Link v-if="contentHub.canManage" class="ks-command-link" href="/alliance/content/manage">
          {{ t('navigation.content') }}
        </Link>
        <Link v-if="contentHub.canManageEvents" class="ks-command-link" href="/events/create">
          {{ t('allianceOperations.events.coordinate') }}
        </Link>
      </nav>
    </section>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
      <section class="ks-surface p-6" aria-labelledby="notices-heading">
        <div class="flex items-center justify-between gap-4">
          <h2 id="notices-heading" class="ks-display text-xl font-semibold">
            {{ t('allianceOperations.overview.notices') }}
          </h2>
          <Link
            class="text-sm font-semibold text-[var(--ks-blue-strong)] hover:text-[var(--ks-ivory)]"
            href="/alliance/content"
          >
            {{ t('allianceOperations.overview.viewAll') }}
          </Link>
        </div>
        <div v-if="contentHub.notices.length" class="mt-5 space-y-3">
          <article
            v-for="notice in contentHub.notices"
            :key="notice.id"
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/35 p-4"
          >
            <span class="text-xs font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase">
              {{
                notice.visibility === 'members'
                  ? t('allianceOperations.overview.membersOnly')
                  : t('allianceOperations.overview.public')
              }}
            </span>
            <h3 class="mt-2 font-semibold">
              <Link
                class="hover:text-[var(--ks-blue-strong)]"
                :href="`/alliance/content/${notice.slug}`"
              >
                {{ notice.title }}
              </Link>
            </h3>
            <p v-if="notice.summary" class="mt-2 text-sm leading-6 text-[var(--ks-text-muted)]">
              {{ notice.summary }}
            </p>
          </article>
        </div>
        <p v-else class="mt-5 text-sm text-[var(--ks-text-muted)]">
          {{ t('allianceOperations.overview.noNotices') }}
        </p>
      </section>

      <section class="ks-surface p-6" aria-labelledby="upcoming-heading">
        <div class="flex items-center justify-between gap-4">
          <h2 id="upcoming-heading" class="ks-display text-xl font-semibold">
            {{ t('allianceOperations.overview.upcoming') }}
          </h2>
          <Link
            class="text-sm font-semibold text-[var(--ks-blue-strong)] hover:text-[var(--ks-ivory)]"
            href="/events"
          >
            {{ t('allianceOperations.overview.viewAll') }}
          </Link>
        </div>
        <div v-if="contentHub.upcomingActivities.length" class="mt-5 space-y-3">
          <article
            v-for="activity in contentHub.upcomingActivities"
            :key="activity.id"
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/35 p-4"
          >
            <h3 class="font-semibold">
              <Link class="hover:text-[var(--ks-blue-strong)]" :href="`/events/${activity.id}`">
                {{ activity.title }}
              </Link>
            </h3>
            <p class="mt-2 text-sm text-[var(--ks-text-muted)]">
              {{ formatInZone(activity.startsAt, activity.allianceTimezone) }}
            </p>
          </article>
        </div>
        <p v-else class="mt-5 text-sm text-[var(--ks-text-muted)]">
          {{ t('allianceOperations.overview.noUpcoming') }}
        </p>
      </section>
    </div>

    <section
      v-if="invitationManagement.allowed"
      class="ks-surface mt-8 p-6 sm:p-7"
      aria-labelledby="invitations-heading"
    >
      <h2 id="invitations-heading" class="ks-display text-2xl font-semibold">
        {{ t('allianceOperations.overview.invitations') }}
      </h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-text-muted)]">
        {{ t('allianceOperations.overview.invitationsIntro') }}
      </p>

      <div
        v-if="invitationManagement.issuedLink"
        class="mt-5 rounded-[var(--ks-radius-md)] border border-emerald-500/25 bg-emerald-500/10 p-4 text-sm text-emerald-100"
      >
        <p class="font-semibold">{{ t('allianceOperations.overview.newInvitationLink') }}</p>
        <a
          class="mt-1 block break-all underline"
          :href="invitationManagement.issuedLink"
          rel="noopener noreferrer"
          target="_blank"
          >{{ invitationManagement.issuedLink }}</a
        >
      </div>

      <form class="mt-6 flex flex-col gap-3 sm:flex-row" @submit.prevent="sendInvitation">
        <div class="flex-1">
          <label class="sr-only" for="invite-player">{{
            t('allianceOperations.overview.player')
          }}</label>
          <select id="invite-player" v-model="inviteForm.player_id" class="ks-input" required>
            <option
              v-for="candidate in invitationManagement.candidates"
              :key="candidate.id"
              :value="candidate.id"
            >
              {{ candidate.name }}{{ candidate.gamePlayerId ? ` · ${candidate.gamePlayerId}` : '' }}
            </option>
          </select>
          <p v-if="inviteForm.errors.player_id" class="mt-1.5 text-sm text-[var(--ks-red)]">
            {{ inviteForm.errors.player_id }}
          </p>
        </div>
        <div class="flex-1">
          <label class="sr-only" for="invite-email">{{ t('auth.login.email') }}</label>
          <input
            id="invite-email"
            v-model="inviteForm.email"
            class="ks-input"
            placeholder="member@example.com"
            required
            type="email"
          />
          <p v-if="inviteForm.errors.email" class="mt-1.5 text-sm text-[var(--ks-red)]">
            {{ inviteForm.errors.email }}
          </p>
        </div>
        <button
          class="min-h-11 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2.5 font-semibold text-[var(--ks-ivory)] transition hover:bg-[var(--ks-blue-strong)] disabled:opacity-60"
          :disabled="inviteForm.processing"
          type="submit"
        >
          {{ t('allianceOperations.overview.sendInvitation') }}
        </button>
      </form>

      <div class="mt-7 overflow-x-auto">
        <table class="min-w-full text-start text-sm">
          <thead class="border-b border-[var(--ks-border)] text-[var(--ks-text-muted)]">
            <tr>
              <th class="px-3 py-3 text-start font-medium">
                {{ t('allianceOperations.overview.player') }}
              </th>
              <th class="px-3 py-3 text-start font-medium">{{ t('auth.login.email') }}</th>
              <th class="px-3 py-3 text-start font-medium">
                {{ t('allianceOperations.overview.status') }}
              </th>
              <th class="px-3 py-3 text-start font-medium">
                {{ t('allianceOperations.overview.expires') }}
              </th>
              <th class="px-3 py-3 text-start font-medium">
                {{ t('allianceOperations.overview.actions') }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="invitation in invitationManagement.invitations" :key="invitation.id">
              <td class="px-3 py-4">
                <span class="font-semibold">{{ invitation.player.name }}</span>
                <span
                  v-if="invitation.player.gamePlayerId"
                  class="mt-1 block text-xs text-[var(--ks-text-muted)]"
                  >{{ invitation.player.gamePlayerId }}</span
                >
              </td>
              <td class="px-3 py-4">{{ invitation.email }}</td>
              <td class="px-3 py-4 text-[var(--ks-text-secondary)]">
                {{ statusLabel(invitation.status) }}
              </td>
              <td class="px-3 py-4 text-[var(--ks-text-muted)]">
                {{
                  invitation.expiresAt ? formatInZone(invitation.expiresAt, alliance.timezone) : '—'
                }}
              </td>
              <td class="px-3 py-4">
                <div
                  v-if="['pending', 'expired'].includes(invitation.status)"
                  class="flex flex-wrap gap-3"
                >
                  <button
                    class="font-semibold text-[var(--ks-blue-strong)] hover:text-[var(--ks-ivory)]"
                    type="button"
                    @click="resendInvitation(invitation.id)"
                  >
                    {{ t('allianceOperations.overview.resend') }}
                  </button>
                  <button
                    v-if="invitation.status === 'pending'"
                    class="font-semibold text-[var(--ks-red)] hover:text-red-200"
                    type="button"
                    @click="revokeInvitation(invitation.id)"
                  >
                    {{ t('allianceOperations.overview.revoke') }}
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="invitationManagement.invitations.length === 0">
              <td class="px-3 py-5 text-[var(--ks-text-muted)]" colspan="5">
                {{ t('allianceOperations.overview.noInvitations') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section
      v-if="membershipManagement.allowed || membershipManagement.rolesAllowed"
      class="ks-surface mt-8 p-6 sm:p-7"
      aria-labelledby="membership-admin-heading"
    >
      <h2 id="membership-admin-heading" class="ks-display text-2xl font-semibold">
        {{ t('allianceOperations.overview.membershipAdmin') }}
      </h2>

      <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <article
          v-for="member in membershipManagement.members"
          :key="member.id"
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/35 p-5"
        >
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <h3 class="font-semibold">
                {{ member.player.name }}
                <span
                  v-if="member.player.id === membershipManagement.currentPlayerId"
                  class="text-[var(--ks-text-muted)]"
                >
                  ({{ t('allianceOperations.overview.you') }})
                </span>
              </h3>
              <p class="mt-1 truncate text-sm text-[var(--ks-text-muted)]">
                {{ member.player.gamePlayerId ?? t('allianceOperations.overview.noGamePlayerId') }}
              </p>
              <p class="mt-2 text-xs font-semibold text-[var(--ks-blue-strong)]">
                {{ statusLabel(member.status) }}
              </p>
              <p class="mt-1 text-xs font-bold tracking-[0.14em] text-[var(--ks-gold)] uppercase">
                {{ member.rank.toUpperCase() }}
              </p>
            </div>

            <div
              v-if="
                membershipManagement.allowed &&
                member.player.id !== membershipManagement.currentPlayerId
              "
              class="flex flex-wrap gap-2"
            >
              <select
                v-model="statusSelections[member.id]"
                class="ks-input w-auto min-w-36 text-sm"
                :aria-label="`${t('allianceOperations.overview.status')} ${member.player.name}`"
              >
                <option value="active">{{ statusLabel('active') }}</option>
                <option value="suspended">{{ statusLabel('suspended') }}</option>
                <option value="removed">{{ statusLabel('removed') }}</option>
              </select>
              <button
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold hover:border-[var(--ks-border-strong)]"
                type="button"
                @click="updateMembershipStatus(member.id)"
              >
                {{ t('allianceOperations.overview.update') }}
              </button>
            </div>
          </div>

          <div
            v-if="
              membershipManagement.rankAllowed &&
              member.player.id !== membershipManagement.currentPlayerId &&
              member.rank !== 'r5'
            "
            class="mt-4 flex flex-wrap gap-2"
          >
            <select
              v-model="rankSelections[member.id]"
              class="ks-input w-auto min-w-28 text-sm"
              :aria-label="`${member.player.name} ${member.rank.toUpperCase()}`"
            >
              <option v-for="rank in membershipManagement.rankOptions" :key="rank" :value="rank">
                {{ rank.toUpperCase() }}
              </option>
            </select>
            <button
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold hover:border-[var(--ks-border-strong)]"
              type="button"
              @click="updateMembershipRank(member.id)"
            >
              {{ t('allianceOperations.overview.update') }}
            </button>
          </div>

          <div
            v-if="
              membershipManagement.leadershipTransferAllowed &&
              member.player.id !== membershipManagement.currentPlayerId &&
              member.status === 'active'
            "
            class="mt-4"
          >
            <button
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-gold)]/40 bg-[var(--ks-gold-soft)] px-3 py-2 text-sm font-semibold text-[var(--ks-gold-strong)]"
              type="button"
              @click="transferLeadership(member.player.id, member.player.name)"
            >
              {{ t('allianceOperations.overview.transferLeadership') }}
            </button>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <span
              v-for="role in member.roles"
              :key="role.id"
              class="inline-flex items-center gap-2 rounded-full border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-1.5 text-xs font-semibold"
            >
              {{ role.name }}
              <button
                v-if="
                  membershipManagement.rolesAllowed &&
                  member.player.id !== membershipManagement.currentPlayerId
                "
                class="text-[var(--ks-red)] hover:text-red-200"
                :aria-label="`${t('allianceOperations.overview.removeRole')} ${role.name}`"
                type="button"
                @click="removeRole(member.id, role.id)"
              >
                ×
              </button>
            </span>
          </div>

          <div v-if="membershipManagement.rolesAllowed" class="mt-4 flex flex-wrap gap-2">
            <select
              v-model="roleSelections[member.id]"
              class="ks-input min-w-48 flex-1 text-sm"
              :aria-label="`${t('allianceOperations.overview.selectRole')} ${member.player.name}`"
            >
              <option value="">{{ t('allianceOperations.overview.selectRole') }}</option>
              <option
                v-for="role in membershipManagement.roleCatalog"
                :key="role.id"
                :disabled="member.roles.some((assigned) => assigned.id === role.id)"
                :value="role.id"
              >
                {{ role.name }}
              </option>
            </select>
            <button
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-strong)] bg-[var(--ks-gold-soft)] px-3 py-2 text-sm font-semibold text-[var(--ks-gold-strong)] disabled:opacity-50"
              :disabled="!roleSelections[member.id]"
              type="button"
              @click="assignRole(member.id)"
            >
              {{ t('allianceOperations.overview.assign') }}
            </button>
          </div>
        </article>
      </div>
    </section>

    <section class="mt-8 rounded-[var(--ks-radius-lg)] border border-red-500/20 bg-red-500/5 p-5">
      <button
        class="font-semibold text-[var(--ks-red)] hover:text-red-200"
        type="button"
        @click="leaveAlliance"
      >
        {{ t('allianceOperations.overview.leaveAlliance') }}
      </button>
    </section>
  </AppLayout>
</template>
