<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import { useLocale } from '@/localization';
type Permission = { key: string; owner: string; description: string };
type Role = {
  id: string;
  key: string;
  name: string;
  description: string | null;
  isSystem: boolean;
  permissions: Permission[];
  effectiveAssignmentCount: number;
};
type Holder = { playerId: string; playerName: string; roles: string[] };
defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  kingdom: { id: string; number: number };
  roles: Role[];
  permissions: Permission[];
  selectedPermission: string | null;
  holders: Holder[];
}>();
const { t } = useLocale();
function choose(event: Event): void {
  const value = (event.target as HTMLSelectElement).value;
  router.get(
    '/alliance/settings/kingdom/governance/authority',
    value ? { permission: value } : {},
    { preserveState: true, replace: true },
  );
}
</script>
<template>
  <Head :title="`${t('governanceExpansion.authorityTitle')} · #${kingdom.number}`" />
  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('governanceExpansion.eyebrow')"
      :title="t('governanceExpansion.authorityTitle')"
      :subtitle="t('governanceExpansion.authorityHelp')"
      image="/images/kingshot/v4/kingdom-map.svg"
      compact
      ><template #actions
        ><Link href="/alliance/settings/kingdom/roles" class="ks-command-link">{{
          t('governanceExpansion.navRoles')
        }}</Link
        ><Link href="/alliance/settings/kingdom/governance/history" class="ks-command-link">{{
          t('governanceExpansion.navHistory')
        }}</Link
        ><Link href="/alliance/settings/kingdom/governance/health" class="ks-command-link">{{
          t('governanceExpansion.navHealth')
        }}</Link></template
      ></RoomBanner
    >
    <section class="ks-surface mt-5 p-5">
      <label class="text-sm font-semibold"
        >{{ t('governanceExpansion.selectPermission')
        }}<select class="ks-input mt-2" :value="selectedPermission ?? ''" @change="choose">
          <option value="">—</option>
          <option v-for="permission in permissions" :key="permission.key" :value="permission.key">
            {{ permission.key }} · {{ permission.owner }}
          </option>
        </select></label
      >
      <div v-if="selectedPermission" class="mt-5">
        <h2 class="ks-display text-xl font-semibold">
          {{ t('governanceExpansion.whoHasAuthority') }}
        </h2>
        <div v-if="holders.length" class="mt-3 divide-y divide-[var(--ks-border)]">
          <div v-for="holder in holders" :key="holder.playerId" class="py-3">
            <p class="font-semibold">{{ holder.playerName }}</p>
            <p class="text-sm text-[var(--ks-text-muted)]">
              {{ t('governanceExpansion.grantedBy') }}: {{ holder.roles.join(', ') }}
            </p>
          </div>
        </div>
        <p v-else class="mt-3 text-sm text-[var(--ks-text-muted)]">
          {{ t('governanceExpansion.noHolders') }}
        </p>
      </div>
    </section>
    <section class="mt-5 grid gap-4 lg:grid-cols-2">
      <article v-for="role in roles" :key="role.id" class="ks-surface p-5">
        <h2 class="font-semibold">{{ role.name }}</h2>
        <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
          {{ role.effectiveAssignmentCount }} effective · {{ role.key }}
        </p>
        <p v-if="role.description" class="mt-2 text-sm text-[var(--ks-text-secondary)]">
          {{ role.description }}
        </p>
        <table class="mt-3 w-full text-left text-sm">
          <thead>
            <tr>
              <th>{{ t('governanceExpansion.permissions') }}</th>
              <th>{{ t('governanceExpansion.permissionOwner') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="permission in role.permissions" :key="permission.key">
              <td class="py-1 pr-3">{{ permission.key }}</td>
              <td>{{ permission.owner }}</td>
            </tr>
          </tbody>
        </table>
      </article>
    </section>
  </AppLayout>
</template>
