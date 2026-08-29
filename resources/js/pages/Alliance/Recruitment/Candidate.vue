<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

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
  transferCampaign: {
    available: boolean;
    unavailableReason?: 'transfer_not_authorized';
    recruitment: { stage: string; submittedAt: string; nextActionAt: string | null };
    playerLink: 'linked' | 'unlinked';
    communications: { total: number; latestStatus: string | null; latestAt: string | null };
    membership: {
      status: string | null;
      rank: string | null;
      joinedAt: string | null;
      rosterState: string | null;
      rosterObservedAt: string | null;
    } | null;
    transfer: {
      participantId: string;
      planId: string;
      planLabel: string;
      planState: string;
      direction: string;
      readiness: string;
      sourceKingdom: number | null;
      destinationKingdom: number | null;
      window: {
        label: string;
        phase: string;
        endsAt: string;
        sourceType: string;
        sourceReference: string;
        observedAt: string;
        evidenceId: string | null;
      };
      eligibility: {
        outcome: string;
        evaluatedAt: string;
        primaryAction: string | null;
        requirements: Array<{
          key: string;
          state: string;
          explanation: string;
          sourceType: string | null;
          sourceReference: string | null;
          observedAt: string | null;
          validUntil: string | null;
        }>;
      } | null;
      evidence: Array<{
        id: string;
        kind: string;
        sourceType: string;
        sourceReference: string;
        observedAt: string;
        validUntil: string | null;
        evidenceId: string | null;
      }>;
      activeBlockers: Array<{ id: string; summary: string }>;
      completion: { completedAt: string; rosterEntryId: string | null } | null;
      withdrawnAt: string | null;
    } | null;
    ownerHrefs: { recruitment: string; transfer: string; roster: string };
  };
}>();

const { t, formatDate, formatNumber } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
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
    { preserveScroll: true, onSuccess: () => reviewerForm.reset() },
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
  requestConfirmation({
    id: 'candidate-merge-confirmation',
    title: t('recruitment.duplicateCandidates'),
    description: t('recruitment.mergeConfirm'),
    confirmLabel: t('recruitment.mergeInto'),
    cancelLabel: t('common.cancel'),
    perform: (finish) =>
      mergeReason.post(`/alliance/recruitment/${props.candidate.id}/merge/${targetId}`, {
        onFinish: finish,
      }),
  });
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
  conversionForm.post(`/alliance/recruitment/${props.candidate.id}/convert`, {
    preserveScroll: true,
  });
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
function stageTone(stage: string): 'success' | 'warning' | 'danger' | 'info' {
  if (stage === 'accepted' || stage === 'joined') return 'success';
  if (stage === 'declined' || stage === 'withdrawn') return 'danger';
  if (stage === 'reviewing' || stage === 'interview') return 'info';
  return 'warning';
}
function onboardingTone(status: string): 'success' | 'warning' | 'danger' | 'info' {
  if (status === 'completed') return 'success';
  if (status === 'blocked') return 'danger';
  if (status === 'in_progress') return 'info';
  return 'warning';
}
function campaignTone(status: string | null): 'success' | 'warning' | 'danger' | 'info' {
  if (status === 'eligible_now' || status === 'completed' || status === 'active') return 'success';
  if (status === 'blocked' || status === 'failed' || status === 'withdrawn') return 'danger';
  if (status === 'needs_verification' || status === 'stale' || status === 'conflicting') {
    return 'warning';
  }
  return 'info';
}
function humanize(value: string): string {
  return value.replaceAll('_', ' ');
}
</script>

<template>
  <Head :title="`${candidate.name} · ${t('recruitment.title')}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('recruitment.candidateRecord')"
      :title="candidate.name"
      :subtitle="`${candidate.email}${candidate.contactHandle ? ` · ${candidate.contactHandle}` : ''}`"
      image="/images/kingshot/v4/recruitment-hall.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/recruitment" class="ks-command-link">
          ← {{ t('recruitment.backToPipeline') }}
        </Link>
      </template>
      <template #aside>
        <span class="ks-status" :data-tone="stageTone(candidate.stage)">{{
          humanize(candidate.stage)
        }}</span>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <StatSeal
        :label="t('recruitment.source')"
        :value="candidate.source || t('recruitment.unspecified')"
        icon="◇"
      />
      <StatSeal
        :label="t('recruitment.submitted')"
        :value="date(candidate.submittedAt)"
        icon="◷"
        tone="stone"
      />
      <StatSeal
        :label="t('recruitment.firstResponse')"
        :value="date(candidate.firstRespondedAt)"
        icon="✉"
        tone="teal"
      />
      <StatSeal
        :label="t('recruitment.nextAction')"
        :value="date(candidate.nextActionAt)"
        icon="→"
      />
    </section>

    <div
      v-if="issuedMembershipInvitationLink"
      class="mt-5 rounded-[var(--ks-radius-md)] border border-emerald-400/25 bg-emerald-500/[.07] p-4"
      role="status"
    >
      <p class="ks-kicker text-emerald-200">{{ t('recruitment.issuedLink') }}</p>
      <a
        class="mt-2 block text-sm break-all text-emerald-100 underline"
        :href="issuedMembershipInvitationLink"
        target="_blank"
        rel="noopener noreferrer"
      >
        {{ issuedMembershipInvitationLink }}
      </a>
    </div>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(20rem,.55fr)_minmax(0,1.45fr)]">
      <aside class="space-y-5">
        <section
          class="ks-surface p-5 2xl:sticky 2xl:top-[6.5rem]"
          aria-labelledby="candidate-actions-heading"
        >
          <p class="ks-kicker">{{ t('recruitment.stageNextAction') }}</p>
          <h2 id="candidate-actions-heading" class="ks-display mt-1 text-xl font-semibold">
            {{ humanize(candidate.stage) }}
          </h2>

          <form v-if="stageOptions.length" class="mt-5 space-y-3" @submit.prevent="updateStage">
            <div>
              <label class="text-xs font-semibold" for="candidate-stage">{{
                t('recruitment.moveTo')
              }}</label>
              <select id="candidate-stage" v-model="stageForm.stage" class="ks-input mt-1.5">
                <option v-for="stage in stageOptions" :key="stage" :value="stage">
                  {{ humanize(stage) }}
                </option>
              </select>
            </div>
            <div>
              <label class="text-xs font-semibold" for="candidate-next-action">{{
                t('recruitment.nextAction')
              }}</label>
              <input
                id="candidate-next-action"
                v-model="stageForm.next_action_at"
                class="ks-input mt-1.5"
                type="datetime-local"
              />
            </div>
            <div>
              <label class="text-xs font-semibold" for="candidate-stage-reason">{{
                t('recruitment.reason')
              }}</label>
              <textarea
                id="candidate-stage-reason"
                v-model="stageForm.reason"
                class="ks-input mt-1.5 min-h-20"
                maxlength="5000"
              />
            </div>
            <AppButton class="w-full" type="submit" :disabled="stageForm.processing">{{
              t('recruitment.updateStage')
            }}</AppButton>
          </form>

          <div class="ks-divider my-5" />

          <form class="space-y-3" @submit.prevent="assignReviewer">
            <p class="ks-kicker">{{ t('recruitment.reviewers') }}</p>
            <select v-model="reviewerForm.player_id" class="ks-input">
              <option value="">{{ t('recruitment.selectReviewer') }}</option>
              <option v-for="member in members" :key="member.id" :value="member.id">
                {{ member.name }} · {{ member.rank.toUpperCase() }}
              </option>
            </select>
            <AppButton
              class="w-full"
              variant="ghost"
              type="submit"
              :disabled="reviewerForm.processing || !reviewerForm.player_id"
            >
              {{ t('recruitment.assignReviewer') }}
            </AppButton>
            <div v-if="reviewers.length" class="flex flex-wrap gap-2">
              <span v-for="reviewer in reviewers" :key="reviewer.id" class="ks-chip">{{
                reviewer.name
              }}</span>
            </div>
          </form>

          <template v-if="conversionPlayers.length">
            <div class="ks-divider my-5" />
            <form class="space-y-3" @submit.prevent="convertCandidate">
              <p class="ks-kicker">{{ t('recruitment.convertCandidate') }}</p>
              <select v-model="conversionForm.player_id" class="ks-input">
                <option value="">{{ t('recruitment.selectPlayer') }}</option>
                <option v-for="player in conversionPlayers" :key="player.id" :value="player.id">
                  {{ player.name }}{{ player.claimed ? ` · ${t('recruitment.claimed')}` : '' }}
                </option>
              </select>
              <AppButton
                class="w-full"
                type="submit"
                :disabled="conversionForm.processing || !conversionForm.player_id"
              >
                {{ t('recruitment.convertCandidate') }}
              </AppButton>
            </form>
          </template>
        </section>
      </aside>

      <div class="min-w-0 space-y-5">
        <section class="ks-surface-gold p-5 sm:p-6" aria-labelledby="transfer-campaign-heading">
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('recruitment.transferCampaign.eyebrow') }}</p>
              <h2 id="transfer-campaign-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{ t('recruitment.transferCampaign.title') }}
              </h2>
            </div>
            <Link :href="transferCampaign.ownerHrefs.transfer" class="ks-command-link">
              {{ t('recruitment.transferCampaign.openTransfer') }}
            </Link>
          </div>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('recruitment.transferCampaign.help') }}
          </p>

          <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4">
              <p class="text-xs text-[var(--ks-muted)]">
                {{ t('recruitment.transferCampaign.recruitment') }}
              </p>
              <strong class="mt-1 block">{{ humanize(transferCampaign.recruitment.stage) }}</strong>
              <span class="mt-1 block text-xs text-[var(--ks-muted)]">
                {{ date(transferCampaign.recruitment.nextActionAt) }}
              </span>
            </div>
            <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4">
              <p class="text-xs text-[var(--ks-muted)]">
                {{ t('recruitment.transferCampaign.governor') }}
              </p>
              <strong class="mt-1 block">{{
                t(`recruitment.transferCampaign.player.${transferCampaign.playerLink}`)
              }}</strong>
            </div>
            <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4">
              <p class="text-xs text-[var(--ks-muted)]">
                {{ t('recruitment.transferCampaign.eligibility') }}
              </p>
              <strong class="mt-1 block">
                {{
                  transferCampaign.transfer?.eligibility
                    ? humanize(transferCampaign.transfer.eligibility.outcome)
                    : t('recruitment.transferCampaign.notAssessed')
                }}
              </strong>
            </div>
            <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4">
              <p class="text-xs text-[var(--ks-muted)]">
                {{ t('recruitment.transferCampaign.arrival') }}
              </p>
              <strong class="mt-1 block">
                {{
                  transferCampaign.membership?.status
                    ? humanize(transferCampaign.membership.status)
                    : t('recruitment.transferCampaign.notRecorded')
                }}
              </strong>
              <span class="mt-1 block text-xs text-[var(--ks-muted)]">
                {{
                  transferCampaign.membership?.rosterObservedAt
                    ? date(transferCampaign.membership.rosterObservedAt)
                    : t('recruitment.transferCampaign.noRosterObservation')
                }}
              </span>
            </div>
          </div>

          <div v-if="transferCampaign.transfer" class="mt-4 grid gap-4 lg:grid-cols-2">
            <article class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="ks-kicker">{{ transferCampaign.transfer.planLabel }}</p>
                  <h3 class="mt-1 font-semibold">{{ transferCampaign.transfer.window.label }}</h3>
                </div>
                <span
                  class="ks-status"
                  :data-tone="campaignTone(transferCampaign.transfer.eligibility?.outcome ?? null)"
                >
                  {{ humanize(transferCampaign.transfer.readiness) }}
                </span>
              </div>
              <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt class="text-xs text-[var(--ks-muted)]">
                    {{ t('recruitment.transferCampaign.kingdoms') }}
                  </dt>
                  <dd class="mt-1">
                    {{ transferCampaign.transfer.sourceKingdom ?? '—' }} →
                    {{ transferCampaign.transfer.destinationKingdom ?? '—' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-xs text-[var(--ks-muted)]">
                    {{ t('recruitment.transferCampaign.windowPhase') }}
                  </dt>
                  <dd class="mt-1">{{ humanize(transferCampaign.transfer.window.phase) }}</dd>
                </div>
              </dl>
              <p class="mt-3 text-xs leading-5 text-[var(--ks-muted)]">
                {{
                  t('recruitment.transferCampaign.provenance', {
                    source: transferCampaign.transfer.window.sourceType,
                    observedAt: date(transferCampaign.transfer.window.observedAt),
                  })
                }}
              </p>
            </article>
            <article class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] p-4">
              <p class="ks-kicker">{{ t('recruitment.transferCampaign.ownerFacts') }}</p>
              <ul class="mt-3 space-y-2 text-sm">
                <li>
                  {{
                    t('recruitment.transferCampaign.evidenceCount', {
                      count: formatNumber(transferCampaign.transfer.evidence.length),
                    })
                  }}
                </li>
                <li>
                  {{
                    t('recruitment.transferCampaign.blockerCount', {
                      count: formatNumber(transferCampaign.transfer.activeBlockers.length),
                    })
                  }}
                </li>
                <li>
                  {{
                    t('recruitment.transferCampaign.communicationCount', {
                      count: formatNumber(transferCampaign.communications.total),
                    })
                  }}
                </li>
                <li>
                  {{
                    transferCampaign.transfer.completion
                      ? t('recruitment.transferCampaign.completedAt', {
                          date: date(transferCampaign.transfer.completion.completedAt),
                        })
                      : t('recruitment.transferCampaign.notCompleted')
                  }}
                </li>
              </ul>
            </article>
          </div>
          <div v-else class="ks-fantasy-empty mt-4">
            {{
              transferCampaign.playerLink === 'unlinked'
                ? t('recruitment.transferCampaign.linkGovernorHelp')
                : t('recruitment.transferCampaign.noParticipant')
            }}
          </div>
        </section>

        <section class="ks-surface p-5 sm:p-6" aria-labelledby="answers-heading">
          <div class="flex items-end justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('recruitment.answers') }}</p>
              <h2 id="answers-heading" class="ks-display mt-1 text-2xl font-semibold">
                {{ t('recruitment.application') }}
              </h2>
            </div>
            <span class="ks-chip" data-active="true">{{ formatNumber(answers.length) }}</span>
          </div>
          <dl v-if="answers.length" class="mt-5 grid gap-3 md:grid-cols-2">
            <div
              v-for="answer in answers"
              :key="answer.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <dt class="text-xs font-semibold text-[var(--ks-muted)]">{{ answer.prompt }}</dt>
              <dd
                class="mt-2 text-sm leading-6 whitespace-pre-line text-[var(--ks-text-secondary)]"
              >
                {{ displayAnswer(answer.answer) }}
              </dd>
            </div>
          </dl>
          <div v-else class="ks-fantasy-empty mt-4">{{ t('recruitment.noAnswers') }}</div>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
          <section class="ks-surface p-5" aria-labelledby="notes-heading">
            <p class="ks-kicker">{{ t('recruitment.notes') }}</p>
            <h2 id="notes-heading" class="ks-display mt-1 text-xl font-semibold">
              {{ t('recruitment.internalNotes') }}
            </h2>
            <form class="mt-4 space-y-3" @submit.prevent="addNote">
              <textarea
                v-model="noteForm.body"
                class="ks-input min-h-24"
                maxlength="5000"
                required
              />
              <AppButton type="submit" variant="ghost" :disabled="noteForm.processing">{{
                t('recruitment.addNote')
              }}</AppButton>
            </form>
            <div v-if="notes.length" class="mt-5 space-y-2 border-t border-[var(--ks-border)] pt-4">
              <article
                v-for="note in notes"
                :key="note.id"
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3"
              >
                <p class="text-sm leading-6 whitespace-pre-line">{{ note.body }}</p>
                <p class="mt-2 text-xs text-[var(--ks-muted)]">
                  {{ note.author }} · {{ date(note.createdAt) }}
                </p>
              </article>
            </div>
          </section>

          <section class="ks-surface p-5" aria-labelledby="tags-heading">
            <p class="ks-kicker">{{ t('recruitment.tags') }}</p>
            <h2 id="tags-heading" class="ks-display mt-1 text-xl font-semibold">
              {{ t('recruitment.tags') }}
            </h2>
            <form class="mt-4 grid grid-cols-[1fr_auto] gap-2" @submit.prevent="addTag">
              <input v-model="tagForm.name" class="ks-input" maxlength="80" required />
              <AppButton type="submit" variant="ghost" :disabled="tagForm.processing">{{
                t('recruitment.addTag')
              }}</AppButton>
            </form>
            <div v-if="tags.length" class="mt-4 flex flex-wrap gap-2">
              <span v-for="tag in tags" :key="tag.id" class="ks-chip">{{ tag.name }}</span>
            </div>
            <div v-else class="ks-fantasy-empty mt-4">{{ t('recruitment.noTags') }}</div>
          </section>
        </div>

        <section class="ks-surface p-5 sm:p-6" aria-labelledby="communications-heading">
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('recruitment.communications') }}</p>
              <h2 id="communications-heading" class="ks-display mt-1 text-xl font-semibold">
                {{ t('recruitment.decisionTemplates') }}
              </h2>
            </div>
            <span class="ks-chip">{{ communications.length }}</span>
          </div>
          <form
            v-if="decisionTemplates.length"
            class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto]"
            @submit.prevent="prepareCommunication"
          >
            <select v-model="communicationForm.template_id" class="ks-input">
              <option value="">{{ t('recruitment.selectTemplate') }}</option>
              <option v-for="template in decisionTemplates" :key="template.id" :value="template.id">
                {{ template.name }} · {{ humanize(template.decisionStage) }}
              </option>
            </select>
            <AppButton
              type="submit"
              :disabled="communicationForm.processing || !communicationForm.template_id"
              >{{ t('recruitment.prepareCommunication') }}</AppButton
            >
          </form>
          <div v-if="communications.length" class="mt-5 grid gap-3 md:grid-cols-2">
            <article
              v-for="communication in communications"
              :key="communication.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <strong class="block truncate">{{ communication.subject }}</strong>
                  <p class="mt-1 text-xs text-[var(--ks-muted)]">
                    {{ date(communication.sentAt ?? communication.createdAt) }}
                  </p>
                </div>
                <span
                  class="ks-status"
                  :data-tone="communication.status === 'sent' ? 'success' : 'warning'"
                  >{{ humanize(communication.status) }}</span
                >
              </div>
              <p
                class="mt-3 line-clamp-4 text-sm leading-6 whitespace-pre-line text-[var(--ks-text-secondary)]"
              >
                {{ communication.body }}
              </p>
              <button
                v-if="communication.status !== 'sent'"
                type="button"
                class="ks-command-button mt-3 w-full"
                data-variant="secondary"
                @click="markCommunicationSent(communication.id)"
              >
                {{ t('recruitment.markSent') }}
              </button>
            </article>
          </div>
          <div v-else class="ks-fantasy-empty mt-4">{{ t('recruitment.noCommunications') }}</div>
        </section>

        <section class="ks-surface p-5 sm:p-6" aria-labelledby="onboarding-heading">
          <div class="flex items-end justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ t('recruitment.onboarding') }}</p>
              <h2 id="onboarding-heading" class="ks-display mt-1 text-xl font-semibold">
                {{ t('recruitment.onboardingProgress') }}
              </h2>
            </div>
            <span class="ks-chip">{{ onboarding.length }}</span>
          </div>
          <div v-if="onboarding.length" class="mt-4 grid gap-3 md:grid-cols-2">
            <article
              v-for="item in onboarding"
              :key="item.id"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h3 class="font-semibold">{{ item.name }}</h3>
                  <p v-if="item.description" class="mt-1 text-xs leading-5 text-[var(--ks-muted)]">
                    {{ item.description }}
                  </p>
                </div>
                <span class="ks-status" :data-tone="onboardingTone(item.status)">{{
                  humanize(item.status)
                }}</span>
              </div>
              <select
                class="ks-input mt-4"
                :value="item.status"
                @change="updateOnboardingFromEvent(item.id, $event)"
              >
                <option v-for="status in onboardingStatusOptions" :key="status" :value="status">
                  {{ humanize(status) }}
                </option>
              </select>
            </article>
          </div>
          <div v-else class="ks-fantasy-empty mt-4">{{ t('recruitment.noOnboarding') }}</div>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
          <section class="ks-surface p-5" aria-labelledby="history-heading">
            <p class="ks-kicker">{{ t('recruitment.stageHistory') }}</p>
            <h2 id="history-heading" class="sr-only">{{ t('recruitment.stageHistory') }}</h2>
            <ol v-if="history.length" class="mt-4 space-y-4">
              <li
                v-for="entry in history"
                :key="entry.id"
                class="relative border-s border-[var(--ks-border-strong)] ps-5"
              >
                <span
                  class="absolute -start-1.5 top-1 h-3 w-3 rounded-full border border-[var(--ks-gold-dark)] bg-[var(--ks-teal)]"
                  aria-hidden="true"
                />
                <div class="flex flex-wrap items-center gap-2 text-sm">
                  <span v-if="entry.from" class="text-[var(--ks-muted)]">{{
                    humanize(entry.from)
                  }}</span
                  ><span v-if="entry.from">→</span><strong>{{ humanize(entry.to) }}</strong>
                </div>
                <p class="mt-1 text-xs text-[var(--ks-muted)]">{{ date(entry.changedAt) }}</p>
                <p v-if="entry.reason" class="mt-2 text-sm text-[var(--ks-text-secondary)]">
                  {{ entry.reason }}
                </p>
              </li>
            </ol>
            <div v-else class="ks-fantasy-empty mt-4">{{ t('recruitment.noHistory') }}</div>
          </section>

          <section class="ks-surface p-5" aria-labelledby="duplicates-heading">
            <p class="ks-kicker">{{ t('recruitment.duplicateCandidates') }}</p>
            <h2 id="duplicates-heading" class="sr-only">
              {{ t('recruitment.duplicateCandidates') }}
            </h2>
            <p class="mt-2 text-xs leading-5 text-[var(--ks-muted)]">
              {{ t('recruitment.duplicateHelp') }}
            </p>
            <template v-if="duplicates.length">
              <textarea
                v-model="mergeReason.reason"
                class="ks-input mt-4 min-h-20"
                maxlength="5000"
                :placeholder="t('recruitment.mergeReason')"
              />
              <div class="mt-4 space-y-2">
                <article
                  v-for="duplicate in duplicates"
                  :key="duplicate.id"
                  class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-3"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="truncate font-semibold">{{ duplicate.name }}</p>
                      <p class="mt-1 truncate text-xs text-[var(--ks-muted)]">
                        {{ duplicate.email }} · {{ date(duplicate.submittedAt) }}
                      </p>
                    </div>
                    <span class="ks-status" :data-tone="stageTone(duplicate.stage)">{{
                      humanize(duplicate.stage)
                    }}</span>
                  </div>
                  <AppButton
                    class="mt-3"
                    variant="danger"
                    type="button"
                    @click="mergeInto(duplicate.id)"
                    >{{ t('recruitment.mergeInto') }}</AppButton
                  >
                </article>
              </div>
            </template>
            <div v-else class="ks-fantasy-empty mt-4">{{ t('recruitment.noDuplicates') }}</div>
          </section>
        </div>
      </div>
    </div>
    <ConfirmActionDialog v-bind="dialog" @confirm="confirmAction" @cancel="cancelConfirmation" />
  </AppLayout>
</template>
