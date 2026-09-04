<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
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
  roles: Role[];
  permissions: string[];
}>();

const { t, formatNumber } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
const createForm = useForm({ name: '', permissions: [] as string[] });
const drafts = reactive(
  Object.fromEntries(
    props.roles.map((role) => [
      role.id,
      { name: role.name, permissions: [...role.permissions] as string[] },
    ]),
  ) as Record<string, { name: string; permissions: string[] }>,
);

function createRole(): void {
  createForm.post('/alliance/roles', {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  });
}

function saveRole(role: Role): void {
  router.patch(`/alliance/roles/${role.id}`, drafts[role.id], { preserveScroll: true });
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

            <div v-if="!role.system && !role.archivedAt" class="mt-5 grid gap-4">
              <div>
                <label class="text-sm font-semibold" :for="`role-name-${role.id}`">
                  {{ t('allianceExpansion.roleName') }}
                </label>
                <input
                  :id="`role-name-${role.id}`"
                  v-model="drafts[role.id]!.name"
                  class="ks-input mt-2"
                  maxlength="100"
                />
              </div>

              <fieldset>
                <legend class="text-sm font-semibold">
                  {{ t('allianceExpansion.permissions') }}
                </legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                  <label
                    v-for="permission in permissions"
                    :key="permission"
                    class="flex items-center gap-2 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm"
                  >
                    <input
                      v-model="drafts[role.id]!.permissions"
                      type="checkbox"
                      :value="permission"
                    />
                    <span>{{ permission }}</span>
                  </label>
                </div>
              </fieldset>

              <div class="flex flex-wrap gap-2">
                <AppButton variant="secondary" @click="saveRole(role)">
                  {{ t('allianceExpansion.updateRole') }}
                </AppButton>
                <button
                  type="button"
                  class="rounded-[var(--ks-radius-sm)] border border-red-400/20 px-4 py-2 text-sm font-semibold text-red-300 transition hover:border-red-400/40"
                  @click="archiveRole(role)"
                >
                  {{ t('allianceExpansion.archiveRole') }}
                </button>
              </div>
            </div>

            <div v-else class="mt-4 flex flex-wrap gap-2">
              <span v-for="permission in role.permissions" :key="permission" class="ks-chip">
                {{ permission }}
              </span>
            </div>
          </article>
        </div>
        <div v-else class="ks-fantasy-empty mt-4">{{ t('allianceExpansion.noRoles') }}</div>
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
                <span>{{ permission }}</span>
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
