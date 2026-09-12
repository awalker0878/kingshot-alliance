<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import GovernanceCataloguePager from '@/components/governance/GovernanceCataloguePager.vue';
import type { GovernancePage } from '@/components/governance/governancePages';
import AppLayout from '@/layouts/AppLayout.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import { useLocale } from '@/localization';
type Item = {
  id: string;
  type: string;
  occurredAt: string;
  actor: { playerId: string | null; name: string };
  metadata: Record<string, unknown>;
};
defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  kingdom: { id: string; number: number };
  items: Item[];
  page: GovernancePage;
  catalogueScope: string;
}>();
const { t, formatDate } = useLocale();
</script>
<template>
  <Head :title="`${t('governanceExpansion.historyTitle')} · #${kingdom.number}`" />
  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('governanceExpansion.eyebrow')"
      :title="t('governanceExpansion.historyTitle')"
      :subtitle="t('governanceExpansion.historyHelp')"
      image="/images/kingshot/v4/kingdom-map.svg"
      compact
      ><template #actions
        ><Link href="/alliance/settings/kingdom/roles" class="ks-command-link">{{
          t('governanceExpansion.navRoles')
        }}</Link
        ><Link href="/alliance/settings/kingdom/governance/authority" class="ks-command-link">{{
          t('governanceExpansion.navAuthority')
        }}</Link
        ><Link href="/alliance/settings/kingdom/governance/health" class="ks-command-link">{{
          t('governanceExpansion.navHealth')
        }}</Link></template
      ></RoomBanner
    >
    <section class="ks-surface mt-5 overflow-hidden">
      <article
        v-for="item in items"
        :key="item.id"
        class="border-b border-[var(--ks-border)] p-5 last:border-b-0"
      >
        <div class="flex flex-wrap justify-between gap-2">
          <div>
            <p class="font-semibold">{{ item.type }}</p>
            <p class="text-sm text-[var(--ks-text-muted)]">{{ item.actor.name }}</p>
          </div>
          <time class="text-sm text-[var(--ks-text-muted)]">{{ formatDate(item.occurredAt) }}</time>
        </div>
        <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
          <template v-for="(value, key) in item.metadata" :key="key"
            ><div v-if="value !== null && typeof value !== 'object'">
              <dt class="text-[var(--ks-text-muted)]">{{ key }}</dt>
              <dd>{{ value }}</dd>
            </div></template
          >
        </dl>
      </article>
      <p v-if="!items.length" class="p-5 text-sm text-[var(--ks-text-muted)]">
        {{ t('common.none') }}
      </p>
    </section>
    <GovernanceCataloguePager
      :page="page"
      kind="history"
      :scope="catalogueScope"
      :label="t('governanceExpansion.historyTitle')"
    />
  </AppLayout>
</template>
