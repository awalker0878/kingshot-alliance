<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import NavIcon from '@/components/navigation/NavIcon.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { useLocale } from '@/localization';

type ApplicationMetadata = { name: string };
type FeatureIcon =
  | 'alliance'
  | 'events'
  | 'roster'
  | 'recruitment'
  | 'content'
  | 'kingdom'
  | 'transfers'
  | 'integrations';
type Feature = { titleKey: string; descriptionKey: string; icon: FeatureIcon };

defineProps<{ application: ApplicationMetadata }>();
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
  { titleKey: 'navigation.transfers', descriptionKey: 'home.transfersDesc', icon: 'transfers' },
  { titleKey: 'navigation.content', descriptionKey: 'home.contentDesc', icon: 'content' },
  { titleKey: 'home.publicPagesTitle', descriptionKey: 'home.publicPagesDesc', icon: 'alliance' },
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
    <section
      class="relative isolate min-h-[calc(100vh-4.75rem)] overflow-hidden border-b border-[var(--ks-border)]"
    >
      <img
        src="/images/kingshot/v4/realm-gate.svg"
        alt=""
        class="absolute inset-0 -z-30 h-full w-full object-cover object-center opacity-86"
        aria-hidden="true"
      />
      <div
        class="absolute inset-0 -z-20 bg-[linear-gradient(90deg,rgba(3,8,8,.97)_0%,rgba(3,8,8,.87)_38%,rgba(3,8,8,.32)_72%,rgba(3,8,8,.65)_100%)]"
      />
      <div
        class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_68%_45%,rgba(201,154,71,.11),transparent_26rem)]"
      />

      <div
        class="mx-auto grid min-h-[calc(100vh-4.75rem)] max-w-[96rem] items-center gap-12 px-5 py-14 lg:grid-cols-[.86fr_1.14fr] lg:px-8 lg:py-20"
      >
        <div class="max-w-3xl">
          <p class="ks-kicker">{{ application.name }}</p>
          <h1
            class="ks-display mt-5 text-5xl leading-[.91] font-medium tracking-[-.025em] text-[#faf0db] sm:text-6xl lg:text-7xl xl:text-[5.7rem]"
          >
            <span class="block">{{ t('home.heroLine1') }}</span>
            <span class="mt-2 block text-[var(--ks-gold-bright)]">{{ t('home.heroLine2') }}</span>
          </h1>
          <div class="mt-6 flex items-center gap-2" aria-hidden="true">
            <span class="h-px w-16 bg-[var(--ks-gold)]" /><span
              class="h-1.5 w-1.5 rotate-45 border border-[var(--ks-gold)]"
            /><span class="h-px w-40 bg-[linear-gradient(90deg,var(--ks-gold),transparent)]" />
          </div>
          <p class="mt-7 max-w-2xl text-base leading-8 text-[var(--ks-text-secondary)] sm:text-lg">
            {{ t('home.body') }}
          </p>
          <div class="mt-9 flex flex-wrap gap-3">
            <Link href="/register" class="ks-command-link px-6">{{
              t('common.createAccount')
            }}</Link
            ><Link href="/login" class="ks-command-link px-6" data-variant="secondary">{{
              t('common.signIn')
            }}</Link>
          </div>
        </div>

        <div class="relative mx-auto w-full max-w-3xl lg:mx-0">
          <div class="absolute -inset-8 -z-10 rounded-full bg-[var(--ks-gold-soft)] blur-3xl" />
          <div
            class="overflow-hidden rounded-[var(--ks-radius-xl)] border border-[var(--ks-border-strong)] bg-[rgba(6,12,12,.9)] shadow-[0_35px_100px_rgba(0,0,0,.58)] backdrop-blur-xl"
          >
            <div
              class="flex items-center justify-between border-b border-[var(--ks-border)] bg-[linear-gradient(90deg,var(--ks-gold-soft),transparent)] px-5 py-4"
            >
              <div>
                <p class="ks-kicker">{{ t('navigation.dashboard') }}</p>
                <p class="mt-1 text-lg font-[var(--ks-font-display)] text-[var(--ks-ivory)]">
                  {{ t('common.currentPlayer') }}
                </p>
              </div>
              <div class="flex items-center gap-3">
                <div
                  class="grid h-11 w-10 place-items-center border border-[var(--ks-gold-dark)] bg-[linear-gradient(160deg,#126b64,#0a2f2c)] font-[var(--ks-font-display)] text-[var(--ks-gold-bright)] [clip-path:polygon(50%_0,95%_16%,86%_76%,50%_100%,14%_76%,5%_16%)]"
                >
                  K
                </div>
                <span class="text-xs text-[var(--ks-teal-bright)]">K1123</span>
              </div>
            </div>
            <div class="grid gap-px bg-[var(--ks-border)] sm:grid-cols-2">
              <article
                v-for="feature in features.slice(0, 4)"
                :key="feature.titleKey"
                class="bg-[rgba(8,14,14,.98)] p-5"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="grid h-10 w-10 place-items-center rounded border border-[var(--ks-border)] bg-[var(--ks-gold-soft)] text-[var(--ks-gold-bright)]"
                  >
                    <NavIcon :name="feature.icon" />
                  </div>
                  <div>
                    <h2 class="text-base font-[var(--ks-font-display)] font-semibold">
                      {{ t(feature.titleKey) }}
                    </h2>
                    <p class="mt-1 text-xs text-[var(--ks-muted)]">
                      {{ t(feature.descriptionKey) }}
                    </p>
                  </div>
                </div>
                <div class="mt-5 h-1 overflow-hidden rounded-full bg-white/[.05]">
                  <div
                    class="h-full w-2/3 rounded-full bg-[linear-gradient(90deg,var(--ks-teal),var(--ks-gold))]"
                  />
                </div>
              </article>
            </div>
            <div
              class="flex items-center justify-between gap-4 border-t border-[var(--ks-border)] p-5"
            >
              <div>
                <p class="text-xs text-[var(--ks-muted)]">{{ t('home.multilingualTitle') }}</p>
                <p class="ks-display mt-1 text-2xl text-[var(--ks-gold-bright)]">17</p>
              </div>
              <div class="flex -space-x-2 rtl:space-x-reverse">
                <span
                  v-for="code in ['EN', 'FR', 'PT', 'AR']"
                  :key="code"
                  class="grid h-9 w-9 place-items-center rounded-full border border-[var(--ks-border)] bg-[var(--ks-surface-2)] text-[.6rem] font-bold"
                  >{{ code }}</span
                >
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="features" class="mx-auto max-w-[96rem] px-5 py-16 lg:px-8 lg:py-24">
      <div class="mx-auto max-w-3xl text-center">
        <p class="ks-kicker">{{ t('home.featuresTitle') }}</p>
        <h2 class="ks-display mt-3 text-4xl font-medium sm:text-5xl">
          {{ t('home.featuresTitle') }}
        </h2>
      </div>
      <div class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="feature in features"
          :key="feature.titleKey"
          class="group ks-surface p-5 transition hover:-translate-y-0.5 hover:border-[var(--ks-border-strong)]"
        >
          <div
            class="grid h-12 w-12 place-items-center rounded-[var(--ks-radius-md)] border border-[var(--ks-border-strong)] bg-[var(--ks-gold-soft)] text-[var(--ks-gold-bright)]"
          >
            <NavIcon :name="feature.icon" />
          </div>
          <h3 class="ks-display mt-5 text-xl font-semibold">{{ t(feature.titleKey) }}</h3>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
            {{ t(feature.descriptionKey) }}
          </p>
        </article>
      </div>
    </section>

    <section class="mx-auto max-w-[96rem] px-5 pb-20 lg:px-8 lg:pb-28">
      <div class="ks-surface-gold relative overflow-hidden p-7 sm:p-9">
        <div
          class="absolute -end-20 -top-24 h-80 w-80 rounded-full bg-[var(--ks-gold-soft)] blur-3xl"
        />
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
          <div class="max-w-3xl">
            <p class="ks-kicker">{{ t('common.appName') }}</p>
            <h2 class="ks-display mt-2 text-3xl font-medium sm:text-4xl">
              {{ t('home.ctaTitle') }}
            </h2>
            <p class="mt-3 text-sm leading-7 text-[var(--ks-text-secondary)]">
              {{ t('home.ctaBody') }}
            </p>
          </div>
          <div class="flex shrink-0 flex-wrap gap-3">
            <Link href="/register" class="ks-command-link px-6">{{
              t('common.createAccount')
            }}</Link
            ><Link href="/login" class="ks-command-link px-6" data-variant="secondary">{{
              t('common.signIn')
            }}</Link>
          </div>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
