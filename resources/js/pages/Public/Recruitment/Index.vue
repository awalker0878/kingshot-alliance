<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

import PublicLayout from '@/layouts/PublicLayout.vue';
import { useLocale } from '@/localization';

type RecruitingAlliance = {
  name: string;
  slug: string;
  title: string;
  introduction: string | null;
  kingdom: number;
  language: string;
  timezone: string;
  profileUrl: string;
  applicationUrl: string;
};

const props = defineProps<{
  alliances: RecruitingAlliance[];
  filters: { q: string; kingdom: number | null; language: string };
  facets: { kingdoms: number[]; languages: string[] };
  resultLimitReached: boolean;
}>();

const { t, formatNumber } = useLocale();
const filters = reactive({
  q: props.filters.q,
  kingdom: props.filters.kingdom?.toString() ?? '',
  language: props.filters.language,
});
const copied = ref(false);

function applyFilters(): void {
  router.get(
    '/recruitment',
    {
      q: filters.q || undefined,
      kingdom: filters.kingdom || undefined,
      language: filters.language || undefined,
    },
    { preserveState: true, replace: true },
  );
}

function clearFilters(): void {
  filters.q = '';
  filters.kingdom = '';
  filters.language = '';
  applyFilters();
}

async function copyFilteredLink(): Promise<void> {
  if (!navigator.clipboard) return;

  await navigator.clipboard.writeText(window.location.href);
  copied.value = true;
  window.setTimeout(() => {
    copied.value = false;
  }, 2400);
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((word) => word.slice(0, 1).toUpperCase())
    .join('');
}
</script>

<template>
  <Head :title="t('recruitmentBoard.title')" />

  <PublicLayout>
    <section class="relative isolate overflow-hidden border-b border-[var(--ks-border)]">
      <img
        class="absolute inset-0 -z-20 h-full w-full object-cover opacity-35"
        src="/images/kingshot/v4/recruitment-hall.svg"
        alt=""
        aria-hidden="true"
      />
      <div
        class="absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(4,8,9,.98),rgba(5,14,15,.9),rgba(5,11,20,.84))]"
      />
      <div class="mx-auto max-w-7xl px-5 py-12 sm:py-16 lg:px-8">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('recruitmentBoard.eyebrow') }}
        </p>
        <h1 class="ks-display mt-3 max-w-4xl text-4xl font-semibold tracking-tight sm:text-6xl">
          {{ t('recruitmentBoard.title') }}
        </h1>
        <p class="mt-5 max-w-3xl text-base leading-8 text-[var(--ks-text-secondary)]">
          {{ t('recruitmentBoard.body') }}
        </p>
        <p class="mt-5 inline-flex items-center gap-2 text-xs font-semibold text-[var(--ks-muted)]">
          <span aria-hidden="true">◇</span>
          {{ t('recruitmentBoard.publicOnly') }}
        </p>
      </div>
    </section>

    <main class="mx-auto max-w-7xl px-5 py-8 lg:px-8 lg:py-10">
      <form class="ks-surface-gold p-5 sm:p-6" @submit.prevent="applyFilters">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_12rem_12rem_auto] lg:items-end">
          <div>
            <label class="text-sm font-semibold" for="recruitment-board-search">
              {{ t('recruitmentBoard.search') }}
            </label>
            <input
              id="recruitment-board-search"
              v-model="filters.q"
              class="ks-input mt-2"
              maxlength="120"
              :placeholder="t('recruitmentBoard.searchPlaceholder')"
            />
          </div>
          <div>
            <label class="text-sm font-semibold" for="recruitment-board-kingdom">
              {{ t('recruitmentBoard.kingdom') }}
            </label>
            <select id="recruitment-board-kingdom" v-model="filters.kingdom" class="ks-input mt-2">
              <option value="">{{ t('recruitmentBoard.allKingdoms') }}</option>
              <option v-for="kingdom in facets.kingdoms" :key="kingdom" :value="kingdom.toString()">
                {{ kingdom }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-sm font-semibold" for="recruitment-board-language">
              {{ t('recruitmentBoard.language') }}
            </label>
            <select id="recruitment-board-language" v-model="filters.language" class="ks-input mt-2">
              <option value="">{{ t('recruitmentBoard.allLanguages') }}</option>
              <option v-for="language in facets.languages" :key="language" :value="language">
                {{ language.toUpperCase() }}
              </option>
            </select>
          </div>
          <button
            class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-3 font-bold text-[var(--ks-ink)] transition hover:bg-[var(--ks-gold-strong)]"
            type="submit"
          >
            {{ t('recruitmentBoard.applyFilters') }}
          </button>
        </div>
        <div class="mt-4 flex flex-wrap gap-3">
          <button type="button" class="ks-command-link" @click="clearFilters">
            {{ t('recruitmentBoard.clearFilters') }}
          </button>
          <button type="button" class="ks-command-link" @click="copyFilteredLink">
            {{ copied ? t('recruitmentBoard.copied') : t('recruitmentBoard.copyLink') }}
          </button>
        </div>
      </form>

      <div class="mt-7 flex flex-wrap items-center justify-between gap-3">
        <p class="ks-kicker">
          {{ t('recruitmentBoard.resultCount', { count: formatNumber(alliances.length) }) }}
        </p>
        <p v-if="resultLimitReached" class="text-sm text-amber-200">
          {{ t('recruitmentBoard.capped') }}
        </p>
      </div>

      <section v-if="alliances.length" class="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="alliance in alliances"
          :key="alliance.slug"
          class="ks-surface flex min-h-full flex-col overflow-hidden"
        >
          <div class="flex items-start gap-4 border-b border-[var(--ks-border)] p-5">
            <div
              class="grid h-14 w-14 shrink-0 place-items-center border border-[var(--ks-gold-dark)] bg-[linear-gradient(160deg,#17433f,#081c1a)] font-[var(--ks-font-display)] text-lg text-[var(--ks-gold-bright)] [clip-path:polygon(50%_0,95%_16%,86%_77%,50%_100%,14%_77%,5%_16%)]"
              aria-hidden="true"
            >
              {{ initials(alliance.name) }}
            </div>
            <div class="min-w-0">
              <p class="ks-kicker">
                {{ t('publicAlliance.kingdom') }} {{ alliance.kingdom }} ·
                {{ alliance.language.toUpperCase() }}
              </p>
              <h2 class="ks-display mt-1 text-2xl font-semibold text-[var(--ks-gold-bright)]">
                {{ alliance.name }}
              </h2>
            </div>
          </div>
          <div class="flex flex-1 flex-col p-5">
            <h3 class="font-semibold text-[var(--ks-ivory)]">{{ alliance.title }}</h3>
            <p
              v-if="alliance.introduction"
              class="mt-3 line-clamp-4 text-sm leading-6 text-[var(--ks-text-secondary)]"
            >
              {{ alliance.introduction }}
            </p>
            <p class="mt-3 text-xs text-[var(--ks-muted)]">
              {{ alliance.timezone }} · {{ t('recruitmentBoard.listedByAlliance') }}
            </p>
            <div class="mt-auto flex flex-wrap gap-3 pt-6">
              <Link :href="alliance.applicationUrl" class="ks-command-link">
                {{ t('recruitmentBoard.apply') }}
              </Link>
              <Link :href="alliance.profileUrl" class="ks-command-link">
                {{ t('recruitmentBoard.viewAlliance') }}
              </Link>
            </div>
          </div>
        </article>
      </section>

      <section v-else class="ks-fantasy-empty mt-5 p-8 text-center sm:p-12">
        <h2 class="ks-display text-2xl font-semibold">
          {{ t('recruitmentBoard.noMatchesTitle') }}
        </h2>
        <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-[var(--ks-muted)]">
          {{ t('recruitmentBoard.noMatchesBody') }}
        </p>
      </section>
    </main>
  </PublicLayout>
</template>
