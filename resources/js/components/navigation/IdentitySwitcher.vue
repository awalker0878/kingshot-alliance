<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

import { useLocale } from '@/localization';
import {
  activeContextKey,
  beginContextTransition,
  cancelContextTransition,
  completeContextTransition,
} from '@/identity/context-isolation';
import {
  EMPTY_PLAYER_CONTEXT,
  activePlayerFrom,
  type PlayerIdentity,
  type SharedPlayerContext,
} from '@/types/player-context';

withDefaults(
  defineProps<{
    compact?: boolean;
  }>(),
  { compact: false },
);

const { t } = useLocale();
const page = usePage();
const open = ref(false);
const switching = ref<string | null>(null);

const playerContext = computed<SharedPlayerContext>(
  () =>
    ((page.props as Record<string, unknown>).playerContext as SharedPlayerContext | undefined) ??
    EMPTY_PLAYER_CONTEXT,
);
const activePlayer = computed(() => activePlayerFrom(playerContext.value));
const activeFingerprint = computed(() => activeContextKey(playerContext.value));
const currentPath = computed(() => {
  const [path] = page.url.split(/[?#]/);
  return path?.startsWith('/') ? path : '/dashboard';
});
const canSwitch = computed(() => playerContext.value.players.length > 1);
const switchingPlayer = computed(
  () => playerContext.value.players.find((player) => player.id === switching.value) ?? null,
);
const identityLabel = computed(() => {
  if (activePlayer.value) return activePlayer.value.name;
  if (playerContext.value.players.length > 0) return t('application.dashboard.selectPlayer');
  return t('common.noPlayers');
});
const switchStatus = computed(() =>
  switchingPlayer.value ? `${t('common.loading')}: ${switchingPlayer.value.name}` : '',
);

watch(activeFingerprint, (nextContextKey, previousContextKey) => {
  if (previousContextKey && previousContextKey !== nextContextKey) {
    completeContextTransition(previousContextKey);
  }
});

function roleLabel(player: PlayerIdentity): string {
  if (!player.alliance) return '';

  return [player.alliance.rank.toUpperCase(), ...player.alliance.roles.map((role) => role.name)]
    .filter(Boolean)
    .join(' · ');
}

function compactContextLabel(player: PlayerIdentity): string {
  const pieces: string[] = [];
  if (player.alliance?.name) pieces.push(player.alliance.name);
  if (player.kingdomNumber) pieces.push(`K${player.kingdomNumber}`);
  if (player.alliance?.rank) pieces.push(player.alliance.rank.toUpperCase());
  return pieces.join(' · ');
}

function contextLabel(player: PlayerIdentity): string {
  const pieces: string[] = [];
  if (player.kingdomNumber) pieces.push(`K${player.kingdomNumber}`);
  if (player.alliance?.name) pieces.push(player.alliance.name);

  const roles = roleLabel(player);
  if (roles) pieces.push(roles);
  return pieces.join(' · ');
}

function focusOption(position: 'active' | 'first' | 'last' | 'next' | 'previous'): void {
  const options = Array.from(
    document.querySelectorAll<HTMLButtonElement>('[data-player-switch-option="true"]'),
  );
  if (options.length === 0) return;

  if (position === 'active') {
    const active = options.find((option) => option.dataset.playerId === activePlayer.value?.id);
    (active ?? options[0])?.focus();
    return;
  }

  if (position === 'first') {
    options[0]?.focus();
    return;
  }

  if (position === 'last') {
    options.at(-1)?.focus();
    return;
  }

  const currentIndex = options.findIndex((option) => option === document.activeElement);
  const delta = position === 'next' ? 1 : -1;
  const nextIndex = currentIndex < 0 ? 0 : (currentIndex + delta + options.length) % options.length;
  options[nextIndex]?.focus();
}

function toggleOpen(): void {
  if (!canSwitch.value || switching.value) return;

  open.value = !open.value;
  if (open.value) void nextTick(() => focusOption('active'));
}

function close(): void {
  if (switching.value) return;
  open.value = false;
}

function activate(playerId: string): void {
  if (playerId === activePlayer.value?.id || switching.value) {
    if (!switching.value) open.value = false;
    return;
  }

  const previousContextKey = activeFingerprint.value;
  switching.value = playerId;
  beginContextTransition(previousContextKey);

  router.post(
    `/players/${playerId}/activate`,
    { returnTo: currentPath.value },
    {
      preserveState: false,
      preserveScroll: false,
      onSuccess: () => {
        completeContextTransition(previousContextKey);
      },
      onError: () => {
        cancelContextTransition(previousContextKey);
        switching.value = null;
      },
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
    <p class="sr-only" aria-live="polite" aria-atomic="true">{{ switchStatus }}</p>

    <button
      type="button"
      class="group flex min-h-12 w-full min-w-0 items-center gap-3 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/20 px-3 py-2 text-start transition hover:border-[var(--ks-border-strong)] hover:bg-white/[0.025] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-teal-bright)]"
      :aria-expanded="canSwitch ? open : undefined"
      :aria-haspopup="canSwitch ? 'listbox' : undefined"
      :aria-busy="switching !== null"
      @click="toggleOpen"
      @keydown.down.prevent="canSwitch && (open ? focusOption('next') : toggleOpen())"
      @keydown.up.prevent="canSwitch && (open ? focusOption('previous') : toggleOpen())"
      @keydown.esc.prevent="close"
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
          v-if="activePlayer && (!compact || compactContextLabel(activePlayer))"
          class="mt-0.5 block truncate text-[0.68rem] text-[var(--ks-muted)]"
        >
          {{ compact ? compactContextLabel(activePlayer) : contextLabel(activePlayer) }}
        </span>
      </span>
      <svg
        v-if="canSwitch"
        class="h-4 w-4 shrink-0 text-[var(--ks-gold)] transition group-hover:text-[var(--ks-gold-bright)]"
        :class="open ? 'rotate-180' : ''"
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
      v-if="open && canSwitch"
      class="absolute end-0 top-[calc(100%+.55rem)] z-[90] w-[min(27rem,calc(100vw-2rem))] overflow-hidden rounded-[var(--ks-radius-lg)] border border-[var(--ks-border-strong)] bg-[rgba(7,13,13,.985)] shadow-[0_28px_80px_rgba(0,0,0,.65)] backdrop-blur-xl"
      role="listbox"
      :aria-label="t('application.dashboard.playerContextTitle')"
      :aria-busy="switching !== null"
      @keydown.down.prevent="focusOption('next')"
      @keydown.up.prevent="focusOption('previous')"
      @keydown.home.prevent="focusOption('first')"
      @keydown.end.prevent="focusOption('last')"
      @keydown.esc.prevent="close"
    >
      <div class="border-b border-[var(--ks-border)] px-4 py-3">
        <p class="ks-kicker">{{ t('application.dashboard.playerContextTitle') }}</p>
        <p class="mt-1 text-xs leading-5 text-[var(--ks-muted)]">
          {{ t('application.dashboard.playerContextIntro') }}
        </p>
      </div>

      <div class="max-h-[24rem] overflow-y-auto p-2">
        <button
          v-for="player in playerContext.players"
          :key="player.id"
          type="button"
          role="option"
          data-player-switch-option="true"
          :data-player-id="player.id"
          :aria-selected="player.id === activePlayer?.id"
          :disabled="switching !== null"
          class="flex min-h-16 w-full items-center gap-3 rounded-[var(--ks-radius-md)] border px-3 py-3 text-start transition focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--ks-teal-bright)] disabled:cursor-wait disabled:opacity-65"
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
            aria-hidden="true"
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
              {{ contextLabel(player) }}
            </span>
            <span
              v-if="player.gamePlayerId"
              class="mt-1 block truncate text-[0.65rem] text-[var(--ks-muted)] opacity-75"
            >
              {{ player.gamePlayerId }}
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
