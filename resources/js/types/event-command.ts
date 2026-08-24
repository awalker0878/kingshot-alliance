export type EventCommandStatus =
  | 'complete'
  | 'needs_attention'
  | 'warning'
  | 'unknown'
  | 'not_applicable';

export type EventCommandSeverity = 'blocking' | 'warning' | 'informational';

export type EventCommandState =
  | 'planning'
  | 'needs_attention'
  | 'ready'
  | 'active'
  | 'closeout_required'
  | 'complete';

export type EventCommandItem = {
  code: string;
  phase: 'readiness' | 'closeout';
  status: EventCommandStatus;
  severity: EventCommandSeverity;
  owner: string;
  classification: 'operational_fact' | 'alliance_strategy' | 'evidence' | 'derived';
  count: number | null;
  messageKey: string;
  messageParameters: Record<string, string | number | null>;
  source: Record<string, unknown> | null;
  handoff: { href: string; labelKey: string } | null;
};

export type EventCommandSection = {
  key: string;
  labelKey: string;
  phase: 'readiness' | 'closeout';
  items: EventCommandItem[];
};

export type EventCommandProjection = {
  eventId: string;
  selectedOccurrenceId: string | null;
  occurrences: Array<{
    id: string;
    startsAt: string;
    endsAt: string;
    status: string;
    selected: boolean;
  }>;
  state: EventCommandState | null;
  eventStatus: string;
  occurrenceStatus: string | null;
  startsAt: string | null;
  endsAt: string | null;
  timezone: string;
  blockerCount: number;
  warningCount: number;
  sections: EventCommandSection[];
};
