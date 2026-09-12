export type GovernanceChoice = { id: string; name: string };
export type GovernanceChoiceKind = 'roles' | 'players';
export type GovernanceChoiceResult = {
  page: {
    items: GovernanceChoice[];
    nextCursor: string | null;
    hasMore: boolean;
    pageSize: number;
    isFirstPage: boolean;
  };
  total: number;
  selected: GovernanceChoice | null;
};
export type GovernanceChoiceRequest = {
  scope: string;
  kind: GovernanceChoiceKind;
  search: string;
  cursor: string | null;
  selectedId: string | null;
};
export type GovernanceChoiceView = {
  result: GovernanceChoiceResult | null;
  busy: boolean;
  failed: boolean;
  search: string;
  cursor: string | null;
};

const idPattern = /^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i;
function record(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
function choice(value: unknown): value is GovernanceChoice {
  return (
    record(value) &&
    typeof value.id === 'string' &&
    idPattern.test(value.id) &&
    typeof value.name === 'string' &&
    [...value.name].length <= 263
  );
}

/** Reject malformed responses rather than replacing the last trustworthy page. */
export function parseGovernanceChoices(
  value: unknown,
  selectedId: string | null,
): GovernanceChoiceResult {
  if (!record(value) || !record(value.page)) throw new Error('Invalid Governance choices.');
  const page = value.page;
  if (
    !Array.isArray(page.items) ||
    page.items.length > 25 ||
    !page.items.every(choice) ||
    new Set(page.items.map((row) => row.id)).size !== page.items.length ||
    !(
      page.nextCursor === null ||
      (typeof page.nextCursor === 'string' &&
        page.nextCursor.length > 0 &&
        page.nextCursor.length <= 4096)
    ) ||
    typeof page.hasMore !== 'boolean' ||
    page.hasMore !== (page.nextCursor !== null) ||
    page.pageSize !== 25 ||
    typeof page.isFirstPage !== 'boolean' ||
    (page.hasMore && page.items.length !== 25) ||
    !Number.isSafeInteger(value.total) ||
    (value.total as number) < 0 ||
    !(value.selected === null || (choice(value.selected) && value.selected.id === selectedId))
  ) {
    throw new Error('Invalid Governance choices.');
  }
  return value as unknown as GovernanceChoiceResult;
}

/** Request coordination only: no domain authorization or eligibility is reproduced here. */
export class GovernanceChoiceLoader {
  private generation = 0;
  private active: AbortController | null = null;
  private context = '';
  private attempt: GovernanceChoiceRequest | null = null;
  private view: GovernanceChoiceView = {
    result: null,
    busy: false,
    failed: false,
    search: '',
    cursor: null,
  };

  private readonly fetcher: (url: string, init: RequestInit) => Promise<Response>;
  private readonly changed: (view: GovernanceChoiceView) => void;

  constructor(
    fetcher: (url: string, init: RequestInit) => Promise<Response>,
    changed: (view: GovernanceChoiceView) => void,
  ) {
    this.fetcher = fetcher;
    this.changed = changed;
  }

  reset(context = ''): void {
    this.generation++;
    this.active?.abort();
    this.active = null;
    this.context = context;
    this.attempt = null;
    this.view = { result: null, busy: false, failed: false, search: '', cursor: null };
    this.changed({ ...this.view });
  }

  async retry(): Promise<void> {
    if (this.attempt) await this.load(this.attempt);
  }

  async load(request: GovernanceChoiceRequest): Promise<void> {
    const context = `${request.scope}|${request.kind}`;
    if (context !== this.context) this.reset(context);
    this.active?.abort();
    const active = new AbortController();
    this.active = active;
    const generation = ++this.generation;
    this.attempt = { ...request };
    this.view = { ...this.view, busy: true, failed: false };
    this.changed({ ...this.view });
    const params = new URLSearchParams({ search: request.search });
    if (request.cursor) params.set('cursor', request.cursor);
    if (request.selectedId) params.set('selected_id', request.selectedId);
    try {
      const response = await this.fetcher(
        `/alliance/settings/kingdom/governance/choices/${request.kind}?${params}`,
        {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
          signal: active.signal,
        },
      );
      if (!response.ok) throw new Error('Governance choices could not be loaded.');
      const result = parseGovernanceChoices(await response.json(), request.selectedId);
      if (generation !== this.generation || active.signal.aborted) return;
      this.view = {
        result,
        busy: false,
        failed: false,
        search: request.search,
        cursor: request.cursor,
      };
      this.changed({ ...this.view });
    } catch {
      if (generation !== this.generation || active.signal.aborted) return;
      this.view = { ...this.view, busy: false, failed: true };
      this.changed({ ...this.view });
    }
  }
}
