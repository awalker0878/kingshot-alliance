<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

import AllianceRoleEditor from '@/components/alliance/AllianceRoleEditor.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import CursorPagination from '@/components/ui/CursorPagination.vue';
import FormError from '@/components/ui/FormError.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Role = {
  id: string;
  key: string;
  name: string;
  system: boolean;
  archivedAt: string | null;
  permissions: string[];
  memberCount: number;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  rolePage: {
    items: Role[];
    nextCursor: string | null;
    hasMore: boolean;
    isFirstPage: boolean;
    pageSize: number;
  };
  filters: { status: 'active' | 'archived'; q: string };
  permissions: string[];
}>();

const { t, formatNumber } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
const createForm = useForm({ name: '', permissions: [] as string[] });
const roles = computed(() => props.rolePage.items);
const filters = reactive({ ...props.filters });
watch(
  () => props.filters,
  (current) => Object.assign(filters, current),
);
const firstPageHref = computed(
  () => '/alliance/roles?' + new URLSearchParams(props.filters).toString(),
);

function filterRoles(): void {
  router.get('/alliance/roles', filters, { preserveState: true, preserveScroll: true });
}

function nextPage(): void {
  if (!props.rolePage.nextCursor) return;
  router.get(
    '/alliance/roles',
    { ...props.filters, cursor: props.rolePage.nextCursor },
    {
      preserveState: true,
      preserveScroll: true,
    },
  );
}

function createRole(): void {
  createForm.post('/alliance/roles', {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  });
}

function archiveRole(role: Role): void {
  requestConfirmation({
    id: `archive-role-${role.id}`,
    title: t('allianceExpansion.archiveRole'),
    description: t('allianceExpansion.archiveRoleHelp'),
    confirmLabel: t('allianceExpansion.archiveRole'),
    cancelLabel: t('common.cancel'),
    perform: (finish) =>
      router.delete(`/alliance/roles/${role.id}`, {
        preserveScroll: true,
        onFinish: finish,
      }),
  });
}
</script>

<template>
  <Head :title="`${t('allianceExpansion.rolesTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('allianceExpansion.rolesEyebrow')"
      :title="t('allianceExpansion.rolesTitle')"
      :subtitle="t('allianceExpansion.rolesSubtitle')"
      image="/images/kingshot/v4/alliance-hall.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/settings" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navSettings') }}
        </Link>
        <Link href="/alliance/members/bulk" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navBulk') }}
        </Link>
      </template>
    </RoomBanner>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <section class="min-w-0" aria-labelledby="role-list-title">
        <h2 id="role-list-title" class="ks-display text-2xl font-semibold">
          {{ t('allianceExpansion.rolesTitle') }}
        </h2>

        <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="filterRoles">
          <div class="min-w-0 flex-1">
            <label for="role-search" class="block text-sm font-semibold">{{
              t('allianceExpansion.roleSearch')
            }}</label>
            <input
              id="role-search"
              v-model="filters.q"
              type="search"
              maxlength="100"
              class="ks-input mt-2 w-full"
            />
          </div>
          <div>
            <label for="role-status" class="block text-sm font-semibold">{{
              t('allianceExpansion.roleStatus')
            }}</label>
            <select id="role-status" v-model="filters.status" class="ks-input mt-2">
              <option value="active">{{ t('allianceExpansion.activeRole') }}</option>
              <option value="archived">{{ t('allianceExpansion.archivedRole') }}</option>
            </select>
          </div>
          <AppButton type="submit" variant="secondary">{{
            t('allianceExpansion.findRoles')
          }}</AppButton>
        </form>

        <div v-if="roles.length" class="mt-4 space-y-4">
          <article v-for="role in roles" :key="role.id" class="ks-surface p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="ks-display text-xl font-semibold">{{ role.name }}</h3>
                  <span class="ks-status" :data-tone="role.archivedAt ? 'warning' : 'success'">
                    {{
                      role.archivedAt
                        ? t('allianceExpansion.archivedRole')
                        : t('allianceExpansion.activeRole')
                    }}
                  </span>
                  <span class="ks-chip">
                    {{
                      role.system
                        ? t('allianceExpansion.systemRole')
                        : t('allianceExpansion.customRole')
                    }}
                  </span>
                </div>
                <p class="mt-1 text-sm text-[var(--ks-muted)]">
                  {{
                    t('allianceExpansion.memberCount', { count: formatNumber(role.memberCount) })
                  }}
                </p>
              </div>
              <code class="text-xs text-[var(--ks-muted)]">{{ role.key }}</code>
            </div>

            <AllianceRoleEditor
              v-if="!role.system && !role.archivedAt"
              :role="role"
              :permissions="permissions"
              @archive="archiveRole(role)"
            />

            <div v-else class="mt-4 flex flex-wrap gap-2">
              <span v-for="permission in role.permissions" :key="permission" class="ks-chip">
                {{ t('allianceExpansion.permissionLabels.' + permission) }}
              </span>
            </div>
          </article>
        </div>
        <div v-else class="ks-fantasy-empty mt-4">{{ t('allianceExpansion.noRoles') }}</div>
        <CursorPagination
          :summary="t('allianceExpansion.rolesOnPage', { count: formatNumber(roles.length) })"
          :is-first-page="rolePage.isFirstPage"
          :first-page-href="firstPageHref"
          :has-more="rolePage.hasMore"
          @next="nextPage"
        />
      </section>

      <aside class="ks-surface h-fit p-5" aria-labelledby="create-role-title">
        <h2 id="create-role-title" class="ks-display text-xl font-semibold">
          {{ t('allianceExpansion.createRole') }}
        </h2>
        <form class="mt-4 space-y-4" @submit.prevent="createRole">
          <div>
            <label class="text-sm font-semibold" for="new-role-name">
              {{ t('allianceExpansion.roleName') }}
            </label>
            <input
              id="new-role-name"
              v-model="createForm.name"
              class="ks-input mt-2"
              maxlength="100"
            />
            <FormError :message="createForm.errors.name" />
          </div>

          <fieldset>
            <legend class="text-sm font-semibold">{{ t('allianceExpansion.permissions') }}</legend>
            <div class="mt-2 space-y-2">
              <label
                v-for="permission in permissions"
                :key="permission"
                class="flex items-center gap-2 text-sm"
              >
                <input v-model="createForm.permissions" type="checkbox" :value="permission" />
                <span>{{ t('allianceExpansion.permissionLabels.' + permission) }}</span>
              </label>
            </div>
            <FormError :message="createForm.errors.permissions" />
          </fieldset>

          <AppButton type="submit" :disabled="createForm.processing">
            {{ t('allianceExpansion.createRole') }}
          </AppButton>
        </form>
      </aside>
    </div>

    <ConfirmActionDialog v-bind="dialog" @confirm="confirmAction" @cancel="cancelConfirmation" />
  </AppLayout>
</template>
