<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import PublicLayout from '@/layouts/PublicLayout.vue';
import { useLocale } from '@/localization';
import { locales } from '@/localization/locales';

const props = defineProps<{
  alliance: { name: string; slug: string; timezone: string };
  content: {
    type: string;
    typeLabel: string;
    title: string;
    summary: string | null;
    body: string;
    locale: string;
    publishedAt: string | null;
    provenance: {
      sourceLabel: string | null;
      sourceUrl: string | null;
      gameVersion: string | null;
      reviewedAt: string | null;
    } | null;
    category: { name: string; slug: string } | null;
  };
  viewerTimezone?: string | null;
}>();

const { t, formatDate } = useLocale();

const typeKeys: Record<string, string> = {
  announcement: 'publicAlliance.announcement',
  guide: 'publicAlliance.guide',
  rule: 'publicAlliance.rule',
  event_instruction: 'publicAlliance.eventInstruction',
  reference_page: 'publicAlliance.referencePage',
};

function formatPublished(value: string | null): string {
  return value ? formatDate(value, { dateStyle: 'long', timeStyle: 'short' }) : '';
}

function formatReviewed(value: string | null): string {
  return value ? formatDate(value, { dateStyle: 'long' }) : '—';
}

function contentTypeLabel(): string {
  const key = typeKeys[props.content.type];
  return key ? t(key) : props.content.typeLabel;
}

function localeName(code: string): string {
  return locales.find((item) => item.code === code)?.nativeName ?? code;
}
</script>

<template>
  <Head :title="`${content.title} · ${alliance.name}`" />

  <PublicLayout>
    <section class="relative isolate overflow-hidden border-b border-[var(--ks-border)]">
      <img
        class="absolute inset-0 -z-20 h-full w-full object-cover opacity-25"
        src="/images/kingshot/v4/noticeboard.svg"
        alt=""
        aria-hidden="true"
      />
      <div
        class="absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(4,8,9,.96),rgba(7,17,17,.82))]"
      />
      <div class="mx-auto max-w-5xl px-5 py-8 lg:px-8">
        <Link
          class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--ks-gold)] transition hover:text-[var(--ks-gold-strong)]"
          :href="`/alliances/${alliance.slug}`"
        >
          <span aria-hidden="true">←</span>
          {{ t('publicContent.backToAlliance') }} · {{ alliance.name }}
        </Link>
      </div>
    </section>

    <article class="mx-auto max-w-5xl px-5 py-10 sm:py-14 lg:px-8 lg:py-16">
      <div class="ks-surface-gold overflow-hidden">
        <header class="border-b border-[var(--ks-border)] px-6 py-8 sm:px-10 sm:py-10 lg:px-12">
          <div
            class="flex flex-wrap items-center gap-2 text-xs font-bold tracking-wide text-[var(--ks-text-muted)] uppercase"
          >
            <span class="text-[var(--ks-gold)]">{{ contentTypeLabel() }}</span>
            <span v-if="content.category">· {{ content.category.name }}</span>
            <span>· {{ localeName(content.locale) }}</span>
          </div>

          <h1
            class="ks-display mt-4 max-w-4xl text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl"
          >
            {{ content.title }}
          </h1>
          <p
            v-if="content.summary"
            class="mt-5 max-w-3xl text-lg leading-8 text-[var(--ks-text-secondary)]"
          >
            {{ content.summary }}
          </p>
          <div
            v-if="content.publishedAt"
            class="mt-6 flex flex-wrap gap-x-4 gap-y-2 text-sm text-[var(--ks-text-muted)]"
          >
            <span
              >{{ t('publicAlliance.published') }} {{ formatPublished(content.publishedAt) }}</span
            >
            <span>· {{ t('publicContent.allianceTimezone') }}: {{ alliance.timezone }}</span>
          </div>
          <dl
            v-if="content.provenance"
            class="mt-6 grid gap-3 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 text-sm sm:grid-cols-3"
          >
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">
                {{ t('contentExperience.sourceLabel') }}
              </dt>
              <dd class="mt-1 font-semibold">
                <a
                  v-if="content.provenance.sourceUrl"
                  :href="content.provenance.sourceUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-[var(--ks-gold)] hover:underline"
                >
                  {{ content.provenance.sourceLabel }}
                </a>
                <span v-else>{{ content.provenance.sourceLabel }}</span>
              </dd>
            </div>
            <div v-if="content.provenance.gameVersion">
              <dt class="text-xs text-[var(--ks-text-muted)]">
                {{ t('contentExperience.gameVersion') }}
              </dt>
              <dd class="mt-1 font-semibold">{{ content.provenance.gameVersion }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">
                {{ t('contentExperience.reviewedAt') }}
              </dt>
              <dd class="mt-1 font-semibold">
                {{ formatReviewed(content.provenance.reviewedAt) }}
              </dd>
            </div>
          </dl>
        </header>

        <div class="px-6 py-8 sm:px-10 sm:py-10 lg:px-12 lg:py-12">
          <div
            class="max-w-none text-base leading-8 whitespace-pre-wrap text-[var(--ks-text-secondary)] sm:text-[1.05rem]"
          >
            {{ content.body }}
          </div>
        </div>
      </div>
    </article>
  </PublicLayout>
</template>
