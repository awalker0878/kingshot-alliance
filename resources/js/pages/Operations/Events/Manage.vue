<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import EventTerritoryPositioning from '@/features/territory-planner/components/EventTerritoryPositioning.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type EventSettingScalar = string | number | boolean | null;
type EventSettingLeaf = EventSettingScalar | EventSettingScalar[];
type EventSettingValue = EventSettingLeaf | Record<string, EventSettingLeaf>;

type EventTemplateForm = {
  scope: string;
  target_id: string;
  event_type_id: string;
  name: string;
  instructions: string;
  duration_minutes: number;
  capacity: number | null;
  registration_opens_minutes_before: number | null;
  registration_closes_minutes_before: number | null;
  recurrence_frequency: 'none' | 'daily' | 'weekly';
  recurrence_interval: number;
  settings: Record<string, EventSettingValue>;
};

type Phase = {
  id: string;
  key: string;
  nameKey: string | null;
  name: string | null;
  type: string;
  startsAt: string | null;
  endsAt: string | null;
  startsLocal: string | null;
  endsLocal: string | null;
  status: string;
  storedStatus: string;
  sortOrder: number;
};
type PollOption = {
  id: string;
  label: string;
  value: string;
  metadata: Record<string, unknown>;
  votes: number | null;
};
type Poll = {
  id: string;
  key: string;
  type: 'choice' | 'time_vote';
  questionKey: string | null;
  question: string | null;
  opensAt: string | null;
  closesAt: string | null;
  opensLocal: string | null;
  closesLocal: string | null;
  status: string;
  votingOpen: boolean;
  maxChoices: number;
  selectedOptionIds: string[];
  settings: Record<string, unknown>;
  options: PollOption[];
};
type OccurrenceOperations = {
  occurrenceId: string;
  startsAt: string;
  phases: Phase[];
  polls: Poll[];
};

type RosterMember = {
  id: string;
  playerId: string;
  playerName: string;
  role: string | null;
  slotNumber: number | null;
  status: string;
  assignmentWarnings: string[];
  warnings: string[];
  assignedAt: string | null;
  respondedAt: string | null;
  notes: string | null;
};
type EventRoster = {
  id: string;
  parentId: string | null;
  key: string;
  nameKey: string | null;
  name: string | null;
  type: string;
  assignmentGroup: string;
  capacity: number | null;
  activeCount: number;
  sortOrder: number;
  settings: Record<string, unknown>;
  members: RosterMember[];
};
type RosterCandidate = {
  playerId: string;
  name: string;
  claimed: boolean;
  response: string | null;
  preferredRole: string | null;
  preferredTeam: string | null;
  availableFrom: string | null;
  availableUntil: string | null;
  registration: string | null;
  waitlistPosition: number | null;
  warnings: string[];
};
type RosterOccurrence = {
  occurrenceId: string;
  startsAt: string;
  rosters: EventRoster[];
  candidates: RosterCandidate[];
};

type RallyGuidance = {
  id: string;
  allianceId: string;
  allianceName: string;
  name: string;
  infantryPercent: number;
  cavalryPercent: number;
  archerPercent: number;
  heroes: string[];
  leadRequirements: string | null;
  joinerGuidance: string | null;
  source: string | null;
  rationale: string | null;
  effectiveFrom: string | null;
  effectiveUntil: string | null;
};
type RallyRecommendation = {
  id: string;
  allianceId: string;
  allianceName: string;
  guidanceRuleId: string | null;
  key: string;
  name: string;
  assignmentRole: string | null;
  infantryPercent: number;
  cavalryPercent: number;
  archerPercent: number;
  heroes: string[];
  notes: string | null;
  sortOrder: number;
};
type RallyAssignment = {
  id: string;
  playerId: string;
  playerName: string;
  role: 'lead' | 'joiner' | 'standby';
  slotNumber: number | null;
  status: string;
  notes: string | null;
};
type RallyGroup = {
  id: string;
  allianceId: string;
  allianceName: string;
  name: string;
  maxJoiners: number | null;
  activeJoiners: number;
  notes: string | null;
  sortOrder: number;
  recommendedFormationId: string | null;
  recommendedFormationName: string | null;
  assignments: RallyAssignment[];
};
type RallyCandidate = { playerId: string; name: string; claimed: boolean };
type RallyOccurrence = {
  occurrenceId: string;
  startsAt: string;
  alliances: Array<{ id: string; name: string }>;
  guidance: RallyGuidance[];
  recommendations: RallyRecommendation[];
  groups: RallyGroup[];
  candidatesByAlliance: Record<string, RallyCandidate[]>;
};
type RallyBuilderIssue = {
  code: string;
  severity: 'blocking' | 'warning';
  count: number;
  playerIds: string[];
  groupIds: string[];
};
type RallyBuilderOccurrence = {
  occurrenceId: string;
  startsAt: string;
  state: 'empty' | 'needs_attention' | 'ready';
  groupCount: number;
  assignmentCount: number;
  leadCount: number;
  joinerCount: number;
  standbyCount: number;
  blockingCount: number;
  warningCount: number;
  observationState: 'available' | 'unavailable';
  issues: RallyBuilderIssue[];
};

type ObjectiveAssignment = {
  id: string;
  rosterId: string | null;
  rosterName: string | null;
  rosterNameKey: string | null;
  rosterKey: string | null;
  playerId: string | null;
  playerName: string | null;
  notes: string | null;
  assignedAt: string | null;
};
type EventObjective = {
  id: string;
  parentId: string | null;
  type: string;
  name: string;
  description: string | null;
  priority: number;
  startsAt: string | null;
  endsAt: string | null;
  startsLocal: string | null;
  endsLocal: string | null;
  status: 'planned' | 'active' | 'completed' | 'failed' | 'cancelled';
  sortOrder: number;
  metadata: Record<string, unknown>;
  assignments: ObjectiveAssignment[];
};
type BattlePlanOccurrence = {
  occurrenceId: string;
  startsAt: string;
  endsAt: string;
  objectives: EventObjective[];
  rosters: Array<{
    id: string;
    name: string | null;
    nameKey: string | null;
    key: string;
    type: string;
  }>;
  players: Array<{ id: string; name: string; claimed: boolean }>;
};

type ResultSummary = {
  id: string;
  outcome: string | null;
  score: number | null;
  opponentScore: number | null;
  rank: number | null;
  metrics: Record<string, unknown>;
  notes: string | null;
  recordedAt: string | null;
};
type PlayerResult = {
  id: string;
  playerId: string;
  playerName: string | null;
  outcome: string | null;
  score: number | null;
  rank: number | null;
  metrics: Record<string, unknown>;
  notes: string | null;
  recordedAt: string | null;
};
type ResultOccurrence = {
  occurrenceId: string;
  startsAt: string;
  summary: ResultSummary | null;
  playerResults: PlayerResult[];
  players: Array<{ id: string; name: string }>;
};
type PlayerIntelligence = {
  playerId: string;
  playerName: string;
  commitments: number;
  completed: number;
  absent: number;
  excused: number;
  unresolved: number;
  reliabilityPercent: number | null;
  resultCount: number;
  averageScore: number | null;
  bestScore: number | null;
  latestScore: number | null;
};

type TerritoryPlanningOperations = {
  supported: boolean;
  availableRevisions: Array<{
    id: string;
    planId: string;
    planName: string;
    revisionNumber: number;
    mapDatasetId: string;
    mapDatasetChecksum: string;
    publishedAt: string | null;
  }>;
  attachments: Array<{
    id: string;
    occurrenceId: string;
    purpose: string;
    revisionId: string;
    planId: string;
    planName: string;
    revisionNumber: number;
    publishedAt: string | null;
  }>;
};

const props = defineProps<{
  user: { name: string; email: string };
  event: {
    id: string;
    eventTypeId: string;
    nameKey: string;
    title: string | null;
    scope: string;
    targetId: string;
    timezone: string;
    firstLocalStart: string;
    instructions: string | null;
    durationMinutes: number;
    capacity: number | null;
    registrationOpensMinutesBefore: number | null;
    registrationClosesMinutesBefore: number | null;
    recurrencePolicy: 'disabled' | 'fixed_interval' | 'configurable';
    recurrenceFrequency: 'none' | 'daily' | 'weekly';
    recurrenceInterval: number;
    recurrenceUntilLocal: string | null;
    settings: Record<string, EventSettingValue>;
    workflowDimensions: string[];
    createdByPlayerId: string | null;
    updatedByPlayerId: string | null;
    occurrences: Array<{ id: string; startsAt: string; endsAt: string; status: string }>;
  };
  participants: Array<{
    occurrenceId: string;
    playerId: string;
    playerName: string;
    response: string | null;
    registration: string | null;
    waitlistPosition: number | null;
    attendance: string | null;
    attendanceNotes: string | null;
  }>;
  operations: OccurrenceOperations[];
  rosterOperations: RosterOccurrence[];
  rallyOperations: RallyOccurrence[];
  rallyBuilder: RallyBuilderOccurrence[];
  battlePlan: BattlePlanOccurrence[];
  resultOperations: ResultOccurrence[];
  playerIntelligence: PlayerIntelligence[];
  territoryPlanning: TerritoryPlanningOperations;
  reminderAudiences: string[];
  reminderRules: Array<{
    id: string;
    pollId: string | null;
    trigger: 'before_start' | 'before_poll_close';
    minutesBefore: number;
    audience: string;
    channel: string;
    enabled: boolean;
  }>;
}>();
const { t, formatDate } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
const form = useForm({
  first_local_start: props.event.firstLocalStart,
  title: props.event.title ?? '',
  instructions: props.event.instructions ?? '',
  duration_minutes: props.event.durationMinutes,
  capacity: props.event.capacity,
  registration_opens_minutes_before: props.event.registrationOpensMinutesBefore,
  registration_closes_minutes_before: props.event.registrationClosesMinutesBefore,
  recurrence_frequency: props.event.recurrenceFrequency,
  recurrence_interval: props.event.recurrenceInterval,
  recurrence_until_local: props.event.recurrenceUntilLocal ?? '',
});

const templateForm = useForm<EventTemplateForm>({
  scope: props.event.scope,
  target_id: props.event.targetId,
  event_type_id: props.event.eventTypeId,
  name: '',
  instructions: props.event.instructions ?? '',
  duration_minutes: props.event.durationMinutes,
  capacity: props.event.capacity,
  registration_opens_minutes_before: props.event.registrationOpensMinutesBefore,
  registration_closes_minutes_before: props.event.registrationClosesMinutesBefore,
  recurrence_frequency: props.event.recurrenceFrequency,
  recurrence_interval: props.event.recurrenceInterval,
  settings: props.event.settings,
});
function save(): void {
  form.patch(`/events/${props.event.id}`, { preserveScroll: true });
}
function saveTemplate(): void {
  templateForm.post('/event-templates', {
    preserveScroll: true,
    onSuccess: () => templateForm.reset('name'),
  });
}
const reminderForm = useForm({
  minutes_before: 60,
  audience: props.reminderAudiences[0] ?? 'all_scope_players',
});
const firstOccurrenceId = props.event.occurrences[0]?.id ?? '';
const editingPhaseId = ref<string | null>(null);
const phaseForm = useForm({
  occurrence_id: firstOccurrenceId,
  key: '',
  name: '',
  phase_type: 'custom',
  starts_at: '',
  ends_at: '',
  status: 'scheduled',
  sort_order: 0,
});
const editingPollId = ref<string | null>(null);
const editingPollOptionsLocked = ref(false);
const pollForm = useForm({
  occurrence_id: firstOccurrenceId,
  key: '',
  poll_type: 'choice',
  question: '',
  opens_at: '',
  closes_at: '',
  status: 'draft',
  max_choices: 1,
  deadline_reminder_minutes: 60 as number | null,
  options_text: '',
});
const editingRosterId = ref<string | null>(null);
const rosterForm = useForm({
  occurrence_id: firstOccurrenceId,
  key: '',
  name: '',
  roster_type: 'roster',
  assignment_group: 'general',
  parent_id: '',
  capacity: null as number | null,
  sort_order: 0,
});
const rosterAssignmentForm = useForm({
  occurrence_id: firstOccurrenceId,
  roster_id: '',
  player_id: '',
  role: '',
  slot_number: null as number | null,
  notes: '',
});
const editingObjectiveId = ref<string | null>(null);
const objectiveForm = useForm({
  occurrence_id: firstOccurrenceId,
  parent_id: '',
  objective_type: 'custom',
  name: '',
  description: '',
  priority: 50,
  starts_at: '',
  ends_at: '',
  status: 'planned' as 'planned' | 'active' | 'completed' | 'failed' | 'cancelled',
  sort_order: 0,
});
const objectiveAssignmentForm = useForm({
  occurrence_id: firstOccurrenceId,
  objective_id: '',
  target_type: 'player' as 'player' | 'roster',
  player_id: '',
  roster_id: '',
  notes: '',
});
function battleOccurrence(id: string): BattlePlanOccurrence | undefined {
  return props.battlePlan.find((item) => item.occurrenceId === id);
}
function resetObjective(): void {
  editingObjectiveId.value = null;
  objectiveForm.reset();
  objectiveForm.occurrence_id = firstOccurrenceId;
  objectiveForm.objective_type = 'custom';
  objectiveForm.priority = 50;
  objectiveForm.status = 'planned';
}
function editObjective(occurrenceId: string, objective: EventObjective): void {
  editingObjectiveId.value = objective.id;
  objectiveForm.occurrence_id = occurrenceId;
  objectiveForm.parent_id = objective.parentId ?? '';
  objectiveForm.objective_type = objective.type;
  objectiveForm.name = objective.name;
  objectiveForm.description = objective.description ?? '';
  objectiveForm.priority = objective.priority;
  objectiveForm.starts_at = objective.startsLocal ?? '';
  objectiveForm.ends_at = objective.endsLocal ?? '';
  objectiveForm.status = objective.status;
  objectiveForm.sort_order = objective.sortOrder;
}
function saveObjective(): void {
  const path = editingObjectiveId.value
    ? `/events/${objectiveForm.occurrence_id}/objectives/${editingObjectiveId.value}`
    : `/events/${objectiveForm.occurrence_id}/objectives`;
  const options = { preserveScroll: true, onSuccess: () => resetObjective() };
  if (editingObjectiveId.value) objectiveForm.patch(path, options);
  else objectiveForm.post(path, options);
}
function assignObjective(): void {
  if (!objectiveAssignmentForm.objective_id) return;
  const base = `/events/${objectiveAssignmentForm.occurrence_id}/objectives/${objectiveAssignmentForm.objective_id}`;
  if (objectiveAssignmentForm.target_type === 'player' && objectiveAssignmentForm.player_id)
    router.put(
      `${base}/players/${objectiveAssignmentForm.player_id}`,
      { notes: objectiveAssignmentForm.notes },
      { preserveScroll: true },
    );
  if (objectiveAssignmentForm.target_type === 'roster' && objectiveAssignmentForm.roster_id)
    router.put(
      `${base}/rosters/${objectiveAssignmentForm.roster_id}`,
      { notes: objectiveAssignmentForm.notes },
      { preserveScroll: true },
    );
}
function removeObjectiveAssignment(occurrenceId: string, assignmentId: string): void {
  router.delete(`/events/${occurrenceId}/objective-assignments/${assignmentId}`, {
    preserveScroll: true,
  });
}
function objectiveTargetLabel(assignment: ObjectiveAssignment): string {
  return (
    assignment.playerName ??
    assignment.rosterName ??
    assignment.rosterKey ??
    t('events.objectives.assignment')
  );
}
const resultForm = useForm({
  occurrence_id: firstOccurrenceId,
  outcome: '',
  score: null as number | null,
  opponent_score: null as number | null,
  rank: null as number | null,
  notes: '',
});
const playerResultForm = useForm({
  occurrence_id: firstOccurrenceId,
  player_id: '',
  outcome: '',
  score: null as number | null,
  rank: null as number | null,
  notes: '',
});
function resultOccurrence(id: string): ResultOccurrence | undefined {
  return props.resultOperations.find((item) => item.occurrenceId === id);
}
function loadResultOccurrence(id: string): void {
  const item = resultOccurrence(id);
  resultForm.occurrence_id = id;
  resultForm.outcome = item?.summary?.outcome ?? '';
  resultForm.score = item?.summary?.score ?? null;
  resultForm.opponent_score = item?.summary?.opponentScore ?? null;
  resultForm.rank = item?.summary?.rank ?? null;
  resultForm.notes = item?.summary?.notes ?? '';
}
function saveResult(): void {
  resultForm.put(`/events/${resultForm.occurrence_id}/result`, { preserveScroll: true });
}
function loadPlayerResult(): void {
  const item = resultOccurrence(playerResultForm.occurrence_id);
  const result = item?.playerResults.find((row) => row.playerId === playerResultForm.player_id);
  playerResultForm.outcome = result?.outcome ?? '';
  playerResultForm.score = result?.score ?? null;
  playerResultForm.rank = result?.rank ?? null;
  playerResultForm.notes = result?.notes ?? '';
}
function savePlayerResult(): void {
  if (!playerResultForm.player_id) return;
  playerResultForm.put(
    `/events/${playerResultForm.occurrence_id}/results/players/${playerResultForm.player_id}`,
    { preserveScroll: true },
  );
}
if (firstOccurrenceId) loadResultOccurrence(firstOccurrenceId);
const firstRallyOccurrence = props.rallyOperations[0];
const firstRallyAllianceId = firstRallyOccurrence?.alliances[0]?.id ?? '';
const editingGuidanceId = ref<string | null>(null);
const guidanceForm = useForm({
  alliance_id: firstRallyAllianceId,
  name: '',
  infantry_percent: 10,
  cavalry_percent: 10,
  archer_percent: 80,
  hero_recommendations_text: '',
  lead_requirements: '',
  joiner_guidance: '',
  source: '',
  rationale: '',
  effective_from: '',
  effective_until: '',
  is_active: true,
});
const editingRallyFormationId = ref<string | null>(null);
const rallyFormationForm = useForm({
  occurrence_id: firstOccurrenceId,
  alliance_id: firstRallyAllianceId,
  guidance_rule_id: '',
  key: '',
  name: '',
  assignment_role: '',
  infantry_percent: 10,
  cavalry_percent: 10,
  archer_percent: 80,
  heroes_text: '',
  notes: '',
  sort_order: 0,
});
const editingRallyGroupId = ref<string | null>(null);
const rallyGroupForm = useForm({
  occurrence_id: firstOccurrenceId,
  alliance_id: firstRallyAllianceId,
  recommended_formation_id: '',
  name: '',
  max_joiners: null as number | null,
  notes: '',
  sort_order: 0,
});
const rallyAssignmentForm = useForm({
  occurrence_id: firstOccurrenceId,
  group_id: '',
  player_id: '',
  role: 'joiner' as 'lead' | 'joiner' | 'standby',
  slot_number: null as number | null,
  notes: '',
});
function rallyOccurrence(id: string): RallyOccurrence | undefined {
  return props.rallyOperations.find((item) => item.occurrenceId === id);
}
function rallyGroup(id: string, groupId: string): RallyGroup | undefined {
  return rallyOccurrence(id)?.groups.find((group) => group.id === groupId);
}
function syncRallyAlliance(occurrenceId: string): void {
  const alliance = rallyOccurrence(occurrenceId)?.alliances[0]?.id ?? '';
  rallyFormationForm.alliance_id = alliance;
  rallyFormationForm.guidance_rule_id = '';
  rallyGroupForm.alliance_id = alliance;
  rallyGroupForm.recommended_formation_id = '';
  rallyAssignmentForm.group_id = '';
  rallyAssignmentForm.player_id = '';
}
function resetGuidance(): void {
  editingGuidanceId.value = null;
  guidanceForm.reset();
  guidanceForm.alliance_id = firstRallyAllianceId;
  guidanceForm.infantry_percent = 10;
  guidanceForm.cavalry_percent = 10;
  guidanceForm.archer_percent = 80;
  guidanceForm.is_active = true;
}
function editGuidance(rule: RallyGuidance): void {
  editingGuidanceId.value = rule.id;
  guidanceForm.alliance_id = rule.allianceId;
  guidanceForm.name = rule.name;
  guidanceForm.infantry_percent = rule.infantryPercent;
  guidanceForm.cavalry_percent = rule.cavalryPercent;
  guidanceForm.archer_percent = rule.archerPercent;
  guidanceForm.hero_recommendations_text = rule.heroes.join(', ');
  guidanceForm.lead_requirements = rule.leadRequirements ?? '';
  guidanceForm.joiner_guidance = rule.joinerGuidance ?? '';
  guidanceForm.source = rule.source ?? '';
  guidanceForm.rationale = rule.rationale ?? '';
  guidanceForm.effective_from = rule.effectiveFrom ?? '';
  guidanceForm.effective_until = rule.effectiveUntil ?? '';
  guidanceForm.is_active = true;
}
function saveGuidance(): void {
  if (!guidanceForm.alliance_id) return;
  guidanceForm.transform((data) => ({
    ...data,
    hero_recommendations: data.hero_recommendations_text
      .split(',')
      .map((hero) => hero.trim())
      .filter(Boolean)
      .slice(0, 5),
    effective_from: data.effective_from || null,
    effective_until: data.effective_until || null,
  }));
  const options = { preserveScroll: true, onSuccess: resetGuidance };
  if (editingGuidanceId.value) {
    guidanceForm.patch(
      `/alliances/${guidanceForm.alliance_id}/rally-guidance/${editingGuidanceId.value}`,
      options,
    );
    return;
  }
  guidanceForm.post(`/alliances/${guidanceForm.alliance_id}/rally-guidance`, options);
}
function resetRallyFormation(): void {
  editingRallyFormationId.value = null;
  rallyFormationForm.reset();
  rallyFormationForm.occurrence_id = firstOccurrenceId;
  rallyFormationForm.alliance_id = firstRallyAllianceId;
  rallyFormationForm.infantry_percent = 10;
  rallyFormationForm.cavalry_percent = 10;
  rallyFormationForm.archer_percent = 80;
}
function editRallyFormation(occurrenceId: string, formation: RallyRecommendation): void {
  editingRallyFormationId.value = formation.id;
  rallyFormationForm.occurrence_id = occurrenceId;
  rallyFormationForm.alliance_id = formation.allianceId;
  rallyFormationForm.guidance_rule_id = formation.guidanceRuleId ?? '';
  rallyFormationForm.key = formation.key;
  rallyFormationForm.name = formation.name;
  rallyFormationForm.assignment_role = formation.assignmentRole ?? '';
  rallyFormationForm.infantry_percent = formation.infantryPercent;
  rallyFormationForm.cavalry_percent = formation.cavalryPercent;
  rallyFormationForm.archer_percent = formation.archerPercent;
  rallyFormationForm.heroes_text = formation.heroes.join(', ');
  rallyFormationForm.notes = formation.notes ?? '';
  rallyFormationForm.sort_order = formation.sortOrder;
}
function saveRallyFormation(): void {
  if (!rallyFormationForm.occurrence_id || !rallyFormationForm.alliance_id) return;
  rallyFormationForm.transform((data) => ({
    ...data,
    guidance_rule_id: data.guidance_rule_id || null,
    assignment_role: data.assignment_role || null,
    heroes: data.heroes_text
      .split(',')
      .map((hero) => hero.trim())
      .filter(Boolean)
      .slice(0, 5),
  }));
  const options = { preserveScroll: true, onSuccess: resetRallyFormation };
  if (editingRallyFormationId.value) {
    rallyFormationForm.patch(
      `/events/${rallyFormationForm.occurrence_id}/rally-formations/${editingRallyFormationId.value}`,
      options,
    );
    return;
  }
  rallyFormationForm.post(`/events/${rallyFormationForm.occurrence_id}/rally-formations`, options);
}
function resetRallyGroup(): void {
  editingRallyGroupId.value = null;
  rallyGroupForm.reset();
  rallyGroupForm.occurrence_id = firstOccurrenceId;
  rallyGroupForm.alliance_id = firstRallyAllianceId;
}
function editRallyGroup(occurrenceId: string, group: RallyGroup): void {
  editingRallyGroupId.value = group.id;
  rallyGroupForm.occurrence_id = occurrenceId;
  rallyGroupForm.alliance_id = group.allianceId;
  rallyGroupForm.recommended_formation_id = group.recommendedFormationId ?? '';
  rallyGroupForm.name = group.name;
  rallyGroupForm.max_joiners = group.maxJoiners;
  rallyGroupForm.notes = group.notes ?? '';
  rallyGroupForm.sort_order = group.sortOrder;
}
function saveRallyGroup(): void {
  if (!rallyGroupForm.occurrence_id || !rallyGroupForm.alliance_id) return;
  rallyGroupForm.transform((data) => ({
    ...data,
    recommended_formation_id: data.recommended_formation_id || null,
  }));
  const options = { preserveScroll: true, onSuccess: resetRallyGroup };
  if (editingRallyGroupId.value) {
    rallyGroupForm.patch(
      `/events/${rallyGroupForm.occurrence_id}/rally-groups/${editingRallyGroupId.value}`,
      options,
    );
    return;
  }
  rallyGroupForm.post(`/events/${rallyGroupForm.occurrence_id}/rally-groups`, options);
}
function assignRallyPlayer(): void {
  if (!rallyAssignmentForm.group_id || !rallyAssignmentForm.player_id) return;
  rallyAssignmentForm.put(
    `/events/${rallyAssignmentForm.occurrence_id}/rally-groups/${rallyAssignmentForm.group_id}/players/${rallyAssignmentForm.player_id}`,
    {
      preserveScroll: true,
      onSuccess: () => rallyAssignmentForm.reset('player_id', 'slot_number', 'notes'),
    },
  );
}
function removeRallyPlayer(occurrenceId: string, groupId: string, playerId: string): void {
  router.delete(`/events/${occurrenceId}/rally-groups/${groupId}/players/${playerId}`, {
    preserveScroll: true,
  });
}
function recordRallyParticipation(
  occurrenceId: string,
  assignmentId: string,
  status: 'participated' | 'absent',
): void {
  router.patch(
    `/events/${occurrenceId}/rally-assignments/${assignmentId}/participation`,
    { status },
    { preserveScroll: true },
  );
}
function rosterLabel(roster: EventRoster): string {
  return roster.name || (roster.nameKey ? t(roster.nameKey) : roster.key);
}
function rosterGroup(occurrenceId: string): RosterOccurrence | undefined {
  return props.rosterOperations.find((group) => group.occurrenceId === occurrenceId);
}
function candidateLabel(candidate: RosterCandidate): string {
  const ownership = candidate.claimed ? t('events.rosters.claimed') : t('events.rosters.unclaimed');
  const warnings = candidate.warnings.map((warning) => t(`events.rosters.warnings.${warning}`));
  return [candidate.name, ownership, warnings.length ? `⚠ ${warnings.join(', ')}` : '']
    .filter(Boolean)
    .join(' · ');
}
function resetRoster(): void {
  editingRosterId.value = null;
  rosterForm.reset();
  rosterForm.occurrence_id = firstOccurrenceId;
  rosterForm.assignment_group = 'general';
  rosterForm.roster_type = 'roster';
}
function editRoster(occurrenceId: string, roster: EventRoster): void {
  editingRosterId.value = roster.id;
  rosterForm.occurrence_id = occurrenceId;
  rosterForm.key = roster.key;
  rosterForm.name = rosterLabel(roster);
  rosterForm.roster_type = roster.type;
  rosterForm.assignment_group = roster.assignmentGroup;
  rosterForm.parent_id = roster.parentId ?? '';
  rosterForm.capacity = roster.capacity;
  rosterForm.sort_order = roster.sortOrder;
}
function saveRoster(): void {
  const options = { preserveScroll: true, onSuccess: resetRoster };
  if (editingRosterId.value) {
    rosterForm.patch(
      `/events/${rosterForm.occurrence_id}/rosters/${editingRosterId.value}`,
      options,
    );
    return;
  }
  rosterForm.post(`/events/${rosterForm.occurrence_id}/rosters`, options);
}
function assignRosterPlayer(): void {
  if (!rosterAssignmentForm.roster_id || !rosterAssignmentForm.player_id) return;
  rosterAssignmentForm.put(
    `/events/${rosterAssignmentForm.occurrence_id}/rosters/${rosterAssignmentForm.roster_id}/players/${rosterAssignmentForm.player_id}`,
    {
      preserveScroll: true,
      onSuccess: () => rosterAssignmentForm.reset('player_id', 'role', 'slot_number', 'notes'),
    },
  );
}
function removeRosterPlayer(occurrenceId: string, rosterId: string, playerId: string): void {
  router.delete(`/events/${occurrenceId}/rosters/${rosterId}/players/${playerId}`, {
    preserveScroll: true,
  });
}
function phaseName(phase: Phase): string {
  return phase.name || (phase.nameKey ? t(phase.nameKey) : phase.key);
}
function pollQuestion(poll: Poll): string {
  return poll.question || (poll.questionKey ? t(poll.questionKey) : poll.key);
}
function resetPhase(): void {
  editingPhaseId.value = null;
  phaseForm.reset();
  phaseForm.occurrence_id = firstOccurrenceId;
}
function editPhase(occurrenceId: string, phase: Phase): void {
  editingPhaseId.value = phase.id;
  phaseForm.occurrence_id = occurrenceId;
  phaseForm.key = phase.key;
  phaseForm.name = phaseName(phase);
  phaseForm.phase_type = phase.type;
  phaseForm.starts_at = phase.startsLocal ?? '';
  phaseForm.ends_at = phase.endsLocal ?? '';
  phaseForm.status = phase.storedStatus;
  phaseForm.sort_order = phase.sortOrder;
}
function savePhase(): void {
  const options = { preserveScroll: true, onSuccess: resetPhase };
  if (editingPhaseId.value) {
    phaseForm.patch(`/events/${phaseForm.occurrence_id}/phases/${editingPhaseId.value}`, options);
    return;
  }
  phaseForm.post(`/events/${phaseForm.occurrence_id}/phases`, options);
}
function resetPoll(): void {
  editingPollId.value = null;
  editingPollOptionsLocked.value = false;
  pollForm.reset();
  pollForm.occurrence_id = firstOccurrenceId;
}
function editPoll(occurrenceId: string, poll: Poll): void {
  editingPollId.value = poll.id;
  editingPollOptionsLocked.value = poll.options.some((option) => (option.votes ?? 0) > 0);
  pollForm.occurrence_id = occurrenceId;
  pollForm.key = poll.key;
  pollForm.poll_type = poll.type;
  pollForm.question = poll.question ?? '';
  pollForm.opens_at = poll.opensLocal ?? '';
  pollForm.closes_at = poll.closesLocal ?? '';
  pollForm.status = poll.status;
  pollForm.max_choices = poll.maxChoices;
  pollForm.deadline_reminder_minutes =
    typeof poll.settings.deadline_reminder_minutes === 'number'
      ? poll.settings.deadline_reminder_minutes
      : 60;
  pollForm.options_text = poll.options
    .map((option) => `${option.label}|${option.value}`)
    .join('\n');
}
function parsedPollOptions(optionsText: string): Array<{ label: string; value: string }> {
  return optionsText
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      const [rawLabel = '', ...rest] = line.split('|');
      const label = rawLabel.trim();
      const value = rest.join('|').trim() || label;
      return { label, value };
    });
}
function savePoll(): void {
  pollForm.transform((data) => {
    const { options_text: optionsText, ...rest } = data;
    return editingPollOptionsLocked.value
      ? rest
      : { ...rest, options: parsedPollOptions(optionsText) };
  });
  const options = { preserveScroll: true, onSuccess: resetPoll };
  if (editingPollId.value) {
    pollForm.patch(`/events/${pollForm.occurrence_id}/polls/${editingPollId.value}`, options);
    return;
  }
  pollForm.post(`/events/${pollForm.occurrence_id}/polls`, options);
}
function saveReminder(): void {
  reminderForm.post(`/events/${props.event.id}/reminders`, { preserveScroll: true });
}
function disableReminder(ruleId: string): void {
  router.delete(`/events/${props.event.id}/reminders/${ruleId}`, { preserveScroll: true });
}
function setAttendance(
  occurrenceId: string,
  playerId: string,
  status: 'present' | 'absent' | 'excused' | 'unknown',
): void {
  router.put(
    `/events/${occurrenceId}/attendance/${playerId}`,
    { status },
    { preserveScroll: true },
  );
}
function cancel(): void {
  requestConfirmation({
    id: 'event-cancellation-confirmation',
    title: t('events.manage.cancel'),
    description: t('events.manage.cancelConfirm'),
    confirmLabel: t('events.manage.cancel'),
    cancelLabel: t('common.cancel'),
    perform: (finish) =>
      router.visit(`/events/${props.event.id}`, {
        method: 'delete',
        onFinish: finish,
      }),
  });
}
</script>

<template>
  <Head :title="t('events.manage.title')" />
  <AppLayout :user="props.user">
    <div class="mx-auto max-w-[94rem]">
      <RoomBanner
        :eyebrow="t('events.manage.eyebrow')"
        :title="event.title || t(event.nameKey)"
        :subtitle="`${t(`events.scope.${event.scope}`)} · ${event.timezone}`"
        image="/images/kingshot/v4/event-command.svg"
        compact
      >
        <template #actions>
          <Link href="/events" class="ks-command-link" data-variant="secondary">
            ← {{ t('events.manage.back') }}
          </Link>
          <Link
            v-if="event.occurrences[0]"
            :href="`/events/${event.occurrences[0].id}`"
            class="ks-command-link"
          >
            {{ t('events.calendar.agenda') }}
          </Link>
        </template>
      </RoomBanner>

      <section
        class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4"
        :aria-label="t('events.manage.title')"
      >
        <StatSeal
          :label="t('events.manage.occurrences')"
          :value="event.occurrences.length"
          icon="▦"
        />
        <StatSeal
          :label="t('events.manage.participants')"
          :value="participants.length"
          icon="♟"
          tone="teal"
        />
        <StatSeal
          :label="t('events.manage.reminders')"
          :value="reminderRules.length"
          icon="⌛"
          tone="stone"
        />
        <StatSeal
          :label="t('events.calendar.viewOptions')"
          :value="event.workflowDimensions.length"
          icon="✦"
        />
      </section>

      <nav class="ks-tab-strip mt-4" :aria-label="t('events.manage.eyebrow')">
        <a href="#schedule" class="ks-tab">{{ t('events.manage.title') }}</a>
        <a href="#phases" class="ks-tab">{{ t('events.phases.manageTitle') }}</a>
        <a href="#polls" class="ks-tab">{{ t('events.polls.manageTitle') }}</a>
        <a v-if="event.workflowDimensions.includes('roster')" href="#rosters" class="ks-tab">{{
          t('events.rosters.manageTitle')
        }}</a>
        <a v-if="event.workflowDimensions.includes('rallies')" href="#rallies" class="ks-tab">{{
          t('events.rallies.manageTitle')
        }}</a>
        <a
          v-if="event.workflowDimensions.includes('battle_assignments')"
          href="#battle-plan"
          class="ks-tab"
          >{{ t('events.objectives.manageTitle') }}</a
        >
        <a v-if="event.workflowDimensions.includes('results')" href="#results" class="ks-tab">{{
          t('events.results.manageTitle')
        }}</a>
        <a
          v-if="event.workflowDimensions.includes('participation')"
          href="#participants"
          class="ks-tab"
          >{{ t('events.manage.participants') }}</a
        >
        <a v-if="territoryPlanning.supported" href="#territory-positioning" class="ks-tab">{{
          t('territory.eventPositioningTitle')
        }}</a>
        <a href="#reminders" class="ks-tab">{{ t('events.manage.reminders') }}</a>
      </nav>
      <EventTerritoryPositioning
        v-if="territoryPlanning.supported"
        class="mt-5"
        :occurrences="event.occurrences"
        :planning="territoryPlanning"
      />

      <div
        id="schedule"
        class="mt-5 grid scroll-mt-28 gap-5 2xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,.65fr)]"
      >
        <form class="ks-surface-gold space-y-5 p-5 sm:p-6" @submit.prevent="save">
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-semibold"
              >{{ t('events.create.start')
              }}<input
                v-model="form.first_local_start"
                type="datetime-local"
                class="ks-input mt-2 w-full" /></label
            ><label class="text-sm font-semibold"
              >{{ t('events.create.duration')
              }}<input
                v-model.number="form.duration_minutes"
                type="number"
                min="1"
                class="ks-input mt-2 w-full" /></label
            ><label class="text-sm font-semibold"
              >{{ t('events.create.capacity')
              }}<input
                v-model.number="form.capacity"
                type="number"
                min="1"
                class="ks-input mt-2 w-full" /></label
            ><label class="text-sm font-semibold"
              >{{ t('events.create.titleOverride')
              }}<input v-model="form.title" type="text" class="ks-input mt-2 w-full"
            /></label>
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-semibold"
              >{{ t('events.create.registrationOpens')
              }}<input
                v-model.number="form.registration_opens_minutes_before"
                type="number"
                min="0"
                class="ks-input mt-2 w-full" /></label
            ><label class="text-sm font-semibold"
              >{{ t('events.create.registrationCloses')
              }}<input
                v-model.number="form.registration_closes_minutes_before"
                type="number"
                min="0"
                class="ks-input mt-2 w-full"
            /></label>
          </div>
          <div v-if="event.recurrencePolicy !== 'disabled'" class="grid gap-4 sm:grid-cols-3">
            <label class="text-sm font-semibold"
              >{{ t('events.create.recurrence')
              }}<select
                v-model="form.recurrence_frequency"
                :disabled="event.recurrencePolicy === 'fixed_interval'"
                class="ks-input mt-2 w-full disabled:opacity-60"
              >
                <option value="none">{{ t('events.recurrenceFrequencies.none') }}</option>
                <option value="daily">{{ t('events.recurrenceFrequencies.daily') }}</option>
                <option value="weekly">{{ t('events.recurrenceFrequencies.weekly') }}</option>
              </select></label
            ><label class="text-sm font-semibold"
              >{{ t('events.create.interval')
              }}<input
                v-model.number="form.recurrence_interval"
                :disabled="event.recurrencePolicy === 'fixed_interval'"
                type="number"
                min="1"
                class="ks-input mt-2 w-full disabled:opacity-60" /></label
            ><label class="text-sm font-semibold"
              >{{ t('events.create.recurrenceUntil')
              }}<input
                v-model="form.recurrence_until_local"
                type="datetime-local"
                class="ks-input mt-2 w-full"
            /></label>
          </div>
          <label class="block text-sm font-semibold"
            >{{ t('events.create.instructions')
            }}<textarea v-model="form.instructions" rows="8" class="ks-input mt-2 w-full" />
          </label>
          <div
            v-if="Object.keys(form.errors).length"
            class="rounded border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200"
          >
            {{ Object.values(form.errors)[0] }}
          </div>
          <button type="submit" :disabled="form.processing" class="ks-command-button">
            {{ t('events.manage.save') }}
          </button>
        </form>
        <aside class="space-y-4">
          <section class="ks-surface p-4">
            <h2 class="font-semibold">{{ t('events.manage.occurrences') }}</h2>
            <div class="mt-3 space-y-2">
              <Link
                v-for="occurrence in event.occurrences"
                :key="occurrence.id"
                :href="`/events/${occurrence.id}`"
                class="block rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-2 text-xs"
              >
                <span class="font-semibold">{{
                  formatDate(new Date(occurrence.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}</span>
                <span class="ms-2 text-[var(--ks-text-muted)]">{{
                  t(`events.occurrenceStatuses.${occurrence.status}`)
                }}</span>
              </Link>
            </div>
          </section>

          <form class="ks-surface p-4" @submit.prevent="saveTemplate">
            <h2 class="font-semibold">{{ t('events.manage.templateTitle') }}</h2>
            <p class="mt-1 text-xs leading-5 text-[var(--ks-text-muted)]">
              {{ t('events.manage.templateHelp') }}
            </p>
            <label class="mt-3 block text-xs font-semibold">
              {{ t('events.manage.templateName') }}
              <input
                v-model="templateForm.name"
                required
                type="text"
                maxlength="120"
                class="ks-input mt-1 w-full"
              />
            </label>
            <p
              v-if="Object.keys(templateForm.errors).length"
              class="mt-3 rounded border border-red-500/30 bg-red-500/10 p-3 text-xs text-red-200"
            >
              {{ Object.values(templateForm.errors)[0] }}
            </p>
            <button
              type="submit"
              :disabled="templateForm.processing || !templateForm.name.trim()"
              class="ks-command-button mt-3 w-full disabled:opacity-50"
            >
              {{ t('events.manage.templateSave') }}
            </button>
          </form>

          <button
            type="button"
            class="w-full rounded-[var(--ks-radius-sm)] border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-100 transition hover:border-red-300/50"
            @click="cancel"
          >
            {{ t('events.manage.cancel') }}
          </button>
        </aside>
      </div>

      <section id="phases" class="ks-surface mt-5 scroll-mt-28 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="ks-kicker">
              {{ t('events.phases.eyebrow') }}
            </p>
            <h2 class="mt-1 text-lg font-semibold">{{ t('events.phases.manageTitle') }}</h2>
          </div>
          <button
            v-if="editingPhaseId"
            type="button"
            class="text-xs font-semibold text-[var(--ks-text-muted)]"
            @click="resetPhase"
          >
            {{ t('events.actions.cancel') }}
          </button>
        </div>
        <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <div class="space-y-3">
            <div v-for="group in operations" :key="group.occurrenceId" class="ks-surface p-3">
              <p class="mb-2 text-xs font-semibold text-[var(--ks-text-muted)]">
                {{
                  formatDate(new Date(group.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </p>
              <div class="space-y-2">
                <button
                  v-for="phase in group.phases"
                  :key="phase.id"
                  type="button"
                  class="flex w-full items-center justify-between rounded border border-[var(--ks-border)] px-3 py-2 text-left text-sm"
                  @click="editPhase(group.occurrenceId, phase)"
                >
                  <span>{{ phaseName(phase) }}</span
                  ><span class="text-xs text-[var(--ks-text-muted)]">{{
                    t(`events.phaseStatuses.${phase.status}`)
                  }}</span>
                </button>
                <p v-if="!group.phases.length" class="text-xs text-[var(--ks-text-muted)]">
                  {{ t('events.phases.none') }}
                </p>
              </div>
            </div>
          </div>
          <form class="ks-surface space-y-3 p-4" @submit.prevent="savePhase">
            <select v-model="phaseForm.occurrence_id" class="ks-input w-full text-sm">
              <option
                v-for="occurrence in event.occurrences"
                :key="occurrence.id"
                :value="occurrence.id"
              >
                {{
                  formatDate(new Date(occurrence.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </option></select
            ><input
              v-model="phaseForm.key"
              required
              :placeholder="t('events.phases.key')"
              class="ks-input w-full text-sm"
            /><input
              v-model="phaseForm.name"
              required
              :placeholder="t('events.phases.name')"
              class="ks-input w-full text-sm"
            /><select v-model="phaseForm.phase_type" class="ks-input w-full text-sm">
              <option
                v-for="value in [
                  'preparation',
                  'voting',
                  'registration',
                  'matchmaking',
                  'roster_lock',
                  'battle',
                  'results',
                  'custom',
                ]"
                :key="value"
                :value="value"
              >
                {{ t(`events.phases.${value}`) }}
              </option>
            </select>
            <div class="grid grid-cols-2 gap-2">
              <input
                v-model="phaseForm.starts_at"
                type="datetime-local"
                class="ks-input text-xs"
              /><input v-model="phaseForm.ends_at" type="datetime-local" class="ks-input text-xs" />
            </div>
            <select v-model="phaseForm.status" class="ks-input w-full text-sm">
              <option
                v-for="value in ['scheduled', 'active', 'completed', 'cancelled']"
                :key="value"
                :value="value"
              >
                {{ t(`events.phaseStatuses.${value}`) }}
              </option></select
            ><button
              type="submit"
              :disabled="phaseForm.processing"
              class="ks-command-button w-full"
            >
              {{ editingPhaseId ? t('events.actions.save') : t('events.phases.add') }}
            </button>
          </form>
        </div>
      </section>

      <section id="polls" class="ks-surface mt-5 scroll-mt-28 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="ks-kicker">
              {{ t('events.polls.eyebrow') }}
            </p>
            <h2 class="mt-1 text-lg font-semibold">{{ t('events.polls.manageTitle') }}</h2>
          </div>
          <button
            v-if="editingPollId"
            type="button"
            class="text-xs font-semibold text-[var(--ks-text-muted)]"
            @click="resetPoll"
          >
            {{ t('events.actions.cancel') }}
          </button>
        </div>
        <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
          <div class="space-y-3">
            <div v-for="group in operations" :key="group.occurrenceId" class="ks-surface p-3">
              <p class="mb-2 text-xs font-semibold text-[var(--ks-text-muted)]">
                {{
                  formatDate(new Date(group.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </p>
              <button
                v-for="poll in group.polls"
                :key="poll.id"
                type="button"
                class="ks-surface mb-2 block w-full p-3 text-left"
                @click="editPoll(group.occurrenceId, poll)"
              >
                <div class="flex justify-between gap-2 text-sm font-semibold">
                  <span>{{ pollQuestion(poll) }}</span
                  ><span class="text-xs font-normal text-[var(--ks-text-muted)]">{{
                    t(`events.pollStatuses.${poll.status}`)
                  }}</span>
                </div>
                <div class="mt-2 grid gap-1 text-xs text-[var(--ks-text-muted)]">
                  <span v-for="option in poll.options" :key="option.id"
                    >{{ option.label
                    }}<template v-if="option.votes !== null"> · {{ option.votes }}</template></span
                  >
                </div>
              </button>
              <p v-if="!group.polls.length" class="text-xs text-[var(--ks-text-muted)]">
                {{ t('events.polls.none') }}
              </p>
            </div>
          </div>
          <form class="ks-surface space-y-3 p-4" @submit.prevent="savePoll">
            <select v-model="pollForm.occurrence_id" class="ks-input w-full text-sm">
              <option
                v-for="occurrence in event.occurrences"
                :key="occurrence.id"
                :value="occurrence.id"
              >
                {{
                  formatDate(new Date(occurrence.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </option></select
            ><input
              v-model="pollForm.key"
              required
              :placeholder="t('events.polls.key')"
              class="ks-input w-full text-sm"
            /><select v-model="pollForm.poll_type" class="ks-input w-full text-sm">
              <option value="choice">{{ t('events.polls.choice') }}</option>
              <option value="time_vote">{{ t('events.polls.timeVote') }}</option></select
            ><input
              v-model="pollForm.question"
              :placeholder="t('events.polls.question')"
              class="ks-input w-full text-sm"
            />
            <div class="grid grid-cols-2 gap-2">
              <input
                v-model="pollForm.opens_at"
                type="datetime-local"
                class="ks-input text-xs"
              /><input
                v-model="pollForm.closes_at"
                type="datetime-local"
                class="ks-input text-xs"
              />
            </div>
            <div class="grid grid-cols-2 gap-2">
              <select v-model="pollForm.status" class="ks-input text-sm">
                <option
                  v-for="value in ['draft', 'open', 'closed', 'cancelled']"
                  :key="value"
                  :value="value"
                >
                  {{ t(`events.pollStatuses.${value}`) }}
                </option></select
              ><input
                v-model.number="pollForm.max_choices"
                type="number"
                min="1"
                max="20"
                class="ks-input text-sm"
              />
            </div>
            <textarea
              v-model="pollForm.options_text"
              :disabled="editingPollOptionsLocked"
              rows="5"
              :placeholder="t('events.polls.optionsHelp')"
              class="ks-input w-full text-sm disabled:opacity-60"
            />
            <p v-if="editingPollOptionsLocked" class="text-xs text-[var(--ks-text-muted)]">
              {{ t('events.polls.optionsLocked') }}
            </p>
            <label class="block text-xs font-semibold"
              >{{ t('events.polls.deadlineReminder')
              }}<input
                v-model.number="pollForm.deadline_reminder_minutes"
                type="number"
                min="1"
                max="10080"
                class="ks-input mt-1 w-full" /></label
            ><button type="submit" :disabled="pollForm.processing" class="ks-command-button w-full">
              {{ editingPollId ? t('events.actions.save') : t('events.polls.add') }}
            </button>
          </form>
        </div>
      </section>

      <section
        v-if="event.workflowDimensions.includes('roster')"
        id="rosters"
        class="ks-surface mt-5 scroll-mt-28 p-5"
      >
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="ks-kicker">
              {{ t('events.rosters.eyebrow') }}
            </p>
            <h2 class="mt-1 text-lg font-semibold">{{ t('events.rosters.manageTitle') }}</h2>
          </div>
          <button
            v-if="editingRosterId"
            type="button"
            class="text-xs font-semibold text-[var(--ks-text-muted)]"
            @click="resetRoster"
          >
            {{ t('events.actions.cancel') }}
          </button>
        </div>
        <div class="mt-4 grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
          <div class="space-y-4">
            <article
              v-for="group in rosterOperations"
              :key="group.occurrenceId"
              class="ks-surface p-4"
            >
              <p class="mb-3 text-xs font-semibold text-[var(--ks-text-muted)]">
                {{
                  formatDate(new Date(group.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </p>
              <div class="grid gap-3 lg:grid-cols-2">
                <div v-for="roster in group.rosters" :key="roster.id" class="ks-surface p-3">
                  <button
                    type="button"
                    class="flex w-full items-center justify-between gap-2 text-left"
                    @click="editRoster(group.occurrenceId, roster)"
                  >
                    <span class="font-semibold">{{ rosterLabel(roster) }}</span
                    ><span class="text-xs text-[var(--ks-text-muted)]"
                      >{{ roster.activeCount
                      }}<template v-if="roster.capacity !== null"
                        >/{{ roster.capacity }}</template
                      ></span
                    >
                  </button>
                  <p class="mt-1 text-[0.68rem] text-[var(--ks-text-muted)] uppercase">
                    {{ t(`events.rosters.types.${roster.type}`) }} · {{ roster.assignmentGroup }}
                  </p>
                  <div class="mt-3 space-y-2">
                    <div
                      v-for="member in roster.members.filter((item) => item.status !== 'removed')"
                      :key="member.id"
                      class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-quiet)] bg-black/15 p-2"
                    >
                      <div class="flex items-start justify-between gap-2">
                        <div>
                          <div class="text-sm font-semibold">{{ member.playerName }}</div>
                          <div class="text-xs text-[var(--ks-text-muted)]">
                            <span v-if="member.role">{{ member.role }}</span
                            ><span v-if="member.slotNumber">
                              · {{ t('events.rosters.slot') }} #{{ member.slotNumber }}</span
                            >
                            · {{ t(`events.rosters.status.${member.status}`) }}
                          </div>
                        </div>
                        <button
                          type="button"
                          class="text-xs font-semibold text-red-200"
                          @click="
                            removeRosterPlayer(group.occurrenceId, roster.id, member.playerId)
                          "
                        >
                          {{ t('events.rosters.remove') }}
                        </button>
                      </div>
                      <div v-if="member.warnings.length" class="mt-2 flex flex-wrap gap-1">
                        <span
                          v-for="warning in member.warnings"
                          :key="warning"
                          class="rounded-full border border-amber-500/30 px-2 py-0.5 text-[0.65rem] text-amber-100"
                          >{{ t(`events.rosters.warnings.${warning}`) }}</span
                        >
                      </div>
                    </div>
                    <p
                      v-if="!roster.members.some((item) => item.status !== 'removed')"
                      class="text-xs text-[var(--ks-text-muted)]"
                    >
                      {{ t('events.rosters.noAssignments') }}
                    </p>
                  </div>
                </div>
              </div>
              <p v-if="!group.rosters.length" class="text-xs text-[var(--ks-text-muted)]">
                {{ t('events.rosters.none') }}
              </p>
            </article>
          </div>
          <div class="space-y-4">
            <form class="ks-surface space-y-3 p-4" @submit.prevent="saveRoster">
              <h3 class="text-sm font-semibold">
                {{ editingRosterId ? t('events.rosters.edit') : t('events.rosters.add') }}
              </h3>
              <select v-model="rosterForm.occurrence_id" class="ks-input w-full text-sm">
                <option
                  v-for="occurrence in event.occurrences"
                  :key="occurrence.id"
                  :value="occurrence.id"
                >
                  {{
                    formatDate(new Date(occurrence.startsAt), {
                      month: 'short',
                      day: 'numeric',
                      hour: 'numeric',
                      minute: '2-digit',
                    })
                  }}
                </option></select
              ><input
                v-model="rosterForm.key"
                required
                :placeholder="t('events.rosters.key')"
                class="ks-input w-full text-sm"
              /><input
                v-model="rosterForm.name"
                required
                :placeholder="t('events.rosters.name')"
                class="ks-input w-full text-sm"
              />
              <div class="grid grid-cols-2 gap-2">
                <select v-model="rosterForm.roster_type" class="ks-input text-sm">
                  <option
                    v-for="value in ['roster', 'combatants', 'substitutes', 'team', 'legion']"
                    :key="value"
                    :value="value"
                  >
                    {{ t(`events.rosters.types.${value}`) }}
                  </option></select
                ><input
                  v-model="rosterForm.assignment_group"
                  required
                  :placeholder="t('events.rosters.group')"
                  class="ks-input text-sm"
                />
              </div>
              <select v-model="rosterForm.parent_id" class="ks-input w-full text-sm">
                <option value="">{{ t('events.rosters.noParent') }}</option>
                <option
                  v-for="roster in rosterGroup(rosterForm.occurrence_id)?.rosters ?? []"
                  :key="roster.id"
                  :value="roster.id"
                  :disabled="roster.id === editingRosterId"
                >
                  {{ rosterLabel(roster) }}
                </option>
              </select>
              <div class="grid grid-cols-2 gap-2">
                <input
                  v-model.number="rosterForm.capacity"
                  type="number"
                  min="1"
                  :placeholder="t('events.rosters.capacity')"
                  class="ks-input text-sm"
                /><input
                  v-model.number="rosterForm.sort_order"
                  type="number"
                  min="0"
                  :placeholder="t('events.rosters.sortOrder')"
                  class="ks-input text-sm"
                />
              </div>
              <button
                type="submit"
                :disabled="rosterForm.processing"
                class="ks-command-button w-full"
              >
                {{ t('events.actions.save') }}
              </button>
            </form>
            <form class="ks-surface space-y-3 p-4" @submit.prevent="assignRosterPlayer">
              <h3 class="text-sm font-semibold">{{ t('events.rosters.assignPlayer') }}</h3>
              <select
                v-model="rosterAssignmentForm.occurrence_id"
                class="ks-input w-full text-sm"
                @change="
                  rosterAssignmentForm.roster_id = '';
                  rosterAssignmentForm.player_id = '';
                "
              >
                <option
                  v-for="occurrence in event.occurrences"
                  :key="occurrence.id"
                  :value="occurrence.id"
                >
                  {{
                    formatDate(new Date(occurrence.startsAt), {
                      month: 'short',
                      day: 'numeric',
                      hour: 'numeric',
                      minute: '2-digit',
                    })
                  }}
                </option></select
              ><select
                v-model="rosterAssignmentForm.roster_id"
                required
                class="ks-input w-full text-sm"
              >
                <option value="" disabled>{{ t('events.rosters.roster') }}</option>
                <option
                  v-for="roster in rosterGroup(rosterAssignmentForm.occurrence_id)?.rosters ?? []"
                  :key="roster.id"
                  :value="roster.id"
                >
                  {{ rosterLabel(roster) }}
                </option></select
              ><select
                v-model="rosterAssignmentForm.player_id"
                required
                class="ks-input w-full text-sm"
              >
                <option value="" disabled>{{ t('events.rosters.player') }}</option>
                <option
                  v-for="candidate in rosterGroup(rosterAssignmentForm.occurrence_id)?.candidates ??
                  []"
                  :key="candidate.playerId"
                  :value="candidate.playerId"
                >
                  {{ candidateLabel(candidate) }}
                </option>
              </select>
              <div class="grid grid-cols-2 gap-2">
                <input
                  v-model="rosterAssignmentForm.role"
                  :placeholder="t('events.rosters.role')"
                  class="ks-input text-sm"
                /><input
                  v-model.number="rosterAssignmentForm.slot_number"
                  type="number"
                  min="1"
                  :placeholder="t('events.rosters.slot')"
                  class="ks-input text-sm"
                />
              </div>
              <textarea
                v-model="rosterAssignmentForm.notes"
                rows="2"
                :placeholder="t('events.rosters.notes')"
                class="ks-input w-full text-sm"
              /><button
                type="submit"
                :disabled="rosterAssignmentForm.processing"
                class="ks-command-button w-full"
              >
                {{ t('events.rosters.assign') }}
              </button>
            </form>
          </div>
        </div>
      </section>

      <section
        v-if="event.workflowDimensions.includes('rallies')"
        id="rallies"
        class="ks-surface mt-5 scroll-mt-28 p-5"
      >
        <p class="ks-kicker">
          {{ t('events.rallies.manageEyebrow') }}
        </p>
        <h2 class="mt-1 text-lg font-semibold">{{ t('events.rallies.manageTitle') }}</h2>
        <section
          v-if="rallyBuilder.length"
          class="mt-4"
          :aria-label="t('events.rallies.builder.title')"
        >
          <div>
            <h3 class="font-semibold">{{ t('events.rallies.builder.title') }}</h3>
            <p class="mt-1 max-w-3xl text-sm text-[var(--ks-text-muted)]">
              {{ t('events.rallies.builder.help') }}
            </p>
          </div>
          <div class="mt-3 grid gap-3 xl:grid-cols-2">
            <article
              v-for="builder in rallyBuilder"
              :key="builder.occurrenceId"
              class="ks-surface p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p class="font-semibold">
                    {{
                      formatDate(new Date(builder.startsAt), {
                        month: 'short',
                        day: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                      })
                    }}
                  </p>
                  <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                    {{
                      t('events.rallies.builder.summary', {
                        groups: builder.groupCount,
                        leads: builder.leadCount,
                        joiners: builder.joinerCount,
                        standbys: builder.standbyCount,
                      })
                    }}
                  </p>
                </div>
                <span class="ks-chip">{{
                  t(`events.rallies.builder.states.${builder.state}`)
                }}</span>
              </div>
              <p
                v-if="builder.state === 'empty'"
                class="mt-3 text-sm text-[var(--ks-text-muted)]"
                role="status"
              >
                {{ t('events.rallies.builder.empty') }}
              </p>
              <ul v-else-if="builder.issues.length" class="mt-3 space-y-2">
                <li
                  v-for="issue in builder.issues"
                  :key="issue.code"
                  class="rounded border px-3 py-2 text-sm"
                  :class="
                    issue.severity === 'blocking'
                      ? 'border-red-400/30 bg-red-500/10 text-red-100'
                      : 'border-amber-400/30 bg-amber-500/10 text-amber-100'
                  "
                >
                  {{ t(`events.rallies.builder.issues.${issue.code}`, { count: issue.count }) }}
                </li>
              </ul>
              <p v-else class="mt-3 text-sm text-emerald-200" role="status">
                {{ t('events.rallies.builder.ready') }}
              </p>
              <p
                v-if="builder.observationState === 'unavailable'"
                class="mt-3 text-xs text-[var(--ks-text-muted)]"
              >
                {{ t('events.rallies.builder.observationsUnavailable') }}
              </p>
            </article>
          </div>
        </section>
        <div class="mt-5 grid gap-5 xl:grid-cols-2">
          <form
            v-if="event.workflowDimensions.includes('rallies')"
            class="ks-surface space-y-3 p-4"
            @submit.prevent="saveGuidance"
          >
            <div class="flex items-center justify-between gap-2">
              <h3 class="font-semibold">{{ t('events.rallies.guidance') }}</h3>
              <button
                v-if="editingGuidanceId"
                type="button"
                class="text-xs font-semibold text-[var(--ks-text-muted)]"
                @click="resetGuidance"
              >
                {{ t('events.actions.cancel') }}
              </button>
            </div>
            <select v-model="guidanceForm.alliance_id" required class="ks-input w-full text-sm">
              <option
                v-for="alliance in firstRallyOccurrence?.alliances ?? []"
                :key="alliance.id"
                :value="alliance.id"
              >
                {{ alliance.name }}
              </option></select
            ><input
              v-model="guidanceForm.name"
              required
              :placeholder="t('events.rallies.guidanceName')"
              class="ks-input w-full text-sm"
            />
            <div class="grid grid-cols-3 gap-2">
              <input
                v-model.number="guidanceForm.infantry_percent"
                type="number"
                min="0"
                max="100"
                :aria-label="t('events.rallies.infantry')"
                class="ks-input text-sm"
              /><input
                v-model.number="guidanceForm.cavalry_percent"
                type="number"
                min="0"
                max="100"
                :aria-label="t('events.rallies.cavalry')"
                class="ks-input text-sm"
              /><input
                v-model.number="guidanceForm.archer_percent"
                type="number"
                min="0"
                max="100"
                :aria-label="t('events.rallies.archers')"
                class="ks-input text-sm"
              />
            </div>
            <input
              v-model="guidanceForm.hero_recommendations_text"
              :placeholder="t('events.rallies.heroesHelp')"
              class="ks-input w-full text-sm"
            /><textarea
              v-model="guidanceForm.lead_requirements"
              rows="2"
              :placeholder="t('events.rallies.leadRequirements')"
              class="ks-input w-full text-sm"
            /><textarea
              v-model="guidanceForm.joiner_guidance"
              rows="2"
              :placeholder="t('events.rallies.joinerGuidance')"
              class="ks-input w-full text-sm"
            />
            <div class="grid grid-cols-2 gap-2">
              <input
                v-model="guidanceForm.source"
                :placeholder="t('events.rallies.source')"
                class="ks-input text-sm"
              /><input
                v-model="guidanceForm.rationale"
                :placeholder="t('events.rallies.rationale')"
                class="ks-input text-sm"
              />
            </div>
            <button
              type="submit"
              :disabled="guidanceForm.processing"
              class="ks-command-button w-full"
            >
              {{ t('events.actions.save') }}
            </button>
          </form>
          <form
            v-if="event.workflowDimensions.includes('rallies')"
            class="ks-surface space-y-3 p-4"
            @submit.prevent="saveRallyFormation"
          >
            <div class="flex items-center justify-between gap-2">
              <h3 class="font-semibold">{{ t('events.rallies.recommendedFormations') }}</h3>
              <button
                v-if="editingRallyFormationId"
                type="button"
                class="text-xs font-semibold text-[var(--ks-text-muted)]"
                @click="resetRallyFormation"
              >
                {{ t('events.actions.cancel') }}
              </button>
            </div>
            <select
              v-model="rallyFormationForm.occurrence_id"
              class="ks-input w-full text-sm"
              @change="syncRallyAlliance(rallyFormationForm.occurrence_id)"
            >
              <option
                v-for="occurrence in rallyOperations"
                :key="occurrence.occurrenceId"
                :value="occurrence.occurrenceId"
              >
                {{
                  formatDate(new Date(occurrence.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </option></select
            ><select
              v-model="rallyFormationForm.alliance_id"
              required
              class="ks-input w-full text-sm"
            >
              <option
                v-for="alliance in rallyOccurrence(rallyFormationForm.occurrence_id)?.alliances ??
                []"
                :key="alliance.id"
                :value="alliance.id"
              >
                {{ alliance.name }}
              </option>
            </select>
            <div class="grid grid-cols-2 gap-2">
              <input
                v-model="rallyFormationForm.key"
                required
                :placeholder="t('events.rallies.key')"
                class="ks-input text-sm"
              /><input
                v-model="rallyFormationForm.name"
                required
                :placeholder="t('events.rallies.formationName')"
                class="ks-input text-sm"
              />
            </div>
            <select v-model="rallyFormationForm.guidance_rule_id" class="ks-input w-full text-sm">
              <option value="">{{ t('events.rallies.noGuidanceRule') }}</option>
              <option
                v-for="rule in rallyOccurrence(rallyFormationForm.occurrence_id)?.guidance.filter(
                  (item) => item.allianceId === rallyFormationForm.alliance_id,
                ) ?? []"
                :key="rule.id"
                :value="rule.id"
              >
                {{ rule.name }}
              </option>
            </select>
            <div class="grid grid-cols-3 gap-2">
              <input
                v-model.number="rallyFormationForm.infantry_percent"
                type="number"
                min="0"
                max="100"
                class="ks-input text-sm"
              /><input
                v-model.number="rallyFormationForm.cavalry_percent"
                type="number"
                min="0"
                max="100"
                class="ks-input text-sm"
              /><input
                v-model.number="rallyFormationForm.archer_percent"
                type="number"
                min="0"
                max="100"
                class="ks-input text-sm"
              />
            </div>
            <input
              v-model="rallyFormationForm.heroes_text"
              :placeholder="t('events.rallies.heroesHelp')"
              class="ks-input w-full text-sm"
            /><button
              type="submit"
              :disabled="rallyFormationForm.processing"
              class="ks-command-button w-full"
            >
              {{ t('events.actions.save') }}
            </button>
          </form>
        </div>
        <div
          v-if="event.workflowDimensions.includes('rallies')"
          class="mt-5 grid gap-5 xl:grid-cols-2"
        >
          <form class="ks-surface space-y-3 p-4" @submit.prevent="saveRallyGroup">
            <h3 class="font-semibold">{{ t('events.rallies.groups') }}</h3>
            <select
              v-model="rallyGroupForm.occurrence_id"
              class="ks-input w-full text-sm"
              @change="syncRallyAlliance(rallyGroupForm.occurrence_id)"
            >
              <option
                v-for="occurrence in rallyOperations"
                :key="occurrence.occurrenceId"
                :value="occurrence.occurrenceId"
              >
                {{
                  formatDate(new Date(occurrence.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </option></select
            ><select v-model="rallyGroupForm.alliance_id" required class="ks-input w-full text-sm">
              <option
                v-for="alliance in rallyOccurrence(rallyGroupForm.occurrence_id)?.alliances ?? []"
                :key="alliance.id"
                :value="alliance.id"
              >
                {{ alliance.name }}
              </option></select
            ><input
              v-model="rallyGroupForm.name"
              required
              :placeholder="t('events.rallies.groupName')"
              class="ks-input w-full text-sm"
            /><select
              v-model="rallyGroupForm.recommended_formation_id"
              class="ks-input w-full text-sm"
            >
              <option value="">{{ t('events.rallies.noRecommendedFormation') }}</option>
              <option
                v-for="formation in rallyOccurrence(
                  rallyGroupForm.occurrence_id,
                )?.recommendations.filter(
                  (item) => item.allianceId === rallyGroupForm.alliance_id,
                ) ?? []"
                :key="formation.id"
                :value="formation.id"
              >
                {{ formation.name }}
              </option></select
            ><input
              v-model.number="rallyGroupForm.max_joiners"
              type="number"
              min="1"
              :placeholder="t('events.rallies.maxJoiners')"
              class="ks-input w-full text-sm"
            /><button
              type="submit"
              :disabled="rallyGroupForm.processing"
              class="ks-command-button w-full"
            >
              {{ t('events.actions.save') }}
            </button>
          </form>
          <form class="ks-surface space-y-3 p-4" @submit.prevent="assignRallyPlayer">
            <h3 class="font-semibold">{{ t('events.rallies.assignPlayer') }}</h3>
            <select
              v-model="rallyAssignmentForm.occurrence_id"
              class="ks-input w-full text-sm"
              @change="
                rallyAssignmentForm.group_id = '';
                rallyAssignmentForm.player_id = '';
              "
            >
              <option
                v-for="occurrence in rallyOperations"
                :key="occurrence.occurrenceId"
                :value="occurrence.occurrenceId"
              >
                {{
                  formatDate(new Date(occurrence.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </option></select
            ><select
              v-model="rallyAssignmentForm.group_id"
              required
              class="ks-input w-full text-sm"
              @change="rallyAssignmentForm.player_id = ''"
            >
              <option value="" disabled>{{ t('events.rallies.group') }}</option>
              <option
                v-for="group in rallyOccurrence(rallyAssignmentForm.occurrence_id)?.groups ?? []"
                :key="group.id"
                :value="group.id"
              >
                {{ group.allianceName }} · {{ group.name }}
              </option></select
            ><select
              v-model="rallyAssignmentForm.player_id"
              required
              class="ks-input w-full text-sm"
            >
              <option value="" disabled>{{ t('events.rallies.player') }}</option>
              <option
                v-for="candidate in rallyOccurrence(rallyAssignmentForm.occurrence_id)
                  ?.candidatesByAlliance[
                  rallyGroup(rallyAssignmentForm.occurrence_id, rallyAssignmentForm.group_id)
                    ?.allianceId ?? ''
                ] ?? []"
                :key="candidate.playerId"
                :value="candidate.playerId"
              >
                {{ candidate.name }}
              </option>
            </select>
            <div class="grid grid-cols-2 gap-2">
              <select v-model="rallyAssignmentForm.role" class="ks-input text-sm">
                <option value="lead">{{ t('events.rallies.roles.lead') }}</option>
                <option value="joiner">{{ t('events.rallies.roles.joiner') }}</option>
                <option value="standby">{{ t('events.rallies.roles.standby') }}</option></select
              ><input
                v-model.number="rallyAssignmentForm.slot_number"
                type="number"
                min="1"
                :placeholder="t('events.rallies.slot')"
                class="ks-input text-sm"
              />
            </div>
            <textarea
              v-model="rallyAssignmentForm.notes"
              rows="2"
              :placeholder="t('events.rallies.notes')"
              class="ks-input w-full text-sm"
            /><button
              type="submit"
              :disabled="rallyAssignmentForm.processing"
              class="ks-command-button w-full"
            >
              {{ t('events.rallies.assign') }}
            </button>
          </form>
        </div>
        <div class="mt-5 space-y-4">
          <article
            v-for="occurrence in rallyOperations"
            :key="occurrence.occurrenceId"
            class="ks-surface p-4"
          >
            <div class="text-sm font-semibold">
              {{
                formatDate(new Date(occurrence.startsAt), {
                  month: 'short',
                  day: 'numeric',
                  hour: 'numeric',
                  minute: '2-digit',
                })
              }}
            </div>
            <div
              v-if="occurrence.guidance.length || occurrence.recommendations.length"
              class="mt-3 grid gap-2 lg:grid-cols-2"
            >
              <button
                v-for="rule in occurrence.guidance"
                :key="rule.id"
                type="button"
                class="rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-3 text-left text-sm"
                @click="editGuidance(rule)"
              >
                <span class="font-semibold">{{ rule.allianceName }} · {{ rule.name }}</span
                ><span class="mt-1 block text-xs text-[var(--ks-text-muted)]"
                  >{{ rule.infantryPercent }}/{{ rule.cavalryPercent }}/{{
                    rule.archerPercent
                  }}</span
                ></button
              ><button
                v-for="formation in occurrence.recommendations"
                :key="formation.id"
                type="button"
                class="rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-3 text-left text-sm"
                @click="editRallyFormation(occurrence.occurrenceId, formation)"
              >
                <span class="font-semibold"
                  >{{ formation.allianceName }} · {{ formation.name }}</span
                ><span class="mt-1 block text-xs text-[var(--ks-text-muted)]"
                  >{{ formation.infantryPercent }}/{{ formation.cavalryPercent }}/{{
                    formation.archerPercent
                  }}</span
                >
              </button>
            </div>
            <div class="mt-3 grid gap-3 lg:grid-cols-2">
              <div
                v-for="group in occurrence.groups"
                :key="group.id"
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-quiet)] bg-black/15 p-3"
              >
                <div class="flex items-center justify-between gap-2">
                  <button
                    type="button"
                    class="text-left font-semibold"
                    @click="editRallyGroup(occurrence.occurrenceId, group)"
                  >
                    {{ group.allianceName }} · {{ group.name }}</button
                  ><span v-if="group.maxJoiners" class="text-xs text-[var(--ks-text-muted)]"
                    >{{ group.activeJoiners }}/{{ group.maxJoiners }}</span
                  >
                </div>
                <div class="mt-2 space-y-2">
                  <div
                    v-for="assignment in group.assignments"
                    :key="assignment.id"
                    class="flex flex-wrap items-center justify-between gap-2 text-sm"
                  >
                    <span
                      >{{ assignment.playerName }} ·
                      {{ t(`events.rallies.roles.${assignment.role}`) }} ·
                      {{ t(`events.rallies.status.${assignment.status}`) }}</span
                    ><span class="flex gap-1"
                      ><button
                        type="button"
                        class="rounded border border-emerald-500/30 px-2 py-1 text-xs text-emerald-100"
                        @click="
                          recordRallyParticipation(
                            occurrence.occurrenceId,
                            assignment.id,
                            'participated',
                          )
                        "
                      >
                        {{ t('events.rallies.participated') }}</button
                      ><button
                        type="button"
                        class="rounded border border-amber-500/30 px-2 py-1 text-xs text-amber-100"
                        @click="
                          recordRallyParticipation(occurrence.occurrenceId, assignment.id, 'absent')
                        "
                      >
                        {{ t('events.rallies.absent') }}</button
                      ><button
                        type="button"
                        class="rounded border border-red-500/30 px-2 py-1 text-xs text-red-100"
                        @click="
                          removeRallyPlayer(occurrence.occurrenceId, group.id, assignment.playerId)
                        "
                      >
                        {{ t('events.rallies.remove') }}
                      </button></span
                    >
                  </div>
                  <p v-if="!group.assignments.length" class="text-xs text-[var(--ks-text-muted)]">
                    {{ t('events.rallies.noAssignments') }}
                  </p>
                </div>
              </div>
            </div>
          </article>
        </div>
      </section>

      <section
        v-if="event.workflowDimensions.includes('battle_assignments')"
        id="battle-plan"
        class="ks-surface mt-5 scroll-mt-28 p-5"
      >
        <div>
          <p class="ks-kicker">
            {{ t('events.objectives.eyebrow') }}
          </p>
          <h2 class="mt-1 text-lg font-semibold">{{ t('events.objectives.manageTitle') }}</h2>
        </div>
        <div class="mt-4 grid gap-5 xl:grid-cols-2">
          <form class="ks-surface space-y-3 p-4" @submit.prevent="saveObjective">
            <div class="flex items-center justify-between gap-3">
              <h3 class="font-semibold">
                {{ editingObjectiveId ? t('events.objectives.edit') : t('events.objectives.add') }}
              </h3>
              <button
                v-if="editingObjectiveId"
                type="button"
                class="text-xs text-[var(--ks-text-muted)]"
                @click="resetObjective"
              >
                {{ t('events.actions.cancel') }}
              </button>
            </div>
            <select v-model="objectiveForm.occurrence_id" class="ks-input w-full text-sm">
              <option
                v-for="occurrence in battlePlan"
                :key="occurrence.occurrenceId"
                :value="occurrence.occurrenceId"
              >
                {{
                  formatDate(new Date(occurrence.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </option>
            </select>
            <select v-model="objectiveForm.parent_id" class="ks-input w-full text-sm">
              <option value="">{{ t('events.objectives.noParent') }}</option>
              <option
                v-for="objective in battleOccurrence(objectiveForm.occurrence_id)?.objectives ?? []"
                :key="objective.id"
                :value="objective.id"
                :disabled="objective.id === editingObjectiveId"
              >
                {{ objective.name }}
              </option>
            </select>
            <div class="grid grid-cols-2 gap-2">
              <input
                v-model="objectiveForm.name"
                required
                :placeholder="t('events.objectives.name')"
                class="ks-input text-sm"
              /><input
                v-model="objectiveForm.objective_type"
                required
                :placeholder="t('events.objectives.type')"
                class="ks-input text-sm"
              />
            </div>
            <textarea
              v-model="objectiveForm.description"
              rows="3"
              :placeholder="t('events.objectives.description')"
              class="ks-input w-full text-sm"
            />
            <div class="grid grid-cols-2 gap-2">
              <input
                v-model.number="objectiveForm.priority"
                type="number"
                min="0"
                max="100"
                :aria-label="t('events.objectives.priority')"
                class="ks-input text-sm"
              /><select v-model="objectiveForm.status" class="ks-input text-sm">
                <option
                  v-for="status in ['planned', 'active', 'completed', 'failed', 'cancelled']"
                  :key="status"
                  :value="status"
                >
                  {{ t(`events.objectives.status.${status}`) }}
                </option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <input
                v-model="objectiveForm.starts_at"
                type="datetime-local"
                :aria-label="t('events.objectives.starts')"
                class="ks-input text-sm"
              /><input
                v-model="objectiveForm.ends_at"
                type="datetime-local"
                :aria-label="t('events.objectives.ends')"
                class="ks-input text-sm"
              />
            </div>
            <button
              type="submit"
              :disabled="objectiveForm.processing"
              class="ks-command-button w-full"
            >
              {{ t('events.actions.save') }}
            </button>
          </form>
          <form class="ks-surface space-y-3 p-4" @submit.prevent="assignObjective">
            <h3 class="font-semibold">{{ t('events.objectives.assign') }}</h3>
            <select
              v-model="objectiveAssignmentForm.occurrence_id"
              class="ks-input w-full text-sm"
              @change="
                objectiveAssignmentForm.objective_id = '';
                objectiveAssignmentForm.player_id = '';
                objectiveAssignmentForm.roster_id = '';
              "
            >
              <option
                v-for="occurrence in battlePlan"
                :key="occurrence.occurrenceId"
                :value="occurrence.occurrenceId"
              >
                {{
                  formatDate(new Date(occurrence.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </option>
            </select>
            <select
              v-model="objectiveAssignmentForm.objective_id"
              required
              class="ks-input w-full text-sm"
            >
              <option value="" disabled>{{ t('events.objectives.objective') }}</option>
              <option
                v-for="objective in battleOccurrence(objectiveAssignmentForm.occurrence_id)
                  ?.objectives ?? []"
                :key="objective.id"
                :value="objective.id"
              >
                {{ objective.name }}
              </option>
            </select>
            <select v-model="objectiveAssignmentForm.target_type" class="ks-input w-full text-sm">
              <option value="player">{{ t('events.objectives.player') }}</option>
              <option value="roster">{{ t('events.objectives.roster') }}</option>
            </select>
            <select
              v-if="objectiveAssignmentForm.target_type === 'player'"
              v-model="objectiveAssignmentForm.player_id"
              required
              class="ks-input w-full text-sm"
            >
              <option value="" disabled>{{ t('events.objectives.player') }}</option>
              <option
                v-for="player in battleOccurrence(objectiveAssignmentForm.occurrence_id)?.players ??
                []"
                :key="player.id"
                :value="player.id"
              >
                {{ player.name }}
              </option>
            </select>
            <select
              v-else
              v-model="objectiveAssignmentForm.roster_id"
              required
              class="ks-input w-full text-sm"
            >
              <option value="" disabled>{{ t('events.objectives.roster') }}</option>
              <option
                v-for="roster in battleOccurrence(objectiveAssignmentForm.occurrence_id)?.rosters ??
                []"
                :key="roster.id"
                :value="roster.id"
              >
                {{ roster.name || (roster.nameKey ? t(roster.nameKey) : roster.key) }}
              </option>
            </select>
            <textarea
              v-model="objectiveAssignmentForm.notes"
              rows="2"
              :placeholder="t('events.objectives.assignmentNotes')"
              class="ks-input w-full text-sm"
            />
            <button type="submit" class="ks-command-button w-full">
              {{ t('events.objectives.assign') }}
            </button>
          </form>
        </div>
        <div class="mt-5 space-y-4">
          <article
            v-for="occurrence in battlePlan"
            :key="occurrence.occurrenceId"
            class="ks-surface p-4"
          >
            <div class="text-sm font-semibold">
              {{
                formatDate(new Date(occurrence.startsAt), {
                  month: 'short',
                  day: 'numeric',
                  hour: 'numeric',
                  minute: '2-digit',
                })
              }}
            </div>
            <div class="mt-3 space-y-2">
              <div
                v-for="objective in occurrence.objectives"
                :key="objective.id"
                class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-quiet)] bg-black/15 p-3"
              >
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <button
                    type="button"
                    class="text-left"
                    @click="editObjective(occurrence.occurrenceId, objective)"
                  >
                    <span class="font-semibold">{{ objective.name }}</span
                    ><span class="ms-2 text-xs text-[var(--ks-text-muted)]"
                      >P{{ objective.priority }} ·
                      {{ t(`events.objectives.status.${objective.status}`) }}</span
                    ></button
                  ><span class="text-xs text-[var(--ks-text-muted)]">{{ objective.type }}</span>
                </div>
                <p
                  v-if="objective.description"
                  class="mt-2 text-sm text-[var(--ks-text-secondary)]"
                >
                  {{ objective.description }}
                </p>
                <div v-if="objective.assignments.length" class="mt-3 flex flex-wrap gap-2">
                  <span
                    v-for="assignment in objective.assignments"
                    :key="assignment.id"
                    class="inline-flex items-center gap-2 rounded border border-[var(--ks-border)] px-2 py-1 text-xs"
                    ><span>{{ objectiveTargetLabel(assignment) }}</span
                    ><button
                      type="button"
                      class="text-red-200"
                      @click="removeObjectiveAssignment(occurrence.occurrenceId, assignment.id)"
                    >
                      ×
                    </button></span
                  >
                </div>
              </div>
              <p v-if="!occurrence.objectives.length" class="text-sm text-[var(--ks-text-muted)]">
                {{ t('events.objectives.none') }}
              </p>
            </div>
          </article>
        </div>
      </section>

      <section
        v-if="event.workflowDimensions.includes('results')"
        id="results"
        class="ks-surface mt-5 scroll-mt-28 p-5"
      >
        <div>
          <p class="ks-kicker">
            {{ t('events.results.eyebrow') }}
          </p>
          <h2 class="mt-1 text-lg font-semibold">{{ t('events.results.manageTitle') }}</h2>
        </div>
        <div class="mt-4 grid gap-5 xl:grid-cols-2">
          <form class="ks-surface space-y-3 p-4" @submit.prevent="saveResult">
            <h3 class="font-semibold">{{ t('events.results.occurrenceResult') }}</h3>
            <select
              v-model="resultForm.occurrence_id"
              class="ks-input w-full text-sm"
              @change="loadResultOccurrence(resultForm.occurrence_id)"
            >
              <option
                v-for="occurrence in resultOperations"
                :key="occurrence.occurrenceId"
                :value="occurrence.occurrenceId"
              >
                {{
                  formatDate(new Date(occurrence.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </option></select
            ><input
              v-model="resultForm.outcome"
              :placeholder="t('events.results.outcome')"
              class="ks-input w-full text-sm"
            />
            <div class="grid grid-cols-3 gap-2">
              <input
                v-model.number="resultForm.score"
                type="number"
                min="0"
                :placeholder="t('events.results.score')"
                class="ks-input text-sm"
              /><input
                v-model.number="resultForm.opponent_score"
                type="number"
                min="0"
                :placeholder="t('events.results.opponentScore')"
                class="ks-input text-sm"
              /><input
                v-model.number="resultForm.rank"
                type="number"
                min="1"
                :placeholder="t('events.results.rank')"
                class="ks-input text-sm"
              />
            </div>
            <textarea
              v-model="resultForm.notes"
              rows="2"
              :placeholder="t('events.results.notes')"
              class="ks-input w-full text-sm"
            /><button type="submit" class="ks-command-button w-full">
              {{ t('events.actions.save') }}
            </button>
          </form>
          <form class="ks-surface space-y-3 p-4" @submit.prevent="savePlayerResult">
            <h3 class="font-semibold">{{ t('events.results.playerResult') }}</h3>
            <select
              v-model="playerResultForm.occurrence_id"
              class="ks-input w-full text-sm"
              @change="
                playerResultForm.player_id = '';
                loadPlayerResult();
              "
            >
              <option
                v-for="occurrence in resultOperations"
                :key="occurrence.occurrenceId"
                :value="occurrence.occurrenceId"
              >
                {{
                  formatDate(new Date(occurrence.startsAt), {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                  })
                }}
              </option></select
            ><select
              v-model="playerResultForm.player_id"
              required
              class="ks-input w-full text-sm"
              @change="loadPlayerResult"
            >
              <option value="" disabled>{{ t('events.results.player') }}</option>
              <option
                v-for="player in resultOccurrence(playerResultForm.occurrence_id)?.players ?? []"
                :key="player.id"
                :value="player.id"
              >
                {{ player.name }}
              </option></select
            ><input
              v-model="playerResultForm.outcome"
              :placeholder="t('events.results.outcome')"
              class="ks-input w-full text-sm"
            />
            <div class="grid grid-cols-2 gap-2">
              <input
                v-model.number="playerResultForm.score"
                type="number"
                min="0"
                :placeholder="t('events.results.score')"
                class="ks-input text-sm"
              /><input
                v-model.number="playerResultForm.rank"
                type="number"
                min="1"
                :placeholder="t('events.results.rank')"
                class="ks-input text-sm"
              />
            </div>
            <textarea
              v-model="playerResultForm.notes"
              rows="2"
              :placeholder="t('events.results.notes')"
              class="ks-input w-full text-sm"
            /><button type="submit" class="ks-command-button w-full">
              {{ t('events.actions.save') }}
            </button>
          </form>
        </div>
        <div v-if="playerIntelligence.length" class="mt-5 overflow-x-auto">
          <h3 class="mb-3 font-semibold">{{ t('events.results.intelligence') }}</h3>
          <table class="w-full min-w-[52rem] text-left text-sm">
            <caption class="sr-only">
              {{
                t('events.results.intelligence')
              }}
            </caption>
            <thead class="text-xs text-[var(--ks-text-muted)] uppercase">
              <tr>
                <th class="pb-2">{{ t('events.results.player') }}</th>
                <th class="pb-2">{{ t('events.results.commitments') }}</th>
                <th class="pb-2">{{ t('events.results.completed') }}</th>
                <th class="pb-2">{{ t('events.results.absent') }}</th>
                <th class="pb-2">{{ t('events.results.reliability') }}</th>
                <th class="pb-2">{{ t('events.results.averageScore') }}</th>
                <th class="pb-2">{{ t('events.results.bestScore') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ks-border)]">
              <tr v-for="row in playerIntelligence" :key="row.playerId">
                <td class="py-3 font-semibold">{{ row.playerName }}</td>
                <td>{{ row.commitments }}</td>
                <td>{{ row.completed }}</td>
                <td>{{ row.absent }}</td>
                <td>{{ row.reliabilityPercent === null ? '—' : `${row.reliabilityPercent}%` }}</td>
                <td>{{ row.averageScore ?? '—' }}</td>
                <td>{{ row.bestScore ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section
        v-if="event.workflowDimensions.includes('participation')"
        id="participants"
        class="ks-surface mt-5 scroll-mt-28 p-5"
      >
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="ks-kicker">
              {{ t('events.manage.participationEyebrow') }}
            </p>
            <h2 class="mt-1 text-lg font-semibold">{{ t('events.manage.participants') }}</h2>
          </div>
          <span class="text-xs text-[var(--ks-text-muted)]">{{ participants.length }}</span>
        </div>
        <div v-if="participants.length" class="mt-4 overflow-x-auto">
          <table class="w-full min-w-[46rem] text-left text-sm">
            <caption class="sr-only">
              {{
                t('events.manage.participants')
              }}
            </caption>
            <thead class="text-xs text-[var(--ks-text-muted)] uppercase">
              <tr>
                <th class="pb-2">{{ t('events.manage.player') }}</th>
                <th class="pb-2">{{ t('events.manage.response') }}</th>
                <th class="pb-2">{{ t('events.manage.registration') }}</th>
                <th class="pb-2">{{ t('events.manage.attendance') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ks-border)]">
              <tr v-for="row in participants" :key="`${row.occurrenceId}:${row.playerId}`">
                <td class="py-3">
                  <div class="font-semibold">{{ row.playerName }}</div>
                  <div class="text-xs text-[var(--ks-text-muted)]">
                    {{
                      event.occurrences.find((item) => item.id === row.occurrenceId)
                        ? formatDate(
                            new Date(
                              event.occurrences.find((item) => item.id === row.occurrenceId)!
                                .startsAt,
                            ),
                            { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' },
                          )
                        : ''
                    }}
                  </div>
                </td>
                <td class="py-3">
                  {{ row.response ? t(`events.responses.${row.response}`) : '—' }}
                </td>
                <td class="py-3">
                  {{ row.registration ? t(`events.registration.${row.registration}`) : '—'
                  }}<span v-if="row.waitlistPosition"> #{{ row.waitlistPosition }}</span>
                </td>
                <td class="py-3">
                  <div
                    v-if="event.workflowDimensions.includes('participation')"
                    class="flex flex-wrap gap-1"
                  >
                    <button
                      v-for="status in ['present', 'absent', 'excused', 'unknown'] as const"
                      :key="status"
                      type="button"
                      class="rounded border px-2 py-1 text-xs"
                      :class="
                        row.attendance === status
                          ? 'border-[var(--ks-gold)] bg-[var(--ks-gold)] text-[var(--ks-ink)]'
                          : 'border-[var(--ks-border)]'
                      "
                      :aria-pressed="row.attendance === status"
                      @click="setAttendance(row.occurrenceId, row.playerId, status)"
                    >
                      {{ t(`events.attendanceStatuses.${status}`) }}
                    </button>
                  </div>
                  <span v-else>{{
                    row.attendance ? t(`events.attendanceStatuses.${row.attendance}`) : '—'
                  }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">
          {{ t('events.manage.noParticipants') }}
        </p>
      </section>

      <section id="reminders" class="ks-surface mt-5 scroll-mt-28 p-5">
        <p class="ks-kicker">
          {{ t('events.manage.reminderEyebrow') }}
        </p>
        <h2 class="mt-1 text-lg font-semibold">{{ t('events.manage.reminders') }}</h2>
        <div class="mt-4 grid gap-5 md:grid-cols-[minmax(0,22rem)_1fr]">
          <form class="space-y-3" @submit.prevent="saveReminder">
            <label class="block text-sm font-semibold"
              >{{ t('events.manage.minutesBefore')
              }}<input
                v-model.number="reminderForm.minutes_before"
                type="number"
                min="1"
                max="10080"
                class="ks-input mt-1 w-full" /></label
            ><label class="block text-sm font-semibold"
              >{{ t('events.manage.audience')
              }}<select v-model="reminderForm.audience" class="ks-input mt-1 w-full">
                <option v-for="audience in reminderAudiences" :key="audience" :value="audience">
                  {{ t(`events.reminderAudiences.${audience}`) }}
                </option>
              </select></label
            ><button type="submit" :disabled="reminderForm.processing" class="ks-command-button">
              {{ t('events.manage.addReminder') }}
            </button>
          </form>
          <div class="space-y-2">
            <div
              v-for="rule in reminderRules"
              :key="rule.id"
              class="ks-surface flex items-center justify-between gap-3 p-3 text-sm"
            >
              <div>
                <span class="font-semibold"
                  >{{ rule.minutesBefore }} {{ t('events.manage.minutes') }}</span
                ><span class="ms-2 text-[var(--ks-text-muted)]"
                  >{{ t(`events.reminderAudiences.${rule.audience}`) }} ·
                  {{
                    rule.trigger === 'before_poll_close'
                      ? t('events.manage.pollDeadline')
                      : t('events.manage.eventStart')
                  }}</span
                ><span v-if="!rule.enabled" class="ms-2 text-xs text-[var(--ks-text-muted)]">{{
                  t('events.manage.reminderDisabled')
                }}</span>
              </div>
              <button
                v-if="rule.enabled"
                type="button"
                class="rounded border border-[var(--ks-border)] px-2 py-1 text-xs font-semibold"
                @click="disableReminder(rule.id)"
              >
                {{ t('events.manage.disableReminder') }}
              </button>
            </div>
            <p v-if="reminderRules.length === 0" class="text-sm text-[var(--ks-text-muted)]">
              {{ t('events.manage.noReminders') }}
            </p>
          </div>
        </div>
      </section>
    </div>
    <ConfirmActionDialog v-bind="dialog" @confirm="confirmAction" @cancel="cancelConfirmation" />
  </AppLayout>
</template>
