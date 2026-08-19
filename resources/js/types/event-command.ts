export type EventSettingScalar = string | number | boolean | null;
export type EventSettingLeaf = EventSettingScalar | EventSettingScalar[];
export type EventSettingValue = EventSettingLeaf | Record<string, EventSettingLeaf>;

export type EventTemplateForm = {
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

export type Phase = {
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

export type PollOption = {
  id: string;
  label: string;
  value: string;
  metadata: Record<string, unknown>;
  votes: number | null;
};

export type Poll = {
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

export type OccurrenceOperations = {
  occurrenceId: string;
  startsAt: string;
  phases: Phase[];
  polls: Poll[];
};

export type RosterMember = {
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

export type EventRoster = {
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

export type RosterCandidate = {
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

export type RosterOccurrence = {
  occurrenceId: string;
  startsAt: string;
  rosters: EventRoster[];
  candidates: RosterCandidate[];
};

export type RallyGuidance = {
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

export type RallyRecommendation = {
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

export type RallyAssignment = {
  id: string;
  playerId: string;
  playerName: string;
  role: 'lead' | 'joiner' | 'standby';
  slotNumber: number | null;
  status: string;
  notes: string | null;
};

export type RallyGroup = {
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

export type RallyCandidate = { playerId: string; name: string; claimed: boolean };

export type RallyOccurrence = {
  occurrenceId: string;
  startsAt: string;
  alliances: Array<{ id: string; name: string }>;
  guidance: RallyGuidance[];
  recommendations: RallyRecommendation[];
  groups: RallyGroup[];
  candidatesByAlliance: Record<string, RallyCandidate[]>;
};

export type ObjectiveAssignment = {
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

export type EventObjective = {
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

export type BattlePlanOccurrence = {
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

export type ResultSummary = {
  id: string;
  outcome: string | null;
  score: number | null;
  opponentScore: number | null;
  rank: number | null;
  metrics: Record<string, unknown>;
  notes: string | null;
  recordedAt: string | null;
};

export type PlayerResult = {
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

export type ResultOccurrence = {
  occurrenceId: string;
  startsAt: string;
  summary: ResultSummary | null;
  playerResults: PlayerResult[];
  players: Array<{ id: string; name: string }>;
};

export type PlayerIntelligence = {
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
