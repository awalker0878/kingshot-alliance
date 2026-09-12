<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import GovernanceChoicePicker from '@/components/governance/GovernanceChoicePicker.vue';
import GovernanceCataloguePager from '@/components/governance/GovernanceCataloguePager.vue';
import type { GovernancePage } from '@/components/governance/governancePages';
import type { GovernanceChoice } from '@/components/governance/governanceChoices';

import RoomBanner from '@/components/game/RoomBanner.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type PermissionOption = { key: string; owner: string | null; description: string };
type RoleOption = {
  id: string;
  key: string;
  name: string;
  description: string | null;
  isSystem: boolean;
  archivedAt: string | null;
  permissions: PermissionOption[];
};
type PlayerOption = { id: string; name: string; gamePlayerId: string | null };
type Assignment = {
  id: string;
  player: PlayerOption;
  role: { id: string; key: string; name: string };
  state: string;
  effectiveFrom: string | null;
  expiresAt: string | null;
  reason: string | null;
  assignedAt: string | null;
};
const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  kingdom: { id: string; number: number };
  catalogueScope: string;
  roles: RoleOption[];
  assignments: Assignment[];
  permissionOptions: PermissionOption[];
  pages: { roles: GovernancePage; assignments: GovernancePage };
  filters: {
    search: string;
    roleId: string | null;
    playerId: string | null;
    permission: string | null;
  };
  selectedRole: GovernanceChoice | null;
  selectedPlayer: GovernanceChoice | null;
}>();
const { t, formatDate } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
const search = ref(props.filters.search);
const roleFilter = ref(props.filters.roleId ?? '');
const scope = computed(() => props.catalogueScope);
const mutationError = ref('');
const filterBusy = ref(false);
const filterFailed = ref(false);
const filteredAssignments = computed(() => props.assignments);
function filterAssignments(clear = false): void {
  filterBusy.value = true;
  filterFailed.value = false;
  const fail = (): false => {
    filterFailed.value = true;
    return false;
  };
  router.get(
    '/alliance/settings/kingdom/roles',
    clear
      ? {}
      : {
          search: search.value,
          role_id: roleFilter.value || undefined,
          player_id: props.filters.playerId ?? undefined,
          permission: props.filters.permission ?? undefined,
        },
    {
      preserveState: true,
      preserveScroll: true,
      onError: fail,
      onNetworkError: fail,
      onHttpException: fail,
      onSuccess: () => {
        if (clear) {
          search.value = '';
          roleFilter.value = '';
        }
      },
      onFinish: () => {
        filterBusy.value = false;
      },
    },
  );
}
const form = useForm({
  player_id: '',
  role_id: '',
  effective_from: '',
  expires_at: '',
  reason: '',
});
const customForm = useForm({ name: '', description: '', permissions: [] as string[] });
const handoffForm = useForm({ player_id: '', mode: 'add', reason: '' });
const bulkRoleId = ref('');
const bulkOperation = ref<'assign' | 'remove'>('assign');
const selectedChoices = ref<GovernanceChoice[]>([]);
const selectedPlayers = computed(() => selectedChoices.value.map((choice) => choice.id));
const bulkPickerId = ref('');
function addBulkPlayer(choice: GovernanceChoice | null): void {
  if (!choice || selectedChoices.value.some((item) => item.id === choice.id)) return;
  if (selectedChoices.value.length >= 50) return;
  selectedChoices.value = [...selectedChoices.value, choice];
}
function removeBulkPlayer(id: string): void {
  selectedChoices.value = selectedChoices.value.filter((choice) => choice.id !== id);
  if (bulkPickerId.value === id) bulkPickerId.value = '';
}
const bulkReason = ref('');
const bulkPreview = ref<{ eligible: string[]; ineligible: Record<string, string> } | null>(null);
const previewing = ref(false);
const bulkCommitting = ref(false);
const bulkError = ref('');
let previewVersion = 0;
watch([bulkRoleId, bulkOperation, selectedPlayers, bulkReason], () => {
  previewVersion++;
  bulkPreview.value = null;
  bulkError.value = '';
});
watch(
  () => props.catalogueScope,
  () => {
    form.reset();
    customForm.reset();
    handoffForm.reset();
    selectedChoices.value = [];
    bulkRoleId.value = '';
    bulkPickerId.value = '';
    bulkReason.value = '';
    mutationError.value = '';
    filterFailed.value = false;
    search.value = props.filters.search;
    roleFilter.value = props.filters.roleId ?? '';
  },
);
function mutationFailed(): false {
  mutationError.value = t('governanceExpansion.historyUnavailable');
  return false;
}
function assignRole(): void {
  form.post('/alliance/settings/kingdom/roles', {
    preserveScroll: true,
    onNetworkError: mutationFailed,
    onHttpException: mutationFailed,
    onSuccess: () => form.reset('effective_from', 'expires_at', 'reason'),
  });
}
function removeRole(assignment: Assignment): void {
  requestConfirmation({
    id: 'kingdom-role-removal-confirmation',
    title: t('governanceExpansion.remove'),
    description: `${t('governanceExpansion.remove')} ${assignment.role.name} · ${assignment.player.name}?`,
    confirmLabel: t('governanceExpansion.remove'),
    cancelLabel: t('common.cancel'),
    perform: (finish) =>
      router.delete(`/alliance/settings/kingdom/roles/${assignment.id}`, {
        preserveScroll: true,
        onFinish: finish,
        onError: (errors) => {
          mutationError.value = Object.values(errors).join(' ');
        },
        onNetworkError: mutationFailed,
        onHttpException: mutationFailed,
      }),
  });
}
function createCustomRole(): void {
  customForm.post('/alliance/settings/kingdom/governance/roles', {
    preserveScroll: true,
    onNetworkError: mutationFailed,
    onHttpException: mutationFailed,
    onSuccess: () => customForm.reset(),
  });
}
function archiveRole(role: RoleOption): void {
  router.post(
    `/alliance/settings/kingdom/governance/roles/${role.id}/archive`,
    {},
    {
      preserveScroll: true,
      onError: (errors) => {
        mutationError.value = Object.values(errors).join(' ');
      },
      onNetworkError: mutationFailed,
      onHttpException: mutationFailed,
    },
  );
}
function handoff(): void {
  handoffForm.post('/alliance/settings/kingdom/governance/administrator-handoff', {
    preserveScroll: true,
    onNetworkError: mutationFailed,
    onHttpException: mutationFailed,
    onSuccess: () => handoffForm.reset('reason'),
  });
}
function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
async function previewBulk(): Promise<void> {
  if (previewing.value) return;
  previewing.value = true;
  bulkError.value = '';
  const version = ++previewVersion;
  const submitted = [...selectedPlayers.value];
  try {
    const response = await fetch('/alliance/settings/kingdom/governance/bulk/preview', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        role_id: bulkRoleId.value,
        operation: bulkOperation.value,
        player_ids: selectedPlayers.value,
      }),
    });
    if (!response.ok) throw new Error('Bulk preview failed');
    const result = await response.json();
    if (version !== previewVersion) return;
    if (
      !result ||
      !Array.isArray(result.eligible) ||
      result.eligible.length > 50 ||
      !result.eligible.every((id: unknown) => typeof id === 'string' && submitted.includes(id)) ||
      !result.ineligible ||
      typeof result.ineligible !== 'object' ||
      Array.isArray(result.ineligible) ||
      !Object.entries(result.ineligible).every(
        ([id, reason]) => submitted.includes(id) && typeof reason === 'string',
      )
    ) {
      throw new Error('Invalid preview response');
    }
    bulkPreview.value = result;
  } catch {
    if (version === previewVersion) bulkError.value = t('governanceExpansion.historyUnavailable');
  } finally {
    previewing.value = false;
  }
}
function commitBulk(): void {
  if (!bulkPreview.value || bulkCommitting.value) return;
  bulkCommitting.value = true;
  bulkError.value = '';
  const fail = (): false => {
    bulkError.value = t('governanceExpansion.historyUnavailable');
    return false;
  };
  router.post(
    '/alliance/settings/kingdom/governance/bulk',
    {
      role_id: bulkRoleId.value,
      operation: bulkOperation.value,
      player_ids: selectedPlayers.value,
      reason: bulkReason.value,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        selectedChoices.value = [];
        bulkPickerId.value = '';
        bulkPreview.value = null;
        bulkReason.value = '';
      },
      onError: (errors) => {
        bulkError.value = Object.values(errors).join(' ');
      },
      onNetworkError: fail,
      onHttpException: fail,
      onFinish: () => {
        bulkCommitting.value = false;
      },
    },
  );
}
function roleStateLabel(state: string): string {
  return t(`governanceExpansion.${state}`);
}
</script>

<template>
  <Head :title="`${t('governanceExpansion.rolesTitle')} · #${kingdom.number}`" />
  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('governanceExpansion.eyebrow')"
      :title="t('governanceExpansion.rolesTitle')"
      :subtitle="t('governanceExpansion.subtitle')"
      image="/images/kingshot/v4/kingdom-map.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/settings/kingdom/governance/authority" class="ks-command-link">{{
          t('governanceExpansion.navAuthority')
        }}</Link>
        <Link href="/alliance/settings/kingdom/governance/history" class="ks-command-link">{{
          t('governanceExpansion.navHistory')
        }}</Link>
        <Link href="/alliance/settings/kingdom/governance/health" class="ks-command-link">{{
          t('governanceExpansion.navHealth')
        }}</Link>
      </template>
    </RoomBanner>

    <p v-if="mutationError" role="alert" class="mt-4 text-sm text-red-200">{{ mutationError }}</p>
    <div class="mt-6 grid gap-5 xl:grid-cols-[minmax(320px,0.42fr)_minmax(0,1fr)]">
      <div class="space-y-5">
        <section class="ks-surface p-5">
          <h2 class="ks-display text-xl font-semibold">
            {{ t('governanceExpansion.assignment') }}
          </h2>
          <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
            {{ t('governanceExpansion.assignmentHelp') }}
          </p>
          <form class="mt-5 space-y-4" @submit.prevent="assignRole">
            <fieldset class="space-y-3" :disabled="form.processing">
              <GovernanceChoicePicker
                id="assignment-player"
                v-model="form.player_id"
                kind="players"
                :scope="scope"
                :label="t('governanceExpansion.governor')"
                :empty-label="t('common.none')"
                required
              />
              <GovernanceChoicePicker
                id="assignment-role"
                v-model="form.role_id"
                kind="roles"
                :scope="scope"
                :label="t('governanceExpansion.role')"
                :empty-label="t('common.none')"
                required
              />
              <p v-if="Object.keys(form.errors).length" role="alert" class="text-sm text-red-200">
                {{ Object.values(form.errors).join(' ') }}
              </p>
              <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm font-semibold"
                  >{{ t('governanceExpansion.effectiveFrom')
                  }}<input
                    v-model="form.effective_from"
                    type="datetime-local"
                    class="ks-input mt-2" /></label
                ><label class="text-sm font-semibold"
                  >{{ t('governanceExpansion.expiresAt')
                  }}<input v-model="form.expires_at" type="datetime-local" class="ks-input mt-2"
                /></label>
              </div>
              <label class="block text-sm font-semibold"
                >{{ t('governanceExpansion.reason')
                }}<input v-model="form.reason" class="ks-input mt-2" maxlength="500"
              /></label>
              <button type="submit" class="ks-command-button" :disabled="form.processing">
                {{ t('governanceExpansion.assign') }}
              </button>
            </fieldset>
          </form>
        </section>

        <section class="ks-surface p-5">
          <h2 class="ks-display text-xl font-semibold">
            {{ t('governanceExpansion.customRoleTitle') }}
          </h2>
          <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
            {{ t('governanceExpansion.customHelp') }}
          </p>
          <form class="mt-4 space-y-3" @submit.prevent="createCustomRole">
            <fieldset class="space-y-3" :disabled="customForm.processing">
              <p
                v-if="Object.keys(customForm.errors).length"
                role="alert"
                class="text-sm text-red-200"
              >
                {{ Object.values(customForm.errors).join(' ') }}
              </p>
              <input
                v-model="customForm.name"
                class="ks-input"
                :placeholder="t('governanceExpansion.name')"
                maxlength="100"
              />
              <input
                v-model="customForm.description"
                class="ks-input"
                :placeholder="t('governanceExpansion.description')"
                maxlength="255"
              />
              <div
                class="max-h-48 space-y-2 overflow-y-auto rounded border border-[var(--ks-border)] p-3"
              >
                <label
                  v-for="permission in permissionOptions"
                  :key="permission.key"
                  class="flex gap-2 text-sm"
                  ><input
                    v-model="customForm.permissions"
                    type="checkbox"
                    :value="permission.key"
                  /><span
                    ><strong>{{ permission.key }}</strong
                    ><span class="block text-xs text-[var(--ks-text-muted)]"
                      >{{ permission.owner }} · {{ permission.description }}</span
                    ></span
                  ></label
                >
              </div>
              <button type="submit" class="ks-command-button" :disabled="customForm.processing">
                {{ t('governanceExpansion.create') }}
              </button>
            </fieldset>
          </form>
        </section>

        <section class="ks-surface p-5">
          <h2 class="ks-display text-xl font-semibold">
            {{ t('governanceExpansion.handoffTitle') }}
          </h2>
          <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
            {{ t('governanceExpansion.handoffHelp') }}
          </p>
          <form class="mt-4 space-y-3" @submit.prevent="handoff">
            <fieldset class="space-y-3" :disabled="handoffForm.processing">
              <GovernanceChoicePicker
                id="handoff-player"
                v-model="handoffForm.player_id"
                kind="players"
                :scope="scope"
                :label="t('governanceExpansion.governor')"
                :empty-label="t('common.none')"
                required
              />
              <p
                v-if="Object.keys(handoffForm.errors).length"
                role="alert"
                class="text-sm text-red-200"
              >
                {{ Object.values(handoffForm.errors).join(' ') }}
              </p>
              <select v-model="handoffForm.mode" class="ks-input">
                <option value="add">{{ t('governanceExpansion.addAdmin') }}</option>
                <option value="replace">{{ t('governanceExpansion.replaceMe') }}</option>
              </select>
              <input
                v-model="handoffForm.reason"
                class="ks-input"
                :placeholder="t('governanceExpansion.reason')"
                maxlength="500"
              />
              <button type="submit" class="ks-command-button" :disabled="handoffForm.processing">
                {{ t('governanceExpansion.handoffTitle') }}
              </button>
            </fieldset>
          </form>
        </section>
      </div>

      <div class="space-y-5">
        <section class="ks-surface p-5">
          <div class="space-y-3" data-testid="assignment-filters">
            <input
              v-model="search"
              class="ks-input w-full"
              maxlength="160"
              :aria-label="t('governanceExpansion.search')"
              :placeholder="t('governanceExpansion.search')"
              @keydown.enter.prevent="filterAssignments()"
            />
            <GovernanceChoicePicker
              id="assignment-role-filter"
              v-model="roleFilter"
              kind="roles"
              :scope="scope"
              :selected-name="selectedRole?.name"
              :label="t('governanceExpansion.role')"
              :empty-label="t('governanceExpansion.allRoles')"
            />
            <p v-if="selectedPlayer" class="text-sm">{{ selectedPlayer.name }}</p>
            <p v-if="filters.permission" class="text-sm">{{ filters.permission }}</p>
            <button class="ks-command-link" :disabled="filterBusy" @click="filterAssignments()">
              {{ t('governanceExpansion.search') }}
            </button>
            <button
              class="ks-command-link ml-3"
              :disabled="filterBusy"
              @click="filterAssignments(true)"
            >
              {{ t('common.cancel') }}
            </button>
            <p v-if="filterFailed" role="alert">
              {{ t('governanceExpansion.historyUnavailable') }}
            </p>
          </div>
          <div v-if="filteredAssignments.length" class="mt-4 divide-y divide-[var(--ks-border)]">
            <article
              v-for="assignment in filteredAssignments"
              :key="assignment.id"
              class="flex flex-wrap items-center justify-between gap-4 py-4"
            >
              <div>
                <p class="font-semibold">{{ assignment.player.name }}</p>
                <p class="text-sm text-[var(--ks-gold)]">
                  {{ assignment.role.name }} · {{ roleStateLabel(assignment.state) }}
                </p>
                <p v-if="assignment.expiresAt" class="text-xs text-[var(--ks-text-muted)]">
                  {{ t('governanceExpansion.expiresAt') }}: {{ formatDate(assignment.expiresAt) }}
                </p>
                <p v-if="assignment.reason" class="text-xs text-[var(--ks-text-muted)]">
                  {{ assignment.reason }}
                </p>
              </div>
              <button
                type="button"
                class="rounded border border-red-400/40 px-3 py-2 text-sm text-red-200"
                @click="removeRole(assignment)"
              >
                {{ t('governanceExpansion.remove') }}
              </button>
            </article>
          </div>
          <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">
            {{ t('governanceExpansion.noAssignments') }}
          </p>
          <GovernanceCataloguePager
            :page="pages.assignments"
            kind="assignments"
            :scope="scope"
            :label="t('governanceExpansion.assignment')"
          />
        </section>

        <section class="ks-surface p-5">
          <h2 class="ks-display text-xl font-semibold">{{ t('governanceExpansion.bulkTitle') }}</h2>
          <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
            {{ t('governanceExpansion.bulkHelp') }}
          </p>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <GovernanceChoicePicker
              id="bulk-role"
              v-model="bulkRoleId"
              :disabled="bulkCommitting"
              kind="roles"
              :scope="scope"
              :label="t('governanceExpansion.role')"
              :empty-label="t('common.none')"
            />
            <select v-model="bulkOperation" class="ks-input" :disabled="bulkCommitting">
              <option value="assign">{{ t('governanceExpansion.assign') }}</option>
              <option value="remove">{{ t('governanceExpansion.remove') }}</option>
            </select>
          </div>
          <GovernanceChoicePicker
            id="bulk-player"
            v-model="bulkPickerId"
            kind="players"
            :scope="scope"
            :label="t('governanceExpansion.governor')"
            :empty-label="t('common.none')"
            :disabled="bulkCommitting || selectedChoices.length >= 50"
            @chosen="addBulkPlayer"
          />
          <p class="mt-3 text-sm">
            {{ t('governanceExpansion.selected', { count: selectedChoices.length }) }}
          </p>
          <ul class="mt-2 space-y-2">
            <li
              v-for="player in selectedChoices"
              :key="player.id"
              class="flex justify-between gap-3 text-sm"
            >
              {{ player.name
              }}<button
                type="button"
                :aria-label="t('common.cancel') + ' ' + player.name"
                :disabled="bulkCommitting"
                @click="removeBulkPlayer(player.id)"
              >
                ×
              </button>
            </li>
          </ul>
          <p v-if="bulkError" role="alert" class="mt-3 text-sm text-red-200">{{ bulkError }}</p>
          <input
            v-model="bulkReason"
            :disabled="bulkCommitting"
            maxlength="500"
            class="ks-input mt-3"
            :placeholder="t('governanceExpansion.reason')"
          />
          <div class="mt-3 flex gap-3">
            <button
              class="ks-command-button"
              :disabled="
                previewing || bulkCommitting || !bulkRoleId || selectedPlayers.length === 0
              "
              @click="previewBulk"
            >
              {{ t('governanceExpansion.preview') }}</button
            ><button
              v-if="bulkPreview"
              class="ks-command-button"
              :disabled="bulkCommitting"
              @click="commitBulk"
            >
              {{ t('governanceExpansion.commit') }}
            </button>
          </div>
          <div v-if="bulkPreview" class="mt-3 text-sm">
            <p>
              {{ t('governanceExpansion.eligible', { count: bulkPreview.eligible.length }) }} ·
              {{
                t('governanceExpansion.ineligible', {
                  count: Object.keys(bulkPreview.ineligible).length,
                })
              }}
            </p>
            <ul
              v-if="Object.keys(bulkPreview.ineligible).length"
              class="mt-2 list-disc pl-5 text-[var(--ks-text-muted)]"
            >
              <li v-for="(reason, playerId) in bulkPreview.ineligible" :key="playerId">
                {{ selectedChoices.find((p) => p.id === playerId)?.name ?? playerId }} —
                {{ reason }}
              </li>
            </ul>
          </div>
        </section>

        <section class="ks-surface p-5">
          <h2 class="ks-display text-xl font-semibold">
            {{ t('governanceExpansion.rolesTitle') }}
          </h2>
          <div class="mt-3 grid gap-3 md:grid-cols-2">
            <article
              v-for="role in roles"
              :key="role.id"
              class="rounded border border-[var(--ks-border)] p-4"
              :class="role.archivedAt ? 'opacity-60' : ''"
            >
              <div class="flex justify-between gap-3">
                <div>
                  <p class="font-semibold">{{ role.name }}</p>
                  <p class="text-xs text-[var(--ks-text-muted)]">
                    {{ role.key }} ·
                    {{
                      role.isSystem
                        ? t('governanceExpansion.systemRole')
                        : t('governanceExpansion.customRoleBadge')
                    }}
                  </p>
                </div>
                <button
                  v-if="!role.isSystem && !role.archivedAt"
                  class="text-sm text-red-200"
                  @click="archiveRole(role)"
                >
                  {{ t('governanceExpansion.archive') }}
                </button>
              </div>
              <p v-if="role.description" class="mt-2 text-sm text-[var(--ks-text-secondary)]">
                {{ role.description }}
              </p>
              <div class="mt-2 flex flex-wrap gap-1">
                <span
                  v-for="permission in role.permissions"
                  :key="permission.key"
                  class="rounded border border-[var(--ks-border)] px-2 py-1 text-xs"
                  >{{ permission.key }} · {{ permission.owner }}</span
                >
              </div>
            </article>
          </div>
          <GovernanceCataloguePager
            :page="pages.roles"
            kind="roles"
            :scope="scope"
            :label="t('governanceExpansion.rolesTitle')"
          />
        </section>
      </div>
    </div>
    <ConfirmActionDialog v-bind="dialog" @confirm="confirmAction" @cancel="cancelConfirmation" />
  </AppLayout>
</template>
