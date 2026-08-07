<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

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
  alliance: {
    id: string;
    name: string;
    slug: string;
    kingdom: string | null;
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

const profileForm = useForm({
  name: props.alliance.name,
  kingdom: props.alliance.kingdom ?? '',
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
  const options = {
    preserveScroll: true,
    onSuccess: () => resetContentForm(),
  };

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

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
</script>

<template>
  <Head :title="`Manage ${alliance.name} content`" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 text-slate-100 lg:px-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <Link
          class="text-sm font-semibold text-cyan-300 hover:text-cyan-200"
          href="/alliance/content"
          >← Content hub</Link
        >
        <h1 class="mt-3 text-3xl font-bold">Manage public presence</h1>
        <p class="mt-2 text-slate-400">
          Profile, content revisions, publication, categories, and branding media. Recruitment
          availability is managed in the Recruitment workspace.
        </p>
      </div>
      <a
        class="rounded-lg border border-cyan-800 px-4 py-2 font-semibold text-cyan-300 hover:border-cyan-600"
        :href="alliance.publicUrl"
        target="_blank"
        rel="noopener noreferrer"
      >
        View public page
      </a>
    </div>

    <section
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/60 p-6"
      aria-labelledby="profile-heading"
    >
      <h2 id="profile-heading" class="text-xl font-semibold">Public alliance profile</h2>
      <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="saveProfile">
        <div>
          <label class="text-sm font-medium" for="profile-name">Alliance name</label>
          <input
            id="profile-name"
            v-model="profileForm.name"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            maxlength="120"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="profile-kingdom">Kingdom</label>
          <input
            id="profile-kingdom"
            v-model="profileForm.kingdom"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="64"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="profile-language">Language / locale</label>
          <input
            id="profile-language"
            v-model="profileForm.language"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            maxlength="16"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="profile-timezone">Alliance time zone</label>
          <input
            id="profile-timezone"
            v-model="profileForm.timezone"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            placeholder="America/Toronto"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="profile-color">Brand accent</label>
          <input
            id="profile-color"
            v-model="profileForm.primary_color"
            class="mt-1 h-10 w-full rounded-lg border border-slate-700 bg-slate-950 px-3"
            type="text"
            placeholder="#22D3EE"
            pattern="#[0-9A-Fa-f]{6}"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="profile-logo">Logo image</label>
          <select
            id="profile-logo"
            v-model="profileForm.logo_media_id"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="">No logo</option>
            <option v-for="asset in brandingMedia" :key="asset.id" :value="asset.id">
              {{ asset.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="profile-banner">Banner image</label>
          <select
            id="profile-banner"
            v-model="profileForm.banner_media_id"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="">No banner</option>
            <option v-for="asset in brandingMedia" :key="asset.id" :value="asset.id">
              {{ asset.name }}
            </option>
          </select>
        </div>
        <div class="sm:col-span-2">
          <label class="text-sm font-medium" for="profile-description">Description</label>
          <textarea
            id="profile-description"
            v-model="profileForm.description"
            class="mt-1 min-h-28 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="5000"
          />
        </div>
        <div class="sm:col-span-2">
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            :disabled="profileForm.processing"
            type="submit"
          >
            Save public profile
          </button>
          <p v-if="Object.keys(profileForm.errors).length" class="mt-2 text-sm text-rose-300">
            Please correct the highlighted profile values.
          </p>
        </div>
      </form>
    </section>

    <section
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/60 p-6"
      aria-labelledby="author-heading"
    >
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="author-heading" class="text-xl font-semibold">
          {{ editingId ? 'Edit content' : 'Create content' }}
        </h2>
        <button
          v-if="editingId"
          class="text-sm font-semibold text-cyan-300"
          type="button"
          @click="resetContentForm"
        >
          Cancel edit
        </button>
      </div>
      <p
        v-if="editingId"
        class="mt-2 rounded-lg border border-amber-800 bg-amber-950/30 p-3 text-sm text-amber-100"
      >
        Saving an existing published or archived item returns the revised copy to draft. Publish it
        explicitly when the revision is ready.
      </p>
      <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="saveContent">
        <div class="sm:col-span-2">
          <label class="text-sm font-medium" for="content-title">Title</label>
          <input
            id="content-title"
            v-model="contentForm.title"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            maxlength="180"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="content-slug">URL slug</label>
          <input
            id="content-slug"
            v-model="contentForm.slug"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="content-locale">Locale</label>
          <input
            id="content-locale"
            v-model="contentForm.locale"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            maxlength="16"
          />
        </div>
        <div>
          <label class="text-sm font-medium" for="content-type">Type</label>
          <select
            id="content-type"
            v-model="contentForm.type"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option v-for="option in contentTypes" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="content-visibility">Visibility</label>
          <select
            id="content-visibility"
            v-model="contentForm.visibility"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option v-for="option in visibilityOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="content-category">Category</label>
          <select
            id="content-category"
            v-model="contentForm.category_id"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          >
            <option value="">No category</option>
            <option v-for="category in categories" :key="category.id" :value="category.id">
              {{ category.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium" for="content-order">Sort order</label>
          <input
            id="content-order"
            v-model.number="contentForm.sort_order"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            min="0"
            type="number"
          />
        </div>
        <div class="sm:col-span-2">
          <label class="text-sm font-medium" for="content-summary">Summary</label>
          <textarea
            id="content-summary"
            v-model="contentForm.summary"
            class="mt-1 min-h-20 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="500"
          />
        </div>
        <div class="sm:col-span-2">
          <label class="text-sm font-medium" for="content-body">Content</label>
          <textarea
            id="content-body"
            v-model="contentForm.body"
            class="mt-1 min-h-64 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            maxlength="50000"
          />
          <p class="mt-1 text-xs text-slate-500">
            Stored as sanitized plain text. HTML is not interpreted.
          </p>
        </div>
        <div class="sm:col-span-2">
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            :disabled="contentForm.processing"
            type="submit"
          >
            {{ editingId ? 'Save new revision' : 'Create draft' }}
          </button>
          <p v-if="Object.keys(contentForm.errors).length" class="mt-2 text-sm text-rose-300">
            Please correct the content form values.
          </p>
        </div>
      </form>
    </section>

    <section
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/60 p-6"
      aria-labelledby="existing-heading"
    >
      <h2 id="existing-heading" class="text-xl font-semibold">Content library</h2>
      <div v-if="content.length" class="mt-5 space-y-5">
        <article
          v-for="item in content"
          :key="item.id"
          class="rounded-xl border border-slate-800 p-5"
        >
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div class="flex flex-wrap gap-2 text-xs font-semibold text-slate-400 uppercase">
                <span>{{ item.typeLabel }}</span
                ><span>· {{ item.status }}</span
                ><span>· {{ item.visibility }}</span
                ><span>· rev {{ item.revisionNumber }}</span>
              </div>
              <h3 class="mt-2 text-lg font-semibold">{{ item.title }}</h3>
              <p class="mt-1 text-sm text-slate-400">/{{ item.slug }}</p>
            </div>
            <div class="flex flex-wrap gap-3 text-sm font-semibold">
              <button
                class="text-cyan-300 hover:text-cyan-200"
                type="button"
                @click="editContent(item)"
              >
                Edit
              </button>
              <button
                class="text-emerald-300 hover:text-emerald-200"
                type="button"
                @click="publishNow(item.id)"
              >
                Publish now
              </button>
              <button
                class="text-rose-300 hover:text-rose-200"
                type="button"
                @click="archiveContent(item.id)"
              >
                Archive
              </button>
            </div>
          </div>

          <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="flex-1">
              <label class="text-sm font-medium" :for="`schedule-${item.id}`"
                >Schedule publication</label
              >
              <input
                :id="`schedule-${item.id}`"
                v-model="scheduleValues[item.id]"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                type="datetime-local"
              />
            </div>
            <button
              class="rounded-lg border border-slate-700 px-4 py-2 font-semibold"
              type="button"
              @click="schedule(item.id)"
            >
              Schedule
            </button>
          </div>

          <details v-if="item.revisions.length" class="mt-4 rounded-lg bg-slate-950/60 p-4">
            <summary class="cursor-pointer font-semibold">Revision history</summary>
            <ul class="mt-3 space-y-2 text-sm">
              <li
                v-for="revision in item.revisions"
                :key="revision.id"
                class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 pt-2 first:border-0 first:pt-0"
              >
                <span>Revision {{ revision.revisionNumber }} · {{ revision.title }}</span>
                <button
                  v-if="revision.revisionNumber !== item.revisionNumber"
                  class="font-semibold text-cyan-300"
                  type="button"
                  @click="restoreRevision(item.id, revision)"
                >
                  Restore as draft
                </button>
              </li>
            </ul>
          </details>
        </article>
      </div>
      <p v-else class="mt-4 text-slate-400">No content has been created yet.</p>
    </section>

    <section
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/60 p-6"
      aria-labelledby="category-heading"
    >
      <h2 id="category-heading" class="text-xl font-semibold">Categories</h2>
      <form
        class="mt-5 grid gap-3 sm:grid-cols-[1fr_1fr_8rem_auto]"
        @submit.prevent="createCategory"
      >
        <div>
          <label class="sr-only" for="new-category-name">Category name</label>
          <input
            id="new-category-name"
            v-model="categoryForm.name"
            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            placeholder="Category name"
            required
          />
        </div>
        <div>
          <label class="sr-only" for="new-category-slug">Category slug</label>
          <input
            id="new-category-slug"
            v-model="categoryForm.slug"
            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            placeholder="category-slug"
            required
            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
          />
        </div>
        <div>
          <label class="sr-only" for="new-category-order">Sort order</label>
          <input
            id="new-category-order"
            v-model.number="categoryForm.sort_order"
            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            min="0"
            type="number"
          />
        </div>
        <button class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950" type="submit">
          Add
        </button>
      </form>

      <div class="mt-5 space-y-3">
        <div
          v-for="category in categories"
          :key="category.id"
          class="grid gap-3 rounded-xl border border-slate-800 p-4 sm:grid-cols-[1fr_1fr_8rem_auto]"
        >
          <label class="sr-only" :for="`category-name-${category.id}`">Category name</label>
          <input
            :id="`category-name-${category.id}`"
            v-model="categoryDrafts[category.id].name"
            class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          />
          <label class="sr-only" :for="`category-slug-${category.id}`">Category slug</label>
          <input
            :id="`category-slug-${category.id}`"
            v-model="categoryDrafts[category.id].slug"
            class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          />
          <label class="sr-only" :for="`category-order-${category.id}`">Sort order</label>
          <input
            :id="`category-order-${category.id}`"
            v-model.number="categoryDrafts[category.id].sort_order"
            class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            min="0"
            type="number"
          />
          <div class="flex gap-3 sm:justify-end">
            <button
              class="font-semibold text-cyan-300"
              type="button"
              @click="updateCategory(category)"
            >
              Save
            </button>
            <button
              class="font-semibold text-rose-300"
              type="button"
              @click="deleteCategory(category)"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </section>

    <section
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/60 p-6"
      aria-labelledby="media-heading"
    >
      <h2 id="media-heading" class="text-xl font-semibold">Media library</h2>
      <p class="mt-2 text-sm text-slate-400">
        Images and PDFs are privately stored, screened, and tenant-prefixed. Clean images can be
        selected for public branding.
      </p>
      <form class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="uploadMedia">
        <div class="flex-1">
          <label class="text-sm font-medium" for="media-upload">Upload media</label>
          <input
            id="media-upload"
            class="mt-1 block w-full text-sm"
            accept="image/jpeg,image/png,image/webp,image/gif,application/pdf"
            type="file"
            required
            @change="chooseMedia"
          />
        </div>
        <button
          class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
          :disabled="mediaForm.processing || !mediaForm.media"
          type="submit"
        >
          Upload
        </button>
      </form>
      <p v-if="mediaForm.errors.media" class="mt-2 text-sm text-rose-300">
        {{ mediaForm.errors.media }}
      </p>

      <div v-if="media.length" class="mt-5 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="text-slate-400">
            <tr>
              <th class="pr-4 pb-3">Name</th>
              <th class="pr-4 pb-3">Type</th>
              <th class="pr-4 pb-3">Size</th>
              <th class="pr-4 pb-3">State</th>
              <th class="pb-3">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="asset in media" :key="asset.id">
              <td class="py-3 pr-4">{{ asset.name }}</td>
              <td class="py-3 pr-4 text-slate-400">{{ asset.mimeType }}</td>
              <td class="py-3 pr-4 text-slate-400">{{ formatBytes(asset.sizeBytes) }}</td>
              <td class="py-3 pr-4">{{ asset.scanStatus }} / {{ asset.lifecycleStatus }}</td>
              <td class="py-3">
                <button
                  v-if="asset.lifecycleStatus === 'active'"
                  class="font-semibold text-rose-300"
                  type="button"
                  @click="archiveMedia(asset)"
                >
                  Archive
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</template>
