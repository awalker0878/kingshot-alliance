<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useLocale } from '@/localization';

type Actor = { name: string } | null;
type Blocker = {
  id: string;
  state: 'active' | 'resolved';
  summary: string;
  details: string | null;
  createdAt: string | null;
  resolvedAt: string | null;
  createdBy: Actor;
  resolvedBy: Actor;
};
type Transition = { id: string; from: string | null; to: string; changedAt: string; actor: Actor };
type HistoryPage = {
  items: (Blocker | Transition)[];
  nextCursor: string | null;
  hasMore: boolean;
  pageSize: number;
  isFirstPage: boolean;
};
const props = defineProps<{
  planId: string;
  participantId: string;
  mode: 'blockers' | 'readiness';
  activeCount?: number;
  resolvedCount?: number;
  transitionCount?: number;
  mutable?: boolean;
}>();
const emit = defineEmits<{
  resolve: [blocker: { id: string; summary: string; state: 'active' | 'resolved' }];
}>();
const { t, formatDate, formatNumber } = useLocale();
const open = ref(false);
const state = ref<'active' | 'resolved'>('active');
const page = ref<HistoryPage | null>(null);
const loading = ref(false);
const error = ref(false);
const requestedCursor = ref<string | null>(null);
let request: AbortController | null = null;
let generation = 0;
const total = computed(() =>
  props.mode === 'readiness'
    ? (props.transitionCount ?? 0)
    : state.value === 'active'
      ? (props.activeCount ?? 0)
      : (props.resolvedCount ?? 0),
);
const blockers = computed(() =>
  props.mode === 'blockers' ? ((page.value?.items as Blocker[] | undefined) ?? []) : [],
);
const transitions = computed(() =>
  props.mode === 'readiness' ? ((page.value?.items as Transition[] | undefined) ?? []) : [],
);

function validPage(value: unknown): value is HistoryPage {
  if (typeof value !== 'object' || value === null) return false;
  const data = value as Partial<HistoryPage>;
  if (
    !Array.isArray(data.items) ||
    data.items.length > 25 ||
    data.pageSize !== 25 ||
    typeof data.hasMore !== 'boolean' ||
    typeof data.isFirstPage !== 'boolean' ||
    !(data.nextCursor === null || typeof data.nextCursor === 'string') ||
    data.hasMore !== (data.nextCursor !== null)
  )
    return false;
  return data.items.every(
    (row) =>
      typeof row === 'object' &&
      row !== null &&
      typeof row.id === 'string' &&
      (props.mode === 'blockers'
        ? 'summary' in row &&
          typeof row.summary === 'string' &&
          'state' in row &&
          row.state === state.value
        : 'to' in row &&
          typeof row.to === 'string' &&
          'changedAt' in row &&
          typeof row.changedAt === 'string'),
  );
}
async function load(cursor: string | null = null): Promise<void> {
  request?.abort();
  const current = ++generation;
  request = new AbortController();
  loading.value = true;
  error.value = false;
  requestedCursor.value = cursor;
  const query = new URLSearchParams();
  if (props.mode === 'blockers') query.set('state', state.value);
  if (cursor !== null) query.set('cursor', cursor);
  const endpoint = props.mode === 'blockers' ? 'blockers' : 'readiness-history';
  try {
    const response = await fetch(
      `/alliance/transfers/${props.planId}/participants/${props.participantId}/${endpoint}?${query.toString()}`,
      {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: request.signal,
      },
    );
    if (!response.ok) throw new Error('Workflow history unavailable');
    const data: unknown = await response.json();
    if (current !== generation) return;
    if (!validPage(data)) throw new Error('Invalid workflow history response');
    page.value = data;
  } catch {
    if (current !== generation) return;
    page.value = null;
    error.value = true;
  } finally {
    if (current === generation) loading.value = false;
  }
}
function toggle(event: Event): void {
  open.value = (event.target as HTMLDetailsElement).open;
  if (open.value && !page.value && !loading.value) void load();
}
watch(
  () => [
    props.planId,
    props.participantId,
    props.activeCount,
    props.resolvedCount,
    props.transitionCount,
    state.value,
  ],
  () => {
    generation++;
    request?.abort();
    page.value = null;
    error.value = false;
    loading.value = false;
    requestedCursor.value = null;
    if (open.value) void load();
  },
);
onBeforeUnmount(() => {
  generation++;
  request?.abort();
});
function timestamp(value: string | null): string {
  return value
    ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' })
    : t('kingdomP7D.notSpecified');
}
</script>

<template>
  <details class="mt-4" :data-testid="`transfer-${mode}-history`" @toggle="toggle">
    <summary class="cursor-pointer font-semibold">
      {{
        t(mode === 'blockers' ? 'kingdomP7D.manualPlanningBlockers' : 'kingdomP7D.readinessHistory')
      }}
      <span class="text-sm text-[var(--ks-muted)]"
        >({{
          formatNumber(
            mode === 'blockers'
              ? (activeCount ?? 0) + (resolvedCount ?? 0)
              : (transitionCount ?? 0),
          )
        }})</span
      >
    </summary>
    <div v-if="mode === 'blockers'" class="mt-3">
      <label :for="`blocker-state-${participantId}`" class="block text-sm">{{
        t('kingdomP7D.state')
      }}</label>
      <select
        :id="`blocker-state-${participantId}`"
        v-model="state"
        class="mt-1 rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] p-2"
      >
        <option value="active">
          {{ t('kingdomP7D.blockerActive') }} ({{ formatNumber(activeCount ?? 0) }})
        </option>
        <option value="resolved">
          {{ t('kingdomP7D.blockerResolved') }} ({{ formatNumber(resolvedCount ?? 0) }})
        </option>
      </select>
    </div>
    <p v-if="loading" class="mt-3 text-sm" role="status">{{ t('common.loading') }}</p>
    <div v-if="error" class="mt-3" role="alert">
      <p>{{ t('kingdomP7D.workflowHistoryUnavailable') }}</p>
      <button
        type="button"
        class="ks-command-button mt-2"
        :disabled="loading"
        @click="load(requestedCursor)"
      >
        {{ t('kingdomP7D.reloadHistory') }}
      </button>
    </div>
    <template v-else-if="page">
      <p v-if="page.items.length === 0" class="mt-3 text-sm">{{ t('common.none') }}</p>
      <ul v-if="mode === 'blockers'" class="mt-3 grid gap-2">
        <li
          v-for="blocker in blockers"
          :key="blocker.id"
          :data-history-id="blocker.id"
          class="rounded-lg border border-[var(--ks-border)] p-3 text-sm"
        >
          <div class="flex justify-between gap-2">
            <strong>{{ blocker.summary }}</strong>
            <button
              v-if="blocker.state === 'active' && mutable"
              type="button"
              class="text-[var(--ks-gold-bright)]"
              @click="
                emit('resolve', { id: blocker.id, summary: blocker.summary, state: blocker.state })
              "
            >
              {{ t('kingdomP7D.resolve') }}
            </button>
          </div>
          <p v-if="blocker.details" class="mt-1 text-[var(--ks-muted)]">{{ blocker.details }}</p>
          <p class="mt-1 text-xs text-[var(--ks-muted)]">
            {{ timestamp(blocker.createdAt) }} ·
            {{ blocker.createdBy?.name ?? t('kingdomP7D.unknownActor') }}
          </p>
          <p v-if="blocker.resolvedAt" class="mt-1 text-xs text-[var(--ks-muted)]">
            {{ t('kingdomP7D.blockerResolved') }}: {{ timestamp(blocker.resolvedAt) }} ·
            {{ blocker.resolvedBy?.name ?? t('kingdomP7D.unknownActor') }}
          </p>
        </li>
      </ul>
      <ul v-else class="mt-3 text-sm text-[var(--ks-muted)]">
        <li v-for="entry in transitions" :key="entry.id" :data-history-id="entry.id">
          {{ t(`kingdomP7D.readiness_${entry.from ?? entry.to}`) }} →
          {{ t(`kingdomP7D.readiness_${entry.to}`) }} · {{ timestamp(entry.changedAt) }} ·
          {{ entry.actor?.name ?? t('kingdomP7D.unknownActor') }}
        </li>
      </ul>
      <nav class="mt-3 flex flex-wrap items-center gap-3" :aria-label="t('common.pagination')">
        <p class="text-xs text-[var(--ks-muted)]" aria-live="polite">
          {{
            t('common.historyItemsOnPage', { count: page.items.length, pageSize: page.pageSize })
          }}
          {{ t('kingdomP7D.workflowHistoryTotal', { count: total }) }}
        </p>
        <button
          v-if="!page.isFirstPage"
          type="button"
          class="ks-command-button"
          :disabled="loading"
          @click="load()"
        >
          {{ t('common.firstPage') }}
        </button>
        <button
          v-if="page.hasMore"
          type="button"
          class="ks-command-button"
          :disabled="loading"
          @click="load(page.nextCursor)"
        >
          {{ t('common.nextPage') }}
        </button>
      </nav>
    </template>
  </details>
</template>
