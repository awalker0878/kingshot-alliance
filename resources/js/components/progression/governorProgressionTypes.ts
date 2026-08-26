export type GovernorProgressionHero = {
  id: string;
  name: string;
  generation: number;
  rarity: string;
  troopClass: string;
};

export type GovernorProgressionSchema = {
  kind: string;
  version: string;
  supportedFields: string[];
  requiredFields: string[];
  minimumClassificationConfidence: number;
  minimumFieldConfidence: number;
  fixtureCorpus: string;
  destinationAction: string;
};

export type GovernorProgressionMachineField = {
  field_id?: string;
  fieldKey?: string;
  field_key?: string;
  rowOrdinal?: number;
  row_ordinal?: number;
  rawText?: string;
  raw_text?: string;
  normalizedValue?: string | null;
  candidate?: string | null;
  confidence: number;
  warnings: string[];
  canonical_id?: string | null;
  identity_confidence?: number | null;
};

export type GovernorProgressionReview = {
  id: string;
  revisionNumber: number;
  status: string;
  kind: string;
  schemaVersion: string;
  datasetId: string;
  datasetChecksum: string;
  capturedAt: string;
  payload: Record<string, unknown>;
  semanticDuplicateReviewId: string | null;
  duplicateResolution: string | null;
};

export type GovernorProgressionEvidenceSummary = {
  id: string;
  expectedKind: string;
  detectedKind: string;
  lifecycleStatus: string;
  createdAt: string | null;
  imageAvailable: boolean;
  visualDuplicate: { evidenceId: string; distance: number | null } | null;
  classification: {
    id: string;
    status: string;
    kind: string;
    confidence: number;
    reason: string | null;
  } | null;
  extraction: {
    id: string;
    status: string;
    schemaVersion: string;
    overallConfidence: number;
    fields: Array<{
      id: string;
      fieldKey: string;
      rowOrdinal: number;
      rawText: string;
      normalizedValue: string | null;
      confidence: number;
      warnings: string[];
    }>;
  } | null;
  normalization: {
    id: string;
    status: string;
    datasetId: string;
    datasetChecksum: string;
    payload: { fields?: GovernorProgressionMachineField[] };
    warnings: string[];
  } | null;
  review: GovernorProgressionReview | null;
  commit: {
    id: string;
    status: string;
    destinationAction: string;
    destinationReceiptId: string | null;
    destinationReceipt: {
      observation_id?: string;
      receipt_id?: string;
      idempotent_replay?: boolean;
    } | null;
    failureCode: string | null;
  } | null;
};

export type GovernorProgressionFact = {
  value: unknown;
  capturedAt: string;
  observationId: string;
  evidenceId: string;
  reviewId: string;
  datasetId: string;
  datasetChecksum: string;
};

export type GovernorProgressionHeroState = {
  facts: Record<string, GovernorProgressionFact>;
  gear: Record<string, Record<string, GovernorProgressionFact>>;
  membership: GovernorProgressionFact | null;
};

export type GovernorProgressionState = {
  history: Array<{
    id?: string;
    kind?: string;
    capturedAt?: string;
    source?: string;
    evidenceId?: string;
    reviewId?: string;
    datasetId?: string;
    datasetChecksum?: string;
    [key: string]: unknown;
  }>;
  current: {
    profile: Record<string, GovernorProgressionFact>;
    heroes: Record<string, GovernorProgressionHeroState>;
    governorGear: Record<string, Record<string, GovernorProgressionFact>>;
    charms: Record<string, Record<string, GovernorProgressionFact>>;
    completeRosterCapture: GovernorProgressionFact | null;
  };
  last_updated_at: string | null;
};

export type GovernorProgressionEvidenceWorkspace = {
  schemas: GovernorProgressionSchema[];
  evidence: GovernorProgressionEvidenceSummary[];
};
