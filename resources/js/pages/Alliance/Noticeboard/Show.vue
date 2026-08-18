<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { name: string; slug: string };
  canManageContent: boolean;
  content: {
    id: string;
    type: string;
    typeLabel: string;
    visibility: string;
    title: string;
    slug: string;
    summary: string | null;
    body: string;
    locale: string;
    publishedAt: string | null;
    category: { name: string; slug: string } | null;
  };
}>();

const { t, formatDate } = useLocale();

function published(value: string | null): string {
  return value ? formatDate(value, { dateStyle: 'long', timeStyle: 'short' }) : '—';
}
</script>

<template>
  <Head :title="`${content.title} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="content.typeLabel"
      :title="content.title"
      :subtitle="content.summary ?? t('contentExperience.memberContent')"
      image="/images/kingshot/v4/noticeboard.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/content" class="ks-command-link">
          ← {{ t('contentExperience.hubTitle') }}
        </Link>
        <Link
          v-if="canManageContent"
          href="/alliance/content/manage"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('contentExperience.manageContent') }}
        </Link>
      </template>
    </RoomBanner>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.5fr)_minmax(18rem,.5fr)]">
      <article class="ks-surface overflow-hidden">
        <header class="border-b border-[var(--ks-border)] p-5 sm:p-7">
          <div class="flex flex-wrap items-center gap-2">
            <span class="ks-chip">{{ content.typeLabel }}</span>
            <span v-if="content.category" class="ks-chip">{{ content.category.name }}</span>
            <span class="ks-chip">{{ content.locale }}</span>
            <span
              class="ks-status"
              :data-tone="content.visibility === 'public' ? 'success' : 'warning'"
            >
              {{
                content.visibility === 'public'
                  ? t('contentExperience.public')
                  : t('contentExperience.membersOnly')
              }}
            </span>
          </div>
          <h1 class="ks-display mt-5 text-3xl font-semibold leading-tight sm:text-4xl">
            {{ content.title }}
          </h1>
          <p v-if="content.summary" class="mt-3 text-base leading-7 text-[var(--ks-text-secondary)]">
            {{ content.summary }}
          </p>
        </header>

        <div class="p-5 sm:p-7">
          <div class="prose prose-invert max-w-none whitespace-pre-wrap leading-8 text-[var(--ks-text-secondary)]">
            {{ content.body }}
          </div>
        </div>
      </article>

      <aside class="space-y-4">
        <section class="ks-surface p-5">
          <p class="ks-kicker">{{ t('contentExperience.publishedContent') }}</p>
          <dl class="mt-4 space-y-4 text-sm">
            <div>
              <dt class="text-xs text-[var(--ks-muted)]">{{ t('contentExperience.type') }}</dt>
              <dd class="mt-1 font-semibold">{{ content.typeLabel }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-muted)]">{{ t('contentExperience.locale') }}</dt>
              <dd class="mt-1 font-semibold">{{ content.locale }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-muted)]">{{ t('contentExperience.published') }}</dt>
              <dd class="mt-1 font-semibold">{{ published(content.publishedAt) }}</dd>
            </div>
          </dl>
        </section>

        <a
          v-if="content.visibility === 'public'"
          :href="`/alliances/${alliance.slug}/content/${content.slug}`"
          target="_blank"
          rel="noopener noreferrer"
          class="ks-command-link w-full"
        >
          {{ t('contentExperience.publicPage') }}
        </a>
      </aside>
    </div>
  </AppLayout>
</template>
