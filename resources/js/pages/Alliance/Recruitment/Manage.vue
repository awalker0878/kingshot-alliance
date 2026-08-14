<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

import AppLayout from '../../../layouts/AppLayout.vue';
import { useLocale } from '../../../localization';

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

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; slug: string };
  settings: {
    mode: string;
    title: string;
    introduction: string | null;
    retentionDays: number;
    open: boolean;
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
  candidates: Candidate[];
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
  issuedApplicationLink: string | null;
}>();

const { t, formatDate, formatNumber } = useLocale();

const settingsForm = useForm({
  mode: props.settings?.mode ?? 'public',
  title: props.settings?.title ?? 'Join our alliance',
  introduction: props.settings?.introduction ?? '',
  retention_days: props.settings?.retentionDays ?? 90,
  open: props.settings?.open ?? true,
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

function date(value: string | null): string {
  return value ? formatDate(value) : '—';
}

function percentage(value: number): string {
  return `${Math.round(value * 100)}%`;
}

function stageTone(stage: string): string {
  if (stage === 'accepted' || stage === 'joined')
    return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (stage === 'declined' || stage === 'withdrawn')
    return 'border-red-400/25 bg-red-500/10 text-red-200';
  if (stage === 'reviewing' || stage === 'interview')
    return 'border-blue-400/25 bg-blue-500/10 text-blue-200';
  return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
}
</script>

<template>
  <Head :title="`${t('recruitment.title')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div class="max-w-3xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('recruitment.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">{{ t('recruitment.title') }}</h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('recruitment.subtitle', { alliance: alliance.name }) }}
        </p>
      </div>
      <a
        v-if="settings?.mode === 'public' && settings.open"
        class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-gold)]/45 bg-[var(--ks-gold-soft)] px-4 py-2 text-sm font-semibold text-[var(--ks-gold-strong)] transition hover:border-[var(--ks-gold)] hover:text-white"
        :href="`/alliances/${alliance.slug}/apply`"
        target="_blank"
        rel="noopener noreferrer"
      >
        {{ t('recruitment.publicForm') }}
      </a>
    </header>

    <section class="ks-surface-gold mt-6 overflow-hidden" :aria-label="t('recruitment.title')">
      <div
        class="grid grid-cols-2 divide-x divide-y divide-[var(--ks-border)] md:grid-cols-5 md:divide-y-0"
      >
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('recruitment.candidates') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold">{{ formatNumber(metrics.total) }}</p>
        </article>
        <article class="p-4 sm:p-5">
          <p
            class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('recruitment.averageResponse') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold">
            {{ metrics.averageResponseHours ?? '—'
            }}<span v-if="metrics.averageResponseHours !== null" class="ms-1 text-sm">{{
              t('recruitment.hours')
            }}</span>
          </p>
        </article>
        <article class="p-4 sm:p-5">
          <p class="text-[0.68rem] font-bold tracking-[0.1em] text-green-300 uppercase">
            {{ t('recruitment.accepted') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold">
            {{ percentage(metrics.acceptedRate) }}
          </p>
        </article>
        <article class="p-4 sm:p-5">
          <p class="text-[0.68rem] font-bold tracking-[0.1em] text-blue-300 uppercase">
            {{ t('recruitment.joined') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold">{{ percentage(metrics.joinedRate) }}</p>
        </article>
        <article class="col-span-2 p-4 sm:p-5 md:col-span-1">
          <p
            class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('recruitment.sources') }}
          </p>
          <p class="ks-display mt-2 text-3xl font-semibold">
            {{ formatNumber(Object.keys(metrics.bySource).length) }}
          </p>
        </article>
      </div>
    </section>

    <section class="ks-surface mt-5 overflow-hidden" aria-labelledby="pipeline-heading">
      <div class="border-b border-[var(--ks-border)] p-4 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">
              {{ t('recruitment.privateRecruiterView') }}
            </p>
            <h2 id="pipeline-heading" class="ks-display mt-1 text-2xl font-semibold">
              {{ t('recruitment.pipeline') }}
            </h2>
          </div>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="stage in candidateStages"
              :key="stage"
              :class="stageTone(stage)"
              class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
            >
              {{ stage }} {{ metrics.byStage[stage] ?? 0 }}
            </span>
          </div>
        </div>
      </div>

      <div v-if="candidates.length" class="lg:hidden">
        <article
          v-for="candidate in candidates"
          :key="candidate.id"
          class="border-b border-[var(--ks-border)] p-4 last:border-b-0"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <Link
                class="block truncate font-semibold text-[var(--ks-blue-strong)] hover:text-white"
                :href="`/alliance/recruitment/${candidate.id}`"
              >
                {{ candidate.name }}
              </Link>
              <p class="mt-1 truncate text-xs text-[var(--ks-text-muted)]">{{ candidate.email }}</p>
              <p class="mt-2 text-xs text-[var(--ks-text-secondary)]">
                {{ candidate.source || t('recruitment.unspecified') }}
              </p>
            </div>
            <span
              :class="stageTone(candidate.stage)"
              class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
              >{{ candidate.stage }}</span
            >
          </div>
          <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
            <div>
              <dt class="text-[var(--ks-text-muted)]">{{ t('recruitment.submitted') }}</dt>
              <dd class="mt-1 text-[var(--ks-text-secondary)]">
                {{ date(candidate.submittedAt) }}
              </dd>
            </div>
            <div>
              <dt class="text-[var(--ks-text-muted)]">{{ t('recruitment.nextAction') }}</dt>
              <dd class="mt-1 text-[var(--ks-text-secondary)]">
                {{ date(candidate.nextActionAt) }}
              </dd>
            </div>
          </dl>
        </article>
      </div>

      <div v-if="candidates.length" class="hidden overflow-x-auto lg:block">
        <table class="w-full min-w-[60rem] text-sm">
          <thead
            class="bg-black/25 text-[0.68rem] font-bold tracking-[0.08em] text-[var(--ks-text-muted)] uppercase"
          >
            <tr>
              <th class="px-4 py-3 text-start">{{ t('recruitment.candidate') }}</th>
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
              class="transition hover:bg-white/[0.025]"
            >
              <td class="px-4 py-3.5">
                <Link
                  class="font-semibold text-[var(--ks-blue-strong)] hover:text-white"
                  :href="`/alliance/recruitment/${candidate.id}`"
                  >{{ candidate.name }}</Link
                >
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">{{ candidate.email }}</p>
              </td>
              <td class="px-4 py-3.5">
                <span
                  :class="stageTone(candidate.stage)"
                  class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                  >{{ candidate.stage }}</span
                >
              </td>
              <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                {{ candidate.source || t('recruitment.unspecified') }}
              </td>
              <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                {{ date(candidate.submittedAt) }}
              </td>
              <td class="px-4 py-3.5 text-[var(--ks-text-secondary)]">
                {{ date(candidate.nextActionAt) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="!candidates.length" class="p-8 text-center text-sm text-[var(--ks-text-muted)]">
        {{ t('recruitment.noCandidates') }}
      </p>
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
      <section class="ks-surface p-5 sm:p-6" aria-labelledby="settings-heading">
        <h2 id="settings-heading" class="ks-display text-xl font-semibold">
          {{ t('recruitment.settings') }}
        </h2>
        <form class="mt-5 space-y-4" @submit.prevent="saveSettings">
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="recruitment-mode"
              >{{ t('recruitment.applicationMode') }}</label
            >
            <select
              id="recruitment-mode"
              v-model="settingsForm.mode"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
            >
              <option v-for="mode in applicationModes" :key="mode" :value="mode" class="capitalize">
                {{ mode }}
              </option>
            </select>
          </div>
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="recruitment-title"
              >{{ t('recruitment.publicTitle') }}</label
            >
            <input
              id="recruitment-title"
              v-model="settingsForm.title"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              maxlength="160"
              required
            />
          </div>
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="recruitment-introduction"
              >{{ t('recruitment.introduction') }}</label
            >
            <textarea
              id="recruitment-introduction"
              v-model="settingsForm.introduction"
              class="mt-1.5 min-h-28 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              maxlength="5000"
            />
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label
                class="text-xs font-semibold text-[var(--ks-text-secondary)]"
                for="retention-days"
                >{{ t('recruitment.retentionDays') }}</label
              >
              <input
                id="retention-days"
                v-model.number="settingsForm.retention_days"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                type="number"
                min="1"
                max="3650"
                required
              />
            </div>
            <label class="flex items-center gap-2 pt-6 text-sm text-[var(--ks-text-secondary)]"
              ><input v-model="settingsForm.open" type="checkbox" />
              {{ t('recruitment.applicationsOpen') }}</label
            >
          </div>
          <button
            class="min-h-10 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white"
            type="submit"
          >
            {{ t('recruitment.saveSettings') }}
          </button>
        </form>

        <form class="mt-6 border-t border-[var(--ks-border)] pt-5" @submit.prevent="issueInvite">
          <h3 class="font-semibold">{{ t('recruitment.inviteLink') }}</h3>
          <p class="mt-1 text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{ t('recruitment.inviteHelp') }}
          </p>
          <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_8rem_auto]">
            <input
              v-model="inviteForm.email"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              type="email"
              :placeholder="t('recruitment.optionalEmail')"
            />
            <input
              v-model.number="inviteForm.ttl_hours"
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              type="number"
              min="1"
              max="720"
              :aria-label="t('recruitment.lifetimeHours')"
            />
            <button
              class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold"
              type="submit"
            >
              {{ t('recruitment.issue') }}
            </button>
          </div>
          <div
            v-if="issuedApplicationLink"
            class="mt-4 rounded-[var(--ks-radius-sm)] border border-green-400/25 bg-green-500/10 p-4 text-sm text-green-200"
            role="status"
          >
            <p class="font-semibold">{{ t('recruitment.issuedLink') }}</p>
            <a
              class="mt-2 block break-all underline"
              :href="issuedApplicationLink"
              target="_blank"
              rel="noopener noreferrer"
              >{{ issuedApplicationLink }}</a
            >
          </div>
        </form>
      </section>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="questions-heading">
        <h2 id="questions-heading" class="ks-display text-xl font-semibold">
          {{ t('recruitment.questions') }}
        </h2>
        <form class="mt-5 grid gap-3 sm:grid-cols-2" @submit.prevent="createQuestion">
          <div class="sm:col-span-2">
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="question-prompt"
              >{{ t('recruitment.prompt') }}</label
            ><input
              id="question-prompt"
              v-model="questionForm.prompt"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              maxlength="240"
              required
            />
          </div>
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="question-type"
              >{{ t('recruitment.questionType') }}</label
            ><select
              id="question-type"
              v-model="questionForm.type"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
            >
              <option v-for="type in questionTypes" :key="type" :value="type">{{ type }}</option>
            </select>
          </div>
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="question-position"
              >{{ t('recruitment.position') }}</label
            ><input
              id="question-position"
              v-model.number="questionForm.position"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
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
            ><textarea
              id="question-help"
              v-model="questionForm.help_text"
              class="mt-1.5 min-h-20 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              maxlength="2000"
            />
          </div>
          <div class="sm:col-span-2">
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="question-options"
              >{{ t('recruitment.options') }}</label
            ><textarea
              id="question-options"
              v-model="questionOptions"
              class="mt-1.5 min-h-20 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
            />
          </div>
          <label class="flex items-center gap-2 text-sm"
            ><input v-model="questionForm.required" type="checkbox" />
            {{ t('recruitment.required') }}</label
          >
          <label class="flex items-center gap-2 text-sm"
            ><input v-model="questionForm.active" type="checkbox" />
            {{ t('recruitment.active') }}</label
          >
          <button
            class="min-h-10 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white sm:col-span-2"
            type="submit"
          >
            {{ t('recruitment.createQuestion') }}
          </button>
        </form>

        <div v-if="questions.length" class="mt-6 space-y-3 border-t border-[var(--ks-border)] pt-5">
          <article
            v-for="question in questions"
            :key="question.id"
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
          >
            <div class="grid gap-3 sm:grid-cols-2">
              <input
                v-model="questionEdit(question.id).prompt"
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 text-sm sm:col-span-2"
              />
              <select
                v-model="questionEdit(question.id).type"
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 text-sm"
              >
                <option v-for="type in questionTypes" :key="type" :value="type">{{ type }}</option>
              </select>
              <input
                v-model.number="questionEdit(question.id).position"
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 text-sm"
                type="number"
                min="0"
                max="65535"
              />
              <textarea
                v-model="questionEdit(question.id).helpText"
                class="min-h-16 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 text-sm sm:col-span-2"
              />
              <textarea
                v-model="questionEdit(question.id).optionsText"
                class="min-h-16 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 text-sm sm:col-span-2"
              />
              <label class="flex items-center gap-2 text-sm"
                ><input v-model="questionEdit(question.id).required" type="checkbox" />
                {{ t('recruitment.required') }}</label
              >
              <label class="flex items-center gap-2 text-sm"
                ><input v-model="questionEdit(question.id).active" type="checkbox" />
                {{ t('recruitment.active') }}</label
              >
            </div>
            <button
              class="mt-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-gold)]/45 bg-[var(--ks-gold-soft)] px-3 py-2 text-sm font-semibold text-[var(--ks-gold-strong)]"
              type="button"
              @click="saveQuestion(question.id)"
            >
              {{ t('recruitment.saveQuestion') }}
            </button>
          </article>
        </div>
      </section>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="templates-heading">
        <h2 id="templates-heading" class="ks-display text-xl font-semibold">
          {{ t('recruitment.decisionTemplates') }}
        </h2>
        <p class="mt-2 text-xs text-[var(--ks-text-muted)]">
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
            class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
            :placeholder="t('recruitment.templateName')"
            maxlength="120"
            required
          />
          <select
            v-model="decisionForm.decision_stage"
            class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
          >
            <option value="accepted">accepted</option>
            <option value="declined">declined</option>
          </select>
          <input
            v-model="decisionForm.subject"
            class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
            :placeholder="t('recruitment.subject')"
            maxlength="200"
            required
          />
          <textarea
            v-model="decisionForm.body"
            class="min-h-28 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
            :placeholder="t('recruitment.body')"
            maxlength="10000"
            required
          />
          <label class="flex items-center gap-2 text-sm"
            ><input v-model="decisionForm.active" type="checkbox" />
            {{ t('recruitment.active') }}</label
          >
          <button
            class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white"
            type="submit"
          >
            {{ t('recruitment.createTemplate') }}
          </button>
        </form>
        <div
          v-if="decisionTemplates.length"
          class="mt-5 space-y-2 border-t border-[var(--ks-border)] pt-4"
        >
          <div
            v-for="template in decisionTemplates"
            :key="template.id"
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3 text-sm"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <strong>{{ template.name }}</strong
              ><span class="text-xs text-[var(--ks-text-muted)] capitalize"
                >{{ template.decisionStage }} ·
                {{ template.active ? t('recruitment.active') : 'inactive' }}</span
              >
            </div>
            <p class="mt-1 text-[var(--ks-text-secondary)]">{{ template.subject }}</p>
          </div>
        </div>
      </section>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="onboarding-heading">
        <h2 id="onboarding-heading" class="ks-display text-xl font-semibold">
          {{ t('recruitment.onboarding') }}
        </h2>
        <form class="mt-5 space-y-3" @submit.prevent="createOnboardingItem">
          <input
            v-model="onboardingForm.name"
            class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
            :placeholder="t('recruitment.itemName')"
            maxlength="160"
            required
          />
          <textarea
            v-model="onboardingForm.description"
            class="min-h-24 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
            :placeholder="t('recruitment.description')"
            maxlength="5000"
          />
          <input
            v-model.number="onboardingForm.position"
            class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
            type="number"
            min="0"
            max="65535"
            :aria-label="t('recruitment.position')"
          />
          <div class="flex flex-wrap gap-5">
            <label class="flex items-center gap-2 text-sm"
              ><input v-model="onboardingForm.required" type="checkbox" />
              {{ t('recruitment.required') }}</label
            ><label class="flex items-center gap-2 text-sm"
              ><input v-model="onboardingForm.active" type="checkbox" />
              {{ t('recruitment.active') }}</label
            >
          </div>
          <button
            class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white"
            type="submit"
          >
            {{ t('recruitment.createItem') }}
          </button>
        </form>
        <div
          v-if="onboardingItems.length"
          class="mt-5 space-y-2 border-t border-[var(--ks-border)] pt-4"
        >
          <div
            v-for="item in onboardingItems"
            :key="item.id"
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3 text-sm"
          >
            <div class="flex items-center justify-between gap-3">
              <strong>{{ item.name }}</strong
              ><span class="text-xs text-[var(--ks-text-muted)]">#{{ item.position }}</span>
            </div>
            <p v-if="item.description" class="mt-1 text-[var(--ks-text-secondary)]">
              {{ item.description }}
            </p>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
