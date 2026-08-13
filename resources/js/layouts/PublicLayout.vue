<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

import BrandMark from '../components/brand/BrandMark.vue';
import LocaleSwitcher from '../components/navigation/LocaleSwitcher.vue';
import NavIcon from '../components/navigation/NavIcon.vue';
import { useLocale } from '../localization';

const { t } = useLocale();
const mobileOpen = ref(false);

function closeMobile(): void {
  mobileOpen.value = false;
}
</script>

<template>
  <div class="min-h-screen text-[var(--ks-text)]">
    <a
      class="sr-only z-50 rounded-md bg-[var(--ks-blue)] px-4 py-2 font-bold text-white focus:not-sr-only focus:fixed focus:start-4 focus:top-4"
      href="#public-content"
    >
      {{ t('common.skipToContent') }}
    </a>

    <header
      class="sticky top-0 z-40 border-b border-[var(--ks-border)] bg-[rgba(5,11,20,0.9)] backdrop-blur-xl"
    >
      <div
        class="mx-auto flex min-h-18 max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-5 lg:px-8"
      >
        <Link
          href="/"
          class="min-w-0 shrink"
          aria-label="Kingshot Alliance home"
          @click="closeMobile"
        >
          <BrandMark class="hidden sm:inline-flex" />
          <BrandMark compact class="sm:hidden" />
        </Link>

        <div class="hidden items-center gap-3 sm:flex">
          <LocaleSwitcher />
          <Link
            href="/login"
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2.5 text-sm font-semibold transition hover:border-[var(--ks-border-strong)] hover:bg-[var(--ks-surface-1)]"
          >
            {{ t('common.signIn') }}
          </Link>
          <Link
            href="/register"
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-strong)] bg-[var(--ks-gold)] px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-[var(--ks-gold-strong)]"
          >
            {{ t('common.createAccount') }}
          </Link>
        </div>

        <div class="flex min-w-0 items-center gap-2 sm:hidden">
          <LocaleSwitcher />
          <button
            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)]"
            type="button"
            :aria-label="mobileOpen ? t('common.closeNavigation') : t('common.openNavigation')"
            :aria-expanded="mobileOpen"
            aria-controls="public-mobile-navigation"
            @click="mobileOpen = !mobileOpen"
          >
            <NavIcon :name="mobileOpen ? 'close' : 'menu'" />
          </button>
        </div>
      </div>

      <div
        v-if="mobileOpen"
        id="public-mobile-navigation"
        class="border-t border-[var(--ks-border)] bg-[rgba(8,17,31,0.98)] px-5 py-4 sm:hidden"
      >
        <nav class="mx-auto grid max-w-7xl gap-2" :aria-label="t('common.menu')">
          <Link
            href="/"
            class="rounded-[var(--ks-radius-sm)] border border-transparent px-4 py-3 text-sm font-semibold hover:border-[var(--ks-border)] hover:bg-[var(--ks-surface-1)]"
            @click="closeMobile"
          >
            {{ t('navigation.home') }}
          </Link>
          <Link
            href="/login"
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-3 text-center text-sm font-semibold"
            @click="closeMobile"
          >
            {{ t('common.signIn') }}
          </Link>
          <Link
            href="/register"
            class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-3 text-center text-sm font-bold text-slate-950"
            @click="closeMobile"
          >
            {{ t('common.createAccount') }}
          </Link>
        </nav>
      </div>
    </header>

    <main id="public-content">
      <slot />
    </main>
  </div>
</template>
