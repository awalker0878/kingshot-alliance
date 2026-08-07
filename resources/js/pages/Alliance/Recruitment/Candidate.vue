<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{
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
    membershipInvitationId: string | null;
  };
  answers: Array<{ id: string; prompt: string; type: string; answer: Record<string, unknown> }>;
  reviewers: Array<{ id: string; name: string }>;
  notes: Array<{ id: string; body: string; author: string; createdAt: string | null }>;
  tags: Array<{ id: string; name: string }>;
  history: Array<{ id: string; from: string | null; to: string; reason: string | null; changedAt: string }>;
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
  members: Array<{ id: string; name: string }>;
  decisionTemplates: Array<{ id: string; name: string; decisionStage: string; subject: string }>;
  stageOptions: string[];
  onboardingStatusOptions: string[];
  issuedMembershipInvitationLink: string | null;
}>();

const stageForm = useForm({
  stage: props.stageOptions[0] ?? props.candidate.stage,
  reason: '',
  next_action_at: '',
});
const reviewerForm = useForm({ membership_id: '' });
const noteForm = useForm({ body: '' });
const tagForm = useForm({ name: '' });
const mergeReason = useForm({ reason: '' });
const communicationForm = useForm({ template_id: '' });

function updateStage(): void {
  stageForm.patch(`/alliance/recruitment/${props.candidate.id}/stage`, { preserveScroll: true });
}

function assignReviewer(): void {
  if (!reviewerForm.membership_id) return;
  reviewerForm.put(
    `/alliance/recruitment/${props.candidate.id}/reviewers/${reviewerForm.membership_id}`,
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
  if (!window.confirm('Merge this candidate into the selected record? The source record will be retained as merged provenance.')) return;
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
  router.post(`/alliance/recruitment/${props.candidate.id}/convert`, {}, { preserveScroll: true });
}

function updateOnboarding(id: string, status: string): void {
  router.patch(`/alliance/recruitment/onboarding/${id}`, { status }, { preserveScroll: true });
}

function formatDate(value: string | null): string {
  if (!value) return '—';
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function displayAnswer(answer: Record<string, unknown>): string {
  const value = answer.value;
  if (typeof value === 'boolean') return value ? 'Yes' : 'No';
  if (typeof value === 'string' || typeof value === 'number') return String(value);
  const values = answer.values;
  return Array.isArray(values) ? values.join(', ') : '—';
}
</script>

<template>
  <Head :title="`${candidate.name} · Recruitment`" />

  <main class="mx-auto min-h-screen max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
    <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/alliance/recruitment">
      ← Recruitment pipeline
    </Link>

    <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-5 sm:p-7">
      <div class="flex flex-wrap items-start justify-between gap-5">
        <div>
          <p class="text-xs font-semibold tracking-[0.16em] text-cyan-300 uppercase">Private candidate record</p>
          <h1 class="mt-2 text-3xl font-bold">{{ candidate.name }}</h1>
          <p class="mt-2 text-slate-300">{{ candidate.email }}</p>
          <p v-if="candidate.contactHandle" class="mt-1 text-sm text-slate-400">{{ candidate.contactHandle }}</p>
        </div>
        <span class="rounded-full bg-slate-800 px-3 py-1.5 text-sm font-semibold capitalize">{{ candidate.stage }}</span>
      </div>

      <dl class="mt-6 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-800 p-3"><dt class="text-slate-500">Source</dt><dd class="mt-1 font-semibold">{{ candidate.source || 'Unspecified' }}</dd></div>
        <div class="rounded-xl border border-slate-800 p-3"><dt class="text-slate-500">Submitted</dt><dd class="mt-1 font-semibold">{{ formatDate(candidate.submittedAt) }}</dd></div>
        <div class="rounded-xl border border-slate-800 p-3"><dt class="text-slate-500">First response</dt><dd class="mt-1 font-semibold">{{ formatDate(candidate.firstRespondedAt) }}</dd></div>
        <div class="rounded-xl border border-slate-800 p-3"><dt class="text-slate-500">Next action</dt><dd class="mt-1 font-semibold">{{ formatDate(candidate.nextActionAt) }}</dd></div>
      </dl>

      <div v-if="issuedMembershipInvitationLink" class="mt-5 rounded-xl border border-emerald-800 bg-emerald-950/30 p-4 text-sm">
        <p class="font-semibold text-emerald-100">Membership invitation created</p>
        <a class="mt-1 block break-all text-emerald-200 underline" :href="issuedMembershipInvitationLink" target="_blank" rel="noopener noreferrer">{{ issuedMembershipInvitationLink }}</a>
      </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5" aria-labelledby="stage-heading">
        <h2 id="stage-heading" class="text-xl font-semibold">Stage and next action</h2>
        <form v-if="stageOptions.length" class="mt-4 space-y-4" @submit.prevent="updateStage">
          <div><label class="text-sm font-medium" for="candidate-stage">Move to</label><select id="candidate-stage" v-model="stageForm.stage" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"><option v-for="stage in stageOptions" :key="stage" :value="stage" class="capitalize">{{ stage }}</option></select></div>
          <div><label class="text-sm font-medium" for="candidate-next-action">Next action</label><input id="candidate-next-action" v-model="stageForm.next_action_at" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" type="datetime-local" /></div>
          <div><label class="text-sm font-medium" for="candidate-stage-reason">Internal reason</label><textarea id="candidate-stage-reason" v-model="stageForm.reason" class="mt-1 min-h-24 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" maxlength="5000" /></div>
          <button class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950" type="submit">Update stage</button>
        </form>
        <p v-else class="mt-4 text-sm text-slate-500">No manual transitions are available from this stage.</p>
        <div v-if="candidate.stage === 'accepted'" class="mt-5 border-t border-slate-800 pt-5">
          <button class="rounded-lg border border-emerald-700 px-4 py-2 font-semibold text-emerald-200" type="button" @click="convertCandidate">
            {{ candidate.membershipInvitationId ? 'View existing invitation state' : 'Create membership invitation' }}
          </button>
          <p class="mt-2 text-xs text-slate-500">The candidate becomes joined only after the alliance invitation is actually accepted.</p>
        </div>
        <p v-if="candidate.retentionDueAt" class="mt-4 text-xs text-amber-300">Retention anonymization due {{ formatDate(candidate.retentionDueAt) }}</p>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5" aria-labelledby="answers-heading">
        <h2 id="answers-heading" class="text-xl font-semibold">Application answers</h2>
        <dl v-if="answers.length" class="mt-4 space-y-4">
          <div v-for="answer in answers" :key="answer.id" class="rounded-xl border border-slate-800 p-4">
            <dt class="font-medium">{{ answer.prompt }}</dt>
            <dd class="mt-2 whitespace-pre-line text-sm text-slate-300">{{ displayAnswer(answer.answer) }}</dd>
          </div>
        </dl>
        <p v-else class="mt-4 text-sm text-slate-500">No configurable answers were submitted.</p>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5" aria-labelledby="review-heading">
        <h2 id="review-heading" class="text-xl font-semibold">Review team and tags</h2>
        <div class="mt-4 flex flex-wrap gap-2">
          <span v-for="reviewer in reviewers" :key="reviewer.id" class="rounded-full bg-slate-800 px-3 py-1 text-sm">{{ reviewer.name }}</span>
          <span v-if="!reviewers.length" class="text-sm text-slate-500">No reviewer assigned.</span>
        </div>
        <form class="mt-4 flex gap-2" @submit.prevent="assignReviewer">
          <select v-model="reviewerForm.membership_id" class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" aria-label="Reviewer to assign"><option value="">Choose reviewer</option><option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }}</option></select>
          <button class="rounded-lg border border-slate-700 px-3 py-2 font-semibold" type="submit">Assign</button>
        </form>
        <div class="mt-5 flex flex-wrap gap-2"><span v-for="tag in tags" :key="tag.id" class="rounded-full border border-slate-700 px-3 py-1 text-sm">{{ tag.name }}</span></div>
        <form class="mt-3 flex gap-2" @submit.prevent="addTag"><input v-model="tagForm.name" class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" maxlength="80" placeholder="Add tag" /><button class="rounded-lg border border-slate-700 px-3 py-2 font-semibold" type="submit">Tag</button></form>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5" aria-labelledby="notes-heading">
        <h2 id="notes-heading" class="text-xl font-semibold">Private recruiter notes</h2>
        <form class="mt-4" @submit.prevent="addNote"><label class="sr-only" for="new-recruitment-note">New private note</label><textarea id="new-recruitment-note" v-model="noteForm.body" class="min-h-28 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" maxlength="10000" placeholder="Add internal context. This is never shown on the public application." required /><button class="mt-2 rounded-lg border border-slate-700 px-3 py-2 font-semibold" type="submit">Add note</button></form>
        <div v-if="notes.length" class="mt-5 space-y-3"><article v-for="note in notes" :key="note.id" class="rounded-xl border border-slate-800 p-4"><p class="whitespace-pre-line text-sm text-slate-200">{{ note.body }}</p><p class="mt-2 text-xs text-slate-500">{{ note.author }} · {{ formatDate(note.createdAt) }}</p></article></div>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5" aria-labelledby="duplicates-heading">
        <h2 id="duplicates-heading" class="text-xl font-semibold">Possible duplicates</h2>
        <p class="mt-1 text-sm text-slate-400">Exact normalized email or contact-handle matches within this alliance.</p>
        <div v-if="duplicates.length" class="mt-4 space-y-3">
          <article v-for="duplicate in duplicates" :key="duplicate.id" class="rounded-xl border border-amber-900/70 p-4">
            <div class="flex flex-wrap justify-between gap-3"><div><h3 class="font-semibold">{{ duplicate.name }}</h3><p class="text-sm text-slate-400">{{ duplicate.email }} · {{ duplicate.stage }}</p></div><button class="rounded-lg border border-amber-700 px-3 py-2 text-sm font-semibold text-amber-200" type="button" @click="mergeInto(duplicate.id)">Merge this record into duplicate</button></div>
          </article>
          <label class="mt-3 block text-sm font-medium" for="merge-reason">Merge reason</label><textarea id="merge-reason" v-model="mergeReason.reason" class="mt-1 min-h-20 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" maxlength="5000" />
        </div>
        <p v-else class="mt-4 text-sm text-slate-500">No exact duplicate signal found.</p>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5" aria-labelledby="communications-heading">
        <h2 id="communications-heading" class="text-xl font-semibold">Decision communications</h2>
        <form v-if="decisionTemplates.length" class="mt-4 flex gap-2" @submit.prevent="prepareCommunication"><select v-model="communicationForm.template_id" class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" aria-label="Decision template"><option value="">Choose template</option><option v-for="template in decisionTemplates" :key="template.id" :value="template.id">{{ template.name }}</option></select><button class="rounded-lg border border-slate-700 px-3 py-2 font-semibold" type="submit">Prepare</button></form>
        <p v-else class="mt-3 text-sm text-slate-500">Decision templates appear here only when they match the candidate's current accepted/declined stage.</p>
        <div v-if="communications.length" class="mt-5 space-y-4"><article v-for="communication in communications" :key="communication.id" class="rounded-xl border border-slate-800 p-4"><div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold">{{ communication.subject }}</h3><span class="text-xs font-semibold uppercase text-slate-500">{{ communication.status }}</span></div><textarea class="mt-3 min-h-28 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm" :value="communication.body" readonly aria-label="Prepared candidate communication" /><div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500"><span>Prepared {{ formatDate(communication.createdAt) }}<span v-if="communication.sentAt"> · sent {{ formatDate(communication.sentAt) }}</span></span><button v-if="communication.status !== 'sent'" class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-200" type="button" @click="markCommunicationSent(communication.id)">Mark sent through approved channel</button></div></article></div>
      </section>
    </div>

    <section v-if="onboarding.length" class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-5" aria-labelledby="candidate-onboarding-heading">
      <h2 id="candidate-onboarding-heading" class="text-xl font-semibold">Onboarding checklist</h2>
      <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <article v-for="item in onboarding" :key="item.id" class="rounded-xl border border-slate-800 p-4"><div class="flex flex-wrap justify-between gap-3"><div><h3 class="font-semibold">{{ item.name }}</h3><p v-if="item.description" class="mt-1 text-sm text-slate-400">{{ item.description }}</p><p class="mt-1 text-xs text-slate-500">{{ item.required ? 'Required' : 'Optional' }}<span v-if="item.completedAt"> · completed {{ formatDate(item.completedAt) }}</span></p></div><select :value="item.status" class="h-fit rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" :aria-label="`Onboarding status for ${item.name}`" @change="updateOnboarding(item.id, ($event.target as HTMLSelectElement).value)"><option v-for="status in onboardingStatusOptions" :key="status" :value="status">{{ status }}</option></select></div></article>
      </div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-5" aria-labelledby="history-heading">
      <h2 id="history-heading" class="text-xl font-semibold">Stage history</h2>
      <ol class="mt-4 space-y-3"><li v-for="entry in history" :key="entry.id" class="rounded-xl border border-slate-800 p-4"><p class="font-semibold capitalize">{{ entry.from ? `${entry.from} → ` : '' }}{{ entry.to }}</p><p v-if="entry.reason" class="mt-1 whitespace-pre-line text-sm text-slate-300">{{ entry.reason }}</p><p class="mt-2 text-xs text-slate-500">{{ formatDate(entry.changedAt) }}</p></li></ol>
    </section>
  </main>
</template>
