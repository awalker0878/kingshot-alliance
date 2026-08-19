export type AppointmentType = {
  key: string;
  label: string;
  durationMinutes: number;
  playerCooldownMinutes: number;
  playerCooldownAnchor: string;
  cancelledPositionCooldownMinutes: number;
  recommendedFocus: string;
};

export type PushCategory = {
  key: string;
  label: string;
  preferredAppointmentTypes: string[];
};

export type SkillType = {
  key: string;
  label: string;
  recommendedFocus: string;
  advanceSchedulingMinutes: number;
};

export type Appointment = {
  id: string;
  type: string;
  typeLabel: string;
  playerId: string;
  playerName: string | null;
  playerEligible: boolean;
  startsAt: string;
  endsAt: string;
  durationMinutes: number;
  playerCooldownMinutes: number;
  playerCooldownAnchor: string;
  status: string;
  confirmedAt: string | null;
  actualStartedAt: string | null;
  actualEndedAt: string | null;
  notes: string | null;
};

export type PerkRequest = {
  id: string;
  playerId: string;
  playerName: string | null;
  category: string;
  categoryLabel: string;
  preferredAppointmentType: string | null;
  availabilityStartsAt: string;
  availabilityEndsAt: string;
  plannedSpeedupMinutes: number | null;
  plannedResourceAmount: number | null;
  status: string;
  scheduledAppointmentId: string | null;
  notes: string | null;
};

export type Skill = {
  id: string;
  key: string;
  label: string;
  plannedActivationAt: string;
  plannedEndsAt: string;
  effectDurationMinutes: number;
  scheduleAvailableAt: string;
  status: string;
  notes: string | null;
};

export type Plan = {
  id: string;
  status: string;
  windowStartsAt: string;
  windowEndsAt: string;
  publishedAt: string | null;
  appointments: Appointment[];
  positionBlocks: Array<{
    id: string;
    type: string;
    startsAt: string;
    endsAt: string;
    reason: string;
  }>;
  skills: Skill[];
  requests: PerkRequest[];
};

export type LiveLane = {
  type: string;
  label: string;
  now: Appointment | null;
  next: Appointment | null;
  following: Appointment | null;
};

export type LiveCourt = {
  generatedAt: string;
  lanes: LiveLane[];
};

export type StrategyDay = {
  day: number;
  startsAt: string;
  endsAt: string;
  focus: string | null;
  skill: string | null;
  appointmentTypes: string[];
  strategyNote: string;
};

export type GovernorOption = {
  id: string;
  name: string;
};
