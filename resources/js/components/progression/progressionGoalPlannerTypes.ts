export type ProgressionPlannerFamily = {
  id: string;
  label: string;
  calculatorFamily: string | null;
};

export type ProgressionPlannerSubject = {
  id: string;
  label: string;
  status: string;
};

export type ProgressionPlannerPrerequisite = {
  label: string;
  status: string;
};

export type ProgressionPlannerState = {
  id: string;
  label: string;
  ordinal: number;
  facts: Record<string, string | number>;
  sourceIds: string[];
  evidenceStatus: string | null;
  prerequisites: ProgressionPlannerPrerequisite[];
};

export type ProgressionPlannerProvenance = {
  capturedAt: string | null;
  observationId: string | null;
  evidenceId: string | null;
  reviewId: string | null;
  datasetId: string | null;
  datasetChecksum: string | null;
};

export type ProgressionPlannerCurrent = {
  status: string;
  state: ProgressionPlannerState | null;
  provenance: ProgressionPlannerProvenance | null;
};

export type ProgressionPlannerComparison = {
  status: string;
  remainingTransitions: number | null;
  path: Array<Pick<ProgressionPlannerState, 'id' | 'label' | 'sourceIds' | 'evidenceStatus'>>;
  prerequisites: ProgressionPlannerPrerequisite[];
};

export type ProgressionGoalPlanner = {
  availability: string;
  dataset: {
    id: string;
    version: string;
    schemaVersion: number;
    checksum: string;
    observedAt: string;
  };
  families: ProgressionPlannerFamily[];
  selection: {
    family: string | null;
    subjectId: string | null;
    targetStateId: string | null;
  };
  subjects: ProgressionPlannerSubject[];
  states: ProgressionPlannerState[];
  current: ProgressionPlannerCurrent | null;
  target: ProgressionPlannerState | null;
  comparison: ProgressionPlannerComparison | null;
  conflicts: Array<Record<string, unknown>>;
};

export type CalculatorQualificationReport = {
  family: string;
  status:
    | 'calculator_ready'
    | 'evidence_incomplete'
    | 'source_gap'
    | 'evidence_conflict'
    | 'evidence_review'
    | 'disabled';
  reason: string;
  reviewedAt: string;
  datasetId: string;
  datasetVersion: string;
  datasetChecksum: string;
  sourceIds: string[];
  gates: Record<string, { status: 'pass' | 'fail'; reason: string }>;
};
