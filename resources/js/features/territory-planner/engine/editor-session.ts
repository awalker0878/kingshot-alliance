/** One serialized save stream. Uncertain retries replay the exact request, never newer edits. */
export type SaveRequest<Layout> = {
  expected_revision: number;
  mutation_id: string;
  layout: Layout;
};
export type SaveReceipt<Layout> = {
  mutation_id: string;
  revision: number;
  status: string;
  layout_checksum: string;
  layout: Layout;
};
export class TerritoryRequestError extends Error {
  readonly status: number;
  constructor(message: string, status: number) {
    super(message);
    this.status = status;
    this.name = 'TerritoryRequestError';
  }
}
export type SessionOptions<Layout> = {
  revision: number;
  read: () => Layout;
  install: (layout: Layout) => void;
  authority: () => string | null;
  save: (request: SaveRequest<Layout>, signal: AbortSignal) => Promise<SaveReceipt<Layout>>;
  accepted: (receipt: SaveReceipt<Layout>) => void;
  mutationId: () => string;
};

export function createEditorSession<Layout>(options: SessionOptions<Layout>) {
  let revision = options.revision;
  let acknowledged = JSON.stringify(options.read());
  let checksum: string | null = null;
  let pending: SaveRequest<Layout> | null = null;
  let active: Promise<void> | null = null;
  let disposed = false;
  const authority = options.authority();
  const controller = new AbortController();
  const copy = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T;
  const assertCurrent = () => {
    if (disposed || options.authority() !== authority) {
      throw new TerritoryRequestError(
        'The active Governor or plan changed. Reopen this workspace.',
        403,
      );
    }
  };
  const dirty = () => acknowledged !== JSON.stringify(options.read());

  async function drain(): Promise<void> {
    assertCurrent();
    // Bound a continuously edited session; the caller can schedule another flush.
    for (let count = 0; pending !== null || dirty() || checksum === null; count++) {
      if (count >= 20)
        throw new TerritoryRequestError('Further edits remain unsaved. Save again.', 429);
      assertCurrent();
      pending ??= {
        expected_revision: revision,
        mutation_id: options.mutationId(),
        layout: copy(options.read()),
      };
      const request = pending;
      const submitted = JSON.stringify(request.layout);
      let receipt: SaveReceipt<Layout>;
      try {
        receipt = await options.save(copy(request), controller.signal);
      } catch (error) {
        // A definite rejection may be corrected. Network/time-out/5xx outcomes
        // stay pending because the server may already have committed the request.
        if (
          error instanceof TerritoryRequestError &&
          error.status >= 400 &&
          error.status < 500 &&
          ![408, 429].includes(error.status)
        )
          pending = null;
        throw error;
      }
      assertCurrent();
      if (
        !Number.isSafeInteger(receipt.revision) ||
        receipt.revision !== request.expected_revision + 1 ||
        receipt.mutation_id !== request.mutation_id ||
        !/^[a-f0-9]{64}$/.test(receipt.layout_checksum)
      ) {
        throw new TerritoryRequestError(
          'The save receipt does not match the submitted revision.',
          502,
        );
      }
      revision = receipt.revision;
      checksum = receipt.layout_checksum;
      acknowledged = JSON.stringify(receipt.layout);
      pending = null;
      // A reply can normalize the submitted work, but must not replace later edits.
      if (JSON.stringify(options.read()) === submitted) options.install(copy(receipt.layout));
      options.accepted(copy(receipt));
    }
  }

  function flush(): Promise<void> {
    if (active !== null) return active;
    active = drain().finally(() => {
      active = null;
    });
    return active;
  }

  return {
    flush,
    dirty,
    pending: () => active !== null || pending !== null,
    dispose() {
      disposed = true;
      pending = null;
      controller.abort();
    },
    /** Explicit import/restore accepts a server-normalized head; never a partial reload. */
    replace(receipt: SaveReceipt<Layout>) {
      assertCurrent();
      if (active !== null || pending !== null)
        throw new TerritoryRequestError(
          'Finish or reconcile the pending save before replacing this layout.',
          409,
        );
      if (
        !Number.isSafeInteger(receipt.revision) ||
        receipt.revision < revision ||
        !/^[a-f0-9]{64}$/.test(receipt.layout_checksum)
      )
        throw new TerritoryRequestError('Invalid replacement receipt.', 502);
      revision = receipt.revision;
      checksum = receipt.layout_checksum;
      acknowledged = JSON.stringify(receipt.layout);
      options.install(copy(receipt.layout));
      options.accepted(copy(receipt));
    },
    async publication(): Promise<{ expected_revision: number; layout_checksum: string }> {
      await flush();
      assertCurrent();
      if (dirty() || checksum === null)
        throw new TerritoryRequestError('Save the current work before publishing.', 409);
      return { expected_revision: revision, layout_checksum: checksum };
    },
  };
}
