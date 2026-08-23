<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import NoticeReactionControls from '@/components/alliance/NoticeReactionControls.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type ReactionSummary = {
  likes: number;
  dislikes: number;
  current: 'like' | 'dislike' | null;
};

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
  reactions: ReactionSummary | null;
  provenance: {
    sourceLabel: string | null;
    gameVersion: string | null;
    reviewedAt: string | null;
  } | null;
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
const contentTypes = computed(() => {
  const types = new Map<string, string>();
  for (const item of props.content) types.set(item.type, item.typeLabel);
  return [...types.entries()].map(([value, label]) => ({ value, label }));
});

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

function visibilityTone(value: string): 'success' | 'warning' | 'info' {
  if (value === 'public') return 'success';
  if (value === 'members') return 'warning';
  return 'info';
}
</script>

<template>
  <Head :title="`${t('contentExperience.hubTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('contentExperience.eyebrow')"
      :title="t('contentExperience.hubTitle')"
      :subtitle="t('contentExperience.hubSubtitle', { alliance: alliance.name })"
      image="/images/kingshot/v4/noticeboard.svg"
    >
      <template #actions>
        <Link href="/alliance/rules" class="ks-command-link">
          {{ t('contentExperience.rulesTitle') }}
        </Link>
        <Link v-if="canManageContent" href="/alliance/content/manage" class="ks-command-link">
          {{ t('contentExperience.manageContent') }}
        </Link>
        <a
          :href="`/alliances/${alliance.slug}`"
          class="ks-command-link"
          data-variant="secondary"
          target="_blank"
          rel="noopener noreferrer"
        >
          {{ t('contentExperience.viewPublicPage') }}
        </a>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
      <StatSeal
        :label="t('contentExperience.results')"
        :value="formatNumber(content.length)"
        icon="▤"
      />
      <StatSeal
        :label="t('contentExperience.publicItems')"
        :value="formatNumber(publicCount)"
        icon="◉"
        tone="teal"
      />
      <StatSeal
        :label="t('contentExperience.memberItems')"
        :value="formatNumber(memberCount)"
        icon="♟"
        tone="stone"
      />
      <StatSeal
        :label="t('contentExperience.categories')"
        :value="formatNumber(categories.length)"
        icon="◇"
      />
    </section>

    <section class="ks-surface mt-5 p-5" aria-labelledby="noticeboard-filters-heading">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p class="ks-kicker">{{ t('contentExperience.search') }}</p>
          <h2 id="noticeboard-filters-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ t('contentExperience.publishedContent') }}
          </h2>
        </div>
        <p class="text-xs text-[var(--ks-muted)]">
          {{ t('contentExperience.displayedIn', { timezone: viewerTimezone }) }}
        </p>
      </div>

      <form class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="applyFilters">
        <div class="md:col-span-2 xl:col-span-2">
          <label class="text-xs font-semibold" for="member-search">
            {{ t('contentExperience.search') }}
          </label>
          <input
            id="member-search"
            v-model="filters.q"
            class="ks-input mt-1.5"
            type="search"
            :placeholder="t('contentExperience.searchPlaceholder')"
          />
        </div>
        <div>
          <label class="text-xs font-semibold" for="member-type">
            {{ t('contentExperience.type') }}
          </label>
          <select id="member-type" v-model="filters.type" class="ks-input mt-1.5">
            <option value="">{{ t('contentExperience.allTypes') }}</option>
            <option v-for="item in contentTypes" :key="item.value" :value="item.value">
              {{ item.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold" for="member-category">
            {{ t('contentExperience.category') }}
          </label>
          <select id="member-category" v-model="filters.category" class="ks-input mt-1.5">
            <option value="">{{ t('contentExperience.allCategories') }}</option>
            <option v-for="category in categories" :key="category.slug" :value="category.slug">
              {{ category.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold" for="member-locale">
            {{ t('contentExperience.locale') }}
          </label>
          <input
            id="member-locale"
            v-model="filters.locale"
            class="ks-input mt-1.5"
            maxlength="16"
            :placeholder="t('contentExperience.anyLocale')"
          />
        </div>
        <div class="flex flex-wrap gap-2 md:col-span-2 xl:col-span-5">
          <button type="submit" class="ks-command-button">
            {{ t('contentExperience.applyFilters') }}
          </button>
          <button
            type="button"
            class="ks-command-button"
            data-variant="secondary"
            @click="clearFilters"
          >
            {{ t('contentExperience.clear') }}
          </button>
        </div>
      </form>
    </section>

    <section class="mt-5" aria-labelledby="member-content-heading">
      <div class="flex flex-wrap items-end justify-between gap-3 px-1">
        <div>
          <p class="ks-kicker">{{ t('contentExperience.eyebrow') }}</p>
          <h2 id="member-content-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('contentExperience.publishedContent') }}
          </h2>
        </div>
      </div>

      <div v-if="content.length" class="mt-4 grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        <article
          v-for="item in content"
          :key="item.id"
          class="ks-surface group flex min-h-64 flex-col p-5 transition hover:-translate-y-0.5 hover:border-[var(--ks-border-strong)]"
        >
          <div class="flex flex-wrap items-center gap-2">
            <span class="ks-chip">{{ item.typeLabel }}</span>
            <span v-if="item.category" class="ks-chip">{{ item.category.name }}</span>
            <span class="ks-chip">{{ item.locale }}</span>
          </div>

          <h3 class="ks-display mt-5 text-2xl leading-tight font-semibold">
            <Link
              class="transition group-hover:text-[var(--ks-gold-bright)]"
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

          <p v-if="item.provenance" class="mt-4 text-xs leading-5 text-[var(--ks-muted)]">
            {{ t('contentExperience.source') }}:
            {{ item.provenance.sourceLabel ?? '—' }}
            <template v-if="item.provenance.reviewedAt">
              · {{ t('contentExperience.reviewed') }} {{ item.provenance.reviewedAt }}
            </template>
          </p>

          <NoticeReactionControls
            v-if="item.type === 'announcement' && item.reactions"
            class="mt-5"
            :content-id="item.id"
            :reactions="item.reactions"
          />

          <div class="mt-auto flex flex-wrap items-end justify-between gap-3 pt-6">
            <span class="ks-status" :data-tone="visibilityTone(item.visibility)">
              {{
                item.visibility === 'members'
                  ? t('contentExperience.membersOnly')
                  : t('contentExperience.public')
              }}
            </span>
            <span v-if="item.publishedAt" class="text-xs text-[var(--ks-muted)]">
              {{ published(item.publishedAt) }}
            </span>
          </div>
        </article>
      </div>
      <div v-else class="ks-fantasy-empty mt-4">
        {{ t('contentExperience.noMatches') }}
      </div>
    </section>
  </AppLayout>
</template>
