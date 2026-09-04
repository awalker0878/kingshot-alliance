<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import CursorPagination from '@/components/ui/CursorPagination.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type MembershipBulkPreview = {
  targetStatus: string;
  items: Array<{
    itemId: string;
    label: string;
    fromStatus: string | null;
    outcome: 'ready' | 'blocked' | 'skipped';
    code: string;
  }>;
  ready: number;
  blocked: number;
  readyItemIds: string[];
};

type MembershipBulkResult = {
  action: string;
  items: Array<{
    itemId: string;
    label: string;
    outcome: 'succeeded' | 'failed' | 'skipped';
    code: string;
  }>;
  succeeded: number;
  failed: number;
  skipped: number;
  failedItemIds: string[];
};

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
  membership: { id: string; rank: string; roles: Array<{ key: string; name: string }> };
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
  governance: {
    canManageSettings: boolean;
    canManageRoles: boolean;
    canManageMembers: boolean;
    canViewHistory: boolean;
    canManageRosterEvidence: boolean;
  };
  invitationManagement: {
    allowed: boolean;
    candidates: Array<{
      id: string;
      name: string;
      gamePlayerId: string | null;
      claimed: boolean;
    }>;
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
    memberPage: {
      items: Array<{
        id: string;
        player: { id: string; name: string; gamePlayerId: string | null; claimed: boolean };
        status: string;
        rank: string;
        roles: Array<{ id: string; key: string; name: string }>;
      }>;
      nextCursor: string | null;
      hasMore: boolean;
      pageSize: number;
      isFirstPage: boolean;
    };
    total: number;
    roleCatalog: Array<{ id: string; key: string; name: string }>;
    currentPlayerId: string;
    leadershipTransferAllowed: boolean;
  };
  membershipBulkPreview: MembershipBulkPreview | null;
  membershipBulkResult: MembershipBulkResult | null;
}>();

const { t, formatDate } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
const members = computed(() => props.membershipManagement.memberPage.items);
const inviteForm = useForm({
  player_id: props.invitationManagement.candidates[0]?.id ?? '',
  email: '',
});
const statusSelections = reactive<Record<string, string>>(
  Object.fromEntries(members.value.map((member) => [member.id, member.status])),
);
const rankSelections = reactive<Record<string, string>>(
  Object.fromEntries(members.value.map((member) => [member.id, member.rank])),
);
const roleSelections = reactive<Record<string, string>>({});
const selectedMemberIds = ref<string[]>(props.membershipBulkResult?.failedItemIds ?? []);
const bulkTargetStatus = ref<'active' | 'suspended' | 'removed'>('suspended');
const bulkBusy = ref(false);
const bulkConfirmationOpen = ref(false);
const allPageMembersSelected = computed(
  () =>
    members.value.length > 0 &&
    members.value.every((member) => selectedMemberIds.value.includes(member.id)),
);
const bulkPreviewMatchesSelection = computed(() => {
  const preview = props.membershipBulkPreview;
  if (!preview || preview.targetStatus !== bulkTargetStatus.value) return false;

  const selected = [...selectedMemberIds.value].sort();
  const previewed = preview.items.map((item) => item.itemId).sort();
  return (
    selected.length === previewed.length && selected.every((id, index) => id === previewed[index])
  );
});

watch(
  members,
  (rows) => {
    for (const member of rows) {
      statusSelections[member.id] ??= member.status;
      rankSelections[member.id] ??= member.rank;
    }
  },
  { immediate: true },
);

watch(
  () => props.membershipBulkResult,
  (result) => {
    if (result) selectedMemberIds.value = [...result.failedItemIds];
  },
);

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

function memberSelected(membershipId: string): boolean {
  return selectedMemberIds.value.includes(membershipId);
}

function setMemberSelected(membershipId: string, selected: boolean): void {
  if (!selected) {
    selectedMemberIds.value = selectedMemberIds.value.filter((id) => id !== membershipId);
    return;
  }

  selectedMemberIds.value = [...new Set([...selectedMemberIds.value, membershipId])].slice(0, 50);
}

function toggleMemberPageSelection(): void {
  if (allPageMembersSelected.value) {
    const pageIds = new Set(members.value.map((member) => member.id));
    selectedMemberIds.value = selectedMemberIds.value.filter((id) => !pageIds.has(id));
    return;
  }

  selectedMemberIds.value = [
    ...new Set([...selectedMemberIds.value, ...members.value.map((member) => member.id)]),
  ].slice(0, 50);
}

function previewBulkStatusChange(): void {
  if (selectedMemberIds.value.length === 0 || bulkBusy.value) return;

  bulkBusy.value = true;
  router.post(
    '/alliance/memberships/bulk-status/preview',
    {
      membership_ids: selectedMemberIds.value,
      status: bulkTargetStatus.value,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => (bulkBusy.value = false),
    },
  );
}

function commitBulkStatusChange(): void {
  if (!props.membershipBulkPreview || !bulkPreviewMatchesSelection.value || bulkBusy.value) return;

  bulkBusy.value = true;
  router.post(
    '/alliance/memberships/bulk-status',
    {
      membership_ids: props.membershipBulkPreview.items.map((item) => item.itemId),
      status: props.membershipBulkPreview.targetStatus,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        bulkBusy.value = false;
        bulkConfirmationOpen.value = false;
      },
    },
  );
}

function membershipBulkOutcomeLabel(code: string): string {
  return t(`allianceOperations.overview.bulkOutcome.${code}`);
}

function nextMemberPage(): void {
  const cursor = props.membershipManagement.memberPage.nextCursor;
  if (!cursor) return;

  router.get('/alliance', { member_cursor: cursor }, { preserveScroll: true, preserveState: true });
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
  requestConfirmation({
    id: 'alliance-leadership-transfer-confirmation',
    title: t('allianceOperations.overview.transferLeadership'),
    description: t('allianceOperations.overview.transferLeadershipConfirm', {
      player: playerName,
    }),
    confirmLabel: t('allianceOperations.overview.transferLeadership'),
    cancelLabel: t('common.cancel'),
    perform: (finish) =>
      router.post(
        '/alliance/leadership/transfer',
        { player_id: playerId },
        { preserveScroll: true, onFinish: finish },
      ),
  });
}

function leaveAlliance(): void {
  requestConfirmation({
    id: 'alliance-leave-confirmation',
    title: t('allianceOperations.overview.leaveAlliance'),
    description: t('allianceOperations.overview.leaveConfirm', { alliance: props.alliance.name }),
    confirmLabel: t('allianceOperations.overview.leaveAlliance'),
    cancelLabel: t('common.cancel'),
    perform: (finish) => router.delete('/alliance/membership', { onFinish: finish }),
  });
}

function statusLabel(value: string): string {
  const key = `allianceOperations.status.${value === 'no_show' ? 'noShow' : value}`;
  const translated = t(key);
  return translated === key ? value.replaceAll('_', ' ') : translated;
}

function statusTone(value: string): 'success' | 'warning' | 'danger' | 'info' {
  if (value === 'active' || value === 'accepted') return 'success';
  if (value === 'pending' || value === 'invited') return 'warning';
  if (value === 'revoked' || value === 'removed' || value === 'suspended') return 'danger';
  return 'info';
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
    <RoomBanner
      :eyebrow="t('allianceOperations.overview.eyebrow')"
      :title="t('navigation.alliance')"
      :subtitle="t('allianceOperations.overview.membershipIntro')"
      image="/images/kingshot/v4/alliance-hall.svg"
    >
      <template #actions>
        <a
          :href="alliance.publicUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="ks-command-link"
        >
          {{ t('allianceOperations.overview.publicPage') }}
        </a>
        <Link
          v-if="contentHub.canManageRecruitment"
          href="/alliance/recruitment"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('navigation.recruitment') }}
        </Link>
        <Link
          v-if="governance.canManageSettings"
          href="/alliance/settings"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('allianceExpansion.navSettings') }}
        </Link>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
      <StatSeal :label="t('navigation.roster')" :value="membershipManagement.total" icon="♟" />
      <StatSeal
        :label="t('application.dashboard.roles')"
        :value="membership.rank.toUpperCase()"
        icon="♛"
        tone="teal"
      />
      <StatSeal
        :label="t('allianceOperations.overview.yourRoles')"
        :value="membership.roles.length"
        icon="⚑"
        tone="stone"
      />
      <StatSeal
        :label="t('allianceOperations.overview.invitations')"
        :value="invitationManagement.invitations.length"
        icon="✉"
      />
    </section>

    <section class="ks-surface-gold mt-5 p-5" aria-labelledby="alliance-context-heading">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="ks-kicker">{{ t('common.playerAlliance') }}</p>
          <h2 id="alliance-context-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ alliance.name }}
          </h2>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link href="/events" class="ks-command-link" data-variant="secondary">
            {{ t('navigation.events') }}
          </Link>
          <Link href="/alliance/roster" class="ks-command-link" data-variant="secondary">
            {{ t('navigation.roster') }}
          </Link>
          <Link href="/alliance/kingdom-alliances" class="ks-command-link" data-variant="secondary">
            {{ t('navigation.kingdom') }}
          </Link>
          <Link
            v-if="governance.canManageRoles"
            href="/alliance/roles"
            class="ks-command-link"
            data-variant="secondary"
          >
            {{ t('allianceExpansion.navRoles') }}
          </Link>
          <Link
            v-if="governance.canManageMembers"
            href="/alliance/members/bulk"
            class="ks-command-link"
            data-variant="secondary"
          >
            {{ t('allianceExpansion.navBulk') }}
          </Link>
          <Link
            v-if="governance.canViewHistory"
            href="/alliance/history"
            class="ks-command-link"
            data-variant="secondary"
          >
            {{ t('allianceExpansion.navHistory') }}
          </Link>
          <Link
            v-if="governance.canManageRosterEvidence"
            href="/alliance/roster/evidence"
            class="ks-command-link"
            data-variant="secondary"
          >
            {{ t('allianceExpansion.navRosterEvidence') }}
          </Link>
          <Link
            v-if="governance.canManageRosterEvidence"
            href="/alliance/roster/reconciliation"
            class="ks-command-link"
            data-variant="secondary"
          >
            {{ t('allianceExpansion.navReconciliation') }}
          </Link>
        </div>
      </div>

      <dl class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <dt class="ks-kicker">{{ t('navigation.kingdom') }}</dt>
          <dd class="mt-2 font-semibold">
            {{ alliance.kingdom ?? t('allianceOperations.overview.notSet') }}
          </dd>
        </div>
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <dt class="ks-kicker">{{ t('application.dashboard.timezone') }}</dt>
          <dd class="mt-2 font-semibold">{{ alliance.timezone }}</dd>
        </div>
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <dt class="ks-kicker">{{ t('common.language') }}</dt>
          <dd class="mt-2 font-semibold">{{ alliance.language }}</dd>
        </div>
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <dt class="ks-kicker">{{ t('allianceOperations.overview.yourRoles') }}</dt>
          <dd class="mt-2 font-semibold">
            {{
              [membership.rank.toUpperCase(), ...membership.roles.map((role) => role.name)].join(
                ' · ',
              )
            }}
          </dd>
        </div>
      </dl>
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.45fr)_minmax(22rem,.55fr)]">
      <section class="ks-surface overflow-hidden" aria-labelledby="membership-heading">
        <div
          class="flex flex-wrap items-end justify-between gap-4 border-b border-[var(--ks-border)] p-5"
        >
          <div class="max-w-2xl">
            <p class="ks-kicker">{{ t('allianceOperations.overview.membershipAdmin') }}</p>
            <h2 id="membership-heading" class="ks-display mt-1 text-2xl font-semibold">
              {{ t('navigation.roster') }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
              {{ t('allianceOperations.overview.membershipIntro') }}
            </p>
          </div>
          <Link href="/alliance/roster" class="ks-command-link" data-variant="secondary">
            {{ t('navigation.roster') }}
          </Link>
        </div>

        <section
          v-if="membershipManagement.allowed && selectedMemberIds.length"
          class="border-b border-[var(--ks-border)] bg-[var(--ks-teal-soft)] p-5"
          :aria-label="t('allianceOperations.overview.bulkActions')"
        >
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[12rem] flex-1">
              <p class="text-sm font-semibold">
                {{
                  t('allianceOperations.overview.selectedMembers', {
                    count: selectedMemberIds.length,
                  })
                }}
              </p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('allianceOperations.overview.bulkPreviewHelp') }}
              </p>
            </div>
            <div>
              <label class="text-xs font-semibold" for="membership-bulk-status">
                {{ t('allianceOperations.overview.changeStatusTo') }}
              </label>
              <select
                id="membership-bulk-status"
                v-model="bulkTargetStatus"
                class="ks-input mt-1.5"
              >
                <option value="active">{{ statusLabel('active') }}</option>
                <option value="suspended">{{ statusLabel('suspended') }}</option>
                <option value="removed">{{ statusLabel('removed') }}</option>
              </select>
            </div>
            <AppButton
              :busy="bulkBusy"
              :busy-label="t('allianceOperations.overview.previewingBulkAction')"
              @click="previewBulkStatusChange"
            >
              {{ t('allianceOperations.overview.previewBulkAction') }}
            </AppButton>
          </div>
        </section>

        <section
          v-if="bulkPreviewMatchesSelection && membershipBulkPreview"
          class="border-b border-[var(--ks-border)] p-5"
          aria-labelledby="membership-bulk-preview-heading"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('allianceOperations.overview.bulkPreview') }}</p>
              <h3 id="membership-bulk-preview-heading" class="mt-1 text-lg font-semibold">
                {{
                  t('allianceOperations.overview.bulkPreviewSummary', {
                    ready: membershipBulkPreview.ready,
                    blocked: membershipBulkPreview.blocked,
                  })
                }}
              </h3>
            </div>
            <AppButton
              :disabled="membershipBulkPreview.ready === 0"
              :variant="bulkTargetStatus === 'removed' ? 'danger' : 'primary'"
              @click="bulkConfirmationOpen = true"
            >
              {{ t('allianceOperations.overview.confirmBulkAction') }}
            </AppButton>
          </div>
          <ul class="mt-4 grid gap-2 md:grid-cols-2">
            <li
              v-for="item in membershipBulkPreview.items"
              :key="item.itemId"
              class="flex items-center justify-between gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 px-3 py-2 text-sm"
            >
              <span class="truncate">{{ item.label }}</span>
              <span
                class="ks-status"
                :data-tone="
                  item.outcome === 'ready'
                    ? 'success'
                    : item.outcome === 'skipped'
                      ? 'warning'
                      : 'danger'
                "
              >
                {{ membershipBulkOutcomeLabel(item.code) }}
              </span>
            </li>
          </ul>
        </section>

        <section
          v-if="membershipBulkResult"
          class="border-b border-[var(--ks-border)] p-5"
          aria-labelledby="membership-bulk-result-heading"
        >
          <p class="ks-kicker">{{ t('allianceOperations.overview.bulkResult') }}</p>
          <h3 id="membership-bulk-result-heading" class="mt-1 text-lg font-semibold">
            {{
              t('allianceOperations.overview.bulkResultSummary', {
                succeeded: membershipBulkResult.succeeded,
                failed: membershipBulkResult.failed,
                skipped: membershipBulkResult.skipped,
              })
            }}
          </h3>
          <ul class="mt-4 grid gap-2 md:grid-cols-2">
            <li
              v-for="item in membershipBulkResult.items"
              :key="item.itemId"
              class="flex items-center justify-between gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 px-3 py-2 text-sm"
            >
              <span class="truncate">{{ item.label }}</span>
              <span
                class="ks-status"
                :data-tone="
                  item.outcome === 'succeeded'
                    ? 'success'
                    : item.outcome === 'skipped'
                      ? 'warning'
                      : 'danger'
                "
              >
                {{ membershipBulkOutcomeLabel(item.code) }}
              </span>
            </li>
          </ul>
          <p v-if="membershipBulkResult.failed" class="mt-3 text-xs text-[var(--ks-muted)]">
            {{ t('allianceOperations.overview.failedItemsSelected') }}
          </p>
        </section>

        <div class="lg:hidden">
          <article
            v-for="member in members"
            :key="member.id"
            class="border-b border-[var(--ks-border)] p-4 last:border-b-0"
          >
            <div class="flex items-start gap-3">
              <input
                v-if="membershipManagement.allowed"
                type="checkbox"
                :checked="memberSelected(member.id)"
                :aria-label="
                  t('allianceOperations.overview.selectMember', { member: member.player.name })
                "
                @change="setMemberSelected(member.id, ($event.target as HTMLInputElement).checked)"
              />
              <div
                class="grid h-11 w-11 shrink-0 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-black/25 font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
                aria-hidden="true"
              >
                {{ member.player.name.slice(0, 1).toUpperCase() }}
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="truncate font-semibold">{{ member.player.name }}</h3>
                  <span
                    v-if="member.player.id === membershipManagement.currentPlayerId"
                    class="ks-status"
                    data-tone="info"
                  >
                    {{ t('allianceOperations.overview.you') }}
                  </span>
                </div>
                <p class="mt-1 truncate text-xs text-[var(--ks-muted)]">
                  {{ member.player.gamePlayerId ?? '—' }}
                </p>
              </div>
              <span class="ks-status" :data-tone="statusTone(member.status)">
                {{ statusLabel(member.status) }}
              </span>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
              <div>
                <p class="ks-kicker">{{ t('application.dashboard.roles') }}</p>
                <p class="mt-2 text-sm">{{ member.rank.toUpperCase() }}</p>
              </div>
              <div>
                <p class="ks-kicker">{{ t('allianceOperations.overview.yourRoles') }}</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                  <span v-for="role in member.roles" :key="role.id" class="ks-chip">
                    {{ role.name }}
                  </span>
                  <span v-if="!member.roles.length" class="text-sm text-[var(--ks-muted)]">—</span>
                </div>
              </div>
            </div>
            <div v-if="membershipManagement.allowed" class="mt-4 grid gap-2 sm:grid-cols-2">
              <div>
                <select v-model="statusSelections[member.id]" class="ks-input">
                  <option value="active">{{ statusLabel('active') }}</option>
                  <option value="suspended">{{ statusLabel('suspended') }}</option>
                  <option value="removed">{{ statusLabel('removed') }}</option>
                </select>
                <AppButton
                  class="mt-2 w-full"
                  variant="ghost"
                  @click="updateMembershipStatus(member.id)"
                >
                  {{ t('allianceOperations.overview.update') }}
                </AppButton>
              </div>
              <div v-if="membershipManagement.rankAllowed">
                <select v-model="rankSelections[member.id]" class="ks-input">
                  <option
                    v-for="rank in membershipManagement.rankOptions"
                    :key="rank"
                    :value="rank"
                  >
                    {{ rank.toUpperCase() }}
                  </option>
                </select>
                <AppButton
                  class="mt-2 w-full"
                  variant="ghost"
                  @click="updateMembershipRank(member.id)"
                >
                  {{ t('allianceOperations.overview.update') }}
                </AppButton>
              </div>
            </div>
          </article>
        </div>

        <div class="hidden overflow-x-auto lg:block">
          <table class="w-full min-w-[68rem] text-start text-sm">
            <thead
              class="bg-black/20 text-[.66rem] font-extrabold tracking-[.1em] text-[var(--ks-muted)] uppercase"
            >
              <tr>
                <th v-if="membershipManagement.allowed" class="w-12 px-4 py-3 text-start">
                  <input
                    type="checkbox"
                    :checked="allPageMembersSelected"
                    :aria-label="t('allianceOperations.overview.selectPage')"
                    @change="toggleMemberPageSelection"
                  />
                </th>
                <th class="px-5 py-3 text-start">{{ t('navigation.roster') }}</th>
                <th class="px-4 py-3 text-start">{{ t('allianceOperations.overview.status') }}</th>
                <th class="px-4 py-3 text-start">{{ t('application.dashboard.roles') }}</th>
                <th class="px-4 py-3 text-start">
                  {{ t('allianceOperations.overview.yourRoles') }}
                </th>
                <th v-if="membershipManagement.allowed" class="px-4 py-3 text-end">
                  {{ t('allianceOperations.overview.actions') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ks-border)]">
              <tr
                v-for="member in members"
                :key="member.id"
                class="transition hover:bg-white/[0.018]"
              >
                <td v-if="membershipManagement.allowed" class="px-4 py-4">
                  <input
                    type="checkbox"
                    :checked="memberSelected(member.id)"
                    :aria-label="
                      t('allianceOperations.overview.selectMember', { member: member.player.name })
                    "
                    @change="
                      setMemberSelected(member.id, ($event.target as HTMLInputElement).checked)
                    "
                  />
                </td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div
                      class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-black/25 font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
                      aria-hidden="true"
                    >
                      {{ member.player.name.slice(0, 1).toUpperCase() }}
                    </div>
                    <div class="min-w-0">
                      <div class="flex items-center gap-2">
                        <strong class="truncate">{{ member.player.name }}</strong>
                        <span
                          v-if="member.player.id === membershipManagement.currentPlayerId"
                          class="text-[.65rem] text-[var(--ks-teal-bright)]"
                        >
                          {{ t('allianceOperations.overview.you') }}
                        </span>
                      </div>
                      <p class="mt-1 truncate text-xs text-[var(--ks-muted)]">
                        {{ member.player.gamePlayerId ?? '—' }}
                      </p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-4">
                  <span class="ks-status" :data-tone="statusTone(member.status)">
                    {{ statusLabel(member.status) }}
                  </span>
                </td>
                <td class="px-4 py-4">
                  <span class="font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]">
                    {{ member.rank.toUpperCase() }}
                  </span>
                </td>
                <td class="px-4 py-4">
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="role in member.roles" :key="role.id" class="ks-chip">
                      {{ role.name }}
                    </span>
                    <span v-if="!member.roles.length" class="text-[var(--ks-muted)]">—</span>
                  </div>
                </td>
                <td v-if="membershipManagement.allowed" class="px-4 py-4 text-end">
                  <details class="relative inline-block text-start">
                    <summary
                      class="grid h-9 w-9 cursor-pointer list-none place-items-center rounded border border-[var(--ks-border)] text-[var(--ks-gold-bright)]"
                    >
                      •••
                    </summary>
                    <div
                      class="absolute end-0 z-20 mt-2 w-80 rounded-[var(--ks-radius-md)] border border-[var(--ks-border-strong)] bg-[rgba(6,12,12,.99)] p-3 shadow-2xl"
                    >
                      <div class="grid grid-cols-[1fr_auto] gap-2">
                        <select v-model="statusSelections[member.id]" class="ks-input">
                          <option value="active">{{ statusLabel('active') }}</option>
                          <option value="suspended">{{ statusLabel('suspended') }}</option>
                          <option value="removed">{{ statusLabel('removed') }}</option>
                        </select>
                        <AppButton variant="ghost" @click="updateMembershipStatus(member.id)">
                          {{ t('allianceOperations.overview.update') }}
                        </AppButton>
                      </div>

                      <div
                        v-if="membershipManagement.rankAllowed"
                        class="mt-2 grid grid-cols-[1fr_auto] gap-2"
                      >
                        <select v-model="rankSelections[member.id]" class="ks-input">
                          <option
                            v-for="rank in membershipManagement.rankOptions"
                            :key="rank"
                            :value="rank"
                          >
                            {{ rank.toUpperCase() }}
                          </option>
                        </select>
                        <AppButton variant="ghost" @click="updateMembershipRank(member.id)">
                          {{ t('allianceOperations.overview.update') }}
                        </AppButton>
                      </div>

                      <div
                        v-if="membershipManagement.rolesAllowed"
                        class="mt-2 grid grid-cols-[1fr_auto] gap-2"
                      >
                        <select v-model="roleSelections[member.id]" class="ks-input">
                          <option value="">
                            {{ t('allianceOperations.overview.selectRole') }}
                          </option>
                          <option
                            v-for="role in membershipManagement.roleCatalog"
                            :key="role.id"
                            :value="role.id"
                          >
                            {{ role.name }}
                          </option>
                        </select>
                        <AppButton variant="ghost" @click="assignRole(member.id)">
                          {{ t('allianceOperations.overview.assign') }}
                        </AppButton>
                      </div>

                      <div
                        v-if="membershipManagement.rolesAllowed && member.roles.length"
                        class="mt-2 space-y-1"
                      >
                        <button
                          v-for="role in member.roles"
                          :key="role.id"
                          type="button"
                          class="w-full rounded border border-[var(--ks-border)] px-3 py-2 text-start text-xs text-[var(--ks-text-secondary)] hover:border-red-400/30 hover:text-red-200"
                          @click="removeRole(member.id, role.id)"
                        >
                          {{ t('allianceOperations.overview.removeRole') }} · {{ role.name }}
                        </button>
                      </div>

                      <button
                        v-if="
                          membershipManagement.leadershipTransferAllowed &&
                          member.player.id !== membershipManagement.currentPlayerId
                        "
                        type="button"
                        class="mt-2 w-full rounded border border-[var(--ks-gold-dark)] px-3 py-2 text-start text-xs text-[var(--ks-gold-bright)]"
                        @click="transferLeadership(member.player.id, member.player.name)"
                      >
                        {{ t('allianceOperations.overview.transferLeadership') }}
                      </button>
                    </div>
                  </details>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <CursorPagination
          v-if="members.length"
          :summary="
            t('allianceOperations.overview.membersOnPage', {
              count: members.length,
              total: membershipManagement.total,
            })
          "
          :is-first-page="membershipManagement.memberPage.isFirstPage"
          first-page-href="/alliance"
          :has-more="membershipManagement.memberPage.hasMore"
          @next="nextMemberPage"
        />
      </section>

      <aside class="space-y-5">
        <section
          v-if="invitationManagement.allowed"
          class="ks-surface p-5"
          aria-labelledby="invitation-heading"
        >
          <p class="ks-kicker">{{ t('allianceOperations.overview.invitations') }}</p>
          <h2 id="invitation-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('allianceOperations.overview.sendInvitation') }}
          </h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('allianceOperations.overview.invitationsIntro') }}
          </p>

          <form class="mt-5 grid gap-3" @submit.prevent="sendInvitation">
            <select v-model="inviteForm.player_id" class="ks-input" required>
              <option
                v-for="candidate in invitationManagement.candidates"
                :key="candidate.id"
                :value="candidate.id"
              >
                {{ candidate.name
                }}<template v-if="candidate.gamePlayerId"> · {{ candidate.gamePlayerId }}</template>
              </option>
            </select>
            <input
              v-model="inviteForm.email"
              class="ks-input"
              type="email"
              required
              autocomplete="email"
            />
            <AppButton type="submit" :disabled="inviteForm.processing">
              {{ t('allianceOperations.overview.sendInvitation') }}
            </AppButton>
          </form>

          <div
            v-if="invitationManagement.issuedLink"
            class="mt-4 rounded-[var(--ks-radius-md)] border border-[rgba(32,178,163,.3)] bg-[var(--ks-teal-soft)] p-3"
          >
            <p class="ks-kicker">{{ t('allianceOperations.overview.newInvitationLink') }}</p>
            <p class="mt-2 text-xs break-all text-[#b8efe8]">
              {{ invitationManagement.issuedLink }}
            </p>
          </div>

          <div class="ks-divider my-5" />

          <div class="space-y-2">
            <article
              v-for="invite in invitationManagement.invitations"
              :key="invite.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-3"
            >
              <div class="flex items-start gap-3">
                <div
                  class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-[var(--ks-gold-dark)] font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
                  aria-hidden="true"
                >
                  {{ invite.player.name.slice(0, 1).toUpperCase() }}
                </div>
                <div class="min-w-0 flex-1">
                  <strong class="block truncate text-sm">{{ invite.player.name }}</strong>
                  <p class="mt-1 truncate text-xs text-[var(--ks-muted)]">{{ invite.email }}</p>
                </div>
                <span class="ks-status" :data-tone="statusTone(invite.status)">
                  {{ statusLabel(invite.status) }}
                </span>
              </div>
              <p v-if="invite.expiresAt" class="mt-3 text-xs text-[var(--ks-muted)]">
                {{ t('allianceOperations.overview.expires') }} ·
                {{ formatInZone(invite.expiresAt, alliance.timezone) }}
              </p>
              <div class="mt-3 grid grid-cols-2 gap-2">
                <button
                  type="button"
                  class="rounded border border-[var(--ks-border)] px-3 py-2 text-xs hover:border-[var(--ks-border-strong)]"
                  @click="resendInvitation(invite.id)"
                >
                  {{ t('allianceOperations.overview.resend') }}
                </button>
                <button
                  type="button"
                  class="rounded border border-red-400/20 px-3 py-2 text-xs text-red-200 hover:border-red-400/40"
                  @click="revokeInvitation(invite.id)"
                >
                  {{ t('allianceOperations.overview.revoke') }}
                </button>
              </div>
            </article>
            <p v-if="!invitationManagement.invitations.length" class="ks-fantasy-empty text-sm">
              {{ t('allianceOperations.overview.noInvitations') }}
            </p>
          </div>
        </section>

        <section class="ks-surface p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('allianceOperations.overview.upcoming') }}</p>
              <h2 class="ks-display mt-1 text-xl font-semibold">{{ t('navigation.events') }}</h2>
            </div>
            <Link href="/events" class="text-xs text-[var(--ks-teal-bright)]">
              {{ t('allianceOperations.overview.viewAll') }} →
            </Link>
          </div>
          <div v-if="contentHub.upcomingActivities.length" class="mt-4 space-y-2">
            <Link
              v-for="activity in contentHub.upcomingActivities"
              :key="activity.id"
              :href="`/events/${activity.id}`"
              class="block rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3 transition hover:border-[var(--ks-border-strong)]"
            >
              <strong class="block text-sm">{{ activity.title }}</strong>
              <span class="mt-1 block text-xs text-[var(--ks-muted)]">
                {{ formatInZone(activity.startsAt, activity.allianceTimezone) }}
              </span>
            </Link>
          </div>
          <p v-else class="mt-4 text-sm text-[var(--ks-muted)]">
            {{ t('allianceOperations.overview.noUpcoming') }}
          </p>
        </section>

        <button
          type="button"
          class="w-full rounded-[var(--ks-radius-md)] border border-red-400/20 bg-red-500/[.035] px-4 py-3 text-start text-sm text-red-200 transition hover:border-red-400/40"
          @click="leaveAlliance"
        >
          {{ t('allianceOperations.overview.leaveAlliance') }}
        </button>
      </aside>
    </div>
    <ConfirmActionDialog
      id="membership-bulk-status-confirmation"
      :open="bulkConfirmationOpen"
      :title="t('allianceOperations.overview.confirmBulkTitle')"
      :description="
        t('allianceOperations.overview.confirmBulkDescription', {
          count: membershipBulkPreview?.ready ?? 0,
          status: statusLabel(bulkTargetStatus),
        })
      "
      :confirm-label="t('allianceOperations.overview.confirmBulkAction')"
      :cancel-label="t('common.cancel')"
      :busy="bulkBusy"
      :busy-label="t('allianceOperations.overview.applyingBulkAction')"
      :danger="bulkTargetStatus === 'removed'"
      @confirm="commitBulkStatusChange"
      @cancel="bulkConfirmationOpen = false"
    />
    <ConfirmActionDialog v-bind="dialog" @confirm="confirmAction" @cancel="cancelConfirmation" />
  </AppLayout>
</template>
