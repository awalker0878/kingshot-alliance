<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import BrandMark from '../components/brand/BrandMark.vue';
import LocaleSwitcher from '../components/navigation/LocaleSwitcher.vue';
import { useLocale } from '../localization';

const { t } = useLocale();
</script>

<template>
  <a
    class="sr-only z-50 rounded-md bg-[var(--ks-blue)] px-4 py-2 font-bold text-white focus:not-sr-only focus:fixed focus:start-4 focus:top-4"
    href="#auth-content"
  >
    {{ t('common.skipToContent') }}
  </a>

  <div class="relative min-h-screen overflow-x-hidden bg-[var(--ks-bg)] text-[var(--ks-text)]">
    <div
      class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_15%_10%,rgba(40,86,144,0.3),transparent_28rem),radial-gradient(circle_at_85%_12%,rgba(226,180,77,0.12),transparent_24rem),linear-gradient(180deg,rgba(5,11,20,0.1),rgba(5,11,20,0.82))]"
      aria-hidden="true"
    />
    <div
      class="pointer-events-none fixed inset-x-0 bottom-0 h-72 bg-[linear-gradient(180deg,transparent,rgba(13,24,40,0.75))]"
      aria-hidden="true"
    />

    <header
      class="relative z-20 mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8 lg:px-12"
    >
      <Link href="/" aria-label="Kingshot Alliance home">
        <BrandMark />
      </Link>
      <LocaleSwitcher />
    </header>

    <main
      id="auth-content"
      tabindex="-1"
      class="relative z-10 mx-auto grid min-h-[calc(100vh-92px)] max-w-7xl content-center gap-8 px-5 py-8 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(28rem,0.88fr)] lg:gap-14 lg:px-12 lg:py-12"
    >
      <section class="max-w-2xl self-center">
        <p class="text-xs font-bold tracking-[0.22em] text-[var(--ks-gold)] uppercase">
          Kingshot Alliance
        </p>
        <h1
          class="ks-display mt-4 text-3xl leading-[1.04] font-semibold tracking-tight sm:text-4xl lg:text-5xl xl:text-6xl"
        >
          <slot name="headline">{{ t('authExperience.shell.headline') }}</slot>
        </h1>
        <div
          class="mt-4 max-w-xl text-base leading-7 text-[var(--ks-text-secondary)] sm:text-lg sm:leading-8"
        >
          <slot name="intro">{{ t('authExperience.shell.intro') }}</slot>
        </div>
      </section>

      <section class="ks-surface-gold w-full self-center p-5 sm:p-7 lg:p-8">
        <slot />
      </section>
    </main>
  </div>
</template>
