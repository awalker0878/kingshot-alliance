<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

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
    invitations: Array<{
      id: string;
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
    members: Array<{
      id: string;
      user: { id: number; name: string; email: string };
      status: string;
      roles: Array<{ id: string; key: string; name: string }>;
    }>;
    roleCatalog: Array<{ id: string; key: string; name: string }>;
    currentUserId: number;
  };
}>();

const { t, formatDate } = useLocale();
const inviteForm = useForm({ email: '' });
const statusSelections = reactive<Record<string, string>>(
  Object.fromEntries(
    props.membershipManagement.members.map((member) => [member.id, member.status]),
  ),
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

function leaveAlliance(): void {
  if (!window.confirm(t('allianceOperations.overview.leaveConfirm', { alliance: props.alliance.name }))) {
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

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
      <div>
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('allianceOperations.overview.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">{{ alliance.name }}</h1>
        <p class="mt-2 text-sm text-[var(--ks-text-muted)]">
          {{ t('navigation.kingdom') }} {{ alliance.kingdom ?? t('allianceOperations.overview.notSet') }}
        </p>
      </div>
      <a
        class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-strong)] bg-[var(--ks-gold-soft)] px-4 py-2.5 text-sm font-semibold text-[var(--ks-gold-strong)] transition hover:bg-[rgba(226,180,77,0.18)]"
        :href="alliance.publicUrl"
        rel="noopener noreferrer"
        target="_blank"
      >
        {{ t('allianceOperations.overview.publicPage') }}
      </a>
    </header>

    <section class="ks-surface-gold mt-7 p-6 sm:p-7" aria-labelledby="alliance-context-heading">
      <h2 id="alliance-context-heading" class="sr-only">{{ alliance.name }}</h2>
      <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/45 p-4">
          <dt class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
            {{ t('navigation.kingdom') }}
          </dt>
          <dd class="mt-2 font-semibold">{{ alliance.kingdom ?? t('allianceOperations.overview.notSet') }}</dd>
        </div>
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/45 p-4">
          <dt class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
            {{ t('application.dashboard.timezone') }}
          </dt>
          <dd class="mt-2 font-semibold">{{ alliance.timezone }}</dd>
        </div>
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/45 p-4">
          <dt class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
            {{ t('common.language') }}
          </dt>
          <dd class="mt-2 font-semibold">{{ alliance.language }}</dd>
        </div>
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/45 p-4">
          <dt class="text-xs font-bold tracking-[0.12em] text-[var(--ks-text-muted)] uppercase">
            {{ t('allianceOperations.overview.yourRoles') }}
          </dt>
          <dd class="mt-2 font-semibold">
            {{ membership.roles.map((role) => role.name).join(', ') || t('application.dashboard.noRoles') }}
          </dd>
        </div>
      </dl>

      <nav class="mt-6 grid gap-2 sm:grid-cols-2 lg:grid-cols-4" :aria-label="t('navigation.allianceOperations')">
        <Link class="ks-command-link" href="/alliance/events">{{ t('navigation.events') }}</Link>
        <Link class="ks-command-link" href="/alliance/content">{{ t('navigation.content') }}</Link>
        <Link class="ks-command-link" href="/alliance/contributions">{{ t('navigation.contributions') }}</Link>
        <Link v-if="contentHub.canManageRecruitment" class="ks-command-link" href="/alliance/recruitment">
          {{ t('navigation.recruitment') }}
        </Link>
        <Link v-if="contentHub.canManageIntegrations" class="ks-command-link" href="/alliance/integrations">
          {{ t('navigation.integrations') }}
        </Link>
        <Link v-if="contentHub.canManage" class="ks-command-link" href="/alliance/content/manage">
          {{ t('publicAlliance.content.manage') }}
        </Link>
        <Link v-if="contentHub.canManageEvents" class="ks-command-link" href="/alliance/events/manage">
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
          <Link class="text-sm font-semibold text-[var(--ks-blue-strong)] hover:text-white" href="/alliance/content">
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
              {{ notice.visibility === 'members' ? t('allianceOperations.overview.membersOnly') : t('allianceOperations.overview.public') }}
            </span>
            <h3 class="mt-2 font-semibold">
              <Link class="hover:text-[var(--ks-blue-strong)]" :href="`/alliance/content/${notice.slug}`">
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
          <Link class="text-sm font-semibold text-[var(--ks-blue-strong)] hover:text-white" href="/alliance/events">
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
              <Link class="hover:text-[var(--ks-blue-strong)]" :href="`/alliance/events/${activity.id}`">
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
        >{{ invitationManagement.issuedLink }}</a>
      </div>

      <form class="mt-6 flex flex-col gap-3 sm:flex-row" @submit.prevent="sendInvitation">
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
          class="min-h-11 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2.5 font-semibold text-white transition hover:bg-[var(--ks-blue-strong)] disabled:opacity-60"
          :disabled="inviteForm.processing"
          type="submit"
        >{{ t('allianceOperations.overview.sendInvitation') }}</button>
      </form>

      <div class="mt-7 overflow-x-auto">
        <table class="min-w-full text-start text-sm">
          <thead class="border-b border-[var(--ks-border)] text-[var(--ks-text-muted)]">
            <tr>
              <th class="px-3 py-3 text-start font-medium">{{ t('auth.login.email') }}</th>
              <th class="px-3 py-3 text-start font-medium">{{ t('allianceOperations.overview.status') }}</th>
              <th class="px-3 py-3 text-start font-medium">{{ t('allianceOperations.overview.expires') }}</th>
              <th class="px-3 py-3 text-start font-medium">{{ t('allianceOperations.overview.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="invitation in invitationManagement.invitations" :key="invitation.id">
              <td class="px-3 py-4">{{ invitation.email }}</td>
              <td class="px-3 py-4 text-[var(--ks-text-secondary)]">{{ statusLabel(invitation.status) }}</td>
              <td class="px-3 py-4 text-[var(--ks-text-muted)]">
                {{ invitation.expiresAt ? formatInZone(invitation.expiresAt, alliance.timezone) : '—' }}
              </td>
              <td class="px-3 py-4">
                <div v-if="['pending', 'expired'].includes(invitation.status)" class="flex flex-wrap gap-3">
                  <button
                    class="font-semibold text-[var(--ks-blue-strong)] hover:text-white"
                    type="button"
                    @click="resendInvitation(invitation.id)"
                  >{{ t('allianceOperations.overview.resend') }}</button>
                  <button
                    v-if="invitation.status === 'pending'"
                    class="font-semibold text-[var(--ks-red)] hover:text-red-200"
                    type="button"
                    @click="revokeInvitation(invitation.id)"
                  >{{ t('allianceOperations.overview.revoke') }}</button>
                </div>
              </td>
            </tr>
            <tr v-if="invitationManagement.invitations.length === 0">
              <td class="px-3 py-5 text-[var(--ks-text-muted)]" colspan="4">
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
      <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-text-muted)]">
        {{ t('allianceOperations.overview.membershipIntro') }}
      </p>

      <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <article
          v-for="member in membershipManagement.members"
          :key="member.id"
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/35 p-5"
        >
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <h3 class="font-semibold">
                {{ member.user.name }}
                <span v-if="member.user.id === membershipManagement.currentUserId" class="text-[var(--ks-text-muted)]">
                  ({{ t('allianceOperations.overview.you') }})
                </span>
              </h3>
              <p class="mt-1 truncate text-sm text-[var(--ks-text-muted)]">{{ member.user.email }}</p>
              <p class="mt-2 text-xs font-semibold text-[var(--ks-blue-strong)]">{{ statusLabel(member.status) }}</p>
            </div>

            <div
              v-if="membershipManagement.allowed && member.user.id !== membershipManagement.currentUserId"
              class="flex flex-wrap gap-2"
            >
              <select
                v-model="statusSelections[member.id]"
                class="ks-input w-auto min-w-36 text-sm"
                :aria-label="`${t('allianceOperations.overview.status')} ${member.user.name}`"
              >
                <option value="active">{{ statusLabel('active') }}</option>
                <option value="suspended">{{ statusLabel('suspended') }}</option>
                <option value="removed">{{ statusLabel('removed') }}</option>
              </select>
              <button
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold hover:border-[var(--ks-border-strong)]"
                type="button"
                @click="updateMembershipStatus(member.id)"
              >{{ t('allianceOperations.overview.update') }}</button>
            </div>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <span
              v-for="role in member.roles"
              :key="role.id"
              class="inline-flex items-center gap-2 rounded-full border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-1.5 text-xs font-semibold"
            >
              {{ role.name }}
              <button
                v-if="membershipManagement.rolesAllowed && member.user.id !== membershipManagement.currentUserId"
                class="text-[var(--ks-red)] hover:text-red-200"
                :aria-label="`${t('allianceOperations.overview.removeRole')} ${role.name}`"
                type="button"
                @click="removeRole(member.id, role.id)"
              >×</button>
            </span>
          </div>

          <div v-if="membershipManagement.rolesAllowed" class="mt-4 flex flex-wrap gap-2">
            <select
              v-model="roleSelections[member.id]"
              class="ks-input min-w-48 flex-1 text-sm"
              :aria-label="`${t('allianceOperations.overview.selectRole')} ${member.user.name}`"
            >
              <option value="">{{ t('allianceOperations.overview.selectRole') }}</option>
              <option
                v-for="role in membershipManagement.roleCatalog"
                :key="role.id"
                :disabled="member.roles.some((assigned) => assigned.id === role.id)"
                :value="role.id"
              >{{ role.name }}</option>
            </select>
            <button
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-strong)] bg-[var(--ks-gold-soft)] px-3 py-2 text-sm font-semibold text-[var(--ks-gold-strong)] disabled:opacity-50"
              :disabled="!roleSelections[member.id]"
              type="button"
              @click="assignRole(member.id)"
            >{{ t('allianceOperations.overview.assign') }}</button>
          </div>
        </article>
      </div>
    </section>

    <section class="mt-8 rounded-[var(--ks-radius-lg)] border border-red-500/20 bg-red-500/5 p-5">
      <button class="font-semibold text-[var(--ks-red)] hover:text-red-200" type="button" @click="leaveAlliance">
        {{ t('allianceOperations.overview.leaveAlliance') }}
      </button>
    </section>
  </AppLayout>
</template>
