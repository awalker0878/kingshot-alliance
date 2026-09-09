<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import CursorPagination from '@/components/ui/CursorPagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type HistoryItem = {
  id: string;
  type: string;
  occurredAt: string | null;
  actor: { playerId: string; name: string } | null;
  metadata: Record<string, unknown>;
  source: string;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  player: { id: string; name: string; gamePlayerId: string | null };
  historyPage: {
    items: HistoryItem[];
    nextCursor: string | null;
    hasMore: boolean;
    pageSize: number;
    isFirstPage: boolean;
  };
}>();

const { t, formatDate } = useLocale();
const loading = ref(false);
function nextPage() {
  if (!props.historyPage.nextCursor || loading.value) return;
  loading.value = true;
  router.get(
    `/alliance/members/${props.player.id}/history`,
    { cursor: props.historyPage.nextCursor },
    {
      preserveScroll: true,
      onFinish: () => {
        loading.value = false;
      },
    },
  );
}
</script>

<template>
  <Head
    :title="`${t('allianceExpansion.memberHistoryTitle', { name: player.name })} · ${alliance.name}`"
  />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('allianceExpansion.memberHistoryEyebrow')"
      :title="t('allianceExpansion.memberHistoryTitle', { name: player.name })"
      :subtitle="t('allianceExpansion.memberHistorySubtitle')"
      image="/images/kingshot/v4/roster-hall.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/history" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navHistory') }}
        </Link>
        <Link href="/alliance/members/bulk" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navBulk') }}
        </Link>
      </template>
    </RoomBanner>

    <section class="ks-surface mt-6 p-5">
      <dl class="grid gap-4 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-[var(--ks-muted)]">{{ t('allianceExpansion.candidate') }}</dt>
          <dd class="mt-1 font-semibold">{{ player.name }}</dd>
        </div>
        <div>
          <dt class="text-sm text-[var(--ks-muted)]">{{ t('allianceExpansion.gamePlayerId') }}</dt>
          <dd class="mt-1 font-semibold">{{ player.gamePlayerId ?? t('common.none') }}</dd>
        </div>
      </dl>
    </section>

    <section class="mt-6 space-y-4" aria-labelledby="member-history-list">
      <h2 id="member-history-list" class="sr-only">
        {{ t('allianceExpansion.memberHistoryTitle', { name: player.name }) }}
      </h2>
      <article v-for="item in historyPage.items" :key="item.id" class="ks-surface p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="ks-kicker">{{ t('allianceExpansion.event') }}</p>
            <h3 class="mt-1 font-semibold">{{ item.type }}</h3>
          </div>
          <p class="text-sm text-[var(--ks-muted)]">
            {{ item.occurredAt ? formatDate(item.occurredAt) : t('common.none') }}
          </p>
        </div>
        <p class="mt-3 text-sm text-[var(--ks-muted)]">
          {{ t('allianceExpansion.actor') }}: {{ item.actor?.name ?? t('common.none') }}
        </p>
        <details v-if="Object.keys(item.metadata).length" class="mt-4">
          <summary class="cursor-pointer text-sm font-semibold">
            {{ t('allianceExpansion.details') }}
          </summary>
          <pre class="mt-3 overflow-x-auto rounded-[var(--ks-radius-sm)] bg-black/20 p-3 text-xs">{{
            JSON.stringify(item.metadata, null, 2)
          }}</pre>
        </details>
      </article>
      <div v-if="historyPage.items.length === 0" class="ks-fantasy-empty">
        {{ t('allianceExpansion.noHistory') }}
      </div>
      <CursorPagination
        :summary="
          t('common.historyItemsOnPage', {
            count: historyPage.items.length,
            pageSize: historyPage.pageSize,
          })
        "
        :is-first-page="historyPage.isFirstPage"
        :first-page-href="`/alliance/members/${player.id}/history`"
        :has-more="historyPage.hasMore"
        :busy="loading"
        @next="nextPage"
      />
    </section>
  </AppLayout>
</template>
