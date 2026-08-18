<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import LocaleSwitcher from '@/components/navigation/LocaleSwitcher.vue';
import { useLocale } from '@/localization';

const { t } = useLocale();
</script>

<template>
  <main class="relative min-h-screen overflow-hidden bg-[var(--ks-night)]">
    <img src="/images/kingshot/v4/realm-gate.svg" alt="" class="absolute inset-0 h-full w-full object-cover object-center opacity-72" aria-hidden="true" />
    <div class="absolute inset-0 bg-[linear-gradient(90deg,rgba(3,8,8,.97)_0%,rgba(3,8,8,.83)_40%,rgba(3,8,8,.54)_67%,rgba(3,8,8,.82)_100%)]" />
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_72%_42%,rgba(201,154,71,.11),transparent_24rem)]" />

    <div class="relative z-10 mx-auto flex min-h-screen max-w-[92rem] flex-col px-4 py-4 sm:px-6 lg:px-8 lg:py-8">
      <header class="flex items-center justify-between gap-4">
        <Link href="/" class="flex items-center gap-3">
          <div class="grid h-12 w-10 place-items-center border border-[var(--ks-gold-dark)] bg-[linear-gradient(160deg,#17433f,#081c1a)] font-[var(--ks-font-display)] text-lg text-[var(--ks-gold-bright)] [clip-path:polygon(50%_0,95%_16%,86%_77%,50%_100%,14%_77%,5%_16%)]">♛</div>
          <div><div class="font-[var(--ks-font-display)] text-xl font-semibold tracking-[.06em] text-[var(--ks-gold-bright)] sm:text-2xl">KINGSHOT</div><div class="text-[.58rem] font-bold tracking-[.18em] text-[var(--ks-muted)] uppercase">{{ t('navigation.allianceOperations') }}</div></div>
        </Link>
        <LocaleSwitcher />
      </header>

      <div class="flex flex-1 items-center py-10 lg:py-14">
        <div class="grid w-full gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(26rem,33rem)] lg:items-center">
          <section class="hidden max-w-2xl lg:block">
            <p class="ks-kicker">{{ t('common.appName') }}</p>
            <h1 class="ks-display mt-4 text-6xl font-medium leading-[.92] text-[#f8efdc] xl:text-7xl">{{ t('navigation.home') }}</h1>
            <div class="mt-6 flex items-center gap-2" aria-hidden="true"><span class="h-px w-16 bg-[var(--ks-gold)]"/><span class="h-1.5 w-1.5 rotate-45 border border-[var(--ks-gold)]"/><span class="h-px w-36 bg-[linear-gradient(90deg,var(--ks-gold),transparent)]"/></div>
            <p class="mt-6 max-w-xl text-base leading-8 text-[var(--ks-text-secondary)]">{{ t('application.dashboard.playerContextIntro') }}</p>
          </section>

          <section class="relative overflow-hidden rounded-[var(--ks-radius-xl)] border border-[var(--ks-border-strong)] bg-[rgba(6,12,12,.94)] shadow-[0_35px_100px_rgba(0,0,0,.58)] backdrop-blur-xl">
            <div class="absolute inset-x-0 top-0 h-px bg-[linear-gradient(90deg,transparent,var(--ks-gold-bright),transparent)] opacity-60" />
            <div class="border-b border-[var(--ks-border)] bg-[linear-gradient(90deg,var(--ks-gold-soft),transparent)] px-6 py-4 sm:px-8">
              <p class="ks-kicker">{{ t('common.appName') }}</p>
            </div>
            <div class="p-6 sm:p-8"><slot /></div>
          </section>
        </div>
      </div>

      <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--ks-border)] pt-4 text-xs text-[var(--ks-muted)]">
        <Link href="/" class="transition hover:text-[var(--ks-gold-bright)]">← {{ t('navigation.home') }}</Link>
        <span>{{ t('common.appName') }}</span>
      </footer>
    </div>
  </main>
</template>
