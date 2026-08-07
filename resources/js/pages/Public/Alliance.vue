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
  alliance: {
    name: string;
    slug: string;
    kingdom: string | null;
    language: string;
    timezone: string;
    description: string | null;
    recruitmentStatus: string;
    primaryColor: string | null;
    logoUrl: string | null;
    bannerUrl: string | null;
  };
  filters: { q: string; type: string; category: string; locale: string };
  categories: Array<{ name: string; slug: string }>;
  content: ContentCard[];
  upcomingActivities: unknown[];
  upcomingActivitiesPhase: number;
}>();

const filters = reactive({ ...props.filters });

function applyFilters(): void {
  router.get(
    `/alliances/${props.alliance.slug}`,
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
  <Head :title="alliance.name" />

  <main class="min-h-screen bg-slate-950 text-slate-100">
    <section class="relative overflow-hidden border-b border-slate-800">
      <img
        v-if="alliance.bannerUrl"
        class="absolute inset-0 h-full w-full object-cover opacity-20"
        :src="alliance.bannerUrl"
        :alt="`${alliance.name} banner`"
      />
      <div class="relative mx-auto max-w-6xl px-6 py-14 lg:px-8">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
          <img
            v-if="alliance.logoUrl"
            class="h-24 w-24 rounded-2xl border border-slate-700 object-cover shadow-xl"
            :src="alliance.logoUrl"
            :alt="`${alliance.name} logo`"
          />
          <div>
            <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
              Kingshot alliance
            </p>
            <h1 class="mt-2 text-4xl font-bold tracking-tight sm:text-5xl">{{ alliance.name }}</h1>
            <p class="mt-3 text-sm text-slate-300">
              <span v-if="alliance.kingdom">Kingdom {{ alliance.kingdom }} · </span>
              {{ alliance.language }} · {{ alliance.timezone }}
            </p>
          </div>
        </div>

        <p v-if="alliance.description" class="mt-8 max-w-3xl whitespace-pre-line text-slate-200">
          {{ alliance.description }}
        </p>

        <div class="mt-6 flex flex-wrap items-center gap-3 text-sm">
          <span class="rounded-full border border-slate-700 px-3 py-1.5">
            Recruitment: {{ alliance.recruitmentStatus.replace('_', ' ') }}
          </span>
          <Link
            class="rounded-full border border-cyan-800 px-3 py-1.5 font-semibold text-cyan-300 hover:border-cyan-600 hover:text-cyan-200"
            href="/login"
          >
            Member sign in
          </Link>
        </div>
      </div>
    </section>

    <div class="mx-auto grid max-w-6xl gap-8 px-6 py-10 lg:grid-cols-[minmax(0,1fr)_18rem] lg:px-8">
      <section aria-labelledby="content-heading">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-semibold tracking-[0.16em] text-cyan-300 uppercase">
              Public hub
            </p>
            <h2 id="content-heading" class="mt-1 text-2xl font-bold">Alliance content</h2>
          </div>
          <span class="text-sm text-slate-400"
            >{{ content.length }} result{{ content.length === 1 ? '' : 's' }}</span
          >
        </div>

        <form
          class="mt-6 grid gap-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-5 sm:grid-cols-2"
          @submit.prevent="applyFilters"
        >
          <div class="sm:col-span-2">
            <label class="text-sm font-medium text-slate-200" for="public-search"
              >Search content</label
            >
            <input
              id="public-search"
              v-model="filters.q"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              type="search"
              placeholder="Search titles, summaries, and content"
            />
          </div>
          <div>
            <label class="text-sm font-medium text-slate-200" for="public-type">Content type</label>
            <select
              id="public-type"
              v-model="filters.type"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="">All types</option>
              <option value="announcement">Announcements</option>
              <option value="guide">Guides</option>
              <option value="rule">Rules</option>
              <option value="event_instruction">Event instructions</option>
              <option value="reference_page">Reference pages</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium text-slate-200" for="public-category">Category</label>
            <select
              id="public-category"
              v-model="filters.category"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option value="">All categories</option>
              <option v-for="category in categories" :key="category.slug" :value="category.slug">
                {{ category.name }}
              </option>
            </select>
          </div>
          <div class="flex flex-wrap gap-3 sm:col-span-2">
            <button
              class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950"
              type="submit"
            >
              Apply filters
            </button>
            <button
              class="rounded-lg border border-slate-700 px-4 py-2 font-semibold"
              type="button"
              @click="clearFilters"
            >
              Clear
            </button>
          </div>
        </form>

        <div v-if="content.length" class="mt-6 grid gap-4">
          <article
            v-for="item in content"
            :key="item.id"
            class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6"
          >
            <div
              class="flex flex-wrap items-center gap-2 text-xs font-semibold tracking-wide text-slate-400 uppercase"
            >
              <span>{{ item.typeLabel }}</span>
              <span v-if="item.category">· {{ item.category.name }}</span>
              <span>· {{ item.locale }}</span>
            </div>
            <h3 class="mt-2 text-xl font-semibold">
              <Link
                class="hover:text-cyan-200"
                :href="`/alliances/${alliance.slug}/content/${item.slug}`"
              >
                {{ item.title }}
              </Link>
            </h3>
            <p v-if="item.summary" class="mt-2 text-slate-300">{{ item.summary }}</p>
            <p v-if="item.publishedAt" class="mt-4 text-xs text-slate-500">
              Published {{ formatPublished(item.publishedAt) }}
            </p>
          </article>
        </div>
        <p
          v-else
          class="mt-6 rounded-2xl border border-dashed border-slate-700 p-8 text-center text-slate-400"
        >
          No public content matches these filters.
        </p>
      </section>

      <aside
        class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6 lg:self-start"
        aria-labelledby="activities-heading"
      >
        <h2 id="activities-heading" class="text-lg font-semibold">Upcoming activities</h2>
        <p class="mt-3 text-sm text-slate-400">
          Event schedules arrive in Phase {{ upcomingActivitiesPhase }}. This Phase 2 page
          intentionally does not create placeholder event records.
        </p>
      </aside>
    </div>
  </main>
</template>
