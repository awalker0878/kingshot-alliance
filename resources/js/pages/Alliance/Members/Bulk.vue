<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Member = {
  membershipId: string;
  playerId: string;
  name: string;
  rank: string;
};

type Role = {
  id: string;
  key: string;
  name: string;
  system: boolean;
};

type BulkItem = {
  itemId: string;
  playerId: string | null;
  outcome: string;
  code: string;
  fromRank?: string | null;
  toRank?: string;
  roleKey?: string;
  operation?: string;
};

type BulkSummary = {
  items: BulkItem[];
  ready?: number;
  blocked?: number;
  succeeded?: number;
  failed?: number;
  skipped?: number;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  members: Member[];
  roles: Role[];
  rankOptions: string[];
  bulkRankPreview: BulkSummary | null;
  bulkRankResult: BulkSummary | null;
  bulkRolePreview: BulkSummary | null;
  bulkRoleResult: BulkSummary | null;
}>();

const { t, formatNumber } = useLocale();
const selected = ref<string[]>([]);
const rankForm = useForm({
  membership_ids: [] as string[],
  rank: props.rankOptions[0] ?? 'r1',
});
const roleForm = useForm({
  membership_ids: [] as string[],
  role_id: props.roles[0]?.id ?? '',
  operation: 'assign',
});

const selectedCount = computed(() => selected.value.length);
const allSelected = computed(
  () => props.members.length > 0 && selected.value.length === Math.min(props.members.length, 50),
);

function selectAll(): void {
  selected.value = props.members.slice(0, 50).map((member) => member.membershipId);
}

function clearSelection(): void {
  selected.value = [];
}

function prepareRank(): void {
  rankForm.membership_ids = [...selected.value];
}

function prepareRole(): void {
  roleForm.membership_ids = [...selected.value];
}

function previewRank(): void {
  prepareRank();
  rankForm.post('/alliance/memberships/bulk-rank/preview', { preserveScroll: true });
}

function commitRank(): void {
  prepareRank();
  rankForm.post('/alliance/memberships/bulk-rank', { preserveScroll: true });
}

function previewRole(): void {
  prepareRole();
  roleForm.post('/alliance/memberships/bulk-role/preview', { preserveScroll: true });
}

function commitRole(): void {
  prepareRole();
  roleForm.post('/alliance/memberships/bulk-role', { preserveScroll: true });
}

function memberName(item: BulkItem): string {
  return props.members.find((member) => member.membershipId === item.itemId)?.name ?? item.itemId;
}
</script>

<template>
  <Head :title="`${t('allianceExpansion.bulkTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('allianceExpansion.bulkEyebrow')"
      :title="t('allianceExpansion.bulkTitle')"
      :subtitle="t('allianceExpansion.bulkSubtitle')"
      image="/images/kingshot/v4/roster-hall.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/roles" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navRoles') }}
        </Link>
        <Link href="/alliance/history" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navHistory') }}
        </Link>
      </template>
    </RoomBanner>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(22rem,0.75fr)]">
      <section class="ks-surface min-w-0 p-5" aria-labelledby="bulk-member-list">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 id="bulk-member-list" class="ks-display text-xl font-semibold">
              {{ t('allianceExpansion.selectedMembers', { count: formatNumber(selectedCount) }) }}
            </h2>
          </div>
          <div class="flex flex-wrap gap-2">
            <AppButton variant="secondary" :disabled="allSelected" @click="selectAll">
              {{ t('allianceExpansion.selectAll') }}
            </AppButton>
            <AppButton variant="secondary" :disabled="selectedCount === 0" @click="clearSelection">
              {{ t('allianceExpansion.clearSelection') }}
            </AppButton>
          </div>
        </div>

        <div class="mt-4 grid gap-2 sm:grid-cols-2">
          <label
            v-for="member in members"
            :key="member.membershipId"
            class="flex items-center gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-3"
          >
            <input
              v-model="selected"
              type="checkbox"
              :value="member.membershipId"
              :disabled="selectedCount >= 50 && !selected.includes(member.membershipId)"
            />
            <span class="min-w-0 flex-1">
              <span class="block truncate font-semibold">{{ member.name }}</span>
              <span class="text-xs text-[var(--ks-muted)]">{{ member.rank }}</span>
            </span>
          </label>
        </div>
      </section>

      <div class="space-y-6">
        <section class="ks-surface p-5" aria-labelledby="bulk-rank-title">
          <h2 id="bulk-rank-title" class="ks-display text-xl font-semibold">
            {{ t('allianceExpansion.rankChange') }}
          </h2>
          <label class="mt-4 block text-sm font-semibold" for="bulk-rank">
            {{ t('allianceExpansion.targetRank') }}
          </label>
          <select id="bulk-rank" v-model="rankForm.rank" class="ks-input mt-2">
            <option v-for="rank in rankOptions" :key="rank" :value="rank">{{ rank }}</option>
          </select>
          <div class="mt-4 flex flex-wrap gap-2">
            <AppButton
              variant="secondary"
              :disabled="selectedCount === 0 || rankForm.processing"
              @click="previewRank"
            >
              {{ t('allianceExpansion.previewRank') }}
            </AppButton>
            <AppButton :disabled="selectedCount === 0 || rankForm.processing" @click="commitRank">
              {{ t('allianceExpansion.commitRank') }}
            </AppButton>
          </div>
        </section>

        <section class="ks-surface p-5" aria-labelledby="bulk-role-title">
          <h2 id="bulk-role-title" class="ks-display text-xl font-semibold">
            {{ t('allianceExpansion.roleChange') }}
          </h2>
          <label class="mt-4 block text-sm font-semibold" for="bulk-role">
            {{ t('allianceExpansion.targetRole') }}
          </label>
          <select id="bulk-role" v-model="roleForm.role_id" class="ks-input mt-2">
            <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
          </select>
          <div class="mt-4 grid grid-cols-2 gap-2">
            <label class="flex items-center gap-2 text-sm">
              <input v-model="roleForm.operation" type="radio" value="assign" />
              <span>{{ t('allianceExpansion.assignRole') }}</span>
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="roleForm.operation" type="radio" value="remove" />
              <span>{{ t('allianceExpansion.removeRole') }}</span>
            </label>
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            <AppButton
              variant="secondary"
              :disabled="selectedCount === 0 || !roleForm.role_id || roleForm.processing"
              @click="previewRole"
            >
              {{ t('allianceExpansion.previewRole') }}
            </AppButton>
            <AppButton
              :disabled="selectedCount === 0 || !roleForm.role_id || roleForm.processing"
              @click="commitRole"
            >
              {{ t('allianceExpansion.commitRole') }}
            </AppButton>
          </div>
        </section>
      </div>
    </div>

    <section class="mt-6 grid gap-6 xl:grid-cols-2">
      <article class="ks-surface p-5">
        <h2 class="ks-display text-xl font-semibold">{{ t('allianceExpansion.previewTitle') }}</h2>
        <div v-if="bulkRankPreview || bulkRolePreview" class="mt-4 space-y-2">
          <div
            v-for="item in (bulkRankPreview ?? bulkRolePreview)?.items ?? []"
            :key="item.itemId"
            class="flex items-start justify-between gap-3 border-b border-[var(--ks-border)] py-2 text-sm last:border-0"
          >
            <span>{{ memberName(item) }}</span>
            <span class="text-end text-[var(--ks-muted)]"
              >{{ item.outcome }} · {{ item.code }}</span
            >
          </div>
        </div>
        <p v-else class="mt-3 text-sm text-[var(--ks-muted)]">
          {{ t('allianceExpansion.noPreview') }}
        </p>
      </article>

      <article class="ks-surface p-5">
        <h2 class="ks-display text-xl font-semibold">{{ t('allianceExpansion.resultTitle') }}</h2>
        <div v-if="bulkRankResult || bulkRoleResult" class="mt-4 space-y-2">
          <div class="grid grid-cols-3 gap-2 text-center">
            <div class="ks-stat-card">
              <span class="ks-kicker">{{ t('allianceExpansion.outcomeSucceeded') }}</span>
              <strong>{{
                formatNumber((bulkRankResult ?? bulkRoleResult)?.succeeded ?? 0)
              }}</strong>
            </div>
            <div class="ks-stat-card">
              <span class="ks-kicker">{{ t('allianceExpansion.outcomeFailed') }}</span>
              <strong>{{ formatNumber((bulkRankResult ?? bulkRoleResult)?.failed ?? 0) }}</strong>
            </div>
            <div class="ks-stat-card">
              <span class="ks-kicker">{{ t('allianceExpansion.outcomeSkipped') }}</span>
              <strong>{{ formatNumber((bulkRankResult ?? bulkRoleResult)?.skipped ?? 0) }}</strong>
            </div>
          </div>
        </div>
        <p v-else class="mt-3 text-sm text-[var(--ks-muted)]">
          {{ t('allianceExpansion.noPreview') }}
        </p>
      </article>
    </section>
  </AppLayout>
</template>
