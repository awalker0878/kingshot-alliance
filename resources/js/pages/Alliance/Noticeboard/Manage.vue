<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type ContentRow = {
  id: string;
  type: string;
  typeLabel: string;
  visibility: string;
  status: string;
  title: string;
  slug: string;
  summary: string | null;
  body: string;
  locale: string;
  categoryId: string | null;
  categoryName: string | null;
  publishedAt: string | null;
  updatedAt: string | null;
};

type ContentDraft = {
  content_type: string;
  category_id: string;
  title: string;
  slug: string;
  summary: string;
  body: string;
  visibility: string;
  locale: string;
  status: string;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { name: string; slug: string };
  permissions: { publish: boolean };
  branding: { logoPresent: boolean; bannerPresent: boolean };
  categories: Array<{ id: string; name: string; slug: string }>;
  content: ContentRow[];
}>();

const { t, formatDate } = useLocale();
const categoryForm = useForm({ name: '', slug: '' });
const createForm = useForm<ContentDraft>({
  content_type: 'notice',
  category_id: '',
  title: '',
  slug: '',
  summary: '',
  body: '',
  visibility: 'members',
  locale: 'en',
  status: 'draft',
});
const edits = reactive<Record<string, ContentDraft>>(
  Object.fromEntries(
    props.content.map((item) => [
      item.id,
      {
        content_type: item.type,
        category_id: item.categoryId ?? '',
        title: item.title,
        slug: item.slug,
        summary: item.summary ?? '',
        body: item.body,
        visibility: item.visibility,
        locale: item.locale,
        status: item.status,
      },
    ]),
  ),
);
const logoForm = useForm<{ image: File | null }>({ image: null });
const bannerForm = useForm<{ image: File | null }>({ image: null });
const logoInput = ref<HTMLInputElement | null>(null);
const bannerInput = ref<HTMLInputElement | null>(null);

function slugify(value: string): string {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}
function fillCategorySlug(): void {
  if (!categoryForm.slug) categoryForm.slug = slugify(categoryForm.name);
}
function fillContentSlug(form: ContentDraft): void {
  if (!form.slug) form.slug = slugify(form.title);
}
function createCategory(): void {
  categoryForm.post('/alliance/content/categories', {
    preserveScroll: true,
    onSuccess: () => categoryForm.reset(),
  });
}
function createContent(): void {
  createForm.post('/alliance/content', {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  });
}
function updateContent(id: string): void {
  router.patch(`/alliance/content/${id}`, edits[id], { preserveScroll: true });
}
function publish(id: string): void {
  router.post(`/alliance/content/${id}/publish`, {}, { preserveScroll: true });
}
function onFile(event: Event, target: 'logo' | 'banner'): void {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0] ?? null;
  if (target === 'logo') logoForm.image = file;
  else bannerForm.image = file;
}
function uploadBranding(target: 'logo' | 'banner'): void {
  const form = target === 'logo' ? logoForm : bannerForm;
  form.post(`/alliance/content/branding/${target}`, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      form.reset();
      const input = target === 'logo' ? logoInput.value : bannerInput.value;
      if (input) input.value = '';
    },
  });
}
function statusTone(value: string): 'success' | 'warning' | 'info' {
  if (value === 'published') return 'success';
  if (value === 'draft') return 'warning';
  return 'info';
}
function visibilityTone(value: string): 'success' | 'warning' | 'info' {
  if (value === 'public') return 'success';
  if (value === 'members') return 'warning';
  return 'info';
}
function timestamp(value: string | null): string {
  return value ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}
</script>

<template>
  <Head :title="`${t('contentExperience.manageTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('contentExperience.manageEyebrow')"
      :title="t('contentExperience.manageTitle')"
      :subtitle="t('contentExperience.manageSubtitle')"
      image="/images/kingshot/v4/noticeboard.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/content" class="ks-command-link">
          ← {{ t('contentExperience.hubTitle') }}
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

    <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <StatSeal :label="t('contentExperience.categories')" :value="categories.length" icon="◇" />
      <StatSeal
        :label="t('contentExperience.items')"
        :value="content.length"
        icon="▤"
        tone="teal"
      />
      <StatSeal
        :label="t('contentExperience.brandLogo')"
        :value="
          branding.logoPresent ? t('contentExperience.present') : t('contentExperience.notPresent')
        "
        icon="♜"
        tone="stone"
      />
      <StatSeal
        :label="t('contentExperience.brandBanner')"
        :value="
          branding.bannerPresent
            ? t('contentExperience.present')
            : t('contentExperience.notPresent')
        "
        icon="⚑"
      />
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(20rem,.58fr)_minmax(0,1.42fr)]">
      <aside class="space-y-5">
        <section class="ks-surface p-5" aria-labelledby="branding-heading">
          <p class="ks-kicker">{{ t('contentExperience.branding') }}</p>
          <h2 id="branding-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ t('contentExperience.brandingTitle') }}
          </h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('contentExperience.brandingHelp') }}
          </p>

          <div class="mt-5 space-y-4">
            <form
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
              @submit.prevent="uploadBranding('logo')"
            >
              <div class="flex items-center justify-between gap-3">
                <label class="font-semibold" for="branding-logo">{{
                  t('contentExperience.logo')
                }}</label>
                <span class="ks-status" :data-tone="branding.logoPresent ? 'success' : 'warning'">
                  {{
                    branding.logoPresent
                      ? t('contentExperience.present')
                      : t('contentExperience.notPresent')
                  }}
                </span>
              </div>
              <input
                id="branding-logo"
                ref="logoInput"
                class="mt-3 block w-full text-xs text-[var(--ks-muted)]"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                @change="onFile($event, 'logo')"
              />
              <AppButton
                class="mt-3 w-full"
                variant="ghost"
                type="submit"
                :disabled="logoForm.processing || !logoForm.image"
              >
                {{ t('contentExperience.uploadLogo') }}
              </AppButton>
            </form>

            <form
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
              @submit.prevent="uploadBranding('banner')"
            >
              <div class="flex items-center justify-between gap-3">
                <label class="font-semibold" for="branding-banner">{{
                  t('contentExperience.banner')
                }}</label>
                <span class="ks-status" :data-tone="branding.bannerPresent ? 'success' : 'warning'">
                  {{
                    branding.bannerPresent
                      ? t('contentExperience.present')
                      : t('contentExperience.notPresent')
                  }}
                </span>
              </div>
              <input
                id="branding-banner"
                ref="bannerInput"
                class="mt-3 block w-full text-xs text-[var(--ks-muted)]"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                @change="onFile($event, 'banner')"
              />
              <AppButton
                class="mt-3 w-full"
                variant="ghost"
                type="submit"
                :disabled="bannerForm.processing || !bannerForm.image"
              >
                {{ t('contentExperience.uploadBanner') }}
              </AppButton>
            </form>
          </div>
        </section>

        <section class="ks-surface p-5" aria-labelledby="categories-heading">
          <p class="ks-kicker">{{ t('contentExperience.categories') }}</p>
          <h2 id="categories-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ t('contentExperience.addCategory') }}
          </h2>
          <form class="mt-4 space-y-3" @submit.prevent="createCategory">
            <div>
              <label class="text-xs font-semibold" for="category-name">{{
                t('contentExperience.name')
              }}</label>
              <input
                id="category-name"
                v-model="categoryForm.name"
                class="ks-input mt-1.5"
                required
                maxlength="120"
                @blur="fillCategorySlug"
              />
            </div>
            <div>
              <label class="text-xs font-semibold" for="category-slug">{{
                t('contentExperience.slug')
              }}</label>
              <input
                id="category-slug"
                v-model="categoryForm.slug"
                class="ks-input mt-1.5"
                required
                maxlength="120"
                pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
              />
            </div>
            <AppButton
              class="w-full"
              type="submit"
              variant="ghost"
              :disabled="categoryForm.processing"
            >
              {{ t('contentExperience.createCategory') }}
            </AppButton>
          </form>
          <div v-if="categories.length" class="mt-4 flex flex-wrap gap-2">
            <span v-for="category in categories" :key="category.id" class="ks-chip">{{
              category.name
            }}</span>
          </div>
        </section>
      </aside>

      <div class="min-w-0 space-y-5">
        <section class="ks-surface p-5 sm:p-6" aria-labelledby="create-content-heading">
          <p class="ks-kicker">{{ t('contentExperience.createContent') }}</p>
          <h2 id="create-content-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('contentExperience.newContent') }}
          </h2>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('contentExperience.createHelp') }}
          </p>

          <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="createContent">
            <div>
              <label class="text-xs font-semibold" for="new-type">{{
                t('contentExperience.type')
              }}</label>
              <select id="new-type" v-model="createForm.content_type" class="ks-input mt-1.5">
                <option value="notice">{{ t('contentExperience.notice') }}</option>
                <option value="guide">{{ t('contentExperience.guide') }}</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-semibold" for="new-category">{{
                t('contentExperience.category')
              }}</label>
              <select id="new-category" v-model="createForm.category_id" class="ks-input mt-1.5">
                <option value="">{{ t('contentExperience.noCategory') }}</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  {{ category.name }}
                </option>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="text-xs font-semibold" for="new-title">{{
                t('contentExperience.title')
              }}</label>
              <input
                id="new-title"
                v-model="createForm.title"
                class="ks-input mt-1.5"
                required
                maxlength="200"
                @blur="fillContentSlug(createForm)"
              />
            </div>
            <div>
              <label class="text-xs font-semibold" for="new-slug">{{
                t('contentExperience.slug')
              }}</label>
              <input
                id="new-slug"
                v-model="createForm.slug"
                class="ks-input mt-1.5"
                required
                maxlength="160"
                pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
              />
            </div>
            <div>
              <label class="text-xs font-semibold" for="new-locale">{{
                t('contentExperience.locale')
              }}</label>
              <input
                id="new-locale"
                v-model="createForm.locale"
                class="ks-input mt-1.5"
                required
                maxlength="16"
              />
            </div>
            <div>
              <label class="text-xs font-semibold" for="new-visibility">{{
                t('contentExperience.visibility')
              }}</label>
              <select id="new-visibility" v-model="createForm.visibility" class="ks-input mt-1.5">
                <option value="members">{{ t('contentExperience.members') }}</option>
                <option value="public">{{ t('contentExperience.public') }}</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-semibold" for="new-status">{{
                t('contentExperience.status')
              }}</label>
              <select id="new-status" v-model="createForm.status" class="ks-input mt-1.5">
                <option value="draft">{{ t('contentExperience.draft') }}</option>
                <option value="published">{{ t('contentExperience.published') }}</option>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="text-xs font-semibold" for="new-summary">{{
                t('contentExperience.summary')
              }}</label>
              <textarea
                id="new-summary"
                v-model="createForm.summary"
                class="ks-input mt-1.5 min-h-20"
                maxlength="1000"
              />
            </div>
            <div class="md:col-span-2">
              <label class="text-xs font-semibold" for="new-body">{{
                t('contentExperience.body')
              }}</label>
              <textarea
                id="new-body"
                v-model="createForm.body"
                class="ks-input mt-1.5 min-h-48"
                required
                maxlength="50000"
              />
            </div>
            <AppButton class="md:col-span-2" type="submit" :disabled="createForm.processing">
              {{ t('contentExperience.createContent') }}
            </AppButton>
          </form>
        </section>

        <section aria-labelledby="existing-content-heading">
          <div class="flex items-end justify-between gap-3 px-1">
            <div>
              <p class="ks-kicker">{{ t('contentExperience.existingContent') }}</p>
              <h2 id="existing-content-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{ content.length }}
              </h2>
            </div>
          </div>

          <div v-if="content.length" class="mt-4 space-y-4">
            <article v-for="item in content" :key="item.id" class="ks-surface overflow-hidden">
              <div
                class="flex flex-wrap items-start justify-between gap-4 border-b border-[var(--ks-border)] p-4 sm:p-5"
              >
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="ks-chip">{{ item.typeLabel }}</span>
                    <span class="ks-status" :data-tone="statusTone(item.status)">{{
                      item.status
                    }}</span>
                    <span class="ks-status" :data-tone="visibilityTone(item.visibility)">{{
                      item.visibility
                    }}</span>
                  </div>
                  <h3 class="ks-display mt-3 text-xl font-semibold">{{ item.title }}</h3>
                  <p class="mt-1 text-xs text-[var(--ks-muted)]">
                    {{ item.updatedAt ? timestamp(item.updatedAt) : '—' }}
                  </p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <Link
                    :href="`/alliance/content/${item.slug}`"
                    class="ks-command-link"
                    data-variant="secondary"
                  >
                    {{ t('contentExperience.view') }}
                  </Link>
                  <AppButton
                    v-if="permissions.publish && item.status !== 'published'"
                    type="button"
                    variant="secondary"
                    @click="publish(item.id)"
                  >
                    {{ t('contentExperience.publish') }}
                  </AppButton>
                </div>
              </div>

              <form
                class="grid gap-4 p-4 sm:p-5 md:grid-cols-2"
                @submit.prevent="updateContent(item.id)"
              >
                <div>
                  <label class="text-xs font-semibold" :for="`type-${item.id}`">{{
                    t('contentExperience.type')
                  }}</label>
                  <select
                    :id="`type-${item.id}`"
                    v-model="edits[item.id]!.content_type"
                    class="ks-input mt-1.5"
                  >
                    <option value="notice">{{ t('contentExperience.notice') }}</option>
                    <option value="guide">{{ t('contentExperience.guide') }}</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold" :for="`category-${item.id}`">{{
                    t('contentExperience.category')
                  }}</label>
                  <select
                    :id="`category-${item.id}`"
                    v-model="edits[item.id]!.category_id"
                    class="ks-input mt-1.5"
                  >
                    <option value="">{{ t('contentExperience.noCategory') }}</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                      {{ category.name }}
                    </option>
                  </select>
                </div>
                <div class="md:col-span-2">
                  <label class="text-xs font-semibold" :for="`title-${item.id}`">{{
                    t('contentExperience.title')
                  }}</label>
                  <input
                    :id="`title-${item.id}`"
                    v-model="edits[item.id]!.title"
                    class="ks-input mt-1.5"
                    maxlength="200"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold" :for="`slug-${item.id}`">{{
                    t('contentExperience.slug')
                  }}</label>
                  <input
                    :id="`slug-${item.id}`"
                    v-model="edits[item.id]!.slug"
                    class="ks-input mt-1.5"
                    maxlength="160"
                    required
                    pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold" :for="`locale-${item.id}`">{{
                    t('contentExperience.locale')
                  }}</label>
                  <input
                    :id="`locale-${item.id}`"
                    v-model="edits[item.id]!.locale"
                    class="ks-input mt-1.5"
                    maxlength="16"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold" :for="`visibility-${item.id}`">{{
                    t('contentExperience.visibility')
                  }}</label>
                  <select
                    :id="`visibility-${item.id}`"
                    v-model="edits[item.id]!.visibility"
                    class="ks-input mt-1.5"
                  >
                    <option value="members">{{ t('contentExperience.members') }}</option>
                    <option value="public">{{ t('contentExperience.public') }}</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold" :for="`status-${item.id}`">{{
                    t('contentExperience.status')
                  }}</label>
                  <select
                    :id="`status-${item.id}`"
                    v-model="edits[item.id]!.status"
                    class="ks-input mt-1.5"
                  >
                    <option value="draft">{{ t('contentExperience.draft') }}</option>
                    <option value="published">{{ t('contentExperience.published') }}</option>
                  </select>
                </div>
                <div class="md:col-span-2">
                  <label class="text-xs font-semibold" :for="`summary-${item.id}`">{{
                    t('contentExperience.summary')
                  }}</label>
                  <textarea
                    :id="`summary-${item.id}`"
                    v-model="edits[item.id]!.summary"
                    class="ks-input mt-1.5 min-h-20"
                    maxlength="1000"
                  />
                </div>
                <div class="md:col-span-2">
                  <label class="text-xs font-semibold" :for="`body-${item.id}`">{{
                    t('contentExperience.body')
                  }}</label>
                  <textarea
                    :id="`body-${item.id}`"
                    v-model="edits[item.id]!.body"
                    class="ks-input mt-1.5 min-h-44"
                    required
                    maxlength="50000"
                  />
                </div>
                <AppButton class="md:col-span-2 md:w-fit" type="submit" variant="secondary">
                  {{ t('contentExperience.saveChanges') }}
                </AppButton>
              </form>
            </article>
          </div>
          <div v-else class="ks-fantasy-empty mt-4">{{ t('contentExperience.noContent') }}</div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
