<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import LocaleSwitcher from '@/components/navigation/LocaleSwitcher.vue';
import { useLocale } from '@/localization';

const { t } = useLocale();
</script>

<template>
  <div class="min-h-screen bg-[var(--ks-night)] text-[var(--ks-ivory)]">
    <header
      class="sticky top-0 z-40 border-b border-[var(--ks-border)] bg-[rgba(5,10,11,.9)] backdrop-blur-xl"
    >
      <div
        class="mx-auto flex min-h-[4.75rem] max-w-[96rem] items-center justify-between gap-2 px-4 sm:gap-4 sm:px-6 lg:px-8"
      >
        <Link href="/" class="flex min-w-0 shrink-0 items-center gap-3">
          <span class="sr-only">{{ t('common.appName') }}</span>
          <div
            class="grid h-11 w-9 shrink-0 place-items-center border border-[var(--ks-gold-dark)] bg-[linear-gradient(160deg,#17433f,#081c1a)] text-base font-[var(--ks-font-display)] text-[var(--ks-gold-bright)] [clip-path:polygon(50%_0,95%_16%,86%_77%,50%_100%,14%_77%,5%_16%)]"
            aria-hidden="true"
          >
            ♛
          </div>
          <div class="hidden min-[28rem]:block" aria-hidden="true">
            <div
              class="text-xl font-[var(--ks-font-display)] font-semibold tracking-[.06em] text-[var(--ks-gold-bright)] sm:text-2xl"
            >
              KINGSHOT
            </div>
            <div
              class="hidden text-[.58rem] font-bold tracking-[.18em] text-[var(--ks-muted)] uppercase sm:block"
            >
              {{ t('navigation.allianceOperations') }}
            </div>
          </div>
        </Link>
        <div class="flex min-w-0 items-center gap-2 sm:gap-3">
          <Link href="/recruitment" class="ks-command-link hidden shrink-0 px-3 text-xs sm:inline-flex sm:px-4 sm:text-sm">
            {{ t('navigation.recruitment') }}
          </Link>
          <LocaleSwitcher />
          <Link href="/login" class="ks-command-link shrink-0 px-3 text-xs sm:px-4 sm:text-sm">
            {{ t('common.signIn') }}
          </Link>
        </div>
      </div>
    </header>
    <main><slot /></main>
  </div>
</template>
