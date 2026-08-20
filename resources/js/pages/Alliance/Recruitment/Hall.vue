<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import CursorPagination from '@/components/ui/CursorPagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Candidate = {
  id: string;
  name: string;
  email: string;
  contactHandle: string | null;
  source: string | null;
  stage: string;
  submittedAt: string;
  firstRespondedAt: string | null;
  nextActionAt: string | null;
};

type Metrics = {
  total: number;
  byStage: Record<string, number>;
  bySource: Record<string, number>;
  sourceFunnel: Record<
    string,
    {
      submitted: number;
      accepted: number;
      joined: number;
      acceptedRate: number;
      joinedRate: number;
    }
  >;
  averageResponseHours: number | null;
  acceptedRate: number;
  joinedRate: number;
  averageStageAgeDays: Record<string, number>;
};

type QuestionEdit = {
  prompt: string;
  helpText: string;
  type: string;
  optionsText: string;
  required: boolean;
  position: number;
  active: boolean;
};

type BulkPreview = {
  targetStage: string;
  items: Array<{
    itemId: string;
    label: string;
    fromStage: string | null;
    outcome: 'ready' | 'blocked' | 'skipped';
    code: string;
  }>;
  ready: number;
  blocked: number;
  readyItemIds: string[];
};

type BulkResult = {
  action: string;
  items: Array<{
    itemId: string;
    label: string;
    outcome: 'succeeded' | 'failed' | 'skipped';
    code: string;
  }>;
  succeeded: number;
  failed: number;
  skipped: number;
  failedItemIds: string[];
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; slug: string };
  settings: {
    mode: string;
    title: string;
    introduction: string | null;
    retentionDays: number;
    open: boolean;
    listed: boolean;
  } | null;
  applicationModes: string[];
  questionTypes: string[];
  candidateStages: string[];
  questions: Array<{
    id: string;
    prompt: string;
    helpText: string | null;
    type: string;
    options: string[];
    required: boolean;
    position: number;
    active: boolean;
  }>;
  candidatePage: {
    items: Candidate[];
    nextCursor: string | null;
    hasMore: boolean;
    pageSize: number;
    isFirstPage: boolean;
  };
  candidateFilters: { q: string; stage: string; source: string };
  members: Array<{ id: string; name: string; rank: string }>;
  decisionTemplates: Array<{
    id: string;
    name: string;
    decisionStage: string;
    subject: string;
    body: string;
    active: boolean;
  }>;
  onboardingItems: Array<{
    id: string;
    name: string;
    description: string | null;
    position: number;
    required: boolean;
    active: boolean;
  }>;
  metrics: Metrics;
  discovery: { boardUrl: string; applicationUrl: string | null };
  issuedApplicationLink: string | null;
  bulkPreview: BulkPreview | null;
  bulkResult: BulkResult | null;
}>();

const { t, formatDate, formatNumber } = useLocale();
const candidates = computed(() => props.candidatePage.items);
const candidateFilters = reactive({ ...props.candidateFilters });
const firstCandidatePageUrl = computed(() => {
  const query = new URLSearchParams(
    Object.fromEntries(Object.entries(candidateFilters).filter(([, value]) => value !== '')),
  ).toString();
  return query === '' ? '/alliance/recruitment' : `/alliance/recruitment?${query}`;
});
const selectedCandidateIds = ref<string[]>(props.bulkResult?.failedItemIds ?? []);
const bulkStageOptions = computed(() =>
  props.candidateStages.filter((stage) => stage !== 'joined'),
);
const bulkTargetStage = ref(bulkStageOptions.value[0] ?? 'screening');
const bulkReason = ref('');
const bulkNextActionAt = ref('');
const bulkBusy = ref(false);
const bulkConfirmationOpen = ref(false);
const allPageCandidatesSelected = computed(
  () =>
    candidates.value.length > 0 &&
    candidates.value.every((candidate) => selectedCandidateIds.value.includes(candidate.id)),
);
const bulkPreviewMatchesSelection = computed(() => {
  const preview = props.bulkPreview;
  if (!preview || preview.targetStage !== bulkTargetStage.value) return false;

  const selected = [...selectedCandidateIds.value].sort();
  const previewed = preview.items.map((item) => item.itemId).sort();
  return (
    selected.length === previewed.length && selected.every((id, index) => id === previewed[index])
  );
});

watch(
  () => props.bulkResult,
  (result) => {
    if (result) selectedCandidateIds.value = [...result.failedItemIds];
  },
);

const settingsForm = useForm({
  mode: props.settings?.mode ?? 'public',
  title: props.settings?.title ?? t('recruitment.title'),
  introduction: props.settings?.introduction ?? '',
  retention_days: props.settings?.retentionDays ?? 90,
  open: props.settings?.open ?? true,
  listed: props.settings?.listed ?? false,
});
const questionForm = useForm({
  prompt: '',
  help_text: '',
  type: 'short_text',
  options: [] as string[],
  required: false,
  position: props.questions.length,
  active: true,
});
const questionOptions = ref('');
const applicationLinkCopied = ref(false);
const questionEdits = reactive<Record<string, QuestionEdit>>({});
for (const question of props.questions) {
  questionEdits[question.id] = {
    prompt: question.prompt,
    helpText: question.helpText ?? '',
    type: question.type,
    optionsText: question.options.join('\n'),
    required: question.required,
    position: question.position,
    active: question.active,
  };
}

const candidatePlaceholder = '{{candidate_name}}';
const alliancePlaceholder = '{{alliance_name}}';
const inviteForm = useForm({ email: '', ttl_hours: 72 });
const decisionForm = useForm({
  name: '',
  decision_stage: 'accepted',
  subject: '',
  body: '',
  active: true,
});
const onboardingForm = useForm({
  name: '',
  description: '',
  position: props.onboardingItems.length,
  required: true,
  active: true,
});

function questionEdit(id: string): QuestionEdit {
  const edit = questionEdits[id];
  if (!edit) throw new Error(`Missing question edit state for ${id}`);
  return edit;
}

async function copyApplicationLink(): Promise<void> {
  if (!props.discovery.applicationUrl || !navigator.clipboard) return;

  await navigator.clipboard.writeText(props.discovery.applicationUrl);
  applicationLinkCopied.value = true;
  window.setTimeout(() => {
    applicationLinkCopied.value = false;
  }, 2400);
}

function saveSettings(): void {
  settingsForm.patch('/alliance/recruitment/settings', { preserveScroll: true });
}

function createQuestion(): void {
  questionForm.options = questionOptions.value
    .split('\n')
    .map((value) => value.trim())
    .filter((value) => value !== '');
  questionForm.post('/alliance/recruitment/questions', {
    preserveScroll: true,
    onSuccess: () => {
      questionForm.reset();
      questionForm.type = 'short_text';
      questionForm.position = props.questions.length + 1;
      questionForm.active = true;
      questionOptions.value = '';
    },
  });
}

function saveQuestion(id: string): void {
  const edit = questionEdit(id);
  router.post(
    '/alliance/recruitment/questions',
    {
      question_id: id,
      prompt: edit.prompt,
      help_text: edit.helpText,
      type: edit.type,
      options: edit.optionsText
        .split('\n')
        .map((value) => value.trim())
        .filter((value) => value !== ''),
      required: edit.required,
      position: edit.position,
      active: edit.active,
    },
    { preserveScroll: true },
  );
}

function issueInvite(): void {
  inviteForm.post('/alliance/recruitment/application-invites', {
    preserveScroll: true,
    onSuccess: () => inviteForm.reset('email'),
  });
}

function createDecisionTemplate(): void {
  decisionForm.post('/alliance/recruitment/decision-templates', {
    preserveScroll: true,
    onSuccess: () => {
      decisionForm.reset();
      decisionForm.decision_stage = 'accepted';
      decisionForm.active = true;
    },
  });
}

function createOnboardingItem(): void {
  onboardingForm.post('/alliance/recruitment/onboarding-items', {
    preserveScroll: true,
    onSuccess: () => {
      onboardingForm.reset();
      onboardingForm.position = props.onboardingItems.length + 1;
      onboardingForm.required = true;
      onboardingForm.active = true;
    },
  });
}

function applyCandidateFilters(): void {
  router.get(
    '/alliance/recruitment',
    Object.fromEntries(Object.entries(candidateFilters).filter(([, value]) => value !== '')),
    { preserveScroll: true, preserveState: true, replace: true },
  );
}

function clearCandidateFilters(): void {
  candidateFilters.q = '';
  candidateFilters.stage = '';
  candidateFilters.source = '';
  applyCandidateFilters();
}

function candidateSelected(candidateId: string): boolean {
  return selectedCandidateIds.value.includes(candidateId);
}

function setCandidateSelected(candidateId: string, selected: boolean): void {
  selectedCandidateIds.value = selected
    ? [...new Set([...selectedCandidateIds.value, candidateId])]
    : selectedCandidateIds.value.filter((id) => id !== candidateId);
}

function togglePageSelection(): void {
  if (allPageCandidatesSelected.value) {
    const pageIds = new Set(candidates.value.map((candidate) => candidate.id));
    selectedCandidateIds.value = selectedCandidateIds.value.filter((id) => !pageIds.has(id));
    return;
  }

  selectedCandidateIds.value = [
    ...new Set([
      ...selectedCandidateIds.value,
      ...candidates.value.map((candidate) => candidate.id),
    ]),
  ];
}

function previewBulkStageChange(): void {
  if (selectedCandidateIds.value.length === 0 || bulkBusy.value) return;

  bulkBusy.value = true;
  router.post(
    '/alliance/recruitment/bulk-stage/preview',
    {
      candidate_ids: selectedCandidateIds.value,
      stage: bulkTargetStage.value,
      reason: bulkReason.value,
      next_action_at: bulkNextActionAt.value || null,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => (bulkBusy.value = false),
    },
  );
}

function commitBulkStageChange(): void {
  if (!props.bulkPreview || !bulkPreviewMatchesSelection.value || bulkBusy.value) return;

  bulkBusy.value = true;
  router.post(
    '/alliance/recruitment/bulk-stage',
    {
      candidate_ids: props.bulkPreview.items.map((item) => item.itemId),
      stage: props.bulkPreview.targetStage,
      reason: bulkReason.value,
      next_action_at: bulkNextActionAt.value || null,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        bulkBusy.value = false;
        bulkConfirmationOpen.value = false;
      },
    },
  );
}

function bulkOutcomeLabel(code: string): string {
  return t(`recruitment.bulkOutcome.${code}`);
}

function nextCandidatePage(): void {
  if (!props.candidatePage.nextCursor) return;

  router.get(
    '/alliance/recruitment',
    {
      ...Object.fromEntries(Object.entries(candidateFilters).filter(([, value]) => value !== '')),
      cursor: props.candidatePage.nextCursor,
    },
    { preserveScroll: true, preserveState: true },
  );
}

function date(value: string | null): string {
  return value ? formatDate(value) : '—';
}

function percentage(value: number): string {
  return `${Math.round(value * 100)}%`;
}

function stageTone(stage: string): 'success' | 'warning' | 'danger' | 'info' {
  if (stage === 'accepted' || stage === 'joined') return 'success';
  if (stage === 'declined' || stage === 'withdrawn') return 'danger';
  if (stage === 'reviewing' || stage === 'interview') return 'info';
  return 'warning';
}

function humanize(value: string): string {
  return value.replaceAll('_', ' ');
}
</script>

<template>
  <Head :title="`${t('recruitment.title')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('recruitment.eyebrow')"
      :title="t('recruitment.pipeline')"
      :subtitle="t('recruitment.subtitle', { alliance: alliance.name })"
      image="/images/kingshot/v4/recruitment-hall.svg"
    >
      <template #actions>
        <a
          :href="discovery.boardUrl"
          class="ks-command-link"
          target="_blank"
          rel="noopener noreferrer"
        >
          {{ t('recruitment.discoveryBoard') }}
        </a>
        <a
          v-if="discovery.applicationUrl"
          :href="discovery.applicationUrl"
          class="ks-command-link"
          target="_blank"
          rel="noopener noreferrer"
        >
          {{ t('recruitment.publicForm') }}
        </a>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-5">
      <StatSeal
        :label="t('recruitment.candidates')"
        :value="formatNumber(metrics.total)"
        icon="♟"
      />
      <StatSeal
        :label="t('recruitment.averageResponse')"
        :value="
          metrics.averageResponseHours === null
            ? '—'
            : `${metrics.averageResponseHours}${t('recruitment.hours')}`
        "
        icon="◷"
        tone="teal"
      />
      <StatSeal
        :label="t('recruitment.accepted')"
        :value="percentage(metrics.acceptedRate)"
        icon="✓"
        tone="stone"
      />
      <StatSeal :label="t('recruitment.joined')" :value="percentage(metrics.joinedRate)" icon="♜" />
      <StatSeal
        :label="t('recruitment.sources')"
        :value="formatNumber(Object.keys(metrics.bySource).length)"
        icon="◇"
        tone="teal"
      />
    </section>

    <section
      v-if="Object.keys(metrics.sourceFunnel).length"
      class="ks-surface mt-5 p-5 sm:p-6"
      aria-labelledby="source-performance-heading"
    >
      <div>
        <p class="ks-kicker">{{ t('recruitment.sources') }}</p>
        <h2 id="source-performance-heading" class="ks-display mt-1 text-xl font-semibold">
          {{ t('recruitment.sourcePerformance') }}
        </h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-muted)]">
          {{ t('recruitment.sourcePerformanceHelp') }}
        </p>
      </div>
      <div class="mt-5 grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
        <article
          v-for="(sourceMetrics, source) in metrics.sourceFunnel"
          :key="source"
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
        >
          <h3
            class="text-lg font-[var(--ks-font-display)] font-semibold text-[var(--ks-gold-bright)]"
          >
            {{ humanize(source) }}
          </h3>
          <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
            <div>
              <dt class="text-xs text-[var(--ks-muted)]">{{ t('recruitment.submittedCount') }}</dt>
              <dd class="mt-1 font-bold">{{ formatNumber(sourceMetrics.submitted) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-muted)]">{{ t('recruitment.acceptedCount') }}</dt>
              <dd class="mt-1 font-bold">
                {{ formatNumber(sourceMetrics.accepted) }} ·
                {{ percentage(sourceMetrics.acceptedRate) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-muted)]">{{ t('recruitment.joinedCount') }}</dt>
              <dd class="mt-1 font-bold">
                {{ formatNumber(sourceMetrics.joined) }} ·
                {{ percentage(sourceMetrics.joinedRate) }}
              </dd>
            </div>
          </dl>
        </article>
      </div>
    </section>

    <section class="ks-surface mt-5 overflow-hidden" aria-labelledby="pipeline-heading">
      <div
        class="flex flex-wrap items-end justify-between gap-4 border-b border-[var(--ks-border)] p-5"
      >
        <div>
          <p class="ks-kicker">{{ t('recruitment.privateRecruiterView') }}</p>
          <h2 id="pipeline-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('recruitment.pipeline') }}
          </h2>
        </div>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="stage in candidateStages"
            :key="stage"
            class="ks-status"
            :data-tone="stageTone(stage)"
          >
            {{ humanize(stage) }} · {{ metrics.byStage[stage] ?? 0 }}
          </span>
        </div>
      </div>

      <form
        class="grid gap-3 border-b border-[var(--ks-border)] bg-black/10 p-5 md:grid-cols-3 xl:grid-cols-[minmax(16rem,2fr)_1fr_1fr_auto]"
        :aria-label="t('recruitment.candidateFilters')"
        @submit.prevent="applyCandidateFilters"
      >
        <div>
          <label class="text-xs font-semibold" for="recruitment-candidate-search">
            {{ t('recruitment.searchCandidates') }}
          </label>
          <input
            id="recruitment-candidate-search"
            v-model="candidateFilters.q"
            class="ks-input mt-1.5"
            maxlength="160"
            :placeholder="t('recruitment.searchCandidatesPlaceholder')"
          />
        </div>
        <div>
          <label class="text-xs font-semibold" for="recruitment-stage-filter">
            {{ t('recruitment.stage') }}
          </label>
          <select
            id="recruitment-stage-filter"
            v-model="candidateFilters.stage"
            class="ks-input mt-1.5"
          >
            <option value="">{{ t('recruitment.allStages') }}</option>
            <option v-for="stage in candidateStages" :key="stage" :value="stage">
              {{ humanize(stage) }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold" for="recruitment-source-filter">
            {{ t('recruitment.source') }}
          </label>
          <select
            id="recruitment-source-filter"
            v-model="candidateFilters.source"
            class="ks-input mt-1.5"
          >
            <option value="">{{ t('recruitment.allSources') }}</option>
            <option v-for="(_, source) in metrics.bySource" :key="source" :value="source">
              {{ humanize(source) }}
            </option>
          </select>
        </div>
        <div class="flex flex-wrap items-end gap-2">
          <AppButton type="submit">{{ t('recruitment.applyFilters') }}</AppButton>
          <AppButton type="button" variant="ghost" @click="clearCandidateFilters">
            {{ t('recruitment.clearFilters') }}
          </AppButton>
        </div>
      </form>

      <section
        v-if="selectedCandidateIds.length"
        class="border-b border-[var(--ks-border)] bg-[var(--ks-teal-soft)] p-5"
        :aria-label="t('recruitment.bulkActions')"
      >
        <div class="flex flex-wrap items-end gap-3">
          <div class="min-w-[12rem] flex-1">
            <p class="text-sm font-semibold">
              {{ t('recruitment.selectedCandidates', { count: selectedCandidateIds.length }) }}
            </p>
            <p class="mt-1 text-xs text-[var(--ks-muted)]">
              {{ t('recruitment.bulkPreviewHelp') }}
            </p>
          </div>
          <div>
            <label class="text-xs font-semibold" for="recruitment-bulk-stage">
              {{ t('recruitment.moveTo') }}
            </label>
            <select id="recruitment-bulk-stage" v-model="bulkTargetStage" class="ks-input mt-1.5">
              <option v-for="stage in bulkStageOptions" :key="stage" :value="stage">
                {{ humanize(stage) }}
              </option>
            </select>
          </div>
          <div class="min-w-[14rem] flex-1">
            <label class="text-xs font-semibold" for="recruitment-bulk-reason">
              {{ t('recruitment.internalReason') }}
            </label>
            <input
              id="recruitment-bulk-reason"
              v-model="bulkReason"
              class="ks-input mt-1.5"
              maxlength="5000"
            />
          </div>
          <div>
            <label class="text-xs font-semibold" for="recruitment-bulk-next-action">
              {{ t('recruitment.nextAction') }}
            </label>
            <input
              id="recruitment-bulk-next-action"
              v-model="bulkNextActionAt"
              class="ks-input mt-1.5"
              type="datetime-local"
            />
          </div>
          <AppButton
            :busy="bulkBusy"
            :busy-label="t('recruitment.previewingBulkAction')"
            @click="previewBulkStageChange"
          >
            {{ t('recruitment.previewBulkAction') }}
          </AppButton>
        </div>
      </section>

      <section
        v-if="bulkPreviewMatchesSelection && bulkPreview"
        class="border-b border-[var(--ks-border)] p-5"
        aria-labelledby="recruitment-bulk-preview-heading"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="ks-kicker">{{ t('recruitment.bulkPreview') }}</p>
            <h3 id="recruitment-bulk-preview-heading" class="mt-1 text-lg font-semibold">
              {{
                t('recruitment.bulkPreviewSummary', {
                  ready: bulkPreview.ready,
                  blocked: bulkPreview.blocked,
                })
              }}
            </h3>
          </div>
          <AppButton
            :disabled="bulkPreview.ready === 0"
            :variant="bulkTargetStage === 'declined' ? 'danger' : 'primary'"
            @click="bulkConfirmationOpen = true"
          >
            {{ t('recruitment.confirmBulkAction') }}
          </AppButton>
        </div>
        <ul class="mt-4 grid gap-2 md:grid-cols-2">
          <li
            v-for="item in bulkPreview.items"
            :key="item.itemId"
            class="flex items-center justify-between gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 px-3 py-2 text-sm"
          >
            <span class="truncate">{{ item.label }}</span>
            <span
              class="ks-status"
              :data-tone="
                item.outcome === 'ready'
                  ? 'success'
                  : item.outcome === 'skipped'
                    ? 'warning'
                    : 'danger'
              "
            >
              {{ bulkOutcomeLabel(item.code) }}
            </span>
          </li>
        </ul>
      </section>

      <section
        v-if="bulkResult"
        class="border-b border-[var(--ks-border)] p-5"
        aria-labelledby="recruitment-bulk-result-heading"
      >
        <p class="ks-kicker">{{ t('recruitment.bulkResult') }}</p>
        <h3 id="recruitment-bulk-result-heading" class="mt-1 text-lg font-semibold">
          {{
            t('recruitment.bulkResultSummary', {
              succeeded: bulkResult.succeeded,
              failed: bulkResult.failed,
              skipped: bulkResult.skipped,
            })
          }}
        </h3>
        <ul class="mt-4 grid gap-2 md:grid-cols-2">
          <li
            v-for="item in bulkResult.items"
            :key="item.itemId"
            class="flex items-center justify-between gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 px-3 py-2 text-sm"
          >
            <span class="truncate">{{ item.label }}</span>
            <span
              class="ks-status"
              :data-tone="
                item.outcome === 'succeeded'
                  ? 'success'
                  : item.outcome === 'skipped'
                    ? 'warning'
                    : 'danger'
              "
            >
              {{ bulkOutcomeLabel(item.code) }}
            </span>
          </li>
        </ul>
        <p v-if="bulkResult.failed" class="mt-3 text-xs text-[var(--ks-muted)]">
          {{ t('recruitment.failedItemsSelected') }}
        </p>
      </section>

      <div v-if="candidates.length" class="lg:hidden">
        <article
          v-for="candidate in candidates"
          :key="candidate.id"
          class="border-b border-[var(--ks-border)] p-4 last:border-b-0"
        >
          <div class="flex items-start gap-3">
            <input
              type="checkbox"
              :checked="candidateSelected(candidate.id)"
              :aria-label="t('recruitment.selectCandidate', { candidate: candidate.name })"
              @change="
                setCandidateSelected(candidate.id, ($event.target as HTMLInputElement).checked)
              "
            />
            <div
              class="grid h-11 w-11 shrink-0 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-black/20 font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
              aria-hidden="true"
            >
              {{ candidate.name.slice(0, 1).toUpperCase() }}
            </div>
            <div class="min-w-0 flex-1">
              <Link
                class="block truncate text-lg font-[var(--ks-font-display)] font-semibold text-[var(--ks-gold-bright)] hover:text-[var(--ks-ivory)]"
                :href="`/alliance/recruitment/${candidate.id}`"
              >
                {{ candidate.name }}
              </Link>
              <p class="mt-1 truncate text-xs text-[var(--ks-muted)]">{{ candidate.email }}</p>
              <p class="mt-1 truncate text-xs text-[var(--ks-text-secondary)]">
                {{ candidate.source || t('recruitment.unspecified') }}
              </p>
            </div>
            <span class="ks-status" :data-tone="stageTone(candidate.stage)">
              {{ humanize(candidate.stage) }}
            </span>
          </div>
          <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
            <div>
              <dt class="text-[var(--ks-muted)]">{{ t('recruitment.submitted') }}</dt>
              <dd class="mt-1">{{ date(candidate.submittedAt) }}</dd>
            </div>
            <div>
              <dt class="text-[var(--ks-muted)]">{{ t('recruitment.nextAction') }}</dt>
              <dd class="mt-1">{{ date(candidate.nextActionAt) }}</dd>
            </div>
          </dl>
        </article>
      </div>

      <div v-if="candidates.length" class="hidden overflow-x-auto lg:block">
        <table class="w-full min-w-[60rem] text-sm">
          <thead
            class="bg-black/20 text-[.66rem] font-extrabold tracking-[.08em] text-[var(--ks-muted)] uppercase"
          >
            <tr>
              <th class="px-4 py-3 text-start">
                <input
                  type="checkbox"
                  :checked="allPageCandidatesSelected"
                  :aria-label="t('recruitment.selectPage')"
                  @change="togglePageSelection"
                />
              </th>
              <th class="px-5 py-3 text-start">{{ t('recruitment.candidate') }}</th>
              <th class="px-4 py-3 text-start">{{ t('recruitment.stage') }}</th>
              <th class="px-4 py-3 text-start">{{ t('recruitment.source') }}</th>
              <th class="px-4 py-3 text-start">{{ t('recruitment.submitted') }}</th>
              <th class="px-4 py-3 text-start">{{ t('recruitment.nextAction') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr
              v-for="candidate in candidates"
              :key="candidate.id"
              class="transition hover:bg-white/[0.018]"
            >
              <td class="px-4 py-4">
                <input
                  type="checkbox"
                  :checked="candidateSelected(candidate.id)"
                  :aria-label="t('recruitment.selectCandidate', { candidate: candidate.name })"
                  @change="
                    setCandidateSelected(candidate.id, ($event.target as HTMLInputElement).checked)
                  "
                />
              </td>
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  <div
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-black/20 font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
                    aria-hidden="true"
                  >
                    {{ candidate.name.slice(0, 1).toUpperCase() }}
                  </div>
                  <div class="min-w-0">
                    <Link
                      class="font-[var(--ks-font-display)] font-semibold text-[var(--ks-gold-bright)] hover:text-[var(--ks-ivory)]"
                      :href="`/alliance/recruitment/${candidate.id}`"
                    >
                      {{ candidate.name }}
                    </Link>
                    <p class="mt-1 truncate text-xs text-[var(--ks-muted)]">
                      {{ candidate.email }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4">
                <span class="ks-status" :data-tone="stageTone(candidate.stage)">
                  {{ humanize(candidate.stage) }}
                </span>
              </td>
              <td class="px-4 py-4 text-[var(--ks-text-secondary)]">
                {{ candidate.source || t('recruitment.unspecified') }}
              </td>
              <td class="px-4 py-4 text-[var(--ks-text-secondary)]">
                {{ date(candidate.submittedAt) }}
              </td>
              <td class="px-4 py-4 text-[var(--ks-text-secondary)]">
                {{ date(candidate.nextActionAt) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!candidates.length" class="ks-fantasy-empty m-5">
        {{ t('recruitment.noCandidates') }}
      </div>
      <CursorPagination
        v-if="candidates.length"
        :summary="
          t('recruitment.candidatesOnPage', {
            count: formatNumber(candidates.length),
            pageSize: formatNumber(candidatePage.pageSize),
          })
        "
        :is-first-page="candidatePage.isFirstPage"
        :first-page-href="firstCandidatePageUrl"
        :has-more="candidatePage.hasMore"
        @next="nextCandidatePage"
      />
    </section>

    <ConfirmActionDialog
      id="recruitment-bulk-stage-confirmation"
      :open="bulkConfirmationOpen"
      :title="t('recruitment.confirmBulkTitle')"
      :description="
        t('recruitment.confirmBulkDescription', {
          count: bulkPreview?.ready ?? 0,
          stage: humanize(bulkTargetStage),
        })
      "
      :confirm-label="t('recruitment.confirmBulkAction')"
      :cancel-label="t('common.cancel')"
      :busy="bulkBusy"
      :busy-label="t('recruitment.applyingBulkAction')"
      :danger="bulkTargetStage === 'declined'"
      @confirm="commitBulkStageChange"
      @cancel="bulkConfirmationOpen = false"
    />

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
      <section class="ks-surface p-5 sm:p-6" aria-labelledby="settings-heading">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="ks-kicker">{{ t('recruitment.settings') }}</p>
            <h2 id="settings-heading" class="ks-display mt-1 text-xl font-semibold">
              {{ t('recruitment.publicForm') }}
            </h2>
          </div>
          <span class="ks-status" :data-tone="settingsForm.open ? 'success' : 'warning'">
            {{ settingsForm.open ? t('recruitment.active') : t('recruitment.no') }}
          </span>
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="saveSettings">
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="recruitment-mode"
            >
              {{ t('recruitment.applicationMode') }}
            </label>
            <select id="recruitment-mode" v-model="settingsForm.mode" class="ks-input mt-1.5">
              <option v-for="mode in applicationModes" :key="mode" :value="mode">
                {{ humanize(mode) }}
              </option>
            </select>
          </div>
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="recruitment-title"
            >
              {{ t('recruitment.publicTitle') }}
            </label>
            <input
              id="recruitment-title"
              v-model="settingsForm.title"
              class="ks-input mt-1.5"
              maxlength="160"
              required
            />
          </div>
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="recruitment-introduction"
            >
              {{ t('recruitment.introduction') }}
            </label>
            <textarea
              id="recruitment-introduction"
              v-model="settingsForm.introduction"
              class="ks-input mt-1.5 min-h-28"
              maxlength="5000"
            />
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="retention-days"
              >
                {{ t('recruitment.retentionDays') }}
              </label>
              <input
                id="retention-days"
                v-model.number="settingsForm.retention_days"
                class="ks-input mt-1.5"
                type="number"
                min="1"
                max="3650"
                required
              />
            </div>
            <label
              class="flex items-center gap-2 self-end pb-3 text-sm text-[var(--ks-text-secondary)]"
            >
              <input v-model="settingsForm.open" type="checkbox" />
              {{ t('recruitment.applicationsOpen') }}
            </label>
          </div>
          <label
            class="block rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
          >
            <span
              class="flex items-center gap-2 text-sm font-semibold text-[var(--ks-text-secondary)]"
            >
              <input v-model="settingsForm.listed" type="checkbox" />
              {{ t('recruitment.publicListing') }}
            </span>
            <span class="mt-2 block text-xs leading-5 text-[var(--ks-muted)]">
              {{ t('recruitment.publicListingHelp') }}
            </span>
          </label>
          <div class="flex flex-wrap gap-3">
            <AppButton type="submit" :disabled="settingsForm.processing">
              {{ t('recruitment.saveSettings') }}
            </AppButton>
            <a
              :href="discovery.boardUrl"
              class="ks-command-link"
              target="_blank"
              rel="noopener noreferrer"
            >
              {{ t('recruitment.discoveryBoard') }}
            </a>
            <button
              v-if="discovery.applicationUrl"
              type="button"
              class="ks-command-link"
              @click="copyApplicationLink"
            >
              {{
                applicationLinkCopied
                  ? t('recruitment.applicationLinkCopied')
                  : t('recruitment.copyApplicationLink')
              }}
            </button>
          </div>
        </form>

        <div class="ks-divider my-6" />

        <form @submit.prevent="issueInvite">
          <p class="ks-kicker">{{ t('recruitment.inviteLink') }}</p>
          <h3 class="ks-display mt-1 text-lg font-semibold">{{ t('recruitment.issue') }}</h3>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('recruitment.inviteHelp') }}
          </p>
          <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_8rem_auto]">
            <input
              v-model="inviteForm.email"
              class="ks-input"
              type="email"
              :placeholder="t('recruitment.optionalEmail')"
            />
            <input
              v-model.number="inviteForm.ttl_hours"
              class="ks-input"
              type="number"
              min="1"
              max="720"
              :aria-label="t('recruitment.lifetimeHours')"
            />
            <AppButton type="submit" variant="ghost" :disabled="inviteForm.processing">
              {{ t('recruitment.issue') }}
            </AppButton>
          </div>
          <div
            v-if="issuedApplicationLink"
            class="mt-4 rounded-[var(--ks-radius-md)] border border-[rgba(73,201,140,.28)] bg-[rgba(73,201,140,.07)] p-4"
            role="status"
          >
            <p class="ks-kicker">{{ t('recruitment.issuedLink') }}</p>
            <a
              class="mt-2 block text-sm break-all text-[#a7e7c4] underline"
              :href="issuedApplicationLink"
              target="_blank"
              rel="noopener noreferrer"
            >
              {{ issuedApplicationLink }}
            </a>
          </div>
        </form>
      </section>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="questions-heading">
        <p class="ks-kicker">{{ t('recruitment.questions') }}</p>
        <h2 id="questions-heading" class="ks-display mt-1 text-xl font-semibold">
          {{ t('recruitment.addQuestion') }}
        </h2>

        <form class="mt-5 grid gap-3 sm:grid-cols-2" @submit.prevent="createQuestion">
          <div class="sm:col-span-2">
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="question-prompt"
              >{{ t('recruitment.prompt') }}</label
            >
            <input
              id="question-prompt"
              v-model="questionForm.prompt"
              class="ks-input mt-1.5"
              maxlength="240"
              required
            />
          </div>
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="question-type"
              >{{ t('recruitment.questionType') }}</label
            >
            <select id="question-type" v-model="questionForm.type" class="ks-input mt-1.5">
              <option v-for="type in questionTypes" :key="type" :value="type">
                {{ humanize(type) }}
              </option>
            </select>
          </div>
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="question-position"
              >{{ t('recruitment.position') }}</label
            >
            <input
              id="question-position"
              v-model.number="questionForm.position"
              class="ks-input mt-1.5"
              type="number"
              min="0"
              max="65535"
            />
          </div>
          <div class="sm:col-span-2">
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="question-help"
              >{{ t('recruitment.helpText') }}</label
            >
            <textarea
              id="question-help"
              v-model="questionForm.help_text"
              class="ks-input mt-1.5 min-h-20"
              maxlength="2000"
            />
          </div>
          <div class="sm:col-span-2">
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="question-options"
              >{{ t('recruitment.options') }}</label
            >
            <textarea
              id="question-options"
              v-model="questionOptions"
              class="ks-input mt-1.5 min-h-20"
            />
          </div>
          <label class="flex items-center gap-2 text-sm"
            ><input v-model="questionForm.required" type="checkbox" />{{
              t('recruitment.required')
            }}</label
          >
          <label class="flex items-center gap-2 text-sm"
            ><input v-model="questionForm.active" type="checkbox" />{{
              t('recruitment.active')
            }}</label
          >
          <AppButton class="sm:col-span-2" type="submit" :disabled="questionForm.processing">
            {{ t('recruitment.createQuestion') }}
          </AppButton>
        </form>

        <div v-if="questions.length" class="mt-6 space-y-3 border-t border-[var(--ks-border)] pt-5">
          <details
            v-for="question in questions"
            :key="question.id"
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
          >
            <summary class="cursor-pointer list-none">
              <div class="flex items-center justify-between gap-3">
                <strong class="truncate">{{ question.prompt }}</strong>
                <span class="ks-status" :data-tone="question.active ? 'success' : 'warning'">
                  {{ question.active ? t('recruitment.active') : t('recruitment.no') }}
                </span>
              </div>
            </summary>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
              <input v-model="questionEdit(question.id).prompt" class="ks-input sm:col-span-2" />
              <select v-model="questionEdit(question.id).type" class="ks-input">
                <option v-for="type in questionTypes" :key="type" :value="type">
                  {{ humanize(type) }}
                </option>
              </select>
              <input
                v-model.number="questionEdit(question.id).position"
                class="ks-input"
                type="number"
                min="0"
                max="65535"
              />
              <textarea
                v-model="questionEdit(question.id).helpText"
                class="ks-input min-h-16 sm:col-span-2"
              />
              <textarea
                v-model="questionEdit(question.id).optionsText"
                class="ks-input min-h-16 sm:col-span-2"
              />
              <label class="flex items-center gap-2 text-sm"
                ><input v-model="questionEdit(question.id).required" type="checkbox" />{{
                  t('recruitment.required')
                }}</label
              >
              <label class="flex items-center gap-2 text-sm"
                ><input v-model="questionEdit(question.id).active" type="checkbox" />{{
                  t('recruitment.active')
                }}</label
              >
            </div>
            <AppButton class="mt-3" variant="secondary" @click="saveQuestion(question.id)">
              {{ t('recruitment.saveQuestion') }}
            </AppButton>
          </details>
        </div>
      </section>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="templates-heading">
        <p class="ks-kicker">{{ t('recruitment.decisionTemplates') }}</p>
        <h2 id="templates-heading" class="ks-display mt-1 text-xl font-semibold">
          {{ t('recruitment.communications') }}
        </h2>
        <p class="mt-2 text-xs text-[var(--ks-muted)]">
          {{
            t('recruitment.placeholders', {
              candidate: candidatePlaceholder,
              alliance: alliancePlaceholder,
            })
          }}
        </p>
        <form class="mt-5 space-y-3" @submit.prevent="createDecisionTemplate">
          <input
            v-model="decisionForm.name"
            class="ks-input"
            :placeholder="t('recruitment.templateName')"
            maxlength="120"
            required
          />
          <select v-model="decisionForm.decision_stage" class="ks-input">
            <option value="accepted">{{ t('recruitment.accepted') }}</option>
            <option value="declined">{{ humanize('declined') }}</option>
          </select>
          <input
            v-model="decisionForm.subject"
            class="ks-input"
            :placeholder="t('recruitment.subject')"
            maxlength="200"
            required
          />
          <textarea
            v-model="decisionForm.body"
            class="ks-input min-h-28"
            :placeholder="t('recruitment.body')"
            maxlength="10000"
            required
          />
          <label class="flex items-center gap-2 text-sm"
            ><input v-model="decisionForm.active" type="checkbox" />{{
              t('recruitment.active')
            }}</label
          >
          <AppButton type="submit" :disabled="decisionForm.processing">{{
            t('recruitment.createTemplate')
          }}</AppButton>
        </form>
        <div
          v-if="decisionTemplates.length"
          class="mt-5 space-y-2 border-t border-[var(--ks-border)] pt-4"
        >
          <article
            v-for="template in decisionTemplates"
            :key="template.id"
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-3 text-sm"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <strong>{{ template.name }}</strong
              ><span class="ks-status" :data-tone="template.active ? 'success' : 'warning'">{{
                template.active ? t('recruitment.active') : t('recruitment.no')
              }}</span>
            </div>
            <p class="mt-1 text-[var(--ks-text-secondary)]">{{ template.subject }}</p>
          </article>
        </div>
      </section>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="onboarding-heading">
        <p class="ks-kicker">{{ t('recruitment.onboarding') }}</p>
        <h2 id="onboarding-heading" class="ks-display mt-1 text-xl font-semibold">
          {{ t('recruitment.onboardingProgress') }}
        </h2>
        <form class="mt-5 space-y-3" @submit.prevent="createOnboardingItem">
          <input
            v-model="onboardingForm.name"
            class="ks-input"
            :placeholder="t('recruitment.itemName')"
            maxlength="160"
            required
          />
          <textarea
            v-model="onboardingForm.description"
            class="ks-input min-h-24"
            :placeholder="t('recruitment.description')"
            maxlength="5000"
          />
          <input
            v-model.number="onboardingForm.position"
            class="ks-input"
            type="number"
            min="0"
            max="65535"
            :aria-label="t('recruitment.position')"
          />
          <div class="flex flex-wrap gap-5">
            <label class="flex items-center gap-2 text-sm"
              ><input v-model="onboardingForm.required" type="checkbox" />{{
                t('recruitment.required')
              }}</label
            >
            <label class="flex items-center gap-2 text-sm"
              ><input v-model="onboardingForm.active" type="checkbox" />{{
                t('recruitment.active')
              }}</label
            >
          </div>
          <AppButton type="submit" :disabled="onboardingForm.processing">{{
            t('recruitment.createItem')
          }}</AppButton>
        </form>
        <div
          v-if="onboardingItems.length"
          class="mt-5 space-y-2 border-t border-[var(--ks-border)] pt-4"
        >
          <article
            v-for="item in onboardingItems"
            :key="item.id"
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-3 text-sm"
          >
            <div class="flex items-center justify-between gap-3">
              <strong>{{ item.name }}</strong
              ><span class="text-xs text-[var(--ks-muted)]">#{{ item.position }}</span>
            </div>
            <p v-if="item.description" class="mt-1 text-[var(--ks-text-secondary)]">
              {{ item.description }}
            </p>
          </article>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
