<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Option = { value: string; label: string };
type Category = { id: string; name: string; slug: string; sortOrder: number };
type Revision = { id: string; revisionNumber: number; title: string; createdAt: string | null };
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
  sortOrder: number;
  revisionNumber: number;
  notifyMembers: boolean;
  scheduledFor: string | null;
  publishedAt: string | null;
  broadcastedAt: string | null;
  archivedAt: string | null;
  updatedAt: string | null;
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
type ContentDraft = {
  type: string;
  category_id: string;
  visibility: string;
  title: string;
  slug: string;
  summary: string;
  body: string;
  locale: string;
  sort_order: number;
  notify_members: boolean;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: {
    id: string;
    name: string;
    slug: string;
    kingdom: number | null;
    language: string;
    timezone: string;
    description: string | null;
    primaryColor: string | null;
    logoMediaId: string | null;
    bannerMediaId: string | null;
    publicUrl: string;
  };
  contentTypes: Option[];
  visibilityOptions: Option[];
  categories: Category[];
  content: ContentRow[];
  media: Media[];
}>();

const { t, formatDate } = useLocale();
const editingId = ref<string | null>(null);
const scheduleInputs = reactive<Record<string, string>>({});
const categoryEdits = reactive<Record<string, { name: string; slug: string; sort_order: number }>>(
  Object.fromEntries(
    props.categories.map((category) => [
      category.id,
      { name: category.name, slug: category.slug, sort_order: category.sortOrder },
    ]),
  ),
);
const publishedCount = computed(
  () => props.content.filter((item) => item.status === 'published').length,
);
const scheduledCount = computed(
  () => props.content.filter((item) => item.status === 'scheduled').length,
);
const pendingBroadcastCount = computed(
  () =>
    props.content.filter(
      (item) =>
        item.type === 'announcement' &&
        item.notifyMembers &&
        item.status === 'published' &&
        item.broadcastedAt === null,
    ).length,
);

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
const mediaForm = useForm<{ media: File | null }>({ media: null });
const contentForm = useForm<ContentDraft>({
  type: 'announcement',
  category_id: '',
  visibility: 'members',
  title: '',
  slug: '',
  summary: '',
  body: '',
  locale: props.alliance.language || 'en',
  sort_order: 0,
  notify_members: false,
});

function slugify(value: string): string {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}
function fillContentSlug(): void {
  if (!contentForm.slug) contentForm.slug = slugify(contentForm.title);
}
function fillCategorySlug(): void {
  if (!categoryForm.slug) categoryForm.slug = slugify(categoryForm.name);
}
function resetContentForm(): void {
  editingId.value = null;
  contentForm.reset();
  contentForm.clearErrors();
}
function editContent(item: ContentRow): void {
  editingId.value = item.id;
  contentForm.type = item.type;
  contentForm.category_id = item.category?.id ?? '';
  contentForm.visibility = item.visibility;
  contentForm.title = item.title;
  contentForm.slug = item.slug;
  contentForm.summary = item.summary ?? '';
  contentForm.body = item.body;
  contentForm.locale = item.locale;
  contentForm.sort_order = item.sortOrder;
  contentForm.notify_members = item.notifyMembers;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function saveContent(): void {
  const options = { preserveScroll: true, onSuccess: resetContentForm };
  if (editingId.value) {
    contentForm.patch('/alliance/content/' + editingId.value, options);
    return;
  }
  contentForm.post('/alliance/content', options);
}
function publishNow(id: string): void {
  router.post(
    '/alliance/content/' + id + '/publish',
    { scheduled_for: null },
    { preserveScroll: true },
  );
}
function schedule(id: string): void {
  const value = scheduleInputs[id];
  if (!value) return;
  router.post(
    '/alliance/content/' + id + '/publish',
    { scheduled_for: new Date(value).toISOString() },
    { preserveScroll: true },
  );
}
function archiveContent(id: string): void {
  router.delete('/alliance/content/' + id, { preserveScroll: true });
}
function restoreRevision(itemId: string, revisionId: string): void {
  router.post(
    '/alliance/content/' + itemId + '/revisions/' + revisionId + '/restore',
    {},
    { preserveScroll: true },
  );
}
function saveProfile(): void {
  profileForm.patch('/alliance/public-profile', { preserveScroll: true });
}
function createCategory(): void {
  categoryForm.post('/alliance/content/categories', {
    preserveScroll: true,
    onSuccess: () => categoryForm.reset(),
  });
}
function updateCategory(id: string): void {
  router.patch('/alliance/content/categories/' + id, categoryEdits[id], {
    preserveScroll: true,
  });
}
function deleteCategory(id: string): void {
  router.delete('/alliance/content/categories/' + id, { preserveScroll: true });
}
function uploadMedia(): void {
  mediaForm.post('/alliance/media', {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => mediaForm.reset(),
  });
}
function archiveMedia(id: string): void {
  router.delete('/alliance/media/' + id, { preserveScroll: true });
}
function statusTone(value: string): 'success' | 'warning' | 'info' {
  if (value === 'published') return 'success';
  if (value === 'draft') return 'warning';
  return 'info';
}
function timestamp(value: string | null): string {
  return value ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}
function bytes(value: number): string {
  if (value < 1024) return value + ' B';
  if (value < 1024 * 1024) return Math.round(value / 1024) + ' KB';
  return (value / 1024 / 1024).toFixed(1) + ' MB';
}
</script>

<template>
  <Head :title="t('contentExperience.managementTitle') + ' · ' + props.alliance.name" />

  <AppLayout
    :user="props.user"
    :player-alliance-name="props.alliance.name"
    :has-player-alliance="true"
  >
    <RoomBanner
      :eyebrow="t('contentExperience.managementEyebrow')"
      :title="t('contentExperience.broadcastDesk')"
      :subtitle="t('contentExperience.broadcastSubtitle')"
      image="/images/kingshot/v4/noticeboard.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/content" class="ks-command-link">
          ← {{ t('contentExperience.hubTitle') }}
        </Link>
        <Link href="/notifications" class="ks-command-link" data-variant="secondary">
          {{ t('contentExperience.deliverySettings') }}
        </Link>
        <a
          :href="props.alliance.publicUrl"
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
      <StatSeal
        :label="t('contentExperience.contentItems')"
        :value="props.content.length"
        icon="▤"
      />
      <StatSeal
        :label="t('contentExperience.publishedItems')"
        :value="publishedCount"
        icon="✦"
        tone="teal"
      />
      <StatSeal :label="t('contentExperience.scheduled')" :value="scheduledCount" icon="◷" />
      <StatSeal
        :label="t('contentExperience.queuedBroadcasts')"
        :value="pendingBroadcastCount"
        icon="↗"
        tone="stone"
      />
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(19rem,.58fr)_minmax(0,1.42fr)]">
      <aside class="space-y-5">
        <section class="ks-surface p-5" aria-labelledby="profile-heading">
          <p class="ks-kicker">{{ t('contentExperience.publicProfile') }}</p>
          <h2 id="profile-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ props.alliance.name }}
          </h2>
          <form class="mt-4 space-y-3" @submit.prevent="saveProfile">
            <label class="block text-sm">
              <span>{{ t('contentExperience.allianceName') }}</span>
              <input v-model="profileForm.name" class="ks-input mt-1.5" required maxlength="120" />
            </label>
            <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-1">
              <label class="block text-sm">
                <span>{{ t('contentExperience.language') }}</span>
                <input
                  v-model="profileForm.language"
                  class="ks-input mt-1.5"
                  required
                  maxlength="16"
                />
              </label>
              <label class="block text-sm">
                <span>{{ t('contentExperience.timezone') }}</span>
                <input v-model="profileForm.timezone" class="ks-input mt-1.5" required />
              </label>
            </div>
            <label class="block text-sm">
              <span>{{ t('contentExperience.description') }}</span>
              <textarea
                v-model="profileForm.description"
                class="ks-input mt-1.5 min-h-24"
                maxlength="5000"
              />
            </label>
            <label class="block text-sm">
              <span>{{ t('contentExperience.brandAccent') }}</span>
              <input
                v-model="profileForm.primary_color"
                class="ks-input mt-1.5"
                placeholder="#0f766e"
              />
            </label>
            <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-1">
              <label class="block text-sm">
                <span>{{ t('contentExperience.logoImage') }}</span>
                <select v-model="profileForm.logo_media_id" class="ks-input mt-1.5">
                  <option value="">{{ t('contentExperience.noLogo') }}</option>
                  <option v-for="asset in props.media" :key="'logo-' + asset.id" :value="asset.id">
                    {{ asset.name }}
                  </option>
                </select>
              </label>
              <label class="block text-sm">
                <span>{{ t('contentExperience.bannerImage') }}</span>
                <select v-model="profileForm.banner_media_id" class="ks-input mt-1.5">
                  <option value="">{{ t('contentExperience.noBanner') }}</option>
                  <option
                    v-for="asset in props.media"
                    :key="'banner-' + asset.id"
                    :value="asset.id"
                  >
                    {{ asset.name }}
                  </option>
                </select>
              </label>
            </div>
            <AppButton
              class="w-full"
              type="submit"
              variant="ghost"
              :disabled="profileForm.processing"
            >
              {{ t('contentExperience.saveProfile') }}
            </AppButton>
            <p v-if="Object.keys(profileForm.errors).length" class="text-xs text-rose-300">
              {{ Object.values(profileForm.errors)[0] }}
            </p>
          </form>
        </section>

        <section class="ks-surface p-5" aria-labelledby="category-heading">
          <p class="ks-kicker">{{ t('contentExperience.categoryManagement') }}</p>
          <h2 id="category-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ t('contentExperience.categories') }}
          </h2>
          <form class="mt-4 space-y-3" @submit.prevent="createCategory">
            <input
              v-model="categoryForm.name"
              class="ks-input"
              :placeholder="t('contentExperience.name')"
              required
              maxlength="120"
              @blur="fillCategorySlug"
            />
            <input
              v-model="categoryForm.slug"
              class="ks-input"
              :placeholder="t('contentExperience.slug')"
              required
              maxlength="120"
              pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
            />
            <AppButton
              class="w-full"
              type="submit"
              variant="ghost"
              :disabled="categoryForm.processing"
            >
              {{ t('contentExperience.createCategory') }}
            </AppButton>
          </form>
          <div v-if="props.categories.length" class="mt-4 space-y-3">
            <form
              v-for="category in props.categories"
              :key="category.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-3"
              @submit.prevent="updateCategory(category.id)"
            >
              <input v-model="categoryEdits[category.id]!.name" class="ks-input" required />
              <input v-model="categoryEdits[category.id]!.slug" class="ks-input mt-2" required />
              <div class="mt-2 flex gap-2">
                <AppButton type="submit" variant="ghost">{{
                  t('contentExperience.saveCategory')
                }}</AppButton>
                <button type="button" class="ks-chip" @click="deleteCategory(category.id)">
                  {{ t('contentExperience.deleteCategory') }}
                </button>
              </div>
            </form>
          </div>
        </section>

        <section class="ks-surface p-5" aria-labelledby="media-heading">
          <p class="ks-kicker">{{ t('contentExperience.mediaLibrary') }}</p>
          <h2 id="media-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ t('contentExperience.activeMedia') }}
          </h2>
          <p class="mt-2 text-sm text-[var(--ks-muted)]">{{ t('contentExperience.mediaHelp') }}</p>
          <form class="mt-4" @submit.prevent="uploadMedia">
            <input
              class="block w-full text-xs text-[var(--ks-muted)]"
              type="file"
              accept="image/png,image/jpeg,image/webp"
              @change="mediaForm.media = ($event.target as HTMLInputElement).files?.[0] ?? null"
            />
            <AppButton
              class="mt-3 w-full"
              type="submit"
              variant="ghost"
              :disabled="!mediaForm.media || mediaForm.processing"
            >
              {{ t('contentExperience.uploadMedia') }}
            </AppButton>
          </form>
          <div v-if="props.media.length" class="mt-4 space-y-2">
            <div
              v-for="asset in props.media"
              :key="asset.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-3"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <strong class="block truncate text-sm">{{ asset.name }}</strong>
                  <p class="mt-1 text-xs text-[var(--ks-muted)]">
                    {{ bytes(asset.sizeBytes) }} · {{ asset.scanStatus }} ·
                    {{ asset.lifecycleStatus }}
                  </p>
                </div>
                <button type="button" class="ks-chip" @click="archiveMedia(asset.id)">
                  {{ t('contentExperience.archiveMedia') }}
                </button>
              </div>
            </div>
          </div>
          <div v-else class="ks-fantasy-empty mt-4">{{ t('contentExperience.noMedia') }}</div>
        </section>
      </aside>

      <div class="min-w-0 space-y-5">
        <section class="ks-surface p-5 sm:p-6" aria-labelledby="editor-heading">
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('contentExperience.broadcastDesk') }}</p>
              <h2 id="editor-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{
                  editingId
                    ? t('contentExperience.editContent')
                    : t('contentExperience.createContent')
                }}
              </h2>
            </div>
            <button v-if="editingId" type="button" class="ks-chip" @click="resetContentForm">
              {{ t('contentExperience.cancelEdit') }}
            </button>
          </div>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
            {{
              editingId
                ? t('contentExperience.revisedDraftHelp')
                : t('contentExperience.broadcastHelp')
            }}
          </p>

          <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="saveContent">
            <label class="block text-sm">
              <span>{{ t('contentExperience.type') }}</span>
              <select v-model="contentForm.type" class="ks-input mt-1.5">
                <option
                  v-for="option in props.contentTypes"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
            </label>
            <label class="block text-sm">
              <span>{{ t('contentExperience.category') }}</span>
              <select v-model="contentForm.category_id" class="ks-input mt-1.5">
                <option value="">{{ t('contentExperience.noCategory') }}</option>
                <option
                  v-for="category in props.categories"
                  :key="category.id"
                  :value="category.id"
                >
                  {{ category.name }}
                </option>
              </select>
            </label>
            <label class="block text-sm md:col-span-2">
              <span>{{ t('contentExperience.title') }}</span>
              <input
                v-model="contentForm.title"
                class="ks-input mt-1.5"
                required
                maxlength="180"
                @blur="fillContentSlug"
              />
            </label>
            <label class="block text-sm">
              <span>{{ t('contentExperience.slug') }}</span>
              <input
                v-model="contentForm.slug"
                class="ks-input mt-1.5"
                required
                maxlength="180"
                pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
              />
            </label>
            <label class="block text-sm">
              <span>{{ t('contentExperience.locale') }}</span>
              <input v-model="contentForm.locale" class="ks-input mt-1.5" required maxlength="16" />
            </label>
            <label class="block text-sm">
              <span>{{ t('contentExperience.visibility') }}</span>
              <select v-model="contentForm.visibility" class="ks-input mt-1.5">
                <option
                  v-for="option in props.visibilityOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
            </label>
            <label class="block text-sm">
              <span>{{ t('contentExperience.sortOrder') }}</span>
              <input
                v-model.number="contentForm.sort_order"
                class="ks-input mt-1.5"
                type="number"
                min="0"
                max="100000"
              />
            </label>
            <label class="block text-sm md:col-span-2">
              <span>{{ t('contentExperience.summary') }}</span>
              <textarea
                v-model="contentForm.summary"
                class="ks-input mt-1.5 min-h-20"
                maxlength="500"
              />
            </label>
            <label class="block text-sm md:col-span-2">
              <span>{{ t('contentExperience.body') }}</span>
              <textarea
                v-model="contentForm.body"
                class="ks-input mt-1.5 min-h-48"
                required
                maxlength="50000"
              />
            </label>
            <label
              v-if="contentForm.type === 'announcement'"
              class="flex items-start gap-3 rounded-[var(--ks-radius-md)] border border-cyan-300/20 bg-cyan-400/5 p-4 md:col-span-2"
            >
              <input v-model="contentForm.notify_members" class="mt-1" type="checkbox" />
              <span>
                <strong class="block text-sm">{{ t('contentExperience.notifyMembers') }}</strong>
                <span class="mt-1 block text-xs leading-5 text-[var(--ks-muted)]">
                  {{ t('contentExperience.notifyMembersHelp') }}
                </span>
              </span>
            </label>
            <AppButton
              class="md:col-span-2 md:w-fit"
              type="submit"
              :disabled="contentForm.processing"
            >
              {{
                editingId ? t('contentExperience.saveChanges') : t('contentExperience.saveDraft')
              }}
            </AppButton>
            <p
              v-if="Object.keys(contentForm.errors).length"
              class="text-xs text-rose-300 md:col-span-2"
            >
              {{ Object.values(contentForm.errors)[0] }}
            </p>
          </form>
        </section>

        <section aria-labelledby="inventory-heading">
          <div class="flex items-end justify-between gap-3 px-1">
            <div>
              <p class="ks-kicker">{{ t('contentExperience.contentInventory') }}</p>
              <h2 id="inventory-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{ props.content.length }}
              </h2>
            </div>
          </div>

          <div v-if="props.content.length" class="mt-4 space-y-4">
            <article
              v-for="item in props.content"
              :key="item.id"
              class="ks-surface overflow-hidden"
            >
              <div class="border-b border-[var(--ks-border)] p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="ks-chip">{{ item.typeLabel }}</span>
                      <span class="ks-status" :data-tone="statusTone(item.status)">{{
                        item.status
                      }}</span>
                      <span v-if="item.notifyMembers" class="ks-status" data-tone="info">
                        {{
                          item.broadcastedAt
                            ? t('contentExperience.broadcastComplete')
                            : t('contentExperience.notifyMembers')
                        }}
                      </span>
                    </div>
                    <h3 class="ks-display mt-3 text-xl font-semibold">{{ item.title }}</h3>
                    <p class="mt-1 text-xs text-[var(--ks-muted)]">
                      {{ item.category?.name ?? t('contentExperience.noCategory') }} ·
                      {{ t('contentExperience.revision') }} {{ item.revisionNumber }} ·
                      {{ timestamp(item.updatedAt) }}
                    </p>
                    <p v-if="item.scheduledFor" class="mt-2 text-xs text-amber-200">
                      {{ t('contentExperience.scheduledFor') }} {{ timestamp(item.scheduledFor) }}
                    </p>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <Link
                      :href="'/alliance/content/' + item.slug"
                      class="ks-command-link"
                      data-variant="secondary"
                    >
                      {{ t('contentExperience.view') }}
                    </Link>
                    <button type="button" class="ks-chip" @click="editContent(item)">
                      {{ t('contentExperience.editContent') }}
                    </button>
                    <button type="button" class="ks-chip" @click="archiveContent(item.id)">
                      {{ t('contentExperience.archive') }}
                    </button>
                  </div>
                </div>

                <div
                  v-if="item.status !== 'published'"
                  class="mt-4 grid gap-3 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-3 sm:grid-cols-[1fr_auto_auto]"
                >
                  <label class="block text-xs">
                    <span>{{ t('contentExperience.scheduleLocal') }}</span>
                    <input
                      v-model="scheduleInputs[item.id]"
                      class="ks-input mt-1.5"
                      type="datetime-local"
                    />
                  </label>
                  <AppButton
                    class="self-end"
                    type="button"
                    variant="secondary"
                    @click="schedule(item.id)"
                  >
                    {{ t('contentExperience.schedule') }}
                  </AppButton>
                  <AppButton class="self-end" type="button" @click="publishNow(item.id)">
                    {{ t('contentExperience.publishNow') }}
                  </AppButton>
                </div>
              </div>

              <details v-if="item.revisions.length" class="p-4 sm:p-5">
                <summary class="cursor-pointer text-sm font-semibold">
                  {{ t('contentExperience.revisions') }} · {{ item.revisions.length }}
                </summary>
                <div class="mt-3 space-y-2">
                  <div
                    v-for="revision in item.revisions"
                    :key="revision.id"
                    class="flex flex-wrap items-center justify-between gap-3 rounded border border-[var(--ks-border)] p-3"
                  >
                    <span class="text-sm">
                      #{{ revision.revisionNumber }} · {{ revision.title }} ·
                      {{ timestamp(revision.createdAt) }}
                    </span>
                    <button
                      type="button"
                      class="ks-chip"
                      @click="restoreRevision(item.id, revision.id)"
                    >
                      {{
                        t('contentExperience.restoreRevision', { number: revision.revisionNumber })
                      }}
                    </button>
                  </div>
                </div>
              </details>
            </article>
          </div>
          <div v-else class="ks-fantasy-empty mt-4">{{ t('contentExperience.noContent') }}</div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
