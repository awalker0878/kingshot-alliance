<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import LocaleSwitcher from '@/components/navigation/LocaleSwitcher.vue';
import { useLocale } from '@/localization';

const { t } = useLocale();
</script>

<template>
  <div class="min-h-screen bg-[var(--ks-night)] text-[var(--ks-ivory)]">
    <header class="sticky top-0 z-40 border-b border-[var(--ks-border)] bg-[rgba(5,10,11,.9)] backdrop-blur-xl">
      <div class="mx-auto flex min-h-[4.75rem] max-w-[96rem] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <Link href="/" class="flex items-center gap-3">
          <div class="grid h-11 w-9 place-items-center border border-[var(--ks-gold-dark)] bg-[linear-gradient(160deg,#17433f,#081c1a)] font-[var(--ks-font-display)] text-base text-[var(--ks-gold-bright)] [clip-path:polygon(50%_0,95%_16%,86%_77%,50%_100%,14%_77%,5%_16%)]">♛</div>
          <div><div class="font-[var(--ks-font-display)] text-xl font-semibold tracking-[.06em] text-[var(--ks-gold-bright)] sm:text-2xl">KINGSHOT</div><div class="hidden text-[.58rem] font-bold tracking-[.18em] text-[var(--ks-muted)] uppercase sm:block">{{ t('navigation.allianceOperations') }}</div></div>
        </Link>
        <div class="flex items-center gap-2 sm:gap-3"><LocaleSwitcher/><Link href="/login" class="ks-command-link">{{ t('common.signIn') }}</Link></div>
      </div>
    </header>
    <main><slot /></main>
  </div>
</template>
