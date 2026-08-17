<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import NavIcon from '@/components/navigation/NavIcon.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { useLocale } from '@/localization';

type ApplicationMetadata = {
  name: string;
};

type FeatureIcon =
  | 'alliance'
  | 'events'
  | 'roster'
  | 'recruitment'
  | 'content'
  | 'kingdom'
  | 'transfers'
  | 'integrations';

type Feature = {
  titleKey: string;
  descriptionKey: string;
  icon: FeatureIcon;
};

defineProps<{
  application: ApplicationMetadata;
}>();

const { t } = useLocale();

const features: Feature[] = [
  { titleKey: 'navigation.events', descriptionKey: 'home.eventsDesc', icon: 'events' },
  { titleKey: 'navigation.roster', descriptionKey: 'home.rosterDesc', icon: 'roster' },
  {
    titleKey: 'navigation.recruitment',
    descriptionKey: 'home.recruitmentDesc',
    icon: 'recruitment',
  },
  { titleKey: 'navigation.kingdom', descriptionKey: 'home.kingdomDesc', icon: 'kingdom' },
  {
    titleKey: 'navigation.transfers',
    descriptionKey: 'home.transfersDesc',
    icon: 'transfers',
  },
  { titleKey: 'navigation.content', descriptionKey: 'home.contentDesc', icon: 'content' },
  {
    titleKey: 'home.publicPagesTitle',
    descriptionKey: 'home.publicPagesDesc',
    icon: 'alliance',
  },
  {
    titleKey: 'home.multilingualTitle',
    descriptionKey: 'home.multilingualDesc',
    icon: 'integrations',
  },
];
</script>

<template>
  <Head :title="application.name" />

  <PublicLayout>
    <section class="relative isolate overflow-hidden border-b border-[var(--ks-border)]">
      <img src="/images/kingshot/realm-command.svg" alt="" class="pointer-events-none absolute inset-0 -z-20 h-full w-full object-cover opacity-80" />
      <div class="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(5,9,9,.94)_0%,rgba(5,9,9,.72)_45%,rgba(5,9,9,.36)_72%,rgba(5,9,9,.72)_100%)]" />

      <div
        class="mx-auto grid min-h-[38rem] max-w-7xl items-center gap-12 px-5 py-16 lg:grid-cols-[0.92fr_1.08fr] lg:px-8 lg:py-24"
      >
        <div class="max-w-2xl">
          <p class="text-sm font-bold tracking-[0.22em] text-[var(--ks-gold)] uppercase">
            {{ application.name }}
          </p>
          <h1 class="ks-display mt-5 text-5xl leading-[0.98] font-bold sm:text-6xl lg:text-7xl">
            <span class="block">{{ t('home.heroLine1') }}</span>
            <span class="mt-2 block text-[var(--ks-gold)]">{{ t('home.heroLine2') }}</span>
          </h1>
          <p class="mt-7 max-w-xl text-base leading-8 text-[var(--ks-text-secondary)] sm:text-lg">
            {{ t('home.body') }}
          </p>

          <div class="mt-9 flex flex-wrap gap-3">
            <Link
              href="/login"
              class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-3 text-sm font-bold text-[var(--ks-ink)] shadow-[0_14px_40px_rgba(226,180,77,0.16)] transition hover:bg-[var(--ks-gold-strong)]"
            >
              {{ t('common.signIn') }}
            </Link>
            <Link
              href="/register"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-strong)] bg-[rgba(8,17,31,0.72)] px-5 py-3 text-sm font-bold transition hover:bg-[var(--ks-surface-1)]"
            >
              {{ t('common.createAccount') }}
            </Link>
          </div>
        </div>

        <div class="relative mx-auto w-full max-w-2xl lg:mx-0">
          <div class="absolute -inset-8 -z-10 rounded-full bg-[var(--ks-gold-soft)] blur-3xl" />
          <div class="ks-panel-royal overflow-hidden">
            <div
              class="flex items-center justify-between border-b border-[var(--ks-border)] px-4 py-3 sm:px-5"
            >
              <div
                class="flex items-center gap-2 text-xs font-semibold text-[var(--ks-text-secondary)]"
              >
                <span class="h-2 w-2 rounded-full bg-[var(--ks-green)]" />
                {{ t('home.featuresTitle') }}
              </div>
              <span class="text-xs font-bold tracking-[0.12em] text-[var(--ks-gold)] uppercase"
                >Kingshot</span
              >
            </div>
            <div class="grid min-h-80 grid-cols-[5.5rem_1fr] sm:grid-cols-[8rem_1fr]">
              <div class="border-e border-[var(--ks-border)] bg-[rgba(5,11,20,0.55)] p-3 sm:p-4">
                <div class="space-y-2">
                  <div
                    v-for="item in features.slice(0, 5)"
                    :key="item.titleKey"
                    class="flex items-center gap-2 rounded-lg px-2 py-2 text-[11px] text-[var(--ks-text-muted)] first:bg-[var(--ks-blue-soft)] first:text-[var(--ks-blue-strong)] sm:text-xs"
                  >
                    <NavIcon :name="item.icon" />
                    <span class="hidden truncate sm:inline">{{ t(item.titleKey) }}</span>
                  </div>
                </div>
              </div>
              <div class="p-4 sm:p-5">
                <div class="grid gap-3 sm:grid-cols-2">
                  <article
                    v-for="item in features.slice(0, 4)"
                    :key="`preview-${item.titleKey}`"
                    class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[rgba(11,22,38,0.8)] p-4"
                  >
                    <div class="flex items-center gap-3 text-[var(--ks-gold)]">
                      <NavIcon :name="item.icon" />
                      <h2 class="text-sm font-bold text-[var(--ks-text)]">
                        {{ t(item.titleKey) }}
                      </h2>
                    </div>
                    <div class="mt-4 h-1.5 rounded-full bg-[var(--ks-surface-3)]">
                      <div class="h-full w-2/3 rounded-full bg-[var(--ks-blue)]" />
                    </div>
                  </article>
                </div>
                <div
                  class="mt-3 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[rgba(8,17,31,0.75)] p-4"
                >
                  <div class="flex items-center justify-between gap-4">
                    <div>
                      <p class="text-xs text-[var(--ks-text-muted)]">
                        {{ t('home.multilingualTitle') }}
                      </p>
                      <p class="mt-1 text-2xl font-bold text-[var(--ks-gold)]">17</p>
                    </div>
                    <div class="flex -space-x-2 rtl:space-x-reverse">
                      <span
                        v-for="code in ['EN', 'FR', 'PT', 'AR']"
                        :key="code"
                        class="grid h-9 w-9 place-items-center rounded-full border border-[var(--ks-border)] bg-[var(--ks-surface-2)] text-[10px] font-bold"
                        >{{ code }}</span
                      >
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="features" class="mx-auto max-w-7xl px-5 py-16 lg:px-8 lg:py-20">
      <div class="mx-auto max-w-2xl text-center">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          Kingshot Alliance
        </p>
        <h2 class="ks-display mt-3 text-3xl font-bold sm:text-4xl">
          {{ t('home.featuresTitle') }}
        </h2>
      </div>

      <div class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="feature in features"
          :key="feature.titleKey"
          class="group rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[linear-gradient(180deg,rgba(16,29,46,0.86),rgba(8,17,31,0.9))] p-5 transition hover:-translate-y-0.5 hover:border-[var(--ks-border-strong)]"
        >
          <div
            class="grid h-11 w-11 place-items-center rounded-xl border border-[var(--ks-border-strong)] bg-[var(--ks-gold-soft)] text-[var(--ks-gold)]"
          >
            <NavIcon :name="feature.icon" />
          </div>
          <h3 class="mt-5 text-base font-bold">{{ t(feature.titleKey) }}</h3>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-text-muted)]">
            {{ t(feature.descriptionKey) }}
          </p>
        </article>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 pb-20 lg:px-8">
      <div
        class="ks-surface-gold flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-center lg:justify-between"
      >
        <div class="max-w-2xl">
          <h2 class="ks-display text-3xl font-bold">{{ t('home.ctaTitle') }}</h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{ t('home.ctaBody') }}
          </p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-3">
          <Link
            href="/login"
            class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-3 text-sm font-bold text-[var(--ks-ink)] hover:bg-[var(--ks-gold-strong)]"
          >
            {{ t('common.signIn') }}
          </Link>
          <Link
            href="/register"
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-5 py-3 text-sm font-bold hover:bg-[var(--ks-surface-1)]"
          >
            {{ t('common.createAccount') }}
          </Link>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
