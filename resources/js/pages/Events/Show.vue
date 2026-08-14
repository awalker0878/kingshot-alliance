<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';


type Phase = { id: string; key: string; nameKey: string | null; name: string | null; type: string; startsAt: string | null; endsAt: string | null; status: string; storedStatus: string; sortOrder: number };
type PollOption = { id: string; label: string; value: string; metadata: Record<string, unknown>; votes: number | null };
type Poll = { id: string; key: string; type: 'choice' | 'time_vote'; questionKey: string | null; question: string | null; opensAt: string | null; closesAt: string | null; status: string; votingOpen: boolean; maxChoices: number; selectedOptionIds: string[]; settings: Record<string, unknown>; options: PollOption[] };

type RosterAssignment = {
  id: string; rosterId: string; rosterKey: string; rosterNameKey: string | null; rosterName: string | null;
  parentNameKey: string | null; parentName: string | null; type: string; role: string | null; slotNumber: number | null;
  status: 'assigned' | 'confirmed' | 'declined' | 'removed' | 'participated' | 'absent'; warnings: string[]; notes: string | null; respondedAt: string | null;
};

type SavedFormation = { id: string; name: string; infantryPercent: number; cavalryPercent: number; archerPercent: number; heroes: string[]; notes: string | null; isDefault: boolean };
type RallyGuidance = { id: string; allianceId: string; allianceName: string; name: string; infantryPercent: number; cavalryPercent: number; archerPercent: number; heroes: string[]; leadRequirements: string | null; joinerGuidance: string | null; source: string | null; rationale: string | null; effectiveFrom: string | null; effectiveUntil: string | null };
type RallyRecommendation = { id: string; allianceId: string; allianceName: string; guidanceRuleId: string | null; key: string; name: string; assignmentRole: string | null; infantryPercent: number; cavalryPercent: number; archerPercent: number; heroes: string[]; notes: string | null; sortOrder: number };
type RallyGroup = { id: string; allianceId: string; allianceName: string; name: string; maxJoiners: number | null; notes: string | null; recommendedFormationId: string | null; recommendedFormationName: string | null; myAssignmentStatus: string | null };
type RallyAssignment = { id: string; groupId: string; groupName: string; allianceId: string; allianceName: string; role: 'lead' | 'joiner' | 'standby'; slotNumber: number | null; status: 'assigned' | 'confirmed' | 'declined' | 'participated' | 'absent' | 'removed'; notes: string | null; recommendedFormationName: string | null; respondedAt: string | null; recordedAt: string | null };
type RallyOperations = { savedFormations: SavedFormation[]; alliances: Array<{ id: string; name: string }>; guidance: RallyGuidance[]; recommendations: RallyRecommendation[]; groups: RallyGroup[]; myAssignments: RallyAssignment[] };

type ObjectiveAssignment = { id: string; rosterId: string | null; rosterName: string | null; rosterNameKey: string | null; rosterKey: string | null; playerId: string | null; playerName: string | null; notes: string | null; assignedAt: string | null };
type EventObjective = { id: string; parentId: string | null; type: string; name: string; description: string | null; priority: number; startsAt: string | null; endsAt: string | null; status: 'planned' | 'active' | 'completed' | 'failed' | 'cancelled'; sortOrder: number; metadata: Record<string, unknown>; assignments: ObjectiveAssignment[] };
type BattlePlan = { objectives: EventObjective[]; myAssignmentIds: string[] };

type ResultSummary = { id: string; outcome: string | null; score: number | null; opponentScore: number | null; rank: number | null; metrics: Record<string, unknown>; notes: string | null; recordedAt: string | null };
type PlayerResult = { id: string; playerId: string; playerName: string | null; outcome: string | null; score: number | null; rank: number | null; metrics: Record<string, unknown>; notes: string | null; recordedAt: string | null };
type PlayerIntelligence = { playerId: string; playerName: string; commitments: number; completed: number; absent: number; excused: number; unresolved: number; reliabilityPercent: number | null; resultCount: number; averageScore: number | null; bestScore: number | null; latestScore: number | null };

type Participation = {
  playerId: string;
  playerName: string;
  response: { response: 'going' | 'maybe' | 'unavailable'; preferredRole: string | null; preferredTeam: string | null; availableFrom: string | null; availableUntil: string | null; note: string | null } | null;
  registration: { status: 'registered' | 'waitlisted' | 'cancelled'; waitlistPosition: number | null; registeredAt: string | null } | null;
  attendance: { status: string; notes: string | null; recordedAt: string | null } | null;
  registrationWindow: { opensAt: string | null; closesAt: string; isOpen: boolean };
};

const props = defineProps<{
  user: { name: string; email: string };
  userTimezone: string;
  event: {
    id: string; eventId: string; nameKey: string; title: string | null; scope: string; targetLabel: string;
    startsAt: string; endsAt: string; timezone: string; status: string; instructions: string | null; settings: Record<string, unknown>;
    capacity: number | null; recurrenceFrequency: string; recurrenceInterval: number; recurrenceUntil: string | null;
    capabilities: string[]; canManage: boolean; participation: Participation | null; operations: { phases: Phase[]; polls: Poll[] }; battlePlan: BattlePlan; results: { summary: ResultSummary | null; player: PlayerResult | null }; playerIntelligence: PlayerIntelligence | null; rosters: RosterAssignment[]; rallies: RallyOperations;
  };
}>();
const { t, formatDate } = useLocale();
const title = props.event.title || t(props.event.nameKey);
const responseForm = useForm({ response: props.event.participation?.response?.response ?? 'going' as 'going' | 'maybe' | 'unavailable', preferred_role: '', preferred_team: '', note: '' });
const registrationForm = useForm({});
const defaultSavedFormation = props.event.rallies.savedFormations.find((formation) => formation.isDefault) ?? props.event.rallies.savedFormations[0];
const formationForm = useForm({
  name: defaultSavedFormation?.name ?? '',
  infantry_percent: defaultSavedFormation?.infantryPercent ?? 10,
  cavalry_percent: defaultSavedFormation?.cavalryPercent ?? 10,
  archer_percent: defaultSavedFormation?.archerPercent ?? 80,
  heroes_text: defaultSavedFormation?.heroes.join(', ') ?? '',
  notes: defaultSavedFormation?.notes ?? '',
  is_default: defaultSavedFormation?.isDefault ?? true,
});
const pollSelections = reactive<Record<string, string[]>>(Object.fromEntries(props.event.operations.polls.map((poll) => [poll.id, [...poll.selectedOptionIds]])));

function pollQuestion(poll: Poll): string { return poll.question || (poll.questionKey ? t(poll.questionKey) : poll.key); }
function phaseName(phase: Phase): string { return phase.name || (phase.nameKey ? t(phase.nameKey) : phase.key); }
function togglePollOption(poll: Poll, optionId: string): void {
  const current = pollSelections[poll.id] ?? [];
  if (poll.maxChoices === 1) { pollSelections[poll.id] = [optionId]; return; }
  pollSelections[poll.id] = current.includes(optionId) ? current.filter((id) => id !== optionId) : current.length < poll.maxChoices ? [...current, optionId] : current;
}
function submitVote(poll: Poll): void {
  router.put(`/events/${props.event.id}/polls/${poll.id}/vote`, { option_ids: pollSelections[poll.id] ?? [] }, { preserveScroll: true });
}

function respond(value: 'going' | 'maybe' | 'unavailable'): void {
  responseForm.response = value;
  responseForm.post(`/events/${props.event.id}/responses`, { preserveScroll: true });
}
function register(): void { registrationForm.post(`/events/${props.event.id}/registrations`, { preserveScroll: true }); }
function cancelRegistration(): void { registrationForm.delete(`/events/${props.event.id}/registrations`, { preserveScroll: true }); }
function rosterName(assignment: RosterAssignment): string { return assignment.rosterName || (assignment.rosterNameKey ? t(assignment.rosterNameKey) : assignment.rosterKey); }
function rosterParentName(assignment: RosterAssignment): string | null { return assignment.parentName || (assignment.parentNameKey ? t(assignment.parentNameKey) : null); }
function respondToRoster(assignment: RosterAssignment, status: 'confirmed' | 'declined'): void { router.put(`/events/${props.event.id}/roster-members/${assignment.id}/response`, { status }, { preserveScroll: true }); }
function savePlayerFormation(): void {
  formationForm.transform((data) => ({ ...data, heroes: data.heroes_text.split(',').map((hero) => hero.trim()).filter(Boolean).slice(0, 5) }));
  formationForm.post('/player/formations', { preserveScroll: true });
}
function deletePlayerFormation(formationId: string): void { router.delete(`/player/formations/${formationId}`, { preserveScroll: true }); }
function respondToRally(assignment: RallyAssignment, status: 'confirmed' | 'declined'): void { router.put(`/events/${props.event.id}/rally-assignments/${assignment.id}/response`, { status }, { preserveScroll: true }); }
function compositionLabel(item: { infantryPercent: number; cavalryPercent: number; archerPercent: number }): string { return `${item.infantryPercent}/${item.cavalryPercent}/${item.archerPercent}`; }
function objectiveTargetLabel(assignment: ObjectiveAssignment): string { return assignment.playerName ?? assignment.rosterName ?? assignment.rosterKey ?? t('events.objectives.assignment'); }
function objectiveAssignedToMe(objective: EventObjective): boolean { return objective.assignments.some((assignment) => props.event.battlePlan.myAssignmentIds.includes(assignment.id)); }
</script>

<template>
  <Head :title="title" />
  <AppLayout :user="props.user">
    <div class="mx-auto max-w-5xl">
      <Link href="/events" class="text-sm font-semibold text-[var(--ks-text-muted)] hover:text-[var(--ks-text)]">← {{ t('events.show.back') }}</Link>
      <header class="mt-5 rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div><div class="mb-2 flex flex-wrap items-center gap-2"><span class="rounded-full border border-[var(--ks-border)] px-2 py-1 text-xs uppercase">{{ t(`events.scope.${event.scope}`) }}</span><span class="text-sm text-[var(--ks-text-muted)]">{{ event.targetLabel }}</span></div><h1 class="ks-display text-3xl font-semibold">{{ title }}</h1><p class="mt-3 text-sm text-[var(--ks-text-muted)]">{{ formatDate(new Date(event.startsAt), { weekday: 'long', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' }) }}</p></div>
          <Link v-if="event.canManage" :href="`/events/${event.eventId}/manage`" class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2 text-sm font-bold text-slate-950">{{ t('events.show.manage') }}</Link>
        </div>
      </header>

      <section v-if="event.participation && (event.capabilities.includes('responses') || event.capabilities.includes('registration'))" class="mt-5 rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-6">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">{{ t('events.participation.eyebrow') }}</p><h2 class="mt-1 text-lg font-semibold">{{ event.participation.playerName }}</h2></div><span v-if="event.participation.attendance" class="rounded-full border border-[var(--ks-border)] px-2 py-1 text-xs">{{ t('events.participation.attendance') }}: {{ t(`events.attendanceStatuses.${event.participation.attendance.status}`) }}</span></div>

        <div v-if="event.capabilities.includes('responses')" class="mt-5">
          <h3 class="text-sm font-semibold">{{ t('events.participation.response') }}</h3>
          <div class="mt-2 flex flex-wrap gap-2">
            <button v-for="choice in ['going','maybe','unavailable'] as const" :key="choice" type="button" :disabled="responseForm.processing" class="rounded px-4 py-2 text-sm font-semibold" :class="event.participation.response?.response === choice ? 'bg-[var(--ks-gold)] text-slate-950' : 'border border-[var(--ks-border)]'" :aria-pressed="event.participation.response?.response === choice" @click="respond(choice)">{{ t(`events.responses.${choice}`) }}</button>
          </div>
          <p v-if="responseForm.errors.response" class="mt-2 text-xs text-red-300">{{ responseForm.errors.response }}</p>
        </div>

        <div v-if="event.capabilities.includes('registration')" class="mt-5 border-t border-[var(--ks-border)] pt-5">
          <h3 class="text-sm font-semibold">{{ t('events.participation.registration') }}</h3>
          <p v-if="event.participation.registration && event.participation.registration.status !== 'cancelled'" class="mt-2 text-sm text-[var(--ks-text-secondary)]">
            {{ t(`events.registration.${event.participation.registration.status}`) }}<span v-if="event.participation.registration.waitlistPosition"> · #{{ event.participation.registration.waitlistPosition }}</span>
          </p>
          <div class="mt-3">
            <button v-if="event.participation.registration && event.participation.registration.status !== 'cancelled'" type="button" :disabled="registrationForm.processing" class="rounded border border-red-500/30 px-4 py-2 text-sm font-semibold text-red-200" @click="cancelRegistration">{{ t('events.participation.cancelRegistration') }}</button>
            <button v-else-if="event.participation.registrationWindow.isOpen" type="button" :disabled="registrationForm.processing" class="rounded bg-[var(--ks-blue-soft)] px-4 py-2 text-sm font-semibold text-[var(--ks-blue-strong)]" @click="register">{{ t('events.participation.register') }}</button>
            <p v-else class="text-xs text-[var(--ks-text-muted)]">{{ t('events.participation.registrationClosed') }}</p>
          </div>
          <p v-if="registrationForm.errors.registration" class="mt-2 text-xs text-red-300">{{ registrationForm.errors.registration }}</p>
        </div>
      </section>

      <section v-if="event.capabilities.includes('rosters') && event.rosters.length" class="mt-5 rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-6">
        <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">{{ t('events.rosters.eyebrow') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('events.rosters.title') }}</h2>
        <div class="mt-4 space-y-3"><article v-for="assignment in event.rosters" :key="assignment.id" class="rounded border border-[var(--ks-border)] p-4"><div class="flex flex-wrap items-start justify-between gap-3"><div><div class="font-semibold"><span v-if="rosterParentName(assignment)">{{ rosterParentName(assignment) }} · </span>{{ rosterName(assignment) }}</div><div class="mt-1 text-xs text-[var(--ks-text-muted)]"><span v-if="assignment.role">{{ assignment.role }}</span><span v-if="assignment.slotNumber"> · {{ t('events.rosters.slot') }} #{{ assignment.slotNumber }}</span></div></div><span class="rounded-full border border-[var(--ks-border)] px-2 py-1 text-xs">{{ t(`events.rosters.status.${assignment.status}`) }}</span></div><div v-if="assignment.warnings.length" class="mt-3 flex flex-wrap gap-2"><span v-for="warning in assignment.warnings" :key="warning" class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-1 text-[0.68rem] text-amber-100">{{ t(`events.rosters.warnings.${warning}`) }}</span></div><p v-if="assignment.notes" class="mt-3 whitespace-pre-line text-sm text-[var(--ks-text-secondary)]">{{ assignment.notes }}</p><div v-if="['assigned','confirmed','declined'].includes(assignment.status)" class="mt-4 flex flex-wrap gap-2"><button type="button" class="rounded px-3 py-2 text-sm font-semibold" :class="assignment.status === 'confirmed' ? 'bg-emerald-500/20 text-emerald-100' : 'border border-[var(--ks-border)]'" @click="respondToRoster(assignment, 'confirmed')">{{ t('events.rosters.confirm') }}</button><button type="button" class="rounded px-3 py-2 text-sm font-semibold" :class="assignment.status === 'declined' ? 'bg-red-500/20 text-red-100' : 'border border-[var(--ks-border)]'" @click="respondToRoster(assignment, 'declined')">{{ t('events.rosters.decline') }}</button></div></article></div>
      </section>

      <section v-if="event.capabilities.includes('formations') || event.capabilities.includes('rally_guidance')" class="mt-5 space-y-5">
        <div v-if="event.capabilities.includes('formations') && event.participation" class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-6">
          <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">{{ t('events.rallies.formationsEyebrow') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('events.rallies.savedFormations') }}</h2>
          <div v-if="event.rallies.savedFormations.length" class="mt-4 grid gap-3 md:grid-cols-2"><article v-for="formation in event.rallies.savedFormations" :key="formation.id" class="rounded border border-[var(--ks-border)] p-3"><div class="flex items-start justify-between gap-3"><div><div class="font-semibold">{{ formation.name }} <span v-if="formation.isDefault" class="text-xs text-[var(--ks-gold)]">{{ t('events.rallies.default') }}</span></div><div class="mt-1 text-xs text-[var(--ks-text-muted)]">{{ compositionLabel(formation) }} · {{ formation.heroes.join(', ') || t('events.rallies.noHeroes') }}</div></div><button type="button" class="text-xs text-red-200" @click="deletePlayerFormation(formation.id)">{{ t('events.rallies.delete') }}</button></div><p v-if="formation.notes" class="mt-2 text-xs text-[var(--ks-text-secondary)]">{{ formation.notes }}</p></article></div>
          <form class="mt-5 grid gap-3 md:grid-cols-2" @submit.prevent="savePlayerFormation"><input v-model="formationForm.name" required :placeholder="t('events.rallies.formationName')" class="rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2 text-sm" /><div class="grid grid-cols-3 gap-2"><input v-model.number="formationForm.infantry_percent" type="number" min="0" max="100" :aria-label="t('events.rallies.infantry')" class="rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-2 py-2 text-sm" /><input v-model.number="formationForm.cavalry_percent" type="number" min="0" max="100" :aria-label="t('events.rallies.cavalry')" class="rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-2 py-2 text-sm" /><input v-model.number="formationForm.archer_percent" type="number" min="0" max="100" :aria-label="t('events.rallies.archers')" class="rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-2 py-2 text-sm" /></div><input v-model="formationForm.heroes_text" :placeholder="t('events.rallies.heroesHelp')" class="rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2 text-sm" /><input v-model="formationForm.notes" :placeholder="t('events.rallies.notes')" class="rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2 text-sm" /><label class="flex items-center gap-2 text-sm"><input v-model="formationForm.is_default" type="checkbox" />{{ t('events.rallies.makeDefault') }}</label><button type="submit" :disabled="formationForm.processing" class="rounded bg-[var(--ks-blue-soft)] px-4 py-2 text-sm font-semibold text-[var(--ks-blue-strong)]">{{ t('events.rallies.saveFormation') }}</button></form>
        </div>
        <div v-if="event.capabilities.includes('rally_guidance')" class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-6"><p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">{{ t('events.rallies.guidanceEyebrow') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('events.rallies.guidance') }}</h2><div class="mt-4 grid gap-3 md:grid-cols-2"><article v-for="rule in event.rallies.guidance" :key="rule.id" class="rounded border border-[var(--ks-border)] p-4"><div class="font-semibold">{{ rule.allianceName }} · {{ rule.name }}</div><div class="mt-1 text-xs text-[var(--ks-text-muted)]">{{ compositionLabel(rule) }} · {{ rule.heroes.join(', ') }}</div><p v-if="rule.leadRequirements" class="mt-3 text-sm"><strong>{{ t('events.rallies.lead') }}:</strong> {{ rule.leadRequirements }}</p><p v-if="rule.joinerGuidance" class="mt-2 text-sm"><strong>{{ t('events.rallies.joiner') }}:</strong> {{ rule.joinerGuidance }}</p></article></div><p v-if="!event.rallies.guidance.length" class="mt-3 text-sm text-[var(--ks-text-muted)]">{{ t('events.rallies.noGuidance') }}</p></div>
        <div v-if="event.rallies.recommendations.length" class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-6"><h2 class="text-lg font-semibold">{{ t('events.rallies.recommendedFormations') }}</h2><div class="mt-4 grid gap-3 md:grid-cols-2"><article v-for="formation in event.rallies.recommendations" :key="formation.id" class="rounded border border-[var(--ks-border)] p-4"><div class="font-semibold">{{ formation.allianceName }} · {{ formation.name }}</div><div class="mt-1 text-xs text-[var(--ks-text-muted)]">{{ compositionLabel(formation) }} · {{ formation.heroes.join(', ') }}</div><p v-if="formation.notes" class="mt-2 text-sm text-[var(--ks-text-secondary)]">{{ formation.notes }}</p></article></div></div>
        <div v-if="event.rallies.myAssignments.length" class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-6"><h2 class="text-lg font-semibold">{{ t('events.rallies.myAssignments') }}</h2><div class="mt-4 space-y-3"><article v-for="assignment in event.rallies.myAssignments" :key="assignment.id" class="rounded border border-[var(--ks-border)] p-4"><div class="flex flex-wrap items-start justify-between gap-3"><div><div class="font-semibold">{{ assignment.allianceName }} · {{ assignment.groupName }}</div><div class="mt-1 text-xs text-[var(--ks-text-muted)]">{{ t(`events.rallies.roles.${assignment.role}`) }}<span v-if="assignment.slotNumber"> · {{ t('events.rallies.slot') }} #{{ assignment.slotNumber }}</span><span v-if="assignment.recommendedFormationName"> · {{ assignment.recommendedFormationName }}</span></div></div><span class="rounded-full border border-[var(--ks-border)] px-2 py-1 text-xs">{{ t(`events.rallies.status.${assignment.status}`) }}</span></div><p v-if="assignment.notes" class="mt-2 text-sm text-[var(--ks-text-secondary)]">{{ assignment.notes }}</p><div v-if="['assigned','confirmed','declined'].includes(assignment.status)" class="mt-3 flex gap-2"><button type="button" class="rounded bg-emerald-500/20 px-3 py-2 text-sm font-semibold text-emerald-100" @click="respondToRally(assignment, 'confirmed')">{{ t('events.rallies.confirm') }}</button><button type="button" class="rounded bg-red-500/15 px-3 py-2 text-sm font-semibold text-red-100" @click="respondToRally(assignment, 'declined')">{{ t('events.rallies.decline') }}</button></div></article></div></div>
      </section>

      <section v-if="event.capabilities.includes('objectives')" class="mt-5 rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-6">
        <div><p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">{{ t('events.objectives.eyebrow') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('events.objectives.title') }}</h2></div>
        <div v-if="event.battlePlan.objectives.length" class="mt-4 space-y-3"><article v-for="objective in event.battlePlan.objectives" :key="objective.id" class="rounded border p-4" :class="objectiveAssignedToMe(objective) ? 'border-[var(--ks-gold)] bg-[var(--ks-gold)]/5' : 'border-[var(--ks-border)]'"><div class="flex flex-wrap items-start justify-between gap-3"><div><div class="font-semibold">{{ objective.name }} <span v-if="objectiveAssignedToMe(objective)" class="ms-2 text-xs text-[var(--ks-gold)]">{{ t('events.objectives.assignedToYou') }}</span></div><div class="mt-1 text-xs text-[var(--ks-text-muted)]">P{{ objective.priority }} · {{ t(`events.objectives.status.${objective.status}`) }} · {{ objective.type }}</div></div><div v-if="objective.startsAt" class="text-xs text-[var(--ks-text-muted)]">{{ formatDate(new Date(objective.startsAt), { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) }}<span v-if="objective.endsAt"> → {{ formatDate(new Date(objective.endsAt), { hour: 'numeric', minute: '2-digit' }) }}</span></div></div><p v-if="objective.description" class="mt-3 text-sm text-[var(--ks-text-secondary)]">{{ objective.description }}</p><div v-if="objective.assignments.length" class="mt-3 flex flex-wrap gap-2"><span v-for="assignment in objective.assignments" :key="assignment.id" class="rounded-full border border-[var(--ks-border)] px-2 py-1 text-xs" :class="event.battlePlan.myAssignmentIds.includes(assignment.id) ? 'border-[var(--ks-gold)] text-[var(--ks-gold)]' : ''">{{ objectiveTargetLabel(assignment) }}</span></div></article></div>
        <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">{{ t('events.objectives.none') }}</p>
      </section>

      <section v-if="event.capabilities.includes('results') && (event.results.summary || event.results.player || event.playerIntelligence)" class="mt-5 rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-6">
        <div><p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">{{ t('events.results.eyebrow') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('events.results.title') }}</h2></div>
        <div class="mt-4 grid gap-4 md:grid-cols-2"><article v-if="event.results.summary" class="rounded border border-[var(--ks-border)] p-4"><h3 class="font-semibold">{{ t('events.results.occurrenceResult') }}</h3><div class="mt-3 grid grid-cols-2 gap-2 text-sm"><span>{{ t('events.results.outcome') }}</span><strong>{{ event.results.summary.outcome ?? '—' }}</strong><span>{{ t('events.results.score') }}</span><strong>{{ event.results.summary.score ?? '—' }}</strong><span>{{ t('events.results.opponentScore') }}</span><strong>{{ event.results.summary.opponentScore ?? '—' }}</strong><span>{{ t('events.results.rank') }}</span><strong>{{ event.results.summary.rank ?? '—' }}</strong></div><p v-if="event.results.summary.notes" class="mt-3 text-sm text-[var(--ks-text-secondary)]">{{ event.results.summary.notes }}</p></article><article v-if="event.results.player" class="rounded border border-[var(--ks-border)] p-4"><h3 class="font-semibold">{{ t('events.results.yourResult') }}</h3><div class="mt-3 grid grid-cols-2 gap-2 text-sm"><span>{{ t('events.results.outcome') }}</span><strong>{{ event.results.player.outcome ?? '—' }}</strong><span>{{ t('events.results.score') }}</span><strong>{{ event.results.player.score ?? '—' }}</strong><span>{{ t('events.results.rank') }}</span><strong>{{ event.results.player.rank ?? '—' }}</strong></div><p v-if="event.results.player.notes" class="mt-3 text-sm text-[var(--ks-text-secondary)]">{{ event.results.player.notes }}</p></article></div>
        <div v-if="event.playerIntelligence" class="mt-4 rounded bg-[var(--ks-surface-2)] p-4"><h3 class="font-semibold">{{ t('events.results.yourHistory') }}</h3><div class="mt-3 grid gap-3 sm:grid-cols-3"><div><div class="text-xs text-[var(--ks-text-muted)]">{{ t('events.results.reliability') }}</div><div class="text-lg font-semibold">{{ event.playerIntelligence.reliabilityPercent === null ? '—' : `${event.playerIntelligence.reliabilityPercent}%` }}</div></div><div><div class="text-xs text-[var(--ks-text-muted)]">{{ t('events.results.averageScore') }}</div><div class="text-lg font-semibold">{{ event.playerIntelligence.averageScore ?? '—' }}</div></div><div><div class="text-xs text-[var(--ks-text-muted)]">{{ t('events.results.bestScore') }}</div><div class="text-lg font-semibold">{{ event.playerIntelligence.bestScore ?? '—' }}</div></div></div></div>
      </section>

      <section v-if="event.operations.phases.length" class="mt-5 rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-6">
        <p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase">{{ t('events.phases.eyebrow') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('events.phases.title') }}</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4"><div v-for="phase in event.operations.phases" :key="phase.id" class="rounded border p-3" :class="phase.status === 'active' ? 'border-[var(--ks-gold)] bg-[var(--ks-gold)]/5' : 'border-[var(--ks-border)]'"><div class="flex items-center justify-between gap-2"><span class="font-semibold">{{ phaseName(phase) }}</span><span class="text-[0.68rem] uppercase text-[var(--ks-text-muted)]">{{ t(`events.phaseStatuses.${phase.status}`) }}</span></div><p v-if="phase.startsAt" class="mt-2 text-xs text-[var(--ks-text-muted)]">{{ formatDate(new Date(phase.startsAt), { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) }}<span v-if="phase.endsAt"> → {{ formatDate(new Date(phase.endsAt), { hour: 'numeric', minute: '2-digit' }) }}</span></p></div></div>
      </section>

      <section v-if="event.operations.polls.length" class="mt-5 space-y-4">
        <article v-for="poll in event.operations.polls" :key="poll.id" class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-6">
          <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-bold tracking-[0.15em] text-[var(--ks-blue-strong)] uppercase">{{ poll.type === 'time_vote' ? t('events.polls.timeVote') : t('events.polls.poll') }}</p><h2 class="mt-1 text-lg font-semibold">{{ pollQuestion(poll) }}</h2></div><span class="rounded-full border border-[var(--ks-border)] px-2 py-1 text-xs">{{ t(`events.pollStatuses.${poll.status}`) }}</span></div>
          <p v-if="poll.closesAt" class="mt-2 text-xs text-[var(--ks-text-muted)]">{{ t('events.polls.closes') }} {{ formatDate(new Date(poll.closesAt), { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) }}</p>
          <div class="mt-4 grid gap-2 sm:grid-cols-2"><button v-for="option in poll.options" :key="option.id" type="button" :disabled="!poll.votingOpen || !event.participation" class="flex items-center justify-between rounded border px-3 py-3 text-left text-sm disabled:opacity-60" :class="(pollSelections[poll.id] ?? []).includes(option.id) ? 'border-[var(--ks-gold)] bg-[var(--ks-gold)]/10' : 'border-[var(--ks-border)]'" :aria-pressed="(pollSelections[poll.id] ?? []).includes(option.id)" @click="togglePollOption(poll, option.id)"><span>{{ option.label }}</span><span v-if="option.votes !== null" class="text-xs text-[var(--ks-text-muted)]">{{ option.votes }}</span></button></div>
          <button v-if="poll.votingOpen && event.participation" type="button" class="mt-4 rounded bg-[var(--ks-gold)] px-4 py-2 text-sm font-bold text-slate-950" @click="submitVote(poll)">{{ t('events.polls.saveVote') }}</button><p v-else-if="!event.participation" class="mt-3 text-xs text-[var(--ks-text-muted)]">{{ t('events.polls.selectPlayer') }}</p>
        </article>
      </section>

      <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <section class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-6"><h2 class="text-lg font-semibold">{{ t('events.show.instructions') }}</h2><p class="mt-3 whitespace-pre-line text-sm leading-7 text-[var(--ks-text-secondary)]">{{ event.instructions || t('events.show.noInstructions') }}</p></section>
        <aside class="space-y-4"><section class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-4"><h2 class="text-sm font-semibold">{{ t('events.show.details') }}</h2><dl class="mt-3 space-y-2 text-xs"><div class="flex justify-between gap-3"><dt class="text-[var(--ks-text-muted)]">{{ t('events.show.status') }}</dt><dd>{{ t(`events.eventStatuses.${event.status}`) }}</dd></div><div class="flex justify-between gap-3"><dt class="text-[var(--ks-text-muted)]">{{ t('events.show.capacity') }}</dt><dd>{{ event.capacity ?? '—' }}</dd></div><div class="flex justify-between gap-3"><dt class="text-[var(--ks-text-muted)]">{{ t('events.show.recurrence') }}</dt><dd>{{ event.recurrenceFrequency === 'none' ? '—' : `${t(`events.recurrenceFrequencies.${event.recurrenceFrequency}`)} × ${event.recurrenceInterval}` }}</dd></div></dl></section><section class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-4"><h2 class="text-sm font-semibold">{{ t('events.show.modules') }}</h2><div class="mt-3 flex flex-wrap gap-2"><span v-for="capability in event.capabilities" :key="capability" class="rounded-full border border-[var(--ks-border)] px-2 py-1 text-[0.68rem]">{{ t(`events.capabilities.${capability}`) }}</span></div></section></aside>
      </div>
    </div>
  </AppLayout>
</template>
