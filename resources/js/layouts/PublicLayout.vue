<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import BrandMark from '../components/brand/BrandMark.vue';
import LocaleSwitcher from '../components/navigation/LocaleSwitcher.vue';
import { useLocale } from '../localization';

const { t } = useLocale();
</script>

<template>
  <div class="min-h-screen text-[var(--ks-text)]">
    <header class="border-b border-[var(--ks-border)] bg-[rgba(5,11,20,0.88)] backdrop-blur-xl">
      <div class="mx-auto flex min-h-18 max-w-7xl items-center justify-between gap-6 px-5 py-4 lg:px-8">
        <Link href="/" class="shrink-0" aria-label="Kingshot Alliance home">
          <BrandMark />
        </Link>

        <div class="flex items-center gap-2 sm:gap-3">
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
      </div>
    </header>

    <main>
      <slot />
    </main>
  </div>
</template>
