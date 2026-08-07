<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
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

function formatPublished(value: string | null): string {
  if (!value) return '';
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'long', timeStyle: 'short' }).format(
    new Date(value),
  );
}
</script>

<template>
  <Head :title="`${content.title} · ${alliance.name}`" />

  <main class="mx-auto min-h-screen max-w-4xl px-6 py-12 text-slate-100 lg:px-8">
    <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/alliance/content">
      ← Content hub
    </Link>

    <article class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/60 p-6 sm:p-10">
      <div class="flex flex-wrap gap-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">
        <span>{{ content.typeLabel }}</span>
        <span v-if="content.category">· {{ content.category.name }}</span>
        <span>· {{ content.locale }}</span>
        <span v-if="content.visibility === 'members'" class="text-amber-300">· Members only</span>
      </div>
      <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ content.title }}</h1>
      <p v-if="content.summary" class="mt-4 text-lg text-slate-300">{{ content.summary }}</p>
      <p v-if="content.publishedAt" class="mt-4 text-sm text-slate-500">
        Published {{ formatPublished(content.publishedAt) }} · Displayed in {{ viewerTimezone }}
      </p>
      <div class="mt-8 whitespace-pre-wrap text-base leading-8 text-slate-200">{{ content.body }}</div>
    </article>
  </main>
</template>
