<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import { useLocale } from '@/localization';
type Issue = {
  severity: 'warning' | 'critical';
  code: string;
  message: string;
  repairable: boolean;
};
defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  kingdom: { id: string; number: number };
  health: { status: 'healthy' | 'degraded' | 'critical'; issues: Issue[] };
}>();
const { t } = useLocale();
function reconcile(): void {
  router.post('/alliance/settings/kingdom/governance/reconcile', {}, { preserveScroll: true });
}
</script>
<template>
  <Head :title="`${t('governanceExpansion.healthTitle')} · #${kingdom.number}`" />
  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('governanceExpansion.eyebrow')"
      :title="t('governanceExpansion.healthTitle')"
      :subtitle="t('governanceExpansion.healthHelp')"
      image="/images/kingshot/v4/kingdom-map.svg"
      compact
      ><template #actions
        ><Link href="/alliance/settings/kingdom/roles" class="ks-command-link">{{
          t('governanceExpansion.navRoles')
        }}</Link
        ><Link href="/alliance/settings/kingdom/governance/authority" class="ks-command-link">{{
          t('governanceExpansion.navAuthority')
        }}</Link
        ><Link href="/alliance/settings/kingdom/governance/history" class="ks-command-link">{{
          t('governanceExpansion.navHistory')
        }}</Link></template
      ></RoomBanner
    >
    <section class="ks-surface-gold mt-5 p-5">
      <p class="text-xs text-[var(--ks-text-muted)] uppercase">
        {{ t('governanceExpansion.healthTitle') }}
      </p>
      <p class="ks-display mt-2 text-3xl font-bold">
        {{ t(`governanceExpansion.${health.status}`) }}
      </p>
    </section>
    <section class="ks-surface mt-5 p-5">
      <div v-if="health.issues.length" class="space-y-3">
        <article
          v-for="issue in health.issues"
          :key="issue.code"
          class="rounded border border-[var(--ks-border)] p-4"
        >
          <div class="flex gap-3">
            <strong>{{ issue.severity.toUpperCase() }}</strong>
            <div>
              <p class="font-semibold">{{ issue.code }}</p>
              <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">{{ issue.message }}</p>
              <p v-if="issue.repairable" class="mt-1 text-xs text-[var(--ks-gold)]">
                {{ t('governanceExpansion.repairable') }}
              </p>
            </div>
          </div>
        </article>
      </div>
      <p v-else>{{ t('governanceExpansion.healthy') }}</p>
      <div class="mt-5 border-t border-[var(--ks-border)] pt-5">
        <p class="text-sm text-[var(--ks-text-secondary)]">
          {{ t('governanceExpansion.reconcileHelp') }}
        </p>
        <button class="ks-command-button mt-3" @click="reconcile">
          {{ t('governanceExpansion.reconcile') }}
        </button>
      </div>
    </section>
  </AppLayout>
</template>
