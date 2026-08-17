<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import { useLocale } from '@/localization';

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
  user: { name: string; email: string };
  alliance: { name: string; slug: string; timezone: string };
  viewerTimezone: string;
  canManageContent: boolean;
  filters: { q: string; type: string; category: string; locale: string };
  categories: Array<{ name: string; slug: string }>;
  content: ContentCard[];
}>();

const { t, formatDate, formatNumber } = useLocale();
const filters = reactive({ ...props.filters });

const publicCount = computed(
  () => props.content.filter((item) => item.visibility === 'public').length,
);
const memberCount = computed(
  () => props.content.filter((item) => item.visibility === 'members').length,
);

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

function published(value: string | null): string {
  return value ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' }) : '';
}
</script>

<template>
  <Head :title="`${t('contentExperience.hubTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner eyebrow="Alliance" title="Noticeboard" subtitle="Read Alliance notices and published guidance from your officers before the next Event begins." image="/images/kingshot/noticeboard.svg" compact />

    <section class="ks-surface-gold mt-6 overflow-hidden">
      <dl
        class="grid grid-cols-2 divide-x divide-y divide-[var(--ks-border)] md:grid-cols-4 md:divide-y-0"
      >
        <div class="p-4 sm:p-5">
          <dt
            class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('contentExperience.results') }}
          </dt>
          <dd class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(content.length) }}</dd>
        </div>
        <div class="p-4 sm:p-5">
          <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-green-300 uppercase">
            {{ t('contentExperience.publicItems') }}
          </dt>
          <dd class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(publicCount) }}</dd>
        </div>
        <div class="p-4 sm:p-5">
          <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-amber-300 uppercase">
            {{ t('contentExperience.memberItems') }}
          </dt>
          <dd class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(memberCount) }}</dd>
        </div>
        <div class="p-4 sm:p-5">
          <dt
            class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('contentExperience.categories') }}
          </dt>
          <dd class="ks-display mt-2 text-3xl font-semibold">
            {{ formatNumber(categories.length) }}
          </dd>
        </div>
      </dl>
    </section>

    <form
      class="ks-surface mt-5 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4"
      @submit.prevent="applyFilters"
    >
      <div class="sm:col-span-2 lg:col-span-4">
        <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="member-search">
          {{ t('contentExperience.search') }}
        </label>
        <input
          id="member-search"
          v-model="filters.q"
          class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
          type="search"
          :placeholder="t('contentExperience.searchPlaceholder')"
        />
      </div>
      <div>
        <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="member-type">
          {{ t('contentExperience.type') }}
        </label>
        <select
          id="member-type"
          v-model="filters.type"
          class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
        >
          <option value="">{{ t('contentExperience.allTypes') }}</option>
          <option value="announcement">Announcements</option>
          <option value="guide">Guides</option>
          <option value="rule">Rules</option>
          <option value="event_instruction">Event instructions</option>
          <option value="reference_page">Reference pages</option>
        </select>
      </div>
      <div>
        <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="member-category">
          {{ t('contentExperience.category') }}
        </label>
        <select
          id="member-category"
          v-model="filters.category"
          class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
        >
          <option value="">{{ t('contentExperience.allCategories') }}</option>
          <option v-for="category in categories" :key="category.slug" :value="category.slug">
            {{ category.name }}
          </option>
        </select>
      </div>
      <div>
        <label class="text-xs font-semibold text-[var(--ks-text-secondary)]" for="member-locale">
          {{ t('contentExperience.locale') }}
        </label>
        <input
          id="member-locale"
          v-model="filters.locale"
          class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
          maxlength="16"
          :placeholder="t('contentExperience.anyLocale')"
        />
      </div>
      <div class="flex items-end gap-2">
        <button
          class="min-h-10 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-[var(--ks-ivory)]"
          type="submit"
        >
          {{ t('contentExperience.applyFilters') }}
        </button>
        <button
          class="min-h-10 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold"
          type="button"
          @click="clearFilters"
        >
          {{ t('contentExperience.clear') }}
        </button>
      </div>
    </form>

    <section class="mt-6" aria-labelledby="member-content-heading">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
            {{ t('contentExperience.eyebrow') }}
          </p>
          <h2 id="member-content-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('contentExperience.publishedContent') }}
          </h2>
        </div>
        <p class="text-xs text-[var(--ks-text-muted)]">
          {{ t('contentExperience.displayedIn', { timezone: viewerTimezone }) }}
        </p>
      </div>

      <div v-if="content.length" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="item in content"
          :key="item.id"
          class="ks-surface group flex min-h-56 flex-col p-5"
        >
          <div
            class="flex flex-wrap gap-2 text-[0.68rem] font-bold tracking-[0.08em] text-[var(--ks-text-muted)] uppercase"
          >
            <span>{{ item.typeLabel }}</span>
            <span v-if="item.category">· {{ item.category.name }}</span>
            <span>· {{ item.locale }}</span>
          </div>
          <h3 class="ks-display mt-3 text-xl font-semibold">
            <Link
              class="transition group-hover:text-[var(--ks-blue-strong)]"
              :href="`/alliance/content/${item.slug}`"
            >
              {{ item.title }}
            </Link>
          </h3>
          <p
            v-if="item.summary"
            class="mt-3 line-clamp-3 text-sm leading-6 text-[var(--ks-text-secondary)]"
          >
            {{ item.summary }}
          </p>
          <div
            class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-5 text-xs text-[var(--ks-text-muted)]"
          >
            <span
              v-if="item.visibility === 'members'"
              class="rounded-full border border-amber-400/25 bg-amber-500/10 px-2.5 py-1 font-semibold text-amber-200"
            >
              {{ t('contentExperience.membersOnly') }}
            </span>
            <span
              v-else
              class="rounded-full border border-green-400/25 bg-green-500/10 px-2.5 py-1 font-semibold text-green-200"
            >
              {{ t('contentExperience.public') }}
            </span>
            <span v-if="item.publishedAt">{{ published(item.publishedAt) }}</span>
          </div>
        </article>
      </div>
      <p v-else class="ks-surface mt-4 p-8 text-center text-sm text-[var(--ks-text-muted)]">
        {{ t('contentExperience.noMatches') }}
      </p>
    </section>
  </AppLayout>
</template>
