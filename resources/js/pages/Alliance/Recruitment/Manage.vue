<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

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
  members: Array<{ id: string; name: string }>;
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

function formatDate(value: string | null): string {
  if (!value) return '—';
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(
    new Date(value),
  );
}

function percentage(value: number): string {
  return `${Math.round(value * 100)}%`;
}
</script>

<template>
  <Head :title="`${alliance.name} recruitment`" />

  <main class="mx-auto min-h-screen max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/alliance">
          ← Alliance home
        </Link>
        <h1 class="mt-4 text-3xl font-bold sm:text-4xl">Recruitment</h1>
        <p class="mt-2 max-w-3xl text-slate-400">
          Manage applications, candidate progress, onboarding, and retention for
          {{ alliance.name }}.
        </p>
      </div>
      <a
        v-if="settings?.mode === 'public' && settings.open"
        class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold hover:border-cyan-400"
        :href="`/alliances/${alliance.slug}/apply`"
        target="_blank"
        rel="noopener noreferrer"
      >
        Open public form
      </a>
    </div>

    <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="Recruitment metrics">
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
        <p class="text-sm text-slate-400">Candidates</p>
        <p class="mt-1 text-2xl font-bold">{{ metrics.total }}</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
        <p class="text-sm text-slate-400">Average response</p>
        <p class="mt-1 text-2xl font-bold">
          {{ metrics.averageResponseHours ?? '—'
          }}<span v-if="metrics.averageResponseHours !== null" class="text-sm"> h</span>
        </p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
        <p class="text-sm text-slate-400">Accepted</p>
        <p class="mt-1 text-2xl font-bold">{{ percentage(metrics.acceptedRate) }}</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
        <p class="text-sm text-slate-400">Joined</p>
        <p class="mt-1 text-2xl font-bold">{{ percentage(metrics.joinedRate) }}</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
        <p class="text-sm text-slate-400">Sources</p>
        <p class="mt-1 text-sm font-semibold">{{ Object.keys(metrics.bySource).length }}</p>
      </div>
    </section>

    <section
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-5 sm:p-6"
      aria-labelledby="pipeline-heading"
    >
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs font-semibold tracking-[0.16em] text-cyan-300 uppercase">
            Private recruiter view
          </p>
          <h2 id="pipeline-heading" class="mt-1 text-2xl font-semibold">Candidate pipeline</h2>
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
          <span
            v-for="stage in candidateStages"
            :key="stage"
            class="rounded-full bg-slate-800 px-2.5 py-1 capitalize"
          >
            {{ stage }} {{ metrics.byStage[stage] ?? 0 }}
          </span>
        </div>
      </div>

      <div v-if="candidates.length" class="mt-5 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="text-slate-400">
            <tr>
              <th class="pr-5 pb-3 font-medium">Candidate</th>
              <th class="pr-5 pb-3 font-medium">Stage</th>
              <th class="pr-5 pb-3 font-medium">Source</th>
              <th class="pr-5 pb-3 font-medium">Submitted</th>
              <th class="pb-3 font-medium">Next action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="candidate in candidates" :key="candidate.id">
              <td class="py-4 pr-5">
                <Link
                  class="font-semibold hover:text-cyan-200"
                  :href="`/alliance/recruitment/${candidate.id}`"
                >
                  {{ candidate.name }}
                </Link>
                <p class="mt-1 text-xs text-slate-500">{{ candidate.email }}</p>
              </td>
              <td class="py-4 pr-5 capitalize">{{ candidate.stage }}</td>
              <td class="py-4 pr-5">{{ candidate.source || 'Unspecified' }}</td>
              <td class="py-4 pr-5 text-slate-400">{{ formatDate(candidate.submittedAt) }}</td>
              <td class="py-4 text-slate-400">{{ formatDate(candidate.nextActionAt) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="mt-5 text-sm text-slate-500">No active recruitment candidates yet.</p>
    </section>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
      <section
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 sm:p-6"
        aria-labelledby="application-settings-heading"
      >
        <h2 id="application-settings-heading" class="text-xl font-semibold">
          Application settings
        </h2>
        <form class="mt-5 space-y-4" @submit.prevent="saveSettings">
          <div>
            <label class="text-sm font-medium" for="recruitment-mode">Application mode</label>
            <select
              id="recruitment-mode"
              v-model="settingsForm.mode"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            >
              <option v-for="mode in applicationModes" :key="mode" :value="mode" class="capitalize">
                {{ mode }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium" for="recruitment-title">Public title</label>
            <input
              id="recruitment-title"
              v-model="settingsForm.title"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              maxlength="160"
              required
            />
          </div>
          <div>
            <label class="text-sm font-medium" for="recruitment-introduction">Introduction</label>
            <textarea
              id="recruitment-introduction"
              v-model="settingsForm.introduction"
              class="mt-1 min-h-28 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              maxlength="5000"
            />
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="text-sm font-medium" for="retention-days"
                >Unsuccessful retention days</label
              >
              <input
                id="retention-days"
                v-model.number="settingsForm.retention_days"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                type="number"
                min="1"
                max="3650"
                required
              />
            </div>
            <label class="flex items-center gap-2 pt-7 text-sm">
              <input v-model="settingsForm.open" type="checkbox" /> Applications open
            </label>
          </div>
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950"
            type="submit"
          >
            Save settings
          </button>
        </form>

        <form class="mt-8 border-t border-slate-800 pt-6" @submit.prevent="issueInvite">
          <h3 class="font-semibold">Invitation-only application link</h3>
          <p class="mt-1 text-sm text-slate-400">
            Issue an expiring one-time application link, optionally restricted to one email.
          </p>
          <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_8rem_auto]">
            <input
              v-model="inviteForm.email"
              class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              type="email"
              placeholder="Optional email"
            />
            <input
              v-model.number="inviteForm.ttl_hours"
              class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              type="number"
              min="1"
              max="720"
              aria-label="Invitation lifetime in hours"
            />
            <button
              class="rounded-lg border border-slate-700 px-4 py-2 font-semibold"
              type="submit"
            >
              Issue
            </button>
          </div>
          <div
            v-if="issuedApplicationLink"
            class="mt-4 rounded-xl border border-emerald-800 bg-emerald-950/30 p-4 text-sm"
          >
            <p class="font-semibold text-emerald-100">New application link</p>
            <a
              class="mt-1 block break-all text-emerald-200 underline"
              :href="issuedApplicationLink"
              target="_blank"
              rel="noopener noreferrer"
              >{{ issuedApplicationLink }}</a
            >
          </div>
        </form>
      </section>

      <section
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 sm:p-6"
        aria-labelledby="questions-heading"
      >
        <h2 id="questions-heading" class="text-xl font-semibold">Application questions</h2>
        <div v-if="questions.length" class="mt-4 space-y-3">
          <article
            v-for="question in questions"
            :key="question.id"
            class="rounded-xl border border-slate-800 p-4"
          >
            <div class="grid gap-3">
              <div>
                <label
                  class="text-xs font-medium text-slate-400"
                  :for="`edit-question-${question.id}`"
                  >Prompt</label
                >
                <input
                  :id="`edit-question-${question.id}`"
                  v-model="questionEdit(question.id).prompt"
                  class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                  maxlength="240"
                  required
                />
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label
                    class="text-xs font-medium text-slate-400"
                    :for="`edit-type-${question.id}`"
                    >Type</label
                  >
                  <select
                    :id="`edit-type-${question.id}`"
                    v-model="questionEdit(question.id).type"
                    class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                  >
                    <option v-for="type in questionTypes" :key="type" :value="type">
                      {{ type.replace('_', ' ') }}
                    </option>
                  </select>
                </div>
                <div>
                  <label
                    class="text-xs font-medium text-slate-400"
                    :for="`edit-position-${question.id}`"
                    >Position</label
                  >
                  <input
                    :id="`edit-position-${question.id}`"
                    v-model.number="questionEdit(question.id).position"
                    class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                    type="number"
                    min="0"
                    max="65535"
                    required
                  />
                </div>
              </div>
              <div>
                <label class="text-xs font-medium text-slate-400" :for="`edit-help-${question.id}`"
                  >Help text</label
                >
                <input
                  :id="`edit-help-${question.id}`"
                  v-model="questionEdit(question.id).helpText"
                  class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                  maxlength="2000"
                />
              </div>
              <div v-if="['select', 'multi_select'].includes(questionEdit(question.id).type)">
                <label
                  class="text-xs font-medium text-slate-400"
                  :for="`edit-options-${question.id}`"
                  >Options, one per line</label
                >
                <textarea
                  :id="`edit-options-${question.id}`"
                  v-model="questionEdit(question.id).optionsText"
                  class="mt-1 min-h-24 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                />
              </div>
              <div class="flex flex-wrap items-center gap-5 text-sm">
                <label class="flex items-center gap-2"
                  ><input v-model="questionEdit(question.id).required" type="checkbox" />
                  Required</label
                >
                <label class="flex items-center gap-2"
                  ><input v-model="questionEdit(question.id).active" type="checkbox" />
                  Active</label
                >
                <button
                  class="rounded-lg border border-slate-700 px-3 py-2 font-semibold"
                  type="button"
                  @click="saveQuestion(question.id)"
                >
                  Save question
                </button>
              </div>
            </div>
          </article>
        </div>
        <form
          class="mt-6 space-y-4 border-t border-slate-800 pt-5"
          @submit.prevent="createQuestion"
        >
          <div>
            <label class="text-sm font-medium" for="question-prompt">New question</label>
            <input
              id="question-prompt"
              v-model="questionForm.prompt"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              maxlength="240"
              required
            />
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="text-sm font-medium" for="question-type">Type</label>
              <select
                id="question-type"
                v-model="questionForm.type"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              >
                <option v-for="type in questionTypes" :key="type" :value="type">
                  {{ type.replace('_', ' ') }}
                </option>
              </select>
            </div>
            <div>
              <label class="text-sm font-medium" for="question-position">Position</label>
              <input
                id="question-position"
                v-model.number="questionForm.position"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                type="number"
                min="0"
                max="65535"
              />
            </div>
          </div>
          <div>
            <label class="text-sm font-medium" for="question-help">Help text</label>
            <input
              id="question-help"
              v-model="questionForm.help_text"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              maxlength="2000"
            />
          </div>
          <div v-if="['select', 'multi_select'].includes(questionForm.type)">
            <label class="text-sm font-medium" for="question-options">Options, one per line</label>
            <textarea
              id="question-options"
              v-model="questionOptions"
              class="mt-1 min-h-28 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            />
          </div>
          <div class="flex flex-wrap gap-5 text-sm">
            <label class="flex items-center gap-2"
              ><input v-model="questionForm.required" type="checkbox" /> Required</label
            >
            <label class="flex items-center gap-2"
              ><input v-model="questionForm.active" type="checkbox" /> Active</label
            >
          </div>
          <button class="rounded-lg border border-slate-700 px-4 py-2 font-semibold" type="submit">
            Add question
          </button>
        </form>
      </section>

      <section
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 sm:p-6"
        aria-labelledby="decision-templates-heading"
      >
        <h2 id="decision-templates-heading" class="text-xl font-semibold">Decision templates</h2>
        <div v-if="decisionTemplates.length" class="mt-4 space-y-3">
          <article
            v-for="template in decisionTemplates"
            :key="template.id"
            class="rounded-xl border border-slate-800 p-4"
          >
            <p class="text-xs font-semibold text-slate-500 uppercase">
              {{ template.decisionStage }} · {{ template.active ? 'active' : 'inactive' }}
            </p>
            <h3 class="mt-1 font-semibold">{{ template.name }}</h3>
            <p class="mt-1 text-sm text-slate-400">{{ template.subject }}</p>
          </article>
        </div>
        <form
          class="mt-6 space-y-4 border-t border-slate-800 pt-5"
          @submit.prevent="createDecisionTemplate"
        >
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="text-sm font-medium" for="decision-name">Template name</label
              ><input
                id="decision-name"
                v-model="decisionForm.name"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                required
              />
            </div>
            <div>
              <label class="text-sm font-medium" for="decision-stage">Decision</label
              ><select
                id="decision-stage"
                v-model="decisionForm.decision_stage"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              >
                <option value="accepted">Accepted</option>
                <option value="declined">Declined</option>
              </select>
            </div>
          </div>
          <div>
            <label class="text-sm font-medium" for="decision-subject">Subject</label
            ><input
              id="decision-subject"
              v-model="decisionForm.subject"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              required
            />
          </div>
          <div>
            <label class="text-sm font-medium" for="decision-body">Message</label
            ><textarea
              id="decision-body"
              v-model="decisionForm.body"
              class="mt-1 min-h-32 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              required
            />
          </div>
          <p class="text-xs text-slate-500">
            Supported placeholders: <code>{{ candidatePlaceholder }}</code> and
            <code>{{ alliancePlaceholder }}</code
            >.
          </p>
          <label class="flex items-center gap-2 text-sm"
            ><input v-model="decisionForm.active" type="checkbox" /> Active</label
          >
          <button class="rounded-lg border border-slate-700 px-4 py-2 font-semibold" type="submit">
            Add template
          </button>
        </form>
      </section>

      <section
        class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 sm:p-6"
        aria-labelledby="onboarding-heading"
      >
        <h2 id="onboarding-heading" class="text-xl font-semibold">Onboarding checklist</h2>
        <div v-if="onboardingItems.length" class="mt-4 space-y-3">
          <article
            v-for="item in onboardingItems"
            :key="item.id"
            class="rounded-xl border border-slate-800 p-4"
          >
            <p class="text-xs text-slate-500">
              Position {{ item.position }} · {{ item.required ? 'required' : 'optional' }} ·
              {{ item.active ? 'active' : 'inactive' }}
            </p>
            <h3 class="mt-1 font-semibold">{{ item.name }}</h3>
            <p v-if="item.description" class="mt-1 text-sm text-slate-400">
              {{ item.description }}
            </p>
          </article>
        </div>
        <form
          class="mt-6 space-y-4 border-t border-slate-800 pt-5"
          @submit.prevent="createOnboardingItem"
        >
          <div>
            <label class="text-sm font-medium" for="onboarding-name">Item name</label
            ><input
              id="onboarding-name"
              v-model="onboardingForm.name"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              required
            />
          </div>
          <div>
            <label class="text-sm font-medium" for="onboarding-description">Description</label
            ><textarea
              id="onboarding-description"
              v-model="onboardingForm.description"
              class="mt-1 min-h-24 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            />
          </div>
          <div>
            <label class="text-sm font-medium" for="onboarding-position">Position</label
            ><input
              id="onboarding-position"
              v-model.number="onboardingForm.position"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              type="number"
              min="0"
              max="65535"
            />
          </div>
          <div class="flex flex-wrap gap-5 text-sm">
            <label class="flex items-center gap-2"
              ><input v-model="onboardingForm.required" type="checkbox" /> Required</label
            >
            <label class="flex items-center gap-2"
              ><input v-model="onboardingForm.active" type="checkbox" /> Active</label
            >
          </div>
          <button class="rounded-lg border border-slate-700 px-4 py-2 font-semibold" type="submit">
            Add onboarding item
          </button>
        </form>
      </section>
    </div>
  </main>
</template>
