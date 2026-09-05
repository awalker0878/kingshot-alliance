<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import ActionNotice from '@/components/ui/ActionNotice.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Governor = {
  id: string;
  name: string;
  gamePlayerId: string | null;
  kingdomNumber: number | null;
};
type RewardItem = {
  type: string;
  key: string | null;
  label: string | null;
  quantity: number | null;
  durationSeconds: number | null;
};
type Reward = { state: string; items: RewardItem[] };
type WorkspaceCode = {
  id: string;
  code: string;
  status: string;
  statusRevision: number;
  expiresAt: string | null;
  expiresRevision: number;
  sourceCount: number;
  reward: Reward;
  personalState: { state: string; snoozedUntil: string | null; remindAt: string | null } | null;
  redemptions: Array<{ playerId: string; status: string; nextAttemptAt: string | null }>;
};
type SessionItem = {
  id: string;
  giftCodeId: string;
  code: string;
  playerId: string;
  playerName: string;
  gamePlayerId: string | null;
  kingdomNumber: number | null;
  sequence: number;
  state: string;
  unavailableReason: string | null;
  skipReason: string | null;
  expiresAt: string | null;
  reward: Reward;
};
type Session = {
  id: string;
  mode: string;
  status: string;
  totalItems: number;
  completedItems: number;
  skippedItems: number;
  failedItems: number;
  lastActivityAt: string;
  items: SessionItem[];
};
type Signal = {
  sampleCount: number;
  distinctAccounts: number;
  successCount: number;
  successRate: number;
  statusCounts: Record<string, number>;
  lastSuccessAt: string | null;
  windowHours: number;
};

const props = defineProps<{
  user: { name: string; email: string };
  views: string[];
  view: string;
  counts: Record<string, number>;
  codes: WorkspaceCode[];
  governors: Governor[];
  pagination: { nextCursor: string | null; previousCursor: string | null };
  session: Session | null;
  currentSignal: Signal | null;
  officialRedemptionUrl: string;
}>();

const { t, formatDate, formatNumber } = useLocale();
const selectedCodeIds = ref<string[]>([]);
const selectedPlayerIds = ref<string[]>(props.governors.filter((item) => item.gamePlayerId).map((item) => item.id));
const selectedOutcome = ref('redeemed');
const busy = ref(false);
const operationError = ref<string | null>(null);
const copied = ref<string | null>(null);
const outcomes = [
  'redeemed',
  'already_redeemed',
  'invalid',
  'expired',
  'wrong_kingdom',
  'rate_limited',
  'temporarily_unavailable',
  'permanent_failure',
];

const currentItem = computed(() =>
  props.session?.items.find((item) => ['ready', 'awaiting_confirmation'].includes(item.state)) ?? null,
);
const sessionDone = computed(() => props.session?.status === 'completed');
const sessionWaiting = computed(
  () => props.session?.items.filter((item) => item.state === 'retry_wait').length ?? 0,
);

function viewUrl(view: string, cursor?: string | null): string {
  const params = new URLSearchParams({ view });
  if (cursor) params.set('cursor', cursor);
  if (props.session?.id) params.set('session', props.session.id);
  return `/gift-codes/workspace?${params.toString()}`;
}

function createRun(mode: string, giftCodeIds: string[] = [], playerIds: string[] = []): void {
  if (busy.value) return;
  operationError.value = null;
  busy.value = true;
  router.post(
    '/gift-codes/workspace/sessions',
    { mode, gift_code_ids: giftCodeIds, player_ids: playerIds },
    {
      preserveScroll: true,
      onError: captureError,
      onFinish: () => (busy.value = false),
    },
  );
}

function updateState(
  giftCodeId: string,
  state: string,
  snoozedUntil: string | null = null,
  remindAt: string | null = null,
): void {
  router.post(
    `/gift-codes/workspace/state/${giftCodeId}`,
    { state, snoozed_until: snoozedUntil, remind_at: remindAt },
    { preserveScroll: true, onError: captureError },
  );
}

function inHours(hours: number): string {
  return new Date(Date.now() + hours * 60 * 60 * 1000).toISOString();
}

function prepare(item: SessionItem): void {
  if (!props.session || busy.value) return;
  busy.value = true;
  router.post(
    `/gift-codes/workspace/sessions/${props.session.id}/items/${item.id}/prepare`,
    {},
    { preserveScroll: true, onError: captureError, onFinish: () => (busy.value = false) },
  );
}

function record(item: SessionItem): void {
  if (!props.session || busy.value) return;
  busy.value = true;
  router.post(
    `/gift-codes/workspace/sessions/${props.session.id}/items/${item.id}/result`,
    { result: selectedOutcome.value },
    { preserveScroll: true, onError: captureError, onFinish: () => (busy.value = false) },
  );
}

function skip(item: SessionItem): void {
  if (!props.session || busy.value) return;
  busy.value = true;
  router.post(
    `/gift-codes/workspace/sessions/${props.session.id}/items/${item.id}/skip`,
    { reason: 'user_skipped' },
    { preserveScroll: true, onError: captureError, onFinish: () => (busy.value = false) },
  );
}

function abandon(): void {
  if (!props.session || busy.value) return;
  busy.value = true;
  router.post(
    `/gift-codes/workspace/sessions/${props.session.id}/abandon`,
    {},
    { preserveScroll: true, onError: captureError, onFinish: () => (busy.value = false) },
  );
}

function captureError(errors: Record<string, string>): void {
  operationError.value = Object.values(errors)[0] ?? t('giftCodes.operationFailed');
}

function copyValue(key: string, value: string): void {
  navigator.clipboard
    .writeText(value)
    .then(() => {
      copied.value = key;
      window.setTimeout(() => {
        if (copied.value === key) copied.value = null;
      }, 1800);
    })
    .catch(() => (operationError.value = t('giftCodes.workspace.copyFailed')));
}

function rewardLabel(item: RewardItem): string {
  const label = item.label ?? item.key ?? t(`giftCodes.workspace.rewards.${item.type}`);
  const quantity = item.quantity === null ? '' : ` × ${formatNumber(item.quantity)}`;
  return `${label}${quantity}`;
}

function statusTone(status: string): 'success' | 'warning' | 'danger' | 'info' {
  if (['completed', 'redeemed', 'already_redeemed', 'ready'].includes(status)) return 'success';
  if (['retry_wait', 'awaiting_confirmation', 'pending'].includes(status)) return 'warning';
  if (['unavailable', 'invalid', 'expired'].includes(status)) return 'danger';
  return 'info';
}
</script>

<template>
  <Head :title="t('giftCodes.workspace.title')" />

  <AppLayout :user="user">
    <RoomBanner
      :eyebrow="t('giftCodes.workspace.eyebrow')"
      :title="t('giftCodes.workspace.title')"
      :subtitle="t('giftCodes.workspace.subtitle')"
      image="/images/kingshot/v4/account-vault.svg"
    >
      <template #actions>
        <Link href="/gift-codes" class="ks-command-link" data-variant="secondary">
          {{ t('giftCodes.workspace.backToCatalogue') }}
        </Link>
      </template>
    </RoomBanner>

    <ActionNotice class="mt-4" :message="operationError" tone="danger" />

    <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <StatSeal :label="t('giftCodes.workspace.views.ready')" :value="formatNumber(counts.ready ?? 0)" icon="◆" />
      <StatSeal :label="t('giftCodes.workspace.views.expiring')" :value="formatNumber(counts.expiring ?? 0)" icon="!" />
      <StatSeal :label="t('giftCodes.workspace.views.retry_ready')" :value="formatNumber(counts.retry_ready ?? 0)" icon="↻" />
      <StatSeal :label="t('giftCodes.workspace.views.completed')" :value="formatNumber(counts.completed ?? 0)" icon="✓" tone="teal" />
    </section>

    <nav class="mt-5 flex flex-wrap gap-2" :aria-label="t('giftCodes.workspace.title')">
      <Link
        v-for="workspaceView in views"
        :key="workspaceView"
        :href="viewUrl(workspaceView)"
        class="ks-command-link"
        :data-variant="workspaceView === view ? undefined : 'secondary'"
      >
        {{ t(`giftCodes.workspace.views.${workspaceView}`) }}
        <span class="ml-1 text-xs">{{ formatNumber(counts[workspaceView] ?? 0) }}</span>
      </Link>
    </nav>

    <section v-if="session" class="ks-surface-gold mt-5 p-5 sm:p-6" aria-labelledby="gift-code-run-heading">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="ks-kicker">{{ t('giftCodes.workspace.currentRun') }}</p>
          <h2 id="gift-code-run-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('giftCodes.workspace.runProgress', { completed: session.completedItems, total: session.totalItems }) }}
          </h2>
          <p class="mt-2 text-xs text-[var(--ks-muted)]">
            {{ t('giftCodes.workspace.runFailures', { count: session.failedItems }) }} ·
            {{ t('giftCodes.workspace.runSkipped', { count: session.skippedItems }) }}
          </p>
        </div>
        <AppButton v-if="session.status === 'active'" type="button" variant="secondary" :busy="busy" @click="abandon">
          {{ t('giftCodes.workspace.abandonRun') }}
        </AppButton>
      </div>

      <div class="mt-4 h-2 overflow-hidden rounded-full bg-[var(--ks-surface-inset)]" aria-hidden="true">
        <div
          class="h-full bg-[var(--ks-gold-bright)] transition-all"
          :style="{ width: `${session.totalItems ? Math.round(((session.completedItems + session.skippedItems) / session.totalItems) * 100) : 0}%` }"
        />
      </div>

      <ActionNotice v-if="sessionDone" class="mt-4" :message="t('giftCodes.workspace.runComplete')" tone="success" />
      <ActionNotice
        v-else-if="!currentItem && sessionWaiting > 0"
        class="mt-4"
        :message="t('giftCodes.workspace.noExecutableItem')"
        tone="warning"
      />

      <article v-if="currentItem" class="mt-5 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="ks-kicker">{{ t('giftCodes.workspace.itemStep', { current: currentItem.sequence, total: session.totalItems }) }}</p>
            <h3 class="mt-1 font-mono text-xl font-bold tracking-[.08em] text-[var(--ks-gold-bright)]">{{ currentItem.code }}</h3>
            <p class="mt-2 text-sm text-[var(--ks-muted)]">
              {{ t('giftCodes.workspace.governor') }}: {{ currentItem.playerName }}
              <template v-if="currentItem.kingdomNumber"> · {{ t('giftCodes.kingdom', { kingdom: currentItem.kingdomNumber }) }}</template>
            </p>
          </div>
          <span class="ks-status" :data-tone="statusTone(currentItem.state)">{{ t(`giftCodes.workspace.states.${currentItem.state}`) }}</span>
        </div>

        <div class="mt-4">
          <p class="ks-kicker">{{ t('giftCodes.workspace.reward') }}</p>
          <ul v-if="currentItem.reward.items.length" class="mt-2 grid gap-2 sm:grid-cols-2">
            <li v-for="(reward, index) in currentItem.reward.items" :key="`${reward.type}:${reward.key}:${index}`" class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-3 text-sm">
              {{ rewardLabel(reward) }}
            </li>
          </ul>
          <p v-else class="mt-2 text-sm text-[var(--ks-muted)]">{{ t('giftCodes.workspace.rewardUnknown') }}</p>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <AppButton type="button" variant="secondary" :disabled="!currentItem.gamePlayerId" @click="copyValue(`player:${currentItem.id}`, currentItem.gamePlayerId ?? '')">
            {{ copied === `player:${currentItem.id}` ? t('giftCodes.copied') : t('giftCodes.workspace.copyPlayerId') }}
          </AppButton>
          <AppButton type="button" variant="secondary" @click="copyValue(`code:${currentItem.id}`, currentItem.code)">
            {{ copied === `code:${currentItem.id}` ? t('giftCodes.copied') : t('giftCodes.workspace.copyCode') }}
          </AppButton>
          <a :href="officialRedemptionUrl" target="_blank" rel="noreferrer" class="ks-command-link">
            {{ t('giftCodes.workspace.openOfficialCenter') }}
          </a>
        </div>

        <div v-if="currentItem.state === 'ready'" class="mt-4 flex flex-wrap gap-2">
          <AppButton type="button" :busy="busy" @click="prepare(currentItem)">{{ t('giftCodes.workspace.prepareHandoff') }}</AppButton>
          <AppButton type="button" variant="secondary" :busy="busy" @click="skip(currentItem)">{{ t('giftCodes.workspace.skip') }}</AppButton>
        </div>

        <div v-else-if="currentItem.state === 'awaiting_confirmation'" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
          <select v-model="selectedOutcome" class="ks-input" :aria-label="t('giftCodes.workspace.observedOutcome')">
            <option v-for="outcome in outcomes" :key="outcome" :value="outcome">{{ t(`giftCodes.workspace.outcomes.${outcome}`) }}</option>
          </select>
          <AppButton type="button" :busy="busy" @click="record(currentItem)">{{ t('giftCodes.workspace.recordContinue') }}</AppButton>
          <AppButton type="button" variant="secondary" :busy="busy" @click="skip(currentItem)">{{ t('giftCodes.workspace.skip') }}</AppButton>
        </div>

        <div class="mt-4 border-t border-[var(--ks-border)] pt-4">
          <p class="ks-kicker">{{ t('giftCodes.workspace.signalTitle') }}</p>
          <p v-if="currentSignal" class="mt-2 text-sm text-[var(--ks-muted)]">
            {{ t('giftCodes.workspace.signalSummary', { successRate: currentSignal.successRate, samples: currentSignal.sampleCount, accounts: currentSignal.distinctAccounts }) }}
          </p>
          <p v-else class="mt-2 text-sm text-[var(--ks-muted)]">{{ t('giftCodes.workspace.signalPrivate') }}</p>
        </div>
      </article>
    </section>

    <section class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="gift-code-run-builder-heading">
      <p class="ks-kicker">{{ t('giftCodes.workspace.createRun') }}</p>
      <h2 id="gift-code-run-builder-heading" class="ks-display mt-1 text-2xl font-semibold">{{ t('giftCodes.workspace.createRun') }}</h2>

      <div class="mt-4 flex flex-wrap gap-2">
        <AppButton type="button" :busy="busy" @click="createRun('all_actionable')">{{ t('giftCodes.workspace.runModes.all_actionable') }}</AppButton>
        <AppButton type="button" variant="secondary" :busy="busy" @click="createRun('expiring')">{{ t('giftCodes.workspace.runModes.expiring') }}</AppButton>
        <AppButton type="button" variant="secondary" :busy="busy" @click="createRun('retry_ready')">{{ t('giftCodes.workspace.runModes.retry_ready') }}</AppButton>
      </div>

      <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <fieldset>
          <legend class="ks-kicker">{{ t('giftCodes.workspace.shownCodes', { count: codes.length }) }}</legend>
          <div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
            <label v-for="giftCode in codes" :key="giftCode.id" class="flex items-center gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-3 text-sm">
              <input v-model="selectedCodeIds" type="checkbox" :value="giftCode.id" />
              <span class="font-mono font-semibold">{{ giftCode.code }}</span>
              <span v-if="giftCode.expiresAt" class="ml-auto text-xs text-[var(--ks-muted)]">{{ formatDate(giftCode.expiresAt) }}</span>
            </label>
          </div>
        </fieldset>
        <fieldset>
          <legend class="ks-kicker">{{ t('giftCodes.governors') }}</legend>
          <div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
            <label v-for="governor in governors" :key="governor.id" class="flex items-center gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-3 text-sm">
              <input v-model="selectedPlayerIds" type="checkbox" :value="governor.id" :disabled="!governor.gamePlayerId" />
              <span>{{ governor.name }}</span>
              <span class="ml-auto text-xs text-[var(--ks-muted)]">{{ governor.kingdomNumber ?? '—' }}</span>
            </label>
          </div>
        </fieldset>
      </div>

      <p class="mt-3 text-xs text-[var(--ks-muted)]">{{ t('giftCodes.workspace.selectedSummary', { codes: selectedCodeIds.length, governors: selectedPlayerIds.length }) }}</p>
      <AppButton
        type="button"
        class="mt-3"
        :busy="busy"
        :disabled="selectedCodeIds.length === 0 || selectedPlayerIds.length === 0"
        @click="createRun('selected', selectedCodeIds, selectedPlayerIds)"
      >
        {{ t('giftCodes.workspace.runModes.selected') }}
      </AppButton>
    </section>

    <section class="ks-surface mt-5 overflow-hidden">
      <div v-if="codes.length" class="divide-y divide-[var(--ks-border)]">
        <article v-for="giftCode in codes" :key="giftCode.id" class="p-5 sm:p-6">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-lg font-bold tracking-[.08em] text-[var(--ks-gold-bright)]">{{ giftCode.code }}</span>
                <span class="ks-status" :data-tone="statusTone(giftCode.status)">{{ giftCode.status }}</span>
              </div>
              <p class="mt-2 text-xs text-[var(--ks-muted)]">
                {{ t('giftCodes.workspace.sourceCount', { count: giftCode.sourceCount }) }} ·
                {{ giftCode.expiresAt ? t('giftCodes.workspace.expires', { date: formatDate(giftCode.expiresAt) }) : t('giftCodes.workspace.noExpiry') }}
              </p>
            </div>
            <span v-if="giftCode.personalState" class="ks-status" data-tone="info">{{ giftCode.personalState.state }}</span>
          </div>

          <div class="mt-3 flex flex-wrap gap-2">
            <AppButton type="button" size="sm" variant="secondary" @click="updateState(giftCode.id, 'pinned')">{{ t('giftCodes.workspace.pin') }}</AppButton>
            <AppButton type="button" size="sm" variant="secondary" @click="updateState(giftCode.id, 'actionable')">{{ t('giftCodes.workspace.restore') }}</AppButton>
            <AppButton type="button" size="sm" variant="secondary" @click="updateState(giftCode.id, 'snoozed', inHours(24))">{{ t('giftCodes.workspace.snoozeDay') }}</AppButton>
            <AppButton type="button" size="sm" variant="secondary" @click="updateState(giftCode.id, 'dismissed')">{{ t('giftCodes.workspace.dismiss') }}</AppButton>
            <AppButton type="button" size="sm" variant="secondary" @click="updateState(giftCode.id, giftCode.personalState?.state ?? 'actionable', null, inHours(24))">{{ t('giftCodes.workspace.remindDay') }}</AppButton>
          </div>
        </article>
      </div>
      <p v-else class="p-8 text-center text-sm text-[var(--ks-muted)]">{{ t('giftCodes.workspace.noCodes') }}</p>

      <div class="flex justify-between border-t border-[var(--ks-border)] p-4">
        <Link v-if="pagination.previousCursor" :href="viewUrl(view, pagination.previousCursor)" class="ks-command-link" data-variant="secondary">{{ t('common.previous') }}</Link>
        <span v-else />
        <Link v-if="pagination.nextCursor" :href="viewUrl(view, pagination.nextCursor)" class="ks-command-link" data-variant="secondary">{{ t('common.next') }}</Link>
      </div>
    </section>
  </AppLayout>
</template>
