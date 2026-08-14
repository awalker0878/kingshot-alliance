<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

import AppLayout from '../../../layouts/AppLayout.vue';
import { useLocale } from '../../../localization';

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  candidate: {
    id: string;
    name: string;
    email: string;
    contactHandle: string | null;
    source: string | null;
    stage: string;
    submittedAt: string;
    firstRespondedAt: string | null;
    nextActionAt: string | null;
    acceptedAt: string | null;
    declinedAt: string | null;
    withdrawnAt: string | null;
    joinedAt: string | null;
    retentionDueAt: string | null;
    playerId: string | null;
    membershipInvitationId: string | null;
  };
  answers: Array<{ id: string; prompt: string; type: string; answer: Record<string, unknown> }>;
  reviewers: Array<{ id: string; name: string }>;
  notes: Array<{ id: string; body: string; author: string; createdAt: string | null }>;
  tags: Array<{ id: string; name: string }>;
  history: Array<{
    id: string;
    from: string | null;
    to: string;
    reason: string | null;
    changedAt: string;
  }>;
  communications: Array<{
    id: string;
    subject: string;
    body: string;
    status: string;
    sentAt: string | null;
    createdAt: string | null;
  }>;
  onboarding: Array<{
    id: string;
    name: string;
    description: string | null;
    required: boolean;
    status: string;
    completedAt: string | null;
  }>;
  duplicates: Array<{
    id: string;
    name: string;
    email: string;
    contactHandle: string | null;
    stage: string;
    submittedAt: string;
  }>;
  members: Array<{ id: string; name: string; rank: string }>;
  conversionPlayers: Array<{ id: string; name: string; claimed: boolean }>;
  decisionTemplates: Array<{ id: string; name: string; decisionStage: string; subject: string }>;
  stageOptions: string[];
  onboardingStatusOptions: string[];
  issuedMembershipInvitationLink: string | null;
}>();

const { t, formatDate } = useLocale();

const stageForm = useForm({
  stage: props.stageOptions[0] ?? props.candidate.stage,
  reason: '',
  next_action_at: '',
});
const reviewerForm = useForm({ player_id: '' });
const conversionForm = useForm({ player_id: props.candidate.playerId ?? '' });
const noteForm = useForm({ body: '' });
const tagForm = useForm({ name: '' });
const mergeReason = useForm({ reason: '' });
const communicationForm = useForm({ template_id: '' });

function updateStage(): void {
  stageForm.patch(`/alliance/recruitment/${props.candidate.id}/stage`, { preserveScroll: true });
}

function assignReviewer(): void {
  if (!reviewerForm.player_id) return;
  reviewerForm.put(
    `/alliance/recruitment/${props.candidate.id}/reviewers/${reviewerForm.player_id}`,
    {
      preserveScroll: true,
      onSuccess: () => reviewerForm.reset(),
    },
  );
}

function addNote(): void {
  noteForm.post(`/alliance/recruitment/${props.candidate.id}/notes`, {
    preserveScroll: true,
    onSuccess: () => noteForm.reset(),
  });
}

function addTag(): void {
  tagForm.put(`/alliance/recruitment/${props.candidate.id}/tags`, {
    preserveScroll: true,
    onSuccess: () => tagForm.reset(),
  });
}

function mergeInto(targetId: string): void {
  if (!window.confirm(t('recruitment.mergeConfirm'))) return;
  mergeReason.post(`/alliance/recruitment/${props.candidate.id}/merge/${targetId}`);
}

function prepareCommunication(): void {
  if (!communicationForm.template_id) return;
  communicationForm.post(
    `/alliance/recruitment/${props.candidate.id}/communications/${communicationForm.template_id}`,
    { preserveScroll: true, onSuccess: () => communicationForm.reset() },
  );
}

function markCommunicationSent(id: string): void {
  router.patch(`/alliance/recruitment/communications/${id}/sent`, {}, { preserveScroll: true });
}

function convertCandidate(): void {
  if (!conversionForm.player_id) return;
  conversionForm.post(`/alliance/recruitment/${props.candidate.id}/convert`, { preserveScroll: true });
}

function updateOnboarding(id: string, status: string): void {
  router.patch(`/alliance/recruitment/onboarding/${id}`, { status }, { preserveScroll: true });
}

function updateOnboardingFromEvent(id: string, event: Event): void {
  const target = event.target;
  if (target instanceof HTMLSelectElement) updateOnboarding(id, target.value);
}

function date(value: string | null): string {
  return value ? formatDate(value) : '—';
}

function displayAnswer(answer: Record<string, unknown>): string {
  const value = answer.value;
  if (typeof value === 'boolean') return value ? t('recruitment.yes') : t('recruitment.no');
  if (typeof value === 'string' || typeof value === 'number') return String(value);
  const values = answer.values;
  return Array.isArray(values) ? values.join(', ') : '—';
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

function onboardingTone(status: string): string {
  if (status === 'completed') return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (status === 'blocked') return 'border-red-400/25 bg-red-500/10 text-red-200';
  return 'border-amber-400/25 bg-amber-500/10 text-amber-200';
}
</script>

<template>
  <Head :title="`${candidate.name} · ${t('recruitment.title')}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div class="max-w-3xl min-w-0">
        <Link
          class="inline-flex min-h-10 items-center text-sm font-semibold text-[var(--ks-blue-strong)] hover:text-white"
          href="/alliance/recruitment"
        >
          ← {{ t('recruitment.backToPipeline') }}
        </Link>
        <p class="mt-4 text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('recruitment.candidateRecord') }}
        </p>
        <h1 class="ks-display mt-2 truncate text-3xl font-bold sm:text-4xl">
          {{ candidate.name }}
        </h1>
        <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">{{ candidate.email }}</p>
        <p v-if="candidate.contactHandle" class="mt-1 text-xs text-[var(--ks-text-muted)]">
          {{ t('recruitment.contact') }}: {{ candidate.contactHandle }}
        </p>
      </div>
      <span
        :class="stageTone(candidate.stage)"
        class="rounded-full border px-3 py-1.5 text-sm font-semibold capitalize"
        >{{ candidate.stage }}</span
      >
    </header>

    <section class="ks-surface-gold mt-6 overflow-hidden">
      <dl
        class="grid grid-cols-2 divide-x divide-y divide-[var(--ks-border)] md:grid-cols-4 md:divide-y-0"
      >
        <div class="p-4 sm:p-5">
          <dt
            class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('recruitment.source') }}
          </dt>
          <dd class="mt-2 font-semibold">{{ candidate.source || t('recruitment.unspecified') }}</dd>
        </div>
        <div class="p-4 sm:p-5">
          <dt
            class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('recruitment.submitted') }}
          </dt>
          <dd class="mt-2 text-sm font-semibold">{{ date(candidate.submittedAt) }}</dd>
        </div>
        <div class="p-4 sm:p-5">
          <dt
            class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('recruitment.firstResponse') }}
          </dt>
          <dd class="mt-2 text-sm font-semibold">{{ date(candidate.firstRespondedAt) }}</dd>
        </div>
        <div class="p-4 sm:p-5">
          <dt
            class="text-[0.68rem] font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase"
          >
            {{ t('recruitment.nextAction') }}
          </dt>
          <dd class="mt-2 text-sm font-semibold">{{ date(candidate.nextActionAt) }}</dd>
        </div>
      </dl>
    </section>

    <div
      v-if="issuedMembershipInvitationLink"
      class="mt-4 rounded-[var(--ks-radius-md)] border border-green-400/25 bg-green-500/10 p-4 text-sm text-green-200"
      role="status"
    >
      <a
        class="block break-all underline"
        :href="issuedMembershipInvitationLink"
        target="_blank"
        rel="noopener noreferrer"
        >{{ issuedMembershipInvitationLink }}</a
      >
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-3">
      <section
        class="ks-surface p-5 sm:p-6 xl:sticky xl:top-24 xl:col-span-1 xl:self-start"
        aria-labelledby="stage-heading"
      >
        <h2 id="stage-heading" class="ks-display text-xl font-semibold">
          {{ t('recruitment.stageNextAction') }}
        </h2>
        <form v-if="stageOptions.length" class="mt-5 space-y-4" @submit.prevent="updateStage">
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="candidate-stage"
              >{{ t('recruitment.moveTo') }}</label
            >
            <select
              id="candidate-stage"
              v-model="stageForm.stage"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
            >
              <option v-for="stage in stageOptions" :key="stage" :value="stage" class="capitalize">
                {{ stage }}
              </option>
            </select>
          </div>
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="candidate-next-action"
              >{{ t('recruitment.nextAction') }}</label
            >
            <input
              id="candidate-next-action"
              v-model="stageForm.next_action_at"
              class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              type="datetime-local"
            />
          </div>
          <div>
            <label
              class="text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="candidate-stage-reason"
              >{{ t('recruitment.internalReason') }}</label
            >
            <textarea
              id="candidate-stage-reason"
              v-model="stageForm.reason"
              class="mt-1.5 min-h-24 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              maxlength="5000"
            />
          </div>
          <button
            class="min-h-10 w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white"
            type="submit"
          >
            {{ t('recruitment.updateStage') }}
          </button>
        </form>
        <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">
          {{ t('recruitment.noTransitions') }}
        </p>

        <div
          v-if="candidate.stage === 'accepted'"
          class="mt-5 border-t border-[var(--ks-border)] pt-5"
        >
          <select
            v-if="!candidate.membershipInvitationId"
            v-model="conversionForm.player_id"
            class="mb-3 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
            :aria-label="t('recruitment.choosePlayer')"
          >
            <option value="">{{ t('recruitment.choosePlayer') }}</option>
            <option v-for="player in conversionPlayers" :key="player.id" :value="player.id">
              {{ player.name }}
            </option>
          </select>
          <button
            class="w-full rounded-[var(--ks-radius-sm)] border border-green-400/25 bg-green-500/10 px-4 py-2 text-sm font-semibold text-green-200 disabled:cursor-not-allowed disabled:opacity-50"
            type="button"
            :disabled="!candidate.membershipInvitationId && !conversionForm.player_id"
            @click="convertCandidate"
          >
            {{
              candidate.membershipInvitationId
                ? t('recruitment.existingInvitation')
                : t('recruitment.createMembershipInvitation')
            }}
          </button>
          <p class="mt-2 text-xs leading-5 text-[var(--ks-text-muted)]">
            {{ t('recruitment.conversionHelp') }}
          </p>
        </div>
        <p v-if="candidate.retentionDueAt" class="mt-4 text-xs text-amber-300">
          {{ t('recruitment.retentionDue', { date: date(candidate.retentionDueAt) }) }}
        </p>
      </section>

      <div class="min-w-0 space-y-5 xl:col-span-2">
        <section class="ks-surface p-5 sm:p-6" aria-labelledby="answers-heading">
          <h2 id="answers-heading" class="ks-display text-xl font-semibold">
            {{ t('recruitment.answers') }}
          </h2>
          <dl v-if="answers.length" class="mt-4 grid gap-3 md:grid-cols-2">
            <div
              v-for="answer in answers"
              :key="answer.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <dt class="font-semibold">{{ answer.prompt }}</dt>
              <dd class="mt-2 text-sm whitespace-pre-line text-[var(--ks-text-secondary)]">
                {{ displayAnswer(answer.answer) }}
              </dd>
            </div>
          </dl>
          <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">
            {{ t('recruitment.noAnswers') }}
          </p>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
          <section class="ks-surface p-5" aria-labelledby="review-heading">
            <h2 id="review-heading" class="ks-display text-xl font-semibold">
              {{ t('recruitment.reviewersTags') }}
            </h2>
            <div class="mt-4 flex flex-wrap gap-2">
              <span
                v-for="reviewer in reviewers"
                :key="reviewer.id"
                class="rounded-full border border-blue-400/20 bg-blue-500/10 px-3 py-1 text-sm text-blue-200"
                >{{ reviewer.name }}</span
              >
              <span v-if="!reviewers.length" class="text-sm text-[var(--ks-text-muted)]">{{
                t('recruitment.noReviewer')
              }}</span>
            </div>
            <form class="mt-4 flex gap-2" @submit.prevent="assignReviewer">
              <select
                v-model="reviewerForm.player_id"
                class="min-w-0 flex-1 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                :aria-label="t('recruitment.chooseReviewer')"
              >
                <option value="">{{ t('recruitment.chooseReviewer') }}</option>
                <option v-for="member in members" :key="member.id" :value="member.id">
                  {{ member.name }} · {{ member.rank.toUpperCase() }}
                </option>
              </select>
              <button
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
                type="submit"
              >
                {{ t('recruitment.assign') }}
              </button>
            </form>
            <div class="mt-5 flex flex-wrap gap-2">
              <span
                v-for="tagItem in tags"
                :key="tagItem.id"
                class="rounded-full border border-[var(--ks-border)] px-3 py-1 text-sm"
                >{{ tagItem.name }}</span
              >
            </div>
            <form class="mt-3 flex gap-2" @submit.prevent="addTag">
              <label class="sr-only" for="candidate-tag">{{ t('recruitment.addTag') }}</label>
              <input
                id="candidate-tag"
                v-model="tagForm.name"
                class="min-w-0 flex-1 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                maxlength="80"
                :placeholder="t('recruitment.addTag')"
              />
              <button
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
                type="submit"
              >
                {{ t('recruitment.tag') }}
              </button>
            </form>
          </section>

          <section class="ks-surface p-5" aria-labelledby="notes-heading">
            <h2 id="notes-heading" class="ks-display text-xl font-semibold">
              {{ t('recruitment.privateNotes') }}
            </h2>
            <form class="mt-4" @submit.prevent="addNote">
              <label class="sr-only" for="new-recruitment-note">{{
                t('recruitment.newPrivateNote')
              }}</label>
              <textarea
                id="new-recruitment-note"
                v-model="noteForm.body"
                class="min-h-28 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
                maxlength="10000"
                :placeholder="t('recruitment.notePlaceholder')"
                required
              />
              <button
                class="mt-2 rounded-[var(--ks-radius-sm)] border border-[var(--ks-gold)]/45 bg-[var(--ks-gold-soft)] px-3 py-2 text-sm font-semibold text-[var(--ks-gold-strong)]"
                type="submit"
              >
                {{ t('recruitment.addNote') }}
              </button>
            </form>
            <div v-if="notes.length" class="mt-5 max-h-80 space-y-3 overflow-y-auto">
              <article
                v-for="note in notes"
                :key="note.id"
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3"
              >
                <p class="text-sm whitespace-pre-line text-[var(--ks-text-secondary)]">
                  {{ note.body }}
                </p>
                <p class="mt-2 text-xs text-[var(--ks-text-muted)]">
                  {{ note.author }} · {{ date(note.createdAt) }}
                </p>
              </article>
            </div>
          </section>
        </div>

        <section class="ks-surface p-5 sm:p-6" aria-labelledby="communications-heading">
          <h2 id="communications-heading" class="ks-display text-xl font-semibold">
            {{ t('recruitment.communications') }}
          </h2>
          <form
            v-if="decisionTemplates.length"
            class="mt-4 flex flex-col gap-3 sm:flex-row"
            @submit.prevent="prepareCommunication"
          >
            <select
              v-model="communicationForm.template_id"
              class="min-w-0 flex-1 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              :aria-label="t('recruitment.chooseTemplate')"
            >
              <option value="">{{ t('recruitment.chooseTemplate') }}</option>
              <option v-for="template in decisionTemplates" :key="template.id" :value="template.id">
                {{ template.name }} · {{ template.subject }}
              </option>
            </select>
            <button
              class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white"
              type="submit"
            >
              {{ t('recruitment.prepareCommunication') }}
            </button>
          </form>
          <div v-if="communications.length" class="mt-5 space-y-3">
            <article
              v-for="communication in communications"
              :key="communication.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h3 class="font-semibold">{{ communication.subject }}</h3>
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                    {{ communication.status }} ·
                    {{ date(communication.sentAt ?? communication.createdAt) }}
                  </p>
                </div>
                <button
                  v-if="communication.status !== 'sent'"
                  class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-1.5 text-xs font-semibold"
                  type="button"
                  @click="markCommunicationSent(communication.id)"
                >
                  {{ t('recruitment.markSent') }}
                </button>
              </div>
              <p class="mt-3 text-sm whitespace-pre-line text-[var(--ks-text-secondary)]">
                {{ communication.body }}
              </p>
            </article>
          </div>
          <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">
            {{ t('recruitment.noCommunications') }}
          </p>
        </section>

        <section class="ks-surface p-5 sm:p-6" aria-labelledby="onboarding-heading">
          <h2 id="onboarding-heading" class="ks-display text-xl font-semibold">
            {{ t('recruitment.onboardingProgress') }}
          </h2>
          <div v-if="onboarding.length" class="mt-4 grid gap-3 md:grid-cols-2">
            <article
              v-for="item in onboarding"
              :key="item.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h3 class="font-semibold">{{ item.name }}</h3>
                  <p
                    v-if="item.description"
                    class="mt-1 text-xs leading-5 text-[var(--ks-text-muted)]"
                  >
                    {{ item.description }}
                  </p>
                </div>
                <span
                  :class="onboardingTone(item.status)"
                  class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                  >{{ item.status }}</span
                >
              </div>
              <label
                class="mt-4 block text-xs font-semibold text-[var(--ks-text-secondary)]"
                :for="`onboarding-${item.id}`"
                >{{ t('recruitment.stage') }}</label
              >
              <select
                :id="`onboarding-${item.id}`"
                class="mt-1.5 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 text-sm"
                :value="item.status"
                @change="updateOnboardingFromEvent(item.id, $event)"
              >
                <option v-for="status in onboardingStatusOptions" :key="status" :value="status">
                  {{ status }}
                </option>
              </select>
            </article>
          </div>
          <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">
            {{ t('recruitment.noOnboarding') }}
          </p>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
          <section class="ks-surface p-5" aria-labelledby="history-heading">
            <h2 id="history-heading" class="ks-display text-xl font-semibold">
              {{ t('recruitment.stageHistory') }}
            </h2>
            <ol v-if="history.length" class="mt-4 space-y-3">
              <li
                v-for="entry in history"
                :key="entry.id"
                class="border-s-2 border-[var(--ks-border-strong)] ps-4"
              >
                <div class="flex flex-wrap items-center gap-2 text-sm">
                  <span v-if="entry.from" class="text-[var(--ks-text-muted)] capitalize">{{
                    entry.from
                  }}</span
                  ><span v-if="entry.from">→</span
                  ><strong class="capitalize">{{ entry.to }}</strong>
                </div>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">{{ date(entry.changedAt) }}</p>
                <p v-if="entry.reason" class="mt-2 text-sm text-[var(--ks-text-secondary)]">
                  {{ entry.reason }}
                </p>
              </li>
            </ol>
            <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">
              {{ t('recruitment.noHistory') }}
            </p>
          </section>

          <section class="ks-surface p-5" aria-labelledby="duplicates-heading">
            <h2 id="duplicates-heading" class="ks-display text-xl font-semibold">
              {{ t('recruitment.duplicateCandidates') }}
            </h2>
            <p class="mt-2 text-xs leading-5 text-[var(--ks-text-muted)]">
              {{ t('recruitment.duplicateHelp') }}
            </p>
            <label
              v-if="duplicates.length"
              class="mt-4 block text-xs font-semibold text-[var(--ks-text-secondary)]"
              for="merge-reason"
              >{{ t('recruitment.mergeReason') }}</label
            >
            <textarea
              v-if="duplicates.length"
              id="merge-reason"
              v-model="mergeReason.reason"
              class="mt-1.5 min-h-20 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 text-sm"
              maxlength="5000"
            />
            <div v-if="duplicates.length" class="mt-4 space-y-3">
              <article
                v-for="duplicate in duplicates"
                :key="duplicate.id"
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="truncate font-semibold">{{ duplicate.name }}</p>
                    <p class="mt-1 truncate text-xs text-[var(--ks-text-muted)]">
                      {{ duplicate.email }}
                    </p>
                    <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                      {{ date(duplicate.submittedAt) }}
                    </p>
                  </div>
                  <span
                    :class="stageTone(duplicate.stage)"
                    class="rounded-full border px-2 py-1 text-xs capitalize"
                    >{{ duplicate.stage }}</span
                  >
                </div>
                <button
                  class="mt-3 rounded-[var(--ks-radius-sm)] border border-red-400/20 bg-red-500/5 px-3 py-1.5 text-xs font-semibold text-red-300"
                  type="button"
                  @click="mergeInto(duplicate.id)"
                >
                  {{ t('recruitment.mergeInto') }}
                </button>
              </article>
            </div>
            <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">
              {{ t('recruitment.noDuplicates') }}
            </p>
          </section>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
