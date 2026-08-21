<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

import PublicLayout from '@/layouts/PublicLayout.vue';
import { useLocale } from '@/localization';
import { locales } from '@/localization/locales';

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
    recruitmentApplicationUrl: string | null;
  };
  filters: { q: string; type: string; category: string; locale: string };
  categories: Array<{ name: string; slug: string }>;
  content: ContentCard[];
}>();

const { t, formatDate } = useLocale();
const filters = reactive({ ...props.filters });

const typeOptions = [
  { value: 'announcement', key: 'publicAlliance.announcement' },
  { value: 'guide', key: 'publicAlliance.guide' },
  { value: 'rule', key: 'publicAlliance.rule' },
  { value: 'event_instruction', key: 'publicAlliance.eventInstruction' },
  { value: 'reference_page', key: 'publicAlliance.referencePage' },
];

function applyFilters(): void {
  router.get(
    `/alliances/${props.alliance.slug}`,
    Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')),
    { preserveState: true, preserveScroll: true, replace: true },
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
  return value ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' }) : '';
}

function recruitmentStatusLabel(status: string): string {
  if (status === 'open') return t('publicAlliance.statusOpen');
  if (status === 'invitation_only') return t('publicAlliance.statusInvitationOnly');
  return t('publicAlliance.statusClosed');
}

function contentTypeLabel(type: string, fallback: string): string {
  const option = typeOptions.find((item) => item.value === type);
  return option ? t(option.key) : fallback;
}

function localeName(code: string): string {
  return locales.find((item) => item.code === code)?.nativeName ?? code;
}
</script>

<template>
  <Head :title="alliance.name" />

  <PublicLayout>
    <section class="relative isolate overflow-hidden border-b border-[var(--ks-border)]">
      <img
        v-if="alliance.bannerUrl"
        class="absolute inset-0 -z-20 h-full w-full object-cover opacity-45"
        :src="alliance.bannerUrl"
        alt=""
        aria-hidden="true"
      />
      <img
        v-else
        class="absolute inset-0 -z-20 h-full w-full object-cover opacity-40"
        src="/images/kingshot/v4/alliance-hall.svg"
        alt=""
        aria-hidden="true"
      />
      <div
        class="absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(5,11,20,0.98),rgba(5,11,20,0.82),rgba(5,11,20,0.55))]"
      />
      <div class="mx-auto max-w-7xl px-5 py-12 sm:py-16 lg:px-8 lg:py-20">
        <div class="flex max-w-4xl flex-col gap-7 sm:flex-row sm:items-center">
          <div
            class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-[var(--ks-radius-lg)] border border-[var(--ks-border-strong)] bg-[var(--ks-surface-1)] shadow-[var(--ks-shadow-gold)] sm:h-28 sm:w-28"
          >
            <img
              v-if="alliance.logoUrl"
              class="h-full w-full object-cover"
              :src="alliance.logoUrl"
              :alt="alliance.name"
            />
            <span
              v-else
              class="ks-display text-3xl font-bold text-[var(--ks-gold)]"
              aria-hidden="true"
            >
              {{ alliance.name.slice(0, 2).toUpperCase() }}
            </span>
          </div>

          <div class="min-w-0">
            <p class="text-xs font-bold tracking-[0.22em] text-[var(--ks-gold)] uppercase">
              {{ t('publicAlliance.publicAlliance') }}
            </p>
            <h1
              class="ks-display mt-2 text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl"
            >
              {{ alliance.name }}
            </h1>
            <div
              class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-[var(--ks-text-secondary)]"
            >
              <span v-if="alliance.kingdom">
                <strong class="text-[var(--ks-text)]">{{ t('publicAlliance.kingdom') }}</strong>
                {{ alliance.kingdom }}
              </span>
              <span>
                <strong class="text-[var(--ks-text)]">{{ t('publicAlliance.language') }}</strong>
                {{ alliance.language }}
              </span>
              <span>
                <strong class="text-[var(--ks-text)]">{{ t('publicAlliance.timezone') }}</strong>
                {{ alliance.timezone }}
              </span>
            </div>
          </div>
        </div>

        <p
          v-if="alliance.description"
          class="mt-8 max-w-3xl text-base leading-7 whitespace-pre-line text-[var(--ks-text-secondary)] sm:text-lg"
        >
          {{ alliance.description }}
        </p>

        <div class="mt-8 flex flex-wrap items-center gap-3">
          <span
            class="inline-flex items-center gap-2 rounded-full border border-[var(--ks-border)] bg-[rgba(8,17,31,0.78)] px-4 py-2 text-sm font-semibold"
          >
            <span class="h-2 w-2 rounded-full bg-[var(--ks-gold)]" aria-hidden="true" />
            {{ t('publicAlliance.recruitment') }}:
            {{ recruitmentStatusLabel(alliance.recruitmentStatus) }}
          </span>
          <Link
            v-if="alliance.recruitmentApplicationUrl"
            class="ks-command-button"
            :href="alliance.recruitmentApplicationUrl"
          >
            {{ t('publicAlliance.applyToJoin') }}
          </Link>
          <Link class="ks-command-link" href="/login">
            {{ t('common.signIn') }}
          </Link>
        </div>
      </div>
    </section>

    <section
      class="mx-auto max-w-7xl px-5 py-10 sm:py-14 lg:px-8 lg:py-16"
      aria-labelledby="content-heading"
    >
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
            {{ t('publicAlliance.publicHub') }}
          </p>
          <h2 id="content-heading" class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
            {{ t('publicAlliance.allianceContent') }}
          </h2>
        </div>
        <p class="text-sm font-medium text-[var(--ks-text-muted)]">
          {{ t('publicAlliance.results', { count: content.length }) }}
        </p>
      </div>

      <form class="ks-surface mt-7 grid gap-4 p-5 lg:grid-cols-12" @submit.prevent="applyFilters">
        <div class="lg:col-span-5">
          <label class="text-sm font-semibold" for="public-search">{{
            t('publicAlliance.searchContent')
          }}</label>
          <input
            id="public-search"
            v-model="filters.q"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 text-sm transition outline-none placeholder:text-[var(--ks-text-muted)] hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"
            type="search"
            :placeholder="t('publicAlliance.searchPlaceholder')"
          />
        </div>
        <div class="lg:col-span-2">
          <label class="text-sm font-semibold" for="public-type">{{
            t('publicAlliance.contentType')
          }}</label>
          <select
            id="public-type"
            v-model="filters.type"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
          >
            <option value="">{{ t('publicAlliance.allTypes') }}</option>
            <option v-for="option in typeOptions" :key="option.value" :value="option.value">
              {{ t(option.key) }}
            </option>
          </select>
        </div>
        <div class="lg:col-span-2">
          <label class="text-sm font-semibold" for="public-category">{{
            t('publicAlliance.category')
          }}</label>
          <select
            id="public-category"
            v-model="filters.category"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
          >
            <option value="">{{ t('publicAlliance.allCategories') }}</option>
            <option v-for="category in categories" :key="category.slug" :value="category.slug">
              {{ category.name }}
            </option>
          </select>
        </div>
        <div class="lg:col-span-3">
          <label class="text-sm font-semibold" for="public-locale">{{
            t('publicAlliance.contentLanguage')
          }}</label>
          <select
            id="public-locale"
            v-model="filters.locale"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
          >
            <option value="">{{ t('publicAlliance.allLanguages') }}</option>
            <option v-for="item in locales" :key="item.code" :value="item.code">
              {{ item.nativeName }}
            </option>
          </select>
        </div>
        <div class="flex flex-wrap gap-3 lg:col-span-12">
          <button
            class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2.5 text-sm font-bold text-[var(--ks-ivory)] transition hover:bg-[var(--ks-blue-strong)]"
            type="submit"
          >
            {{ t('publicAlliance.applyFilters') }}
          </button>
          <button
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2.5 text-sm font-semibold transition hover:border-[var(--ks-border-strong)] hover:bg-[var(--ks-surface-2)]"
            type="button"
            @click="clearFilters"
          >
            {{ t('publicAlliance.clearFilters') }}
          </button>
        </div>
      </form>

      <div v-if="content.length" class="mt-7 grid gap-4 lg:grid-cols-2">
        <article
          v-for="item in content"
          :key="item.id"
          class="group rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[linear-gradient(180deg,rgba(16,29,46,0.82),rgba(8,17,31,0.88))] p-6 transition hover:-translate-y-0.5 hover:border-[var(--ks-border-strong)]"
        >
          <div
            class="flex flex-wrap items-center gap-2 text-xs font-bold tracking-wide text-[var(--ks-text-muted)] uppercase"
          >
            <span class="text-[var(--ks-gold)]">{{
              contentTypeLabel(item.type, item.typeLabel)
            }}</span>
            <span v-if="item.category">· {{ item.category.name }}</span>
            <span>· {{ localeName(item.locale) }}</span>
          </div>
          <h3 class="mt-3 text-xl font-bold sm:text-2xl">
            <Link
              class="transition group-hover:text-[var(--ks-gold-strong)]"
              :href="`/alliances/${alliance.slug}/content/${item.slug}`"
            >
              {{ item.title }}
            </Link>
          </h3>
          <p
            v-if="item.summary"
            class="mt-3 line-clamp-3 leading-7 text-[var(--ks-text-secondary)]"
          >
            {{ item.summary }}
          </p>
          <p v-if="item.publishedAt" class="mt-5 text-xs text-[var(--ks-text-muted)]">
            {{ t('publicAlliance.published') }} {{ formatPublished(item.publishedAt) }}
          </p>
        </article>
      </div>

      <div
        v-else
        class="mt-7 rounded-[var(--ks-radius-lg)] border border-dashed border-[var(--ks-border)] bg-[rgba(8,17,31,0.45)] px-6 py-14 text-center text-[var(--ks-text-muted)]"
      >
        {{ t('publicAlliance.noMatches') }}
      </div>
    </section>
  </PublicLayout>
</template>
