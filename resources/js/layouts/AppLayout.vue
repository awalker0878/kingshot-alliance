<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AllianceCrest from '@/components/game/AllianceCrest.vue';
import IdentitySwitcher from '@/components/navigation/IdentitySwitcher.vue';
import LocaleSwitcher from '@/components/navigation/LocaleSwitcher.vue';
import NavIcon from '@/components/navigation/NavIcon.vue';
import { useLocale } from '@/localization';

type NavIconName =
  | 'dashboard'
  | 'alliance'
  | 'events'
  | 'roster'
  | 'recruitment'
  | 'content'
  | 'contributions'
  | 'kingdom'
  | 'transfers'
  | 'integrations'
  | 'profile';

type NavigationItem = {
  key:
    | 'dashboard'
    | 'alliance'
    | 'events'
    | 'roster'
    | 'recruitment'
    | 'content'
    | 'contributions'
    | 'kingdom'
    | 'transfers'
    | 'integrations';
  href: string;
  icon: NavIconName;
  allianceScoped?: boolean;
  exact?: boolean;
};

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
    user: { name: string; email?: string };
    playerAllianceName?: string | null;
    hasPlayerAlliance?: boolean;
  }>(),
  { playerAllianceName: null, hasPlayerAlliance: false },
);

const { t } = useLocale();
const page = usePage();
const mobileOpen = ref(false);

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
const currentPath = computed(() => page.url.split('?')[0]?.replace(/\/+$/, '') || '/');

const rooms: NavigationItem[] = [
  { key: 'dashboard', href: '/dashboard', icon: 'dashboard', exact: true },
  { key: 'alliance', href: '/alliance', icon: 'alliance', allianceScoped: true, exact: true },
  { key: 'recruitment', href: '/alliance/recruitment', icon: 'recruitment', allianceScoped: true },
  { key: 'events', href: '/events', icon: 'events' },
  { key: 'kingdom', href: '/alliance/kingdom-alliances', icon: 'kingdom', allianceScoped: true },
  { key: 'roster', href: '/alliance/roster', icon: 'roster', allianceScoped: true },
  {
    key: 'contributions',
    href: '/alliance/contributions',
    icon: 'contributions',
    allianceScoped: true,
  },
  { key: 'transfers', href: '/alliance/transfers', icon: 'transfers', allianceScoped: true },
  { key: 'content', href: '/alliance/content', icon: 'content', allianceScoped: true },
  {
    key: 'integrations',
    href: '/alliance/integrations',
    icon: 'integrations',
    allianceScoped: true,
  },
];

function isDisabled(item: NavigationItem): boolean {
  return item.allianceScoped === true && !props.hasPlayerAlliance;
}

function isActive(item: NavigationItem): boolean {
  const href = item.href.replace(/\/+$/, '') || '/';
  return item.exact
    ? currentPath.value === href
    : currentPath.value === href || currentPath.value.startsWith(`${href}/`);
}

function logout(): void {
  router.delete('/logout');
}
</script>

<template>
  <a
    href="#main-content"
    class="fixed start-4 top-4 z-[120] -translate-y-24 rounded bg-[var(--ks-gold)] px-4 py-2 font-bold text-[#181108] shadow-xl focus:translate-y-0"
  >
    {{ t('common.skipToNoticeboard') }}
  </a>

  <div class="min-h-screen bg-transparent text-[var(--ks-text)]">
    <!-- Desktop command rail -->
    <aside
      class="fixed inset-y-0 start-0 z-50 hidden w-[18.5rem] flex-col border-e border-[var(--ks-border)] bg-[rgba(4,9,9,.96)] shadow-[18px_0_55px_rgba(0,0,0,.28)] backdrop-blur-xl xl:flex"
    >
      <div class="relative overflow-hidden border-b border-[var(--ks-border)] px-5 pt-6 pb-5">
        <div
          class="pointer-events-none absolute -end-16 -top-20 h-52 w-52 rounded-full bg-[var(--ks-gold-soft)] blur-3xl"
        />
        <Link href="/dashboard" class="relative flex items-center gap-3">
          <div
            class="grid h-14 w-12 place-items-center border border-[var(--ks-gold-dark)] bg-[linear-gradient(160deg,#163f3c,#071a19)] [clip-path:polygon(50%_0,95%_16%,86%_77%,50%_100%,14%_77%,5%_16%)]"
          >
            <span class="text-xl font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
              >♛</span
            >
          </div>
          <div>
            <div
              class="text-[1.72rem] leading-none font-[var(--ks-font-display)] font-semibold tracking-[.06em] text-[var(--ks-gold-bright)]"
            >
              KINGSHOT
            </div>
            <div
              class="mt-1 text-[.61rem] font-extrabold tracking-[.19em] text-[var(--ks-muted)] uppercase"
            >
              {{ t('navigation.allianceOperations') }}
            </div>
          </div>
        </Link>
      </div>

      <div class="px-3 py-4">
        <IdentitySwitcher :alliance-name="playerAllianceName ?? null" />
      </div>

      <nav
        class="min-h-0 flex-1 overflow-y-auto px-3 pb-5"
        :aria-label="t('navigation.allianceOperations')"
      >
        <div
          class="mb-2 px-3 text-[.62rem] font-extrabold tracking-[.18em] text-[var(--ks-gold)] uppercase"
        >
          {{ t('navigation.allianceOperations') }}
        </div>
        <div class="space-y-1">
          <template v-for="room in rooms" :key="room.href">
            <span
              v-if="isDisabled(room)"
              class="flex min-h-11 items-center gap-3 rounded-[var(--ks-radius-sm)] px-3 py-2 text-sm text-[var(--ks-muted)] opacity-30"
              :aria-label="t(`navigation.${room.key}`)"
            >
              <NavIcon :name="room.icon" class="h-5 w-5" />
              <span class="truncate">{{ t(`navigation.${room.key}`) }}</span>
            </span>
            <Link
              v-else
              :href="room.href"
              class="group relative flex min-h-11 items-center gap-3 overflow-hidden rounded-[var(--ks-radius-sm)] border px-3 py-2 text-[.9rem] font-[var(--ks-font-display)] transition"
              :class="
                isActive(room)
                  ? 'border-[rgba(32,178,163,.38)] bg-[linear-gradient(90deg,rgba(10,121,113,.42),rgba(10,67,63,.12))] text-[#f6ecd7] shadow-[inset_3px_0_var(--ks-teal-bright)]'
                  : 'border-transparent text-[var(--ks-text-muted)] hover:border-[var(--ks-border)] hover:bg-white/[0.025] hover:text-[var(--ks-text)]'
              "
            >
              <NavIcon
                :name="room.icon"
                class="h-5 w-5 shrink-0"
                :class="
                  isActive(room)
                    ? 'text-[var(--ks-gold-bright)]'
                    : 'text-[var(--ks-gold)] opacity-80'
                "
              />
              <span class="min-w-0 flex-1 truncate">{{ t(`navigation.${room.key}`) }}</span>
              <span v-if="isActive(room)" class="text-[var(--ks-teal-bright)]">›</span>
            </Link>
          </template>
        </div>
      </nav>

      <div class="relative border-t border-[var(--ks-border)] p-3">
        <div class="grid grid-cols-[1fr_auto] gap-2">
          <Link
            href="/profile"
            class="flex min-h-11 items-center gap-2 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 text-sm text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-border-strong)] hover:text-[var(--ks-gold-bright)]"
          >
            <NavIcon name="profile" class="h-4 w-4" />
            <span class="truncate">{{ t('navigation.profile') }}</span>
          </Link>
          <LocaleSwitcher />
        </div>
        <button
          type="button"
          class="mt-2 w-full rounded-[var(--ks-radius-sm)] px-3 py-2 text-start text-xs text-[var(--ks-muted)] transition hover:bg-white/[0.025] hover:text-[var(--ks-text)]"
          @click="logout"
        >
          {{ t('common.signOut') }} · {{ user.name }}
        </button>
      </div>
    </aside>

    <!-- Mobile/tablet top bar -->
    <header
      class="sticky top-0 z-40 flex min-h-16 items-center gap-3 border-b border-[var(--ks-border)] bg-[rgba(5,10,11,.92)] px-3 py-2 backdrop-blur-xl xl:hidden"
    >
      <button
        type="button"
        class="grid h-11 w-11 shrink-0 place-items-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] text-[var(--ks-gold-bright)]"
        :aria-label="t('common.openNavigation')"
        @click="mobileOpen = true"
      >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M4 7h16M4 12h16M4 17h16"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
          />
        </svg>
      </button>
      <div class="min-w-0 flex-1">
        <div
          class="truncate text-base font-[var(--ks-font-display)] font-semibold text-[var(--ks-gold-bright)]"
        >
          {{ playerAllianceName ?? 'KINGSHOT' }}
        </div>
        <div class="truncate text-[.65rem] text-[var(--ks-muted)]">
          {{ activePlayer?.name ?? user.name
          }}<template v-if="activePlayer?.kingdomNumber">
            · K{{ activePlayer.kingdomNumber }}</template
          >
        </div>
      </div>
      <div class="w-[min(12rem,42vw)]">
        <IdentitySwitcher :alliance-name="playerAllianceName ?? null" compact />
      </div>
    </header>

    <div v-if="mobileOpen" class="fixed inset-0 z-[100] xl:hidden">
      <button
        type="button"
        class="absolute inset-0 bg-black/75 backdrop-blur-sm"
        :aria-label="t('common.closeNavigation')"
        @click="mobileOpen = false"
      />
      <aside
        class="relative flex h-full w-[90%] max-w-sm flex-col border-e border-[var(--ks-border-strong)] bg-[rgba(5,10,10,.99)] shadow-2xl"
      >
        <div class="flex items-center justify-between border-b border-[var(--ks-border)] p-4">
          <div>
            <div class="text-2xl font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]">
              KINGSHOT
            </div>
            <div
              class="mt-1 text-[.62rem] font-bold tracking-[.16em] text-[var(--ks-muted)] uppercase"
            >
              {{ t('navigation.allianceOperations') }}
            </div>
          </div>
          <button
            type="button"
            class="grid h-11 w-11 place-items-center rounded border border-[var(--ks-border)] text-2xl text-[var(--ks-text-secondary)]"
            :aria-label="t('common.closeNavigation')"
            @click="mobileOpen = false"
          >
            ×
          </button>
        </div>

        <div class="border-b border-[var(--ks-border)] p-3">
          <IdentitySwitcher :alliance-name="playerAllianceName ?? null" />
        </div>

        <nav
          class="min-h-0 flex-1 overflow-y-auto p-3"
          :aria-label="t('navigation.allianceOperations')"
        >
          <div class="space-y-1">
            <template v-for="room in rooms" :key="room.href">
              <span
                v-if="isDisabled(room)"
                class="flex min-h-12 items-center gap-3 rounded px-3 text-sm text-[var(--ks-muted)] opacity-30"
              >
                <NavIcon :name="room.icon" class="h-5 w-5" />
                {{ t(`navigation.${room.key}`) }}
              </span>
              <Link
                v-else
                :href="room.href"
                class="flex min-h-12 items-center gap-3 rounded-[var(--ks-radius-sm)] border px-3 text-sm"
                :class="
                  isActive(room)
                    ? 'border-[rgba(32,178,163,.38)] bg-[var(--ks-teal-soft)] text-[var(--ks-ivory)]'
                    : 'border-transparent text-[var(--ks-text-secondary)]'
                "
                @click="mobileOpen = false"
              >
                <NavIcon :name="room.icon" class="h-5 w-5 text-[var(--ks-gold)]" />
                <span class="flex-1">{{ t(`navigation.${room.key}`) }}</span>
              </Link>
            </template>
          </div>
        </nav>

        <div class="space-y-2 border-t border-[var(--ks-border)] p-3">
          <div class="flex items-center justify-between gap-2">
            <Link
              href="/profile"
              class="flex min-h-11 flex-1 items-center gap-2 rounded border border-[var(--ks-border)] px-3 text-sm"
              @click="mobileOpen = false"
            >
              <NavIcon name="profile" class="h-4 w-4" />
              {{ t('navigation.profile') }}
            </Link>
            <LocaleSwitcher />
          </div>
          <button
            type="button"
            class="min-h-11 w-full rounded border border-[var(--ks-border)] px-3 text-start text-sm text-[var(--ks-text-secondary)]"
            @click="logout"
          >
            {{ t('common.signOut') }}
          </button>
        </div>
      </aside>
    </div>

    <div class="xl:ps-[18.5rem]">
      <!-- Desktop identity context bar -->
      <div
        class="sticky top-0 z-30 hidden min-h-[5.25rem] items-center gap-5 border-b border-[var(--ks-border)] bg-[rgba(5,10,11,.88)] px-6 backdrop-blur-xl xl:flex"
      >
        <div class="flex min-w-0 flex-1 items-center gap-4">
          <AllianceCrest :name="playerAllianceName || activePlayer?.name || 'Kingshot'" size="md" />
          <div class="min-w-0">
            <p class="ks-kicker">{{ t('common.currentPlayer') }}</p>
            <div class="mt-1 flex min-w-0 flex-wrap items-baseline gap-x-3 gap-y-1">
              <strong
                class="truncate text-xl font-[var(--ks-font-display)] font-semibold text-[var(--ks-ivory)]"
              >
                {{ activePlayer?.name ?? user.name }}
              </strong>
              <span
                v-if="playerAllianceName"
                class="truncate text-sm text-[var(--ks-text-secondary)]"
                >{{ playerAllianceName }}</span
              >
              <span
                v-if="activePlayer?.kingdomNumber"
                class="text-sm font-semibold text-[var(--ks-teal-bright)]"
                >K{{ activePlayer.kingdomNumber }}</span
              >
            </div>
          </div>
        </div>
        <div class="w-[20rem] max-w-[34vw]">
          <IdentitySwitcher :alliance-name="playerAllianceName ?? null" />
        </div>
      </div>

      <main id="main-content" class="relative min-h-screen">
        <div
          class="mx-auto w-full max-w-[112rem] px-3 py-4 sm:px-5 sm:py-5 lg:px-6 xl:px-7 xl:py-6"
        >
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>
