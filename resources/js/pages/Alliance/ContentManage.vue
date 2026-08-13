<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

type Category = { id: string; name: string; slug: string; sortOrder: number };
type Revision = { id: string; revisionNumber: number; title: string; createdAt: string | null };
type ContentItem = {
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
  sortOrder: number;
  revisionNumber: number;
  scheduledFor: string | null;
  publishedAt: string | null;
  archivedAt: string | null;
  category: { id: string; name: string; slug: string } | null;
  revisions: Revision[];
};
type Media = {
  id: string;
  name: string;
  mimeType: string;
  sizeBytes: number;
  scanStatus: string;
  lifecycleStatus: string;
  createdAt: string | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: {
    id: string;
    name: string;
    slug: string;
    language: string;
    timezone: string;
    description: string | null;
    primaryColor: string | null;
    logoMediaId: string | null;
    bannerMediaId: string | null;
    publicUrl: string;
  };
  contentTypes: Array<{ value: string; label: string }>;
  visibilityOptions: Array<{ value: string; label: string }>;
  categories: Category[];
  content: ContentItem[];
  media: Media[];
}>();

const { t, formatDate, formatNumber } = useLocale();

const profileForm = useForm({
  name: props.alliance.name,
  language: props.alliance.language,
  timezone: props.alliance.timezone,
  description: props.alliance.description ?? '',
  primary_color: props.alliance.primaryColor ?? '',
  logo_media_id: props.alliance.logoMediaId ?? '',
  banner_media_id: props.alliance.bannerMediaId ?? '',
});
const categoryForm = useForm({ name: '', slug: '', sort_order: 0 });
const categoryDrafts = reactive<Record<string, { name: string; slug: string; sort_order: number }>>(
  Object.fromEntries(
    props.categories.map((category) => [
      category.id,
      { name: category.name, slug: category.slug, sort_order: category.sortOrder },
    ]),
  ),
);
const editingId = ref<string | null>(null);
const contentForm = useForm({
  category_id: '',
  type: 'announcement',
  visibility: 'public',
  title: '',
  slug: '',
  summary: '',
  body: '',
  locale: props.alliance.language,
  sort_order: 0,
});
const scheduleValues = reactive<Record<string, string>>({});
const mediaForm = useForm<{ media: File | null }>({ media: null });

const brandingMedia = computed(() =>
  props.media.filter(
    (asset) =>
      asset.lifecycleStatus === 'active' &&
      asset.scanStatus === 'clean' &&
      asset.mimeType.startsWith('image/'),
  ),
);
const publishedCount = computed(
  () => props.content.filter((item) => item.status === 'published').length,
);
const draftCount = computed(() => props.content.filter((item) => item.status === 'draft').length);
const activeMediaCount = computed(
  () => props.media.filter((asset) => asset.lifecycleStatus === 'active').length,
);

function saveProfile(): void {
  profileForm.patch('/alliance/public-profile', { preserveScroll: true });
}

function createCategory(): void {
  categoryForm.post('/alliance/content/categories', {
    preserveScroll: true,
    onSuccess: () => categoryForm.reset(),
  });
}

function updateCategory(category: Category): void {
  router.patch(`/alliance/content/categories/${category.id}`, categoryDrafts[category.id], {
    preserveScroll: true,
  });
}

function deleteCategory(category: Category): void {
  if (!window.confirm(`Delete category “${category.name}”?`)) return;
  router.delete(`/alliance/content/categories/${category.id}`, { preserveScroll: true });
}

function resetContentForm(): void {
  editingId.value = null;
  contentForm.reset();
  contentForm.category_id = '';
  contentForm.type = 'announcement';
  contentForm.visibility = 'public';
  contentForm.locale = props.alliance.language;
  contentForm.sort_order = 0;
  contentForm.clearErrors();
}

function editContent(item: ContentItem): void {
  editingId.value = item.id;
  contentForm.category_id = item.category?.id ?? '';
  contentForm.type = item.type;
  contentForm.visibility = item.visibility;
  contentForm.title = item.title;
  contentForm.slug = item.slug;
  contentForm.summary = item.summary ?? '';
  contentForm.body = item.body;
  contentForm.locale = item.locale;
  contentForm.sort_order = item.sortOrder;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function saveContent(): void {
  const options = { preserveScroll: true, onSuccess: () => resetContentForm() };
  if (editingId.value) {
    contentForm.patch(`/alliance/content/${editingId.value}`, options);
  } else {
    contentForm.post('/alliance/content', options);
  }
}

function publishNow(id: string): void {
  router.post(`/alliance/content/${id}/publish`, {}, { preserveScroll: true });
}

function schedule(id: string): void {
  const value = scheduleValues[id];
  if (!value) return;
  router.post(
    `/alliance/content/${id}/publish`,
    { scheduled_for: new Date(value).toISOString() },
    { preserveScroll: true },
  );
}

function archiveContent(id: string): void {
  if (!window.confirm('Archive this content item? It will disappear from public and member views.'))
    return;
  router.delete(`/alliance/content/${id}`, { preserveScroll: true });
}

function restoreRevision(itemId: string, revision: Revision): void {
  if (!window.confirm(`Restore revision ${revision.revisionNumber} as a new draft?`)) return;
  router.post(
    `/alliance/content/${itemId}/revisions/${revision.id}/restore`,
    {},
    { preserveScroll: true },
  );
}

function chooseMedia(event: Event): void {
  const input = event.target as HTMLInputElement;
  mediaForm.media = input.files?.[0] ?? null;
}

function uploadMedia(): void {
  mediaForm.post('/alliance/media', {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => mediaForm.reset(),
  });
}

function archiveMedia(asset: Media): void {
  if (!window.confirm(`Archive “${asset.name}”?`)) return;
  router.delete(`/alliance/media/${asset.id}`, { preserveScroll: true });
}

function bytes(value: number): string {
  if (value < 1024) return `${value} B`;
  if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
  return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function date(value: string | null): string {
  return value ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

function statusTone(status: string): string {
  if (status === 'published' || status === 'clean' || status === 'active')
    return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (status === 'scheduled' || status === 'pending')
    return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
  if (status === 'archived' || status === 'infected' || status === 'failed')
    return 'border-red-400/25 bg-red-500/10 text-red-200';
  return 'border-blue-400/25 bg-blue-500/10 text-blue-200';
}
</script>

<template>
  <Head :title="`${t('contentExperience.manageContent')} · ${alliance.name}`" />

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div class="max-w-3xl">
        <Link
          class="inline-flex min-h-10 items-center text-sm font-semibold text-[var(--ks-blue-strong)] hover:text-white"
          href="/alliance/content"
        >
          ← {{ t('contentExperience.backToHub') }}
        </Link>
        <p class="mt-4 text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('contentExperience.managementEyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('contentExperience.managementTitle') }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('contentExperience.managementSubtitle') }}
        </p>
      </div>
      <a
        class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-gold)]/45 bg-[var(--ks-gold-soft)] px-4 py-2 text-sm font-semibold text-[var(--ks-gold-strong)]"
        :href="alliance.publicUrl"
        target="_blank"
        rel="noopener noreferrer"
      >
        {{ t('contentExperience.viewPublicPage') }}
      </a>
    </header>

    <section class="ks-surface-gold mt-6 overflow-hidden">
      <dl
        class="grid grid-cols-2 divide-x divide-y divide-[var(--ks-border)] md:grid-cols-4 md:divide-y-0"
      >
        <div class="p-4 sm:p-5">
          <dt
            class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('contentExperience.contentItems') }}
          </dt>
          <dd class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(content.length) }}</dd>
        </div>
        <div class="p-4 sm:p-5">
          <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-green-300 uppercase">
            {{ t('contentExperience.publishedItems') }}
          </dt>
          <dd class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(publishedCount) }}</dd>
        </div>
        <div class="p-4 sm:p-5">
          <dt class="text-[0.68rem] font-bold tracking-[0.1em] text-blue-300 uppercase">
            {{ t('contentExperience.draftItems') }}
          </dt>
          <dd class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(draftCount) }}</dd>
        </div>
        <div class="p-4 sm:p-5">
          <dt
            class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('contentExperience.activeMedia') }}
          </dt>
          <dd class="ks-display mt-2 text-3xl font-semibold">
            {{ formatNumber(activeMediaCount) }}
          </dd>
        </div>
      </dl>
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-3">
      <div class="space-y-5 xl:col-span-2">
        <section class="ks-surface p-5 sm:p-6" aria-labelledby="author-heading">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="author-heading" class="ks-display text-xl font-semibold">
              {{
                editingId
                  ? t('contentExperience.editContent')
                  : t('contentExperience.createContent')
              }}
            </h2>
            <button
              v-if="editingId"
              class="text-sm font-semibold text-[var(--ks-blue-strong)]"
              type="button"
              @click="resetContentForm"
            >
              {{ t('contentExperience.cancelEdit') }}
            </button>
          </div>
          <p
            v-if="editingId"
            class="mt-3 rounded-[var(--ks-radius-sm)] border border-amber-400/25 bg-amber-500/10 p-3 text-sm text-amber-100"
          >
            {{ t('contentExperience.revisedDraftHelp') }}
          </p>
          <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="saveContent">
            <div class="sm:col-span-2">
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="content-title"
                >{{ t('contentExperience.title') }}</label
              ><input
                id="content-title"
                v-model="contentForm.title"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                maxlength="180"
                required
              />
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="content-slug"
                >{{ t('contentExperience.slug') }}</label
              ><input
                id="content-slug"
                v-model="contentForm.slug"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                required
              />
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="content-locale"
                >{{ t('contentExperience.locale') }}</label
              ><input
                id="content-locale"
                v-model="contentForm.locale"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                maxlength="16"
                required
              />
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="content-type"
                >{{ t('contentExperience.type') }}</label
              ><select
                id="content-type"
                v-model="contentForm.type"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              >
                <option v-for="option in contentTypes" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="content-visibility"
                >{{ t('contentExperience.visibility') }}</label
              ><select
                id="content-visibility"
                v-model="contentForm.visibility"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              >
                <option
                  v-for="option in visibilityOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="content-category"
                >{{ t('contentExperience.category') }}</label
              ><select
                id="content-category"
                v-model="contentForm.category_id"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              >
                <option value="">{{ t('contentExperience.noCategory') }}</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  {{ category.name }}
                </option>
              </select>
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="content-sort"
                >{{ t('contentExperience.sortOrder') }}</label
              ><input
                id="content-sort"
                v-model.number="contentForm.sort_order"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                type="number"
                min="0"
                max="100000"
              />
            </div>
            <div class="sm:col-span-2">
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="content-summary"
                >{{ t('contentExperience.summary') }}</label
              ><textarea
                id="content-summary"
                v-model="contentForm.summary"
                class="mt-1.5 min-h-20 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                maxlength="500"
              />
            </div>
            <div class="sm:col-span-2">
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="content-body"
                >{{ t('contentExperience.body') }}</label
              ><textarea
                id="content-body"
                v-model="contentForm.body"
                class="mt-1.5 min-h-72 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 font-mono text-sm"
                maxlength="50000"
                required
              />
            </div>
            <button
              class="min-h-10 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white sm:col-span-2"
              type="submit"
            >
              {{
                editingId ? t('contentExperience.saveChanges') : t('contentExperience.saveDraft')
              }}
            </button>
          </form>
        </section>

        <section class="ks-surface overflow-hidden" aria-labelledby="inventory-heading">
          <div class="border-b border-[var(--ks-border)] p-4 sm:p-5">
            <h2 id="inventory-heading" class="ks-display text-xl font-semibold">
              {{ t('contentExperience.contentInventory') }}
            </h2>
          </div>
          <div v-if="content.length" class="space-y-px bg-[var(--ks-border)]">
            <article
              v-for="item in content"
              :key="item.id"
              class="bg-[var(--ks-surface-1)] p-4 sm:p-5"
            >
              <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <h3 class="ks-display text-lg font-semibold">{{ item.title }}</h3>
                    <span
                      :class="statusTone(item.status)"
                      class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                      >{{ item.status }}</span
                    ><span
                      class="rounded-full border border-[var(--ks-border)] px-2.5 py-1 text-xs text-[var(--ks-text-muted)]"
                      >{{ item.visibility }}</span
                    >
                  </div>
                  <p class="mt-2 text-xs text-[var(--ks-text-muted)]">
                    {{ item.typeLabel }} · {{ item.locale }} · {{ t('contentExperience.revision') }}
                    {{ item.revisionNumber }}
                  </p>
                  <p v-if="item.summary" class="mt-2 text-sm text-[var(--ks-text-secondary)]">
                    {{ item.summary }}
                  </p>
                </div>
                <button
                  class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-1.5 text-xs font-semibold text-[var(--ks-blue-strong)]"
                  type="button"
                  @click="editContent(item)"
                >
                  {{ t('contentExperience.editContent') }}
                </button>
              </div>
              <div class="mt-4 flex flex-wrap items-end gap-2">
                <button
                  class="min-h-9 rounded-[var(--ks-radius-sm)] border border-green-400/25 bg-green-500/10 px-3 py-1.5 text-xs font-semibold text-green-200"
                  type="button"
                  @click="publishNow(item.id)"
                >
                  {{ t('contentExperience.publishNow') }}
                </button>
                <label class="sr-only" :for="`schedule-${item.id}`">{{
                  t('contentExperience.scheduledFor')
                }}</label>
                <input
                  :id="`schedule-${item.id}`"
                  v-model="scheduleValues[item.id]"
                  class="min-h-9 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-1.5 text-xs"
                  type="datetime-local"
                />
                <button
                  class="min-h-9 rounded-[var(--ks-radius-sm)] border border-amber-400/25 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-200"
                  type="button"
                  @click="schedule(item.id)"
                >
                  {{ t('contentExperience.schedule') }}
                </button>
                <button
                  class="min-h-9 rounded-[var(--ks-radius-sm)] border border-red-400/20 bg-red-500/5 px-3 py-1.5 text-xs font-semibold text-red-300"
                  type="button"
                  @click="archiveContent(item.id)"
                >
                  {{ t('contentExperience.archive') }}
                </button>
              </div>
              <div
                v-if="item.revisions.length"
                class="mt-4 border-t border-[var(--ks-border)] pt-3"
              >
                <p
                  class="text-xs font-bold tracking-[0.08em] text-[var(--ks-text-muted)] uppercase"
                >
                  {{ t('contentExperience.revisions') }}
                </p>
                <div class="mt-2 flex flex-wrap gap-2">
                  <button
                    v-for="revision in item.revisions"
                    :key="revision.id"
                    class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-2.5 py-1.5 text-xs text-[var(--ks-text-secondary)]"
                    type="button"
                    @click="restoreRevision(item.id, revision)"
                  >
                    {{
                      t('contentExperience.restoreRevision', { number: revision.revisionNumber })
                    }}
                  </button>
                </div>
              </div>
              <p v-if="item.scheduledFor" class="mt-3 text-xs text-amber-300">
                {{ t('contentExperience.scheduledFor') }}: {{ date(item.scheduledFor) }}
              </p>
              <p v-else-if="item.publishedAt" class="mt-3 text-xs text-[var(--ks-text-muted)]">
                {{ t('contentExperience.published', { date: date(item.publishedAt) }) }}
              </p>
            </article>
          </div>
          <p v-else class="p-8 text-center text-sm text-[var(--ks-text-muted)]">
            {{ t('contentExperience.noContent') }}
          </p>
        </section>
      </div>

      <aside class="space-y-5 xl:col-span-1">
        <section class="ks-surface p-5 xl:sticky xl:top-24" aria-labelledby="profile-heading">
          <h2 id="profile-heading" class="ks-display text-xl font-semibold">
            {{ t('contentExperience.publicProfile') }}
          </h2>
          <form class="mt-5 space-y-4" @submit.prevent="saveProfile">
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="profile-name"
                >{{ t('contentExperience.allianceName') }}</label
              ><input
                id="profile-name"
                v-model="profileForm.name"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                maxlength="120"
                required
              />
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
              <div>
                <label
                  class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                  for="profile-language"
                  >{{ t('contentExperience.language') }}</label
                ><input
                  id="profile-language"
                  v-model="profileForm.language"
                  class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                  maxlength="16"
                  required
                />
              </div>
              <div>
                <label
                  class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                  for="profile-timezone"
                  >{{ t('contentExperience.timezone') }}</label
                ><input
                  id="profile-timezone"
                  v-model="profileForm.timezone"
                  class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                  required
                />
              </div>
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="profile-color"
                >{{ t('contentExperience.brandAccent') }}</label
              ><input
                id="profile-color"
                v-model="profileForm.primary_color"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                pattern="#[0-9A-Fa-f]{6}"
              />
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="profile-logo"
                >{{ t('contentExperience.logoImage') }}</label
              ><select
                id="profile-logo"
                v-model="profileForm.logo_media_id"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              >
                <option value="">{{ t('contentExperience.noLogo') }}</option>
                <option v-for="asset in brandingMedia" :key="asset.id" :value="asset.id">
                  {{ asset.name }}
                </option>
              </select>
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="profile-banner"
                >{{ t('contentExperience.bannerImage') }}</label
              ><select
                id="profile-banner"
                v-model="profileForm.banner_media_id"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              >
                <option value="">{{ t('contentExperience.noBanner') }}</option>
                <option v-for="asset in brandingMedia" :key="asset.id" :value="asset.id">
                  {{ asset.name }}
                </option>
              </select>
            </div>
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="profile-description"
                >{{ t('contentExperience.description') }}</label
              ><textarea
                id="profile-description"
                v-model="profileForm.description"
                class="mt-1.5 min-h-24 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                maxlength="5000"
              />
            </div>
            <button
              class="min-h-10 w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white"
              type="submit"
            >
              {{ t('contentExperience.saveProfile') }}
            </button>
            <p
              v-if="Object.keys(profileForm.errors).length"
              class="text-sm text-red-300"
              role="alert"
            >
              {{ t('contentExperience.profileError') }}
            </p>
          </form>
        </section>

        <section class="ks-surface p-5" aria-labelledby="categories-heading">
          <h2 id="categories-heading" class="ks-display text-xl font-semibold">
            {{ t('contentExperience.categoryManagement') }}
          </h2>
          <form class="mt-4 grid gap-3" @submit.prevent="createCategory">
            <label class="sr-only" for="category-name">{{ t('contentExperience.name') }}</label
            ><input
              id="category-name"
              v-model="categoryForm.name"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              maxlength="100"
              :placeholder="t('contentExperience.name')"
              required
            />
            <label class="sr-only" for="category-slug">{{ t('contentExperience.slug') }}</label
            ><input
              id="category-slug"
              v-model="categoryForm.slug"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
              :placeholder="t('contentExperience.slug')"
              required
            />
            <label class="sr-only" for="category-sort">{{ t('contentExperience.sortOrder') }}</label
            ><input
              id="category-sort"
              v-model.number="categoryForm.sort_order"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              type="number"
              min="0"
              max="100000"
            />
            <button
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-gold)]/45 bg-[var(--ks-gold-soft)] px-3 py-2 text-sm font-semibold text-[var(--ks-gold-strong)]"
              type="submit"
            >
              {{ t('contentExperience.createCategory') }}
            </button>
          </form>
          <div
            v-if="categories.length"
            class="mt-4 space-y-3 border-t border-[var(--ks-border)] pt-4"
          >
            <div
              v-for="category in categories"
              :key="category.id"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3"
            >
              <input
                v-model="categoryDrafts[category.id].name"
                class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-2.5 py-2 text-sm"
              /><input
                v-model="categoryDrafts[category.id].slug"
                class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-2.5 py-2 text-sm"
              /><input
                v-model.number="categoryDrafts[category.id].sort_order"
                class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-2.5 py-2 text-sm"
                type="number"
                min="0"
                max="100000"
              />
              <div class="mt-2 flex gap-2">
                <button
                  class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-2.5 py-1.5 text-xs font-semibold"
                  type="button"
                  @click="updateCategory(category)"
                >
                  {{ t('contentExperience.saveCategory') }}</button
                ><button
                  class="rounded-[var(--ks-radius-sm)] border border-red-400/20 px-2.5 py-1.5 text-xs font-semibold text-red-300"
                  type="button"
                  @click="deleteCategory(category)"
                >
                  {{ t('contentExperience.deleteCategory') }}
                </button>
              </div>
            </div>
          </div>
        </section>

        <section class="ks-surface p-5" aria-labelledby="media-heading">
          <h2 id="media-heading" class="ks-display text-xl font-semibold">
            {{ t('contentExperience.mediaLibrary') }}
          </h2>
          <p class="mt-2 text-xs leading-5 text-[var(--ks-text-muted)]">
            {{ t('contentExperience.mediaHelp') }}
          </p>
          <form class="mt-4" @submit.prevent="uploadMedia">
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="media-upload"
              >{{ t('contentExperience.mediaFile') }}</label
            ><input
              id="media-upload"
              accept="image/*"
              class="mt-1.5 block w-full text-sm"
              type="file"
              required
              @change="chooseMedia"
            /><button
              class="mt-3 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-3 py-2 text-sm font-semibold text-white"
              type="submit"
              :disabled="mediaForm.processing || !mediaForm.media"
            >
              {{ t('contentExperience.uploadMedia') }}
            </button>
          </form>
          <div v-if="media.length" class="mt-4 space-y-3 border-t border-[var(--ks-border)] pt-4">
            <article
              v-for="asset in media"
              :key="asset.id"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold">{{ asset.name }}</p>
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                    {{ asset.mimeType }} · {{ bytes(asset.sizeBytes) }}
                  </p>
                </div>
                <span
                  :class="statusTone(asset.lifecycleStatus)"
                  class="rounded-full border px-2 py-1 text-xs capitalize"
                  >{{ asset.lifecycleStatus }}</span
                >
              </div>
              <div class="mt-2 flex flex-wrap gap-2 text-xs">
                <span :class="statusTone(asset.scanStatus)" class="rounded-full border px-2 py-1"
                  >{{ t('contentExperience.scan') }}: {{ asset.scanStatus }}</span
                ><span class="text-[var(--ks-text-muted)]">{{ date(asset.createdAt) }}</span>
              </div>
              <button
                v-if="asset.lifecycleStatus === 'active'"
                class="mt-3 rounded-[var(--ks-radius-sm)] border border-red-400/20 px-2.5 py-1.5 text-xs font-semibold text-red-300"
                type="button"
                @click="archiveMedia(asset)"
              >
                {{ t('contentExperience.archiveMedia') }}
              </button>
            </article>
          </div>
          <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">
            {{ t('contentExperience.noMedia') }}
          </p>
        </section>
      </aside>
    </div>
  </AppLayout>
</template>
