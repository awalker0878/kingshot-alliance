<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

defineProps<{
  user: { name: string; email: string };
}>();

const { t } = useLocale();
</script>

<template>
  <Head :title="t('progression.title')" />
  <AppLayout :user="user" active="progression">
    <main class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
      <section class="ks-surface p-6 sm:p-8" data-testid="progression-library-no-dataset">
        <p class="ks-kicker">{{ t('progression.eyebrow') }}</p>
        <h1 class="ks-display mt-2 text-3xl font-semibold">{{ t('progression.noDataset') }}</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('progression.noDatasetHelp') }}
        </p>
        <p class="mt-4 text-sm text-[var(--ks-muted)]">
          {{ t('progression.noRecommendations') }}
        </p>
        <Link href="/progression/governor/planner" class="ks-command-link mt-6 inline-flex">
          {{ t('progression.goalPlanner') }}
        </Link>
      </section>
    </main>
  </AppLayout>
</template>
