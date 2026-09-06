<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type PermissionOption = { key: string; owner: string | null; description: string };
type RoleOption = { id: string; key: string; name: string; description: string | null; isSystem: boolean; archivedAt: string | null; permissions: PermissionOption[] };
type PlayerOption = { id: string; name: string; gamePlayerId: string | null };
type Assignment = { id: string; player: PlayerOption; role: { id: string; key: string; name: string }; state: string; effectiveFrom: string | null; expiresAt: string | null; reason: string | null; assignedAt: string | null };
const props = defineProps<{ user: { name: string; email: string }; alliance: { id: string; name: string }; kingdom: { id: string; number: number }; roles: RoleOption[]; players: PlayerOption[]; assignments: Assignment[]; permissionOptions: PermissionOption[] }>();
const { t, formatDate } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
const search = ref('');
const roleFilter = ref('');
const activeRoles = computed(() => props.roles.filter((role) => !role.archivedAt));
const filteredAssignments = computed(() => props.assignments.filter((assignment) => {
  const term = search.value.trim().toLowerCase();
  return (!roleFilter.value || assignment.role.id === roleFilter.value) && (!term || assignment.player.name.toLowerCase().includes(term) || (assignment.player.gamePlayerId ?? '').toLowerCase().includes(term));
}));
const form = useForm({ player_id: props.players[0]?.id ?? '', role_id: activeRoles.value[0]?.id ?? '', effective_from: '', expires_at: '', reason: '' });
const customForm = useForm({ name: '', description: '', permissions: [] as string[] });
const handoffForm = useForm({ player_id: props.players[0]?.id ?? '', mode: 'add', reason: '' });
const bulkRoleId = ref(activeRoles.value[0]?.id ?? '');
const bulkOperation = ref<'assign' | 'remove'>('assign');
const selectedPlayers = ref<string[]>([]);
const bulkReason = ref('');
const bulkPreview = ref<{ eligible: string[]; ineligible: Record<string, string> } | null>(null);
const previewing = ref(false);
function assignRole(): void { form.post('/alliance/settings/kingdom/roles', { preserveScroll: true, onSuccess: () => form.reset('effective_from', 'expires_at', 'reason') }); }
function removeRole(assignment: Assignment): void { requestConfirmation({ id: 'kingdom-role-removal-confirmation', title: t('governanceExpansion.remove'), description: `${t('governanceExpansion.remove')} ${assignment.role.name} · ${assignment.player.name}?`, confirmLabel: t('governanceExpansion.remove'), cancelLabel: t('common.cancel'), perform: (finish) => router.delete(`/alliance/settings/kingdom/roles/${assignment.id}`, { preserveScroll: true, onFinish: finish }) }); }
function createCustomRole(): void { customForm.post('/alliance/settings/kingdom/governance/roles', { preserveScroll: true, onSuccess: () => customForm.reset() }); }
function archiveRole(role: RoleOption): void { router.post(`/alliance/settings/kingdom/governance/roles/${role.id}/archive`, {}, { preserveScroll: true }); }
function handoff(): void { handoffForm.post('/alliance/settings/kingdom/governance/administrator-handoff', { preserveScroll: true, onSuccess: () => handoffForm.reset('reason') }); }
function csrfToken(): string { return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''; }
async function previewBulk(): Promise<void> {
  previewing.value = true;
  try {
    const response = await fetch('/alliance/settings/kingdom/governance/bulk/preview', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ role_id: bulkRoleId.value, operation: bulkOperation.value, player_ids: selectedPlayers.value }) });
    if (!response.ok) throw new Error('Bulk preview failed');
    bulkPreview.value = await response.json();
  } finally { previewing.value = false; }
}
function commitBulk(): void { if (!bulkPreview.value) return; router.post('/alliance/settings/kingdom/governance/bulk', { role_id: bulkRoleId.value, operation: bulkOperation.value, player_ids: selectedPlayers.value, reason: bulkReason.value }, { preserveScroll: true, onSuccess: () => { selectedPlayers.value = []; bulkPreview.value = null; bulkReason.value = ''; } }); }
function roleStateLabel(state: string): string { return t(`governanceExpansion.${state}`); }
</script>

<template>
  <Head :title="`${t('governanceExpansion.rolesTitle')} · #${kingdom.number}`" />
  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner :eyebrow="t('governanceExpansion.eyebrow')" :title="t('governanceExpansion.rolesTitle')" :subtitle="t('governanceExpansion.subtitle')" image="/images/kingshot/v4/kingdom-map.svg" compact>
      <template #actions>
        <Link href="/alliance/settings/kingdom/governance/authority" class="ks-command-link">{{ t('governanceExpansion.navAuthority') }}</Link>
        <Link href="/alliance/settings/kingdom/governance/history" class="ks-command-link">{{ t('governanceExpansion.navHistory') }}</Link>
        <Link href="/alliance/settings/kingdom/governance/health" class="ks-command-link">{{ t('governanceExpansion.navHealth') }}</Link>
      </template>
    </RoomBanner>

    <div class="mt-6 grid gap-5 xl:grid-cols-[minmax(320px,0.42fr)_minmax(0,1fr)]">
      <div class="space-y-5">
        <section class="ks-surface p-5">
          <h2 class="ks-display text-xl font-semibold">{{ t('governanceExpansion.assignment') }}</h2>
          <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">{{ t('governanceExpansion.assignmentHelp') }}</p>
          <form class="mt-5 space-y-4" @submit.prevent="assignRole">
            <label class="block text-sm font-semibold">{{ t('governanceExpansion.governor') }}<select v-model="form.player_id" class="ks-input mt-2"><option v-for="player in players" :key="player.id" :value="player.id">{{ player.name }}{{ player.gamePlayerId ? ` · ${player.gamePlayerId}` : '' }}</option></select></label>
            <label class="block text-sm font-semibold">{{ t('governanceExpansion.role') }}<select v-model="form.role_id" class="ks-input mt-2"><option v-for="role in activeRoles" :key="role.id" :value="role.id">{{ role.name }}</option></select></label>
            <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm font-semibold">{{ t('governanceExpansion.effectiveFrom') }}<input v-model="form.effective_from" type="datetime-local" class="ks-input mt-2" /></label><label class="text-sm font-semibold">{{ t('governanceExpansion.expiresAt') }}<input v-model="form.expires_at" type="datetime-local" class="ks-input mt-2" /></label></div>
            <label class="block text-sm font-semibold">{{ t('governanceExpansion.reason') }}<input v-model="form.reason" class="ks-input mt-2" maxlength="500" /></label>
            <button type="submit" class="ks-command-button" :disabled="form.processing">{{ t('governanceExpansion.assign') }}</button>
          </form>
        </section>

        <section class="ks-surface p-5">
          <h2 class="ks-display text-xl font-semibold">{{ t('governanceExpansion.customRoleTitle') }}</h2>
          <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">{{ t('governanceExpansion.customHelp') }}</p>
          <form class="mt-4 space-y-3" @submit.prevent="createCustomRole">
            <input v-model="customForm.name" class="ks-input" :placeholder="t('governanceExpansion.name')" maxlength="100" />
            <input v-model="customForm.description" class="ks-input" :placeholder="t('governanceExpansion.description')" maxlength="255" />
            <div class="max-h-48 space-y-2 overflow-y-auto rounded border border-[var(--ks-border)] p-3"><label v-for="permission in permissionOptions" :key="permission.key" class="flex gap-2 text-sm"><input v-model="customForm.permissions" type="checkbox" :value="permission.key" /><span><strong>{{ permission.key }}</strong><span class="block text-xs text-[var(--ks-text-muted)]">{{ permission.owner }} · {{ permission.description }}</span></span></label></div>
            <button type="submit" class="ks-command-button" :disabled="customForm.processing">{{ t('governanceExpansion.create') }}</button>
          </form>
        </section>

        <section class="ks-surface p-5">
          <h2 class="ks-display text-xl font-semibold">{{ t('governanceExpansion.handoffTitle') }}</h2>
          <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">{{ t('governanceExpansion.handoffHelp') }}</p>
          <form class="mt-4 space-y-3" @submit.prevent="handoff">
            <select v-model="handoffForm.player_id" class="ks-input"><option v-for="player in players" :key="player.id" :value="player.id">{{ player.name }}</option></select>
            <select v-model="handoffForm.mode" class="ks-input"><option value="add">{{ t('governanceExpansion.addAdmin') }}</option><option value="replace">{{ t('governanceExpansion.replaceMe') }}</option></select>
            <input v-model="handoffForm.reason" class="ks-input" :placeholder="t('governanceExpansion.reason')" maxlength="500" />
            <button type="submit" class="ks-command-button">{{ t('governanceExpansion.handoffTitle') }}</button>
          </form>
        </section>
      </div>

      <div class="space-y-5">
        <section class="ks-surface p-5">
          <div class="flex flex-wrap gap-3"><input v-model="search" class="ks-input min-w-64 flex-1" :placeholder="t('governanceExpansion.search')" /><select v-model="roleFilter" class="ks-input max-w-64"><option value="">{{ t('governanceExpansion.allRoles') }}</option><option v-for="role in activeRoles" :key="role.id" :value="role.id">{{ role.name }}</option></select></div>
          <div v-if="filteredAssignments.length" class="mt-4 divide-y divide-[var(--ks-border)]"><article v-for="assignment in filteredAssignments" :key="assignment.id" class="flex flex-wrap items-center justify-between gap-4 py-4"><div><p class="font-semibold">{{ assignment.player.name }}</p><p class="text-sm text-[var(--ks-gold)]">{{ assignment.role.name }} · {{ roleStateLabel(assignment.state) }}</p><p v-if="assignment.expiresAt" class="text-xs text-[var(--ks-text-muted)]">{{ t('governanceExpansion.expiresAt') }}: {{ formatDate(assignment.expiresAt) }}</p><p v-if="assignment.reason" class="text-xs text-[var(--ks-text-muted)]">{{ assignment.reason }}</p></div><button type="button" class="rounded border border-red-400/40 px-3 py-2 text-sm text-red-200" @click="removeRole(assignment)">{{ t('governanceExpansion.remove') }}</button></article></div>
          <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">{{ t('governanceExpansion.noAssignments') }}</p>
        </section>

        <section class="ks-surface p-5">
          <h2 class="ks-display text-xl font-semibold">{{ t('governanceExpansion.bulkTitle') }}</h2><p class="mt-2 text-sm text-[var(--ks-text-secondary)]">{{ t('governanceExpansion.bulkHelp') }}</p>
          <div class="mt-4 grid gap-3 sm:grid-cols-2"><select v-model="bulkRoleId" class="ks-input"><option v-for="role in activeRoles" :key="role.id" :value="role.id">{{ role.name }}</option></select><select v-model="bulkOperation" class="ks-input"><option value="assign">{{ t('governanceExpansion.assign') }}</option><option value="remove">{{ t('governanceExpansion.remove') }}</option></select></div>
          <div class="mt-3 max-h-56 overflow-y-auto rounded border border-[var(--ks-border)] p-3"><label v-for="player in players" :key="player.id" class="flex gap-2 py-1 text-sm"><input v-model="selectedPlayers" type="checkbox" :value="player.id" />{{ player.name }}</label></div>
          <input v-model="bulkReason" class="ks-input mt-3" :placeholder="t('governanceExpansion.reason')" />
          <div class="mt-3 flex gap-3"><button class="ks-command-button" :disabled="previewing || selectedPlayers.length === 0" @click="previewBulk">{{ t('governanceExpansion.preview') }}</button><button v-if="bulkPreview" class="ks-command-button" @click="commitBulk">{{ t('governanceExpansion.commit') }}</button></div>
          <div v-if="bulkPreview" class="mt-3 text-sm"><p>{{ t('governanceExpansion.eligible', { count: bulkPreview.eligible.length }) }} · {{ t('governanceExpansion.ineligible', { count: Object.keys(bulkPreview.ineligible).length }) }}</p><ul v-if="Object.keys(bulkPreview.ineligible).length" class="mt-2 list-disc pl-5 text-[var(--ks-text-muted)]"><li v-for="(reason, playerId) in bulkPreview.ineligible" :key="playerId">{{ players.find((p) => p.id === playerId)?.name ?? playerId }} — {{ reason }}</li></ul></div>
        </section>

        <section class="ks-surface p-5"><h2 class="ks-display text-xl font-semibold">{{ t('governanceExpansion.rolesTitle') }}</h2><div class="mt-3 grid gap-3 md:grid-cols-2"><article v-for="role in roles" :key="role.id" class="rounded border border-[var(--ks-border)] p-4" :class="role.archivedAt ? 'opacity-60' : ''"><div class="flex justify-between gap-3"><div><p class="font-semibold">{{ role.name }}</p><p class="text-xs text-[var(--ks-text-muted)]">{{ role.key }} · {{ role.isSystem ? t('governanceExpansion.systemRole') : t('governanceExpansion.customRoleBadge') }}</p></div><button v-if="!role.isSystem && !role.archivedAt" class="text-sm text-red-200" @click="archiveRole(role)">{{ t('governanceExpansion.archive') }}</button></div><p v-if="role.description" class="mt-2 text-sm text-[var(--ks-text-secondary)]">{{ role.description }}</p><div class="mt-2 flex flex-wrap gap-1"><span v-for="permission in role.permissions" :key="permission.key" class="rounded border border-[var(--ks-border)] px-2 py-1 text-xs">{{ permission.key }} · {{ permission.owner }}</span></div></article></div></section>
      </div>
    </div>
    <ConfirmActionDialog v-bind="dialog" @confirm="confirmAction" @cancel="cancelConfirmation" />
  </AppLayout>
</template>
