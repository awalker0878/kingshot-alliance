<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { useLocale } from '@/localization';

type PlayerIdentity = {
  id: string;
  name: string;
  gamePlayerId: string | null;
  kingdomNumber: number | null;
};

type SharedPlayerContext = {
  activePlayerId: string | null;
  players: PlayerIdentity[];
};

const props = withDefaults(
  defineProps<{
    allianceName?: string | null;
    compact?: boolean;
  }>(),
  { allianceName: null, compact: false },
);

const { t } = useLocale();
const page = usePage();
const open = ref(false);
const switching = ref<string | null>(null);

const playerContext = computed<SharedPlayerContext>(
  () =>
    ((page.props as Record<string, unknown>).playerContext as SharedPlayerContext | undefined) ?? {
      activePlayerId: null,
      players: [],
    },
);

const activePlayer = computed(() => {
  const activePlayerId = playerContext.value.activePlayerId;
  if (!activePlayerId) return null;

  return playerContext.value.players.find((player) => player.id === activePlayerId) ?? null;
});

const identityLabel = computed(() => {
  if (activePlayer.value) return activePlayer.value.name;
  if (playerContext.value.players.length > 0) return t('application.dashboard.selectPlayer');
  return t('common.noPlayers');
});

function contextLabel(player: PlayerIdentity): string {
  const pieces: string[] = [];
  if (player.kingdomNumber) pieces.push(`K${player.kingdomNumber}`);
  if (player.id === activePlayer.value?.id && props.allianceName) pieces.push(props.allianceName);
  if (player.gamePlayerId) pieces.push(player.gamePlayerId);
  return pieces.join(' · ');
}

function activate(playerId: string): void {
  if (playerId === activePlayer.value?.id || switching.value) {
    open.value = false;
    return;
  }

  switching.value = playerId;
  router.post(
    `/players/${playerId}/activate`,
    {},
    {
      preserveState: false,
      preserveScroll: false,
      onFinish: () => {
        switching.value = null;
        open.value = false;
      },
    },
  );
}
</script>

<template>
  <div class="relative min-w-0">
    <button
      type="button"
      class="group flex min-h-12 w-full min-w-0 items-center gap-3 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/20 px-3 py-2 text-start transition hover:border-[var(--ks-border-strong)] hover:bg-white/[0.025]"
      :aria-expanded="open"
      aria-haspopup="listbox"
      @click="open = !open"
    >
      <span
        class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-[radial-gradient(circle_at_38%_28%,#5a4c38,#17211f_68%)] font-[var(--ks-font-display)] font-bold text-[var(--ks-gold-bright)] shadow-inner"
        aria-hidden="true"
      >
        {{ activePlayer?.name?.slice(0, 1).toUpperCase() ?? '—' }}
      </span>
      <span class="min-w-0 flex-1">
        <span
          class="block text-[0.62rem] font-extrabold tracking-[0.12em] text-[var(--ks-muted)] uppercase"
        >
          {{ t('common.currentPlayer') }}
        </span>
        <strong
          class="block truncate text-sm font-[var(--ks-font-display)] font-semibold text-[var(--ks-ivory)]"
        >
          {{ identityLabel }}
        </strong>
        <span
          v-if="!compact && activePlayer"
          class="mt-0.5 block truncate text-[0.68rem] text-[var(--ks-muted)]"
        >
          {{ contextLabel(activePlayer) }}
        </span>
      </span>
      <svg
        v-if="playerContext.players.length > 1"
        class="h-4 w-4 shrink-0 text-[var(--ks-gold)] transition group-hover:text-[var(--ks-gold-bright)]"
        viewBox="0 0 20 20"
        fill="none"
        aria-hidden="true"
      >
        <path
          d="m6 8 4 4 4-4"
          stroke="currentColor"
          stroke-width="1.7"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </button>

    <div
      v-if="open && playerContext.players.length > 1"
      class="absolute end-0 top-[calc(100%+.55rem)] z-[90] w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-[var(--ks-radius-lg)] border border-[var(--ks-border-strong)] bg-[rgba(7,13,13,.985)] shadow-[0_28px_80px_rgba(0,0,0,.65)] backdrop-blur-xl"
      role="listbox"
      :aria-label="t('application.dashboard.playerContextTitle')"
    >
      <div class="border-b border-[var(--ks-border)] px-4 py-3">
        <p class="ks-kicker">{{ t('application.dashboard.playerContextTitle') }}</p>
        <p class="mt-1 text-xs leading-5 text-[var(--ks-muted)]">
          {{ t('application.dashboard.playerContextIntro') }}
        </p>
      </div>

      <div class="max-h-[22rem] overflow-y-auto p-2">
        <button
          v-for="player in playerContext.players"
          :key="player.id"
          type="button"
          role="option"
          :aria-selected="player.id === activePlayer?.id"
          :disabled="switching !== null"
          class="flex w-full items-center gap-3 rounded-[var(--ks-radius-md)] border px-3 py-3 text-start transition"
          :class="
            player.id === activePlayer?.id
              ? 'border-[rgba(32,178,163,.38)] bg-[rgba(20,153,141,.12)]'
              : 'border-transparent hover:border-[var(--ks-border)] hover:bg-white/[0.025]'
          "
          @click="activate(player.id)"
        >
          <span
            class="grid h-10 w-10 shrink-0 place-items-center rounded-full border font-[var(--ks-font-display)] font-bold"
            :class="
              player.id === activePlayer?.id
                ? 'border-[var(--ks-teal-bright)] bg-[var(--ks-teal-soft)] text-[#aef6ea]'
                : 'border-[var(--ks-border)] bg-black/20 text-[var(--ks-gold-bright)]'
            "
          >
            {{ player.name.slice(0, 1).toUpperCase() }}
          </span>
          <span class="min-w-0 flex-1">
            <strong
              class="block truncate text-sm font-[var(--ks-font-display)] text-[var(--ks-ivory)]"
            >
              {{ player.name }}
            </strong>
            <span class="mt-1 block truncate text-xs text-[var(--ks-muted)]">
              {{ contextLabel(player) || t('application.dashboard.selectPlayer') }}
            </span>
          </span>
          <span
            v-if="player.id === activePlayer?.id"
            class="ks-status shrink-0"
            data-tone="success"
          >
            {{ t('common.active') }}
          </span>
          <span v-else-if="switching === player.id" class="text-xs text-[var(--ks-gold)]">
            {{ t('common.loading') }}
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
