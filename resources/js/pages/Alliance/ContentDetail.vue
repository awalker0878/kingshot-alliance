<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

defineProps<{
  user: { name: string; email: string };
  alliance: { name: string; slug: string; timezone: string };
  viewerTimezone: string;
  content: {
    typeLabel: string;
    visibility: string;
    title: string;
    summary: string | null;
    body: string;
    locale: string;
    publishedAt: string | null;
    category: { name: string; slug: string } | null;
  };
}>();

const { t, formatDate } = useLocale();

function published(value: string | null): string {
  return value ? formatDate(value, { dateStyle: 'long', timeStyle: 'short' }) : '';
}
</script>

<template>
  <Head :title="`${content.title} · ${alliance.name}`" />

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <Link
      class="inline-flex min-h-10 items-center text-sm font-semibold text-[var(--ks-blue-strong)] hover:text-white"
      href="/alliance/content"
    >
      ← {{ t('contentExperience.backToHub') }}
    </Link>

    <article class="ks-surface-gold mx-auto mt-5 max-w-4xl overflow-hidden">
      <header class="border-b border-[var(--ks-border)] p-6 sm:p-8 lg:p-10">
        <div
          class="flex flex-wrap gap-2 text-[0.68rem] font-bold tracking-[0.08em] text-[var(--ks-text-muted)] uppercase"
        >
          <span>{{ content.typeLabel }}</span>
          <span v-if="content.category">· {{ content.category.name }}</span>
          <span>· {{ content.locale }}</span>
          <span v-if="content.visibility === 'members'" class="text-amber-300">
            · {{ t('contentExperience.membersOnly') }}
          </span>
        </div>
        <h1 class="ks-display mt-3 text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">
          {{ content.title }}
        </h1>
        <p
          v-if="content.summary"
          class="mt-4 max-w-3xl text-lg leading-7 text-[var(--ks-text-secondary)]"
        >
          {{ content.summary }}
        </p>
        <p v-if="content.publishedAt" class="mt-5 text-xs text-[var(--ks-text-muted)]">
          {{ t('contentExperience.published', { date: published(content.publishedAt) }) }} ·
          {{ t('contentExperience.displayedIn', { timezone: viewerTimezone }) }}
        </p>
      </header>
      <div
        class="p-6 text-base leading-8 whitespace-pre-wrap text-[var(--ks-text-secondary)] sm:p-8 lg:p-10"
      >
        {{ content.body }}
      </div>
    </article>
  </AppLayout>
</template>
