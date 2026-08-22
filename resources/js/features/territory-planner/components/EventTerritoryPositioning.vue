<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import ActionNotice from '@/components/ui/ActionNotice.vue';
import AppButton from '@/components/ui/AppButton.vue';
import { useLocale } from '@/localization';

type Occurrence = { id: string; startsAt: string };
type RevisionOption = {
  id: string;
  planId: string;
  planName: string;
  revisionNumber: number;
  mapDatasetId: string;
  mapDatasetChecksum: string;
  publishedAt: string | null;
};
type Attachment = {
  id: string;
  occurrenceId: string;
  purpose: string;
  revisionId: string;
  planId: string;
  planName: string;
  revisionNumber: number;
  publishedAt: string | null;
};

const props = defineProps<{
  occurrences: Occurrence[];
  planning: {
    supported: boolean;
    availableRevisions: RevisionOption[];
    attachments: Attachment[];
  };
}>();

const { t, formatDate } = useLocale();
const busyOccurrence = ref<string | null>(null);
const notice = ref<{ tone: 'success' | 'danger' | 'info'; message: string } | null>(null);
const selected = ref<Record<string, string>>({});

function attachmentFor(occurrenceId: string): Attachment | undefined {
  return props.planning.attachments.find(
    (attachment) =>
      attachment.occurrenceId === occurrenceId && attachment.purpose === 'positioning',
  );
}

function syncSelections(): void {
  selected.value = Object.fromEntries(
    props.occurrences.map((occurrence) => [
      occurrence.id,
      attachmentFor(occurrence.id)?.revisionId ?? '',
    ]),
  );
}

syncSelections();
watch(
  () => props.planning.attachments,
  () => syncSelections(),
  { deep: true },
);

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function request(
  occurrenceId: string,
  method: 'PUT' | 'DELETE',
  body: object,
): Promise<boolean> {
  busyOccurrence.value = occurrenceId;
  notice.value = null;
  try {
    const response = await fetch(`/events/${occurrenceId}/territory-positioning`, {
      method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(body),
    });
    const payload = (await response.json().catch(() => ({}))) as {
      message?: string;
      errors?: Record<string, string[] | string>;
    };
    if (!response.ok) {
      const first = payload.errors ? Object.values(payload.errors).flat()[0] : null;
      throw new Error(
        typeof first === 'string'
          ? first
          : (payload.message ?? t('territory.eventPositioningRequestFailed')),
      );
    }

    notice.value = {
      tone: 'success',
      message:
        method === 'DELETE'
          ? t('territory.eventPositioningDetached')
          : t('territory.eventPositioningAttached'),
    };
    router.reload({ only: ['territoryPlanning'] });
    return true;
  } catch (error) {
    notice.value = {
      tone: 'danger',
      message:
        error instanceof Error ? error.message : t('territory.eventPositioningRequestFailed'),
    };
    return false;
  } finally {
    busyOccurrence.value = null;
  }
}

async function attach(occurrenceId: string): Promise<void> {
  const revisionId = selected.value[occurrenceId];
  if (!revisionId) return;
  await request(occurrenceId, 'PUT', {
    territory_plan_revision_id: revisionId,
    purpose: 'positioning',
  });
}

async function detach(occurrenceId: string): Promise<void> {
  if (await request(occurrenceId, 'DELETE', { purpose: 'positioning' })) {
    selected.value[occurrenceId] = '';
  }
}
</script>

<template>
  <section
    v-if="planning.supported"
    id="territory-positioning"
    class="ks-surface scroll-mt-28 p-5 sm:p-6"
    aria-labelledby="territory-positioning-heading"
  >
    <p class="ks-kicker">{{ t('territory.eventPositioningEyebrow') }}</p>
    <h2 id="territory-positioning-heading" class="ks-display mt-1 text-2xl font-semibold">
      {{ t('territory.eventPositioningTitle') }}
    </h2>
    <p class="mt-2 max-w-3xl text-sm text-[var(--ks-muted)]">
      {{ t('territory.eventPositioningHelp') }}
    </p>
    <ActionNotice v-if="notice" class="mt-4" :tone="notice.tone" :message="notice.message" />

    <div v-if="planning.availableRevisions.length" class="mt-5 grid gap-4 xl:grid-cols-2">
      <article
        v-for="occurrence in occurrences"
        :key="occurrence.id"
        class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-4"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-xs font-semibold tracking-[0.14em] text-[var(--ks-muted)] uppercase">
              {{ t('territory.eventOccurrence') }}
            </p>
            <p class="mt-1 font-semibold">{{ formatDate(occurrence.startsAt) }}</p>
          </div>
          <Link
            v-if="attachmentFor(occurrence.id)"
            :href="`/territory/${attachmentFor(occurrence.id)?.planId}`"
            class="ks-command-link"
            data-variant="secondary"
          >
            {{ t('territory.openPinnedPlan') }}
          </Link>
        </div>

        <p v-if="attachmentFor(occurrence.id)" class="mt-3 text-xs text-[var(--ks-muted)]">
          {{
            t('territory.currentPinnedRevision', {
              plan: attachmentFor(occurrence.id)?.planName ?? '',
              revision: attachmentFor(occurrence.id)?.revisionNumber ?? 0,
            })
          }}
        </p>
        <p v-else class="mt-3 text-xs text-[var(--ks-muted)]">
          {{ t('territory.noPinnedRevision') }}
        </p>

        <label class="mt-4 block text-sm font-semibold">
          {{ t('territory.publishedRevision') }}
          <select v-model="selected[occurrence.id]" class="ks-input mt-2 w-full">
            <option value="">{{ t('territory.choosePublishedRevision') }}</option>
            <option
              v-for="revision in planning.availableRevisions"
              :key="revision.id"
              :value="revision.id"
            >
              {{ revision.planName }} · #{{ revision.revisionNumber }} ·
              {{ revision.publishedAt ? formatDate(revision.publishedAt) : '—' }}
            </option>
          </select>
        </label>

        <div class="mt-3 flex flex-wrap gap-2">
          <AppButton
            :busy="busyOccurrence === occurrence.id"
            :disabled="!selected[occurrence.id]"
            @click="attach(occurrence.id)"
          >
            {{ t('territory.pinRevision') }}
          </AppButton>
          <button
            v-if="attachmentFor(occurrence.id)"
            type="button"
            class="ks-command-link"
            data-variant="danger"
            :disabled="busyOccurrence === occurrence.id"
            @click="detach(occurrence.id)"
          >
            {{ t('territory.removePinnedRevision') }}
          </button>
        </div>
      </article>
    </div>
    <p v-else class="mt-4 text-sm text-[var(--ks-muted)]">
      {{ t('territory.noPublishedRevisionsForEvent') }}
    </p>
  </section>
</template>
