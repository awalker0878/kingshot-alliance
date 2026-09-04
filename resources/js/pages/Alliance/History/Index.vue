<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type TimelineItem = {
  id: string;
  type: string;
  occurredAt: string | null;
  actor: { playerId: string; name: string } | null;
  metadata: Record<string, unknown>;
  source: string;
  handoff: string;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  timeline: { items: TimelineItem[]; nextCursor: string | null };
  filters: { capability: string | null; actorPlayerId: string | null };
}>();

const { t, formatDate } = useLocale();
const form = useForm({
  capability: props.filters.capability ?? '',
  actor_player_id: props.filters.actorPlayerId ?? '',
});

const capabilityOptions = [
  'alliance',
  'membership',
  'invitation',
  'recruitment',
  'content',
  'integration',
];
const olderHref = computed(() => {
  if (!props.timeline.nextCursor) return null;
  const params = new URLSearchParams();
  params.set('before', props.timeline.nextCursor);
  if (props.filters.capability) params.set('capability', props.filters.capability);
  if (props.filters.actorPlayerId) params.set('actor_player_id', props.filters.actorPlayerId);
  return `/alliance/history?${params.toString()}`;
});

function applyFilters(): void {
  form.get('/alliance/history', {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  });
}
</script>

<template>
  <Head :title="`${t('allianceExpansion.historyTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('allianceExpansion.historyEyebrow')"
      :title="t('allianceExpansion.historyTitle')"
      :subtitle="t('allianceExpansion.historySubtitle')"
      image="/images/kingshot/v4/alliance-hall.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/settings" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navSettings') }}
        </Link>
        <Link
          href="/alliance/roster/reconciliation"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('allianceExpansion.navReconciliation') }}
        </Link>
      </template>
    </RoomBanner>

    <form
      class="ks-surface mt-6 grid gap-4 p-5 md:grid-cols-[1fr_1fr_auto]"
      @submit.prevent="applyFilters"
    >
      <div>
        <label class="text-sm font-semibold" for="history-capability">
          {{ t('allianceExpansion.capability') }}
        </label>
        <select id="history-capability" v-model="form.capability" class="ks-input mt-2">
          <option value="">{{ t('common.all') }}</option>
          <option v-for="capability in capabilityOptions" :key="capability" :value="capability">
            {{ capability }}
          </option>
        </select>
      </div>
      <div>
        <label class="text-sm font-semibold" for="history-actor">
          {{ t('allianceExpansion.actorPlayerId') }}
        </label>
        <input id="history-actor" v-model="form.actor_player_id" class="ks-input mt-2" />
      </div>
      <div class="flex items-end gap-2">
        <AppButton type="submit" :disabled="form.processing">
          {{ t('allianceExpansion.applyFilters') }}
        </AppButton>
        <Link href="/alliance/history" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.clearFilters') }}
        </Link>
      </div>
    </form>

    <section class="mt-6" aria-labelledby="governance-timeline-title">
      <h2 id="governance-timeline-title" class="sr-only">
        {{ t('allianceExpansion.historyTitle') }}
      </h2>
      <div v-if="timeline.items.length" class="space-y-4">
        <article v-for="item in timeline.items" :key="item.id" class="ks-surface p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('allianceExpansion.event') }}</p>
              <h3 class="mt-1 font-semibold">{{ item.type }}</h3>
            </div>
            <p class="text-sm text-[var(--ks-muted)]">
              {{ item.occurredAt ? formatDate(item.occurredAt) : t('common.none') }}
            </p>
          </div>

          <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt class="text-[var(--ks-muted)]">{{ t('allianceExpansion.actor') }}</dt>
              <dd class="mt-1">{{ item.actor?.name ?? t('common.none') }}</dd>
            </div>
            <div>
              <dt class="text-[var(--ks-muted)]">{{ t('allianceExpansion.source') }}</dt>
              <dd class="mt-1">{{ item.source }}</dd>
            </div>
          </dl>

          <details v-if="Object.keys(item.metadata).length" class="mt-4">
            <summary class="cursor-pointer text-sm font-semibold">
              {{ t('allianceExpansion.details') }}
            </summary>
            <pre
              class="mt-3 overflow-x-auto rounded-[var(--ks-radius-sm)] bg-black/20 p-3 text-xs"
              >{{ JSON.stringify(item.metadata, null, 2) }}</pre>
          </details>

          <div class="mt-4">
            <Link :href="item.handoff" class="ks-command-link" data-variant="secondary">
              {{ t('allianceExpansion.openOwner') }}
            </Link>
          </div>
        </article>
      </div>
      <div v-else class="ks-fantasy-empty">{{ t('allianceExpansion.noHistory') }}</div>

      <div v-if="olderHref" class="mt-5 flex justify-center">
        <Link :href="olderHref" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.loadOlder') }}
        </Link>
      </div>
    </section>
  </AppLayout>
</template>
