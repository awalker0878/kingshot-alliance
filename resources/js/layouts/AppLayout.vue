<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import BrandMark from '../components/brand/BrandMark.vue';
import LocaleSwitcher from '../components/navigation/LocaleSwitcher.vue';
import NavIcon from '../components/navigation/NavIcon.vue';
import { useLocale } from '../localization';

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
  key: string;
  href: string;
  icon: NavIconName;
  allianceScoped?: boolean;
  exact?: boolean;
};

type NavigationGroup = {
  labelKey: string;
  items: NavigationItem[];
};

const props = withDefaults(
  defineProps<{
    user: {
      name: string;
      email?: string;
    };
    allianceName?: string | null;
    hasActiveAlliance?: boolean;
  }>(),
  {
    allianceName: null,
    hasActiveAlliance: false,
  },
);

const { t } = useLocale();
const page = usePage();
const mobileOpen = ref(false);

const navigationGroups: NavigationGroup[] = [
  {
    labelKey: 'navigation.allianceOperations',
    items: [
      { key: 'navigation.dashboard', href: '/dashboard', icon: 'dashboard', exact: true },
      { key: 'navigation.alliance', href: '/alliance', icon: 'alliance', allianceScoped: true, exact: true },
      { key: 'navigation.events', href: '/alliance/events', icon: 'events', allianceScoped: true },
      { key: 'navigation.roster', href: '/alliance/roster', icon: 'roster', allianceScoped: true },
      { key: 'navigation.recruitment', href: '/alliance/recruitment', icon: 'recruitment', allianceScoped: true },
      { key: 'navigation.content', href: '/alliance/content', icon: 'content', allianceScoped: true },
      {
        key: 'navigation.contributions',
        href: '/alliance/contributions',
        icon: 'contributions',
        allianceScoped: true,
      },
    ],
  },
  {
    labelKey: 'navigation.kingdomOperations',
    items: [
      {
        key: 'navigation.kingdom',
        href: '/alliance/kingdom-alliances',
        icon: 'kingdom',
        allianceScoped: true,
      },
      {
        key: 'navigation.transfers',
        href: '/alliance/transfers',
        icon: 'transfers',
        allianceScoped: true,
      },
      {
        key: 'navigation.integrations',
        href: '/alliance/integrations',
        icon: 'integrations',
        allianceScoped: true,
      },
    ],
  },
];

const currentPath = computed(() => page.url.split('?')[0]?.replace(/\/+$/, '') || '/');

function isDisabled(item: NavigationItem): boolean {
  return item.allianceScoped === true && !props.hasActiveAlliance;
}

function isActive(item: NavigationItem): boolean {
  const href = item.href.replace(/\/+$/, '') || '/';

  if (item.exact) {
    return currentPath.value === href;
  }

  return currentPath.value === href || currentPath.value.startsWith(`${href}/`);
}

function closeMobile(): void {
  mobileOpen.value = false;
}

function logout(): void {
  router.delete('/logout');
}

const initials = computed(() =>
  props.user.name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join(''),
);
</script>

<template>
  <a
    href="#main-content"
    class="fixed start-4 top-4 z-[70] -translate-y-24 rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2 font-semibold text-slate-950 transition focus:translate-y-0"
  >
    {{ t('common.skipToContent') }}
  </a>

  <div class="min-h-screen bg-[var(--ks-bg)] text-[var(--ks-text)]">
    <aside
      class="fixed inset-y-0 start-0 z-40 hidden w-64 border-e border-[var(--ks-border)] bg-[rgba(5,11,20,0.97)] lg:flex lg:flex-col"
    >
      <div class="border-b border-[var(--ks-border)] px-5 py-5">
        <Link href="/dashboard" aria-label="Kingshot Alliance">
          <BrandMark />
        </Link>
      </div>

      <div class="px-4 py-4">
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-3">
          <p class="text-[0.68rem] font-bold tracking-[0.18em] text-[var(--ks-text-muted)] uppercase">
            {{ t('common.currentAlliance') }}
          </p>
          <p v-if="hasActiveAlliance && allianceName" class="mt-1.5 truncate text-sm font-semibold">
            {{ allianceName }}
          </p>
          <p v-else class="mt-1.5 text-xs leading-5 text-[var(--ks-text-muted)]">
            {{ t('common.noActiveAlliance') }}
          </p>
        </div>
      </div>

      <nav class="flex-1 overflow-y-auto px-3 pb-5" :aria-label="t('common.menu')">
        <section v-for="group in navigationGroups" :key="group.labelKey" class="mb-5">
          <h2 class="px-3 pb-2 text-[0.68rem] font-bold tracking-[0.17em] text-[var(--ks-text-muted)] uppercase">
            {{ t(group.labelKey) }}
          </h2>

          <div class="space-y-1">
            <template v-for="item in group.items" :key="item.href">
              <span
                v-if="isDisabled(item)"
                class="flex cursor-not-allowed items-center gap-3 rounded-[var(--ks-radius-sm)] px-3 py-2.5 text-sm text-[var(--ks-text-muted)] opacity-45"
                :title="t('common.noActiveAlliance')"
                aria-disabled="true"
              >
                <NavIcon :name="item.icon" />
                <span>{{ t(item.key) }}</span>
              </span>
              <Link
                v-else
                :href="item.href"
                class="flex items-center gap-3 rounded-[var(--ks-radius-sm)] px-3 py-2.5 text-sm font-medium transition"
                :class="
                  isActive(item)
                    ? 'bg-[var(--ks-blue-soft)] text-[var(--ks-blue-strong)] ring-1 ring-inset ring-[rgba(75,143,247,0.26)]'
                    : 'text-[var(--ks-text-secondary)] hover:bg-[var(--ks-surface-1)] hover:text-[var(--ks-text)]'
                "
              >
                <NavIcon :name="item.icon" />
                <span>{{ t(item.key) }}</span>
              </Link>
            </template>
          </div>
        </section>
      </nav>

      <div class="border-t border-[var(--ks-border)] p-3">
        <Link
          href="/profile"
          class="flex items-center gap-3 rounded-[var(--ks-radius-md)] px-3 py-3 transition hover:bg-[var(--ks-surface-1)]"
        >
          <span
            class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-[var(--ks-border-strong)] bg-[var(--ks-gold-soft)] text-xs font-bold text-[var(--ks-gold-strong)]"
          >
            {{ initials || '?' }}
          </span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold">{{ user.name }}</span>
            <span v-if="user.email" class="block truncate text-xs text-[var(--ks-text-muted)]">{{ user.email }}</span>
          </span>
          <NavIcon name="profile" class="text-[var(--ks-text-muted)]" />
        </Link>
      </div>
    </aside>

    <div class="min-h-screen lg:ps-64">
      <header
        class="sticky top-0 z-30 border-b border-[var(--ks-border)] bg-[rgba(5,11,20,0.88)] backdrop-blur-xl"
      >
        <div class="flex min-h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
          <button
            class="grid h-10 w-10 place-items-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] text-[var(--ks-text-secondary)] transition hover:bg-[var(--ks-surface-1)] hover:text-[var(--ks-text)] lg:hidden"
            type="button"
            :aria-label="t('common.openNavigation')"
            @click="mobileOpen = true"
          >
            <NavIcon name="menu" />
          </button>

          <div class="min-w-0 flex-1">
            <p v-if="hasActiveAlliance && allianceName" class="truncate text-sm font-semibold">
              {{ allianceName }}
            </p>
            <p v-else class="truncate text-sm text-[var(--ks-text-muted)]">
              Kingshot Alliance
            </p>
          </div>

          <LocaleSwitcher />

          <Link
            href="/profile"
            class="hidden rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-2.5 text-[var(--ks-text-secondary)] transition hover:bg-[var(--ks-surface-1)] hover:text-[var(--ks-text)] sm:block"
            :aria-label="`${t('navigation.profile')}: ${user.name}`"
          >
            <NavIcon name="profile" />
          </Link>

          <button
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-2.5 text-[var(--ks-text-secondary)] transition hover:border-[rgba(248,113,113,0.4)] hover:bg-[rgba(248,113,113,0.08)] hover:text-[var(--ks-red)]"
            type="button"
            :aria-label="t('common.signOut')"
            @click="logout"
          >
            <NavIcon name="logout" />
          </button>
        </div>
      </header>

      <main id="main-content" class="px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        <div class="mx-auto w-full max-w-[96rem]">
          <slot />
        </div>
      </main>
    </div>

    <Transition
      enter-active-class="transition duration-200"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150"
      leave-to-class="opacity-0"
    >
      <div v-if="mobileOpen" class="fixed inset-0 z-50 lg:hidden">
        <button
          class="absolute inset-0 bg-black/70 backdrop-blur-sm"
          type="button"
          :aria-label="t('common.closeNavigation')"
          @click="closeMobile"
        />

        <aside
          class="absolute inset-y-0 start-0 flex w-[min(88vw,22rem)] flex-col border-e border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] shadow-2xl"
        >
          <div class="flex items-center justify-between gap-4 border-b border-[var(--ks-border)] px-5 py-4">
            <Link href="/dashboard" @click="closeMobile">
              <BrandMark />
            </Link>
            <button
              class="grid h-10 w-10 place-items-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)]"
              type="button"
              :aria-label="t('common.closeNavigation')"
              @click="closeMobile"
            >
              <NavIcon name="close" />
            </button>
          </div>

          <div class="px-4 py-4">
            <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-3">
              <p class="text-[0.68rem] font-bold tracking-[0.18em] text-[var(--ks-text-muted)] uppercase">
                {{ t('common.currentAlliance') }}
              </p>
              <p v-if="hasActiveAlliance && allianceName" class="mt-1.5 truncate text-sm font-semibold">
                {{ allianceName }}
              </p>
              <p v-else class="mt-1.5 text-xs leading-5 text-[var(--ks-text-muted)]">
                {{ t('common.noActiveAlliance') }}
              </p>
            </div>
          </div>

          <nav class="flex-1 overflow-y-auto px-3 pb-5" :aria-label="t('common.menu')">
            <section v-for="group in navigationGroups" :key="group.labelKey" class="mb-5">
              <h2 class="px-3 pb-2 text-[0.68rem] font-bold tracking-[0.17em] text-[var(--ks-text-muted)] uppercase">
                {{ t(group.labelKey) }}
              </h2>
              <div class="space-y-1">
                <template v-for="item in group.items" :key="item.href">
                  <span
                    v-if="isDisabled(item)"
                    class="flex cursor-not-allowed items-center gap-3 rounded-[var(--ks-radius-sm)] px-3 py-2.5 text-sm text-[var(--ks-text-muted)] opacity-45"
                    aria-disabled="true"
                  >
                    <NavIcon :name="item.icon" />
                    <span>{{ t(item.key) }}</span>
                  </span>
                  <Link
                    v-else
                    :href="item.href"
                    class="flex items-center gap-3 rounded-[var(--ks-radius-sm)] px-3 py-2.5 text-sm font-medium transition"
                    :class="
                      isActive(item)
                        ? 'bg-[var(--ks-blue-soft)] text-[var(--ks-blue-strong)]'
                        : 'text-[var(--ks-text-secondary)] hover:bg-[var(--ks-surface-1)] hover:text-[var(--ks-text)]'
                    "
                    @click="closeMobile"
                  >
                    <NavIcon :name="item.icon" />
                    <span>{{ t(item.key) }}</span>
                  </Link>
                </template>
              </div>
            </section>
          </nav>

          <div class="border-t border-[var(--ks-border)] p-4">
            <div class="flex items-center gap-3">
              <span
                class="grid h-10 w-10 place-items-center rounded-full border border-[var(--ks-border-strong)] bg-[var(--ks-gold-soft)] text-xs font-bold text-[var(--ks-gold-strong)]"
              >
                {{ initials || '?' }}
              </span>
              <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ user.name }}</span>
              <button
                class="grid h-10 w-10 place-items-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] text-[var(--ks-text-secondary)]"
                type="button"
                :aria-label="t('common.signOut')"
                @click="logout"
              >
                <NavIcon name="logout" />
              </button>
            </div>
          </div>
        </aside>
      </div>
    </Transition>
  </div>
</template>
