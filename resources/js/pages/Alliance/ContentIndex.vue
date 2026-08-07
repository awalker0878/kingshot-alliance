<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

type ContentCard = {
  id: string;
  type: string;
  typeLabel: string;
  visibility: string;
  status: string;
  title: string;
  slug: string;
  summary: string | null;
  locale: string;
  publishedAt: string | null;
  category: { id: string; name: string; slug: string } | null;
};

const props = defineProps<{
  alliance: { name: string; slug: string; timezone: string };
  viewerTimezone: string;
  canManageContent: boolean;
  filters: { q: string; type: string; category: string; locale: string };
  categories: Array<{ name: string; slug: string }>;
  content: ContentCard[];
}>();

const filters = reactive({ ...props.filters });

function applyFilters(): void {
  router.get(
    '/alliance/content',
    Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')),
    { preserveState: true, replace: true },
  );
}

function clearFilters(): void {
  filters.q = '';
  filters.type = '';
  filters.category = '';
  filters.locale = '';
  applyFilters();
}

function formatPublished(value: string | null): string {
  if (!value) return '';
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(
    new Date(value),
  );
}
</script>

<template>
  <Head :title="`${alliance.name} content`" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 text-slate-100 lg:px-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/alliance">
          ← Alliance home
        </Link>
        <h1 class="mt-3 text-3xl font-bold">Content hub</h1>
        <p class="mt-2 text-slate-400">Published public and member-only information for {{ alliance.name }}.</p>
      </div>
      <Link
        v-if="canManageContent"
        class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950"
        href="/alliance/content/manage"
      >
        Manage content
      </Link>
    </div>

    <form class="mt-8 grid gap-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-5 sm:grid-cols-2" @submit.prevent="applyFilters">
      <div class="sm:col-span-2">
        <label class="text-sm font-medium" for="member-search">Search alliance content</label>
        <input
          id="member-search"
          v-model="filters.q"
          class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          type="search"
          placeholder="Search titles, summaries, and content"
        />
      </div>
      <div>
        <label class="text-sm font-medium" for="member-type">Content type</label>
        <select id="member-type" v-model="filters.type" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
          <option value="">All types</option>
          <option value="announcement">Announcements</option>
          <option value="guide">Guides</option>
          <option value="rule">Rules</option>
          <option value="event_instruction">Event instructions</option>
          <option value="reference_page">Reference pages</option>
        </select>
      </div>
      <div>
        <label class="text-sm font-medium" for="member-category">Category</label>
        <select id="member-category" v-model="filters.category" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
          <option value="">All categories</option>
          <option v-for="category in categories" :key="category.slug" :value="category.slug">
            {{ category.name }}
          </option>
        </select>
      </div>
      <div class="sm:col-span-2 flex flex-wrap gap-3">
        <button class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950" type="submit">Apply filters</button>
        <button class="rounded-lg border border-slate-700 px-4 py-2 font-semibold" type="button" @click="clearFilters">Clear</button>
      </div>
    </form>

    <section class="mt-8" aria-labelledby="member-content-heading">
      <div class="flex items-center justify-between gap-4">
        <h2 id="member-content-heading" class="text-xl font-semibold">Published content</h2>
        <p class="text-sm text-slate-500">Displayed in {{ viewerTimezone }}</p>
      </div>

      <div v-if="content.length" class="mt-4 grid gap-4 sm:grid-cols-2">
        <article v-for="item in content" :key="item.id" class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
          <div class="flex flex-wrap gap-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">
            <span>{{ item.typeLabel }}</span>
            <span v-if="item.category">· {{ item.category.name }}</span>
            <span v-if="item.visibility === 'members'" class="text-amber-300">· Members only</span>
          </div>
          <h3 class="mt-2 text-xl font-semibold">
            <Link class="hover:text-cyan-200" :href="`/alliance/content/${item.slug}`">{{ item.title }}</Link>
          </h3>
          <p v-if="item.summary" class="mt-2 text-slate-300">{{ item.summary }}</p>
          <p v-if="item.publishedAt" class="mt-4 text-xs text-slate-500">{{ formatPublished(item.publishedAt) }}</p>
        </article>
      </div>
      <p v-else class="mt-4 rounded-2xl border border-dashed border-slate-700 p-8 text-center text-slate-400">
        No published content matches these filters.
      </p>
    </section>
  </main>
</template>
