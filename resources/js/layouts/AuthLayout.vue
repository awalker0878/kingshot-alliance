<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import BrandMark from '../components/brand/BrandMark.vue';
import LocaleSwitcher from '../components/navigation/LocaleSwitcher.vue';
</script>

<template>
  <div class="relative min-h-screen overflow-hidden bg-[var(--ks-bg)] text-[var(--ks-text)]">
    <div
      class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(40,86,144,0.24),transparent_30rem),radial-gradient(circle_at_80%_10%,rgba(226,180,77,0.08),transparent_24rem)]"
      aria-hidden="true"
    />

    <header class="relative z-10 flex items-center justify-between px-5 py-5 sm:px-8 lg:px-12">
      <Link href="/" aria-label="Kingshot Alliance home">
        <BrandMark />
      </Link>
      <LocaleSwitcher />
    </header>

    <main
      class="relative z-10 mx-auto grid min-h-[calc(100vh-90px)] max-w-7xl items-center gap-10 px-5 py-8 lg:grid-cols-[1fr_0.9fr] lg:px-12 lg:py-12"
    >
      <section class="hidden lg:block">
        <p class="text-sm font-semibold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          Kingshot Alliance
        </p>
        <h1 class="ks-display mt-5 max-w-xl text-5xl leading-[1.05] font-semibold xl:text-6xl">
          <slot name="headline">Built for alliances.</slot>
        </h1>
        <div class="mt-6 max-w-xl text-lg leading-8 text-[var(--ks-text-secondary)]">
          <slot name="intro" />
        </div>
      </section>

      <section class="ks-surface-gold mx-auto w-full max-w-xl p-5 sm:p-7 lg:p-8">
        <slot />
      </section>
    </main>
  </div>
</template>
