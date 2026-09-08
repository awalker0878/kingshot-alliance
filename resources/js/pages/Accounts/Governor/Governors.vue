<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

interface HistoryEntry {
  id: string;
  userId: number | null;
  kingdomId: string;
  name: string;
  gamePlayerId: string | null;
  validFrom: string;
  validTo: string | null;
  sourceType: string;
  sourceReference: string | null;
  observedAt: string | null;
  confidenceBasisPoints: number | null;
  reason: string | null;
}

interface Governor {
  id: string;
  name: string;
  gamePlayerId: string | null;
  kingdomId: string;
  kingdomNumber: number | null;
  alliance: null | {
    id: string;
    name: string;
    rank: string;
    roles: Array<{ key: string; name: string }>;
  };
  releaseBlockers: string[];
  history: HistoryEntry[];
}

const props = defineProps<{
  user: { name: string; email: string };
  activePlayerId: string | null;
  governors: Governor[];
}>();

const { formatDate, t } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
const createForm = useForm({ name: '', kingdom_number: '', game_player_id: '' });
const edits = reactive<Record<string, { name: string; game_player_id: string }>>(
  Object.fromEntries(
    props.governors.map((governor) => [
      governor.id,
      { name: governor.name, game_player_id: governor.gamePlayerId ?? '' },
    ]),
  ),
);
const moves = reactive<Record<string, { kingdom_number: string }>>(
  Object.fromEntries(
    props.governors.map((governor) => [
      governor.id,
      { kingdom_number: governor.kingdomNumber ? String(governor.kingdomNumber) : '' },
    ]),
  ),
);

function createGovernor(): void {
  createForm.post('/governors', { onSuccess: () => createForm.reset() });
}

function updateGovernor(governor: Governor): void {
  router.patch(`/governors/${governor.id}`, edits[governor.id]);
}

function moveGovernor(governor: Governor): void {
  router.post(`/governors/${governor.id}/move`, moves[governor.id]);
}

function activateGovernor(governor: Governor): void {
  router.post(
    `/players/${governor.id}/activate`,
    { returnTo: '/governors' },
    { preserveState: false },
  );
}

function releaseGovernor(governor: Governor): void {
  if (governor.releaseBlockers.length > 0) return;

  requestConfirmation({
    id: `governor-release-${governor.id}`,
    title: t('governorLifecycle.releaseTitle'),
    description: t('governorLifecycle.releaseConfirm', { governor: governor.name }),
    confirmLabel: t('governorLifecycle.releaseGovernor'),
    cancelLabel: t('common.cancel'),
    perform: (finish) => router.delete(`/governors/${governor.id}`, { onFinish: finish }),
  });
}
</script>

<template>
  <Head :title="t('governorLifecycle.title')" />

  <AppLayout :user="props.user">
    <RoomBanner
      :eyebrow="t('governorLifecycle.eyebrow')"
      :title="t('governorLifecycle.title')"
      :subtitle="t('governorLifecycle.subtitle')"
      image="/images/kingshot/v4/account-vault.svg"
      compact
    >
      <template #actions>
        <Link href="/dashboard" class="ks-command-link">{{
          t('governorLifecycle.dashboard')
        }}</Link>
        <Link href="/profile" class="ks-command-link" data-variant="secondary">{{
          t('governorLifecycle.accountSettings')
        }}</Link>
      </template>
    </RoomBanner>

    <section class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="add-governor-heading">
      <p class="ks-kicker">{{ t('governorLifecycle.identityRegistry') }}</p>
      <h2 id="add-governor-heading" class="ks-display mt-1 text-2xl font-semibold">
        {{ t('governorLifecycle.addGovernor') }}
      </h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('governorLifecycle.addIntro') }}
      </p>
      <form
        class="mt-5 grid gap-4 md:grid-cols-[1fr_10rem_1fr_auto] md:items-end"
        @submit.prevent="createGovernor"
      >
        <div>
          <label for="governor-name" class="block text-sm font-semibold">{{
            t('governorLifecycle.governorName')
          }}</label>
          <input
            id="governor-name"
            v-model="createForm.name"
            class="ks-input mt-2"
            maxlength="160"
            required
          />
          <p v-if="createForm.errors.name" class="mt-1 text-sm text-[var(--ks-red)]" role="alert">
            {{ createForm.errors.name }}
          </p>
        </div>
        <div>
          <label for="governor-kingdom" class="block text-sm font-semibold">{{
            t('governorLifecycle.kingdom')
          }}</label>
          <input
            id="governor-kingdom"
            v-model="createForm.kingdom_number"
            class="ks-input mt-2"
            inputmode="numeric"
            required
          />
          <p
            v-if="createForm.errors.kingdom_number"
            class="mt-1 text-sm text-[var(--ks-red)]"
            role="alert"
          >
            {{ createForm.errors.kingdom_number }}
          </p>
        </div>
        <div>
          <label for="governor-game-id" class="block text-sm font-semibold">
            {{ t('governorLifecycle.gamePlayerId') }}
            <span class="font-normal text-[var(--ks-muted)]"
              >({{ t('governorLifecycle.optional') }})</span
            >
          </label>
          <input
            id="governor-game-id"
            v-model="createForm.game_player_id"
            class="ks-input mt-2"
            maxlength="100"
          />
          <p
            v-if="createForm.errors.game_player_id"
            class="mt-1 text-sm text-[var(--ks-red)]"
            role="alert"
          >
            {{ createForm.errors.game_player_id }}
          </p>
        </div>
        <button
          class="ks-command-link min-h-11 justify-center"
          type="submit"
          :disabled="createForm.processing"
        >
          {{ t('governorLifecycle.add') }}
        </button>
      </form>
    </section>

    <section v-if="props.governors.length === 0" class="ks-surface mt-5 p-8 text-center">
      <p class="ks-kicker">{{ t('governorLifecycle.noGovernor') }}</p>
      <h2 class="ks-display mt-2 text-2xl font-semibold">
        {{ t('governorLifecycle.startWithIdentity') }}
      </h2>
      <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('governorLifecycle.noGovernorIntro') }}
      </p>
    </section>

    <section v-else class="mt-5 grid gap-5" :aria-label="t('governorLifecycle.ownedGovernors')">
      <article v-for="governor in props.governors" :key="governor.id" class="ks-surface p-5 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <p class="ks-kicker">K{{ governor.kingdomNumber ?? '—' }}</p>
              <span
                v-if="governor.id === props.activePlayerId"
                class="ks-status"
                data-tone="success"
              >
                {{ t('governorLifecycle.activeGovernor') }}
              </span>
            </div>
            <h2 class="ks-display mt-1 text-2xl font-semibold">{{ governor.name }}</h2>
            <p class="mt-1 text-sm text-[var(--ks-muted)]">
              <template v-if="governor.alliance">
                {{ governor.alliance.name }} · {{ governor.alliance.rank.toUpperCase() }}
              </template>
              <template v-else>{{ t('governorLifecycle.noActiveAlliance') }}</template>
              <template v-if="governor.gamePlayerId"> · ID {{ governor.gamePlayerId }}</template>
            </p>
          </div>
          <button
            v-if="governor.id !== props.activePlayerId"
            type="button"
            class="ks-command-link"
            @click="activateGovernor(governor)"
          >
            {{ t('governorLifecycle.useGovernor') }}
          </button>
        </header>

        <div class="mt-6 grid gap-5 xl:grid-cols-2">
          <form
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4"
            @submit.prevent="updateGovernor(governor)"
          >
            <h3 class="font-semibold">{{ t('governorLifecycle.currentIdentity') }}</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
              <div>
                <label :for="`name-${governor.id}`" class="block text-sm font-semibold">{{
                  t('governorLifecycle.governorName')
                }}</label>
                <input
                  :id="`name-${governor.id}`"
                  v-model="edits[governor.id]!.name"
                  class="ks-input mt-2"
                  maxlength="160"
                  required
                />
              </div>
              <div>
                <label :for="`game-id-${governor.id}`" class="block text-sm font-semibold">{{
                  t('governorLifecycle.gamePlayerId')
                }}</label>
                <input
                  :id="`game-id-${governor.id}`"
                  v-model="edits[governor.id]!.game_player_id"
                  class="ks-input mt-2"
                  maxlength="100"
                />
              </div>
            </div>
            <p class="mt-3 text-xs leading-5 text-[var(--ks-muted)]">
              {{ t('governorLifecycle.stableIdHelp') }}
            </p>
            <button type="submit" class="ks-command-link mt-4">
              {{ t('governorLifecycle.saveIdentity') }}
            </button>
          </form>

          <form
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4"
            @submit.prevent="moveGovernor(governor)"
          >
            <h3 class="font-semibold">{{ t('governorLifecycle.kingdomIdentity') }}</h3>
            <p class="mt-2 text-xs leading-5 text-[var(--ks-muted)]">
              {{ t('governorLifecycle.kingdomMoveHelp') }}
            </p>
            <label :for="`kingdom-${governor.id}`" class="mt-4 block text-sm font-semibold">{{
              t('governorLifecycle.currentTargetKingdom')
            }}</label>
            <div class="mt-2 flex gap-2">
              <input
                :id="`kingdom-${governor.id}`"
                v-model="moves[governor.id]!.kingdom_number"
                class="ks-input"
                inputmode="numeric"
                required
              />
              <button type="submit" class="ks-command-link shrink-0">
                {{ t('governorLifecycle.changeKingdom') }}
              </button>
            </div>
          </form>
        </div>

        <section
          class="mt-5 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4"
          :aria-labelledby="`history-${governor.id}`"
        >
          <h3 :id="`history-${governor.id}`" class="font-semibold">
            {{ t('governorLifecycle.identityHistory') }}
          </h3>
          <div v-if="governor.history.length" class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[48rem] text-left text-sm">
              <thead class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">
                <tr>
                  <th class="py-2 pr-4">{{ t('governorLifecycle.historyFrom') }}</th>
                  <th class="py-2 pr-4">{{ t('governorLifecycle.historyName') }}</th>
                  <th class="py-2 pr-4">{{ t('governorLifecycle.historyKingdomId') }}</th>
                  <th class="py-2 pr-4">{{ t('governorLifecycle.historyGameId') }}</th>
                  <th class="py-2 pr-4">{{ t('governorLifecycle.historySource') }}</th>
                  <th class="py-2">{{ t('governorLifecycle.historyReason') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="entry in governor.history"
                  :key="entry.id"
                  class="border-t border-[var(--ks-border)]"
                >
                  <td class="py-2 pr-4">{{ formatDate(entry.validFrom) }}</td>
                  <td class="py-2 pr-4">{{ entry.name }}</td>
                  <td class="py-2 pr-4 font-mono text-xs">{{ entry.kingdomId }}</td>
                  <td class="py-2 pr-4">{{ entry.gamePlayerId ?? '—' }}</td>
                  <td class="py-2 pr-4">{{ entry.sourceType.replaceAll('_', ' ') }}</td>
                  <td class="py-2">{{ entry.reason ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <footer
          class="mt-5 flex flex-wrap items-start justify-between gap-4 border-t border-[var(--ks-border)] pt-4"
        >
          <div>
            <p class="text-sm font-semibold">{{ t('governorLifecycle.releaseTitle') }}</p>
            <ul
              v-if="governor.releaseBlockers.length"
              class="mt-1 list-disc pl-5 text-xs leading-5 text-[var(--ks-muted)]"
            >
              <li v-for="blocker in governor.releaseBlockers" :key="blocker">{{ blocker }}</li>
            </ul>
            <p v-else class="mt-1 text-xs text-[var(--ks-muted)]">
              {{ t('governorLifecycle.releasePreserves') }}
            </p>
          </div>
          <button
            type="button"
            class="ks-command-link"
            data-variant="danger"
            :disabled="governor.releaseBlockers.length > 0"
            @click="releaseGovernor(governor)"
          >
            {{ t('governorLifecycle.releaseGovernor') }}
          </button>
        </footer>
      </article>
    </section>

    <ConfirmActionDialog v-bind="dialog" @confirm="confirmAction" @cancel="cancelConfirmation" />
  </AppLayout>
</template>
