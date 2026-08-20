<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Option = { value: string; label: string; requiresProvenance: boolean };
type Provenance = {
  sourceLabel: string | null;
  sourceUrl: string | null;
  gameVersion: string | null;
  reviewedAt: string | null;
};
type Freshness = {
  status: 'current' | 'due_soon' | 'stale' | 'not_applicable';
  dueAt: string | null;
  daysUntilDue: number | null;
};
type ContextLink = { type: 'event_type'; key: string };
type EventTypeOption = { slug: string; nameKey: string };
type Category = { id: string; name: string; slug: string; sortOrder: number };
type Revision = { id: string; revisionNumber: number; title: string; createdAt: string | null };
type BroadcastSchedule = {
  id: string;
  status: string;
  weekdays: number[];
  localTime: string;
  timezone: string;
  nextRunAt: string | null;
  lastRunAt: string | null;
  endsAt: string | null;
  cancelledAt: string | null;
};
type BroadcastRun = {
  id: string;
  scheduleId: string | null;
  scheduledFor: string;
  status: string;
  recipientCount: number;
  deliveryCount: number;
  deliveryCounts: Record<string, number>;
  readCount: number;
  failedDeliveryIds: string[];
  queuedAt: string | null;
};
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
  provenance: Provenance | null;
  freshness: Freshness;
  contextLinks: ContextLink[];
  scheduledFor: string | null;
  publishedAt: string | null;
  broadcastedAt: string | null;
  archivedAt: string | null;
  updatedAt: string | null;
  category: { id: string; name: string; slug: string } | null;
  revisions: Revision[];
  broadcastSchedule: BroadcastSchedule | null;
  broadcastRuns: BroadcastRun[];
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
  source_label: string;
  source_url: string;
  game_version: string;
  reviewed_at: string;
  event_type_slugs: string[];
};
type RecurrenceDraft = {
  weekdays: number[];
  local_time: string;
  timezone: string;
  ends_at: string;
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
  eventTypes: EventTypeOption[];
  content: ContentRow[];
  media: Media[];
}>();

const { t, formatDate } = useLocale();
const editingId = ref<string | null>(null);
const scheduleInputs = reactive<Record<string, string>>({});
const recurrenceBusyId = ref<string | null>(null);
const testBusyId = ref<string | null>(null);
const retryBusyId = ref<string | null>(null);
const cancellingSchedule = ref<BroadcastSchedule | null>(null);
const recurrenceForms = reactive<Record<string, RecurrenceDraft>>(
  Object.fromEntries(
    props.content
      .filter((item) => item.type === 'announcement')
      .map((item) => [
        item.id,
        {
          weekdays: item.broadcastSchedule?.weekdays ?? [1, 2, 3, 4, 5],
          local_time: item.broadcastSchedule?.localTime ?? '18:00',
          timezone: item.broadcastSchedule?.timezone ?? props.alliance.timezone,
          ends_at: localDateTimeInput(item.broadcastSchedule?.endsAt ?? null),
        },
      ]),
  ),
);
const weekdayOptions = Array.from({ length: 7 }, (_, index) => {
  const day = index + 1;
  return {
    value: day,
    label: formatDate(new Date(Date.UTC(2024, 0, day)), {
      weekday: 'short',
      timeZone: 'UTC',
    }),
  };
});
watch(
  () => props.content,
  (items) => {
    for (const item of items) {
      if (item.type !== 'announcement' || recurrenceForms[item.id]) continue;
      recurrenceForms[item.id] = {
        weekdays: item.broadcastSchedule?.weekdays ?? [1, 2, 3, 4, 5],
        local_time: item.broadcastSchedule?.localTime ?? '18:00',
        timezone: item.broadcastSchedule?.timezone ?? props.alliance.timezone,
        ends_at: localDateTimeInput(item.broadcastSchedule?.endsAt ?? null),
      };
    }
  },
);
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
const knowledgeReviewQueue = computed(() =>
  props.content.filter(
    (item) => item.freshness.status === 'stale' || item.freshness.status === 'due_soon',
  ),
);
const requiresProvenance = computed(
  () =>
    props.contentTypes.find((option) => option.value === contentForm.type)?.requiresProvenance ??
    false,
);
const today = new Date().toISOString().slice(0, 10);

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
  source_label: '',
  source_url: '',
  game_version: '',
  reviewed_at: '',
  event_type_slugs: [],
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
  contentForm.source_label = item.provenance?.sourceLabel ?? '';
  contentForm.source_url = item.provenance?.sourceUrl ?? '';
  contentForm.game_version = item.provenance?.gameVersion ?? '';
  contentForm.reviewed_at = item.provenance?.reviewedAt ?? '';
  contentForm.event_type_slugs = item.contextLinks
    .filter((link) => link.type === 'event_type')
    .map((link) => link.key);
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
function localDateTimeInput(value: string | null): string {
  if (!value) return '';
  const date = new Date(value);
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
  return local.toISOString().slice(0, 16);
}
function saveRecurrence(item: ContentRow): void {
  const form = recurrenceForms[item.id];
  if (!form || !form.weekdays.length) return;
  recurrenceBusyId.value = item.id;
  router.put(
    '/alliance/content/' + item.id + '/broadcast-schedule',
    {
      ...form,
      ends_at: form.ends_at ? new Date(form.ends_at).toISOString() : null,
    },
    {
      preserveScroll: true,
      onFinish: () => (recurrenceBusyId.value = null),
    },
  );
}
function requestCancelRecurrence(schedule: BroadcastSchedule): void {
  cancellingSchedule.value = schedule;
}
function confirmCancelRecurrence(): void {
  const schedule = cancellingSchedule.value;
  if (!schedule) return;
  recurrenceBusyId.value = schedule.id;
  router.delete('/alliance/content/broadcast-schedules/' + schedule.id, {
    preserveScroll: true,
    onSuccess: () => (cancellingSchedule.value = null),
    onFinish: () => (recurrenceBusyId.value = null),
  });
}
function testBroadcast(id: string): void {
  testBusyId.value = id;
  router.post(
    '/alliance/content/' + id + '/broadcast-test',
    {},
    { preserveScroll: true, onFinish: () => (testBusyId.value = null) },
  );
}
function retryBroadcastFailures(run: BroadcastRun): void {
  if (!run.failedDeliveryIds.length) return;
  retryBusyId.value = run.id;
  router.post(
    '/alliance/content/broadcast-runs/' + run.id + '/retry-failures',
    { delivery_ids: run.failedDeliveryIds },
    { preserveScroll: true, onFinish: () => (retryBusyId.value = null) },
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
function freshnessTone(value: Freshness['status']): 'success' | 'warning' | 'danger' | 'info' {
  if (value === 'current') return 'success';
  if (value === 'due_soon') return 'warning';
  if (value === 'stale') return 'danger';
  return 'info';
}
function timestamp(value: string | null): string {
  return value ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}
function timezoneTimestamp(value: string | null, timezone: string): string {
  return value
    ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short', timeZone: timezone })
    : '—';
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
        <section
          v-if="knowledgeReviewQueue.length"
          class="ks-surface-gold p-5 sm:p-6"
          aria-labelledby="knowledge-review-heading"
        >
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('contentExperience.knowledgeReviewQueue') }}</p>
              <h2 id="knowledge-review-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{
                  t('contentExperience.knowledgeReviewNeeded', {
                    count: knowledgeReviewQueue.length,
                  })
                }}
              </h2>
            </div>
            <span class="ks-status" data-tone="warning">{{ knowledgeReviewQueue.length }}</span>
          </div>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('contentExperience.knowledgeReviewHelp') }}
          </p>
          <ul class="mt-4 grid gap-3 lg:grid-cols-2">
            <li
              v-for="item in knowledgeReviewQueue"
              :key="'review-' + item.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <strong class="block truncate">{{ item.title }}</strong>
                  <p class="mt-1 text-xs text-[var(--ks-muted)]">
                    {{ t('contentExperience.reviewDue') }} {{ item.freshness.dueAt ?? '—' }}
                  </p>
                </div>
                <span class="ks-status" :data-tone="freshnessTone(item.freshness.status)">
                  {{ t(`contentExperience.freshness.${item.freshness.status}`) }}
                </span>
              </div>
              <button type="button" class="ks-chip mt-3" @click="editContent(item)">
                {{ t('contentExperience.reviewNow') }}
              </button>
            </li>
          </ul>
        </section>

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
            <fieldset
              v-if="requiresProvenance"
              class="grid gap-4 rounded-[var(--ks-radius-md)] border border-amber-300/20 bg-amber-300/5 p-4 md:col-span-2 md:grid-cols-2"
            >
              <legend class="px-2 text-sm font-semibold">
                {{ t('contentExperience.knowledgeProvenance') }}
              </legend>
              <p class="text-xs leading-5 text-[var(--ks-muted)] md:col-span-2">
                {{ t('contentExperience.provenanceHelp') }}
              </p>
              <label class="block text-sm">
                <span>{{ t('contentExperience.sourceLabel') }}</span>
                <input
                  v-model="contentForm.source_label"
                  class="ks-input mt-1.5"
                  required
                  maxlength="180"
                />
              </label>
              <label class="block text-sm">
                <span>{{ t('contentExperience.sourceUrl') }}</span>
                <input
                  v-model="contentForm.source_url"
                  class="ks-input mt-1.5"
                  type="url"
                  inputmode="url"
                  maxlength="2048"
                  placeholder="https://"
                />
              </label>
              <label class="block text-sm">
                <span>{{ t('contentExperience.gameVersion') }}</span>
                <input v-model="contentForm.game_version" class="ks-input mt-1.5" maxlength="64" />
              </label>
              <label class="block text-sm">
                <span>{{ t('contentExperience.reviewedAt') }}</span>
                <input
                  v-model="contentForm.reviewed_at"
                  class="ks-input mt-1.5"
                  type="date"
                  :max="today"
                  required
                />
              </label>
            </fieldset>
            <fieldset
              v-if="props.eventTypes.length"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/10 p-4 md:col-span-2"
            >
              <legend class="px-2 text-sm font-semibold">
                {{ t('contentExperience.contextualEvents') }}
              </legend>
              <p class="text-xs leading-5 text-[var(--ks-muted)]">
                {{ t('contentExperience.contextualEventsHelp') }}
              </p>
              <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <label
                  v-for="eventType in props.eventTypes"
                  :key="eventType.slug"
                  class="flex items-start gap-2 rounded border border-[var(--ks-border)] p-2 text-sm"
                >
                  <input
                    v-model="contentForm.event_type_slugs"
                    class="mt-1"
                    type="checkbox"
                    :value="eventType.slug"
                  />
                  <span>{{ t(eventType.nameKey) }}</span>
                </label>
              </div>
            </fieldset>
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
                      <span
                        v-if="item.freshness.status !== 'not_applicable'"
                        class="ks-status"
                        :data-tone="freshnessTone(item.freshness.status)"
                      >
                        {{ t(`contentExperience.freshness.${item.freshness.status}`) }}
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
                    <p v-if="item.provenance" class="mt-2 text-xs leading-5 text-[var(--ks-muted)]">
                      {{ t('contentExperience.source') }}:
                      {{ item.provenance.sourceLabel ?? '—' }}
                      <template v-if="item.provenance.gameVersion">
                        · {{ t('contentExperience.gameVersion') }}
                        {{ item.provenance.gameVersion }}
                      </template>
                      <template v-if="item.provenance.reviewedAt">
                        · {{ t('contentExperience.reviewed') }}
                        {{ item.provenance.reviewedAt }}
                      </template>
                    </p>
                    <p v-if="item.contextLinks.length" class="mt-2 text-xs text-[var(--ks-muted)]">
                      {{ t('contentExperience.contextualEvents') }}:
                      {{ item.contextLinks.map((link) => link.key).join(', ') }}
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

                <section
                  v-if="item.type === 'announcement' && item.notifyMembers"
                  class="mt-4 rounded-[var(--ks-radius-md)] border border-cyan-300/20 bg-cyan-400/5 p-4"
                  :aria-labelledby="'broadcast-controls-' + item.id"
                >
                  <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p class="ks-kicker">{{ t('contentExperience.deliveryAutomation') }}</p>
                      <h4 :id="'broadcast-controls-' + item.id" class="mt-1 font-semibold">
                        {{ t('contentExperience.recurringBroadcast') }}
                      </h4>
                    </div>
                    <AppButton
                      type="button"
                      variant="secondary"
                      :busy="testBusyId === item.id"
                      :busy-label="t('contentExperience.sendingTest')"
                      @click="testBroadcast(item.id)"
                    >
                      {{ t('contentExperience.sendTest') }}
                    </AppButton>
                  </div>

                  <form
                    v-if="item.status === 'published' && recurrenceForms[item.id]"
                    class="mt-4 grid gap-3 md:grid-cols-2"
                    @submit.prevent="saveRecurrence(item)"
                  >
                    <fieldset class="md:col-span-2">
                      <legend class="text-sm font-medium">
                        {{ t('contentExperience.recurringDays') }}
                      </legend>
                      <div class="mt-2 flex flex-wrap gap-2">
                        <label
                          v-for="day in weekdayOptions"
                          :key="day.value"
                          class="ks-chip cursor-pointer"
                        >
                          <input
                            v-model="recurrenceForms[item.id]!.weekdays"
                            class="mr-1.5"
                            type="checkbox"
                            :value="day.value"
                          />
                          {{ day.label }}
                        </label>
                      </div>
                    </fieldset>
                    <label class="block text-sm">
                      <span>{{ t('contentExperience.deliveryTime') }}</span>
                      <input
                        v-model="recurrenceForms[item.id]!.local_time"
                        class="ks-input mt-1.5"
                        type="time"
                        required
                      />
                    </label>
                    <label class="block text-sm">
                      <span>{{ t('contentExperience.deliveryTimezone') }}</span>
                      <input
                        v-model="recurrenceForms[item.id]!.timezone"
                        class="ks-input mt-1.5"
                        required
                      />
                    </label>
                    <label class="block text-sm md:col-span-2 md:max-w-sm">
                      <span>{{ t('contentExperience.recurrenceEnds') }}</span>
                      <input
                        v-model="recurrenceForms[item.id]!.ends_at"
                        class="ks-input mt-1.5"
                        type="datetime-local"
                      />
                    </label>
                    <p class="text-xs leading-5 text-[var(--ks-muted)] md:col-span-2">
                      {{ t('contentExperience.timezonePreview') }}:
                      <strong class="text-[var(--ks-text)]">
                        {{ recurrenceForms[item.id]!.local_time }}
                        {{ recurrenceForms[item.id]!.timezone }}
                      </strong>
                      <template v-if="item.broadcastSchedule?.nextRunAt">
                        · {{ t('contentExperience.nextDelivery') }}
                        {{
                          timezoneTimestamp(
                            item.broadcastSchedule.nextRunAt,
                            item.broadcastSchedule.timezone,
                          )
                        }}
                      </template>
                    </p>
                    <div class="flex flex-wrap gap-2 md:col-span-2">
                      <AppButton
                        type="submit"
                        :busy="recurrenceBusyId === item.id"
                        :busy-label="t('contentExperience.savingRecurrence')"
                        :disabled="!recurrenceForms[item.id]!.weekdays.length"
                      >
                        {{ t('contentExperience.saveRecurrence') }}
                      </AppButton>
                      <AppButton
                        v-if="item.broadcastSchedule?.status === 'active'"
                        type="button"
                        variant="ghost"
                        @click="requestCancelRecurrence(item.broadcastSchedule)"
                      >
                        {{ t('contentExperience.cancelRecurrence') }}
                      </AppButton>
                    </div>
                  </form>
                  <p v-else class="mt-3 text-xs leading-5 text-[var(--ks-muted)]">
                    {{ t('contentExperience.publishBeforeRecurrence') }}
                  </p>

                  <details v-if="item.broadcastRuns.length" class="mt-4">
                    <summary class="cursor-pointer text-sm font-semibold">
                      {{ t('contentExperience.deliveryHistory') }} · {{ item.broadcastRuns.length }}
                    </summary>
                    <div class="mt-3 space-y-2">
                      <article
                        v-for="run in item.broadcastRuns"
                        :key="run.id"
                        class="rounded border border-[var(--ks-border)] bg-black/10 p-3"
                      >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                          <div>
                            <strong class="text-sm">{{ timestamp(run.scheduledFor) }}</strong>
                            <p class="mt-1 text-xs text-[var(--ks-muted)]">
                              {{
                                t('contentExperience.deliveryRunSummary', {
                                  recipients: run.recipientCount,
                                  sent: run.deliveryCounts.sent ?? 0,
                                  queued:
                                    (run.deliveryCounts.queued ?? 0) +
                                    (run.deliveryCounts.pending ?? 0),
                                  failed: run.deliveryCounts.failed ?? 0,
                                  read: run.readCount,
                                })
                              }}
                            </p>
                          </div>
                          <AppButton
                            v-if="run.failedDeliveryIds.length"
                            type="button"
                            variant="ghost"
                            :busy="retryBusyId === run.id"
                            :busy-label="t('contentExperience.retryingFailures')"
                            @click="retryBroadcastFailures(run)"
                          >
                            {{
                              t('contentExperience.retryFailed', {
                                count: run.failedDeliveryIds.length,
                              })
                            }}
                          </AppButton>
                        </div>
                      </article>
                    </div>
                  </details>
                  <p v-else class="mt-4 text-xs text-[var(--ks-muted)]">
                    {{ t('contentExperience.noDeliveryHistory') }}
                  </p>
                </section>
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
    <ConfirmActionDialog
      id="broadcast-schedule-cancellation"
      :open="cancellingSchedule !== null"
      :title="t('contentExperience.cancelRecurrenceTitle')"
      :description="t('contentExperience.cancelRecurrenceDescription')"
      :confirm-label="t('contentExperience.cancelRecurrence')"
      :cancel-label="t('common.cancel')"
      :busy="cancellingSchedule !== null && recurrenceBusyId === cancellingSchedule.id"
      :busy-label="t('contentExperience.cancellingRecurrence')"
      danger
      @confirm="confirmCancelRecurrence"
      @cancel="cancellingSchedule = null"
    />
  </AppLayout>
</template>
